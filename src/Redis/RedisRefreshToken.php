<?php

namespace Fahmiardi\OAuth2\Server\Storage\Redis;

use Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule;
use Fahmiardi\OAuth2\Server\Storage\Util\RedisUtil;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\RefreshTokenInterface;

class RedisRefreshToken extends AbstractStorage implements RefreshTokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($token)
    {
        $key = RedisUtil::prefix($token, 'oauth_refresh_tokens');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::unserialize($value);
        }

        return (new RefreshTokenEntity($this->server))
            ->setId($result['id'])
            ->setExpireTime($result['expire_time'])
            ->setAccessTokenId($result['access_token_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $accessToken)
    {
        $payload = [
            'id'              => $token,
            'expire_time'     => $expireTime,
            'access_token_id' => $accessToken
        ];

        $key = RedisUtil::prefix($token, 'oauth_refresh_tokens');
        $this->cache[$key] = $payload;
        RedisCapsule::set($key, RedisUtil::prepare($payload));

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        array_push($this->cache[$key], $token);

        RedisCapsule::sadd(null, RedisUtil::prepare($token));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(RefreshTokenEntity $token)
    {
        // Deletes the access token entry.
        $key = RedisUtil::prefix($token->getId(), 'oauth_refresh_tokens');

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        RedisCapsule::del($key);

        // Deletes the access token entry from the access tokens set.
        $key = RedisUtil::prefix(null, 'oauth_refresh_tokens');

        if (isset($this->cache[$key]) && ($cacheKey = array_search($token->getId(), $this->cache[$key])) !== false) {
            unset($this->cache[$key][$cacheKey]);
        }

        RedisCapsule::srem($key, $token->getId());
    }
}