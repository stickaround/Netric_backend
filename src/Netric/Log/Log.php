<?php

namespace Netric\Log;

use Aereus\Config\Config;
use Netric\Log\Writer\LogWriterInterface;
use Netric\Log\Writer\PhpErrorLogWriter;
use Netric\Log\Writer\GelfLogWriter;
use Netric\Log\Writer\NullLogWriter;

/**
 * Description of Log
 */
class Log implements LogInterface
{
    /**
     * If this is set we will add it to every log to make tracking all calls/events easier
     *
     * @var string
     */
    private $requestId = "";

    /**
     * Log levels
     */
    const LOG_EMERG = 0;
    const LOG_ALERT = 1;
    const LOG_CRIT = 2;
    const LOG_ERR = 3;
    const LOG_WARNING = 4;
    const LOG_NOTICE = 5;
    const LOG_INFO = 6;
    const LOG_DEBUG = 7;

    /**
     * Current log level
     *
     * @var int
     */
    private $level = self::LOG_ERR;

    /**
     * Which writer we are going to use for logging
     *
     * @var LogWriterInterface
     */
    private $writer = null;

    /**
     * Flag to print logs to the console
     *
     * @var bool
     */
    private $printToConsole = false;

    /**
     * Internally keep track of how many times each level are logged
     *
     * @var array
     */
    private $stats = [];

    /**
     * Constructor
     *
     * @param Config $logConfig
     */
    public function __construct(Config $logConfig)
    {
        // Get log writer form config name
        $writer = $this->getWriterClassNameFromConfig($logConfig);
        $this->setLogWriter($writer);

        // Set current logging level if defined
        if ($logConfig->level) {
            $this->level = $logConfig->level;
        }
    }

    /**
     * Determine which writer we are going to use
     *
     * @param LogWriterInterface $writer
     */
    public function setLogWriter(LogWriterInterface $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Get the current writer used for loggin
     *
     * @return LogWriterInterface
     */
    public function getLogWriter(): LogWriterInterface
    {
        return $this->writer;
    }

    /**
     * Set the requestID to correlate log entries
     *
     * @param string $requestId
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     * Get the unique request ID if set
     *
     * @return string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Put a new entry into the log
     *
     * This is usually called by one of the aliased methods like info, error, warning
     * which in turn just sets the level and writes to this method.
     *
     * @param int $level The level of the event being logged
     * @param string|array $message The message to log
     * @return bool true on success, false on failure
     */
    public function writeLog($level, $message): bool
    {
        // Only log events below the current logging level set
        if ($level > $this->level) {
            return false;
        }

        // Prepare the log message
        $logMessage = new LogMessage('netric_svc', 'Applicaion Log');
        $logMessage->setLevelNumber($level);
        $logMessage->setApplicationEnvironment(getenv('APPLICATION_ENV'));
        // This is actually used for daemon vs server - application name
        $logMessage->setApplicationVersion(getenv('APPLICATION_VER'));

        // Add remote client IP address
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $logMessage->getClientIp($_SERVER['REMOTE_ADDR']);
        }

        // Add request to the log if available
        if (isset($_SERVER['REQUEST_URI'])) {
            $logMessage->setRequestPath($_SERVER['REQUEST_URI']);
        }

        // If the request ID was set the log it
        if ($this->requestId) {
            $logMessage->setRequestId($this->requestId);
        }

        /*
         * This can either be a string or a structured message - associative array.
         * Note that these MAY override any of the keys above. That is intentional
         * and useful for things like when you want to pass through client logs
         * from and override application_name to the client's name.
         */
        $logMessage->setBody($message);

        // Determine what writer to use
        $this->writer->write($logMessage);

        // Increment the stats counter for this level
        if (!isset($this->stats[$logMessage->getLevelName()])) {
            $this->stats[$logMessage->getLevelName()] = 0;
        }
        $this->stats[$logMessage->getLevelName()]++;

        return true;
    }

    /**
     * Log a debug message
     *
     * @param string|array $message The message to insert into the log
     * @return bool true if success
     */
    public function debug($message)
    {
        return $this->writeLog(self::LOG_DEBUG, $message);
    }

    /**
     * Log an informational message
     *
     * @param string|array $message The message to insert into the log
     * @return bool true if success
     */
    public function info($message)
    {
        return $this->writeLog(self::LOG_INFO, $message);
    }

