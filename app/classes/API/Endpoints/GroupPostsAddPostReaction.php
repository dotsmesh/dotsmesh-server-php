<?php

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\GroupEndpoint;
use X\Utilities;

class GroupPostsAddPostReaction extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $this->requireValidAccessKey($groupID, $memberID);

        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);

        $postID = $this->getArgument('postID', ['notEmptyString']);
        // validate $postID
        $reaction = $this->getArgument('reaction', ['notEmptyString']);
        $resources = $this->getArgument('resources', ['array']);

        $reactionID = Utilities::generateDateBasedID();
        $app->data->setValue($dataPrefix . 'd/s/a/r/' . $postID . '/' . $reactionID, $reaction);
        foreach ($resources as $resourceID => $resourceValue) {
            // todo validate $resourceID
            $app->data->setValue($dataPrefix . 'd/s/a/e/' . $postID . '/' . $reactionID . '-' . $resourceID, $resourceValue);
        }
        $this->addToGroupLog('s', $groupID, 'r', 5, [$memberID, $postID, $reactionID]);
        $this->addToMemberLog('s', $groupID, $memberID, 5, [$postID, $reactionID]);

        $this->announceChanges($groupID, ['gp/' . $postID, 'gp/*']);

        return ['status' => 'ok', 'id' => $reactionID];
    }
}
