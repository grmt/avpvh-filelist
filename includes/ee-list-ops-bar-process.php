<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// The File List Operations Bar Processor
if( isset($_POST['eeSFL_ListOpsBarGo']) ) {

	eeSFL_Debug_Log("=== BULK OPERATIONS POST RECEIVED ===", 'OpsProcess');

	// Sanitize all POST inputs at the top
	$fileOpsAction = isset($_POST['eeSFL_FileOpsAction']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsAction'])) : '';
	$fileOpsFiles = isset($_POST['eeSFL_FileOpsFiles']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_FileOpsFiles'])) : '';

	eeSFL_Debug_Log("Action: " . $fileOpsAction, 'OpsProcess');
	eeSFL_Debug_Log("Files: " . $fileOpsFiles, 'OpsProcess');

	$eeNewFolderName = isset($_POST['eeSFL_NewFolderName']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_NewFolderName'])) : '';

	$zipFileName = isset($_POST['eeSFL_ZipFileName']) ? sanitize_file_name(wp_unslash($_POST['eeSFL_ZipFileName'])) : '';
	$moveToFolder = isset($_POST['eeSFL_MoveToFolder']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_MoveToFolder'])) : '';
	$description = isset($_POST['eeSFL_Description']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_Description'])) : '';





	// Wordpress Security
	if($eeAdmin AND isset($_POST['eeSFL_DownloadFolderForm'])) { // Back-End Download Folder Link Click

		if( check_admin_referer( 'ee-simple-file-list-zip-folder', 'ee-simple-file-list-zip-folder-nonce') ) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Back-End Download Folder Link Click'); }

	} elseif($eeAdmin) { // Back-End File Ops Action Taken

		if(check_admin_referer( 'ee-simple-file-list-file-ops-bar', 'ee-simple-file-list-file-ops-bar-nonce')) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Back-End File Ops Action Taken'); }

	} elseif(isset($_POST['eeSFL_DownloadFolderForm'])) {

		$zipNonce = isset($_POST['ee-simple-file-list-zip-folder-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-zip-folder-nonce'])) : '';
		if( wp_verify_nonce( $zipNonce, 'ee-simple-file-list-zip-folder') ) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Front-End Download Folder Link Click'); }

	} else {

		$fileOpsNonce = isset($_POST['ee-simple-file-list-file-ops-bar-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-file-ops-bar-nonce'])) : '';
		if( wp_verify_nonce( $fileOpsNonce, 'ee-simple-file-list-file-ops-bar') ) {
			$eeProceed = TRUE;
		} else { exit('ERROR 98 - Front-End File Ops Action Taken'); }
	}

	// SECURITY: A valid nonce only proves the form came from this site, not that the visitor may manage files.
	// Front-end visitors may only download (when bulk download is enabled) unless front-end management is on.
	if($eeProceed && !$eeAdmin && !current_user_can('manage_options')) {
		$eeCanManage = $eeSFL->eeListSettings['AllowFrontManage'] == 'YES';
		$eeCanDownload = $eeCanManage || $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES'
			|| ( isset($_POST['eeSFL_DownloadFolderForm']) && ($eeSFL->eeListSettings['AllowFolderDownload'] ?? '') == 'YES' );
		if( $fileOpsAction == 'Download' ? !$eeCanDownload : !$eeCanManage ) {
			eeSFL_Debug_Log("Bulk operation '" . $fileOpsAction . "' denied for front-end visitor");
			exit('ERROR 97 - Not Allowed');
		}
	}

	if($eeProceed) {

		$eeZipObject = FALSE; // Zip File Object if Downloading

		if($fileOpsAction == 'Folder') { // Create a Folder

			if(strlen($eeNewFolderName)) {

				// exit($eeSFL->eeCurrentFolder);

				eeSFL_Debug_Log("Raw folder name: " . $eeNewFolderName, 'OpsProcess', $eeSFL->eeListID);

				$eeNewFolderName = eeSFL_SanitizeFolderName($eeNewFolderName);
				eeSFL_Debug_Log("Sanitized folder name: " . $eeNewFolderName, 'OpsProcess', $eeSFL->eeListID);
				eeSFL_Debug_Log("FileListDir: " . $eeSFL->eeListSettings['FileListDir'], 'OpsProcess', $eeSFL->eeListID);

				// Add the slash if we are in a sub-folder
				$eeSFL->eeCurrentFolder = eeSFL_NormalizeSlashes($eeSFL->eeCurrentFolder);
				eeSFL_Debug_Log("Current folder: " . $eeSFL->eeCurrentFolder, 'OpsProcess', $eeSFL->eeListID);



				$eeNewPath = $eeSFL->eeListSettings['FileListDir'] . $eeSFL->eeCurrentFolder . $eeNewFolderName;



				eeSFL_Debug_Log("Constructed path: " . $eeNewPath, 'OpsProcess', $eeSFL->eeListID);

				if ($eeSFLF && $eeSFLF->eeSFLF_CreateFolder($eeNewPath) == TRUE) { // Relative to site root

					eeSFL_Debug_Log("New Folder Created. Re-Sorting", 'OpsProcess', $eeSFL->eeListID);

					// Re-Sort, then persist the sorted order so subsequent page loads reflect it correctly
					$eeSFL->eeSFL_SortFiles($eeSFL->eeListSettings['SortBy'], $eeSFL->eeListSettings['SortOrder']);
					$eeSFL->eeSFL_UpdateMainFileArray(false);

					// Get the new list including this new folder
					if($eeSFLF) {
						$eeSFLF->eeSFLF_GetListOfFolders();
					}

				}

			}

		} else { // Bulk File Operations

			// Debug logging for bulk operations
			eeSFL_Debug_Log("BULK POST Action: " . $fileOpsAction, 'Ops Bar Process', $eeSFL->eeListID);
			eeSFL_Debug_Log("BULK POST Files: " . $fileOpsFiles, 'Ops Bar Process', $eeSFL->eeListID);

			$eeString = preg_replace("/[^0-9,]/", "", $fileOpsFiles); // Only numbers and commas
			// $eeString = substr($eeString, 1); // Strip the leading comma
			$eeFileIDs = ($fileOpsFiles !== '' && $fileOpsFiles !== null) ? explode(',', $fileOpsFiles) : array();

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

			if( is_array($eeFileIDs) AND !$eeSFL->eeUserMessages['errors'] ) {

				foreach( $eeFileIDs as $eeThisID) { // Loop thru the checked files

					if( is_numeric($eeThisID) ) { // Might be zero or null

						// Loop through and find this file
						foreach($eeSFL->eeAllFiles as $eeKey => $eeFileArray) {

							if($eeKey == $eeThisID) {

								// The full path
							$eeSiteRoot = defined('eeSFL_WP_ROOT') ? eeSFL_WP_ROOT : ABSPATH;
							$eePath = $eeSiteRoot . $eeSFL->eeListSettings['FileListDir'] . $eeFileArray['FilePath'];
								if($fileOpsAction == 'Download') {

									// Never zip a file this user may not open
									if( !eeSFL_UserCanReadFile($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id()) ) { continue; }

									if( is_object($eeZipObject) ) {

							if($eeFileArray['FileExt'] == 'folder') {

								if($eeSFLF) {
									$eeThisFolder = $eeSFLF->eeSFLF_GetItemsBelow($eeFileArray['FilePath']);
								} else {
									$eeThisFolder = array();
								}

							$eeSiteRoot = defined('eeSFL_WP_ROOT') ? eeSFL_WP_ROOT : ABSPATH;
							eeSFL_Debug_Log('FOLDER DOWNLOAD - Folder: ' . $eeFileArray['FilePath'] . ' | Items: ' . count($eeThisFolder) . ' | Root: ' . $eeSiteRoot, 'OpsProcess', $eeSFL->eeListID);

							if(empty($eeThisFolder)) {
								$eeZipObject->addEmptyDir(rtrim($eeFileArray['FilePath'], '/'));
								eeSFL_Debug_Log('FOLDER DOWNLOAD - Folder is empty, added empty dir to ZIP', 'OpsProcess', $eeSFL->eeListID);
							}

							foreach($eeThisFolder as $eeThisItem) {
								if( !eeSFL_UserCanReadFile($eeSFL->eeListID, $eeSFL->eeListSettings, $eeThisItem, get_current_user_id()) ) { continue; }
								$eePath = $eeSiteRoot . $eeSFL->eeListSettings['FileListDir'] . $eeThisItem['FilePath'];
								eeSFL_Debug_Log('FOLDER DOWNLOAD - Adding: ' . $eePath, 'OpsProcess', $eeSFL->eeListID);
								if(file_exists($eePath)) {
									$eeZipObject->addFile($eePath, $eeThisItem['FilePath']);
								} else {
									eeSFL_Debug_Log('FOLDER DOWNLOAD - File NOT FOUND: ' . $eePath, 'OpsProcess', $eeSFL->eeListID);
								}
							}

										} else {

											eeSFL_Debug_Log('- Adding File: ' . $eePath . ' ——> ' . $eeFileArray['FilePath'] . '<br />', 'General', $eeSFL->eeListID);
											$eeZipObject->addFile($eePath, $eeFileArray['FilePath']);
										}
									}

								} elseif($fileOpsAction == 'Delete') {

									eeSFL_Debug_Log("BULK DELETE - Processing file: " . $eeFileArray['FilePath']);
									eeSFL_Debug_Log("BULK DELETE - File type: " . (isset($eeFileArray['FileExt']) ? $eeFileArray['FileExt'] : 'NOT SET'));
									eeSFL_Debug_Log("BULK DELETE - Full path: " . $eePath);

								if(isset($eeFileArray['FileExt']) && $eeFileArray['FileExt'] == 'folder') {
									// For folders, let eeSFLF_DeleteFolder handle both filesystem and array cleanup
									eeSFL_Debug_Log("BULK DELETE - Calling folder delete for: " . $eePath);
									if($eeSFLF) {
										$deleteResult = $eeSFLF->eeSFLF_DeleteFolder($eePath);
									} else {
										$deleteResult = false;
									}
									if($deleteResult) {
										eeSFL_Debug_Log("BULK DELETE - Folder deleted successfully: " . basename($eePath));
									} else {
										eeSFL_Debug_Log("BULK DELETE - Folder deletion failed: " . basename($eePath));
									}
									} else {
										// For files, remove from array first, then delete file
										eeSFL_Debug_Log("BULK DELETE - Calling file delete for: " . $eePath);
										unset($eeSFL->eeAllFiles[$eeKey]); // Remove the array from the array
										if(count($eeSFL->eeAllFiles) < 1) { $eeSFL->eeAllFiles = array(); $eeFileArray = array(); } // Reset if empty
										$eeDeleteResult = eeSFL_FileSystem('delete', array('file' => $eePath));
										if($eeDeleteResult['success']) { eeSFL_Debug_Log("BULK DELETE - File deleted successfully: " . basename($eePath)); } // Delete File
									}

								} elseif($fileOpsAction == 'Move') {

									eeSFL_Debug_Log('- Moving File: ' . $eeFileArray['FilePath'], 'General', $eeSFL->eeListID);

									// The Source
									$eeSource = $eeSFL->eeListSettings['FileListDir'] . $eeFileArray['FilePath'];

									// The Destination
									$eeDestination = substr($moveToFolder, 0, 1024);

									if($eeFileArray['FileExt'] == 'folder' AND $eeFileArray['FilePath'] == $eeDestination) { // Can't move a folder to itself
										$eeSFL->eeUserMessages['errors'][] = __('A folder cannot be moved to itself', 'simple-file-list') . ': ' . $eeDestination;
										continue; // Skip it
									}

									// The Destination
									$eeDestination = $eeSFL->eeListSettings['FileListDir'] . $eeDestination . basename($eeFileArray['FilePath']);

									if($eeSFLF) {
										$eeResult = $eeSFLF->eeSFLF_Move($eeSource, $eeDestination);
										// Site root relative path expected, List ID or Full File Array
									} else {
										$eeResult = __('Folder extension not available', 'simple-file-list');
									}

									if( $eeResult === TRUE ) {
										eeSFL_Debug_Log('- Move Completed: ' . $eeFileArray['FilePath'], 'General', $eeSFL->eeListID);
									} else {
										$eeSFL->eeUserMessages['errors'][] = $eeResult;
									}

								} elseif($fileOpsAction == 'Description') {

									eeSFL_Debug_Log("Adding Description to Files...", 'General');

									// Sanitize input and add to the file array.
									$eeString = substr($description, 0, 1024);
									if($eeString) {
										$eeSFL->eeAllFiles[$eeKey]['FileDescription'] = $eeString;
										eeSFL_Debug_Log("Description Added", 'General');
									}

								} elseif($fileOpsAction == 'Copy') {

									eeSFL_Debug_Log("Copying Files...", 'General');

									// TO DO

								} elseif($fileOpsAction == 'Grant') {

									eeSFL_Debug_Log("Granting Access to Files...", 'General');

									// TO DO
								}
							}
						}
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


?>