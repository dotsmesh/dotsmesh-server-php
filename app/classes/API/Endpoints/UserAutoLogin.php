<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\UserEndpoint;

class UserAutoLogin extends UserEndpoint
{
    public function run(): array
    {
        $app = App::get();

        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);
        $pushSubscription = $this->getArgument('pushSubscription', ['string']);
        $sessionDataKey = $session['dataKey'];

        return [
            'sessionData' => $session['sessionData']
        ];

        // todo
        $data = json_decode($app->data->getValue($sessionDataKey), true);
        if ($data['p'] !== $pushSubscription) {
            $data['p'] = $pushSubscription;
            $app->data->setValue($sessionDataKey, json_encode($data));
        }
    }
}
