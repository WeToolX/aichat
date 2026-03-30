<?php

/** 入口安全检查器，用于拦截明显的文件注入与 SQL 注入攻击特征。 */
class Security
{
    protected static $filePatterns = array(
        '/\.\.[\/\\\\]/i',
        '/(?:php|file|data|expect|zip|phar|glob|ftp|http|https):\/\//i',
        '/(?:^|[\/\\\\])\.env(?:$|[\/\\\\])/i',
        '/\/etc\/passwd/i',
        '/[a-z]:\\\\windows\\\\/i',
        '/%00/i',
        '/\x00/',
    );

    protected static $sqlPatterns = array(
        '/\bunion\s+all\s+select\b/i',
        '/\bunion\s+select\b/i',
        '/\bselect\b.{0,40}\bfrom\b.{0,40}\bwhere\b/i',
        '/\binformation_schema\b/i',
        '/\binto\s+outfile\b/i',
        '/\bload_file\s*\(/i',
        '/\bupdatexml\s*\(/i',
        '/\bextractvalue\s*\(/i',
        '/\bsleep\s*\(\s*\d+\s*\)/i',
        '/\bbenchmark\s*\(/i',
        '/(?:\bor\b|\band\b)\s+\d+\s*=\s*\d+/i',
        '/[\'"`]\s*(?:or|and)\s*[\'"`]?\d+[\'"`]?\s*=\s*[\'"`]?\d+/i',
        '/--\s*$/m',
        '/\/\*.*\*\//s',
    );

    /** 在单入口处统一检查请求安全。 */
    public static function inspect(Request $request)
    {
        self::inspectPath($request->uri());
        self::inspectInputBag('query', $request->query());
        self::inspectInputBag('body', $request->all());
        self::inspectHeaders($request->headers());
    }

    /** 检查路径中的文件访问攻击特征。 */
    protected static function inspectPath($uri)
    {
        $value = urldecode((string) $uri);
        self::assertSafeValue($value, 'path');
    }

    /** 检查请求头，跳过常见客户端标识头，避免误伤 Apifox、浏览器等正常请求。 */
    protected static function inspectHeaders(array $headers)
    {
        $skipHeaders = array(
            'authorization',
            'cookie',
            'user-agent',
            'referer',
            'origin',
            'host',
            'connection',
            'content-length',
            'content-type',
            'accept',
            'accept-encoding',
            'accept-language',
            'sec-ch-ua',
            'sec-ch-ua-mobile',
            'sec-ch-ua-platform',
            'sec-fetch-site',
            'sec-fetch-mode',
            'sec-fetch-dest',
            'postman-token',
        );

        foreach ($headers as $name => $value) {
            if (in_array($name, $skipHeaders, true)) {
                continue;
            }
            self::inspectValue($name, $value, 'header');
        }
    }

    /** 递归检查参数集合。 */
    protected static function inspectInputBag($bag, array $data)
    {
        foreach ($data as $key => $value) {
            self::inspectValue((string) $key, $value, $bag);
        }
    }

    /** 检查单个参数值。 */
    protected static function inspectValue($key, $value, $source)
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                self::inspectValue($key . '.' . $childKey, $childValue, $source);
            }
            return;
        }

        if (is_object($value) || $value === null) {
            return;
        }

        $string = urldecode(trim((string) $value));
        if ($string === '') {
            return;
        }

        self::assertSafeValue($string, $source . ':' . $key);
    }

    /** 执行文件注入与 SQL 注入规则匹配。 */
    protected static function assertSafeValue($value, $location)
    {
        foreach (self::$filePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new RuntimeException('请求被拦截：检测到非法文件访问特征 [' . $location . ']', 403);
            }
        }

        foreach (self::$sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new RuntimeException('请求被拦截：检测到非法SQL注入特征 [' . $location . ']', 403);
            }
        }
    }
}
