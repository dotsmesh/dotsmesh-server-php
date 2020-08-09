<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities;

class UserChangesAnnounce extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();
        $this->requireValidSessionKey($userID);
        $keys = $this->getArgument('keys', ['array']);
        Utilities::announceChanges($userID, $keys);
        return 'ok';
    }
}
