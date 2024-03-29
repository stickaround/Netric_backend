<?php
/**
 * @author Sky Stebnicki <sky.stebnicki@aereus.com>
 * @copyright 2015 Aereus
 */
namespace NetricTest\Stdlib;

use PHPUnit\Framework\TestCase;
use Netric\Stdlib\ErrorHandler;

class ErrorHandlerTest extends TestCase
{
    public function tearDown(): void
    {
        if (ErrorHandler::getNestedLevel()) {
            ErrorHandler::clean();
        }
    }
    public function testNestedLevel()
    {
        $this->assertSame(0, ErrorHandler::getNestedLevel());
        ErrorHandler::start();
        $this->assertSame(1, ErrorHandler::getNestedLevel());
        ErrorHandler::start();
        $this->assertSame(2, ErrorHandler::getNestedLevel());
        ErrorHandler::stop();
        $this->assertSame(1, ErrorHandler::getNestedLevel());
        ErrorHandler::stop();
        $this->assertSame(0, ErrorHandler::getNestedLevel());
    }
    public function testClean()
    {
        ErrorHandler::start();
        $this->assertSame(1, ErrorHandler::getNestedLevel());
        ErrorHandler::start();
        $this->assertSame(2, ErrorHandler::getNestedLevel());
        ErrorHandler::clean();
        $this->assertSame(0, ErrorHandler::getNestedLevel());
    }
    public function testStarted()
    {
        $this->assertFalse(ErrorHandler::started());
        ErrorHandler::start();
        $this->assertTrue(ErrorHandler::started());
        ErrorHandler::stop();
        $this->assertFalse(ErrorHandler::started());
    }
    public function testAddError()
    {
        ErrorHandler::start();
        ErrorHandler::addError(1, 'test-msg1', 'test-file1', 100);
        ErrorHandler::addError(2, 'test-msg2', 'test-file2', 200);
        $err = ErrorHandler::stop();
        $this->assertInstanceOf('ErrorException', $err);
        $this->assertEquals('test-file2', $err->getFile());
        $this->assertEquals('test-msg2', $err->getMessage());
        $this->assertEquals(200, $err->getLine());
        $this->assertEquals(0, $err->getCode());
        $this->assertEquals(2, $err->getSeverity());
        $previous = $err->getPrevious();
        $this->assertInstanceOf('ErrorException', $previous);
        $this->assertEquals('test-file1', $previous->getFile());
        $this->assertEquals('test-msg1', $previous->getMessage());
        $this->assertEquals(100, $previous->getLine());
        $this->assertEquals(0, $previous->getCode());
        $this->assertEquals(1, $previous->getSeverity());
    }
}
