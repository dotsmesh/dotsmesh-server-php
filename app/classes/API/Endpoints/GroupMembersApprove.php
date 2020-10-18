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

class GroupMembersApprove extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $isAdministrator = $this->requireValidAccessKey($groupID, $memberID);

        $memberIDToApprove = $this->getArgument('memberID', ['notEmptyString']);

        if ($isAdministrator) {
            $app = App::get();
            $dataPrefix = $this->getDataPrefix($groupID);
            $memberSourceDataKeyPrefix = $dataPrefix . 'd/s/m/p/' . $memberIDToApprove . '/';
            $memberTargetDataKeyPrefix = $dataPrefix . 'd/s/m/a/' . $memberIDToApprove . '/';

            $memberData = $app->data->getValue($memberSourceDataKeyPrefix . 'a');
            if ($memberData !== null) {
                $unpackedMemberData = Utilities::unpack($memberData);
                if ($unpackedMemberData['name'] === 'z') {
                    $unpackedMemberData['value'][] = Utilities::getDateID(Utilities::getMilliseconds());
                    $app->data->setValue($memberTargetDataKeyPrefix . 'a', Utilities::pack('w', $unpackedMemberData['value']));
                    $list = $app->data->getList()->filterBy('key', $memberSourceDataKeyPrefix . 'd/', 'startWith');
                    foreach ($list as $item) {
                        $app->data->rename($item->key, str_replace($memberSourceDataKeyPrefix, $memberTargetDataKeyPrefix, $item->key));
                    }
                    $app->data->delete($memberSourceDataKeyPrefix . 'a');
                    $this->addToGroupLog('s', $groupID, 'm', '0', $memberIDToApprove);
                    $this->addToMemberLog('s', $groupID, $memberIDToApprove, '0');
                    $this->announceChanges($groupID, ['gm', 'gma', 'gmp', 'gm/' . $memberIDToApprove . '/s']);
                } else {
                    throw new \Exception();
                }
            }
            return ['status' => 'ok']; // todo return notFound maybe ??
        }
        return ['status' => 'notAllowed'];
    }
}
