<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API;

class EndpointError extends \Error
{

    public $code = null;
    public $message = null;

    public function __construct(string $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }
}
