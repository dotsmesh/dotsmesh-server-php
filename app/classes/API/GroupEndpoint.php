<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

/*
Group log names
0 - added member
1 - added invitation
2 - member left
3 - invitation denied
4 - new post
5 - new reaction
6 - delete post
7 - invitation used and deleted (specific member joined)
8 - invitation deleted (specific member left)
9 - invitation used (non specific member)
a - member deleted (from the admin)
b - member approved (from the admin)
c - edit post
*/

namespace X\API;

use X\Utilities;
use BearFramework\App;

class GroupEndpoint extends Endpoint
{

    /**
     * 
     * @param boolean $mustExist
     * @return string
     */
    protected function requireValidGroupID(bool $mustExist = true): string
    {
        $id = $this->getOption('propertyID', ['notEmptyString']);
        if (!Utilities::isPropertyID($id)) {
            throw new EndpointError('invalidGroupID', 'The group ID specified is not valid!');
        }
        if ($mustExist) {
            if (!Utilities::groupExists($id)) {
                throw new EndpointError('groupNotFound', 'The group specified does not exists!');
            }
        }
        return $id;
    }

    protected function hasMemberID()
    {
        return $this->hasOption('memberID');
    }

    protected function requireValidMemberID(string $groupID, bool $allowNotApproved = false)
    {
        $app = App::get();
        $memberID = $this->getOption('memberID', ['notEmptyString']);
        $dataPrefix = $this->getDataPrefix($groupID);
        if ($app->data->exists($dataPrefix . 'd/s/m/a/' . $memberID . '/a')) {
            return $memberID;
        }
        if ($allowNotApproved) {
            if ($app->data->exists($dataPrefix . 'd/s/m/p/' . $memberID . '/a')) {
                return $memberID;
            }
        }
        throw new EndpointError('invalidMemberID', 'invalidMemberID');
    }

    protected function getAccessKey()
    {
        return Utilities::getHash('SHA-512', $this->getOption('accessKey', ['notEmptyString']));
    }

