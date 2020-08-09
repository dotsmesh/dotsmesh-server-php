<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API\Endpoints;

use X\API\Endpoint;
use X\Utilities;

class HostValidatePropertyID extends Endpoint
{
    public function run()
    {
        $id = $this->getArgument('id', ['notEmptyString']);
        //$context = $this->getArgument('context', ['notEmptyString']);

        if (!Utilities::isPropertyID($id)) {
            return 'invalid';
        }
        if (Utilities::propertyExists($id)) {
            return 'taken';
        }
        return 'valid';
    }
}
