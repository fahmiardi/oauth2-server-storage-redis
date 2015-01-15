<?php

namespace Fahmiardi\OAuth2\Server\Storage\Redis;

use Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule;
use Fahmiardi\OAuth2\Server\Storage\Util\RedisUtil;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\AuthCodeInterface;

class RedisAuthCode extends AbstractStorage implements AuthCodeInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($code)
    {
        $key = RedisUtil::prefix($code, 'oauth_auth_codes');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::unserialize($value);
        }

        if ($result['expire_time'] >= time()) {
            return;
        }

        return (new AuthCodeEntity($this->server))
            ->setId($result['id'])
            ->setRedirectUri($result['client_redirect_uri'])
            ->setExpireTime($result['expire_time']);
    }

    /**
     * {@inheritdoc}
     */
    public function create($token, $expireTime, $sessionId, $redirectUri)
    {
        $payload = [
            'id'                    => $token,
            'expire_time'           => $expireTime,
            'session_id'            => $sessionId,
            'client_redirect_uri'   => $redirectUri
        ];

        $key = RedisUtil::prefix($token, 'oauth_auth_codes');
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
    public function getScopes(AuthCodeEntity $token)
    {
        $key = RedisUtil::prefix($token->getId(), 'oauth_auth_code_scopes');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {
            $result = $this->cache[$key] = RedisUtil::map(RedisCapsule::smembers($key));
        }

        $response = [];

        foreach ($result as $row) {
            $key = RedisUtil::prefix($row['id'], 'oauth_scopes');

            if (isset($this->cache[$key])) {
                $scope = $this->cache[$key];
            } else {
                if (! $value = RedisCapsule::get($key)) {
                    continue;
                }

                $scope = $this->cache[$key] = RedisUtil::unserialize($value);
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
    public function associateScope(AuthCodeEntity $token, ScopeEntity $scope)
    {
        $key = RedisUtil::prefix($token->getId(), 'oauth_auth_code_scopes');

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        $value = ['id' => $scope->getId()];

        array_push($this->cache[$key], $value);

        RedisCapsule::sadd($key, RedisUtil::prepare($value));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AuthCodeEntity $token)
    {
        // // Deletes the authorization code entry.
        $key = RedisUtil::prefix($token->getId(), 'oauth_auth_codes');

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        RedisCapsule::del($key);

        // // Deletes the authorization code entry from the authorization codes set.
        $key = RedisUtil::prefix(null, 'oauth_auth_codes');

        if (isset($this->cache[$key]) && ($cacheKey = array_search($token->getId(), $this->cache[$key])) !== false) {
            unset($this->cache[$key][$cacheKey]);
        }

        RedisCapsule::srem($key, $token->getId());

        // // Deletes the authorization codes associated scopes.
        $key = RedisUtil::prefix($token->getId(), 'oauth_auth_code_scopes');

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        RedisCapsule::del($key);
    }
}