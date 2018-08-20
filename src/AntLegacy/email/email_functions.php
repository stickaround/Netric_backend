<?php
require_once("src/AntLegacy/contact_functions.awp");
require_once("src/AntLegacy/file_functions.awp");
require_once("src/AntLegacy/user_functions.php");
require_once("src/AntLegacy/parsers/MimeMailParser.php");
require_once("src/AntLegacy/parsers/imc/CImcCalendar.php");
//define('SM_PATH', '/var/www/html/ant.aereus.com/email/');
define('SM_PATH', APPLICATION_PATH."/email/");
require_once('plugins/attachment_tnef/constants.php');
require_once('plugins/attachment_tnef/functions.php');
require_once('plugins/attachment_tnef/class/tnef.php');
//include_once('mimeDecode.php');
// Aereus lib
require_once("src/AntLegacy/aereus.lib.php/CCache.php");

function EmailGetHeaders(&$dbh, $MID, $fltr='', $hdr='')
{
	$headers = array();
	if (!$hdr && $MID > 0)
	{
		$result = $dbh->Query("select orig_header from email_messages where id='$MID'");
		if ($dbh->GetNumberRows($result))
		{
			$hdr = $dbh->GetValue($result, 0, "orig_header");
		}
		$dbh->FreeResults($result);
	}

	if ($hdr)
	{
		$hdr = str_replace("\r", '', $hdr);
		$lines = explode("\n", $hdr);
		foreach ($lines as $line)
		{
			//$fchar = substr($line, 0, 1);
			//if ($fchar != "" && $fchar != "\n" && $fchar != " " && $fchar != "\t")
			if (substr($line, 0, strlen($fltr)) == $fltr)
			{
				$div = strpos($line, ":");

				if ($div)
				{
					$headers[substr($line, 0, $div)] = trim(substr($line, $div+1));
				}
			}
		}
	}

	return $headers;
}

/*
function EmailProcessAddressList($dbh, $USERID, $list)
{
	if ($list)
	{
		$parts = explode(",", $list);
		$list = "";

		foreach ($parts as $part)
		{
			$part = trim($part);
			$part = stripslashes($part);

			if (strpos($part, "@")===false)
			{
				// Check for groups
				if ($USERID)
				{
					$possible_name = trim($part, "\"");
					$result = $dbh->Query("select id from contacts_personal_labels where 
											lower(name)=lower('".$dbh->Escape($possible_name)."') and user_id='$USERID'");
					if ($dbh->GetNumberRows($result))
					{
						$gid = $dbh->GetValue($result, 0, "id");
						$part = EmailExplodeGroup($dbh, $gid);
					}
				}
			}

			$list .= ($list) ? ", $part" : $part;
		}

		$list = EmailCleanAddressList($list);
	}

	return $list;
}

function EmailCleanAddressList($list)
{
	if ($list && strpos($list, ",")!==false)
	{
		$parts = explode(",", $list);
		$list = "";

		foreach ($parts as $part)
		{
			$part = trim($part);
			if ($part != "" && $part != " " && strpos($part, "@")!==false)
				$list .= ($list) ? ", $part" : $part;
		}
	}

	return $list;
}

function EmailExplodeGroup($dbh, $gid)
{
	$ret = "";
	if ($gid)
	{
		$result = $dbh->Query("select id from contacts_personal where id in 
								(select contact_id from contacts_personal_label_mem where label_id='$gid')");
		$num = $dbh->GetNumberRows($result);
		for ($i = 0; $i < $num; $i++)
		{
			$row = $dbh->GetRow($result, $i);
			$addr = ContactGetEmail($dbh, $row['id'], 'default');
			if (!$addr)
				$addr = ContactGetEmail($dbh, $row['id'], 'email2');
			if (!$addr)
				$addr = ContactGetEmail($dbh, $row['id'], 'email');

			if ($addr)
			{
				if ($ret) $ret .= ", ";
				$ret .= $addr;
			}
		}
		$dbh->FreeResults($result);
	}

	return $ret;
}
*/

function EmailCreateFolder($dbh, $PATH, $userid)
{
	$folder_names = explode("/", $PATH);
	$parent_id = null;

	for ($i = 0; $i < count($folder_names); $i++)
	{
		$folder = $folder_names[$i];

		// Get special/root folders
		if ($i == 0)
		{
			if ($folder == "Deleted Items")
				$folder = "Trash";

			if ($folder == "Sent Items")
				$folder = "Sent";

			$parent_id = EmailGetSpecialBoxId($dbh, $userid, $folder);

			if ($parent_id == null)
				return false;
		}
		else
		{
			$query = "select id from email_mailboxes where lower(name)=lower('$folder') and user_id='$userid'";
			if ($parent_id)
				$query .= " and parent_box='$parent_id' ";
			$result = $dbh->Query($query);
			if ($dbh->GetNumberRows($result))
			{
				$parent_id=$dbh->GetValue($result, 0, "id");
				$dbh->FreeResults($result);
			}
			else
			{
				$result = $dbh->Query("insert into email_mailboxes(user_id, name, f_system, parent_box)
											values('$userid', '".$dbh->Escape($folder)."', 
											'f', ".$dbh->EscapeNumber($parent_id).");
									   select currval('email_mailboxes_id_seq') as id;");
				if ($dbh->GetNumberRows($result))
				{
					$parent_id=$dbh->GetValue($result, 0, "id");
					$dbh->FreeResults($result);
				}
				else
				{
					// Could not create a folder in the tree
					return false;
				}
			}
		}
	}

	return $parent_id;
}

function EmailGetFolder($dbh, $PATH, $userid)
{
	$inbx_id = EmailGetSpecialBoxId($dbh, $userid, "Inbox");
	$folder_names = explode("/", $PATH);
	$parent_id = null;

	for ($i = 0; $i < count($folder_names); $i++)
	{
		$folder = $folder_names[$i];

		// Get special/root folders
		if ($i == 0)
		{
			if ($folder == "Deleted Items")
				$folder = "Trash";

			if ($folder == "Sent Items")
				$folder = "Sent";

			$parent_id = EmailGetSpecialBoxId($dbh, $userid, $folder);

			if ($parent_id == null)
				return false;
		}
		else
		{
			$query = "select id from email_mailboxes where lower(name)=lower('$folder') and user_id='$userid'";
			if ($parent_id)
				$query .= " and parent_box='$parent_id' ";
			$result = $dbh->Query($query);
			if ($dbh->GetNumberRows($result))
			{
				$parent_id=$dbh->GetValue($result, 0, "id");
				$dbh->FreeResults($result);
			}
			else
			{
				return false;
			}
		}
	}

	return $parent_id;
}

function EmailGetSpecialBoxId(&$dbh, $userid, $boxname)
{
	$query = "select id from email_mailboxes where user_id='$userid' and name='$boxname'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$id = $row['id'];
	}
	else
	{
		$result = $dbh->Query("insert into email_mailboxes(name, user_id, f_system) values('$boxname', '$userid', 't');
					 			select currval('email_mailboxes_id_seq') as id;");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$id = $row['id'];
		}
		else
		{
			$id = null;
		}
	}
	
	$dbh->FreeResults($result);
	return $id;
}

// Purged messages are sent to this mailbox to be purged later due to perfomance (deletion takes some time)
function EmailGetPurgedBoxId(&$dbh)
{
	$query = "select id from email_mailboxes where name='ANT_SYS_PURGED'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$id = $row['id'];
	}
	else
	{
		$result = $dbh->Query("insert into email_mailboxes(name) values('ANT_SYS_PURGED');
					 			select currval('email_mailboxes_id_seq') as id;");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$id = $row['id'];
		}
		else
		{
			$id = null;
		}
	}
	
	$dbh->FreeResults($result);
	return $id;
}

function EmailGetBoxName(&$dbh, $id)
{
	$name = "Unknown";
	
	if (is_numeric($id))
	{
		$query = "select name from email_mailboxes where id='$id'";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$name = $row['name'];
			$dbh->FreeResults($result);
		}
	}
		
	return $name;
}

function EmailGetBoxNumMessages(&$dbh, $id)
{
	$cnt = "0";
	
	if (is_numeric($id))
	{
		$query = "select count(*) as cnt from email_messages where mailbox_id='$id'";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$cnt = $row['cnt'];
			$dbh->FreeResults($result);
		}
	}
		
	return $cnt;
}

function EmailGetUserIdFromAddress(&$dbh, $address)
{
	$query = "select id from email_accounts where address='$address'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$EMAILUSERID = $row["id"];
	}
	$dbh->FreeResults($result);
	
	return $EMAILUSERID;
}

function EmailGetUserName(&$dbh, $USERID, $type='address', $use=null, $fromusers=false)
{
    $email_address = null;
    
	$query = "select address, name, reply_to from email_accounts where user_id='$USERID'";
	if ($use)
		$query .= " and id='$use'";
	else
		$query .= " and f_default='t'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		switch ($type)
		{
		case 'address':
			$email_address = $row["address"];
			break;
		case 'reply_to':
			if ($row["reply_to"])
				$email_address = $row["reply_to"];
			else
				$email_address = $row["address"];
			break;
		case 'full':
			if ($row["name"])
				$email_address = '"'.$row["name"].'" ';
			
			$email_address .= "<".$row["address"].">";
			break;
		case 'full_rep':
			if ($row["name"])
				$email_address = '"'.$row["name"].'" ';
			
			if ($row["reply_to"])
				$email_address .= "<".$row["reply_to"].">";
			else
				$email_address .= "<".$row["address"].">";
			break;
		}
	}
	$dbh->FreeResults($result);
	
	if (!$email_address && !$fromusers)
		$email_address = UserGetEmail($dbh, $USERID);

	return $email_address;
}

function EmailMimeTypeFromName($filename)
{
	// Get last extension
	$pos = strrpos($filename, ".");
	$ret = "";
	if ($pos !== FALSE)
	{
		$ext = substr($filename, $pos + 1);
		switch(strtolower($ext))
		{
		case "jpg":
			$ret = "image/jpeg";
			break;
		case "gif":
			$ret = "image/gif";
			break;
		case "png":
			$ret = "image/png";
			break;
		case "exe":
			$ret = "application/octet-stream";
			break;
		case "zip":
			$ret = "application/octet-stream";
			break;
		case "txt":
			$ret = "text/plain";
			break;
		case "html":
		case "htm":
			$ret = "text/html";
			break;
		default:
			$ret = "application/octet-stream";
			break;
		}
	}
	else
	{
		$ret = "application/octet-stream";
	}
	
	return $ret;
}

function EmailGetThreadId(&$dbh, $message_id, $userid, $uid=false)
{
	if ($message_id || $uid)
	{
		if ($uid)
		{
			$query = "select thread from email_messages, email_mailboxes where email_messages.id='$uid'
					  and email_messages.mailbox_id = email_mailboxes.id and 
					  email_mailboxes.user_id='$userid';";
		}
		else
		{
			$query = "select thread from email_messages, email_mailboxes where message_id='$message_id'
					  and email_messages.mailbox_id = email_mailboxes.id and 
					  email_mailboxes.user_id='$userid';";
		}
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$retval = $row['thread'];
			$dbh->FreeResults($result);
		}
	}
	else
	{
		$retval = 0;

		/*
		$result = $dbh->Query("select nextval('email_messages_threads_seq') as cnt;");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$retval = $row['cnt'];
			$dbh->FreeResults($result);
		}
		else
			$retval = 0;
		 */
	}	
	return $retval;
}

function EmailNewThread($dbh, $boxid, $f_seen='f')
{
	$retval = null;

	if ($boxid)
	{
		if (!$fseen)
			$fseen = 't';

		$query = "insert into email_threads(f_seen, ts_delivered, time_updated) values('$f_seen', 'now', 'now'); 
				  select currval('email_threads_id_seq') as id;";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$retval = $row['id'];

			if ($retval)
				$dbh->Query("insert into email_thread_mailbox_mem(thread_id, mailbox_id) values('$retval', '$boxid');");
			$dbh->FreeResults($result);
		}
	}

	return $retval;
}

