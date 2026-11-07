<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Pre-filter files for RESTRICTED/USER mode to check if any will be displayed
$eeSFLA_HasAccessibleFiles = false;
if($eeSFLA && !is_admin() &&
   isset($eeSFL->eeListSettings['Mode']) &&
   ($eeSFL->eeListSettings['Mode'] == 'RESTRICTED' || $eeSFL->eeListSettings['Mode'] == 'USER')) {

    // Check if at least one file passes the firewall
    foreach($eeSFL->eeDisplayFiles as $eeFileArray) {
        if($eeSFLA->eeSFLA_FileFirewall($eeFileArray) !== FALSE) {
            $eeSFLA_HasAccessibleFiles = true;
            break;
        }
    }

    // If no accessible files, don't render the table at all
    if(!$eeSFLA_HasAccessibleFiles) {
        return;
    }
}

// TABLE HEAD ==================================================================================================

 $eeOutput .= '<table class="eeFiles">';

if($eeSFL->eeListSettings['ShowHeader'] == 'YES' OR $eeAdmin) {  $eeOutput .= '<thead><tr>';

	// Bulk Editing
	if( ($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') AND !$eeShowingResults) {
		 $eeOutput .= '<th class="eeSFL_BulkEdit">
			<input type="checkbox" id="eeSFL_BulkEditAll" name="eeBulkEditAll" value="YES"/></th>';
	}

	if($eeAdmin OR $eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {

		 $eeOutput .= '<th class="eeSFL_Thumbnail">';

		if($eeSFL->eeListSettings['LabelThumb']) {  $eeOutput .= esc_html(stripslashes($eeSFL->eeListSettings['LabelThumb'])); }
			else {  $eeOutput .= __('Thumb', 'simple-file-list'); }

		 $eeOutput .= '</th>';
	}


	 $eeOutput .= '<th class="eeSFL_FileName">';

	if($eeSFL->eeListSettings['LabelName']) {  $eeOutput .= esc_html(stripslashes($eeSFL->eeListSettings['LabelName'])); }
		else {  $eeOutput .= __('Name', 'simple-file-list'); }

	 $eeOutput .= '</th>';


	if($eeAdmin OR $eeSFL->eeListSettings['ShowFileSize'] == 'YES') {

		 $eeOutput .= '<th class="eeSFL_FileSize">';

		if($eeSFL->eeListSettings['LabelSize']) {  $eeOutput .= esc_html(stripslashes($eeSFL->eeListSettings['LabelSize'])); }
			else {  $eeOutput .= __('Size', 'simple-file-list'); }

		 $eeOutput .= '</th>';
	}


	if($eeAdmin OR $eeSFL->eeListSettings['ShowFileDate'] == 'YES') {

		 $eeOutput .= '<th class="eeSFL_FileDate">';

		if($eeSFL->eeListSettings['LabelDate']) {  $eeOutput .= esc_html(stripslashes($eeSFL->eeListSettings['LabelDate'])); }
			else {  $eeOutput .= __('Date', 'simple-file-list'); }

		 $eeOutput .= '</th>';
	}


	 $eeOutput .= '</tr>

	</thead>';
}

 $eeOutput .= '

<tbody>';


eeSFL_Debug_Log("Listing Files in Table View...", 'General');

// echo '<pre>'; print_r($eeSFL->eeAllFiles); echo '</pre>'; exit;

// Loop through array
foreach($eeSFL->eeDisplayFiles as $eeFileID => $eeFileArray) { // <<<---------------------------- BEGIN FILE LIST LOOP ----------------<<<

	// Populate our class properties for this file
	if( $eeSFL->eeSFL_ProcessFileArray($eeFileArray, $eeSFL_HideName, $eeSFL_HideType) ) {

		// Extension Check
		if($eeSFLA AND !is_admin() ) {
			if( $eeSFLA->eeSFLA_FileFirewall($eeFileArray) === FALSE ) { continue; } // Skip this file if FALSE
		}


		// Start The List --------------------------------------------------------------

		 $eeOutput .= '

		<tr id="eeSFL_FileID-' . esc_attr($eeFileID) . '" class="eeSFL_Item">';


		// Bulk Editing
		if($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') {
			 $eeOutput .= '
			<td class="eeSFL_BulkEdit">';
			 $eeOutput .= '<input type="checkbox" id="eeSFL_BulkEdit_' . esc_attr($eeFileID) . '"  class="eeSFL_BulkEditCheck" name="eeBulkEdit" value="' . esc_attr($eeFileID) . '"/></td>';
		}


		// Thumbnail
		if($eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {

			 $eeOutput .= '
			<td class="eeSFL_Thumbnail">';

			if($eeSFL->eeFileThumbURL) {  $eeOutput .= '<a href="' . esc_url($eeSFL->eeFileURL) .  '"';

				$eeOutput .= ' target="_blank"';

				 $eeOutput .= '><img src="' . esc_url($eeSFL->eeFileThumbURL) . '" width="64" height="64" alt="Thumb" /></a>'; }

				 $eeOutput .= '</td>';
		}


		// NAME
		 $eeOutput .= '
		<td class="eeSFL_FileNameCell eeSFL_FileName">';

		if($eeSFL->eeFileURL) {



			 $eeOutput .= '
			<span class="eeSFL_RealFileName eeHide">' . esc_html($eeSFL->eeRealFileName) . '</span>
			<span class="eeSFL_FileNiceName eeHide">' . esc_html($eeSFL->eeFileNiceName) . '</span><p class="eeSFL_FileLink">
			<span class="eeSFL_FileMimeType eeHide">' . esc_html($eeSFL->eeFileMIME) . '</span>';



			 $eeOutput .= '<a class="eeSFL_FileName" href="' . esc_url($eeSFL->eeFileURL) .  '"';

			$eeOutput .= ' target="_blank"';

			 $eeOutput .= '>' . esc_html(stripslashes($eeSFL->eeFileName)) . '</a>';

			 $eeOutput .= '</p>';

			// Show File Description
			if(!$eeAdmin AND $eeSFL->eeListSettings['ShowFileDesc'] == 'NO') { $eeClass = 'eeHide'; }

			// This is always here in case of editing, but hidden if empty
			 $eeOutput .= '
			<p class="eeSFL_FileDesc ' . esc_attr($eeClass) . '">' . esc_html(stripslashes($eeSFL->eeFileDescription)) . '</p>';


			// Submitter Info
			$eeShowIt = FALSE;
			if($eeAdmin AND $eeThisUser != $eeSFL->eeFileOwner) {
				$eeShowIt = TRUE;
			} elseif($eeSFL->eeListSettings['ShowSubmitterInfo'] == 'YES' ) {
				if($eeThisUser AND $eeThisUser != $eeSFL->eeFileOwner) {
					$eeShowIt = TRUE;
				} elseif( !$eeThisUser ) { // Not logged in
					$eeShowIt = TRUE;
				}
			}
			if($eeShowIt AND $eeSFL->eeFileSubmitterName) {
				 $eeOutput .= '<p class="eeSFL_FileSubmitter"><span>' . esc_html($eeSFL->eeListSettings['LabelOwner']) . ': </span>';
				if($eeAdmin OR $eeSFL->eeListSettings['ShowSubmitterEmail'] == 'YES') {
					 $eeOutput .= '<a href="mailto:' . esc_attr($eeSFL->eeFileSubmitterEmail) . '">' . esc_html(stripslashes($eeSFL->eeFileSubmitterName)) . '</a>';
				} else {
					 $eeOutput .= esc_html(stripslashes($eeSFL->eeFileSubmitterName));
				}
				 $eeOutput .= '</p>';
			}
			$eeShowIt = FALSE;


			// File Actions
			 $eeOutput .= $eeSFL->eeSFL_ReturnFileActions($eeFileID, $eeFileArray);



		 $eeOutput .= '</td>';



		// File Size
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileSize'] == 'YES') {

			 $eeOutput .= '
			<td class="eeSFL_FileSize">';

			 $eeOutput .= $eeSFL->eeFileSize;

			 $eeOutput .= '</td>';
		}


		// File Modification Date
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileDate'] == 'YES') {

			 $eeOutput .= '<td class="eeSFL_FileDate"><span class="eeSFL_FileDateDisplayed">' . $eeSFL->eeFileDate . '</span></td>';
		}

		 $eeOutput .= '
		</tr>';

		} // END If URL

	}

} // END $eeSFL->eeDisplayFiles loop


 $eeOutput .= '

</tbody>

</table>';

?>