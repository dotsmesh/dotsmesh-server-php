<?php

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\GroupEndpoint;

class GroupPostsDelete extends GroupEndpoint
{
    public function run(): array
    {
        $groupID = $this->requireValidGroupID();
        $memberID = $this->requireValidMemberID($groupID);
        $this->requireValidAccessKey($groupID, $memberID);

        $app = App::get();
        $dataPrefix = $this->getDataPrefix($groupID);

        $postID = $this->getArgument('postID', ['notEmptyString']);
        // todo vaildate ownership

        $dataKey = $dataPrefix . 'd/s/a/p/' . $postID;
        if ($app->data->exists($dataKey)) {
            // delete resources
            $list = $app->data->getList()
                ->filterBy('key', $dataPrefix . 'd/s/a/a/' . $postID . '-', 'startWith');
            foreach ($list as $item) {
                $app->data->delete($item->key);
            }
            // delete reactions
            $list = $app->data->getList()
                ->filterBy('key', $dataPrefix . 'd/s/a/r/' . $postID . '/', 'startWith');
            foreach ($list as $item) {
                $app->data->delete($item->key);
            }
            // delete reactions resources
            $list = $app->data->getList()
                ->filterBy('key', $dataPrefix . 'd/s/a/e/' . $postID . '/', 'startWith');
            foreach ($list as $item) {
                $app->data->delete($item->key);
            }
            $app->data->delete($dataKey);
            $this->addToGroupLog('s', $groupID, 'p', 6, [$memberID, $postID]);
            $this->addToMemberLog('s', $groupID, $memberID, 6, $postID);

            $this->announceChanges($groupID, ['gp/' . $postID, 'gp/*']);

            return ['status' => 'ok'];
        }
        return ['status' => 'postNotFound'];
    }
}
