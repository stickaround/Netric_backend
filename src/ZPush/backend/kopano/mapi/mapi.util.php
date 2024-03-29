<?php
/*
 * Copyright 2005 - 2016  Zarafa B.V. and its licensors
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
 */

/**
 * Function to make a MAPIGUID from a php string.
 * The C++ definition for the GUID is:
 *  typedef struct _GUID
 *  {
 *   unsigned long        Data1;
 *   unsigned short       Data2;
 *   unsigned short       Data3;
 *   unsigned char        Data4[8];
 *  } GUID;
 *
 * A GUID is normally represented in the following form:
 *     {00062008-0000-0000-C000-000000000046}
 *
 * @param String GUID
 */
function makeGuid($guid)
{
    // remove the { and } from the string and explode it into an array
    $guidArray = explode('-', substr($guid, 1, strlen($guid) - 2));

    // convert to hex!
    $data1[0] = intval(substr($guidArray[0], 0, 4), 16); // we need to split the unsigned long
    $data1[1] = intval(substr($guidArray[0], 4, 4), 16);
    $data2 = intval($guidArray[1], 16);
    $data3 = intval($guidArray[2], 16);

    $data4[0] = intval(substr($guidArray[3], 0, 2), 16);
    $data4[1] = intval(substr($guidArray[3], 2, 2), 16);

    for ($i = 0; $i < 6; $i++) {
        $data4[] = intval(substr($guidArray[4], $i * 2, 2), 16);
    }

    return pack("vvvvCCCCCCCC", $data1[1], $data1[0], $data2, $data3, $data4[0], $data4[1], $data4[2], $data4[3], $data4[4], $data4[5], $data4[6], $data4[7]);
}

/**
 * Function to get a human readable string from a MAPI error code
 *
 *@param int $errcode the MAPI error code, if not given, we use mapi_last_hresult
 *@return string The defined name for the MAPI error code
 */
function get_mapi_error_name($errcode = null)
{
    if ($errcode === null) {
        $errcode = mapi_last_hresult();
    }

    if ($errcode !== 0) {
        // get_defined_constants(true) is preferred, but crashes PHP
        // https://bugs.php.net/bug.php?id=61156
        $allConstants = get_defined_constants();

        foreach ($allConstants as $key => $value) {
            /**
             * If PHP encounters a number beyond the bounds of the integer type,
             * it will be interpreted as a float instead, so when comparing these error codes
             * we have to manually typecast value to integer, so float will be converted in integer,
             * but still its out of bound for integer limit so it will be auto adjusted to minus value
             */
            if ($errcode == (int) $value) {
                // Check that we have an actual MAPI error or warning definition
                $prefix = substr($key, 0, 7);
                if ($prefix == "MAPI_E_" || $prefix == "MAPI_W_") {
                    return $key;
                }
            }
        }
    } else {
        return "NOERROR";
    }

    // error code not found, return hex value (this is a fix for 64-bit systems, we can't use the dechex() function for this)
    $result = unpack("H*", pack("N", $errcode));
    return "0x" . $result[1];
}

/**
 * Parses properties from an array of strings. Each "string" may be either an ULONG, which is a direct property ID,
 * or a string with format "PT_TYPE:{GUID}:StringId" or "PT_TYPE:{GUID}:0xXXXX" for named
 * properties.
 *
 * @returns array of properties
 */
function getPropIdsFromStrings($store, $mapping)
{
    $props = [];

    $ids = ["name" => [], "id" => [], "guid" => [], "type" => []]; // this array stores all the information needed to retrieve a named property
    $num = 0;

    // caching
    $guids = [];

    foreach ($mapping as $name => $val) {
        if (is_string($val)) {
            $split = explode(":", $val);

            if (count($split) != 3) { // invalid string, ignore
                trigger_error(sprintf("Invalid property: %s \"%s\"", $name, $val), E_USER_NOTICE);
                continue;
            }

            if (substr($split[2], 0, 2) == "0x") {
                $id = hexdec(substr($split[2], 2));
            } else {
                $id = $split[2];
            }

            // have we used this guid before?
            if (!defined($split[1])) {
                if (!array_key_exists($split[1], $guids)) {
                    $guids[$split[1]] = makeguid($split[1]);
                }
                $guid = $guids[$split[1]];
            } else {
                $guid = constant($split[1]);
            }

            // temp store info about named prop, so we have to call mapi_getidsfromnames just one time
            $ids["name"][$num] = $name;
            $ids["id"][$num] = $id;
            $ids["guid"][$num] = $guid;
            $ids["type"][$num] = $split[0];
            $num++;
        } else {
            // not a named property
            $props[$name] = $val;
        }
    }

    if (empty($ids["id"])) {
        return $props;
    }

    // get the ids
    $named = mapi_getidsfromnames($store, $ids["id"], $ids["guid"]);
    foreach ($named as $num => $prop) {
        $props[$ids["name"][$num]] = mapi_prop_tag(constant($ids["type"][$num]), mapi_prop_id($prop));
    }

    return $props;
}

/**
 * Check wether a call to mapi_getprops returned errors for some properties.
 * mapi_getprops function tries to get values of properties requested but somehow if
 * if a property value can not be fetched then it changes type of property tag as PT_ERROR
 * and returns error for that particular property, probable errors
 * that can be returned as value can be MAPI_E_NOT_FOUND, MAPI_E_NOT_ENOUGH_MEMORY
 *
 * @param long $property Property to check for error
 * @param Array $propArray An array of properties
 * @return mixed Gives back false when there is no error, if there is, gives the error
 */
