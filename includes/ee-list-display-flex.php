<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


avpvh_filelist_debug_log("Listing Files in Tile View...", 'General');

// Bulk Editing
if( ($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') AND !$eeShowingResults) {
	 $eeOutput .= '<p class="eeSFL_BulkEdit eeCentered"><input type="checkbox" id="eeSFL_BulkEditAll" name="eeBulkEditAll" value="YES"/>
	<label for="eeSFL_BulkEditAll">' . __('Select All', 'avpvh-filelist') . '</label></p>';
}

 $eeOutput .= '<section class="eeFiles eeSFL_Item">';

// Loop through array
foreach($eeSFL->eeDisplayFiles as $eeFileID => $eeFileArray) { // <<<---------------------------- BEGIN FILE LIST LOOP ----------------<<<

	// Populate our class properties for this file
	if( $eeSFL->process_file_array($eeFileArray, $eeSFL_HideName, $eeSFL_HideType) ) {



		 $eeOutput .= '

		<article id="eeSFL_FileID-' . esc_attr($eeFileID) . '" class="eeSFL_Item">';



		 $eeOutput .= '
		<span class="eeSFL_RealFileName eeHide">' . esc_html($eeSFL->eeRealFileName) . '</span>
		<span class="eeSFL_FileNiceName eeHide">' . esc_html($eeSFL->eeFileNiceName) . '</span>
		<span class="eeSFL_FileMimeType eeHide">' . esc_html($eeSFL->eeFileMIME) . '</span>

		<div class="eeSFL_FlexRow">';


		// Thumbnail
		if($eeAdmin OR $eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {
			if($eeSFL->eeFileThumbURL) {
				 $eeOutput .= '<div class="eeSFL_Thumbnail"><a href="' . esc_url($eeSFL->eeFileURL) .  '"><img src="' . esc_url($eeSFL->eeFileThumbURL) . '" width="64" height="64" alt="Thumb" /></a></div>';
			}
		}


		// File Name, Description and Submitter
		 $eeOutput .= '

		<div class="eeSFL_FileInfo">

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


		 $eeOutput .= '</div>

		<div class="eeSFL_FileDetails">';

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




		 $eeOutput .= '

		</div>
		</div>

		<div class="eeSFL_FileOps">';

		// File Actions
		 $eeOutput .= $eeSFL->return_file_actions($eeFileID, $eeFileArray);

		 $eeOutput .= '

		</div>


		</article>';

	}
}

 $eeOutput .= '</section>';


?>