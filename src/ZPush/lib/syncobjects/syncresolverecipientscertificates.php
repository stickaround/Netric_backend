<?php
/***********************************************
* File      :   syncresolverecipentscertificates.php
* Project   :   Z-Push
* Descr     :   WBXML appointment entities that can be
*               parsed directly (as a stream) from WBXML.
*               It is automatically decoded
*               according to $mapping,
*               and the Sync WBXML mappings
*
* Created   :   28.10.2012
*
* Copyright 2007 - 2013, 2015 - 2016 Zarafa Deutschland GmbH
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

class SyncResolveRecipientsCertificates extends SyncObject
{
    public $status;
    public $certificatecount;
    public $recipientcount;
    public $certificate;
    public $minicertificate;

    public function __construct()
    {
        $mapping = [
            SYNC_RESOLVERECIPIENTS_STATUS                   => [  self::STREAMER_VAR      => "status"],
            SYNC_RESOLVERECIPIENTS_CERTIFICATECOUNT         => [  self::STREAMER_VAR      => "certificatecount"],
            SYNC_RESOLVERECIPIENTS_RECIPIENTCOUNT           => [  self::STREAMER_VAR      => "recipientcount"],

            SYNC_RESOLVERECIPIENTS_CERTIFICATE              => [  self::STREAMER_VAR      => "certificate",
                                                                        self::STREAMER_ARRAY    => SYNC_RESOLVERECIPIENTS_CERTIFICATE,
                                                                        self::STREAMER_PROP     => self::STREAMER_TYPE_NO_CONTAINER],

            SYNC_RESOLVERECIPIENTS_MINICERTIFICATE          => [  self::STREAMER_VAR      => "minicertificate",
                                                                        self::STREAMER_ARRAY    => SYNC_RESOLVERECIPIENTS_MINICERTIFICATE,
                                                                        self::STREAMER_PROP     => self::STREAMER_TYPE_NO_CONTAINER]
        ];

        parent::__construct($mapping);
    }
}
