<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;

class UserLogout extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);

        $this->deleteSessionData($userID, $session['key']);

        return 'ok';
    }
}
