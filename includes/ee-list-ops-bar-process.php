<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// The File List Operations Bar Processor: Description, Download (ZIP) and Delete for the checked files
if( isset($_POST['eeSFL_ListOpsBarGo']) ) {

	eeSFL_Debug_Log("=== BULK OPERATIONS POST RECEIVED ===", 'OpsProcess');

	// Sanitize all POST inputs at the top
	$fileOpsAction = isset($_POST['eeSFL_FileOpsAction']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsAction'])) : '';
	$fileOpsFiles = isset($_POST['eeSFL_FileOpsFiles']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsFiles'])) : '';
	$zipFileName = isset($_POST['eeSFL_ZipFileName']) ? sanitize_file_name(wp_unslash($_POST['eeSFL_ZipFileName'])) : '';
	$description = isset($_POST['eeSFL_Description']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_Description'])) : '';

	eeSFL_Debug_Log("Action: " . $fileOpsAction, 'OpsProcess');
	eeSFL_Debug_Log("Files: " . $fileOpsFiles, 'OpsProcess');

	// Wordpress Security
	if($eeAdmin) { // Back-End File Ops Action Taken

		if(check_admin_referer( 'ee-simple-file-list-file-ops-bar', 'ee-simple-file-list-file-ops-bar-nonce')) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Back-End File Ops Action Taken'); }

	} else {

		$fileOpsNonce = isset($_POST['ee-simple-file-list-file-ops-bar-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-file-ops-bar-nonce'])) : '';
		if( wp_verify_nonce( $fileOpsNonce, 'ee-simple-file-list-file-ops-bar') ) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Front-End File Ops Action Taken'); }
	}

	// SECURITY: A valid nonce only proves the form came from this site, not that the visitor may manage files.
	// Front-end visitors may only download (when bulk download is enabled) unless front-end management is on.
	if($eeProceed && !$eeAdmin && !eeSFL_UserManagesList($eeSFL->eeListID, get_current_user_id())) {
		$eeCanManage = $eeSFL->eeListSettings['AllowFrontManage'] == 'YES';
		$eeCanDownload = $eeCanManage || $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES';
		if( $fileOpsAction == 'Download' ? !$eeCanDownload : !$eeCanManage ) {
			eeSFL_Debug_Log("Bulk operation '" . $fileOpsAction . "' denied for front-end visitor");
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
			if(!$eeSFL->eeSFL_EnsureTempDirectory()) {
				$eeSFL->eeUserMessages['errors'][] = __('The temporary directory could not be created. ZIP download is unavailable.', 'simple-file-list');
				return;
			}

			// Define the zip file within the uploads-based temp directory with an unguessable token
			$eeZipBase = (substr(strtolower($zipFileName), -4) === '.zip') ? substr($zipFileName, 0, -4) : $zipFileName;
			if(!$eeZipBase) { $eeZipBase = 'File-Archive'; }
			$eeRandomZipName = $eeZipBase . '-' . wp_generate_password(12, false, false) . '.zip';
			$eeZipFileName = eeSFL_TempDir . '/' . $eeRandomZipName;
			$eeZipFileURL = eeSFL_TempUrl . '/' . $eeRandomZipName;

			// Delete if already exists
			$eeZipFileCheck = eeSFL_FileSystem('is_file', array('file' => $eeZipFileName));
			if($eeZipFileCheck['success'] && $eeZipFileCheck['data']) {
				eeSFL_FileSystem('delete', array('file' => $eeZipFileName));
			}

			// Create ZIP Archive
			$eeZipObject = new ZipArchive;
			if ($eeZipObject->open($eeZipFileName, ZipArchive::CREATE) !== TRUE) {
				$eeSFL->eeUserMessages['errors'][] = __('The ZIP file cannot be created.', 'simple-file-list');
			}
		}

		if( !$eeSFL->eeUserMessages['errors'] ) {

			$eeSiteRoot = defined('eeSFL_WP_ROOT') ? eeSFL_WP_ROOT : ABSPATH;

			foreach( $eeFileIDs as $eeThisID ) {

				if( !isset($eeSFL->eeAllFiles[$eeThisID]) ) { continue; }

				$eeKey = $eeThisID;
				$eeFileArray = $eeSFL->eeAllFiles[$eeKey];
				$eePath = $eeSiteRoot . $eeSFL->eeListSettings['FileListDir'] . $eeFileArray['FilePath'];

				// Only files directly in the list folder (never folders left by the old Pro version)
				if( strpos($eeFileArray['FilePath'], '/') !== FALSE || ($eeFileArray['FileExt'] ?? '') == 'folder' ) { continue; }

				// Never change an item this user may not read or manage (list and per-file access)
				if( $fileOpsAction != 'Download' && !eeSFL_UserCanChangeFile($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id()) ) {
					eeSFL_Debug_Log("Bulk '" . $fileOpsAction . "' skipped, no access: " . $eeFileArray['FilePath'], 'OpsProcess', $eeSFL->eeListID);
					continue;
				}

				if($fileOpsAction == 'Download') {

					// Never zip a file this user may not open
					if( !eeSFL_UserCanReadFile($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id()) ) { continue; }

					if( is_object($eeZipObject) ) {
						eeSFL_Debug_Log('- Adding File: ' . $eePath . ' ——> ' . $eeFileArray['FilePath'], 'General', $eeSFL->eeListID);
						$eeZipObject->addFile($eePath, $eeFileArray['FilePath']);
					}

				} elseif($fileOpsAction == 'Delete') {

					eeSFL_Debug_Log("BULK DELETE - Deleting: " . $eePath, 'OpsProcess', $eeSFL->eeListID);
					unset($eeSFL->eeAllFiles[$eeKey]); // Remove the array from the array
					$eeDeleteResult = eeSFL_FileSystem('delete', array('file' => $eePath));
					if($eeDeleteResult['success']) { eeSFL_Debug_Log("BULK DELETE - File deleted: " . basename($eePath), 'OpsProcess', $eeSFL->eeListID); }

				} elseif($fileOpsAction == 'Description') {

					// Sanitize input and add to the file array.
					$eeString = substr($description, 0, 1024);
					if($eeString) {
						$eeSFL->eeAllFiles[$eeKey]['FileDescription'] = $eeString;
						eeSFL_Debug_Log("Description Added", 'General');
					}
				}
			}

			// Write the new array to the database
			if(count($eeSFL->eeUserMessages['errors']) < 1) {
				$eeSFL->eeSFL_UpdateMainFileArray(false);
			}

			if($eeZipObject) {

				// All files are added, so close the zip file.
				$eeZipObject->close();

				$eeSFL->eeUserMessages['messages'][] = __('Download File', 'simple-file-list') . ' &rarr; <a class="button" id="eeSFL_DownloadArchiveButton" href="' . esc_url($eeZipFileURL) . '" download="' . esc_attr(basename($eeZipFileName)) . '">' . esc_html(basename($eeZipFileName)) . '</a></strong>';
			}
		}
	}
}
