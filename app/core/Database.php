<?php

/** 数据库访问层，统一封装 PDO 查询、插入、更新与简单缓存。 */
class Database
{
    protected static $instance;
    protected $pdo;
    protected $cache = array();

    /** 初始化 PDO 连接。 */
    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['username'], $config['password'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
    }

    /** 获取数据库单例。 */
    public static function getInstance(?array $config = null)
    {
        if (!static::$instance) {
            if ($config === null) {
                $config = app('config')['database'];
            }
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /** 返回底层 PDO 连接。 */
    public function getConn()
    {
        return $this->pdo;
    }

    /** 执行预处理 SQL。 */
    public function query($sql, array $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** 查询单条记录。 */
    public function fetchOne($sql, array $params = array())
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /** 查询多条记录。 */
    public function fetchAll($sql, array $params = array())
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /** 插入数据并返回新主键。 */
    public function insert($table, array $data)
    {
        $columns = array_keys($data);
        $placeholders = array();
        $params = array();

        foreach ($columns as $column) {
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $data[$column];
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /** 按条件更新数据。 */
    public function update($table, array $data, array $where)
    {
        $set = array();
        $conditions = array();
        $params = array();

        foreach ($data as $column => $value) {
            $key = ':set_' . $column;
            $set[] = $column . ' = ' . $key;
            $params[$key] = $value;
        }

        foreach ($where as $column => $value) {
            $key = ':where_' . $column;
            $conditions[] = $column . ' = ' . $key;
            $params[$key] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set),
            implode(' AND ', $conditions)
        );

        return $this->query($sql, $params)->rowCount();
    }

    /** 执行带内存缓存的查询。 */
    public function cachedQuery($sql, array $params = array(), $ttl = 300)
    {
        $key = md5($sql . serialize($params));
        $now = time();

        if (isset($this->cache[$key]) && $this->cache[$key]['expires_at'] > $now) {
            return $this->cache[$key]['value'];
        }

        $value = $this->fetchAll($sql, $params);
        $this->cache[$key] = array(
            'value' => $value,
            'expires_at' => $now + (int) $ttl,
        );

        return $value;
    }
}
