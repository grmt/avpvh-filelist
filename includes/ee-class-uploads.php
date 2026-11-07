<?php

// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



class eeSFL_UploadClass {

	public $eeUploadedFiles = array(); // Save the original file names for an upload job


	public function eeSFL_UploadForm() {

		global $eeSFL;
		 $eeOutput = '';

		$eeObject = $eeSFL;
		$eeListID = $eeObject->eeListID;

		// User Messaging
		 $eeOutput .= $eeObject->eeSFL_ResultsNotification();

		 $eeOutput .= '

		<!-- Simple File List Upload Form -->

		<form action="' . $eeObject->eeSFL_GetThisURL() . '" method="POST" enctype="multipart/form-data" name="eeSFL_UploadForm" id="eeSFL_UploadForm">
		<input type="hidden" name="MAX_FILE_SIZE" value="' . (($eeObject->eeListSettings['UploadMaxFileSize']*1024)*1024) . '" />
		<input type="hidden" name="ee" value="1" />
		<input type="hidden" name="eeSFL_Upload" value="TRUE" />
		<input type="hidden" name="eeListID" value="' . $eeListID . '" />
		<input type="hidden" name="eeSFL_FileCount" value="" id="eeSFL_FileCount" />
		<input type="hidden" name="eeSFL_FileList" value="" id="eeSFL_FileList" />';
		if($eeObject->eeEnvironment['wpUserID'] > 0) {  $eeOutput .= '
		<input type="hidden" name="eeSFL_FileOwner" value="' . $eeObject->eeEnvironment['wpUserID'] . '" id="eeSFL_FileOwner" />
		'; }

		 $eeOutput .= wp_nonce_field( 'ee-simple-file-list-upload-form', 'ee-simple-file-list-upload-form-nonce', TRUE, FALSE);

		 $eeOutput .= '

		<h2 class="eeSFL_UploadFilesTitle">' . __('Upload Files', 'simple-file-list') . '</h2>
		<div class="eeClearFix" id="eeSFL_FileDropZone" ondrop="eeSFL_DropHandler(event);" ondragover="eeSFL_DragOverHandler(event);">';

		$eeName = ''; $eeEmail = '';

		$wpUserObj = wp_get_current_user();

		if(!empty($wpUserObj->user_email)) {
			$eeName = $wpUserObj->first_name . ' ' . $wpUserObj->last_name;
			$eeEmail = $wpUserObj->user_email;
		}

		 $eeOutput .= '
		<div id="eeUploadInfoForm" class="eeClearFix">';

		if($eeObject->eeListSettings['GetUploaderInfo'] == 'YES') {

			// If user is logged in, use hidden fields with their data
			if($eeEmail) {

				 $eeOutput .= '
				<input type="hidden" name="eeSFL_Name" value="' . esc_attr($eeName) . '" id="eeSFL_Name" />
				<input type="hidden" name="eeSFL_Email" value="' . esc_attr($eeEmail) . '" id="eeSFL_Email" />';

			} else {

				// User not logged in, show visible input fields
				 $eeOutput .= '

				<label for="eeSFL_Name">' . __('Name', 'simple-file-list') . ':</label>
				<input type="text" name="eeSFL_Name" value="" id="eeSFL_Name" size="64" maxlength="64" />

				<label for="eeSFL_Email">' . __('Email', 'simple-file-list') . ':</label>
				<input type="text" name="eeSFL_Email" value="" id="eeSFL_Email" size="64" maxlength="128" />';

			}

		}

		if($eeObject->eeListSettings['GetUploaderDesc'] == 'YES') {

			 $eeOutput .= '<label for="eeSFL_FileDesc">' . __('Description', 'simple-file-list') . '</label>

			<textarea placeholder="' . __('Add a description (optional)', 'simple-file-list') . '" name="eeSFL_FileDesc" id="eeSFL_FileDesc" rows="5" cols="64" maxlength="5012"></textarea>';

		}

		 $eeOutput .= '</div>

		<input type="file" name="eeSFL_FileInput" id="eeSFL_FileInput" onchange="eeSFL_FileInputHandler(event)" multiple />
		<p id="eeSFL_FilesDrug"></p>

		<script>
		var eeSFL_ListID = "' . intval($eeListID) . '";
		var eeSFL_FileLimit = ' . intval($eeObject->eeListSettings['UploadLimit']) . ';
		var eeSFL_UploadMaxFileSize = ' . intval(($eeObject->eeListSettings['UploadMaxFileSize']*1024)*1024) . ';
		var eeSFL_FileFormats = "' . esc_js(str_replace(' ' , '', $eeObject->eeListSettings['FileFormats'])) . '";
		var eeSFL_Nonce = "' . esc_js(wp_create_nonce('ee-simple-file-list-upload')) . '";
		var eeSFL_UploadEngineURL = "' . esc_url(admin_url( 'admin-ajax.php')) . '";
		</script>

		<span id="eeSFL_UploadProgress"><em class="eeHide">' . __('Processing the Upload', 'simple-file-list') . '</em></span>
		<div id="eeSFL_FileUploadQueue"></div>
		<button type="button" class="button" name="eeSFL_UploadGo" id="eeSFL_UploadGo" onclick="eeSFL_UploadProcessor(eeSFL_FileObjects);">' . __('Upload', 'simple-file-list') . '</button>';

		if($eeObject->eeListSettings['ShowUploadLimits'] == 'YES') {

			 $eeOutput .= '<p class="sfl_instuctions">' . __('File Limit', 'simple-file-list') . ': ' . intval($eeObject->eeListSettings['UploadLimit']) . ' ' . __('files', 'simple-file-list') . '<br />

			' . __('Size Limit', 'simple-file-list') . ': ' . intval($eeObject->eeListSettings['UploadMaxFileSize']) . ' MB

			' . __('per file', 'simple-file-list') . '.<br />

			' . __('Types Allowed', 'simple-file-list') . ': ' . esc_html(str_replace(',', ', ', $eeObject->eeListSettings['FileFormats']))  . '<br />

			' . __('Drag-and-drop files here or use the Browse button.', 'simple-file-list') . '</p>';

		}

		 $eeOutput .= '
		</div>
		</form>

		<!-- END Upload Form -->';

		return  $eeOutput;
	}





	// Check for an Upload Job
	public function eeSFL_UploadCheck($eeListRun) {

		if($eeListRun > 1 ) { return; }

		global $eeSFL_BASE;
		$eeListID = 1;
		$eeMessages = array('Upload Job Complete');

		$eeUploaded = FALSE; // Show Confirmation

		// Check for an upload job, then run notification routine.
		if(isset($_POST['eeSFL_Upload'])) {

			// Verify nonce for security
			if (!wp_verify_nonce(isset($_POST['ee-simple-file-list-upload-form-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-upload-form-nonce'])) : '', 'ee-simple-file-list-upload-form')) {
				return; // Exit silently if nonce verification fails
			}

			global $eeSFL;
			$eeObject = $eeSFL;

			// Only the list shown here: its settings are the ones checked below
			$eeListID = isset($_POST['eeListID']) ? absint(wp_unslash($_POST['eeListID'])) : 1;
			if( $eeListID !== (int) $eeObject->eeListID ) {
				return FALSE;
			}

			// Enforce AllowUploads policy for callers who don't manage the list
			if( !eeSFL_UserManagesList($eeListID, get_current_user_id()) ) {
				$eeAllowUploads = $eeObject->eeListSettings['AllowUploads'] ?? 'NO';
				if( $eeAllowUploads === 'USER' && !get_current_user_id() ) {
					return FALSE;
				} elseif( $eeAllowUploads !== 'YES' && $eeAllowUploads !== 'USER' ) {
					return FALSE;
				}
			}

			if( $eeListID >=1 ) { $this->eeSFL_ProcessUploadJob($eeListID); $eeObject->eeListID = $eeListID; }

			$eeMessages[] = 'List ID: ' . $eeListID;
			$eeMessages[] = isset($_POST['eeSFL_FileList']) ? sanitize_textarea_field(wp_unslash($_POST['eeSFL_FileList'])) : 'No file list provided'; // json string

			// Add Custom Hook
			if( is_admin() ) {
				$eeMessages[] = 'Back-End Upload Complete';
				do_action('eeSFL_Admin_Hook_Uploaded', $eeMessages);
			} else {
				$eeMessages[] = 'Front-End Upload Complete';
				do_action('eeSFL_Hook_Uploaded', $eeMessages);
			}

			// Legacy hooks
			if( is_admin() ) {
				do_action('eeSFL_UploadCompletedAdmin');
			} else {
				do_action('eeSFL_UploadCompleted');
			}

			if($eeObject->eeListSettings['UploadConfirm'] == 'YES' OR is_admin() ) { $eeUploaded = TRUE; }

		}

		return $eeUploaded;
	}