function EmailTimeZone()
{
	$diff_second = date('Z');
	$sign = ($diff_second > 0) ? '+' : '-';
		
	$diff_second = abs($diff_second);
	$diff_hour = floor ($diff_second / 3600);
	$diff_minute = floor (($diff_second-3600*$diff_hour) / 60);
	$zonename = '('.strftime('%Z').')';
	$result = sprintf ("%s%02d%02d %s", $sign, $diff_hour, $diff_minute, $zonename);
	return ($result);
}

function GetThreadCount(&$dbh, $thread_id)
{
	$query = "select count(*) as cnt from email_messages where thread='$thread_id'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$retval = $row['cnt'];
		$dbh->FreeResults($result);
	}
	else
		$retval = 1;
		
	return $retval;
}

function EmailThreadGetAdressColor($senderaddress, $useraddress, $type='expanded')
{
	global $EML_THD_COLORS, $EML_THD_CLRS_CUR_INDX;
	if (!$EML_THD_CLRS_CUR_INDX) 
		$EML_THD_CLRS_CUR_INDX = 0;
	if (!is_array($EML_THD_COLORS))
		$EML_THD_COLORS = array();
	$retval = '';
	$getindex = ($type == 'expanded') ? 0 : 1;
	
	$sederCol = array("EmailThreadSenderExp", "EmailThreadSenderCol");
	$arrCol = array(array("EmailThreadRand1Exp", "EmailThreadRand1Col"), 
					array("EmailThreadRand2Exp", "EmailThreadRand2Col"), 
					array("EmailThreadRand3Exp", "EmailThreadRand3Col"), 
					array("EmailThreadRand4Exp", "EmailThreadRand4Col"), 
					array("EmailThreadRand5Exp", "EmailThreadRand5Col"), 
					array("EmailThreadRand6Exp", "EmailThreadRand6Col"), 
					array("EmailThreadRand7Exp", "EmailThreadRand7Col"), 
					array("EmailThreadRand8Exp", "EmailThreadRand8Col"), 
					array("EmailThreadRand9Exp", "EmailThreadRand9Col"), 
					array("EmailThreadRand10Exp", "EmailThreadRand10Col"));
	
	if ($senderaddress == $useraddress)
	{
		$retval = $sederCol[$getindex];
	}
	else
	{
		foreach ($EML_THD_COLORS as $addr=>$colr)
		{
			if ($addr == $senderaddress)
				$retval = $colr[$getindex];
		}
		// New sender
		if ($retval == '')
		{
			$EML_THD_COLORS[$senderaddress] = $arrCol[$EML_THD_CLRS_CUR_INDX];
			$retval = $arrCol[$EML_THD_CLRS_CUR_INDX][$getindex];
			
			$num_clrs = count($arrCol);
			if ($EML_THD_CLRS_CUR_INDX <= ($num_clrs-1))
			{
				$EML_THD_CLRS_CUR_INDX++;
			}
			else
			{
				$EML_THD_CLRS_CUR_INDX = 0;
			}
		}
	}
	return $retval;
}

function EmailSearchLoop($val_array, $fieldname)
{
	$header = " (";
	if (is_array($val_array))
	{
		foreach ($val_array as $cond)
		{
			if ($cond)
			{
				if ($retval)
					$retval .= " and ";
				if (strpos($fieldname, "attached_data") === false)
					$retval .= " $fieldname ilike '%".strtolower($cond)."%'";
				else
					$retval .= " $fieldname ilike '%".strtolower($cond)."%'";
			}
		}
	}
	$footer = ")";
	return $header.$retval.$footer;
}

function EmailThreadHasAttachment(&$dbh, $TID)
{
	$query = "select sum(num_attachments) as cnt from email_messages
				where email_messages.thread='$TID'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$retval = $row['cnt'];
		$dbh->FreeResults($result);
	}
	else
		$retval = 0;
		
	return $retval;
}

function EmailMessageHasAttachment(&$dbh, $MID)
{
	$query = "select count(*) as cnt from email_message_attachments, email_messages where
				email_message_attachments.message_id=email_messages.id and
				email_message_attachments.disposition = 'attachment'
				and email_messages.id='$MID'";
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetNextRow($result, 0);
		$retval = $row['cnt'];
		$dbh->FreeResults($result);
	}
	else
		$retval = 0;
		
	return $retval;
}

function EmailGetThreadMembers(&$dbh, $thread, $myaddr)
{
	$addrs = array();
	
	$query = "select addr, message_date from (
			  select sent_from as addr, message_date from email_messages where thread='$thread' and sent_from not like '%$myaddr%'
			  union all
			  select sent_from as addr, message_date from email_messages where thread='$thread' and sent_from like '%$myaddr%'
			  order by message_date
		      ) as tbl group by addr, message_date order by message_date DESC";
	$result = $dbh->Query($query);
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		
		$parts = explode(",", $row['addr']);
		foreach ($parts as $part)
		{
			if ($part)
			{
				$tmp = EmailAdressGetDisplay($part);
				$tmp_a = EmailAdressGetDisplay($part, 'address');
				
				if ($tmp_a == $myaddr)
					$tmp = "Me";
					
				$process = true;
				
				foreach ($addrs as $adr)
				{
					if ($adr == $tmp)
						$process = false;
				}

				if ($process)
					$addrs[] = $tmp;
			}
		}
	}
	$dbh->FreeResults($result);

	foreach ($addrs as $adr)
	{
		$retval .= ($retval) ? ', ' : '';
		$retval .= $adr;
	}

	return $retval;
}

function EmailAdressGetDisplay($addr_list, $parts = 'name')
{
	$addresses = array();
    $addr_full = null;
    
	if (strpos($addr_list, ",") !== false)
		$addresses = explode(",", $addr_list);
	else if (strpos($addr_list, ";") !== false)
		$addresses = explode(";", $addr_list);
	else
		$addresses[0] = $addr_list;
		
	foreach($addresses as $address)
	{
		if (strpos($address, "\"") === false && strpos($address, "<") === false)
		{
			$newstr = str_replace("<", "&lt;", $address);
			$newstr = str_replace(">", "&gt;", $newstr);
		}
		else
		{
			// Get ending quotation
			switch($parts)
			{
			case 'all':
				$newstr = str_replace("\"", "", $address);
				$newstr = str_replace("<", "&lt;", $newstr);
				$newstr = str_replace(">", "&gt;", $newstr);
				break;
			case 'address':
				$startpos = strpos($address, "<");
				$endpos = strpos($address, ">");
				if ($endpos !== false && $startpos !== false)
					$newstr = strtolower(substr($address, $startpos+1, $endpos-($startpos+1)));
				else
					$newstr = strtolower($address);
				break;
			case 'name':
				$endpos = strpos($address, "<");
				if ($endpos !== false)
					$newstr = str_replace('"', '', substr($address, 0, $endpos));
				else
					$newstr = $address;
					
				// Make sure we don't have a blank name
				if (!$newstr) $newstr = EmailAdressGetDisplay($address, 'all');
				break;
			}
		}
		
		if ($addr_full)
			$addr_full .= ", ";
		$addr_full .= $newstr;
	}
	return $addr_full;
}

function EmailGetNumNewMessages(&$dbh, $boxnum, $mode="threads")
{
	if (is_numeric($boxnum))
	{
		$cache = CCache::getInstance();
		$exp = 3600; // 1 hour

		$cval = $cache->get($dbh->dbname."/email/newcnt/$boxnum", $exp);

		if ($cval === false)
		{
			$olist = new CAntObjectList($dbh, "email_message");
			$olist->addCondition('and', "mailbox_id", "is_equal", $boxnum);
			$olist->addCondition('and', "flag_seen", "is_equal", 'f');
			$olist->getObjects(0, 1);
			$retval = $olist->getTotalNumObjects();
			$cache->set($dbh->dbname."/email/newcnt/$boxnum", $retval, $exp);

			/*
			$retval = "";
			//$query = "select i_newmessages from email_mailboxes where id='$boxnum';";
			$query = "select count(*) as i_newmessages from email_messages where mailbox_id='$boxnum' and flag_seen is not true and f_deleted is not true;";
			$result = $dbh->Query($query);
			if ($dbh->GetNumberRows($result))
			{
				$row = $dbh->GetNextRow($result, 0);
				$retval = $row['i_newmessages'];
				$cache->set($dbh->dbname."/email/newcnt/$boxnum", $row['i_newmessages'], $exp);
				$dbh->FreeResults($result);
			}
			*/
		}
		else
		{
			$retval = $cval;
		}
	}	
	return $retval;
}

function EmailMimeParseAlternative(&$dbh, $partid, $msg, $get_type = NULL, $boundry = NULL)
{
	$processed_db = false;
	// The lower the number the higher the preference "text/html" will come first
	$types_viewable = array("text/calendar", "text/html", "text/enriched", "text/plain");
	
	// Check to see if message is parsed into parts
	if ($partid)
	{
		$result = $dbh->Query("select id, content_type, encoding, disposition, filename, attached_data, size, name from 
								email_message_attachments where parent_id='$partid' 
								and lower(disposition) != 'attachment' 
								order by id DESC");
		$num = $dbh->GetNumberRows($result);
		for ($i = 0; $i < $num; $i++)
		{
			// In multipart/alternative the last item should be the one to display (ORDER BY ID DESC); but not always
			$row = $dbh->GetNextRow($result, $i);
				
			switch(strtolower($row['content_type']))
			{
			case "multipart/alternative":
				$buf =  EmailMimeParseAlternative($dbh, $row['id'], "");
				break;
			default:
				foreach ($types_viewable as $tpe)
				{
					if (strtolower($row['content_type']) == $tpe)
					{
						if ($row['encoding'] == "quoted-printable")
							$buf = quoted_printable_decode($row['attached_data']);
						else if ($row['encoding'] == "base64")
							$buf = base64_decode($row['attached_data']);
						else
							$buf = $row['content_type'].$row['encoding']."<br>".$row['attached_data'];

						$i = $num;
						break;
					}
				}
				break;
			}
			$processed_db = true;
		}
	}
	
	if (!$processed_db)
	{
		if (!$boundry)
		{
			// Get the boundry
			$boundry = substr($msg, 0, strpos($msg, "\n", 1));
		}

		// Get message parts
		$str_parts = explode($boundry, $msg);

		foreach ($str_parts as $msgpart)
		{
			if ($msgpart && $msgpart != "--\n")
			{
				//Get header
				$header_end = strpos($msgpart, "\n\n");
				$header = substr($msgpart, 0, $header_end);
				
				// Get Content Type
				$ctype_pos = strpos($header, "Content-Type: ");
				if ($ctype_pos !== false)
				{
					$ctype_end = strpos($header, ';', $ctype_pos);
					if ($ctype_end !== false)
						$ctype = substr($header, $ctype_pos + 14, $ctype_end - ($ctype_pos + 14));
					else
						$ctype = substr($header, $ctype_pos + 14);
				}
				// Get boundary
				$boundary_pos = strpos($header, "boundary=");
				if ($boundary_pos !== false)
				{
					$boundary_end = strpos($header, ';', $boundary_pos);
					if ($boundary_end !== false)
						$boundary = substr($header, $boundary_pos + 9, $ctype_end - ($boundary_pos + 9));
					else
						$boundary = substr($header, $boundary_pos + 9);
						
					$boundary = str_replace("\"", '', $boundary);
				}
				// Get Transfer Encoding
				$enc_pos = strpos($header, "Content-Transfer-Encoding:");
				if ($enc_pos !== false)
				{
					$enc_pos_end = strpos($header, '\n', $enc_pos);
					if ($enc_pos_end !== false)
						$encoding = substr($header, $enc_pos + 27, $enc_pos_end - ($enc_pos + 27));
					else
						$encoding = substr($header, $enc_pos + 27);
				}
				
				// Leave out plain text
				switch(strtolower($ctype))
				{
				case 'text/enriched': // Fall through
				case 'text/html':
					$msg_body = substr($msgpart, $header_end+1);
					$buf = stripslashes(($encoding != '7bit') ? quoted_printable_decode($msg_body) : $msg_body);
					break;
				default:
					$buf = "";
					break;
				}
			}
		}
	}
	return $buf;
}

function EmailReplaceInlineImages(&$dbh, $MID, &$tmp_body)
{
	$query = "select id, content_id from email_message_attachments where message_id='$MID' and content_id != '' order by id";
	$result = $dbh->Query($query);
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		$tmp_body = str_replace("src=\"cid:".str_replace("<", '', str_replace(">", '', $row['content_id']))."\"", 
						"src=\"/legacy/email/attachment.awp?attid=".$row['id']."\"", $tmp_body);
		$tmp_body = str_replace("src='cid:".str_replace("<", '', str_replace(">", '', $row['content_id']))."'", 
						"src=\"/legacy/email/attachment.awp?attid=".$row['id']."\"", $tmp_body);
	}
	$dbh->FreeResults($result);


	// Deal with MS J with Wingdings
	$tmp_body = str_replace("font-family:Wingdings'>J</span>", "font-family:Wingdings'><img src='/images/icons/emoticons/smile_ant_small.gif' /></span>", $tmp_body);
	$tmp_body = str_replace("font-family:Wingdings\">J</span>", "font-family:Wingdings\"><img src='/images/icons/emoticons/smile_ant_small.gif' /></span>", $tmp_body);
	$tmp_body = str_replace("font-family: Wingdings; color: black;\">J</span>", "font-family:Wingdings;\"><img src='/images/icons/emoticons/smile_ant_small.gif' /></span>", $tmp_body);
	$tmp_body = str_replace("font-family:Wingdings'>L</span>", "font-family:Wingdings'><img src='/images/icons/emoticons/sad_ant_small.gif' /></span>", $tmp_body);
	$tmp_body = str_replace("font-family:Wingdings\">L</span>", "font-family:Wingdings\"><img src='/images/icons/emoticons/sad_ant_small.gif' /></span>", $tmp_body);
	$tmp_body = str_replace("font-family: Wingdings; color: black;\">L</span>", "font-family:Wingdings\"><img src='/images/icons/emoticons/sad_ant_small.gif' /></span>", $tmp_body);

	//$str = preg_replace( "/(font-family:)?(s)Wingdings;http:\/\/|http%3a%2f%2f)?((www)+(s)?.[^<>\s]+)/i", "\${1}<a href=\"http://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	
	return $tmp_body;
}

