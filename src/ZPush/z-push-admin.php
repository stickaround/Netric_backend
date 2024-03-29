#!/usr/bin/env php
<?php
/***********************************************
* File      :   z-push-admin.php
* Project   :   Z-Push
* Descr     :   This is a small command line
*               client to see and modify the
*               wipe status of Kopano users.
*
* Created   :   14.05.2010
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

require_once 'vendor/autoload.php';

/**
 * //TODO resync of single folders of a users device
 */

/************************************************
 * MAIN
 */
    define('BASE_PATH_CLI', dirname(__FILE__) ."/");
    set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH_CLI);

if (!defined('ZPUSH_CONFIG')) {
    define('ZPUSH_CONFIG', BASE_PATH_CLI . 'config.php');
}
    include_once(ZPUSH_CONFIG);

try {
    ZPush::CheckConfig();
    ZPushAdminCLI::CheckEnv();
    ZPushAdminCLI::CheckOptions();

    if (! ZPushAdminCLI::SureWhatToDo()) {
        // show error message if available
        if (ZPushAdminCLI::GetErrorMessage()) {
            fwrite(STDERR, ZPushAdminCLI::GetErrorMessage() . "\n");
        }

        echo ZPushAdminCLI::UsageInstructions();
        exit(1);
    }

    ZPushAdminCLI::RunCommand();
} catch (ZPushException $zpe) {
    fwrite(STDERR, get_class($zpe) . ": ". $zpe->getMessage() . "\n");
    exit(1);
}


/************************************************
 * Z-Push-Admin CLI
 */
class ZPushAdminCLI
{
    const COMMAND_SHOWALLDEVICES = 1;
    const COMMAND_SHOWDEVICESOFUSER = 2;
    const COMMAND_SHOWUSERSOFDEVICE = 3;
    const COMMAND_WIPEDEVICE = 4;
    const COMMAND_REMOVEDEVICE = 5;
    const COMMAND_RESYNCDEVICE = 6;
    const COMMAND_CLEARLOOP = 7;
    const COMMAND_SHOWLASTSYNC = 8;
    const COMMAND_RESYNCFOLDER = 9;
    const COMMAND_FIXSTATES = 10;
    const COMMAND_RESYNCHIERARCHY = 11;

    const TYPE_OPTION_EMAIL = "email";
    const TYPE_OPTION_CALENDAR = "calendar";
    const TYPE_OPTION_CONTACT = "contact";
    const TYPE_OPTION_TASK = "task";
    const TYPE_OPTION_NOTE = "note";
    const TYPE_OPTION_HIERARCHY = "hierarchy";
    const TYPE_OPTION_GAB = "gab";

    private static $command;
    private static $user = false;
    private static $device = false;
    private static $type = false;
    private static $errormessage;

    /**
     * Returns usage instructions
     *
     * @return string
     * @access public
     */
    public static function UsageInstructions()
    {
        return  "Usage:\n\tz-push-admin.php -a ACTION [options]\n\n" .
                "Parameters:\n\t-a list/lastsync/wipe/remove/resync/clearloop/fixstates\n\t[-u] username\n\t[-d] deviceid\n" .
                "\t[-t] type\tthe following types are available: '".self::TYPE_OPTION_EMAIL."', '".self::TYPE_OPTION_CALENDAR."', '".self::TYPE_OPTION_CONTACT."', '".self::TYPE_OPTION_TASK."', '".self::TYPE_OPTION_NOTE."', '".self::TYPE_OPTION_HIERARCHY."' of '".self::TYPE_OPTION_GAB."' (for KOE) or a folder id.\n\n" .
                "Actions:\n" .
                "\tlist\t\t\t\t\t Lists all devices and synchronized users\n" .
                "\tlist -u USER\t\t\t\t Lists all devices of user USER\n" .
                "\tlist -d DEVICE\t\t\t\t Lists all users of device DEVICE\n" .
                "\tlastsync\t\t\t\t Lists all devices and synchronized users and the last synchronization time\n" .
                "\twipe -u USER\t\t\t\t Remote wipes all devices of user USER\n" .
                "\twipe -d DEVICE\t\t\t\t Remote wipes device DEVICE\n" .
                "\twipe -u USER -d DEVICE\t\t\t Remote wipes device DEVICE of user USER\n" .
                "\tremove -u USER\t\t\t\t Removes all state data of all devices of user USER\n" .
                "\tremove -d DEVICE\t\t\t Removes all state data of all users synchronized on device DEVICE\n" .
                "\tremove -u USER -d DEVICE\t\t Removes all related state data of device DEVICE of user USER\n" .
                "\tresync -u USER -d DEVICE\t\t Resynchronizes all data of device DEVICE of user USER\n" .
                "\tresync -t TYPE \t\t\t\t Resynchronizes all folders of type (possible values above) for all devices and users.\n" .
                "\tresync -t TYPE -u USER \t\t\t Resynchronizes all folders of type (possible values above) for the user USER.\n" .
                "\tresync -t TYPE -u USER -d DEVICE\t Resynchronizes all folders of type (possible values above) for a specified device and user.\n" .
                "\tresync -t FOLDERID -u USER\t\t Resynchronize the specified folder id only. The USER should be specified for better performance.\n" .
                "\tresync -t hierarchy -u USER -d DEVICE\t Resynchronize the folder hierarchy data for an optional USER and optional DEVICE.\n" .
                "\tclearloop\t\t\t\t Clears system wide loop detection data\n" .
                "\tclearloop -d DEVICE -u USER\t\t Clears all loop detection data of a device DEVICE and an optional user USER\n" .
                "\tfixstates\t\t\t\t Checks the states for integrity and fixes potential issues\n" .
                "\n";
    }

