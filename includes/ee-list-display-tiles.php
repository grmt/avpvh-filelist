<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

eeSFL_Debug_Log("Listing Files in Tile View...", 'General');

// Bulk Editing
if( ($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') AND !$eeShowingResults) {
	 $eeOutput .= '<p class="eeSFL_BulkEdit eeCentered"><input type="checkbox" id="eeSFL_BulkEditAll" name="eeBulkEditAll" value="YES"/>
	<label for="eeSFL_BulkEditAll">' . __('Select All', 'simple-file-list') . '</label></p>';
}

 $eeOutput .= '<section class="eeFiles eeSFL_Item">';

// Loop through array
foreach($eeSFL->eeDisplayFiles as $eeFileID => $eeFileArray) { // <<<---------------------------- BEGIN FILE LIST LOOP ----------------<<<

	// Populate our class properties for this file
	if( $eeSFL->eeSFL_ProcessFileArray($eeFileArray, $eeSFL_HideName, $eeSFL_HideType) ) {

		// Extension Check
		if($eeSFLA AND !is_admin()) {
			if( $eeSFLA->eeSFLA_FileFirewall($eeFileArray) === FALSE ) { continue; } // Skip this file if FALSE
		}

		// Extension Check
		if(!$eeListPosition AND $eeListPosition !== 0) { // eeSFLS Pagination
			// $eeListPosition = $eeFileID; // Get the first key
		}

		 $eeOutput .= '

		<article id="eeSFL_FileID-' . esc_attr($eeFileID) . '" class="eeSFL_Item">';


		// Proper Path for Search Results
		if(isset($_POST['eeSFLS_Searching'])) {
			// Verify nonce for search form submission (Missing nonce security)
			if (!wp_verify_nonce(isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '', 'ee-search-form') && !is_admin()) {
				// Skip search result path display if nonce verification fails
			} else {

			$eePathInfo = pathinfo($eeSFL->eeFilePath);
			$eePath = $eePathInfo['dirname'];
			if($eePath != '.') {
				 $eeOutput .= '
				<span class="eeSFL_RealFilePath eeHide">' . esc_html($eePath . '/') . '</span>';
			}
			} // Close nonce verification block
		}

		 $eeOutput .= '
		<span class="eeSFL_RealFileName eeHide">' . esc_html($eeSFL->eeRealFileName) . '</span>
		<span class="eeSFL_FileNiceName eeHide">' . esc_html($eeSFL->eeFileNiceName) . '</span>
		<span class="eeSFL_FileMimeType eeHide">' . esc_html($eeSFL->eeFileMIME) . '</span>

		<h4 class="eeSFL_FileLink">';

		// Bulk Editing
		if( ($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') AND !$eeShowingResults) {
			 $eeOutput .= '
			<span class="eeSFL_BulkEdit">
				<input type="checkbox" id="eeSFL_BulkEdit_' . esc_attr($eeFileID) . '"  class="eeSFL_BulkEditCheck" name="eeBulkEdit" value="' . esc_attr($eeFileID) . '"/></span> ';
		}

		 $eeOutput .= '<a class="eeSFL_FileName" href="' . esc_url($eeSFL->eeFileURL) .  '"';

		$eeOutput .= ' target="_blank"';

		 $eeOutput .= '>' . esc_html(stripslashes($eeSFL->eeFileName)) . '</a>';

		 $eeOutput .= '</h4>';

		// File Actions
		 $eeOutput .= $eeSFL->eeSFL_ReturnFileActions($eeFileID, $eeFileArray);


		// Thumbnail
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {
			if($eeSFL->eeFileThumbURL) {
				 $eeOutput .= '<div class="eeSFL_Thumbnail"><a href="' . esc_url($eeSFL->eeFileURL) .  '"';

			$eeOutput .= ' target="_blank"';

			 $eeOutput .= '><img src="' . esc_url($eeSFL->eeFileThumbURL) . '" width="64" height="64" alt="Thumb" /></a></div>';
			}
		}


		// File Description
		if($eeSFL->eeListSettings['ShowFileDesc'] == 'NO' AND !$eeAdmin) { $eeClass = 'eeHide'; }
		 $eeOutput .= '<p class="eeSFL_FileDesc ' . esc_attr($eeClass) . '">' . esc_html(stripslashes($eeSFL->eeFileDescription)) . '</p>'; // Always here for JS

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
		$eeShowIt = FALSE;
		}

		 $eeOutput .= '<div class="eeSFL_FileDetails">';


		// File Size
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileSize'] == 'YES') {

			 $eeOutput .= '<span class="eeSFL_FileSize">';

			 $eeOutput .= $eeSFL->eeFileSize;

			 $eeOutput .= '</span>';
		}


		// File Modification Date
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileDate'] == 'YES') {

			 $eeOutput .= '<span class="eeSFL_FileDate">' . $eeSFL->eeFileDate . '</span>';
		}


		 $eeOutput .= '</div>

		</article>';

	}

}

 $eeOutput .= '</section>';


?>