function EmailGetAttachmentIcon($filename)
{
	return "/images/icons/filetypes/".UserFilesGetTypeIcon(substr($filename, 0, strrpos($filename, '.')));
}

function EmailGetAttachmentName(&$dbh, $ATTID)
{
	if ($ATTID)
	{
		$result = $dbh->Query("select name from email_message_attachments where id='$ATTID'");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$dbh->FreeResults($result);
			return $row['name'];
		}
		else
			return false;
	}
}

function EmailGetAttachmentType(&$dbh, $ATTID)
{
	if ($ATTID)
	{
		$result = $dbh->Query("select content_type from email_message_attachments where id='$ATTID'");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$dbh->FreeResults($result);
			return $row['content_type'];
		}
		else
			return false;
	}
}

if (!function_exists('quoted_printable_encode'))
{
	function quoted_printable_encode($sString , $line_max = 76)
	{
		/* strip CR */
		$sString = preg_replace("~[\r]*~", "", $sString);

		/* encode characters */
		$sString = preg_replace("~([\x01-\x08\x10-\x1F\x3D\x7F-\xFF])~e",
						"sprintf('=%02X', ord('\\1'))", $sString);

		/* encode blanks and tabs */
		$sString = preg_replace("~([\x09\x20])\n~e",
						"sprintf('=%02X\n', ord('\\1'))", $sString);

		/* split string */
		$aStrParts = explode("\n", $sString);
		$nNumLines = count($aStrParts);
		for($i = 0; $i < $nNumLines; $i++)
				{
						/* if longer than 76 adds a soft-line break */
						if(strlen($aStrParts[$i]) > 76)
								$aStrParts[$i] = preg_replace("~((.){73,76}((=[0-9A-Fa-f]{2})|([^=]{0,3})))~",
												"\\1=\n", $aStrParts[$i]);
				}

		return(implode("\r\n", $aStrParts));
	}
}

function EmailActivateLinks($str)
{
	$str = str_replace("<a", "<a target='_blank'", $str);
	$str = str_replace("<A", "<a target='_blank'", $str);

	$str = preg_replace("/(^|>|\s)(http:\/\/|https:\/\/|https%3a%2f%2f)+(.[^<>\s]+)/i", "\${1}<a href=\"\${2}\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	/*
	$str = preg_replace('/(^|>|\s)(http:\/\/|http%3a%2f%2f)?((www)+(s)?.[^<>\s]+)/i', 
						"\${1}<a href=\"http://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	$str = preg_replace('/(^|>|\s)(https:\/\/|https%3a%2f%2f)+((www)+(s)?.[^<>\s]+)/i', 
						"\${1}<a href=\"https://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	 */
	
	/*
	$str = preg_replace( '/(^|>|\s)(http:\/\/|http%3a%2f%2f)?((www)+(s)?.[^<>\s]+)/i', "\${1}<a href=\"http://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	$str = preg_replace( '/(^|>|\s)(https:\/\/|https%3a%2f%2f)+((www)+(s)?.[^<>\s]+)/i', "\${1}<a href=\"https://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	 */

	//$str = preg_replace( '/(?<![">])\b(?:(?:https?|ftp|file)://|www\.|ftp\.)[-A-Z0-9+&@#/%=~_|$?!:,.]*[A-Z0-9+&@#/%=~_|$]/i', "<a href=\"\\0\" target='_blank'>\\0</a>", $str);
	//$str = preg_replace( "/(?<!\"|')((http|ftp)+(s)?:\/\/[^<>\s]+)/i", "<a href=\"\\0\" target='_blank'>\\0</a>", $str);
	//$str = preg_replace( "/(?<!\"|'|http:\/\/|https:\/\/|http%3a%2f%2f)((www)+(s)?.[^<>\s]+)/i", "<a href=\"http://\\0\" target='_blank'>\\0</a>", $str);
	//$str = preg_replace('/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|](?![^<>]*(?:>|<\/a>))/i', "<a href=\"#\" onclick=\"open_url('\\0')\";return false;>\\0</a>", $str);
	//$str = preg_replace( "/(^|>)(http:\/\/|https:\/\/|http%3a%2f%2f)?((www)+(s)?.[^<>\s]+)/i", "<a href=\"http://\\0\" target='_blank'>\\0</a>", $str);
	//$str = preg_replace( "/(^|>)www\\.[a-z0-9-._]\\.[a-z]/i", "<a href=\"http://\\0\" target='_blank'>\\0</a>", $str);

	//$str = preg_replace( "/(^|>)(http:\/\/|http%3a%2f%2f)?((www)+(s)?.[^<>\s]+)/i", "\${1}<a href=\"http://\${3}\" target='_blank'>\${2}\${3}</a>", $str);
	//$str = preg_replace( "/(^|>)(https:\/\/|https%3a%2f%2f)+((www)+(s)?.[^<>\s]+)/i", "\${1}<a href=\"https://\${3}\" target='_blank'>\${2}\${3}</a>", $str);

    return $str;
}

function EmailFindQuoted($m_body)
{
	$arrQuotes = array("----Original Message----", 
						"---- Original Message ----", 
						"--------------- Original Message ---------------",
						"<span class=\"gmail_quote\"", );
	$last_pos = false;
	foreach ($arrQuotes as $qbreak)
	{
		$orig_pos = strpos($m_body, $qbreak);
		if ($orig_pos !== false)
		{
			if ($orig_pos <= $last_pos || !$last_pos)
				$last_pos = $orig_pos;
		}
	}
	
	return $last_pos;
}

function EmailGetCachedMessageBody(&$dbh, $MID)
{
	$ret = false;

	if ($MID)
	{
		$result = $dbh->Query("select message from email_message_parsed where message_id='$MID'");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result);
			$ret = $row['message'];
		}
		$dbh->FreeResults($result);
	}
	return $ret;
}