    /**
     * Log a warning message
     *
     * @param string|array $message The message to insert into the log
     * @return bool true if success
     */
    public function warning($message)
    {
        return $this->writeLog(self::LOG_WARNING, $message);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|array $message The message to insert into the log
     * @return bool true if success
     */
    public function error($message)
    {
        return $this->writeLog(self::LOG_ERR, $message);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @return void
     */
    public function critical($message)
    {
        $this->writeLog(self::LOG_CRIT, $message);
    }

    /**
     * Return the number of log entries that have been written for each level
     * @return array ['error'=>10, 'warning'=>4 ...]
     */
    public function getLevelStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset number of log entries for each level
     */
    public function resetLevelStats()
    {
        $this->stats = [];
    }

    /**
     * PHP error handler function is called with set_error_handler early in execution
     *
     * @param int $errno The error code
     * @param string $errstr The error message
     * @param string $errfile The file originating the error
     * @param int $errline The line that triggered the error
     * @param array $errcontext Every variable that existed in the scope the error was triggered in
     */
    public function phpErrorHandler($errno, $errstr, $errfile = '', $errline = '', $errcontext = '')
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        $this->error("Error ($errno) $errstr in $errfile line $errline");

        // Note: the below was causing errors, we need to troubleshoot
        // // check if function has been called by an exception
        // if (func_num_args() == 5) {
        //     // called by trigger_error()
        //     $exception = null;
        //     list($errno, $errstr, $errfile, $errline) = func_get_args();
        //     $backtrace = array_reverse(debug_backtrace());
        // } else {
        //     // called by unhandled exception
        //     $exc = func_get_arg(0);
        //     $errno = $exc->getCode();
        //     $errstr = $exc->getMessage();
        //     $errfile = $exc->getFile();
        //     $errline = $exc->getLine();
        //     $backtrace = $exc->getTrace();
        // }

        // $errorType = [
        //     E_ERROR          => 'ERROR',
        //     E_WARNING        => 'WARNING',
        //     E_PARSE          => 'PARSING ERROR',
        //     E_NOTICE         => 'NOTICE',
        //     E_CORE_ERROR     => 'CORE ERROR',
        //     E_CORE_WARNING   => 'CORE WARNING',
        //     E_COMPILE_ERROR  => 'COMPILE ERROR',
        //     E_COMPILE_WARNING => 'COMPILE WARNING',
        //     E_USER_ERROR     => 'USER ERROR',
        //     E_USER_WARNING   => 'USER WARNING',
        //     E_USER_NOTICE    => 'USER NOTICE',
        //     E_STRICT         => 'STRICT NOTICE',
        //     E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR',
        // ];

        // // create error message
        // $err = 'UNHANDLED ERROR';
        // if (array_key_exists($errno, $errorType)) {
        //     $err = $errorType[$errno];
        // }

        // $errMsg = "$err: $errstr in $errfile on line $errline";

        // // start backtrace
        // $trace = "";
        // foreach ($backtrace as $v) {
        //     if (isset($v['class'])) {
        //         $trace = 'in class ' . $v['class'] . '::' . $v['function'] . '(';

        //         if (isset($v['args'])) {
        //             $separator = '';

        //             foreach ($v['args'] as $arg) {
        //                 $trace .= "$separator" . $this->getPhpErrorArgumentStr($arg);
        //                 $separator = ', ';
        //             }
        //         }
        //         $trace .= ')';
        //     } elseif (isset($v['function']) && empty($trace)) {
        //         $trace = 'in function ' . $v['function'] . '(';
        //         if (!empty($v['args'])) {
        //             $separator = '';

        //             foreach ($v['args'] as $arg) {
        //                 $trace .= "$separator" . $this->getPhpErrorArgumentStr($arg);
        //                 $separator = ', ';
        //             }
        //         }
        //         $trace .= ')';
        //     }
        // }

        // // what to do
        // switch ($errno) {
        //     case E_NOTICE:
        //     case E_USER_NOTICE:
        //     case E_STRICT:
        //     case E_DEPRECATED:
        //         return;
        //         break;

        //     default:
        //         // Log the error
        //         $this->error($errMsg . "\nTrace:\n" . $trace);

        //         break;
        // }
    }

    /**
     * Log an unhandled exception
     *
     * @param \Exception $exception
     */
    public function phpUnhandledExceptionHandler($exception)
    {
        $errno = $exception->getCode();
        $errstr = $exception->getMessage();
        $errfile = $exception->getFile();
        $errline = $exception->getLine();
        $backtrace = $exception->getTraceAsString();

        $body = "errNo = \"$errno: $errstr in $errfile on line $errline\";\n";
        $body .= "When: " . date('Y-m-d H:i:s') . "\n";
        $body .= "PAGE: " . $_SERVER['PHP_SELF'] . "\n";
        $body .= "----------------------------------------------\n";
        $body .= $errstr . "\nTrace: $backtrace";
        $body .= "\n----------------------------------------------\n";

        // Log the error
        $this->error($body);
    }

    /**
     * Capture PHP shutdown event to look for a fatal error
     */
    public function phpShutdownErrorChecker()
    {
        // Check for a fatal error that would halted execution
        $error = error_get_last();
        if (null != $error) {
            if ($error['type'] <= E_ERROR) {
                $this->phpErrorHandler(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line'],
                    []
                );
            }
        }
    }

    /**
     * Set or unset a flag that will print all logs to the console
     *
     * @param bool $print
     */
    public function setPrintToConsole($print = false)
    {
        $this->printToConsole = $print;
    }

    /**
     * Convert an error argument or backtrace to a string for logging
     */
    private function getPhpErrorArgumentStr($arg)
    {
        switch (strtolower(gettype($arg))) {
            case 'string':
                return ('"' . str_replace(["\n"], [''], $arg) . '"');

            case 'boolean':
                return (bool) $arg;

            case 'object':
                return 'object(' . get_class($arg) . ')';

            case 'array':
                $ret = 'array(';
                $separtor = '';

                foreach ($arg as $k => $v) {
                    //$ret .= $separtor.$this->getPhpErrorArgumentStr).' => '.$this->getPhpErrorArgumentStr);
                    $separtor = ', ';
                }
                $ret .= ')';

                return $ret;

            case 'resource':
                return 'resource(' . get_resource_type($arg) . ')';

            default:
                return var_export($arg, true);
        }
    }

    /**
     * Determine which log writer to use based on the config name
     *
     * @param Config $config
     * @return LogWriterInterface
     */
    private function getWriterClassNameFromConfig(Config $logconfig): LogWriterInterface
    {
        $writerClassName = 'Netric\Log\Writer\\';
        $writerClassName .= str_replace('_', '', ucwords($logconfig->writer, '_'));
        $writerClassName .= "LogWriter";

        // Check to see if the writer exist
        if (class_exists($writerClassName)) {
            // Instantiate it with the log config and return
            return new $writerClassName($logconfig);
        }

        // Default to the php_error writer
        return new PhpErrorLogWriter();
    }
}
