<?php
/*
	*********************************************************************
	* phpLogCon - http://www.phplogcon.org
	* -----------------------------------------------------------------
	* Details File											
	*																	
	* -> Shows all possible details of a syslog message
	*																	
	* All directives are explained within this file
	*
	* Copyright (C) 2008 Adiscon GmbH.
	*
	* This file is part of phpLogCon.
	*
	* PhpLogCon is free software: you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation, either version 3 of the License, or
	* (at your option) any later version.
	*
	* PhpLogCon is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with phpLogCon. If not, see <http://www.gnu.org/licenses/>.
	*
	* A copy of the GPL can be found in the file "COPYING" in this
	* distribution				
	*********************************************************************
*/

// *** Default includes	and procedures *** //
define('IN_PHPLOGCON', true);
$gl_root_path = './';

// Now include necessary include files!
include($gl_root_path . 'include/functions_common.php');
include($gl_root_path . 'include/functions_frontendhelpers.php');
include($gl_root_path . 'include/functions_filters.php');

// Include LogStream facility
include($gl_root_path . 'classes/logstream.class.php');

InitPhpLogCon();
InitSourceConfigs();
InitFrontEndDefaults();	// Only in WebFrontEnd
InitFilterHelpers();	// Helpers for frontend filtering!
// ---

// --- Define Extra Stylesheet!
//$content['EXTRA_STYLESHEET']  = '<link rel="stylesheet" href="css/highlight.css" type="text/css">' . "\r\n";
//$content['EXTRA_STYLESHEET'] .= '<link rel="stylesheet" href="css/menu.css" type="text/css">';
// --- 

// --- CONTENT Vars
if ( isset($_GET['uid']) ) 
	$content['uid_current'] = intval($_GET['uid']);
else
	$content['uid_current'] = UID_UNKNOWN;

// Copy UID for later use ...
$content['uid_fromgetrequest'] = $content['uid_current'];

// Init Pager variables
$content['uid_first'] = UID_UNKNOWN;
$content['uid_last'] = UID_UNKNOWN;
$content['main_pagerenabled'] = false;
$content['main_pager_first_found'] = false;
$content['main_pager_previous_found'] = false;
$content['main_pager_next_found'] = false;
$content['main_pager_last_found'] = false;

// Set Default reading direction 
$content['read_direction'] = EnumReadDirection::Backward;

// If set read direction property!
if ( isset($_GET['direction']) )
{
	if ( $_GET['direction'] == "next" ) 
	{
		$content['skiprecords'] = 1;
		$content['read_direction'] = EnumReadDirection::Backward;
	}
	else if ( $_GET['direction'] == "previous" ) 
	{
		$content['skiprecords'] = 1;
		$content['read_direction'] = EnumReadDirection::Forward;
	}
}

// Init Sorting variables
$content['sorting'] = "";
$content['searchstr'] = "";
$content['highlightstr'] = "";
$content['EXPAND_HIGHLIGHT'] = "false";

// Set Page title
$content['TITLE'] = "phpLogCon :: Details";