function EmailGetMessageBody(&$dbh, $MID, $content_type, &$m_body, &$att_buf, $parent_id=NULL)
{
	$headers = EmailGetHeaders($dbh, $MID, "X-ANT-");
	// Get all inline attachments
	$query = "select id, content_type, encoding, disposition, filename, attached_data, name, file_id,
				size from email_message_attachments where message_id='$MID' 
				and (content_type like 'text/%' or content_type like 'multipart/%' or content_type like 'message/%')
				and ".(($parent_id) ? "parent_id='$parent_id'" : "parent_id is NULL")." order by id";
	$result = $dbh->Query($query);
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		//echo $row['content_type']." - '".$row['encoding']."' - $parent_id<br />";
		switch(strtolower($row['content_type']))
		{
		case 'text/plain':
			if ($row['disposition'] == 'attachment')
			{
				$att_buf.="<br><table border='0'><tr><td>
							<a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' target='_blank'>
							<img border='0' src='/images/icons/email_attachments/generic.gif'></a>
							</td><td valign='top'>
							Filename: <a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' 
							target='_blank'>".$row['filename']."</a><br>
							Filesize: ".number_format($row['size']/1000, 0)."k</td></tr></table>";
			}
			else
			{
				$txt_body = "";

				switch (strtolower(trim($row['encoding'])))
				{
				case "quoted-printable":
					$txt_body .= quoted_printable_decode($row['attached_data']);
					break;
				case "base64":
					$txt_body .= base64_decode($row['attached_data']);
					break;
				default:
					$txt_body .= stripslashes($row['attached_data']);
					break;
				}
				$txt_body .= "\n";

				$txt_body = htmlspecialchars($txt_body);

				if ($content_type == "multipart/alternative")
					$m_body = str_replace("\n", "<br>", $txt_body);
				else
					$m_body .= str_replace("\n", "<br>", $txt_body);;	
			}
			break;
		case 'multipart/report':
			if ($row['disposition'] == 'attachment')
			{
				$att_buf.="<br><table border='0'><tr><td>
							<a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' target='_blank'>
							<img border='0' src='/images/icons/email_attachments/generic.gif'></a>
							</td><td valign='top'>
							Filename: <a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' 
							target='_blank'>".$row['filename']."</a><br>
							Filesize: ".number_format($row['size']/1000, 0)."k</td></tr></table>";
			}
			else
			{
				EmailGetMessageBody($dbh, $MID, $row['content_type'], $m_body, $att_buf, $row['id']);
			}
			break;
		case 'message/delivery-status':
			$txt_body = "";
			switch (strtolower($row['encoding']))
			{
			case "quoted-printable":
				$txt_body .= quoted_printable_decode($row['attached_data']);
				break;
			case "base64":
				$txt_body .= base64_decode($row['attached_data']);
				break;
			default:
				$txt_body .= $row['attached_data'];
				break;
			}
			$txt_body = htmlspecialchars($txt_body);
			$m_body .= str_replace("\n", "<br>", $txt_body);
			break;
		case 'message/rfc822':
			$txt_body = "";
			EmailGetMessageBody($dbh, $MID, $row['content_type'], $txt_body, $att_buf, $row['id']);
			$m_body .= $txt_body;
			break;
		case 'text/html':
			if ($row['disposition'] == 'attachment')
			{
				if (strlen($row['filename']))
					$attname = $row['filename'];
				else
					$attname = $row['name'];
				
				if (!$attname)
					$attname = "Untitiled";
					
				$att_buf.="<br><table border='0'><tr><td>
							<a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' target='_blank'>
							<img border='0' src='".EmailGetAttachmentIcon($attname)."'></a>
							</td><td valign='top' style='padding-left:10px;'>
							Filename: <a href='/legacy/email/attachment.awp?attid=".$row['id']."&disposition=attachment' 
							target='_blank'>".$attname."</a><br>
							Filesize: ".number_format($row['size']/1000, 0)."k</div><div style='clear:both;'>
							</td></tr></table>";
			}
			else
			{
				$tmp_body .= "<div>";

				switch (strtolower(trim($row['encoding'])))
				{
				case "quoted-printable":
					$tmp_body .= quoted_printable_decode($row['attached_data']);
					break;
				case "base64":
					$tmp_body .= base64_decode($row['attached_data']);
					break;
				default:
					$tmp_body .= stripslashes($row['attached_data']);
					break;
				}
				$tmp_body .= "</div>";

				if ($content_type == "multipart/alternative")
					$m_body = EmailReplaceInlineImages($dbh, $MID, $tmp_body);
				else
					$m_body .= EmailReplaceInlineImages($dbh, $MID, $tmp_body);
			}
			break;
		case 'text/calendar':
			switch (strtolower($row['encoding']))
			{
			case "quoted-printable":
				$buf = stripslashes(quoted_printable_decode($row['attached_data']));
				break;
			case "base64":
				$buf = base64_decode($row['attached_data']);
				break;
			default:
				$buf = stripslashes($row['attached_data']);
				break;
			}

			$tmp_body = "<div>";
			$vcal = new CImcCalendar();
			$vcal->parseText(trim($buf));
			if ($vcal->getNumEvents())
			{
				$ev = $vcal->getEvent(0);

				// Adjust times
				$str_ts_start = date("m/d/Y h:i A", $ev->ts_start);
				$str_ts_end = date("m/d/Y h:i A", $ev->ts_end);
				$oldtz = date_default_timezone_get();
				date_default_timezone_set($ev->timezone);
				$ts_start = strtotime($str_ts_start);
				$ts_end = strtotime($str_ts_end);
				date_default_timezone_set($oldtz);

				$tmp_body .= "<div>";
				if (!$headers['X-ANT-CAL-EID'] && $ev->status!='CONFIRMED' && $ev->status!='DECLINED' && $vcal->method!='REPLY')
				{
					//$tmp_body .= ButtonCreate("Accept", "EmlAcceptInv()", "b2");
					//$tmp_body .= ButtonCreate("Decline", "EmlDeclineInv()", "b3");
					$tmp_body .= "<a href='javascript:void(0);' onclick='EmlAcceptInv()'>Accept</a>";
					$tmp_body .= " | ";
					$tmp_body .= "<a href='javascript:void(0);' onclick='EmlDeclineInv()'>Decline</a>";
					$tmp_body .= "<br />";
					$tmp_body .= "<br />";
				}
				else if ($headers['X-ANT-CAL-EID'])
				{
					$tmp_body .= "<p class='notice'>You have accepted this event invitation.</p>";
				}

				$tmp_body .= "<form name='email_accept_inv' method='post'>";
				$tmp_body .= "<input type='hidden' name='subject' value=\"".$ev->summary."\">";
				$tmp_body .= "<input type='hidden' name='description' value=\"".$ev->description."\">";
				$tmp_body .= "<input type='hidden' name='location' value=\"".$ev->location."\">";
				$tmp_body .= "<input type='hidden' name='uid' value=\"".$ev->uid."\">";
				$tmp_body .= "<input type='hidden' name='start_date' value=\"".date("m/d/Y", $ts_start)."\">";
				$tmp_body .= "<input type='hidden' name='start_time' value=\"".date(" h:i A", $ts_start)."\">";
				$tmp_body .= "<input type='hidden' name='end_date' value=\"".date("m/d/Y", $ts_end)."\">";
				$tmp_body .= "<input type='hidden' name='end_time' value=\"".date(" h:i A", $ts_end)."\">";
				$tmp_body .= "<input type='hidden' name='timezone' value=\"".$ev->timezone."\">";
				$tmp_body .= "</form>";
				$tmp_body .= "</div>";

				$tmp_body .= "<div style='font-weight:bold;'>".rawurldecode($ev->summary)."</div>";
				$tmp_body .= rawurldecode($ev->organizer);
				$tmp_body .= "<div>When: ".date("l, F j, Y h:i A", $ts_start)." - ".date("l jS \of F Y h:i A", $ts_end)."</div>";
				$tmp_body .= "<div>Location: ".$ev->location."</div>";
				$tmp_body .= "<div>Attendees: ";
				for ($m = 0; $m < $ev->getNumAttendees(); $m++)
				{
					$att = $ev->getAttendee($m);
					$tmp_body .= str_replace("MAILTO:", '', $att->name).", ";
				}
				$tmp_body .= "</div>";

				//$tmp_body .= "<div>";
				//$tmp_body .= str_replace("\\n", "<br />", $ev->description);
				$tmp_body .= $ev->description;

				// TEMP: This is added to deal with a CImcCalendar bug and not handling \n
				$tmp_body = str_replace("nnClick the link below to respond.nn", "<br /><br />Click the link below to respond.<br /><br />", $tmp_body);
			}

			$tmp_body .= "</div>";

			if ($content_type == "multipart/alternative")
				$m_body = $tmp_body;
			else
				$m_body .= $tmp_body;

			break;
		case 'application/ms-tnef':
			$tnef = base64_decode($row['attached_data']);
			$attachment = new TnefAttachment(false);
			$fresult = $attachment->decodeTnef($tnef);
			$tnef_files = &$attachment->getFilesNested();
			//print_r($tnef_files); // See the format of the returned array
			for ($m = 0; $m < count($tnef_files); $m++)
			{
				$file = $tnef_files[$m];

				if ($file->getType() != "application/rtf")
				{
					$lnk = "/legacy/email/attachment.awp?attid=".$row['id']."&tnefatt=$m&disposition=attachment";
					$att_buf.="<br><table border='0'><tr><td>
								<a href='$lnk'><img border='0' src='".EmailGetAttachmentIcon($file->getName())."'></a>
								</td><td valign='top' style='padding-left:10px;'>
								Filename: <a href='$lnk'>".$file->getName()."</a> [".$file->getType()."]<br>
								Filesize: ".number_format($file->getSize()/1000, 0)."k</div><div style='clear:both;'>
								</td></tr></table>";
				}
    		}

			break;
		case 'multipart/related':
			EmailGetMessageBody($dbh, $MID, $row['content_type'], $m_body, $att_buf, $row['id']);
			$m_body = EmailReplaceInlineImages($dbh, $MID, $m_body);
			break;
		case 'multipart/alternative':
			//$tmp_body = EmailMimeParseAlternative($dbh, $row['id'], $row['attached_data']);
			//break;
		case 'multipart/appledouble':
		case 'multipart/mixed':
			EmailGetMessageBody($dbh, $MID, $row['content_type'], $m_body, $att_buf, $row['id']);
			break;
		case 'image/gif':
		case 'image/png':
		case 'image/jpeg':
		case 'image/pjpeg':
			if ($row['disposition'] == 'attachment')
			{
				// If not already detached, then detach and store binary files in file system
				if (!$row['file_id'])
				{
					//$fid = EmailDetachAttachemnt($dbh, $row['id']);
					$lnk = "/legacy/userfiles/file_download.awp?fid=".$row['file_id'];
					$lnk_thumb = "/legacy/userfiles/getthumb_by_id.awp?fid=".$row['file_id']."&iw=100";
				}
				else
				{
					//$fid = $row['file_id'];
					$lnk = "/legacy/email/attachment.awp?attid=".$row['id'];
					$lnk_thumb = "/legacy/email/attachment.awp?attid=".$row['id'];
				}
				
				$att_buf.="<br><table border='0'><tr><td>
							<img border='0' style='width:100px;' src='$lnk_thumb'>
							</td><td valign='top'>
							Filename: <a target='_blank' href='$lnk'>".$row['filename']."</a><br>
							Filesize: ".number_format($row['size']/1000, 0)."k<br>
							Actions: <a href='$lnk'>download</a> | 
							<a href='$lnk_thumb'
							target='_blank'>view</a></td></tr></table>";
			}
			else if ($row['disposition'] != 'inline' || $content_type!='multipart/related')
				$m_body .= "<br><div><img border='0' src='/legacy/email/attachment.awp?attid=".$row['id']."'></div>";

			break;
		default:
			// Get Name
			if (strlen($row['filename']))
				$attname = $row['filename'];
			else
				$attname = $row['name'];

			if (!$attname && !$parent_id)
			{
				$attname = EmailGetAttBodyDesc($dbh, $MID);
				if ($attname)
					$dbh->Query("update email_message_attachments set name='".$dbh->Escape($attname)."' where id=".$row['id']."");
			}
			else if (!$attname)
				$attname = "Untitiled";
			
			// If not already detached, then detach and store binary files in file system
			if (!$row['file_id'])
			{
				$lnk = "/legacy/email/attachment.awp?attid=".$row['id'];
				//$fid = EmailDetachAttachemnt($dbh, $row['id']);
			}
			else
			{
				//$fid = $row['file_id'];
				$lnk = "/legacy/userfiles/file_download.awp?fid=".$row['file_id'];
			}
			
			$att_buf.="<br><table border='0'><tr><td>
							<a href='$lnk'>
							<img border='0' src='".EmailGetAttachmentIcon($attname)."'></a>
							</td><td valign='top' style='padding-left:10px;'>
							Filename: <a target='_blank' href='$lnk'>".$attname."</a><br>
							Filesize: ".number_format($row['size']/1000, 0)."k</div><div style='clear:both;'>
							</td></tr></table>";
			
			break;
		}
	}
	$dbh->FreeResults($result);


	if (!$m_body)
	{
	}
}

function EmailGetAttBodyDesc($dbh, $mid)
{
	$attname = "Untitled";

    if($mid > 0)
    {
        $result = $dbh->Query("select orig_header from email_messages where id='$mid'");
        if ($dbh->GetNumberRows($result))
        {
            $hdr = $dbh->GetValue($result, 0, "orig_header");
            $vars = EmailGetHeaders($dbh, $mid, "Content-Description", $hdr);
            $attname = $vars['Content-Description'];
        }
    }

	return $attname;
}

function EmailDetachAttachemnt(&$dbh, $ATTID)
{
	global $USERID;
	$fid = null;

	if ($USERID)
	{
		$CATID = UserFilesGetSpecialCatId($dbh, $USERID, "Email Attachments");
		$aid = UserFilesGetCatAccount($dbh, $CATID);
		
		// Check if cat id dir exists
		UserFilesCheckDirectory($USERID, $aid);

		$target_dir = AntConfig::getInstance()->data_path."/$aid/userfiles/$USERID";
		
		$query = "select content_type, encoding, disposition, filename, attached_data, name from 
				  email_message_attachments where id = '$ATTID'";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			if ($row['encoding'] == 'base64')
				$content = base64_decode($row['attached_data']);
			else
				$content = $row['attached_data'];
			
			$attname = "att".$ATTID;
			// Get Name
			if (strlen($row['filename']))
				$attname = $row['filename'];
			else
				$attname = $row['name'];
						
			if (!$attname)
			{
				$attname = "Untitiled";
			}
			else if(strlen($attname) > 64) // Shorten name if too long
			{
				$periodpos = strrpos($attname, '.');
				if ($periodpos !== false)
				{
					$ext_size = strlen($attname) - ($periodpos);
					$attname_1 = substr($attname, 0, (64 - $ext_size));
					$attname_2 = substr($attname, $periodpos);
					$attname = $attname_1 . $attname_2;
				}
				else
				{
					$attname = substr($attname, 0, 64);
				}
			}
				
			// Get File name
			$fname = "att".$ATTID;
			
			// Upload the file to correct directory
			$fh = fopen("$target_dir/$fname", "w");
			fwrite($fh, $content);
			fclose($fh);
			
			// Get file size (pointer or reference)
			$filesize = filesize("$target_dir/$fname");

			// Get extension
			$pos = strrpos($attname, ".");
			if ($pos !== FALSE)
				$ext = substr($attname, $pos + 1);
			else
				$ext = $attname;
			
			if(strlen($ext) > 32) // Shorten name if too long
				$ext = substr($ext, 0, 32);

			if (file_exists("$target_dir/$fname"))
			{
				$fid_res = $dbh->Query("insert into user_files(file_name, file_title, user_id,
										 category_id, file_size, time_updated, file_type)
										 values('$fname', '".$dbh->Escape($attname)."', ".db_CheckNumber($USERID).", 
												".db_CheckNumber($CATID).",
												'".round($filesize * .1, 0)."', 'now', '".strtolower($ext)."');
										 select currval('user_files_id_seq') as fid;");
				 if ($dbh->GetNumberRows($fid_res))
				 {
					 $fid_row = $dbh->GetNextRow($fid_res, 0);
					 $dbh->FreeResults($fid_res);
					 $fid = $fid_row['fid'];
				 }
			}
		}
		$dbh->FreeResults($result);
	}
	
	// Update file_id and delete content
	if ($fid)
	{
		$dbh->Query("update email_message_attachments set file_id='$fid', 
					 attached_data='' where id='$ATTID'");
	}
		
	return $fid;
}

