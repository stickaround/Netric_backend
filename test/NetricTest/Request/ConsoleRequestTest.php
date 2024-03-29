<?php
namespace NetricTest\Request;

use Netric\Request\ConsoleRequest;
use PHPUnit\Framework\TestCase;

class ConsoleRequestTest extends TestCase
{
    /**
     * Make sure we can parse args into an array of params
     */
    public function testParseArgs()
    {
        $args = [
            "nonoptionarray",
            "-v",
            "-f", "myfile.txt",
            "--username",  "sky",
            "--password=\"test -pass\""
        ];
        $reflectionMethod = new \ReflectionMethod('Netric\Request\ConsoleRequest', 'parseArgs');
        $reflectionMethod->setAccessible(true);
        $ret = $reflectionMethod->invoke(new ConsoleRequest(), $args);

        $expects = [
            0 => 'nonoptionarray',
            'v' => true,
            'f' => 'myfile.txt',
            'username' => 'sky',
            'password' => '"test -pass"',
        ];
        $this->assertEquals($expects, $ret);
    }
}
