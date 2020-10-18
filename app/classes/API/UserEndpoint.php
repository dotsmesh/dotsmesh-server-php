<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API;

use BearFramework\App;
use X\Utilities;

class UserEndpoint extends Endpoint
{

    /**
     * 
     * @param boolean $mustExist
     * @return string
     */
    protected function requireValidUserID(bool $mustExist = true): string
    {
        $id = $this->getOption('propertyID', ['notEmptyString']);
        if (!Utilities::isPropertyID($id)) {
            throw new EndpointError('invalidUserID', 'The user ID specified is not valid!');
        }
        if ($mustExist) {
            if (!Utilities::userExists($id)) {
                throw new EndpointError('userNotFound', 'The user specified does not exists!');
            }
        }
        return $id;
    }

    /**
     * 
     * @return boolean
     */
    protected function hasSessionKey(): bool
    {
        return $this->hasOption('sessionKey');
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionKey
     * @return string
     */
    protected function getSessionDataKey(string $userID, string $sessionKey): string
    {
        $dataPrefix = $this->getDataPrefix($userID);
        return $dataPrefix . 'e/' . md5($sessionKey);
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionKey
     * @return array|null
     */
    protected function getSessionData(string $userID, string $sessionKey): ?array
    {
        $app = App::get();
        $sessionDataKey = $this->getSessionDataKey($userID, $sessionKey);
        $sessionData = $app->data->getValue($sessionDataKey);
        if ($sessionData !== null) {
            $sessionData = Utilities::parseUserSessionData($sessionData);
            if (is_array($sessionData) && isset($sessionData['s']) && $sessionData['s'] === $sessionKey) {
                return $sessionData;
            }
        }
        return null;
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionKey
     * @param array $data
     * @return void
     */
    protected function setSessionData(string $userID, string $sessionKey, array $data): void
    {
        $app = App::get();
        $sessionDataKey = $this->getSessionDataKey($userID, $sessionKey);
        $app->data->setValue($sessionDataKey, Utilities::pack('q', $data));
    }

    /**
     * 
     * @param string $userID
     * @param string $sessionKey
     * @return void
     */
    protected function deleteSessionData(string $userID, string $sessionKey): void
    {
        $app = App::get();
        $sessionDataKey = $this->getSessionDataKey($userID, $sessionKey);
        $app->data->delete($sessionDataKey);
    }

    /**
     * 
     * @param string $userID
     * @return array
     */
    protected function requireValidSessionKey(string $userID): array
    {
        $sessionKey = Utilities::getHash('SHA-512', $this->getOption('sessionKey', ['notEmptyString']));
        $data = $this->getSessionData($userID, $sessionKey);
        if ($data !== null) {
            return [
                'key' => $sessionKey,
                'sessionData' => isset($data['d']) ? $data['d'] : '',
                'pushData' => isset($data['p']) ? $data['p'] : null,
            ];
        }
        throw new EndpointError('invalidSessionKey', 'The session key provided is not valid!');
    }

    /**
     * 
     * @param string $userID
     * @return string
     */
    protected function requireValidAccessKey(string $userID): string
    {
        $app = App::get();
        $accessKey = $this->getOption('accessKey', ['notEmptyString']);
        $accessKeyHash = Utilities::getHash('SHA-512', $accessKey);
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($userID);
        $data = $app->data->getValue($dataPrefix . 'd/p/f/h');
        $firewall = [];
        if ($data !== null) {
            $data = Utilities::unpack($data);
            if ($data['name'] === '') {
                $firewall = $data['value'];
            } else {
                // not supported format
            }
        }
        if (array_search($accessKeyHash, $firewall) !== false) {
            return $accessKeyHash;
        }
        throw new EndpointError('invalidAccessKey', 'The access key provided is not valid!');
    }

    /**
     * 
     * @param string $data
     * @return array|null
     */
    protected static function parseAuthData(string $data): ?array
    {
        if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
            return json_decode($data, true);
        } else {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'r') {
                return $data['value'];
            }
        }
        return null;
    }

    /**
     * 
     * @param string $userID
     * @param string $key
     * @param mixed $data
     * @return void
     */
    protected function setAuthData(string $userID, string $key, $data)
    {
        $app = App::get();
        $authKeyHash = Utilities::getHash('SHA-512', $key);
        $dataPrefix = $this->getDataPrefix($userID);
        $app->data->setValue($dataPrefix . 'a/' . md5($authKeyHash), Utilities::pack('r', [
            'k' => $authKeyHash,
            'd' => $data
        ]));
    }

    /**
     * 
     * @param string $userID
     * @param string $key
     * @return array|null
     */
    protected function getAuthData(string $userID, string $key): ?array
    {
        $authKeyHash = Utilities::getHash('SHA-512', $key);
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($userID);
        $dataKey = $dataPrefix . 'a/' . md5($authKeyHash);
        $authData = $app->data->getValue($dataKey);
        if ($authData !== null) {
            $authData = self::parseAuthData($authData);
            if (is_array($authData) && isset($authData['k']) && $authData['k'] === $authKeyHash) {
                return [
                    'data' => $authData['d'],
                    'dataKey' => $dataKey
                ];
            }
        }
        return null;
    }
}