function EmailGetOriginalFid(&$dbh, $MID)
{
	$fid = 0;

	if ($MID)
	{
		$result = $dbh->Query("select file_id from email_message_original where message_id='$MID' and file_id is not null");
		if ($dbh->GetNumberRows($result))
			$fid = $dbh->GetValue($result, 0, "file_id");
	}

	return $fid;
}

function EmailDetachOriginal(&$dbh, $OID)
{
	global $USERID;
	$fid = null;

	if ($USERID)
	{
		$CATID = UserFilesGetSpecialCatId($dbh, $USERID, "Email Attachments");
		$aid = UserFilesGetCatAccount($dbh, $CATID);
		
		// Check if cat id dir exists
		UserFilesCheckDirectory($USERID);

		$target_dir = AntConfig::getInstance()->data_path."/$aid/userfiles/$USERID";
		
		$query = "select message, file_id from email_message_original where id = '$OID'";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$content = $row['message'];
			
			// Get Name
			$fname = "fullmsg$OID.eml";
				
			// Upload the file to correct directory
			$fh = fopen("$target_dir/$fname", "w");
			fwrite($fh, $content);
			fclose($fh);
			
			// Get file size (pointer or reference)
			$filesize = filesize("$target_dir/$fname");

			// Get extension
			$ext = "eml";
			
			$fid_res = $dbh->Query("insert into user_files(file_name, file_title, user_id,
									 category_id, file_size, time_updated, file_type)
									 values('$fname', '".$dbh->Escape($fname)."', ".db_CheckNumber($USERID).", 
											".db_CheckNumber($CATID).",
											'".round($filesize * .1, 0)."', 'now', '".strtolower($ext)."');
									 select currval('user_files_id_seq') as fid;");
			 if ($dbh->GetNumberRows($fid_res))
			 {
				 $fid_row = $dbh->GetNextRow($fid_res, 0);
				 $dbh->FreeResults($fid_res);
				 $fid = $fid_row['fid'];
			 }
			 
			// Now give full access to creator
			// TODO: Security
		}
		$dbh->FreeResults($result);
	}
	
	// Update file_id and delete content
	if ($fid)
	{
		$dbh->Query("update email_message_original set file_id='$fid', 
					 message='' where id='$OID'");
	}
		
	return $fid;
}

function EmailGetBodySnippet(&$dbh, $THREAD, $BOX, $SENTBOX)
{
	if (is_numeric($THREAD))
	{
		if ($BOX == $SENTBOX)
			$query = "select id, content_type from email_messages where thread='$THREAD' order by message_date DESC";
		else
			$query = "select id, content_type from email_messages where thread='$THREAD' and mailbox_id != '$SENTBOX' order by message_date DESC";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0); // select only the latest
			$dbh->FreeResults($result);
			$MID = $row['id'];
				
			$query = "select id, content_type, encoding, disposition, filename, 
						substring(email_message_attachments.attached_data, 0, 256) as msg
						from email_message_attachments where message_id='$MID' and disposition != 'attachment' and 
						(lower(content_type)='text/plain' or lower(content_type) = 'text/html') order by id";
			$result = $dbh->Query($query);
			if ($dbh->GetNumberRows($result))
			{
				$row = $dbh->GetNextRow($result, 0);
				$dbh->FreeResults($result);
				// Decode message if necessary
				if (strtolower($row['encoding']) == 'quoted-printable')
					$msg = quoted_printable_decode($row['msg']);
				else
					$msg = $row['msg'];
			}
		}
	}
	else
	{
	}
	
	return $msg;
}

function EmailAttachmentGetContents(&$dbh, $ATTID)
{
	$attres = $dbh->Query("select encoding, attached_data from email_message_attachments where id='$ATTID'");
	if ($dbh->GetNumberRows($attres))
	{
		$attrow = $dbh->GetNextRow($attres, 0);
		if ($attrow['encoding'] == 'base64')
			$contents = base64_decode($attrow["attached_data"]);
		else
			$contents = $attrow["attached_data"];
		$dbh->FreeResults($attres);
	}
	return $contents;
}

function EmailGetBodyAntfiles(&$dbh, &$body)
{
	global $settings_localhost;

	$arr_files = array();
		
	$break = false;
	$arr_check = array("src=\"/legacy/userfiles/file_download.awp?view=1&amp;fid="=>'"',
					   "src='/legacy/userfiles/file_download.awp?view=1&amp;fid="=>"'",
					   "src=\"/files/images/"=>'"',
					   "src='/files/images/"=>"'",
				   	   "src=\"http://$settings_localhost/legacy/userfiles/file_download.awp?view=1&amp;fid="=>'"',
					   "src='http://$settings_localhost/legacy/userfiles/file_download.awp?view=1&amp;fid="=>"'");
	// attachment_image.awp?attid=
	// /userfiles/file_download.awp?view=1&fid=
	foreach ($arr_check as $chec_beg=>$check_end)
	{
		$cur_pos = 0;
		while (1) 
		{
			$cur_pos = strpos($body, $chec_beg, $cur_pos);
				
			if ($cur_pos !== false)
			{
				$arr_file_attribs = array();
				$cur_pos = $cur_pos  + strlen($chec_beg);
				$cur_pos_end = strpos($body, $check_end, $cur_pos);
				if ($cur_pos_end !== false)
				{
					$FID = substr($body, $cur_pos, $cur_pos_end - $cur_pos);
					$arr_file_attribs[0] = "file";
					$arr_file_attribs[1] = $FID;
					$arr_file_attribs[2] = "image/".UserFilesGetFileType($dbh, $FID);
					$arr_file_attribs[3] = UserFilesGetFileName($dbh, $FID);
					$arr_file_attribs[4] = $arr_file_attribs[3]."@".$FID;
				}
				else
					break;
				
				$arr_files[] = $arr_file_attribs;
				unset($arr_file_attribs);
				$cur_pos = $cur_pos_end;
			}
			else
				break;
		}
	}

	// Local ant files
	$break = false;
	$arr_check = array("src=\"/images/"=>'"',
						"src='/images/"=>"'",
						"src=\"http://$settings_localhost/images/"=>'"',
						"src='http://$settings_localhost/images/"=>"'");
	// attachment_image.awp?attid=
	// /userfiles/file_download.awp?view=1&fid=
	foreach ($arr_check as $chec_beg=>$check_end)
	{
		$cur_pos = 0;
		while (1) 
		{
			$cur_pos = strpos($body, $chec_beg, $cur_pos);
				
			if ($cur_pos !== false)
			{
				$arr_file_attribs = array();
				$cur_pos = $cur_pos  + strlen($chec_beg);
				$cur_pos_end = strpos($body, $check_end, $cur_pos);
				if ($cur_pos_end !== false)
				{
					$path = substr($body, $cur_pos, $cur_pos_end - $cur_pos);
					$fparts = explode("/", $path);
					$fname = $fparts[count($fparts)-1];
					$arr_file_attribs[0] = "file_local";
					$arr_file_attribs[1] = $path;
					$arr_file_attribs[2] = UserFilesGetContentType($fname);
					$arr_file_attribs[3] = $fname;
					$arr_file_attribs[4] = $arr_file_attribs[3];
				}
				else
					break;
				
				$arr_files[] = $arr_file_attribs;
				unset($arr_file_attribs);
				$cur_pos = $cur_pos_end;
			}
			else
				break;
		}
	}

	$arr_check = array("src=\"/legacy/email/attachment.awp?attid="=>'"',
					   "src='/legacy/email/attachment.awp?attid="=>"'");
	foreach ($arr_check as $chec_beg=>$check_end)
	{
		$cur_pos = 0;
		while (1) 
		{
			$cur_pos = strpos($body, $chec_beg, $cur_pos);
				
			if ($cur_pos !== false)
			{
				$arr_file_attribs = array();
				
				$cur_pos = $cur_pos  + strlen($chec_beg);
				$cur_pos_end = strpos($body, $check_end, $cur_pos);
				if ($cur_pos_end !== false)
				{
					$ATTID = substr($body, $cur_pos, $cur_pos_end - $cur_pos);
					$arr_file_attribs[0] = "attachment";
					$arr_file_attribs[1] = $ATTID;
					$arr_file_attribs[2] = EmailGetAttachmentType($dbh, $ATTID);
					$arr_file_attribs[3] = EmailGetAttachmentName($dbh, $ATTID);
					$arr_file_attribs[4] = $arr_file_attribs[3]."@".$ATTID;
				}
				else
					break;
					
				$arr_files[] = $arr_file_attribs;
				unset($arr_file_attribs);
				$cur_pos = $cur_pos_end;;
			}
			else
				break;
		}
	}
	
	return $arr_files;
}

function EmailEmbedBodyAntfiles(&$dbh, &$body, &$antfiles)
{
	global $settings_localhost;

	foreach ($antfiles as $emfile)
	{
		// Enter embeded files here
		switch($emfile[0])
		{
		case "file":
			$cont_id = $emfile[4];
			$body = str_replace("src=\"/legacy/userfiles/file_download.awp?view=1&amp;fid=".$emfile[1]."\"", "src=\"$cont_id\"", $body);
			$body = str_replace("src='/legacy/userfiles/file_download.awp?view=1&amp;fid=".$emfile[1]."'", "src='$cont_id'", $body);
			$body = str_replace("src=\"/files/images/".$emfile[1]."\"", "src=\"$cont_id\"", $body);
			$body = str_replace("src='/files/images/".$emfile[1]."'", "src='$cont_id'", $body);
			$body = str_replace("src=\"http://$settings_localhost/legacy/userfiles/file_download.awp?view=1&amp;fid=".$emfile[1]."\"", "src=\"$cont_id\"", $body);
			$body = str_replace("src='http://$settings_localhost/legacy/userfiles/file_download.awp?view=1&amp;fid=".$emfile[1]."'", "src='$cont_id'", $body);
			break;
		case "attachment":
			$cont_id = $emfile[4];
			$body = str_replace("src=\"/legacy/email/attachment.awp?attid=".$emfile[1]."\"", "src=\"$cont_id\"", $body);
			$body = str_replace("src='/legacy/email/attachment.awp?attid=".$emfile[1]."'", "src='$cont_id'", $body);
			break;
		}
	}
}

function EmailDeleteMessage(&$dbh, $USERID, $un_ident, $selid)
{
	$usr = new AntUser($dbh, $USERID);
	if ($un_ident == "thread")
		$eml = new CAntObject($dbh, "email_thread", $selid);
	else
		$eml = new CAntObject($dbh, "email_message", $selid);
	$emp->remove();
	// First delete any attachments that might have been saved
	/*
	$result = $dbh->Query("select id, flag_seen, mailbox_id from email_messages where $un_ident='$selid'");
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		// Decrement new message counter if not yet seen
		if ('t' == $row['flag_seen'])
			EmailMailboxSetNewmessages($dbh, $row['mailbox_id'], "-");

		$res2 = $dbh->Query("select file_id from email_message_attachments where 
							 message_id='".$row['id']."' and file_id is not NULL");
		$num2 = $dbh->GetNumberRows($res2);
		for ($j = 0; $j < $num2; $j++)
		{
			$row2 = $dbh->GetNextRow($res2, $j);
			if ($row2['file_id'])
				UserFilesRemoveFile($dbh, $row2['file_id'], $USERID);	
		}
		$dbh->FreeResults($res2);

		// Delete archived original message if exists
		$res2 = $dbh->Query("select file_id from email_message_original where 
							 message_id='".$row['id']."' and file_id is not NULL");
		$num2 = $dbh->GetNumberRows($res2);
		for ($j = 0; $j < $num2; $j++)
		{
			$row2 = $dbh->GetNextRow($res2, $j);
			if ($row2['file_id'])
				UserFilesRemoveFile($dbh, $row2['file_id'], $USERID);	
		}
		$dbh->FreeResults($res2);

		$res2 = $dbh->Query("delete from email_message_original where 
							 message_id='".$row['id']."'");
	}
	$dbh->FreeResults($result);
	
	$dbh->Query("delete from email_messages where $un_ident='$selid'");
	if ($un_ident == "thread")
		$dbh->Query("delete from email_threads where id='$selid'");
	 */
}

