<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\GroupEndpoint;
use X\Utilities;

class GroupMembersLeave extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $hasMember = $this->hasMemberID();
        if ($hasMember) {
            $memberID = $this->requireValidMemberID($groupID, true);
            $this->requireValidAccessKey($groupID, $memberID);
            $this->deleteMember($groupID, $memberID);
            $status = 'ok';
        } else {
            $accessKey = $this->requireValidInvitationAccessKey($groupID);
            $invitationData = $this->getInvitationData($groupID, $accessKey);
            if ($invitationData !== null) {
                $data = Utilities::unpack($invitationData);
                if ($data['name'] === '0') { // specific member invitation
                    $this->deleteInvitation($groupID, $accessKey);
                    $this->addToGroupLog('p', $groupID, 'i', 8, $accessKey);
                }
            }
            $status = 'ok';
        }
        return ['status' => $status];
    }
}
