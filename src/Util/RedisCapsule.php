<?php

namespace Fahmiardi\OAuth2\Server\Storage\Util;

use Illuminate\Redis\Database as RedisAdapter;

class RedisCapsule extends RedisAdapter
{
	/**
	 * The current globally used instance.
	 *
	 * @var \Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule
	 */
	protected static $instance;
	
	/**
     * {@inheritdoc}
     */
	public function __construct(array $server)
	{
		parent::__construct($server);
	}

	/**
     * {@inheritdoc}
     */
	public function setAsGlobal()
	{
		static::$instance = $this;
	}

	/**
     * {@inheritdoc}
     */
	public static function getConnection($name = null)
	{
		return static::$instance->connection($name);
	}

	/**
     * Dynamically pass redis command
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
	public static function __callStatic($method, $parameters)
	{
		return static::$instance->command($method, $parameters);
	}
}