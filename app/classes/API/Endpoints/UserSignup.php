<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\EndpointError;
use X\API\UserEndpoint;
use X\Utilities;

class UserSignup extends UserEndpoint
{
    public function run(): array
    {
        $app = App::get();

        $userID = $this->getArgument('id', ['notEmptyString']);
        if (!Utilities::isPropertyID($userID)) {
            throw new EndpointError('invalidUserID', 'The user ID specified is not valid!');
        }
        $profileKey = $this->getArgument('profileKey', ['notEmptyString']);
        $authKey = $this->getArgument('authKey', ['notEmptyString']);
        $authData = $this->getArgument('authData', ['notEmptyString']);
        $publicKeys = $this->getArgument('publicKeys', ['notEmptyString']);

        if (!Utilities::validatePropertyKey($profileKey, 'u')) {
            throw new EndpointError('invalidProfileKey', 'invalidProfileKey');
        }

        if (Utilities::propertyExists($userID)) {
            throw new EndpointError('idTaken', 'todo');
        }
        $resultCode = Utilities::createProperty($userID, 'u', $profileKey);
        if ($resultCode === 1) {
            $this->setAuthData($userID, $authKey, $authData);
            $dataPrefix = $this->getDataPrefix($userID);
            $app->data->setValue($dataPrefix . 'd/s/keys', $publicKeys);
            return ['status' => 'ok'];
        } elseif ($resultCode === 2) {
            throw new EndpointError('idTaken', 'todo');
        }
        throw new EndpointError('tryAgain', 'todo');
    }
}
