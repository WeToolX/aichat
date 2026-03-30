<?php

/** 模型基类，提供通用的条件查询、统计、存在性判断与写入能力。 */
abstract class BaseModel
{
    protected static $table = '';
    protected static $primaryKey = 'id';

    /** 获取数据库实例。 */
    public static function db()
    {
        return App::db();
    }

    /** 返回当前模型对应的数据表。 */
    public static function table()
    {
        return static::$table;
    }

    /** 按主键查询单条记录。 */
    public static function find($id)
    {
        return static::findOneBy(array(static::$primaryKey => $id));
    }

    /** 查询当前表全部记录。 */
    public static function all()
    {
        return static::queryAll('SELECT * FROM ' . static::table());
    }

    /** 新建记录。 */
    public static function create(array $data)
    {
        return static::db()->insert(static::table(), $data);
    }

    /** 按条件更新记录。 */
    public static function updateBy(array $where, array $data)
    {
        return static::db()->update(static::table(), $data, $where);
    }

    /** 通用单条条件查询。 */
    public static function findOneBy(array $where, $columns = '*', $orderBy = '', $limit = 1)
    {
        $sql = 'SELECT ' . $columns . ' FROM ' . static::table();
        $params = array();
        $sql .= static::buildWhereClause($where, $params);

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return static::queryOne($sql, $params);
    }

    /** 通用多条条件查询。 */
    public static function findAllBy(array $where = array(), $columns = '*', $orderBy = '', $limit = null)
    {
        $sql = 'SELECT ' . $columns . ' FROM ' . static::table();
        $params = array();
        $sql .= static::buildWhereClause($where, $params);

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return static::queryAll($sql, $params);
    }

    /** 按条件统计数量。 */
    public static function countBy(array $where = array())
    {
        $sql = 'SELECT COUNT(*) AS total FROM ' . static::table();
        $params = array();
        $sql .= static::buildWhereClause($where, $params);
        $result = static::queryOne($sql, $params);

        return (int) ($result['total'] ?? 0);
    }

    /** 按条件判断记录是否存在。 */
    public static function existsBy(array $where = array())
    {
        return static::countBy($where) > 0;
    }

    /** 执行单条查询。 */
    public static function queryOne($sql, array $params = array())
    {
        return static::db()->fetchOne($sql, $params);
    }

    /** 执行多条查询。 */
    public static function queryAll($sql, array $params = array())
    {
        return static::db()->fetchAll($sql, $params);
    }

    /** 将数组条件转换成 SQL where 片段。 */
    protected static function buildWhereClause(array $where, array &$params)
    {
        if (empty($where)) {
            return '';
        }

        $clauses = array();
        $index = 0;
        foreach ($where as $column => $value) {
            $parts = preg_split('/\s+/', trim($column));
            $field = $parts[0];
            $operator = strtoupper($parts[1] ?? '=');
            $base = ':w_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $field) . '_' . $index;

            // 支持 IN / NOT IN 这类数组条件。
            if (($operator === 'IN' || $operator === 'NOT IN') && is_array($value)) {
                if (empty($value)) {
                    $clauses[] = $operator === 'IN' ? '1 = 0' : '1 = 1';
                } else {
                    $placeholders = array();
                    foreach (array_values($value) as $valueIndex => $item) {
                        $placeholder = $base . '_' . $valueIndex;
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = $item;
                    }
                    $clauses[] = $field . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';
                }
            } else {
                $clauses[] = $field . ' ' . $operator . ' ' . $base;
                $params[$base] = $value;
            }

            $index++;
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }
}
