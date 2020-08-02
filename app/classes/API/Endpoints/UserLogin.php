<?php

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\EndpointError;
use X\API\UserEndpoint;
use X\Utilities;

class UserLogin extends UserEndpoint
{
    public function run(): array
    {
        $app = App::get();

        $userID = $this->getArgument('id', ['notEmptyString']);
        if (!Utilities::isPropertyID($userID)) {
            throw new EndpointError('invalidUserID', 'The user ID specified is not valid!');
        }
        if (!Utilities::userExists($userID)) {
            return ['status' => 'userNotFound'];
        }
        $dataPrefix = $this->getDataPrefix($userID);

        $authKey = $this->getArgument('authKey', ['notEmptyString']);
        $sessionData = $this->getArgument('sessionData', ['notEmptyString']);
        $pushSubscription = $this->getArgument('pushSubscription', ['string']);

        $authData = $this->getAuthData($userID, $authKey);
        if ($authData !== null) {
            for ($i = 0; $i < 100; $i++) {
                $sessionKey = Utilities::generateRandomBase62String(rand(50, 60));
                $sessionKeyHash = Utilities::getHash('SHA-512', Utilities::getHash('SHA-512', $sessionKey));
                // delete others sessions - temp
                $list = $app->data->getList()
                    ->filterBy('key', $dataPrefix . 'e/', 'startWith');
                foreach ($list as $item) {
                    $app->data->delete($item->key);
                }
                $sessionDataKey = $dataPrefix . 'e/' . md5($sessionKeyHash);
                if (!$app->data->exists($sessionDataKey)) {
                    $app->data->setValue($sessionDataKey, json_encode([
                        's' => $sessionKeyHash,
                        'c' => time(),
                        'd' => $sessionData,
                        'p' => $pushSubscription
                    ]));
                    return [
                        'status' => 'ok',
                        'authData' => $authData['data'],
                        'sessionKey' => $sessionKey
                    ];
                }
            }
            return ['status' => 'tryAgain'];
        }
        return ['status' => 'invalidAuthKey'];
    }
}