// --- BEGIN Custom Code
if ( isset($content['Sources'][$currentSourceID]) ) // && $content['uid_current'] != UID_UNKNOWN ) // && $content['Sources'][$currentSourceID]['SourceType'] == SOURCE_DISK )
{
	// Obtain and get the Config Object
	$stream_config = $content['Sources'][$currentSourceID]['ObjRef'];

	// Create LogStream Object 
	$stream = $stream_config->LogStreamFactory($stream_config);
//	$stream->SetFilter($content['searchstr']);

	// --- Init the fields we need
	foreach($fields as $mycolkey => $myfield)
	{
		$content['fields'][$mycolkey]['FieldID'] = $mycolkey;
		$content['fields'][$mycolkey]['FieldCaption'] = $content[ $myfield['FieldCaptionID'] ];
		$content['fields'][$mycolkey]['FieldType'] = $myfield['FieldType'];
		$content['fields'][$mycolkey]['DefaultWidth'] = $myfield['DefaultWidth'];

		// Append to columns array
		$content['AllColumns'][] = $mycolkey;
	}
	// --- 

	$res = $stream->Open( $content['AllColumns'], true );
	if ( $res == SUCCESS ) 
	{
		// TODO Implement ORDER
		$stream->SetReadDirection($content['read_direction']);
		
		// Set current ID and init Counter
		$uID = $content['uid_current'];
		
		if ( $uID != UID_UNKNOWN )	// We know the UID, so read from where we know
			$ret = $stream->Read($uID, $logArray);
		else						// Unknown UID, so we start from first!
			$ret = $stream->ReadNext($uID, $logArray);
		
		// --- If set we move forward / backward!
		if ( isset($content['skiprecords']) && $content['skiprecords'] >= 1 )
		{
			$counter = 0;
			while( $counter < $content['skiprecords'] && ($ret = $stream->ReadNext($uID, $logArray)) == SUCCESS)
			{
				// Increment Counter
				$counter++;
			}
		}
		// --- 

		// Set new current uid!
		if ( isset($uID) && $uID != UID_UNKNOWN ) 
			$content['uid_current'] = $uID;
		
		// now we know enough to set the page title!
		$content['TITLE'] = "phpLogCon :: " . $content['LN_DETAILS_DETAILSFORMSG'] . " '" . $uID . "'";

		// We found matching records, so continue
		if ( $ret == SUCCESS )
		{
			// --- PreChecks to be done
			// Set Record Count
			$content['main_recordcount'] = $stream->GetMessageCount();
			if ( $content['main_recordcount'] != -1 )
				$content['main_recordcount_found'] = true;
			else
				$content['main_recordcount_found'] = false;
			// ---

			// Loop through fields - Copy value into fields list! We are going to use this list here
			$counter = 0;
			foreach($content['fields'] as $mycolkey => $myfield)
			{
//				// Default copy value into array!
//				$content['fields'][$mycolkey]['FieldValue'] = $logArray[$mycolkey];

				// --- Set CSS Class
				if ( $counter % 2 == 0 )
					$content['fields'][$mycolkey]['cssclass'] = "line1";
				else
					$content['fields'][$mycolkey]['cssclass'] = "line2";
				// --- 

				// Set defaults
				$content['fields'][$mycolkey]['fieldbgcolor'] = "";
				$content['fields'][$mycolkey]['hasdetails'] = "false";

				if ( $content['fields'][$mycolkey]['FieldType'] == FILTER_TYPE_DATE )
				{
					$content['fields'][$mycolkey]['fieldvalue'] = GetFormatedDate($logArray[$mycolkey]); 
					// TODO: Show more!
				}
				else if ( $content['fields'][$mycolkey]['FieldType'] == FILTER_TYPE_NUMBER )
				{
					$content['fields'][$mycolkey]['fieldvalue'] = $logArray[$mycolkey];

					// Special style classes and colours for SYSLOG_FACILITY
					if ( $mycolkey == SYSLOG_FACILITY )
					{
						if ( isset($logArray[$mycolkey][SYSLOG_FACILITY]) && strlen($logArray[$mycolkey][SYSLOG_FACILITY]) > 0)
						{
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $facility_colors[ $logArray[SYSLOG_FACILITY] ] . '" ';
							$content['fields'][$mycolkey]['cssclass'] = "lineColouredBlack";

							// Set Human readable Facility!
							$content['fields'][$mycolkey]['fieldvalue'] = GetFacilityDisplayName( $logArray[$mycolkey] );
						}
						else
						{
							// Use default colour!
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $facility_colors[SYSLOG_LOCAL0] . '" ';
						}
					}
					else if ( $mycolkey == SYSLOG_SEVERITY )
					{
						if ( isset($logArray[$mycolkey][SYSLOG_SEVERITY]) && strlen($logArray[$mycolkey][SYSLOG_SEVERITY]) > 0)
						{
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $severity_colors[ $logArray[SYSLOG_SEVERITY] ] . '" ';
							$content['fields'][$mycolkey]['cssclass'] = "lineColouredWhite";

							// Set Human readable Facility!
							$content['fields'][$mycolkey]['fieldvalue'] = GetSeverityDisplayName( $logArray[$mycolkey] );
						}
						else
						{
							// Use default colour!
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $severity_colors[SYSLOG_INFO] . '" ';
						}
					}
					else if ( $mycolkey == SYSLOG_MESSAGETYPE )
					{
						if ( isset($logArray[$mycolkey][SYSLOG_MESSAGETYPE]) )
						{
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $msgtype_colors[ $logArray[SYSLOG_MESSAGETYPE] ] . '" ';
							$content['fields'][$mycolkey]['cssclass'] = "lineColouredBlack";

							// Set Human readable Facility!
							$content['fields'][$mycolkey]['fieldvalue'] = GetMessageTypeDisplayName( $logArray[$mycolkey] );
						}
						else
						{
							// Use default colour!
							$content['fields'][$mycolkey]['fieldbgcolor'] = 'bgcolor="' . $msgtype_colors[IUT_Unknown] . '" ';
						}
						
					}
				}
				else if ( $content['fields'][$mycolkey]['FieldType'] == FILTER_TYPE_STRING )
				{
					if ( $mycolkey == SYSLOG_MESSAGE )
						$content['fields'][$mycolkey]['fieldvalue'] = GetStringWithHTMLCodes($logArray[$mycolkey]);
					else	// kindly copy!
						$content['fields'][$mycolkey]['fieldvalue'] = $logArray[$mycolkey];
				}

				// Increment helpcounter
				$counter++;
			}

//print_r ( $content['fields'] );
//exit;
			
			// Enable pager if the count is above 1 or we don't know the record count!
			if ( $content['main_recordcount'] > 1 || $content['main_recordcount'] == -1 )
			{
				// Enable Pager in any case here!
				$content['main_pagerenabled'] = true;

				// --- Handle uid_first page button 
				if ( $content['uid_fromgetrequest'] == $content['uid_first'] ) 
					$content['main_pager_first_found'] = false;
				else
				{
					// Probe next item !
					$ret = $stream->ReadNext($uID, $tmpArray);
					if ( $ret == SUCCESS )
						$content['main_pager_first_found'] = true;
					else
						$content['main_pager_first_found'] = false;
				}
				// --- 

				// --- Handle uid_last page button 
				// Option the last UID from the stream!
				$content['uid_last'] = $stream->GetLastPageUID();

				// if we found a last uid, and if it is not the current one (which means we already are on the last page ;)!
				if ( $content['uid_last'] != -1 && $content['uid_last'] != $content['uid_current'])
					$content['main_pager_last_found'] = true;
				else
					$content['main_pager_last_found'] = false;
				// --- 
				
				// --- Handle uid_next page button 
				if ( $content['uid_current'] != $content['uid_last'] )
					$content['main_pager_next_found'] = true;
				else
					$content['main_pager_next_found'] = false;
				// --- 

				// --- Handle uid_previous page button 
				if ( $content['main_pager_first_found'] == true && $content['uid_current'] != $content['uid_first'] )
					$content['main_pager_previous_found'] = true;
				else
					$content['main_pager_previous_found'] = false;
				// --- 
			}
			else	// Disable pager in this case!
				$content['main_pagerenabled'] = false;

			// This will enable to Main SyslogView
			$content['messageenabled'] = "true";
		}
		else
		{
			// Disable view and print error state!
			$content['messageenabled'] = "false";

			// Set error code 
			$content['error_code'] = $ret;
			

		if ( $ret == ERROR_UNDEFINED ) 
			$content['detailederror'] = "Undefined error happened within the logstream.";
//		else if ( $ret == ERROR_FILE_NOT_READABLE ) 
//			$content['detailederror'] = "Syslog file is not readable, read access may be denied. ";
		else 
				$content['detailederror'] = "Unknown or unhandeled error occured.";

		}
	}
	else
	{
		// This will disable to Main SyslogView and show an error message
		$content['messageenabled'] = "false";

		// Set error code 
		$content['error_code'] = $ret;

		if ( $ret == ERROR_FILE_NOT_FOUND ) 
			$content['detailederror'] = "Syslog file could not be found.";
		else if ( $ret == ERROR_FILE_NOT_READABLE ) 
			$content['detailederror'] = "Syslog file is not readable, read access may be denied. ";
		else 
			$content['detailederror'] = "Unknown or unhandeled error occured.";
			
	}

	// Close file!
	$stream->Close();
}
// --- 

// --- Parsen and Output
InitTemplateParser();
$page -> parser($content, "details.html");
$page -> output(); 
// --- 

?>