<?php
/***********************************************
* File      :   requestprocessor.php
* Project   :   Z-Push
* Descr     :   This file provides/loads the handlers
*               for the different commands.
*               The request handlers are optimised
*               so that as little as possible
*               data is kept-in-memory, and all
*               output data is directly streamed
*               to the client, while also streaming
*               input data from the client.
*
* Created   :   12.08.2011
*
* Copyright 2007 - 2016 Zarafa Deutschland GmbH
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

abstract class RequestProcessor
{
    protected static $backend;
    protected static $deviceManager;
    protected static $topCollector;
    protected static $decoder;
    protected static $encoder;
    protected static $userIsAuthenticated;
    protected static $specialHeaders;
    protected static $waitTime = 0;

    /**
     * Authenticates the remote user
     * The sent HTTP authentication information is used to on Backend->Logon().
     * As second step the GET-User verified by Backend->Setup() for permission check
     * Request::GetGETUser() is usually the same as the Request::GetAuthUser().
     * If the GETUser is different from the AuthUser, the AuthUser MUST HAVE admin
     * permissions on GETUsers data store. Only then the Setup() will be sucessfull.
     * This allows the user 'john' to do operations as user 'joe' if he has sufficient privileges.
     *
     * @access public
     * @return
     * @throws AuthenticationRequiredException
     */
    public static function Authenticate()
    {
        self::$userIsAuthenticated = false;

        // when a certificate is sent, allow authentication only as the certificate owner
        if (defined("CERTIFICATE_OWNER_PARAMETER") && isset($_SERVER[CERTIFICATE_OWNER_PARAMETER]) && strtolower($_SERVER[CERTIFICATE_OWNER_PARAMETER]) != strtolower(Request::GetAuthUser())) {
            throw new AuthenticationRequiredException(sprintf("Access denied. Access is allowed only for the certificate owner '%s'", $_SERVER[CERTIFICATE_OWNER_PARAMETER]));
        }

        $backend = ZPush::GetBackend();
        if ($backend->Logon(Request::GetAuthUser(), Request::GetAuthDomain(), Request::GetAuthPassword()) == false) {
            throw new AuthenticationRequiredException("Access denied. Username or password incorrect");
        }

        // mark this request as "authenticated"
        self::$userIsAuthenticated = true;
    }

    /**
     * Indicates if the user was "authenticated"
     *
     * @access public
     * @return boolean
     */
    public static function isUserAuthenticated()
    {
        if (!isset(self::$userIsAuthenticated)) {
            return false;
        }
        return self::$userIsAuthenticated;
    }

    /**
     * Initialize the RequestProcessor
     *
     * @access public
     * @return
     */
    public static function Initialize()
    {
        self::$backend = ZPush::GetBackend();
        self::$deviceManager = ZPush::GetDeviceManager();
        self::$topCollector = ZPush::GetTopCollector();

        if (!ZPush::CommandNeedsPlainInput(Request::GetCommandCode())) {
            self::$decoder = new WBXMLDecoder(Request::GetInputStream());
        }

        self::$encoder = new WBXMLEncoder(Request::GetOutputStream(), Request::GetGETAcceptMultipart());
        self::$waitTime = 0;
    }

    /**
     * Loads the command handler and processes a command sent from the mobile
     *
     * @access public
     * @return boolean
     */
    public static function HandleRequest()
    {
        $handler = ZPush::GetRequestHandlerForCommand(Request::GetCommandCode());

        // if there is an error decoding wbxml, consume remaining data and include it in the WBXMLException
        try {
            if (!$handler->Handle(Request::GetCommandCode())) {
                throw new WBXMLException(sprintf("Unknown error in %s->Handle()", get_class($handler)));
            }
        } catch (Exception $ex) {
            // Log 10 KB of the WBXML data
            ZLog::Write(LOGLEVEL_FATAL, "WBXML 10K debug data: " . Request::GetInputAsBase64(10240), false);
            throw $ex;
        }

        // also log WBXML in happy case
        if (ZLog::IsWbxmlDebugEnabled()) {
            // Log 4 KB in the happy case
            ZLog::Write(LOGLEVEL_WBXML, "WBXML-IN : ". Request::GetInputAsBase64(4096), false);
        }
    }

    /**
     * Returns any additional headers which should be sent to the mobile
     *
     * @access public
     * @return array
     */
    public static function GetSpecialHeaders()
    {
        if (!isset(self::$specialHeaders) || !is_array(self::$specialHeaders)) {
            return [];
        }

        return self::$specialHeaders;
    }

    /**
     * Returns the amount of seconds RequestProcessor waited e.g. during Ping.
     *
     * @access public
     * @return int
     */
    public static function GetWaitTime()
    {
        return self::$waitTime;
    }

    /**
     * Handles a command
     *
     * @param int       $commandCode
     *
     * @access public
     * @return boolean
     */
    abstract public function Handle($commandCode);
}
