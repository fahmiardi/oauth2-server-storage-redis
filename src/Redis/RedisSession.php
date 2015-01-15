<?php

namespace Fahmiardi\OAuth2\Server\Storage\Redis;

use Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule;
use Fahmiardi\OAuth2\Server\Storage\Util\RedisUtil;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\SessionInterface;

class RedisSession extends AbstractStorage implements SessionInterface
{
    /**
     * {@inheritdoc}
     */
    private function getSession($sessionId)
    {
        $key = RedisUtil::prefix($sessionId, 'oauth_sessions');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::unserialize($value);
        }

        return (new SessionEntity($this->server))
            ->setId($result['id'])
            ->setOwner($result['owner_type'], $result['owner_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getByAccessToken(AccessTokenEntity $accessToken)
    {
        $key = RedisUtil::prefix($accessToken->getId(), 'oauth_access_tokens');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::unserialize($value);
        }

        return $this->getSession($result['session_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getByAuthCode(AuthCodeEntity $authCode)
    {
        $key = RedisUtil::prefix($authCode->getId(), 'oauth_auth_codes');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::unserialize($value);
        }

        return $this->getSession($result['session_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getScopes(SessionEntity $session)
    {
        $key = RedisUtil::prefix($session->getId(), 'oauth_session_scopes');

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
    public function create($ownerType, $ownerId, $clientId, $clientRedirectUri = null)
    {
        $key = RedisUtil::prefix(null, 'oauth_session_ids');
        $sessionId = RedisCapsule::incr($key);
        $key = RedisUtil::prefix($sessionId, 'oauth_sessions');
        $value = [
            'id'         => $sessionId,
            'client_id'  => $clientId,
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId
        ];
        $this->cache[$key] = $value;

        RedisCapsule::set($key, RedisUtil::prepare($value));

        return $sessionId;
    }

    /**
     * {@inheritdoc}
     */
    public function associateScope(SessionEntity $session, ScopeEntity $scope)
    {
        $key = RedisUtil::prefix($session->getId(), 'oauth_session_scopes');

        if (! isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        $value = ['id' => $scope->getId()];

        array_push($this->cache[$key], $value);

        RedisCapsule::sadd($key, RedisUtil::prepare($value));
    }
}