<?php

namespace Fahmiardi\OAuth2\Server\Storage\Redis;

use Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule;
use Fahmiardi\OAuth2\Server\Storage\Util\RedisUtil;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\AccessTokenInterface;

class RedisAccessToken extends AbstractStorage implements AccessTokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($token)
    {
        $key = RedisUtil::instance()->prefix($token, 'oauth_access_tokens');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::instance()->unserialize($value);
        }

        return (new AccessTokenEntity($this->server))
            ->setId($result['id'])
            ->setExpireTime($result['expire_time']);
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(AccessTokenEntity $token)
    {
        $key = RedisUtil::instance()->prefix($token->getId(), 'oauth_access_token_scopes');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {
            $result = $this->cache[$key] = RedisUtil::instance()->map(RedisCapsule::smembers($key));
        }

        $response = [];

        foreach ($result as $row) {
            $key = RedisUtil::instance()->prefix($row['id'], 'oauth_scopes');

            if (isset($this->cache[$key])) {
                $scope = $this->cache[$key];
            } else {
                if (! $value = RedisCapsule::get($key)) {
                    continue;
                }

                $scope = $this->cache[$key] = RedisUtil::instance()->unserialize($value);
            }

            $response[] = (new ScopeEntity($this->server))->hydrate([
                'id'            => $scope['id'],
                'description'   => $scope['description']
            ]);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $sessionId)
    {
        $payload = [
            'id'          => $token,
            'expire_time' => $expireTime,
            'session_id'  => $sessionId
        ];

        $key = RedisUtil::instance()->prefix($token, 'oauth_access_tokens');
        $this->cache[$key] = $payload;
        RedisCapsule::set($key, RedisUtil::instance()->prepare($payload));

        $key = RedisUtil::instance()->prefix(null, 'oauth_access_tokens');

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        array_push($this->cache[$key], $token);

        RedisCapsule::sadd($key, RedisUtil::instance()->prepare($token));
    }

    /**
     * {@inheritdoc}
     */
    public function associateScope(AccessTokenEntity $token, ScopeEntity $scope)
    {
        $key = RedisUtil::instance()->prefix($token->getId(), 'oauth_access_token_scopes');

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        $value = ['id' => $scope->getId()];

        array_push($this->cache[$key], $value);

        RedisCapsule::sadd($key, RedisUtil::instance()->prepare($value));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AccessTokenEntity $token)
    {
        // Deletes the access token entry.
        $key = RedisUtil::instance()->prefix($token->getId(), 'oauth_access_tokens');

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        RedisCapsule::del($key);

        // Deletes the access token entry from the access tokens set.
        $key = RedisUtil::instance()->prefix(null, 'oauth_access_tokens');

        if (isset($this->cache[$key]) && ($cacheKey = array_search($token->getId(), $this->cache[$key])) !== false) {
            unset($this->cache[$key][$cacheKey]);
        }

        RedisCapsule::srem($key, $token->getId());

        // Deletes the access tokens associated scopes.
        $key = RedisUtil::instance()->prefix($token->getId(), 'oauth_access_token_scopes');

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        RedisCapsule::del($key);
    }
}