    protected function requireValidAccessKey(string $groupID, string $memberID)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $hashedAccessKey = $this->getAccessKey();
        $data = $app->data->getValue($dataPrefix . 'f/' . $memberID);
        if ($data !== null) {
            $data = json_decode($data, true);
            if ($data[0] === $hashedAccessKey) {
                return (int) $data[1];
            }
        }
        throw new EndpointError('invalidAccessKey', 'The access key provided is not valid!');
        // if ($permission === null) {
        //     return;
        // }
        // if (isset($data['permissions']) && is_array($data['permissions'])) {
        //     if (array_search($permission, $data['permissions']) !== false) {
        //         return;
        //     }
        // }
    }

    protected function requireValidInvitationAccessKey(string $groupID)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $hashedAccessKey = $this->getAccessKey();
        $invitationDataKey = $dataPrefix . 'd/p/i/' . md5($hashedAccessKey);
        $data = $app->data->getValue($invitationDataKey);
        if ($data !== null) {
            $data = json_decode($data, true);
            if (is_array($data) && isset($data['a']) && $data['a'] === $hashedAccessKey) {
                return $hashedAccessKey;
            }
        }
        throw new EndpointError('invalidAccessKey', 'The access key provided is not valid!');
    }

    protected function addAccessKey(string $groupID,  string $memberID, string $accessKey, bool $isAdministator = false)
    {
        // $accessKey must be hashed on the client
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $app->data->setValue($dataPrefix . 'f/' . $memberID, json_encode([$accessKey, (int) $isAdministator]));
    }

    protected function removeAccessKey(string $groupID, string $memberID)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $app->data->delete($dataPrefix . 'f/' . $memberID);
    }

    protected function addToGroupLog(string $type, string $groupID, string $context, string $name, $data = null)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $milliseconds = Utilities::getMilliseconds();
        $dataKey = $dataPrefix . 'd/' . $type . '/l/' . $context . '/' . Utilities::getDateID($milliseconds, 2);
        $app->data->append($dataKey, Utilities::getDateID($milliseconds) . ':' . Utilities::pack($name, $data)  . "\n");
    }

    protected function addToMemberLog(string $type, string $groupID, string $memberID, string $name, $data = null)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $milliseconds = Utilities::getMilliseconds();
        $dataKey = $dataPrefix . 'd/' . $type . '/m/a/' . $memberID . '/l/' . Utilities::getDateID($milliseconds, 2);
        $app->data->append($dataKey, Utilities::getDateID($milliseconds) . ':' . Utilities::pack($name, $data) . "\n");
    }

    protected function isMemberPost(string $groupID, string $memberID, string $postID): bool
    {
        $app = App::get();
        $type = 's'; // shared
        $dataPrefix = $this->getDataPrefix($groupID) . 'd/' . $type . '/m/a/' . $memberID . '/l/';
        $list = $app->data->getList()
            ->filterBy('key', $dataPrefix, 'startWith')
            ->sortBy('key', 'desc');;
        foreach ($list as $item) {
            $log = Utilities::parseLog($item->value, ['4']);
            foreach ($log as $item) {
                if ($item['data'] === $postID) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function addInvitation(string $groupID, string $memberID, string $accessKey, $invitationData)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $invitationDataKey = $dataPrefix . 'd/p/i/' . md5($accessKey);
        if ($app->data->exists($invitationDataKey)) {
            throw new EndpointError('keyAlreadyExists', 'keyAlreadyExists');
        }
        $app->data->setValue($invitationDataKey, json_encode([
            'a' => $accessKey,
            'd' => $invitationData,
            'm' => $memberID // the user that made it
        ]));
        $this->announceChanges($groupID, ['gi']);
    }

    protected function getInvitationData(string $groupID, string $accessKey)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $invitationDataKey = $dataPrefix . 'd/p/i/' . md5($accessKey);
        $invitationData = $app->data->getValue($invitationDataKey);
        if ($invitationData !== null) {
            $invitationData = json_decode($invitationData, true);
            if (is_array($invitationData) && isset($invitationData['a']) && isset($invitationData['d']) && $invitationData['a'] === $accessKey) {
                return $invitationData['d'];
            }
        }
        return null;
    }

    protected function deleteInvitation(string $groupID, string $accessKey)
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $invitationDataKey = $dataPrefix . 'd/p/i/' . md5($accessKey);
        $app->data->delete($invitationDataKey);
        $this->announceChanges($groupID, ['gi']);
    }

    protected function deleteMember(string $groupID, string $memberID, string $logName = '2')
    {
        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);
        $locations = ['a', 'p'];
        $changeKeys = [];
        foreach ($locations as $location) {
            $memberDataKeyPrefix = $dataPrefix .  'd/s/m/' . $location . '/' . $memberID . '/';
            $list = $app->data->getList()
                ->filterBy('key', $memberDataKeyPrefix, 'startWith');
            if ($list->count() > 0) {
                foreach ($list as $item) {
                    $app->data->delete($item->key);
                }
                $this->addToGroupLog('s', $groupID, 'm', $logName, $memberID);
                $changeKeys = ['gm', 'gm' . $location, 'gm/' . $memberID . '/s'];
            }
        }
        $memberDataKeyPrefix = $dataPrefix .  'd/p/m/' . $memberID . '/';
        $list = $app->data->getList()
            ->filterBy('key', $memberDataKeyPrefix, 'startWith');
        foreach ($list as $item) {
            $app->data->delete($item->key);
        }
        $this->removeAccessKey($groupID, $memberID);
        if (!empty($changeKeys)) {
            $this->announceChanges($groupID, $changeKeys);
        }
        return true;
    }

    protected function announceChanges(string $groupID, array $keys)
    {
        if (empty($keys)) {
            return;
        }
        $hashedKeys = [];
        foreach ($keys as $key) {
            $hashedKeys[] = Utilities::getHash('SHA-512-10', $groupID . '$' . $key);
        }
        Utilities::announceChanges($groupID, $hashedKeys);
    }
}