    /**
     * Checks the environment
     *
     * @return
     * @access public
     */
    public static function CheckEnv()
    {
        if (php_sapi_name() != "cli") {
            self::$errormessage = "This script can only be called from the CLI.";
        }

        if (!function_exists("getopt")) {
            self::$errormessage = "PHP Function getopt not found. Please check your PHP version and settings.";
        }
    }

    /**
     * Checks the options from the command line
     *
     * @return
     * @access public
     */
    public static function CheckOptions()
    {
        if (self::$errormessage) {
            return;
        }

        $options = getopt("u:d:a:t:");

        // get 'user'
        if (isset($options['u']) && !empty($options['u'])) {
            self::$user = strtolower(trim($options['u']));
        } elseif (isset($options['user']) && !empty($options['user'])) {
            self::$user = strtolower(trim($options['user']));
        }

        // get 'device'
        if (isset($options['d']) && !empty($options['d'])) {
            self::$device = strtolower(trim($options['d']));
        } elseif (isset($options['device']) && !empty($options['device'])) {
            self::$device = strtolower(trim($options['device']));
        }

        // get 'action'
        $action = false;
        if (isset($options['a']) && !empty($options['a'])) {
            $action = strtolower(trim($options['a']));
        } elseif (isset($options['action']) && !empty($options['action'])) {
            $action = strtolower(trim($options['action']));
        }

        // get 'type'
        if (isset($options['t']) && !empty($options['t'])) {
            self::$type = strtolower(trim($options['t']));
        } elseif (isset($options['type']) && !empty($options['type'])) {
            self::$type = strtolower(trim($options['type']));
        }

        // if type is set, it must be one of known types or a 44 or 48 byte long folder id
        if (self::$type !== false) {
            if (self::$type !== self::TYPE_OPTION_EMAIL &&
                self::$type !== self::TYPE_OPTION_CALENDAR &&
                self::$type !== self::TYPE_OPTION_CONTACT &&
                self::$type !== self::TYPE_OPTION_TASK &&
                self::$type !== self::TYPE_OPTION_NOTE &&
                self::$type !== self::TYPE_OPTION_HIERARCHY &&
                self::$type !== self::TYPE_OPTION_GAB &&
                strlen(self::$type) !== 6 &&       // like U1f38d
                strlen(self::$type) !== 44 &&
                strlen(self::$type) !== 48) {
                    self::$errormessage = "Wrong 'type'. Possible values are: ".
                        "'".self::TYPE_OPTION_EMAIL."', '".self::TYPE_OPTION_CALENDAR."', '".self::TYPE_OPTION_CONTACT."', '".self::TYPE_OPTION_TASK."', '".self::TYPE_OPTION_NOTE."', '".self::TYPE_OPTION_HIERARCHY."', '".self::TYPE_OPTION_GAB."' ".
                        "or a 6, 44 or 48 byte long folder id (as hex).";
                    return;
            }
        }

        // get a command for the requested action
        switch ($action) {
            // list data
            case "list":
                if (self::$user === false && self::$device === false) {
                    self::$command = self::COMMAND_SHOWALLDEVICES;
                }

                if (self::$user !== false) {
                    self::$command = self::COMMAND_SHOWDEVICESOFUSER;
                }

                if (self::$device !== false) {
                    self::$command = self::COMMAND_SHOWUSERSOFDEVICE;
                }
                break;

            // list data
            case "lastsync":
                self::$command = self::COMMAND_SHOWLASTSYNC;
                break;

            // remove wipe device
            case "wipe":
                if (self::$user === false && self::$device === false) {
                    self::$errormessage = "Not possible to execute remote wipe. Device, user or both must be specified.";
                } else {
                    self::$command = self::COMMAND_WIPEDEVICE;
                }
                break;

            // remove device data of user
            case "remove":
                if (self::$user === false && self::$device === false) {
                    self::$errormessage = "Not possible to remove data. Device, user or both must be specified.";
                } else {
                    self::$command = self::COMMAND_REMOVEDEVICE;
                }
                break;

            // resync a device
            case "resync":
            case "re-sync":
            case "sync":
            case "resynchronize":
            case "re-synchronize":
            case "synchronize":
                // full resync
                if (self::$type === false) {
                    if (self::$user === false || self::$device === false) {
                        self::$errormessage = "Not possible to resynchronize device. Device and user must be specified.";
                    } else {
                        self::$command = self::COMMAND_RESYNCDEVICE;
                    }
                } elseif (self::$type === self::TYPE_OPTION_HIERARCHY) {
                    self::$command = self::COMMAND_RESYNCHIERARCHY;
                } else {
                    self::$command = self::COMMAND_RESYNCFOLDER;
                }
                break;

            // clear loop detection data
            case "clearloop":
            case "clearloopdetection":
                self::$command = self::COMMAND_CLEARLOOP;
                break;

            // clear loop detection data
            case "fixstates":
            case "fix":
                self::$command = self::COMMAND_FIXSTATES;
                break;


            default:
                self::UsageInstructions();
        }
    }

