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

class GroupMembersJoin extends GroupEndpoint
{
    public function run(): array
    {
        $app = App::get();

        $groupID = $this->requireValidGroupID();
        $accessKey = $this->requireValidInvitationAccessKey($groupID);

        $memberID = $this->getArgument('memberID', ['notEmptyString']); // todo validate
        $newAccessKey = $this->getArgument('newAccessKey', ['notEmptyString']);
        //$sharedData = $this->getArgument('sharedData', ['array']);

        $dataPrefix = $this->getDataPrefix($groupID);

        $invitationData = $this->getInvitationData($groupID, $accessKey);
        if ($invitationData === null) {
            return ['status' => 'invitationNotFound'];
        }
        $data = Utilities::unpack($invitationData);
        if ($data['name'] === '0') { // specific member invitation
            $data = $data['value'];
            if ($data['i'] === $memberID) {
                $memberDataKey = $dataPrefix . 'd/s/m/a/' . $memberID . '/a';
                if (!$app->data->exists($memberDataKey)) {
                    $app->data->setValue($memberDataKey, Utilities::pack('y', [$data['m'], Utilities::getDateID(Utilities::getMilliseconds())]));
                    $this->addToGroupLog('s', $groupID, 'm', '0', $memberID);
                    $this->addToMemberLog('s', $groupID, $memberID, '0');
                }
                // foreach ($sharedData as $key => $value) {
                //     $app->data->setValue($dataPrefix . 'd/s/m/a/' . $memberID . '/d/' . $key, $value);
                // }
                $this->deleteInvitation($groupID, $accessKey);
                $this->addToGroupLog('p', $groupID, 'i', 7, [$accessKey, $memberID]);
                $this->addAccessKey($groupID, $memberID, $newAccessKey);
                $this->announceChanges($groupID, ['gm', 'gma', 'gm/' . $memberID . '/s']);
                return ['status' => 'ok'];
            }
        } else if ($data['name'] === '1') { // no specific member invitation
            $data = $data['value'];
            $memberData = $this->getArgument('memberData', ['notEmptyString']);
            $memberDataKey = $dataPrefix . 'd/s/m/p/' . $memberID . '/a';
            if (!$app->data->exists($memberDataKey)) {
                $app->data->setValue($memberDataKey, Utilities::pack('z', [$data['m'], $memberData, Utilities::getDateID(Utilities::getMilliseconds())]));
                $this->addToGroupLog('s', $groupID, 'm', '0', $memberID);
            }
            // foreach ($sharedData as $key => $value) {
            //     $app->data->setValue($dataPrefix . 'd/s/m/p/' . $memberID . '/d/' . $key, $value);
            // }
            $this->addToGroupLog('p', $groupID, 'i', 9, [$accessKey, $memberID]);
            $this->addAccessKey($groupID, $memberID, $newAccessKey);
            $this->announceChanges($groupID, ['gm', 'gmp', 'gm/' . $memberID . '/s']);
            return ['status' => 'pendingApproval'];
        }
        return ['status' => 'invalidInvitationData'];
    }
}
