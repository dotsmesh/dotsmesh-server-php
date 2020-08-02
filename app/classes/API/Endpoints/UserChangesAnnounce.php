<?php

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