function EmailMoveMessage(&$dbh, $un_ident, $selid, $to_id, $sent=null, $trash_id=null, $curr_id=null, $removeCurrent=true)
{
	if ($un_ident == "thread")
	{
		$obj = new CAntObject($dbh, "email_thread", $selid);
	}
	else
	{
		$obj = new CAntObject($dbh, "email_message", $selid);
	}

    /*$query = "select id, flag_seen, mailbox_id, thread from 
                           email_messages where $un_ident='$selid' 
                           ".(($sent)?" and mailbox_id!='$sent'":"");
	$result = $dbh->Query($query);
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		$mid = $row['id'];
		// Decrement new message counter if not yet seen
		if ('f' == $row['flag_seen'])
		{
			EmailMailboxSetNewmessages($dbh, $row['mailbox_id'], "-");
			EmailMailboxSetNewmessages($dbh, $to_id, "+");
		}
        
		if (!$curr_id)
			$curr_id = $row['mailbox_id'];

		$obj = new CAntObject($dbh, "email_message", $mid);
		$obj->setValue('mailbox_id', $to_id);
		if ($trash_id && $to_id == $trash_id && $obj->getValue('f_deleted')!='t')
		{
			$obj->remove();
		}
		else if ($obj->getValue("f_deleted") == 't' && $trash_id && $to_id != $trash_id)
		{
			$obj->unremove();
		}
		else
		{
			$obj->save(false);
		}
		//$dbh->Query("update email_messages set mailbox_id='$to_id' where id='$mid'");
	}
	$dbh->FreeResults($result);*/

	if ($un_ident == "thread")
	{
		$obj = new CAntObject($dbh, "email_thread", $selid);
		if ($trash_id && $to_id == $trash_id && $obj->getValue('f_deleted')!='t')
		{
			$obj->remove();
		}
		else
		{
			if ($obj->getValue("f_deleted") == 't' && $trash_id && $to_id != $trash_id)
				$obj->unremove();

			if ($curr_id && $removeCurrent)
            {
                $obj->removeMValue('mailbox_id', $curr_id);
            }

            /*echo $to_id;
            exit();*/
            
			$obj->setMValue('mailbox_id', $to_id);
			$obj->save(false);
		}
        
		//$dbh->Query("update email_threads set mailbox_id='$to_id' where id='$selid'");
	}
	/*else if ($row['thread'])
	{
		$obj = new CAntObject($dbh, "email_thread", $row['thread']);
		if ($trash_id && $to_id == $trash_id && $obj->getValue('f_deleted')!='t')
		{
			$obj->remove();
		}
		else
		{
			if ($obj->getValue("f_deleted") == 't' && $trash_id && $to_id != $trash_id)
				$obj->unremove();

			if ($curr_id)
				$obj->removeMValue('mailbox_id', $curr_id);
                
			$obj->setMValue('mailbox_id', $to_id);
			$obj->save(false);
		}
		//$obj->setValue('mailbox_id', $to_id);
		//$obj->save(false);
		//$dbh->Query("update email_threads set mailbox_id='$to_id' where id='".$row['thread']."'");
	}*/
}

function EmailMarkThread($dbh, $thread_id, $markas)
{
	if ($thread_id)
	{
		$result = $dbh->Query("select id from email_messages where thread='$thread_id'");
		$num = $dbh->GetNumberRows($result);
		for ($i = 0; $i < $num; $i++)
		{
			$row = $dbh->GetNextRow($result, $i);
			$mid = $row['id'];

			if ("read" == $markas)
				EmailMarkMessageRead($dbh, $mid);
			if ("unread" == $markas)
				EmailMarkMessageUnread($dbh, $mid);
			if ("flagged" == $markas)
				EmailMarkMessageFlagged($dbh, $mid);
			if ("unflagged" == $markas)
				EmailMarkMessageUnflagged($dbh, $mid);
		}
		$dbh->FreeResults($result);
		
		$obj = new CAntObject($dbh, "email_thread", $thread_id);

		if ("read" == $markas)
			$obj->setValue('f_seen', 't');
			//$dbh->Query("update email_threads set f_seen='t' where id='$thread_id'");
		if ("unread" == $markas)
			$obj->setValue('f_seen', 'f');
			//$dbh->Query("update email_threads set f_seen='f' where id='$thread_id'");
		if ("flagged" == $markas)
			$obj->setValue('f_flagged', 't');
			//$dbh->Query("update email_threads set f_flagged='t' where id='$thread_id'");
		if ("unflagged" == $markas)
			$obj->setValue('f_flagged', 'f');
			//$dbh->Query("update email_threads set f_flagged='f' where id='$thread_id'");

		$obj->save(false);
	}
}

function EmailGetMailboxFromMessage($dbh, $mid)
{
	if ($mid)
	{
		$result = $dbh->Query("select mailbox_id from email_messages where id='$mid'");
		$num = $dbh->GetNumberRows($result);
		for ($i = 0; $i < $num; $i++)
		{
			$row = $dbh->GetNextRow($result, $i);
			$box = $row['mailbox_id'];
		}
		$dbh->FreeResults($result);
	}

	return $box;
}

function EmailMarkMessageRead($dbh, $message_id)
{
	global $USER;
	$user = ($USER) ? $USER : null;
	if ($message_id)
	{
		$obj = new CAntObject($dbh, "email_message", $message_id, $user);
		if ($obj->getValue('flag_seen') != 't')
		{
			$obj->setValue('flag_seen', 't');
			EmailMailboxSetNewmessages($dbh, $obj->getValue('mailbox_id'), '-');
			if ($obj->getValue('thread'))
			{
				$obj_th = new CAntObject($dbh, "email_thread", $obj->getValue('thread'), $user);
				$obj_th->setValue('f_seen', 't');
				$obj_th->save(false);
				//$dbh->Query("update email_threads set f_seen='t' where id='".$obj->getValue('thread')."'");
			}
			$obj->save(false);
		}
	}
}

function EmailMarkMessageUnread($dbh, $message_id)
{
	global $USER;
	$user = ($USER) ? $USER : null;
	if ($message_id)
	{
		$obj = new CAntObject($dbh, "email_message", $message_id, $user);
		if ($obj->getValue('flag_seen') != 'f')
		{
			$obj->setValue('flag_seen', 'f');
			EmailMailboxSetNewmessages($dbh, $obj->getValue('mailbox_id'), '+');
			if ($obj->getValue('thread'))
			{
				$obj_th = new CAntObject($dbh, "email_thread", $obj->getValue('thread'), $user);
				$obj_th->setValue('f_seen', 'f');
				$obj_th->save(false);
				//$dbh->Query("update email_threads set f_seen='f' where id='".$obj->getValue('thread')."'");
			}
			$obj->save(false);
		}
	}
}

function EmailMarkMessageFlagged($dbh, $message_id)
{
	global $USER;
	$user = ($USER) ? $USER : null;

	if ($message_id)
	{
		$obj = new CAntObject($dbh, "email_message", $message_id, $user);
		if ($obj->getValue('flag_flagged') != 't')
		{
			$obj->setValue('flag_flagged', 't');
			if ($obj->getValue('thread'))
			{
				$obj_th = new CAntObject($dbh, "email_thread", $obj->getValue('thread'), $user);
				$obj_th->setValue('f_flagged', 't');
				$obj_th->save(false);
			}
			$obj->save(false);
		}
		/*
		$query = "select mailbox_id, flag_flagged, thread from email_messages where id='$message_id' and flag_flagged is not true";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$mailbox_id = $row['mailbox_id'];
			$dbh->FreeResults($result);


			$dbh->Query("update email_messages set flag_flagged='t' where id='$message_id'");

			if ($row['thread'])
				$dbh->Query("update email_threads set f_flagged='t' where id='".$row['thread']."'");
		}
		 */
	}
}

function EmailMarkMessageUnflagged($dbh, $message_id)
{
	global $USER;
	$user = ($USER) ? $USER : null;

	if ($message_id)
	{
		$obj = new CAntObject($dbh, "email_message", $message_id, $user);
		if ($obj->getValue('flag_flagged') != 'f')
		{
			$obj->setValue('flag_flagged', 'f');
			if ($obj->getValue('thread'))
			{
				$obj_th = new CAntObject($dbh, "email_thread", $obj->getValue('thread'), $user);
				$obj_th->setValue('f_flagged', 'f');
				$obj_th->save(false);
			}
			$obj->save(false);
		}
		/*
		$query = "select mailbox_id, flag_flagged, thread from email_messages where id='$message_id' and flag_flagged is not true";
		$result = $dbh->Query($query);
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetNextRow($result, 0);
			$mailbox_id = $row['mailbox_id'];
			$dbh->FreeResults($result);


			$dbh->Query("update email_messages set flag_flagged='t' where id='$message_id'");

			if ($row['thread'])
				$dbh->Query("update email_threads set f_flagged='t' where id='".$row['thread']."'");
		}
		 */
	}
}

// Action will be - or + char
function EmailMailboxSetNewmessages($dbh, $mailbox_id, $action)
{
	$cache = CCache::getInstance();
	$exp = 3600; // 1 hour

	$cache->remove($dbh->dbname."/email/newcnt/$mailbox_id");

	/*
	$cval = $cache->get($dbh->dbname."/email/newcnt/$mailbox_id", $exp);

	if ("+" == $action)
	{
		$cache->set($dbh->dbname."/email/newcnt/$mailbox_id", ($cval+1), $exp);
	}
	else if ("x" == $action)
	{
		$cache->set($dbh->dbname."/email/newcnt/$mailbox_id", 0, $exp);
	}
	else // Assume '-'
	{
		$cache->set($dbh->dbname."/email/newcnt/$mailbox_id", ($cval-1), $exp);
	}
	 */

	/*
	if ("+" == $action)
	{
		$dbh->Query("update email_mailboxes set i_newmessages = (i_newmessages + 1) 
					 where id='$mailbox_id';");
	}
	else if ("x" == $action)
	{
		$dbh->Query("update email_mailboxes set i_newmessages = '0' where id='$mailbox_id';");
	}
	else // Assume '-'
	{
		$dbh->Query("update email_mailboxes set i_newmessages = (i_newmessages - 1) 
					 where id='$mailbox_id' and i_newmessages>0;");
	}
	 */
}

