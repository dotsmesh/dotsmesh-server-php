<?php

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

    protected function hasSessionKey(): bool
    {
        return $this->hasOption('sessionKey');
    }

    protected function requireValidSessionKey(string $userID): array
    {
        $app = App::get();
        $sessionKey = $this->getOption('sessionKey', ['notEmptyString']);
        $sessionKeyHash = Utilities::getHash('SHA-512', $sessionKey);
        $dataPrefix = $this->getDataPrefix($userID);
        $sessionDataKey = $dataPrefix . 'e/' . md5($sessionKeyHash);
        $sessionData = $app->data->getValue($sessionDataKey);
        if ($sessionData !== null) {
            $sessionData = json_decode($sessionData, true);
            if ($sessionData['s'] === $sessionKeyHash) {
                return [
                    'dataKey' => $sessionDataKey,
                    'sessionData' => $sessionData['d']
                ];
            }
        }
        throw new EndpointError('invalidSessionKey', 'The session key provided is not valid!');
    }

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

    protected function generateSessionKey(): string
    {
        return Utilities::generateRandomBase36String(rand(50, 80));
    }

    // protected function validateAppID(string $appID): void
    // {
    //     if (!Utilities::isAlphanumeric($appID, 80)) {
    //         throw new EndpointError('invalidAppID', 'The appID provided is not valid!'); // todo check real apps
    //     }
    // }

    protected function setAuthData(string $userID, string $key, $data)
    {
        $app = App::get();
        $authKeyHash = Utilities::getHash('SHA-512', $key);
        $dataPrefix = $this->getDataPrefix($userID);
        $app->data->setValue($dataPrefix . 'a/' . md5($authKeyHash), json_encode([
            'k' => $authKeyHash,
            'd' => $data
        ]));
    }

    protected function getAuthData(string $userID, string $key)
    {
        $authKeyHash = Utilities::getHash('SHA-512', $key);
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($userID);
        $dataKey = $dataPrefix . 'a/' . md5($authKeyHash);
        $authData = $app->data->getValue($dataKey);
        if ($authData !== null) {
            $authData = json_decode($authData, true);
            if (isset($authData['k']) && $authData['k'] === $authKeyHash) {
                return [
                    'data' => $authData['d'],
                    'dataKey' => $dataKey
                ];
            }
        }
        return null;
    }
}
