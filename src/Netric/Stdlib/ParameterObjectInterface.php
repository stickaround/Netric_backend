<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */
namespace Netric\Stdlib;

interface ParameterObjectInterface
{
    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value);
    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key);
    /**
     * @param string $key
     * @return bool
     */
    public function __isset($key);
    /**
     * @param string $key
     * @return void
     */
    public function __unset($key);
}