	// Process an Upload Job, Update the DB as Needed and Return the Results in a Nice Message
	public function eeSFL_ProcessUploadJob($eeListID) {

		global $eeSFL, $eeSFLA, $eeSFL_Tasks;
		$eeObject = $eeSFL;

		$eeObject->eeUserMessages['notice'][] = 'Processing the Upload Job...';

		// Get a list of the original file names that were uploaded. JSON STRING
		$eeFileListString = isset($_POST['eeSFL_FileList']) ? sanitize_textarea_field(wp_unslash($_POST['eeSFL_FileList'])) : '[]'; // ["Sunset2.jpg","Sunset.jpg","Boats.jpg"]
		$eeFileListArray = json_decode($eeFileListString);

		if(!is_array($eeFileListArray)) {

			$eeObject->eeUserMessages['error'][] = 'Upload String Not a JSON Array.';
			return FALSE;
		}


		// Get the File Count
		$eeFileCount = count($eeFileListArray);

		$eeObject->eeUserMessages['notice'][] = '' . $eeFileCount . ' Files Uploaded';

		// Check for Form Nonce
		if(check_admin_referer( 'ee-simple-file-list-upload-form', 'ee-simple-file-list-upload-form-nonce')) {

			$eeUploadJob = ''; // This will be the well-formed message we return

			// Semantics
			if($eeFileCount > 1) {
				$eeUploadJob .= $eeFileCount . ' ' . __('Files Uploaded', 'simple-file-list');
			} else {
				$eeUploadJob .= __('File Uploaded', 'simple-file-list');
			}
			$eeUploadJob .= ":" . PHP_EOL . PHP_EOL;

			// Get the existing array
			if(empty($eeObject->eeAllFiles)) {
				$eeObject->eeAllFiles = get_option('eeSFL_FileList_' . $eeListID);
			}

			// Ensure eeAllFiles is always a valid array (e.g. new install or first upload to a new list)
			if(!is_array($eeObject->eeAllFiles)) {
				eeSFL_Debug_Log("eeAllFiles was not an array for List " . $eeListID . " — initializing to empty array. This is expected on new installs or new lists.", 'Upload', $eeListID);
				$eeObject->eeAllFiles = array();
			}

			// Loop through the uploaded files, original names.
			if(count($eeFileListArray)) {

			foreach($eeFileListArray as $eeKey => $eeFile) {

				$eeFile = sanitize_text_field($eeFile);

				// Security: Check for directory traversal attempts before any processing
				if (strpos($eeFile, '..') !== false || strpos($eeFile, './') !== false) {
					$eeObject->eeUserMessages['error'][] = 'Invalid filename detected: ' . $eeFile;
					continue; // Skip this file
				}

				$eeFile = urlencode($eeFile);

				// Check if Name was Sanitized using hashed transient key
				$eeFileOriginal = FALSE; // Transient is named using hash of original file name

				// Create the same hash key used during upload
				// FIX: Use sanitized filename for hash key to match upload phase
				eeSFL_Debug_Log("Processing phase filename extraction:", 'Upload', $eeObject->eeListID);
				eeSFL_Debug_Log("Raw eeFile: " . $eeFile, 'Upload', $eeObject->eeListID);

				// Extract just the filename part
				$eeOriginalFileName = urldecode($eeFile);

				// If there's still a folder path in the filename, extract just the basename
				if (strpos($eeOriginalFileName, '/') !== false) {
					$eeOriginalFileName = basename($eeOriginalFileName);
				}

				eeSFL_Debug_Log("Extracted original filename: '" . $eeOriginalFileName . "'", 'Upload', $eeObject->eeListID);

				// FIX: Sanitize the extracted filename to match what upload phase would have used
				global $eeSFL;
				$eeSanitizedFileName = $eeSFL->eeSFL_SanitizeFileName($eeOriginalFileName);
				eeSFL_Debug_Log("Re-sanitized for hash: '" . $eeSanitizedFileName . "'", 'Upload', $eeObject->eeListID);

				// Use the extracted filename (which is actually the sanitized filename) for hash consistency
				$eeHashKey = 'eeSFL-Renamed-' . hash('sha256', $eeSanitizedFileName . $eeListID);					// Debug logging for processing phase
				eeSFL_Debug_Log("PROCESSING PHASE - Original: " . $eeOriginalFileName . " | Hash: " . $eeHashKey, 'UPLOAD_HASH');

				$eeFileSanitized = get_transient($eeHashKey);

				// Debug transient result
				eeSFL_Debug_Log("Transient result: " . ($eeFileSanitized ? $eeFileSanitized : 'NOT FOUND'), 'UPLOAD_HASH');

				if($eeFileSanitized) {

					$eeFileOriginal = $eeFile;
					$eeFileSanitized = urldecode($eeFileSanitized); // The sanitized name
					delete_transient($eeHashKey); // Clean up the transient
					$eeFile = $eeFileSanitized;

				} else {
					$eeFile = urldecode($eeFile);
				}

				// Security: Additional directory traversal check after all filename processing
				$eeObject->eeSFL_DetectUpwardTraversal($eeObject->eeListSettings['FileListDir'] . $eeFile);

				// Check to be sure the file is there
				if( is_file($eeObject->eeSFL_GetRootPath() . $eeObject->eeListSettings['FileListDir'] . $eeFile) ) {

					$eeObject->eeUserMessages['notice'][] = 'Creating File Array: ' . $eeFile;

					$eeFound = FALSE;

					// Only look for existing file arrays if we're allowing overwrites
					// If overwrites are disabled, files are renamed and we should always create new entries
					if($eeObject->eeListSettings['AllowOverwrite'] == 'YES') { // Look for existing file array

						foreach( $eeObject->eeAllFiles as $eeKey => $eeThisFileArray ) {
							$eeFound = FALSE;
							if($eeThisFileArray['FilePath'] == $eeFile) { $eeFound = TRUE; break; }
						}

						if($eeFound) {
							$eeNewFileArray = $eeObject->eeSFL_BuildFileArray($eeFile, $eeThisFileArray);
						} else {
							$eeNewFileArray = $eeObject->eeSFL_BuildFileArray($eeFile); // Path relative to FileListDir
						}
					} else {

						$eeNewFileArray = $eeObject->eeSFL_BuildFileArray($eeFile); // Path relative to FileListDir

					}

					// Use Original as the Nice Name (applies regardless of AllowOverwrite setting)
					if($eeFileOriginal AND $eeObject->eeListSettings['PreserveName'] == 'YES') {
						$eeNewFileArray['FileNiceName'] = basename(urldecode($eeFileOriginal)); // The original name
					}



						// Save Owner Info
						$eeID = get_current_user_id();

						if( !is_admin() ) { // Front-end only

							if($eeID === 0) {

								$eeNewFileArray['FileOwner'] = '0'; // Public

								if( isset($_POST['eeSFL_Name'])) {

									$eeString = esc_textarea(sanitize_text_field(wp_unslash($_POST['eeSFL_Name'])));

									if($eeString) {

										$eeNewFileArray['SubmitterName'] = $eeString; // Who uploaded the file
									}
								}

								if( isset($_POST['eeSFL_Email'])) {

									$eeString = filter_var( sanitize_email(wp_unslash($_POST['eeSFL_Email'])), FILTER_VALIDATE_EMAIL);

									if($eeString) {

										$eeNewFileArray['SubmitterEmail'] = $eeString; // Their email
									}
								}

							} else {
								$eeNewFileArray['FileOwner'] = $eeID;
							}
						} else {
							$eeNewFileArray['FileOwner'] = $eeID;
						}



						if( isset($_POST['eeSFL_FileDesc'])) {

							$eeString = esc_textarea(sanitize_text_field(wp_unslash($_POST['eeSFL_FileDesc'])));

					if($eeString) {

						$eeNewFileArray['FileDescription'] = $eeString; // A short description of the file
						$eeNewFileArray['SubmitterComments'] = $eeString; // What they said
					}
				}

				$eeTime = current_time('H:i:s');
				$eeObject->eeUserMessages['notice'][] = $eeTime . ' ——> Done';						$eeNewFileArray = array_filter($eeNewFileArray); // Remove empty elements

						// Use safeguard function to prevent duplicate FilePath entries
						$eeArrayResult = $eeObject->eeSFL_UpdateMainFileArray($eeNewFileArray);
						if ($eeArrayResult === false) {
							$eeObject->eeUserMessages['errors'][] = 'Failed to add file to array: ' . $eeFile;
						}

					// Create thumbnail if needed
						// Create thumbnail if needed
						if(isset($eeSFL_Tasks) AND $eeObject->eeListSettings['ShowFileThumb'] == 'YES') {

							if(( $eeObject->eeListSettings['GeneratePDFThumbs'] == 'YES' AND $eeNewFileArray['FileExt'] == 'pdf' )

							OR ( $eeObject->eeListSettings['GenerateVideoThumbs'] == 'YES' AND in_array($eeNewFileArray['FileExt'], $eeObject->eeDynamicVideoThumbFormats) )

							) {

								// Start the background function: eeSFL_Background_GenerateThumbs()
								if(is_array($eeSFL_Tasks)) {
									$eeSFL_Tasks[$eeObject->eeListID]['GenerateThumbs'] = 'YES';
									update_option('eeSFL_Tasks', $eeSFL_Tasks);
								}
							}
						}


						// Notification Info
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Email notifications use direct links to avoid email client censoring
						$eeFileURL = eeSFL_FileURL($eeObject->eeListID, $eeObject->eeListSettings, $eeFile);

						$eeUploadJob .=  $eeFile . " (" . $eeObject->eeSFL_FormatFileSize($eeNewFileArray['FileSize']) . ")" . PHP_EOL;
						$eeUploadJob .=  $eeFileURL . PHP_EOL . PHP_EOL;
					}

					// Add to our Upload Results Array
					$this->eeUploadedFiles[] = $eeFile;
				}

				// Add the Description
				if(!empty($eeNewFileArray['FileDescription'])) {
					$eeUploadJob .= $eeNewFileArray['FileDescription'] . PHP_EOL . PHP_EOL;
				}

			$eeObject->eeSFL_SortFiles($eeObject->eeListSettings['SortBy'], $eeObject->eeListSettings['SortOrder']);

			// Save the sorted array (safeguard method saved individual files, but sorting changed the order)
			$eeObject->eeSFL_UpdateMainFileArray(false);				$eeObject->eeUserMessages['messages'][] = __('File Upload Complete', 'simple-file-list');

				if( is_admin() ) {

					return TRUE;

				} else  {

				// Upload Email Notice — only send if at least one file was actually saved
				if($eeObject->eeListSettings['Notify'] == 'YES' && count($this->eeUploadedFiles) > 0) {

					// Send the Email Notification
					$eeObject->eeSFL_NotificationEmail($eeUploadJob);
						$_POST = array();
						return TRUE;

					} else {
						$_POST = array();
						return TRUE; // No notice wanted
					}
				}


			} else {
				$_POST = array();
				wp_die('ERROR 98 - ProcessUpload');
			}

		} else {
			$eeObject->eeUserMessages['errors'][] = 'No Files to Process';
			return FALSE;
		}
	}


