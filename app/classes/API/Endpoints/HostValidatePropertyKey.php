<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostValidatePropertyKey extends Endpoint
{
    public function run()
    {
        $key = $this->getArgument('key', ['notEmptyString']);
        $context = $this->getArgument('context', ['notEmptyString']);
        if (Utilities::validatePropertyKey($key, $context === 'user' ? 'u' : 'g')) {
            return 'valid';
        }
        return 'invalid';
    }
}
