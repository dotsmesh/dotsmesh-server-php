<?php

namespace X\API\Endpoints;

use X\API\EndpointError;
use X\API\GroupEndpoint;
use X\Utilities\DataStorage;
use BearFramework\App;

class GroupDataStorage extends GroupEndpoint
{
    public function run(): array
    {
        $app = App::get();
        $groupID = $this->requireValidGroupID();
        if ($this->hasMemberID()) {
            $memberID = $this->requireValidMemberID($groupID, true);
            $isAdministrator = $this->requireValidAccessKey($groupID, $memberID);
        } else {
            $memberID = null;
            $this->requireValidInvitationAccessKey($groupID);
            $isAdministrator = false;
        }
        //echo (int)$isAdministrator;exit;

        $type = $this->getArgument('type', ['notEmptyString']);
        $commands = $this->getArgument('commands', ['array']);

        $groupDataPrefix = $this->getDataPrefix($groupID);
        $dataPrefix = $groupDataPrefix;

        $readOnly = true;
        if ($type === 's') { // shared
            $dataPrefix .= 'd/s/';
            if ($isAdministrator) {
                $readOnly = false; // needed for profile
            }
        } else if ($type === 'f') { // full
            if (!$isAdministrator) {
                throw new EndpointError('noPrivateDataAccess', 'noPrivateDataAccess');
            }
            $dataPrefix .= 'd/';
            $readOnly = false;
        } else if ($type === 'mf') { // current member shared
            if ($memberID === null) {
                throw new \InvalidArgumentException('missingMemberID');
            }
            $readOnly = false;
            if ($app->data->exists($groupDataPrefix . 'd/s/m/a/' . $memberID . '/a')) {
                $dataPrefix .= 'd/s/m/a/' . $memberID . '/d/';
            } elseif ($app->data->exists($groupDataPrefix . 'd/s/m/p/' . $memberID . '/a')) {
                $dataPrefix .= 'd/s/m/p/' . $memberID . '/d/';
            } else {
                throw new EndpointError('memberNotFound', 'memberNotFound');
            }
        } else if ($type === 'ms') { // member shared
            $dataMemberID = $this->getArgument('dataMemberID', ['notEmptyString']);
            if ($app->data->exists($groupDataPrefix . 'd/s/m/a/' . $dataMemberID . '/a')) {
                $dataPrefix .= 'd/s/m/a/' . $dataMemberID . '/d/';
            } elseif ($app->data->exists($groupDataPrefix . 'd/s/m/p/' . $dataMemberID . '/a')) {
                $dataPrefix .= 'd/s/m/p/' . $dataMemberID . '/d/';
            } else {
                throw new EndpointError('memberNotFound', 'memberNotFound');
            }
        } else {
            throw new \InvalidArgumentException('invalidType');
        }

        $dataStorage = new DataStorage($dataPrefix, $readOnly);
        return $dataStorage->execute($commands);
    }
}
