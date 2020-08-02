<?php

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
