<?php

/*
 * Dots Mesh Observer (PHP)
 * https://github.com/dotsmesh/dotsmesh-observer-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\Endpoint;

class UtilitiesGetPushKeys extends Endpoint
{
    public function run()
    {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        return [
            'vapidPublicKey' => $keys['publicKey'],
            'vapidPrivateKey' => $keys['privateKey'],
        ];
    }
}
