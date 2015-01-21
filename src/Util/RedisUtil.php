<?php

namespace Fahmiardi\OAuth2\Server\Storage\Util;

class RedisUtil
{
    /**
     * {@inheritdoc}
     */
    public static function prefix($key, $table)
    {
        $table = str_replace('_', ':', $table);

        return trim("{$table}:{$key}", ':');
    }

    /**
     * {@inheritdoc}
     */
    public static function prepare($value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public static function map($value)
    {
        return array_map(function ($item) {
            if (is_string($item) && $decoded = json_decode($item, true)) {
                return $decoded;
            }

            return $item;
        }, $value);
    }

    /**
     * {@inheritdoc}
     */
    public static function unserialize($value)
    {
        return (is_string($value) && $decoded = json_decode($value, true)) ? $decoded : $value;
    }
}