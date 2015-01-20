<?php

namespace Fahmiardi\OAuth2\Server\Storage\Redis;

use Fahmiardi\OAuth2\Server\Storage\Util\RedisCapsule;
use Fahmiardi\OAuth2\Server\Storage\Util\RedisUtil;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Storage\AbstractStorage;
use League\OAuth2\Server\Storage\ClientInterface;

class RedisClient extends AbstractStorage implements ClientInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($clientId, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        $key = RedisUtil::instance()->prefix($clientId, 'oauth_clients');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::instance()->unserialize($value);
        }

        // If a secret and redirection URI were given then we must correctly
        // validate the client by comparing its ID, secret, and that
        // the supplied redirection URI was registered.
        if (! is_null($clientSecret) && ! is_null($redirectUri)) {
            if ($clientSecret != $result['secret'] || $redirectUri != $result['redirect_uri']) {
                return;
            }

        // If only the clients secret is given then we must correctly validate
        // the client by comparing its ID and secret.
        } elseif (! is_null($clientSecret) && is_null($redirectUri)) {
            if ($clientSecret != $result['secret']) {
                return;
            }

        // If only the clients redirection URI is given then we must correctly
        // validate the client by comparing the redirection URI.
        } elseif (is_null($clientSecret) && ! is_null($redirectUri)) {
            if ($redirectUri != $result['redirect_uri']) {
                return;
            }
        }

        return (new ClientEntity($this->server))->hydrate([
            'id'            => $result['id'],
            'secret'        => $result['secret'],
            'name'          => $result['name'],
            'redirect_uri'  => $result['redirect_uri']
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBySession(SessionEntity $session)
    {
        $key = RedisUtil::instance()->prefix($session->getId(), 'oauth_sessions');

        if (isset($this->cache[$key])) {
            $result = $this->cache[$key];
        } else {

            if (! $value = RedisCapsule::get($key)) {
                return;
            }

            $result = $this->cache[$key] = RedisUtil::instance()->unserialize($value);
        }

        return $this->get($result['client_id']);
    }
}