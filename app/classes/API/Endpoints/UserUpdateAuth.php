<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\UserEndpoint;

class UserUpdateAuth extends UserEndpoint
{
    public function run()
    {
        $app = App::get();

        $userID = $this->requireValidUserID();
        $this->requireValidSessionKey($userID);

        $oldAuthKey = $this->getArgument('oldAuthKey', ['notEmptyString']);
        $newAuthKey = $this->getArgument('newAuthKey', ['notEmptyString']);
        $newAuthData = $this->getArgument('newAuthData', ['notEmptyString']);

        $authData = $this->getAuthData($userID, $oldAuthKey);
        if ($authData !== null) {
            if ($oldAuthKey === $newAuthKey) {
                return ['status' => 'noChange'];
            }
            $app->data->rename($authData['dataKey'], $authData['dataKey'] . '_' . time());
            $this->setAuthData($userID, $newAuthKey, $newAuthData);
            return ['status' => 'ok'];
        }
        return ['status' => 'invalidAuth'];
    }
}