    /**
     * Indicates if the options from the command line
     * could be processed correctly
     *
     * @return boolean
     * @access public
     */
    public static function SureWhatToDo()
    {
        return isset(self::$command);
    }

    /**
     * Returns a errormessage of things which could have gone wrong
     *
     * @return string
     * @access public
     */
    public static function GetErrorMessage()
    {
        return (isset(self::$errormessage)) ? self::$errormessage : "";
    }

    /**
     * Runs a command requested from an action of the command line
     *
     * @return
     * @access public
     */
    public static function RunCommand()
    {
        echo "\n";
        switch (self::$command) {
            case self::COMMAND_SHOWALLDEVICES:
                self::CommandShowDevices();
                break;

            case self::COMMAND_SHOWDEVICESOFUSER:
                self::CommandShowDevices();
                break;

            case self::COMMAND_SHOWUSERSOFDEVICE:
                self::CommandDeviceUsers();
                break;

            case self::COMMAND_SHOWLASTSYNC:
                self::CommandShowLastSync();
                break;

            case self::COMMAND_WIPEDEVICE:
                if (self::$device) {
                    echo sprintf("Are you sure you want to REMOTE WIPE device '%s' [y/N]: ", self::$device);
                } else {
                    echo sprintf("Are you sure you want to REMOTE WIPE all devices of user '%s' [y/N]: ", self::$user);
                }

                $confirm  = strtolower(trim(fgets(STDIN)));
                if ($confirm === 'y' || $confirm === 'yes') {
                    self::CommandWipeDevice();
                } else {
                    echo "Aborted!\n";
                }
                break;

            case self::COMMAND_REMOVEDEVICE:
                self::CommandRemoveDevice();
                break;

            case self::COMMAND_RESYNCDEVICE:
                if (self::$device == false) {
                    echo sprintf("Are you sure you want to re-synchronize all devices of user '%s' [y/N]: ", self::$user);
                    $confirm  = strtolower(trim(fgets(STDIN)));
                    if (!($confirm === 'y' || $confirm === 'yes')) {
                        echo "Aborted!\n";
                        exit(1);
                    }
                }
                self::CommandResyncDevices();
                break;

            case self::COMMAND_RESYNCFOLDER:
                if (self::$device == false && self::$user == false) {
                    echo "Are you sure you want to re-synchronize this folder type of all devices and users [y/N]: ";
                    $confirm  = strtolower(trim(fgets(STDIN)));
                    if (!($confirm === 'y' || $confirm === 'yes')) {
                        echo "Aborted!\n";
                        exit(1);
                    }
                }
                self::CommandResyncFolder();
                break;

            case self::COMMAND_RESYNCHIERARCHY:
                if (self::$device == false && self::$user == false) {
                    echo "Are you sure you want to re-synchronize the hierarchy of all devices and users [y/N]: ";
                    $confirm  = strtolower(trim(fgets(STDIN)));
                    if (!($confirm === 'y' || $confirm === 'yes')) {
                        echo "Aborted!\n";
                        exit(1);
                    }
                }
                self::CommandResyncHierarchy();
                break;

            case self::COMMAND_CLEARLOOP:
                self::CommandClearLoopDetectionData();
                break;

            case self::COMMAND_FIXSTATES:
                self::CommandFixStates();
                break;
        }
        echo "\n";
    }

    /**
     * Command "Show all devices" and "Show devices of user"
     * Prints the device id of/and connected users
     *
     * @return
     * @access public
     */
    public static function CommandShowDevices()
    {
        $devicelist = ZPushAdmin::ListDevices(self::$user);
        if (empty($devicelist)) {
            echo "\tno devices found\n";
        } else {
            if (self::$user === false) {
                echo "All synchronized devices\n\n";
                echo str_pad("Device id", 36). "Synchronized users\n";
                echo "-----------------------------------------------------\n";
            } else {
                echo "Synchronized devices of user: ". self::$user. "\n";
            }
        }

        foreach ($devicelist as $deviceId) {
            if (self::$user === false) {
                echo str_pad($deviceId, 36) . implode(",", ZPushAdmin::ListUsers($deviceId)) ."\n";
            } else {
                self::printDeviceData($deviceId, self::$user);
            }
        }
    }

