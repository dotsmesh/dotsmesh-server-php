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

class GroupPostsAdd extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $this->requireValidAccessKey($groupID, $memberID);

        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);

        $post = $this->getArgument('post', ['notEmptyString']);
        $resources = $this->getArgument('resources', ['array']);

        $postID = Utilities::generateDateBasedID();
        $app->data->setValue($dataPrefix . 'd/s/a/p/' . $postID, $post);
        foreach ($resources as $resourceID => $resourceValue) {
            // todo validate $resourceID
            $app->data->setValue($dataPrefix . 'd/s/a/a/' . $postID . '-' . $resourceID, $resourceValue);
        }
        $this->addToGroupLog('s', $groupID, 'p', 4, [$memberID, $postID]);
        $this->addToMemberLog('s', $groupID, $memberID, 4, $postID);

        $this->announceChanges($groupID, ['gp']);

        return ['status' => 'ok', 'id' => $postID];
    }
}