function propIsError($property, $propArray)
{
    if (array_key_exists(mapi_prop_tag(PT_ERROR, mapi_prop_id($property)), $propArray)) {
        return $propArray[mapi_prop_tag(PT_ERROR, mapi_prop_id($property))];
    } else {
        return false;
    }
}

/******** Macro Functions for PR_DISPLAY_TYPE_EX values *********/
/**
 * check addressbook object is a remote mailuser
 */
function DTE_IS_REMOTE_VALID($value)
{
    return !!($value & DTE_FLAG_REMOTE_VALID);
}

/**
 * check addressbook object is able to receive permissions
 */
function DTE_IS_ACL_CAPABLE($value)
{
    return !!($value & DTE_FLAG_ACL_CAPABLE);
}

function DTE_REMOTE($value)
{
    return (($value & DTE_MASK_REMOTE) >> 8);
}

function DTE_LOCAL($value)
{
    return ($value & DTE_MASK_LOCAL);
}

/**
 * Note: Static function, more like a utility function.
 *
 * Gets all the items (including recurring items) in the specified calendar in the given timeframe. Items are
 * included as a whole if they overlap the interval <$start, $end> (non-inclusive). This means that if the interval
 * is <08:00 - 14:00>, the item [6:00 - 8:00> is NOT included, nor is the item [14:00 - 16:00>. However, the item
 * [7:00 - 9:00> is included as a whole, and is NOT capped to [8:00 - 9:00>.
 *
 * @param $store resource The store in which the calendar resides
 * @param $calendar resource The calendar to get the items from
 * @param $viewstart int Timestamp of beginning of view window
 * @param $viewend int Timestamp of end of view window
 * @param $propsrequested array Array of properties to return
 * @param $rows array Array of rowdata as if they were returned directly from mapi_table_queryrows. Each recurring item is
 *                    expanded so that it seems that there are only many single appointments in the table.
 */
function getCalendarItems($store, $calendar, $viewstart, $viewend, $propsrequested)
{
    $result = [];
    $properties = getPropIdsFromStrings($store, [ "duedate" => "PT_SYSTIME:PSETID_Appointment:0x820e",
                                               "startdate" => "PT_SYSTIME:PSETID_Appointment:0x820d",
                                               "enddate_recurring" => "PT_SYSTIME:PSETID_Appointment:0x8236",
                                               "recurring" => "PT_BOOLEAN:PSETID_Appointment:0x8223",
                                               "recurring_data" => "PT_BINARY:PSETID_Appointment:0x8216",
                                               "timezone_data" => "PT_BINARY:PSETID_Appointment:0x8233",
                                               "label" => "PT_LONG:PSETID_Appointment:0x8214"
                                                ]);

    // Create a restriction that will discard rows of appointments that are definitely not in our
    // requested time frame

    $table = mapi_folder_getcontentstable($calendar);

    $restriction =
        // OR
        [RES_OR,
                 [
                       [RES_AND,    // Normal items: itemEnd must be after viewStart, itemStart must be before viewEnd
                             [
                                   [RES_PROPERTY,
                                         [RELOP => RELOP_GT,
                                               ULPROPTAG => $properties["duedate"],
                                               VALUE => $viewstart
                                               ]
                                         ],
                                   [RES_PROPERTY,
                                         [RELOP => RELOP_LT,
                                               ULPROPTAG => $properties["startdate"],
                                               VALUE => $viewend
                                               ]
                                         ]
                                   ]
                             ],
                       // OR
                       [RES_PROPERTY,
                             [RELOP => RELOP_EQ,
                                   ULPROPTAG => $properties["recurring"],
                                   VALUE => true
                                   ]
                             ]
                       ] // EXISTS OR
                 ];        // global OR

    // Get requested properties, plus whatever we need
    $proplist = [PR_ENTRYID, $properties["recurring"], $properties["recurring_data"], $properties["timezone_data"]];
    $proplist = array_merge($proplist, $propsrequested);
    $propslist = array_unique($proplist);

    $rows = mapi_table_queryallrows($table, $proplist, $restriction);

    // $rows now contains all the items that MAY be in the window; a recurring item needs expansion before including in the output.

    foreach ($rows as $row) {
        $items = [];

        if (isset($row[$properties["recurring"]]) && $row[$properties["recurring"]]) {
            // Recurring item
            $rec = new Recurrence($store, $row);

            // GetItems guarantees that the item overlaps the interval <$viewstart, $viewend>
            $occurrences = $rec->getItems($viewstart, $viewend);
            foreach ($occurrences as $occurrence) {
                // The occurrence takes all properties from the main row, but overrides some properties (like start and end obviously)
                $item = $occurrence + $row;
                array_push($items, $item);
            }
        } else {
            // Normal item, it matched the search criteria and therefore overlaps the interval <$viewstart, $viewend>
            array_push($items, $row);
        }

        $result = array_merge($result, $items);
    }

    // All items are guaranteed to overlap the interval <$viewstart, $viewend>. Note that we may be returning a few extra
    // properties that the caller did not request (recurring, etc). This shouldn't be a problem though.
    return $result;
}