    /**
     * Command "Show all devices and users with last sync time"
     * Prints the device id of/and connected users
     *
     * @return
     * @access public
     */
    public static function CommandShowLastSync()
    {
        $devicelist = ZPushAdmin::ListDevices(false);
        if (empty($devicelist)) {
            echo "\tno devices found\n";
        } else {
            echo "All known devices and users and their last synchronization time\n\n";
            echo str_pad("Device id", 36) . str_pad("Synchronized user", 31) . str_pad("Last sync time", 20) . "Short Ids\n";
            echo "-----------------------------------------------------------------------------------------------------\n";
        }

        foreach ($devicelist as $deviceId) {
            $users = ZPushAdmin::ListUsers($deviceId);
            foreach ($users as $user) {
                $device = ZPushAdmin::GetDeviceDetails($deviceId, $user);
                $lastsync = $device->GetLastSyncTime() ? strftime("%Y-%m-%d %H:%M", $device->GetLastSyncTime()) : "never";
                $hasShortFolderIds = $device->HasFolderIdMapping() ? "Yes" : "No";
                echo str_pad($deviceId, 36) . str_pad($user, 30) . " " . str_pad($lastsync, 20) . $hasShortFolderIds . "\n";
            }
        }
    }

    /**
     * Command "Show users of device"
     * Prints informations about all users which use a device
     *
     * @return
     * @access public
     */
    public static function CommandDeviceUsers()
    {
        $users = ZPushAdmin::ListUsers(self::$device);

        if (empty($users)) {
            echo "\tno user data synchronized to device\n";
        }
        // if a user is specified, we only want to see the devices of this one
        elseif (self::$user !== false && !in_array(self::$user, $users)) {
            printf("\tuser '%s' not known in device data '%s'\n", self::$user, self::$device);
        }

        foreach ($users as $user) {
            if (self::$user !== false && strtolower($user) !== self::$user) {
                continue;
            }
            echo "Synchronized by user: ". $user. "\n";
            self::printDeviceData(self::$device, $user);
        }
    }

