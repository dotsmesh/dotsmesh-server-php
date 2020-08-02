<?php

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\UserEndpoint;
use X\Utilities;

class UserInbox extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();
        $accessKey = $this->requireValidAccessKey($userID);

        $dataPrefix = $this->getDataPrefix($userID);

        $app = App::get();

        $dataPrefix .= 'd/p/i/';
        $data = $this->getArgument('data', ['notEmptyString']);
        $resources = $this->getArgument('resources', ['array']);

        $messageID = '3' . Utilities::generateDateBasedID(); // todo priority 1-5
        $app->data->setValue($dataPrefix . 'd/' . $messageID, Utilities::pack('0', [
            $accessKey, // access key used
            $data, // encrypted data
            array_keys($resources) // resources ids
        ]));
        foreach ($resources as $resourceID => $value) {
            //validate $resourceID
            $app->data->setValue($dataPrefix . 'r/' . $messageID . '-' . $resourceID, $value);
        }

        Utilities::queuePushNotifications($userID);

        return 'ok';
    }
}
