<?php

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostChangesSubscription extends Endpoint
{
    public function run()
    {
        // todo add secret to prevent unautorized access
        $host = $this->getArgument('host', ['notEmptyString']); // validate
        $keysToAdd = $this->getArgument('keysToAdd', ['array']); // validate
        $keysToRemove = $this->getArgument('keysToRemove', ['array']); // validate
        Utilities::modifyChangesSubscription($host, $keysToAdd, $keysToRemove);
        return 'ok';
    }
}
