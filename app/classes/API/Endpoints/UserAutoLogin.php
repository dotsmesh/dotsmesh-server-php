<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;

class UserAutoLogin extends UserEndpoint
{
    public function run(): array
    {
        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);

        return [
            'sessionData' => $session['sessionData'],
            'pushData' => $session['pushData']
        ];
    }
}
