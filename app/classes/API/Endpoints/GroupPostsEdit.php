<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\GroupEndpoint;

class GroupPostsEdit extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $this->requireValidAccessKey($groupID, $memberID);

        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);

        $postID = $this->getArgument('postID', ['notEmptyString']);
        $post = $this->getArgument('post', ['notEmptyString']);
        $resourcesToSave = $this->getArgument('resourcesToSave', ['array']);
        $resourcesToDelete = $this->getArgument('resourcesToDelete', ['array']);

        if (!$this->isMemberPost($groupID, $memberID, $postID)) {
            return ['status' => 'noAccess'];
        }

        $app->data->setValue($dataPrefix . 'd/s/a/p/' . $postID, $post);

        $resourceDataKeyPrefix = $dataPrefix . 'd/s/a/a/' . $postID . '-';
        foreach ($resourcesToSave as $resourceID => $resourceValue) {
            // todo validate $resourceID
            $app->data->setValue($resourceDataKeyPrefix . $resourceID, $resourceValue);
        }

        foreach ($resourcesToDelete as $resourceID) {
            // todo validate $resourceID
            $app->data->delete($resourceDataKeyPrefix . $resourceID);
        }

        $this->addToGroupLog('s', $groupID, 'p', 'c', [$memberID, $postID]);
        $this->addToMemberLog('s', $groupID, $memberID, 'c', $postID);

        $this->announceChanges($groupID, ['gp']);

        return ['status' => 'ok'];
    }
}