function EmailSetThreadCount($dbh, $thread_id, $action="+")
{
	if ("+" == $action)
	{
		$dbh->Query("update email_threads set num_messages = (num_messages + 1) 
					 where id='$thread_id';");
	}
	else // Assume '-'
	{
		$dbh->Query("update email_threads set num_messages = (num_messages - 1) 
					 where id='$thread_id';");
	}
}

function EmailGetMailboxPathId($dbh, $userid, $path)
{
	$parts = explode(".", $path);
	$parent = "";
	$mbox_id = false;

	for ($i = 0; $i < count($parts); $i++)
	{
		$mbox = $parts[$i];
		if ($mbox)
		{
			$mbox_id = EmailGetMailboxIdFromName($dbh, $userid, $mbox, $parent);
			$parent = $mbox_id;
		}
	}

	return $mbox_id;
}

function EmailInitFromAccounts($dbh, $USERID)
{
	if (!$dbh->GetNumberRows($dbh->Query("select id from email_accounts where user_id='".$USERID."'")))
	{
		$result = $dbh->Query("select address, name, reply_to from email_accounts where user_id='$USERID' ");
		if ($dbh->GetNumberRows($result))
		{
			$row = $dbh->GetRow($result, 0);
			$addr = $row['address'];
			$reply_to = $row['reply_to'];
			$dbh->Query("insert into email_accounts(user_id, name, address, reply_to, f_default) 
							values('$USERID', '".$dbh->Escape(UserGetFullName($dbh, $USERID))."', '".$dbh->Escape($addr)."', '".$dbh->Escape($reply_to)."', 't');");
		}
	}
}

function EmailGetMailboxIdFromName($dbh, $userid, $name, $parent_id)
{
	$query = "select id from email_mailboxes where user_id='$userid' and name='".$dbh->Escape($name)."'";
	if ($parent_id)
		$query .= " and parent_box='$parent_id'";
	else
		$query .= " and parent_box is null";
	
	$result = $dbh->Query($query);
	if ($dbh->GetNumberRows($result))
	{
		return $dbh->GetValue($result, 0, "id");
	}

	return false;
}

function EmailMsgIndex($dbh, $mid)
{
	$result = $dbh->Query("select id, thread from email_messages where id='$mid' and f_indexed='f'");
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		$thread_ind = "";

		$res2 = $dbh->Query("select attached_data from email_message_attachments where message_id='".$row['id']."' and lower(content_type) like '%text%'");
		$num2 = $dbh->GetNumberRows($res2);
		for ($j = 0; $j < $num2; $j++)
		{
			$row2 = $dbh->GetNextRow($res2, $j);
			$bdy = $row2['attached_data'];
			$bdy = str_replace("<br>", " ", $bdy);
			$bdy = strip_tags($bdy);
			$bdy = str_replace("and", "", $bdy);
			$bdy = str_replace("&nbsp;", " ", $bdy);
			$bdy = str_replace(";", "", $bdy);
			$bdy = str_replace(":", "", $bdy);
			$bdy = str_replace(".", "", $bdy);
			$bdy = str_replace(",", "", $bdy);
			$bdy = str_replace("\r", "", $bdy);
			$bdy = str_replace("\n", "", $bdy);

			$indexed_bdy = "";
			$bdy_parts = explode(" ", $bdy);
			foreach ($bdy_parts as $wrd)
			{
				if ($wrd && strpos($indexed_bdy, $wrd)===false)
				{
					$indexed_bdy .= $wrd.",";
				}
			}

			$thread_ind .= "$indexed_bdy,";
			
		}
		$dbh->FreeResults($res2);

		$res2 = $dbh->Query("select name, filename from email_message_attachments where message_id='".$row['id']."' and (name!='' or filename!='')");
		$num2 = $dbh->GetNumberRows($res2);
		for ($j = 0; $j < $num2; $j++)
		{
			$row2 = $dbh->GetNextRow($res2, $j);

			if ($row2['name'])
				$thread_ind .= $row2['name'].",";
			if ($row2['filename'])
				$thread_ind .= $row2['filename'].",";
		}
		$dbh->FreeResults($res2);

		$indexed_bdy = "";
		$bdy_parts = explode(",", $thread_ind);
		foreach ($bdy_parts as $wrd)
		{
			if ($wrd && strpos($indexed_bdy, $wrd)===false)
			{
				$indexed_bdy .= $wrd.",";
			}
		}

		$dbh->Query("update email_messages set f_indexed='t' where id='".$row['id']."'");
		$dbh->Query("update email_messages set keywords='".$dbh->Escape($indexed_bdy)."' where id='".$row['id']."'");

		if ($row['thread'])
		{
			$dbh->Query("update email_threads set keywords='' where id='".$row['thread']."' and keywords is null");
			$dbh->Query("update email_threads set keywords=keywords||',".$dbh->Escape($indexed_bdy)."' where id='".$row['thread']."'");
		}
	}
	$dbh->FreeResults($result);
}

function EmailDecodeMimeStr($string, $charset="UTF-8" )
{
      $newString = '';
      $elements=imap_mime_header_decode($string);
      for($i=0;$i<count($elements);$i++)
      {
        if ($elements[$i]->charset == 'default')
          $elements[$i]->charset = $charset; //'iso-8859-1';

		if (mb_check_encoding($elements[$i]->text, $charset))
        	$newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
		else
			$newString .= utf8_encode($elements[$i]->text);
      }
      return $newString;
} 

function EmailFixUtf8Encoding($in_str)
{
  $cur_encoding = mb_detect_encoding($in_str) ;
  if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8"))
    return $in_str;
  else
    return utf8_encode($in_str);
}

function EmailProcessAttachments($dbh, $MID)
{
	$result = $dbh->Query("select email_messages.id, email_messages.subject, 
								 to_char(email_messages.message_date, 'YYYY-MM-DD HH12:MI:SS AM') as date,
								 email_message_original.message
								 from email_messages, email_message_original where 
								 email_messages.id='".$MID."' and email_message_original.message_id=email_messages.id
								 and email_message_original.message is not null");
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetRow($result, 0);

		if (strlen($row['message']))
		{
			// $contact->anniversary = date("Y-m-d", strtotime($row['anniversary']));
			$message = Mail_mimeDecode::decode(array('decode_headers' => true, 'decode_bodies' => true, 
												'include_bodies' => true, 'input' => stripslashes($row['message']), 
												'crlf' => "\n"));
		} 

		// Attachments are only searched in the top-level part
		if(isset($message->parts)) 
		{
			$dbh->Query("delete from email_message_attachments where message_id='$MID' and disposition='attachment'");
			foreach($message->parts as $part) 
			{
				if(isset($part->disposition) && $part->disposition == "attachment") 
				{
					if ($part->headers['content-transfer-encoding'] == 'base64')
						$content = base64_decode($part->body);
					else
						$content = $part->body;

					$dbh->Query("insert into email_message_attachments(message_id, content_type, encoding, disposition, 
																	   filename, attached_data, size, name, content_id)
									   values('$MID', '".$part->ctype_primary."/".$part->ctype_secondary."', 
									   	   'base64',
										   '".$part->disposition."', '".$dbh->Escape($part->d_parameters['filename'])."', 
										   '".base64_encode($content)."', '".strlen($content)."', 
										   '".$dbh->Escape($part->ctype_parameters['name'])."', 
										   '".$dbh->Escape($part->ctype_parameters['content-id'])."')");
				}
			}
		}
	}
}

function EmailReprocess($dbh, $MID, $fixret=false)
{
	$result = $dbh->Query("select email_messages.id, email_messages.subject, 
								 to_char(email_messages.message_date, 'YYYY-MM-DD HH12:MI:SS AM') as date, email_message_original.message
								 from email_messages, email_message_original where 
								 email_messages.id='".$MID."' and email_message_original.message_id=email_messages.id
								 and email_message_original.message is not null");
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetRow($result, 0);

		if (strlen($row['message']))
		{
			// $contact->anniversary = date("Y-m-d", strtotime($row['anniversary']));
			$message = Mail_mimeDecode::decode(array('decode_headers' => true, 'decode_bodies' => false, 
												'include_bodies' => true, 'input' => stripslashes($row['message']), 
												'crlf' => "\r\n"));
		} 

		// Check for and delete detached attachments
		$res2 = $dbh->Query("select file_id from email_message_attachments where message_id='$MID' and file_id is not null");
		$num2 = $dbh->GetNumberRows($res2);
		for ($j = 0; $j < $num2; $j++)
		{
			$fid = $dbh->GetValue($res2, $j, "file_id");
			UserFilesRemoveFile($dbh, $fid, null, false, true);
		}

		// Delete all parts
		$dbh->Query("delete from email_message_attachments where message_id='$MID'");

		EmailReprocessParts($dbh, $MID, $message, false, $fixret);
	}

	$dbh->Query("update email_message_original set antmail_version='2'");
}

function EmailReprocessParts($dbh, $mid, $message, $parent=null, $fixret=false) 
{
	$content = $message->body;

	if ($fixret)
	{
		$content = str_replace("\r\n", "<%ANTTMPCRNL%>", $content);
		$content = str_replace("\n", "\r\n", $content);
		$content = str_replace("<%ANTTMPCRNL%>", "\r\n", $content);
	}

	$result = $dbh->Query("insert into email_message_attachments(message_id, content_type, boundary, encoding, disposition, 
														   filename, attached_data, size, name, content_id, header, parent_id)
						   values('$mid', '".$message->ctype_primary."/".$message->ctype_secondary."', 
							   '".$dbh->Escape(trim($message->ctype_parameters['boundary']))."',
							   '".trim($message->headers['content-transfer-encoding'])."',
							   '".trim($message->disposition)."', '".$dbh->Escape($message->d_parameters['filename'])."', 
							   '".$dbh->Escape($content)."', '".strlen($content)."', 
							   '".$dbh->Escape($message->ctype_parameters['name'])."', 
							   '".$dbh->Escape($message->ctype_parameters['content-id'])."',
							   '".$dbh->Escape(trim($message->header))."',
						   	   ".(($parent)?"'$parent'":"NULL")."); select currval('email_message_attachments_id_seq') as id;");
	if ($dbh->GetNumberRows($result))
		$partId = $dbh->GetValue($result, 0, "id");
	
	// Add parts
	if (isset($message->parts) && $partId)
	{
		foreach($message->parts as $part) 
		{
			EmailReprocessParts($dbh, $mid, $part, $partId);
		}
	}
}

function EmailAssocInArray($ent, $arr)
{
	if (!is_array($arr))
	{
		$arr = array();
		return false;
	}
	else
	{
		return in_array($ent, $arr);
	}
}

/*
 * Replace with Ant::getEmailDefaultDomain
function EmailGetDefaultDomain($dbh, $account)
{
	$acname = settingsGetAccountName();

	// Get deafault domain
	$query	= "select domain from email_domains where default_domain='t' and account_id='$account'";
	$dom_result = $dbh->Query($query);
	if ($dbh->GetNumberRows($dom_result))
	{
		$row = $dbh->GetNextRow($dom_result, 0);
		$def_domain = $row["domain"];
	}
	else
	{
		$def_domain = $acname.".".$settings_localhost_root;
		$dbh->Query("insert into email_domains(domain, default_domain, account_id) values('$def_domain', 't', '$account');");
	}
	$dbh->FreeResults($dom_result);

	return $def_domain;
}
*/

/**
 * Get raw data for attachment if not a file
 *
 * This should be phased out with time, currently it is only used in /controllers/EmailController::getMessageBody
 */
function EmailGetAttachmentData(&$dbh, $aid)
{
	$ret = NULL;
	$result = $dbh->Query("select encoding, attached_data from 
							email_message_attachments where id='$aid'");
	if ($dbh->GetNumberRows($result))
	{
		$row = $dbh->GetRow($result, 0);
		if ($row['encoding'] == 'base64')
			$ret = base64_decode($row['attached_data']);
		else
			$ret = $row['attached_data'];
	}
	return $ret;
}

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsert
*
*	Purpose:	Inject an email from a file into ANT
*
*	Params:		CDatabase $dbh = reference to database
*				AntUser $user = user object
*				string $filepath = path to mime file to import
*				string parser = force a specific parser - used for testing
**************************************************************************************
function EmailInsert(&$dbh, &$user, $filepath, $parser=null)
{
	// Determine what parser to use
	if(function_exists('mailparse_msg_parse') && (null==$parser || "mailparse"==$parser)) 
	{
		// pecl mailparse is preferred but only available on *nix
		return EmailInsert_mailParse($dbh, $user, $filepath); // Preferred mailparse extension
	}
	else
	{
		return EmailInsert_mimeDecode($dbh, $user, $filepath); // failsafe php extension
	}
}
*/

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsert_mimeDecode
*
*	Purpose:	Inject an email from a file into ANT using mail_mimeDecode to parse
*				Mail_mimeDecode is ineffecient because it requires you read the entire
*				message into memory to parse. This is fine for small messages but ANT
*				will accept up to 2GB emails so memory can be a limitation here. It is preferrable
*				to use the php mimeParse extension and read is incrementally in to the resource
*
*	Params:		CDatabase $dbh = reference to database
*				AntUser $user = user object
*				string $filepath = path to mime file to import
**************************************************************************************
function EmailInsert_mimeDecode($dbh, &$user, $filepath)
{
	if (file_exists($filepath))
	{
		$rfc822Msg = file_get_contents($filepath);
		//$rfc822Msg = preg_replace("/(?<!\\n)\\r+(?!\\n)/", "\r\n", $rfc822Msg); //replace just CR with CRLF 
	}
	else
	{
		return false; // Fail
	}

	$mobj = new Mail_mimeDecode($rfc822Msg);
	$message = $mobj->decode(array('decode_headers' => true, 'decode_bodies' => true, 
								   'include_bodies' => true, 'charset' => 'utf-8'));
	$plainbody = EmailgetMimeBody_mimeDecode($message, "plain");
    $htmlbody = EmailgetMimeBody_mimeDecode($message, "html");

	// Create new mail object and save it to ANT
	$email = new CEmailMessage($dbh, null, $user->id, $user->accountId);
	$email->setGroup("Inbox");
	$email->f_seen = 'f';
	$email->setHeader("Subject", trim($message->headers['subject']));
	$email->setHeader("From", trim($message->headers['from']));
	$email->setHeader("To", trim($message->headers['to']));
	$email->setHeader("Cc", trim($message->headers['cc']));
	$email->setHeader("Bcc", trim($message->headers['bcc']));
	$email->setHeader("In-reply-to", trim($message->headers['in-reply-to']));
	$email->setHeader("X-Spam-Flag", trim($message->headers['x-spam-flag']));
	$email->setHeader("Message-ID", trim($message->headers['message-id']));
	$email->setBody($plainbody, "plain");
	if ($htmlbody)
		$email->setBody($htmlbody, "html");

	//echo "pb: ".$plainbody."\n";
	//echo "hb: ".$htmlbody."\n";
	//echo "Subject: ".$message->headers['subject']."\n";

	$ret = EmailInsertAttachments_mimeDecode($message, $email);

	// Process filters/autoresponders before saving
	EmailInsertProcessFilters($dbh, $email, $user);

	$mid = $email->save();

	return $mid;
}
 */

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsertAttachments_mimeDecode
*
*	Purpose:	Add attachments from mime message using mimeDecode parser
*
*	Params:		Mail_mimeDecode::part $mimePart = mime part to process
*				CEmailMessage $email = ANT email message being saved
**************************************************************************************
function EmailInsertAttachments_mimeDecode(&$mimePart, &$email)
{
	if(isset($mimePart->disposition) || strcasecmp($mimePart->disposition,"attachment")==0 || $mimePart->ctype_primary=="image")  
	{
		/*
		 * 	1. Write attachment to temp file
		 *	-------------------------------------------------------
		 * 	It is important to use streams here to try and keep the attachment out of memory if possible
		 * 	The parser should alrady have decoded the bodies for us so no need to use base64_decode or
		 * 	anything like that
		$tmpFolder = (AntConfig::getInstance()->data_path) ? AntConfig::getInstance()->data_path."/tmp" : sys_get_temp_dir();
		if (!file_exists($tmpFolder))
			@mkdir($tmpFolder, 0777);
		$tmpFile = tempnam($tmpFolder, "ematt");
		$handle = fopen($tmpFile, "w");
		fwrite($handle, trim($mimePart->body));
		fclose($handle);

		if (!file_exists($tmpFile))
			return false;

		// 2. Add the attachment to the CEmailMessage object
		$att = $email->addAttachment($tmpFile, $mimePart->d_parameters['filename']);
		$att->name = $mimePart->ctype_parameters['name'];
		$att->fileName = $mimePart->d_parameters['filename'];
		$att->conentType = $mimePart->ctype_primary."/".$mimePart->ctype_secondary; 
		$att->contentId	= $mimePart->ctype_parameters['content-id']; // content_id
		$att->contentDisposition = $mimePart->disposition; // disposition
		$att->contentTransferEncoding = $mimePart->headers['content-transfer-encoding']; // encoding
		$att->purgeFileOnSave = true; // cleanup once the attachment has been saved
	}
	else if(strcasecmp($mimePart->ctype_primary,"multipart")==0) // call recurrsively to get all attachments
	{
		foreach($mimePart->parts as $subPart) 
		{
			EmailInsertAttachments_mimeDecode($subPart, $email);
		}
	}
}
*/

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailgetMimeBody_mimeDecode
*
*	Purpose:	Get the text body of a message based on subtype. This can be used
*				to recurrsively find the html body or the plain text body of the message
*
*	Params:		Mail_mimeDecode::part $mimePart = mime part to process
*				subtype $subtype = subtype to get - usually plain or html
**************************************************************************************
function EmailgetMimeBody_mimeDecode($mimePart, $subtype) 
{
	$body = "";

	if(strcasecmp($mimePart->ctype_primary,"text")==0 && strcasecmp($mimePart->ctype_secondary,$subtype)==0 && isset($mimePart->body))
	{
		$body = $mimePart->body;
	}
	else if(strcasecmp($mimePart->ctype_primary,"multipart")==0) 
	{
		foreach($mimePart->parts as $part) 
		{
			if(!isset($part->disposition) || strcasecmp($part->disposition,"attachment"))  
			{
				$body = EmailgetMimeBody_mimeDecode($part, $subtype, $body);
			}
		}
	}

	return $body;
}
*/

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsert_mailParse
*
*	Purpose:	Inject an email from a file into ANT using mailparse to parse
*				Mail_mimeDecode is ineffecient because it requires you read the entire
*				message into memory to parse. This is fine for small messages but ANT
*				will accept up to 2GB emails so memory can be a limitation here. It is preferrable
*				to use the php mimeParse extension and read is incrementally in to the resource
*
*	Params:		CDatabase $dbh = reference to database
*				AntUser $user = user object
*				string $filepath = path to mime file to import
**************************************************************************************
function EmailInsert_mailParse(&$dbh, &$user, $filepath)
{
	if (!file_exists($filepath))
		return false; // Fail

	$parser = new MimeMailParser();
	$parser->setPath($filepath);

	$plainbody = $parser->getMessageBody('text');
    $htmlbody = $parser->getMessageBody('html');
	
	// Create new mail object and save it to ANT
	$email = new CEmailMessage($dbh, null, $user->id, $user->accountId);
	$email->setGroup("Inbox");
	$email->f_seen = 'f';
	$email->setHeader("Subject", trim($parser->getHeader('subject')));
	$email->setHeader("From", trim($parser->getHeader('from')));
	$email->setHeader("To", trim($parser->getHeader('to')));
	$email->setHeader("Cc", trim($parser->getHeader('cc')));
	$email->setHeader("Bcc", trim($parser->getHeader('bcc')));
	$email->setHeader("In-reply-to", trim($parser->getHeader('in-reply-to')));
	$email->setHeader("X-Spam-Flag", trim($parser->getHeader('x-spam-flag')));
	$email->setHeader("Message-ID", trim($parser->getHeader('message-id')));
	$email->setBody($plainbody, "plain");
	if ($htmlbody)
		$email->setBody($htmlbody, "html");

	$attachments = $parser->getAttachments();
	foreach ($attachments as $att)
		EmailInsertAttachment_mailParse($att, $email);

	// Process filters/autoresponders before saving
	EmailInsertProcessFilters($dbh, $email, $user);

	$mid = $email->save();

	return $mid;
}
*/

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsertAttachments_mailParse
*
*	Purpose:	Add attachment from mime message using mimeparse parser
*
*	Params:		Mail_mimeDecode::part $mimePart = mime part to process
*				CEmailMessage $email = ANT email message being saved
**************************************************************************************
function EmailInsertAttachment_mailParse(&$parserAttach, &$email)
{
	/*
	 * 	1. Write attachment to temp file
	 *	-------------------------------------------------------
	 * 	It is important to use streams here to try and keep the attachment out of memory if possible
	 * 	The parser should alrady have decoded the bodies for us so no need to use base64_decode or
	 * 	anything like that
	$tmpFolder = (AntConfig::getInstance()->data_path) ? AntConfig::getInstance()->data_path."/tmp" : sys_get_temp_dir();
	if (!file_exists($tmpFolder))
		@mkdir($tmpFolder, 0777);
	$tmpFile = tempnam($tmpFolder, "ematt");
	$handle = fopen($tmpFile, "w");
	$buf = null;
	while (($buf = $parserAttach->read()) != false)
	{
		fwrite($handle, $buf);
	}
	fclose($handle);

	if (!file_exists($tmpFile))
		return false;

	// 2. Add the attachment to the CEmailMessage object
	$att = $email->addAttachment($tmpFile, $parserAttach->getFilename());
	$att->name = $parserAttach->getFilename();
	$att->fileName = $parserAttach->getFilename();
	$att->conentType = $parserAttach->getContentType(); 
	$att->contentId	= $parserAttach->content_id; // content_id
	$att->contentDisposition = $parserAttach->getContentDisposition(); // disposition
	$att->contentTransferEncoding = $parserAttach->transfer_encoding; // encoding
	$att->purgeFileOnSave = true; // cleanup once the attachment has been saved

	//echo "<pre>".var_export($att, true)."</pre>";
}
*/

/*************************************************************************************
* @depricated Now use /lib/AntMail/DeliveryAgent
*	Function:	EmailInsertProcessFilters
*
*	Purpose:	Process filters and actions for this email
*
*	Params:		CDatabase $dbh = reference to database
*				AntUser $user = user object
*				string $filepath = path to mime file to import
**************************************************************************************
function EmailInsertProcessFilters(&$dbh, &$email, &$user)
{
	// Check for spam status
	// ------------------------------------------------
	$fromEmail = EmailAdressGetDisplay($email->getHeader("from"), 'address');
	if ("yes" == strtolower($email->getHeader("X-Spam-Flag")))
	{
		// First make sure this user is not in the whitelist
		$query = "select id from email_settings_spam where preference='whitelist_from' 
					and '".strtolower($fromEmail)."' like lower(replace(value, '*', '%'))
					and user_id='".$user->id."'";
		if (!$dbh->GetNumberRows($dbh->Query($query)))
		{
			$email->setGroup("Junk Mail");
			return; // No futher filters should be processed if this is junk
		}
	}
	else
	{
		// First make sure this user is not in the blacklist
		$query = "select id from email_settings_spam where preference='blacklist_from' 
					and '".strtolower($fromEmail)."' like lower(replace(value, '*', '%'))
					and user_id='".$user->id."'";
		if ($dbh->GetNumberRows($dbh->Query($query)))
			$email->setGroup("Junk Mail");
	}

	// Check for filters
	// ------------------------------------------------
	$query = "select kw_subject, kw_to, kw_from, kw_body, act_mark_read, act_move_to 
				from email_filters where user_id='".$user->id."'";
	$result = $dbh->Query($query);
	$num = $dbh->GetNumberRows($result);
	for ($i = 0; $i < $num; $i++)
	{
		$row = $dbh->GetNextRow($result, $i);
		$fSkipFilter = false;

		if ($row['kw_subject'] && $email->getHeader("subject"))
		{
			if (stristr(strtolower($email->getHeader("subject")), strtolower($row['kw_subject']))!==false)
			{
				$fSkipFilter = false;
			}
			else
			{
				$fSkipFilter = true;
			}
		}

		if ($row['kw_to'] && $email->getHeader("to"))
		{
			if (stristr(strtolower($email->getHeader("to")), strtolower($row['kw_to']))!==false)
			{
				$fSkipFilter = false;
			}
			else
			{
				$fSkipFilter = true;
			}
		}

		if ($row['kw_from'] && $email->getHeader("from"))
		{
			if (stristr(strtolower($email->getHeader("from")), strtolower($row['kw_from']))!==false)
			{
				$fSkipFilter = false;
			}
			else
			{
				$fSkipFilter = true;
			}
		}

		if ($row['kw_body'] && $email->getBody())
		{
			$body = strtolower(strip_tags($email->getBody()));
			if (stristr($body, strtolower($row['kw_body']))!==false)
			{
				$fSkipFilter = false;
			}
			else
			{
				$fSkipFilter = true;
			}
		}

		if (!$fSkipFilter)
		{
			if ($row['act_move_to'])
				$email->setGroupId($row['act_move_to']);

			if ($rpw['act_mark_read'] == 't')
				$email->f_seen = 'f';
		}
	}
	$dbh->FreeResults($result);
}
 */
?>