<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// The File List Operations Bar Processor: Description, Download (ZIP) and Delete for the checked files
if( isset($_POST['eeSFL_ListOpsBarGo']) ) {

	avpvh_flst_debug_log("=== BULK OPERATIONS POST RECEIVED ===", 'OpsProcess');

	// Sanitize all POST inputs at the top
	$fileOpsAction = isset($_POST['eeSFL_FileOpsAction']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsAction'])) : '';
	$fileOpsFiles = isset($_POST['eeSFL_FileOpsFiles']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsFiles'])) : '';
	$zipFileName = isset($_POST['eeSFL_ZipFileName']) ? sanitize_file_name(wp_unslash($_POST['eeSFL_ZipFileName'])) : '';
	$description = isset($_POST['eeSFL_Description']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_Description'])) : '';

	avpvh_flst_debug_log("Action: " . $fileOpsAction, 'OpsProcess');
	avpvh_flst_debug_log("Files: " . $fileOpsFiles, 'OpsProcess');

	// Wordpress Security
	if($eeAdmin) { // Back-End File Ops Action Taken

		if(check_admin_referer( 'avpvh-filelist-file-ops-bar', 'avpvh-filelist-file-ops-bar-nonce')) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Back-End File Ops Action Taken'); }

	} else {

		$fileOpsNonce = isset($_POST['avpvh-filelist-file-ops-bar-nonce']) ? sanitize_text_field(wp_unslash($_POST['avpvh-filelist-file-ops-bar-nonce'])) : '';
		if( wp_verify_nonce( $fileOpsNonce, 'avpvh-filelist-file-ops-bar') ) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Front-End File Ops Action Taken'); }
	}

	// SECURITY: A valid nonce only proves the form came from this site, not that the visitor may manage files.
	// Front-end visitors may only download (when bulk download is enabled) unless front-end management is on.
	if($eeProceed && !$eeAdmin && !avpvh_flst_user_manages_list($eeSFL->eeListID, get_current_user_id())) {
		$eeCanManage = $eeSFL->eeListSettings['AllowFrontManage'] == 'YES';
		$eeCanDownload = $eeCanManage || $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES';
		if( $fileOpsAction == 'Download' ? !$eeCanDownload : !$eeCanManage ) {
			avpvh_flst_debug_log("Bulk operation '" . $fileOpsAction . "' denied for front-end visitor");
			exit('ERROR 97 - Not Allowed');
		}
	}

	if($eeProceed) {

		$eeZipObject = FALSE; // Zip File Object if Downloading

		// The checked items, by their key in the file array
		$eeFileIDs = array_filter(explode(',', $fileOpsFiles), 'is_numeric');

		// If Downloading, initialize the zip file
		if($fileOpsAction == 'Download') {

			$eeSFL->eeUserMessages['notice']['Download'] = '- Downloading Files';

			// Ensure temp directory is writable before attempting ZIP creation
			if(!$eeSFL->ensure_temp_directory()) {
				$eeSFL->eeUserMessages['errors'][] = __('The temporary directory could not be created. ZIP download is unavailable.', 'avpvh-filelist');
				return;
			}

			// Define the zip file within the uploads-based temp directory with an unguessable token
			$eeZipBase = (substr(strtolower($zipFileName), -4) === '.zip') ? substr($zipFileName, 0, -4) : $zipFileName;
			if(!$eeZipBase) { $eeZipBase = 'File-Archive'; }
			$eeRandomZipName = $eeZipBase . '-' . wp_generate_password(12, false, false) . '.zip';
			$eeZipFileName = AVPVH_FLST_TEMP_DIR . '/' . $eeRandomZipName;
			$eeZipFileURL = AVPVH_FLST_TEMP_URL . '/' . $eeRandomZipName;

			// Delete if already exists
			$eeZipFileCheck = avpvh_flst_file_system('is_file', array('file' => $eeZipFileName));
			if($eeZipFileCheck['success'] && $eeZipFileCheck['data']) {
				avpvh_flst_file_system('delete', array('file' => $eeZipFileName));
			}

			// Create ZIP Archive
			$eeZipObject = new ZipArchive;
			if ($eeZipObject->open($eeZipFileName, ZipArchive::CREATE) !== TRUE) {
				$eeSFL->eeUserMessages['errors'][] = __('The ZIP file cannot be created.', 'avpvh-filelist');
			}
		}

		if( !$eeSFL->eeUserMessages['errors'] ) {

			$eeSiteRoot = defined('AVPVH_FLST_WP_ROOT') ? AVPVH_FLST_WP_ROOT : ABSPATH;

			foreach( $eeFileIDs as $eeThisID ) {

				if( !isset($eeSFL->eeAllFiles[$eeThisID]) ) { continue; }

				$eeKey = $eeThisID;
				$eeFileArray = $eeSFL->eeAllFiles[$eeKey];
				$eePath = $eeSiteRoot . $eeSFL->eeListSettings['FileListDir'] . $eeFileArray['FilePath'];

				// Only files directly in the list folder (never folders left by the old Pro version)
				if( strpos($eeFileArray['FilePath'], '/') !== FALSE || ($eeFileArray['FileExt'] ?? '') == 'folder' ) { continue; }

				// Never change an item this user may not read or manage (list and per-file access)
				if( $fileOpsAction != 'Download' && !avpvh_flst_user_can_change_file($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id()) ) {
					avpvh_flst_debug_log("Bulk '" . $fileOpsAction . "' skipped, no access: " . $eeFileArray['FilePath'], 'OpsProcess', $eeSFL->eeListID);
					continue;
				}

				if($fileOpsAction == 'Download') {

					// Never zip a file this user may not open
					if( !avpvh_flst_user_can_read_file($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id()) ) { continue; }

					if( is_object($eeZipObject) ) {
						avpvh_flst_debug_log('- Adding File: ' . $eePath . ' ——> ' . $eeFileArray['FilePath'], 'General', $eeSFL->eeListID);
						$eeZipObject->addFile($eePath, $eeFileArray['FilePath']);
					}

				} elseif($fileOpsAction == 'Delete') {

					avpvh_flst_debug_log("BULK DELETE - Deleting: " . $eePath, 'OpsProcess', $eeSFL->eeListID);
					unset($eeSFL->eeAllFiles[$eeKey]); // Remove the array from the array
					$eeDeleteResult = avpvh_flst_file_system('delete', array('file' => $eePath));
					if($eeDeleteResult['success']) { avpvh_flst_debug_log("BULK DELETE - File deleted: " . basename($eePath), 'OpsProcess', $eeSFL->eeListID); }

				} elseif($fileOpsAction == 'Description') {

					// Sanitize input and add to the file array.
					$eeString = substr($description, 0, 1024);
					if($eeString) {
						$eeSFL->eeAllFiles[$eeKey]['FileDescription'] = $eeString;
						avpvh_flst_debug_log("Description Added", 'General');
					}
				}
			}

			// Write the new array to the database
			if(count($eeSFL->eeUserMessages['errors']) < 1) {
				$eeSFL->update_main_file_array(false);
			}

			if($eeZipObject) {

				// All files are added, so close the zip file.
				$eeZipObject->close();

				$eeSFL->eeUserMessages['messages'][] = __('Download File', 'avpvh-filelist') . ' &rarr; <a class="button" id="eeSFL_DownloadArchiveButton" href="' . esc_url($eeZipFileURL) . '" download="' . esc_attr(basename($eeZipFileName)) . '">' . esc_html(basename($eeZipFileName)) . '</a></strong>';
			}
		}
	}
}
