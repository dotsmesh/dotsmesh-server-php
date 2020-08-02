<?php

namespace X\API\Endpoints;

use X\API\GroupEndpoint;
use X\Utilities;

class GroupInvitationsGet extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $accessKey = $this->requireValidInvitationAccessKey($groupID);
        $invitationData = $this->getInvitationData($groupID, $accessKey);
        if ($invitationData !== null) {
            $data = Utilities::unpack($invitationData);
            if ($data['name'] === '1') { // no member specified
                $data = $data['value'];
                if (isset($data['w'])) {
                    return [
                        'status' => 'ok',
                        'result' => $data['w']
                    ];
                }
            }
        }
        return ['status' => 'invitationNotFound'];
    }
}
