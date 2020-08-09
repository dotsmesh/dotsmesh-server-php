<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostChangesGet extends Endpoint
{
    public function run()
    {
        // todo add some secret to build trust
        $age = $this->getArgument('age', ['int']);
        $keys = $this->getArgument('keys', ['array']);

        return Utilities::getChanges($age, $keys);
    }
}
