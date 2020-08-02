<?php

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostChangesNotify extends Endpoint
{
    public function run()
    {
        // todo add secret to prevent unautorized access
        $host = $this->getArgument('host', ['notEmptyString']); // validate
        $keys = $this->getArgument('keys', ['array']); // validate
        Utilities::notifyChangesObservers($host, $keys);
        return 'ok';
    }
}
