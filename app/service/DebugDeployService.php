<?php

/** 调试部署服务，仅在调试模式下执行固定的拉码命令。 */
class DebugDeployService
{
    /** 返回接口可用性与当前部署配置概览。 */
    public function status(Request $request)
    {
        $config = $this->config();
        $this->assertAllowed($request, $config);

        return array(
            'debug' => (bool) ($config['app']['debug'] ?? false),
            'enabled' => (bool) ($config['deploy']['debug_allow_pull'] ?? false),
            'workdir' => $this->workdir($config),
            'force_sync' => (bool) ($config['deploy']['force_sync'] ?? false),
            'remote' => (string) ($config['deploy']['remote'] ?? 'origin'),
            'pull_command' => $config['deploy']['pull_command'] ?? '',
            'ip' => $request->ip(),
        );
    }

    /** 执行固定的拉码命令。 */
    public function pull(Request $request)
    {
        $config = $this->config();
        $this->assertAllowed($request, $config);

        $workdir = $this->workdir($config);
        if (!is_dir($workdir)) {
            throw new RuntimeException('部署目录不存在：' . $workdir, 500);
        }

        $before = $this->runGitInfo('git rev-parse --short HEAD', $workdir);
        $branch = $this->runGitInfo('git branch --show-current', $workdir);
        $result = $this->deploy($config, $workdir, $branch);
        $after = $this->runGitInfo('git rev-parse --short HEAD', $workdir);

        $payload = array(
            'workdir' => $workdir,
            'branch' => $branch,
            'before_revision' => $before,
            'after_revision' => $after,
            'changed' => $before !== '' && $after !== '' ? $before !== $after : null,
            'command' => $result['command'],
            'exit_code' => $result['exit_code'],
            'output' => $result['output'],
        );

        $this->appendLog($request, $payload);
        return $payload;
    }

    /** 拉取配置。 */
    protected function config()
    {
        return app('config', array());
    }

    /** 计算实际执行目录。 */
    protected function workdir(array $config)
    {
        $workdir = trim((string) ($config['deploy']['workdir'] ?? ''));
        return $workdir === '' ? BASE_PATH : $workdir;
    }

    /** 执行部署动作。 */
    protected function deploy(array $config, $workdir, $branch)
    {
        if (!empty($config['deploy']['force_sync'])) {
            return $this->forceSync($config, $workdir, $branch);
        }

        $command = trim((string) ($config['deploy']['pull_command'] ?? ''));
        $this->assertSafeCommand($command);
        $result = $this->runCommand($command, $workdir);
        $result['command'] = $command;
        return $result;
    }

    /** 校验当前请求是否允许访问。 */
    protected function assertAllowed(Request $request, array $config)
    {
        if (empty($config['app']['debug'])) {
            throw new RuntimeException('仅调试模式允许访问此接口', 403);
        }

        if (empty($config['deploy']['debug_allow_pull'])) {
            throw new RuntimeException('调试部署接口未开启', 403);
        }

        $token = trim((string) ($config['deploy']['token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('未配置部署密钥', 500);
        }

        $provided = $this->extractToken($request);
        if ($provided === '' || !hash_equals($token, $provided)) {
            throw new RuntimeException('部署密钥无效', 401);
        }

        $allowedIps = $config['deploy']['allowed_ips'] ?? array();
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            throw new RuntimeException('当前来源 IP 未被允许', 403);
        }
    }

    /** 从请求中提取部署密钥。 */
    protected function extractToken(Request $request)
    {
        $authorization = trim((string) $request->header('authorization', ''));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        $headers = array('x-deploy-token', 'deploy-token');

        foreach ($headers as $header) {
            $value = trim((string) $request->header($header, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** 校验命令格式，避免变成任意远程执行入口。 */
    protected function assertSafeCommand($command)
    {
        if ($command === '') {
            throw new RuntimeException('部署命令不能为空', 500);
        }

        if (preg_match('/[;&|><`\\r\\n]/', $command)) {
            throw new RuntimeException('部署命令包含不安全字符', 500);
        }

        if (strpos($command, 'git ') !== 0) {
            throw new RuntimeException('部署命令仅允许 git 开头的固定命令', 500);
        }
    }

    /** 强制同步远端分支，覆盖服务器上已跟踪文件的本地修改。 */
    protected function forceSync(array $config, $workdir, $branch)
    {
        $remote = trim((string) ($config['deploy']['remote'] ?? 'origin'));
        if ($remote === '') {
            throw new RuntimeException('部署远端不能为空', 500);
        }

        if ($branch === '' || $branch === 'HEAD') {
            throw new RuntimeException('无法识别当前分支，不能执行强制同步', 500);
        }

        $fetchCommand = 'git fetch ' . escapeshellarg($remote) . ' --prune';
        $resetCommand = 'git reset --hard ' . escapeshellarg($remote . '/' . $branch);

        $fetchResult = $this->runCommand($fetchCommand, $workdir);
        $resetResult = $this->runCommand($resetCommand, $workdir);

        return array(
            'command' => $fetchCommand . ' && ' . $resetCommand,
            'exit_code' => 0,
            'output' => trim($fetchResult['output'] . ($fetchResult['output'] !== '' && $resetResult['output'] !== '' ? PHP_EOL : '') . $resetResult['output']),
        );
    }

    /** 执行 git 查询类命令，失败时只返回空字符串。 */
    protected function runGitInfo($command, $workdir)
    {
        try {
            $result = $this->runCommand($command, $workdir);
            return trim($result['output']);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** 执行固定命令并收集输出。 */
    protected function runCommand($command, $workdir)
    {
        if (function_exists('proc_open')) {
            return $this->runWithProcOpen($command, $workdir);
        }

        if (function_exists('exec')) {
            return $this->runWithExec($command, $workdir);
        }

        throw new RuntimeException('服务器未开放 proc_open/exec，无法执行部署命令', 500);
    }

    /** 通过 proc_open 执行命令。 */
    protected function runWithProcOpen($command, $workdir)
    {
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = @proc_open(array('/bin/sh', '-lc', $command), $descriptors, $pipes, $workdir);
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动部署进程', 500);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $output = trim($stdout . ($stderr !== '' ? PHP_EOL . $stderr : ''));
        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : '部署命令执行失败', 500);
        }

        return array(
            'exit_code' => (int) $exitCode,
            'output' => $output,
        );
    }

    /** 通过 exec 执行命令。 */
    protected function runWithExec($command, $workdir)
    {
        $output = array();
        $exitCode = 0;
        $shell = 'cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1';
        exec($shell, $output, $exitCode);

        $text = trim(implode(PHP_EOL, $output));
        if ($exitCode !== 0) {
            throw new RuntimeException($text !== '' ? $text : '部署命令执行失败', 500);
        }

        return array(
            'exit_code' => (int) $exitCode,
            'output' => $text,
        );
    }

    /** 记录部署日志。 */
    protected function appendLog(Request $request, array $payload)
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $entry = '[' . date('Y-m-d H:i:s') . '] '
            . 'ip=' . $request->ip()
            . ' branch=' . ($payload['branch'] !== '' ? $payload['branch'] : '-')
            . ' before=' . ($payload['before_revision'] !== '' ? $payload['before_revision'] : '-')
            . ' after=' . ($payload['after_revision'] !== '' ? $payload['after_revision'] : '-')
            . ' changed=' . json_encode($payload['changed'])
            . ' command=' . $payload['command']
            . PHP_EOL
            . ($payload['output'] !== '' ? $payload['output'] . PHP_EOL : '');

        @file_put_contents($dir . '/deploy.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
