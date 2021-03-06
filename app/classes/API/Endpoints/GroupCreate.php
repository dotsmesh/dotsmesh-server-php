<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\EndpointError;
use X\API\GroupEndpoint;
use X\Utilities;

class GroupCreate extends GroupEndpoint
{
    public function run(): array
    {
        $app = App::get();

        $groupKey = strtolower($this->getArgument('groupKey', ['notEmptyString']));
        $memberID = $this->getArgument('memberID', ['notEmptyString']); // validate
        $accessKey = $this->getArgument('accessKey', ['notEmptyString']);
        $memberData = $this->getArgument('memberData', ['notEmptyString']);

        if (!Utilities::validatePropertyKey($groupKey, 'g')) {
            throw new EndpointError('invalidGroupKey', 'invalidGroupKey');
        }
        $parts = explode(':', $groupKey);
        $host = $parts[0];

        for ($i = 0; $i < 1000; $i++) {
            $groupID =  Utilities::generateRandomBase36String(15) . '.' . $host;
            $resultCode = Utilities::createProperty($groupID, 'g', $groupKey);
            if ($resultCode === 1) {
                $dataPrefix = $this->getDataPrefix($groupID);
                $app->data->setValue($dataPrefix . 'd/s/m/a/' . $memberID . '/a', Utilities::pack('y', [$memberData, Utilities::getDateID(Utilities::getMilliseconds())]));
                $this->addAccessKey($groupID, $memberID, $accessKey, true);
                $this->addToGroupLog('s', $groupID, 'm', 0, $memberID);
                $this->addToMemberLog('s', $groupID, $memberID, '0');
                return ['status' => 'ok', 'id' => $groupID];
            } elseif ($resultCode === 2) {
                // retry
            } else {
                return ['status' => 'tryAgain'];
            }
        }
    }
}
