<?php

namespace Fahmiardi\OAuth2\Server\Storage\Util;

class RedisUtil
{
    /**
     * {@inheritdoc}
     */
    protected static $instance;

    /**
     * {@inheritdoc}
     */
    public function instance()
    {
        static::$instance = $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prefix($key, $table)
    {
        $table = str_replace('_', ':', $table);

        return trim("{$table}:{$key}", ':');
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function map($value)
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
    public function unserialize($value)
    {
        return (is_string($value) && $decoded = json_decode($value, true)) ? $decoded : $value;
    }

    /**
     * Dynamically pass methods
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return call_user_func_array($method, $parameters);
    }
}