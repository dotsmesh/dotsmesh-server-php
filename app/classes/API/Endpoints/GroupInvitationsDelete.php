<?php

namespace X\API\Endpoints;

use X\API\GroupEndpoint;

class GroupInvitationsDelete extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $isAdministrator = $this->requireValidAccessKey($groupID, $memberID);

        $accessKey = $this->getArgument('accessKey', ['notEmptyString']);

        if ($isAdministrator) {
            $this->deleteInvitation($groupID, $accessKey);
            return ['status' => 'ok'];
        }
        return ['status' => 'notAllowed'];
    }
}
