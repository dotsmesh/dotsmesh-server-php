<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\UserEndpoint;

class UserSetPushSubscription extends UserEndpoint
{
    public function run(): string
    {
        $app = App::get();

        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);
        $pushSubscription = $this->getArgument('pushSubscription', ['string']);
        $sessionDataKey = $session['dataKey'];

        $data = json_decode($app->data->getValue($sessionDataKey), true);
        if ((isset($data['p']) && $data['p'] !== $pushSubscription) || !isset($data['p'])) {
            $data['p'] = $pushSubscription;
            $app->data->setValue($sessionDataKey, json_encode($data));
        }

        return 'ok';
    }
}
