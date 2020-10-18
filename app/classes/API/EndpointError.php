<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API;

class EndpointError extends \Error
{

    /**
     * 
     * @var string
     */
    public $code = null;

    /**
     * 
     * @var string
     */
    public $message = null;

    /**
     * 
     * @param string $code
     * @param string $message
     */
    public function __construct(string $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }
}