	// --------------------------------------------------------------------------



	// File Upload Engine
	public function eeSFL_FileUploader() {

		global $eeSFL, $eeSFLA, $eeSFL_Tasks;

		// return print_r($_POST, FALSE);

		$eeObject = $eeSFL;

		// Verify upload nonce before reading any POST data
		$eeUploadNonce = isset($_POST['ee-simple-file-list-upload']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-upload'])) : '';
		if(!wp_verify_nonce($eeUploadNonce, 'ee-simple-file-list-upload')) {
			eeSFL_Debug_Log("Upload nonce verification failed", 'Upload');
			return __('Security check failed.', 'simple-file-list');
		}

		if(isset($_POST['eeSFL_ID'])) { $eeListID = absint(wp_unslash($_POST['eeSFL_ID'])); } else { $eeListID = 1; };

		// Debug: Upload method entry
		eeSFL_Debug_Log("eeSFL_FileUploader() method started", 'Upload', $eeObject->eeListID);

		// The FILE object
		eeSFL_Debug_Log("Checking _FILES object. Count: " . count($_FILES), 'Upload', $eeObject->eeListID);
		if(empty($_FILES)) {
			eeSFL_Debug_Log("_FILES object is empty", 'ERROR', $eeObject->eeListID);
			return 'The File Object is Empty';
		}

		// Get this List's Settings before enforcing AllowUploads policy
		$eeObject->eeSFL_GetSettings($eeListID);
		if( empty($eeObject->eeListSettings['FileListDir']) ) {
			eeSFL_Debug_Log("Upload denied: unknown list ID " . $eeListID, 'ERROR', $eeListID);
			return 'ERROR 97';
		}

		// Enforce AllowUploads policy on every request.
		// SECURITY: is_admin() returns TRUE on admin-ajax.php and cannot guard frontend AJAX uploads.
		// The list's managers are always permitted; all other callers go through the policy check.
		if( !eeSFL_UserManagesList($eeListID, get_current_user_id()) ) {

			eeSFL_Debug_Log("Checking upload permissions for non-admin caller...", 'Upload', $eeObject->eeListID);
			// Who should be uploading?
			switch ($eeObject->eeListSettings['AllowUploads'] ?? 'NO') {
				case 'YES':
					eeSFL_Debug_Log("Upload allowed: YES (all users)", 'Upload', $eeObject->eeListID);
					break;
				case 'USER':
					// Allow logged-in users only
					if( get_current_user_id() ) {
						eeSFL_Debug_Log("Upload allowed: USER (logged in user: " . get_current_user_id() . ")", 'Upload', $eeObject->eeListID);
						break;
					} else {
						eeSFL_Debug_Log("Upload denied: USER setting but not logged in", 'ERROR', $eeObject->eeListID);
						return 'ERROR 97';
					}
				default: // 'ADMIN', 'NO', or any unrecognised value — deny non-admins
					eeSFL_Debug_Log("Upload denied: setting '" . $eeObject->eeListSettings['AllowUploads'] . "' not permitted for this user", 'ERROR', $eeObject->eeListID);
					return 'ERROR 97';
			}
		} else {
			eeSFL_Debug_Log("Upload allowed: user manages the list", 'Upload', $eeObject->eeListID);
		}

		$eeFileUploadDir = $eeObject->eeListSettings['FileListDir'];



		// Check size
		$eeFileSize = isset($_FILES['file']['size']) ? filter_var($_FILES['file']['size'], FILTER_VALIDATE_INT) : 0;
		$eeUploadMaxFileSize = $eeObject->eeListSettings['UploadMaxFileSize']*1024*1024; // Convert MB to B

		if($eeFileSize > $eeUploadMaxFileSize) {
			return __('File size is too large.', 'simple-file-list');
		}

		// Go...
		$eeSiteRoot = $eeObject->eeSFL_GetRootPath(); // Resolves correctly on managed hosts (e.g. Pressable) where ABSPATH != site root
		if(is_dir($eeSiteRoot . $eeFileUploadDir)) {

			if(wp_verify_nonce($eeUploadNonce, 'ee-simple-file-list-upload')) {

				// Temp file
				$eeTempFile = isset($_FILES['file']['tmp_name']) ? sanitize_text_field($_FILES['file']['tmp_name']) : '';

				// Clean up messy names — sanitize_file_name() strips dangerous characters first,
				// then eeSFL_SanitizeFileName() applies plugin-specific rules (encoding, length, etc.)
				$eeRawFileName = isset($_FILES['file']['name']) ? wp_unslash( $_FILES['file']['name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_file_name() in the expression below
				$eeFileName = FALSE;
				if(!empty($eeRawFileName)) {
					$eeFileName = $eeObject->eeSFL_SanitizeFileName(sanitize_file_name($eeRawFileName));
				}

				// WordPress file type and extension validation
				if($eeTempFile && $eeFileName) {
					$eeFileCheck = wp_check_filetype_and_ext($eeTempFile, $eeFileName);

					// Check if the file type is valid
					if(!$eeFileCheck['ext'] || !$eeFileCheck['type']) {
						return __('File type validation failed. The file may be unsafe or not allowed.', 'simple-file-list');
					}

					// Use the validated extension from WordPress
					$eeValidatedExt = $eeFileCheck['ext'];
				}

				// Store the original sanitized filename for hash key consistency
				$eeOriginalSanitizedFileName = $eeFileName;

				// Overwrite only files this user may read: never replace a file restricted to others
				$eeMayOverwrite = $eeObject->eeListSettings['AllowOverwrite'] == 'YES';
				if( $eeMayOverwrite ) {
					$eeExisting = eeSFL_ListedItem(get_option('eeSFL_FileList_' . $eeListID), $eeFileName);
					if( $eeExisting && !eeSFL_UserCanReadFile($eeListID, $eeObject->eeListSettings, $eeExisting, get_current_user_id()) ) {
						$eeMayOverwrite = FALSE;
					}
				}

				// Check if it already exists and get the renamed filename
				if( !$eeMayOverwrite ) {
					$eeRenamedFilePath = $eeObject->eeSFL_CheckForDuplicateFile($eeFileUploadDir . $eeFileName);
					$eeRenamedFileName = basename($eeRenamedFilePath);
					if($eeRenamedFileName != $eeFileName) {
						$eeFileName = $eeRenamedFileName; // Use the renamed filename
					}
				}

				$eeObject->eeSFL_DetectUpwardTraversal($eeFileUploadDir . $eeFileName); // Die if foolishness

				$eePathParts = pathinfo($eeFileName);
				$eeFileNameAlone = $eePathParts['filename'];
				$eeExtension = strtolower($eePathParts['extension']); // We need to do this here and in eeSFL_ProcessUpload()

				// Double-check extension matches WordPress validation
				if(isset($eeValidatedExt) && $eeValidatedExt !== $eeExtension) {
					// Use the WordPress validated extension for additional security
					eeSFL_Debug_Log("Extension mismatch detected. Original: " . $eeExtension . " | Validated: " . $eeValidatedExt, 'Upload', $eeObject->eeListID);
					$eeExtension = $eeValidatedExt;
				}

				// Format Check
				$eeFileFormatsArray = array_map('trim', explode(',', $eeObject->eeListSettings['FileFormats']));

				if(!in_array($eeExtension, $eeFileFormatsArray) OR in_array($eeExtension, $eeObject->eeForbiddenTypes)) {
					return __('File type not allowed', 'simple-file-list') . ': (' . $eeExtension . ')';
				}

				// Assemble FilePath - use the already processed $eeFileName which includes duplicate numbering
				$eeTargetFile = $eeFileUploadDir . $eeFileName;

				// Check if the name has changed
				eeSFL_Debug_Log("Checking filename changes. Original: '" . ($eeRawFileName ?: 'MISSING') . "' | Sanitized: '" . $eeFileName . "'", 'Upload', $eeObject->eeListID);
				if(!empty($eeRawFileName) && $eeRawFileName != $eeFileName) {

					eeSFL_Debug_Log("Filename was changed, creating transient", 'Upload', $eeObject->eeListID);
					// Create a consistent hash key for the transient using original sanitized file name and list ID
					// IMPORTANT: Use the original sanitized filename, NOT the final renamed filename
					// This ensures the processing phase can find the transient with the same hash key

					$eeHashKey = 'eeSFL-Renamed-' . hash('sha256', $eeOriginalSanitizedFileName . $eeListID);
					$eeNewFilePath = str_replace($eeObject->eeListSettings['FileListDir'], '', $eeTargetFile); // Strip the FileListDir

					eeSFL_Debug_Log("Uploading -> Original: " . $eeRawFileName . " | Sanitized: " . $eeOriginalSanitizedFileName . " | Final: " . $eeFileName . " | Hash: " . $eeHashKey, 'UPLOAD_HASH');

					// Set transient with hashed key
					eeSFL_Debug_Log("About to set transient. Key length: " . strlen($eeHashKey) . " | Value length: " . strlen($eeNewFilePath), 'Upload', $eeObject->eeListID);
					eeSFL_Debug_Log("Key: " . $eeHashKey, 'Upload', $eeObject->eeListID);
					eeSFL_Debug_Log("Value: " . $eeNewFilePath, 'Upload', $eeObject->eeListID);

					$transient_result = set_transient($eeHashKey, $eeNewFilePath, 900); // Expires in 15 minutes

					// Test if we can immediately retrieve it
					$test_retrieve = get_transient($eeHashKey);
					eeSFL_Debug_Log("Transient set result: " . ($transient_result ? 'SUCCESS' : 'FAILED') . " | Immediate test retrieve: " . ($test_retrieve ? $test_retrieve : 'FAILED'), 'Upload', $eeObject->eeListID);
				} else {
					eeSFL_Debug_Log("Filename unchanged, no transient needed", 'Upload', $eeObject->eeListID);
				}

				$eeTarget = $eeSiteRoot . $eeTargetFile;

				// return $eeTarget;

				// Save the file
				$eeFileSystemResult = eeSFL_FileSystem('copy', array('from' => $eeTempFile, 'to' => $eeTarget, 'overwrite' => true));
				if( $eeFileSystemResult['success'] ) {

					if(!is_file($eeTarget)) {
						return 'Error - File System Error.'; // No good.
					} else {

						// Check for corrupt images
						if( in_array($eeExtension, $eeObject->eeDynamicImageThumbFormats) ) {

							$eeString = implode('...', getimagesize($eeTarget) );

							if(!strpos($eeString, 'width=') OR !strpos($eeString, 'height=')) { // Make sure it's really an image

								eeSFL_FileSystem('delete', array('file' => $eeTarget));

								return 'Corrupt Image Not Accepted';
							}
						}

						// Update the File Date
						$eeDate = isset($_POST['eeSFL_FileDate']) ? esc_textarea(sanitize_text_field(wp_unslash($_POST['eeSFL_FileDate']))) : '';
						$eeDate = strtotime($eeDate);
						if($eeDate) {
							eeSFL_FileSystem('touch', array('file' => $eeTarget, 'time' => $eeDate));  // Do nothing if bad date
						}

						// Build Image thumbs right away right away. We'll set other types to use the background job within eeSFL_ProcessUpload()
						if($eeObject->eeListSettings['ShowFileThumb'] == 'YES') {
							if( in_array($eeExtension, $eeObject->eeDynamicImageThumbFormats) ) {

								$eeTargetFile = str_replace($eeObject->eeListSettings['FileListDir'], '', $eeTargetFile); // Strip the FileListDir
								$eeObject->eeSFL_CheckThumbnail($eeTargetFile, $eeObject->eeListSettings);
							}
						}

						return 'SUCCESS';
					}

				} else {
					return 'Cannot save the uploaded file: ' . $eeTargetFile;
				}

			} else {

				return 'ERROR 98 - FileUploader';
			}

		} else {
			return 'Upload Path Not Found: ' . $eeFileUploadDir;
		}
	}




	// Clean up orphaned rename transients (called during plugin maintenance)
	public function eeSFL_CleanupRenameTransients() {

		global $wpdb;

		// Clean up any orphaned eeSFL rename transients (they expire in 15 minutes anyway)
		// Using prepared statements for WordPress compliance
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance cleanup operation, no caching needed
		$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_eeSFL-Renamed-%') );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance cleanup operation, no caching needed
		$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_eeSFL-Renamed-%') );

		return true;
	}




	// Get Actual Max Upload Size
	public function eeSFL_ActualUploadMax() {

		$eeEnv = array();

		$eeEnv['upload_max_filesize'] = substr(ini_get('upload_max_filesize'), 0, -1); // PHP Limit (Strip off the "M")
		$eeEnv['post_max_size'] = substr(ini_get('post_max_size'), 0, -1); // PHP Limit (Strip off the "M")

		// Check which is smaller, upload size or post size.
		if ($eeEnv['upload_max_filesize'] <= $eeEnv['post_max_size']) {
			return $eeEnv['upload_max_filesize'];
		} else {
			return $eeEnv['post_max_size'];
		}
	}

}
?>