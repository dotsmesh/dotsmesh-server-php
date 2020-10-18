<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\UserEndpoint;
use X\Utilities\DataStorage;
use BearFramework\App;
use X\Utilities;

class UserDataStorage extends UserEndpoint
{
    public function run(): array
    {
        $userID = $this->requireValidUserID();

        $type = $this->getArgument('type', ['notEmptyString']);
        $commands = $this->getArgument('commands', ['array']);

        $dataPrefix = $this->getDataPrefix($userID);

        $readOnly = true;
        if ($type === 's') { // shared
            if ($this->hasSessionKey()) {
                $this->requireValidSessionKey($userID);
                $readOnly = false;
            }
            $dataPrefix .= 'd/s/';
        } else if ($type === 'f') { // full
            $this->requireValidSessionKey($userID);
            $readOnly = false;
            $dataPrefix .= 'd/';

            $app = App::get();
            $app->data->addEventListener('itemChange', function (\BearFramework\App\Data\ItemChangeEventDetails $details) use ($userID, $dataPrefix) {
                if ($details->key === $dataPrefix . 'p/o/h') {
                    Utilities::updateChangesSubscriptions($userID);
                }
            });
        } else {
            throw new \InvalidArgumentException();
        }

        $dataStorage = new DataStorage($dataPrefix, $readOnly);
        return $dataStorage->execute($commands);
    }
}
