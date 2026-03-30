<?php

/** 参数校验器，提供必填、数组、非空与正整数等基础校验规则。 */
class Validator
{
    /** 校验多组必填字段。 */
    public static function requireFields(array $data, array $rules)
    {
        foreach ($rules as $field => $message) {
            $value = array_key_exists($field, $data) ? $data[$field] : null;
            if (self::isEmpty($value)) {
                throw new RuntimeException($message, 400);
            }
        }
    }

    /** 校验字段必须为非空数组。 */
    public static function requireArray(array $data, $field, $message)
    {
        if (!isset($data[$field]) || !is_array($data[$field]) || empty($data[$field])) {
            throw new RuntimeException($message, 400);
        }
    }

    /** 校验值不能为空。 */
    public static function requireValue($value, $message)
    {
        if (self::isEmpty($value)) {
            throw new RuntimeException($message, 400);
        }
    }

    /** 校验值为正整数。 */
    public static function requirePositiveInt($value, $message)
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw new RuntimeException($message, 400);
        }
    }

    /** 统一空值判断。 */
    protected static function isEmpty($value)
    {
        if (is_array($value)) {
            return empty($value);
        }

        return $value === null || $value === '';
    }
}
