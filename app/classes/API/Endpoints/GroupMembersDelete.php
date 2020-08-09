<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\GroupEndpoint;

class GroupMembersDelete extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $isAdministrator = $this->requireValidAccessKey($groupID, $memberID);

        $memberIDToDelete = $this->getArgument('memberID', ['notEmptyString']);

        if ($isAdministrator) {
            $this->deleteMember($groupID, $memberIDToDelete, 'a');
            return ['status' => 'ok'];
        }
        return ['status' => 'notAllowed'];
    }
}
