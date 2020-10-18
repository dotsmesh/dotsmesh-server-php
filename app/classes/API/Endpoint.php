<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X\API;

use X\API\EndpointError;
use X\Utilities;

class Endpoint
{

    /**
     * 
     * @var array
     */
    private $args = [];

    /**
     * 
     * @var array
     */
    private $options = [];

    /**
     * 
     * @param array $args
     * @param array $options
     */
    public function __construct(array $args, array $options)
    {
        $this->args = $args;
        $this->options = $options;
    }

    /**
     * Validates an argument and returns it's value if valid. Throws EndpointError otherwise.
     *
     * @param string $key The name of the property of interest.
     * @param array $types A list of valid value types.
     * @return mixed
     * @throws \X\API\EndpointError
     */
    protected function getArgument(string $key, array $types)
    {
        return $this->getValue($this->args, $key,  $types);
    }

    /**
     * 
     * @param string $key
     * @param array $types
     * @return mixed
     */
    protected function getOption(string $key, array $types)
    {
        return $this->getValue($this->options, $key,  $types);
    }

    /**
     * 
     * @param string $key
     * @return boolean
     */
    protected function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    /**
     * 
     * @param array $values
     * @param string $key
     * @param array $types
     * @return mixed
     */
    private function getValue(array $values, string $key, array $types)
    {
        if (!array_key_exists($key, $values)) {
            throw new EndpointError('invalidArgument', 'The ' . $key . ' property is missing in the arguments provided!');
        }
        $value = $values[$key];
        foreach ($types as $type) {
            if ($type === 'string') {
                if (is_string($value)) {
                    return $value;
                }
            } elseif ($type === 'int') {
                if (is_int($value)) {
                    return $value;
                }
            } elseif ($type === 'array') {
                if (is_array($value)) {
                    return $value;
                }
            } elseif ($type === 'notEmptyString') {
                if (is_string($value) && strlen($value) > 0) {
                    return $value;
                }
            } elseif ($type === 'notEmptyStringOrNull') {
                if ($value === null || (is_string($value) && strlen($value) > 0)) {
                    return $value;
                }
            }
        }
        throw new EndpointError('invalidArgument', 'The ' . $key . ' property must be of type ' . implode('|', $types) . '!');
    }

    /**
     * Returns the data prefix for the property specified.
     * 
     * @param string $id
     * @return string
     */
    protected function getDataPrefix(string $id): string
    {
        return Utilities::getPropertyDataPrefix($id);
    }
}