    /**
     * Command "Wipe device"
     * Marks a device of that user to be remotely wiped
     *
     * @return
     * @access public
     */
    public static function CommandWipeDevice()
    {
        $stat = ZPushAdmin::WipeDevice($_SERVER["LOGNAME"], self::$user, self::$device);

        if (self::$user !== false && self::$device !== false) {
            echo sprintf("Mark device '%s' of user '%s' to be wiped: %s", self::$device, self::$user, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";

            if ($stat) {
                echo "Updated information about this device:\n";
                self::printDeviceData(self::$device, self::$user);
            }
        } elseif (self::$user !== false) {
            echo sprintf("Mark devices of user '%s' to be wiped: %s", self::$user, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
            self::CommandShowDevices();
        }
    }

    /**
     * Command "Remove device"
     * Remove a device of that user from the device list
     *
     * @return
     * @access public
     */
    public static function CommandRemoveDevice()
    {
        $stat = ZPushAdmin::RemoveDevice(self::$user, self::$device);
        if (self::$user === false) {
            echo sprintf("State data of device '%s' removed: %s", self::$device, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        } elseif (self::$device === false) {
            echo sprintf("State data of all devices of user '%s' removed: %s", self::$user, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        } else {
            echo sprintf("State data of device '%s' of user '%s' removed: %s", self::$device, self::$user, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        }
    }

    /**
     * Command "Resync device(s)"
     * Resyncs one or all devices of that user
     *
     * @return
     * @access public
     */
    public static function CommandResyncDevices()
    {
        $stat = ZPushAdmin::ResyncDevice(self::$user, self::$device);
        echo sprintf("Resync of device '%s' of user '%s': %s", self::$device, self::$user, ($stat) ? 'Requested' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
    }

    /**
     * Command "Resync folder(s)"
     * Resyncs a folder type of a specific device/user or of all users
     *
     * @return
     * @access public
     */
    public static function CommandResyncFolder()
    {
        // if no device is specified, search for all devices of a user. If user is not set, all devices are returned.
        if (self::$device === false) {
            $devicelist = ZPushAdmin::ListDevices(self::$user);
            if (empty($devicelist)) {
                echo "\tno devices/users found\n";
                return true;
            }
        } else {
            $devicelist = [self::$device];
        }

        foreach ($devicelist as $deviceId) {
            $users = ZPushAdmin::ListUsers($deviceId);
            foreach ($users as $user) {
                if (self::$user && self::$user != $user) {
                    continue;
                }
                self::resyncFolder($deviceId, $user, self::$type);
            }
        }
    }

    /**
     * Command "Resync hierarchy"
     * Resyncs a folder type of a specific device/user or of all users
     *
     * @return
     * @access public
     */
    public static function CommandResyncHierarchy()
    {
        // if no device is specified, search for all devices of a user. If user is not set, all devices are returned.
        if (self::$device === false) {
            $devicelist = ZPushAdmin::ListDevices(self::$user);
            if (empty($devicelist)) {
                echo "\tno devices/users found\n";
                return true;
            }
        } else {
            $devicelist = [self::$device];
        }

        foreach ($devicelist as $deviceId) {
            $users = ZPushAdmin::ListUsers($deviceId);
            foreach ($users as $user) {
                if (self::$user && self::$user != $user) {
                    continue;
                }
                self::resyncHierarchy($deviceId, $user);
            }
        }
    }

    /**
     * Command to clear the loop detection data
     * Mobiles may enter loop detection (one-by-one synchring due to timeouts / erros).
     *
     * @return
     * @access public
     */
    public static function CommandClearLoopDetectionData()
    {
        $stat = false;
        $stat = ZPushAdmin::ClearLoopDetectionData(self::$user, self::$device);
        if (self::$user === false && self::$device === false) {
            echo sprintf("System wide loop detection data removed: %s", ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        } elseif (self::$user === false) {
            echo sprintf("Loop detection data of device '%s' removed: %s", self::$device, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        } elseif (self::$device === false && self::$user !== false) {
            echo sprintf("Error: %s", ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_WARN)). "\n";
        } else {
            echo sprintf("Loop detection data of device '%s' of user '%s' removed: %s", self::$device, self::$user, ($stat) ? 'OK' : ZLog::GetLastMessage(LOGLEVEL_ERROR)). "\n";
        }
    }

    /**
     * Resynchronizes a folder type of a device & user
     *
     * @param string    $deviceId       the id of the device
     * @param string    $user           the user
     * @param string    $type           the folder type
     *
     * @return
     * @access private
     */
    private static function resyncFolder($deviceId, $user, $type)
    {
        $device = ZPushAdmin::GetDeviceDetails($deviceId, $user);

        if (! $device instanceof ASDevice) {
            echo sprintf("Folder resync failed: %s\n", ZLog::GetLastMessage(LOGLEVEL_ERROR));
            return false;
        }

        $folders = [];
        $searchFor = $type;
        // get the KOE gab folderid
        if ($type == self::TYPE_OPTION_GAB) {
            if (@constant('KOE_GAB_FOLDERID') !== '') {
                $gab = KOE_GAB_FOLDERID;
            } else {
                $gab = $device->GetKoeGabBackendFolderId();
            }
            if (!$gab) {
                printf("Could not find KOE GAB folderid for device '%s' of user '%s'\n", $deviceId, $user);
                return false;
            }
            $searchFor = $gab;
        }
        // potential long ids are converted to folderids here, incl. the gab id
        $searchFor = strtolower($device->GetFolderIdForBackendId($searchFor, false, false, null));

        foreach ($device->GetAllFolderIds() as $folderid) {
            // if  submitting a folderid as type to resync a specific folder.
            if (strtolower($folderid) === $searchFor) {
                printf("Found and resynching requested folderid '%s' on device '%s' of user '%s'\n", $folderid, $deviceId, $user);
                $folders[] = $folderid;
                break;
            }

            if ($device->GetFolderUUID($folderid)) {
                $foldertype = $device->GetFolderType($folderid);
                switch ($foldertype) {
                    case SYNC_FOLDER_TYPE_APPOINTMENT:
                    case SYNC_FOLDER_TYPE_USER_APPOINTMENT:
                        if ($searchFor == "calendar") {
                            $folders[] = $folderid;
                        }
                        break;
                    case SYNC_FOLDER_TYPE_CONTACT:
                    case SYNC_FOLDER_TYPE_USER_CONTACT:
                        if ($searchFor == "contact") {
                            $folders[] = $folderid;
                        }
                        break;
                    case SYNC_FOLDER_TYPE_TASK:
                    case SYNC_FOLDER_TYPE_USER_TASK:
                        if ($searchFor == "task") {
                            $folders[] = $folderid;
                        }
                        break;
                    case SYNC_FOLDER_TYPE_NOTE:
                    case SYNC_FOLDER_TYPE_USER_NOTE:
                        if ($searchFor == "note") {
                            $folders[] = $folderid;
                        }
                        break;
                    default:
                        if ($searchFor == "email") {
                            $folders[] = $folderid;
                        }
                        break;
                }
            }
        }

        $stat = ZPushAdmin::ResyncFolder($user, $deviceId, $folders);
        echo sprintf("Resync of %d folders of type '%s' on device '%s' of user '%s': %s\n", count($folders), $type, $deviceId, $user, ($stat) ? 'Requested' : ZLog::GetLastMessage(LOGLEVEL_ERROR));
    }

    /**
     * Resynchronizes the hierarchy of a device & user
     *
     * @param string    $deviceId       the id of the device
     * @param string    $user           the user
     *
     * @return
     * @access private
     */
    private static function resyncHierarchy($deviceId, $user)
    {
        $stat = ZPushAdmin::ResyncHierarchy($user, $deviceId);
        echo sprintf("Removing hierarchy information for resync on device '%s' of user '%s': %s\n", $deviceId, $user, ($stat) ? 'Requested' : ZLog::GetLastMessage(LOGLEVEL_ERROR));
    }

    /**
     * Fixes the states for potential issues
     *
     * @return
     * @access private
     */
    private static function CommandFixStates()
    {
        echo "Validating and fixing states (this can take some time):\n";

        echo "\tChecking username casings: ";
        if ($stat = ZPushAdmin::FixStatesDifferentUsernameCases()) {
            printf("Processed: %d - Converted: %d - Removed: %d\n", $stat[0], $stat[1], $stat[2]);
        } else {
            echo ZLog::GetLastMessage(LOGLEVEL_ERROR) . "\n";
        }

        // fixes ZP-339
        echo "\tChecking available devicedata & user linking: ";
        if ($stat = ZPushAdmin::FixStatesDeviceToUserLinking()) {
            printf("Processed: %d - Fixed: %d\n", $stat[0], $stat[1]);
        } else {
            echo ZLog::GetLastMessage(LOGLEVEL_ERROR) . "\n";
        }

        echo "\tChecking for unreferenced (obsolete) state files: ";
        if (($stat = ZPushAdmin::FixStatesUserToStatesLinking()) !== false) {
            printf("Processed: %d - Deleted: %d\n", $stat[0], $stat[1]);
        } else {
            echo ZLog::GetLastMessage(LOGLEVEL_ERROR) . "\n";
        }

        echo "\tChecking for hierarchy folder data state: ";
        if (($stat = ZPushAdmin::FixStatesHierarchyFolderData()) !== false) {
            printf("Devices: %d - Processed: %d - Fixed: %d - Device+User without hierarchy: %d\n", $stat[0], $stat[1], $stat[2], $stat[3]);
        } else {
            echo ZLog::GetLastMessage(LOGLEVEL_ERROR) . "\n";
        }

        echo "\tChecking flags of shared folders: ";
        if (($stat = ZPushAdmin::FixStatesAdditionalFolders()) !== false) {
            printf("Devices: %d - Devices with additional folders: %d - Fixed: %d\n", $stat[0], $stat[1], $stat[2]);
        } else {
            echo ZLog::GetLastMessage(LOGLEVEL_ERROR) . "\n";
        }
    }

    /**
     * Prints detailed informations about a device
     *
     * @param string    $deviceId       the id of the device
     * @param string    $user           the user
     *
     * @return
     * @access private
     */
    private static function printDeviceData($deviceId, $user)
    {
        global $additionalFolders;
        $device = ZPushAdmin::GetDeviceDetails($deviceId, $user);

        if (! $device instanceof ASDevice) {
            echo sprintf("Folder resync failed: %s\n", ZLog::GetLastMessage(LOGLEVEL_ERROR));
            return false;
        }

        // Gather some statistics about synchronized folders
        $folders = $device->GetAllFolderIds();
        $synchedFolders = 0;
        $synchedFolderTypes = [];
        $syncedFoldersInProgress = 0;
        foreach ($folders as $folderid) {
            if ($device->GetFolderUUID($folderid)) {
                $synchedFolders++;
                $type = $device->GetFolderType($folderid);
                $folder = $device->GetHierarchyCache()->GetFolder($folderid);
                $name = $folder ? $folder->displayname : "unknown";
                switch ($type) {
                    case SYNC_FOLDER_TYPE_APPOINTMENT:
                    case SYNC_FOLDER_TYPE_USER_APPOINTMENT:
                        if ($name == KOE_GAB_NAME) {
                            $gentype = "GAB";
                        } else {
                            $gentype = "Calendars";
                        }
                        break;
                    case SYNC_FOLDER_TYPE_CONTACT:
                    case SYNC_FOLDER_TYPE_USER_CONTACT:
                        $gentype = "Contacts";
                        break;
                    case SYNC_FOLDER_TYPE_TASK:
                    case SYNC_FOLDER_TYPE_USER_TASK:
                        $gentype = "Tasks";
                        break;
                    case SYNC_FOLDER_TYPE_NOTE:
                    case SYNC_FOLDER_TYPE_USER_NOTE:
                        $gentype = "Notes";
                        break;
                    default:
                        $gentype = "Emails";
                        break;
                }
                if (!isset($synchedFolderTypes[$gentype])) {
                    $synchedFolderTypes[$gentype] = 0;
                }
                $synchedFolderTypes[$gentype]++;

                // set the folder name for all folders which are not fully synchronized yet
                $fstatus = $device->GetFolderSyncStatus($folderid);
                if ($fstatus !== false && is_array($fstatus)) {
                    $fstatus['name'] = $name ? $name : $gentype;
                    $device->SetFolderSyncStatus($folderid, $fstatus);
                    $syncedFoldersInProgress++;
                }
            }
        }
        $folderinfo = "";
        foreach ($synchedFolderTypes as $gentype => $count) {
            $folderinfo .= $gentype;
            if ($count > 1) {
                $folderinfo .= "($count)";
            }
            $folderinfo .= " ";
        }
        if (!$folderinfo) {
            $folderinfo = "None available";
        }

        // additional folders
        $addFolders = [];
        $sharedFolders = $device->GetAdditionalFolders();
        array_walk($sharedFolders, function (&$key) {
            $key["origin"] = 'Shared';
        });
        // $additionalFolders comes directly from the config
        array_walk($additionalFolders, function (&$key) {
            $key["origin"] = 'Configured';
        });
        foreach (array_merge($additionalFolders, $sharedFolders) as $df) {
            $df['additional'] = '';
            $syncfolderid = $device->GetFolderIdForBackendId($df['folderid'], false, false, null);
            switch ($df['type']) {
                case SYNC_FOLDER_TYPE_USER_APPOINTMENT:
                    if ($name == KOE_GAB_NAME) {
                        $gentype = "GAB";
                    } else {
                        $gentype = "Calendar";
                    }
                    break;
                case SYNC_FOLDER_TYPE_USER_CONTACT:
                    $gentype = "Contact";
                    break;
                case SYNC_FOLDER_TYPE_USER_TASK:
                    $gentype = "Task";
                    break;
                case SYNC_FOLDER_TYPE_USER_NOTE:
                    $gentype = "Note";
                    break;
                default:
                    $gentype = "Email";
                    break;
            }
            if ($device->GetFolderType($syncfolderid) == SYNC_FOLDER_TYPE_UNKNOWN) {
                $df['additional'] = "(KOE patching incomplete)";
            }
            $df['type'] = $gentype;
            $df['synched'] = ($device->GetFolderUUID($syncfolderid)) ? 'Active' : 'Inactive (not yet synchronized or no permissions)';
            $addFolders[] = $df;
        }
        $addFoldersTotal = !empty($addFolders) ? count($addFolders) : 'none';

        echo "-----------------------------------------------------\n";
        echo "DeviceId:\t\t$deviceId\n";
        echo "Device type:\t\t". ($device->GetDeviceType() !== ASDevice::UNDEFINED ? $device->GetDeviceType() : "unknown") ."\n";
        echo "UserAgent:\t\t".($device->GetDeviceUserAgent() !== ASDevice::UNDEFINED ? $device->GetDeviceUserAgent() : "unknown") ."\n";
        // TODO implement $device->GetDeviceUserAgentHistory()

        // device information transmitted during Settings command
        if ($device->GetDeviceModel()) {
            echo "Device Model:\t\t". $device->GetDeviceModel(). "\n";
        }
        if ($device->GetDeviceIMEI()) {
            echo "Device IMEI:\t\t". $device->GetDeviceIMEI(). "\n";
        }
        if ($device->GetDeviceFriendlyName()) {
            echo "Device friendly name:\t". $device->GetDeviceFriendlyName(). "\n";
        }
        if ($device->GetDeviceOS()) {
            echo "Device OS:\t\t". $device->GetDeviceOS(). "\n";
        }
        if ($device->GetDeviceOSLanguage()) {
            echo "Device OS Language:\t". $device->GetDeviceOSLanguage(). "\n";
        }
        if ($device->GetDevicePhoneNumber()) {
            echo "Device Phone nr:\t". $device->GetDevicePhoneNumber(). "\n";
        }
        if ($device->GetDeviceMobileOperator()) {
            echo "Device Operator:\t". $device->GetDeviceMobileOperator(). "\n";
        }
        if ($device->GetDeviceEnableOutboundSMS()) {
            echo "Device Outbound SMS:\t". $device->GetDeviceEnableOutboundSMS(). "\n";
        }

        echo "ActiveSync version:\t".($device->GetASVersion() ? $device->GetASVersion() : "unknown") ."\n";
        echo "First sync:\t\t". strftime("%Y-%m-%d %H:%M", $device->GetFirstSyncTime()) ."\n";
        echo "Last sync:\t\t". ($device->GetLastSyncTime() ? strftime("%Y-%m-%d %H:%M", $device->GetLastSyncTime()) : "never")."\n";
        echo "Total folders:\t\t". count($folders). "\n";
        echo "Short folder Ids:\t". ($device->HasFolderIdMapping() ? "Yes" : "No") ."\n";
        echo "Synchronized folders:\t". $synchedFolders;
        if ($syncedFoldersInProgress > 0) {
            echo " (". $syncedFoldersInProgress. " in progress)";
        }
        echo "\n";
        echo "Synchronized data:\t$folderinfo\n";
        if ($syncedFoldersInProgress > 0) {
            echo "Synchronization progress:\n";
            foreach ($folders as $folderid) {
                $d = $device->GetFolderSyncStatus($folderid);
                if ($d) {
                    $status = "";
                    if ($d['total'] > 0) {
                        $percent = round($d['done'] * 100 / $d['total']);
                        $status = sprintf("Status: %s%d%% (%d/%d)", ($percent < 10) ? " " : "", $percent, $d['done'], $d['total']);
                    }
                    if (strlen($d['name']) > 20) {
                        $d['name'] = substr($d['name'], 0, 18) . "..";
                    }
                    printf("\tFolder: %s Sync: %s %s\n", str_pad($d['name'], 20), str_pad($d['status'], 13), $status);
                }
            }
        }
        echo "Additional Folders:\t$addFoldersTotal\n";
        foreach ($addFolders as $folder) {
            if (strlen($folder['store']) > 14) {
                $folder['store'] = substr($folder['store'], 0, 12) . "..";
            }
            if (strlen($folder['name']) > 20) {
                $folder['name'] = substr($folder['name'], 0, 18) . "..";
            }
            printf("\t%s %s %s %s %s %s\n", str_pad($folder['origin'], 10), str_pad($folder['type'], 8), str_pad($folder['store'], 14), str_pad($folder['name'], 20), $folder['synched'], $folder['additional']);
        }
        echo "Status:\t\t\t";
        switch ($device->GetWipeStatus()) {
            case SYNC_PROVISION_RWSTATUS_OK:
                echo "OK\n";
                break;
            case SYNC_PROVISION_RWSTATUS_PENDING:
                echo "Pending wipe\n";
                break;
            case SYNC_PROVISION_RWSTATUS_REQUESTED:
                echo "Wipe requested on device\n";
                break;
            case SYNC_PROVISION_RWSTATUS_WIPED:
                echo "Wiped\n";
                break;
            default:
                echo "Not available\n";
                break;
        }

        echo "WipeRequest on:\t\t". ($device->GetWipeRequestedOn() ? strftime("%Y-%m-%d %H:%M", $device->GetWipeRequestedOn()) : "not set")."\n";
        echo "WipeRequest by:\t\t". ($device->GetWipeRequestedBy() ? $device->GetWipeRequestedBy() : "not set")."\n";
        echo "Wiped on:\t\t". ($device->GetWipeActionOn() ? strftime("%Y-%m-%d %H:%M", $device->GetWipeActionOn()) : "not set")."\n";
        echo "Policy name:\t\t". ($device->GetPolicyName() ? $device->GetPolicyName() : ASDevice::DEFAULTPOLICYNAME)."\n";

        if ($device->GetKoeVersion()) {
            echo "Kopano Outlook Extension:\n";
            echo "\tVersion:\t". $device->GetKoeVersion() ."\n";
            echo "\tBuild:\t\t". $device->GetKoeBuild() ."\n";
            echo "\tBuild Date:\t". strftime("%Y-%m-%d %H:%M", $device->GetKoeBuildDate()) ."\n";
            echo "\tCapabilities:\t". (count($device->GetKoeCapabilities()) ? implode(',', $device->GetKoeCapabilities()) : 'unknown') ."\n";
        }

        echo "Attention needed:\t";

        if ($device->GetDeviceError()) {
            echo $device->GetDeviceError() ."\n";
        } elseif (!isset($device->ignoredmessages) || empty($device->ignoredmessages)) {
            echo "No errors known\n";
        } else {
            printf("%d messages need attention because they could not be synchronized\n", count($device->ignoredmessages));
            foreach ($device->ignoredmessages as $im) {
                $info = "";
                if (isset($im->asobject->subject)) {
                    $info .= sprintf("Subject: '%s'", $im->asobject->subject);
                }
                if (isset($im->asobject->fileas)) {
                    $info .= sprintf("FileAs: '%s'", $im->asobject->fileas);
                }
                if (isset($im->asobject->from)) {
                    $info .= sprintf(" - From: '%s'", $im->asobject->from);
                }
                if (isset($im->asobject->starttime)) {
                    $info .= sprintf(" - On: '%s'", strftime("%Y-%m-%d %H:%M", $im->asobject->starttime));
                }
                $reason = $im->reasonstring;
                if ($im->reasoncode == 2) {
                    $reason = "Message was causing loop";
                }
                printf("\tBroken object:\t'%s' ignored on '%s'\n", $im->asclass, strftime("%Y-%m-%d %H:%M", $im->timestamp));
                printf("\tInformation:\t%s\n", $info);
                printf("\tReason: \t%s (%s)\n", $reason, $im->reasoncode);
                printf("\tItem/Parent id: %s/%s\n", $im->id, $im->folderid);
                echo "\n";
            }
        }
    }
}
