<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\GroupEndpoint;

class GroupInvitationsAdd extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $this->requireValidAccessKey($groupID, $memberID);

        $accessKey = $this->getArgument('accessKey', ['notEmptyString']);
        $invitationData = $this->getArgument('data', ['notEmptyString']);

        $this->addInvitation($groupID, $memberID, $accessKey, $invitationData);
        $this->addToGroupLog('p', $groupID, 'i', 1, [$accessKey, $memberID]);

        return ['status' => 'ok'];
    }
}
