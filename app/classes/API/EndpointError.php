<?php

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
