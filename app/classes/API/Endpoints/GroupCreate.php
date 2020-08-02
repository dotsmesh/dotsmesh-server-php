<?php

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

        $groupKey = $this->getArgument('groupKey', ['notEmptyString']);
        //$initialData = $this->getArgument('initialData', ['array']);
        $memberID = $this->getArgument('memberID', ['notEmptyString']); // validate
        $accessKey = $this->getArgument('accessKey', ['notEmptyString']);
        $memberData = $this->getArgument('memberData', ['notEmptyString']);
        //$sharedData = $this->getArgument('sharedData', ['array']);

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
                // foreach ($initialData as $dataKey => $dataValue) {
                //     $app->data->setValue($dataPrefix . $dataKey, $dataValue);
                // }
                $app->data->setValue($dataPrefix . 'd/s/m/a/' . $memberID . '/a', Utilities::pack('y', [$memberData, Utilities::getDateID(Utilities::getMilliseconds())]));
                // foreach ($sharedData as $key => $value) {
                //     $app->data->setValue($dataPrefix . 'd/s/m/a/' . $memberID . '/d/' . $key, $value);
                // }
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
