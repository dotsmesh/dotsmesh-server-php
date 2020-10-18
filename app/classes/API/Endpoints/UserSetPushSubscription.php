<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;

class UserSetPushSubscription extends UserEndpoint
{
    public function run(): string
    {
        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);
        $pushSubscription = $this->getArgument('pushSubscription', ['string']);

        $sessionKey = $session['key'];

        $data = $this->getSessionData($userID, $sessionKey);
        if (is_array($data)) {
            if ((isset($data['p']) && $data['p'] !== $pushSubscription) || !isset($data['p'])) {
                $data['p'] = $pushSubscription;
                $this->setSessionData($userID, $sessionKey, $data);
            }
        }

        return 'ok';
    }
}
