<?php

namespace X\API\Endpoints;

use BearFramework\App;
use X\API\UserEndpoint;

class UserLogout extends UserEndpoint
{
    public function run()
    {
        $userID = $this->requireValidUserID();
        $session = $this->requireValidSessionKey($userID);

        $app = App::get();
        $app->data->delete($session['dataKey']);

        return 'ok';
    }
}
