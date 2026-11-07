<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


class eeSFL_MainClass {

	// public $eeCustomThumbsPath = '';

    public $eeExcludedFileNames = array('error_log', 'index.html', '__MACOSX', '_eeSFL_Thumbnails');

    public $eeForbiddenTypes = array('php','php3','php4','php5','php7','php8','pht','phar','pl','py','com','cgi','asp','exe','js','phtml','wsh','vbs','inc','htm','html','svg','shtml','xhtml');

    public $eeDynamicImageThumbFormats = array('gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff');

    public $eeDynamicVideoThumbFormats = array('avi', 'flv', 'm4v', 'mov', 'mp4', 'webm', 'wmv');

    public $eeDefaultThumbFormats = array('3gp', 'ai', 'aif', 'aiff', 'apk', 'avi', 'bmp', 'cr2', 'dmg', 'doc', 'docx', 'eps', 'flv', 'gz', 'indd', 'iso', 'jpeg', 'jpg', 'm4v', 'mov', 'mp3', 'mp4', 'mpeg', 'mpg', 'pdf', 'png', 'pps', 'ppsx', 'ppt', 'pptx', 'psd', 'tar', 'tgz', 'tif', 'tiff', 'txt', 'wav', 'wma', 'wmv', 'xls', 'xlsx', 'zip');

	public $eeOpenableFileFormats = array('aif', 'aiff', 'avi', 'bmp', 'flv', 'jpeg', 'jpg', 'gif', 'm4v', 'mov', 'mp3', 'mp4', 'mpeg', 'mpg', 'pdf', 'png', 'txt', 'wav', 'wma', 'wmv');


    // END Customizable Properties -----------------------------



    // Default Values
	public $eeLocaleSetting = ''; // For back-end language
    public $eeDefaultUploadLimit = 99;
	public $eeFileThumbSize = 256;
    public $eeArchiveFileTypes= array('zip');
    public $eeTempDirectory = ''; // Full path to temp files directory - set in constructor
    public $eeListRun = 1; // Count of lists per page
    public $eeUploadFormRun = FALSE; // Check if uploader form has run or not


    // User Messaging
    public $eeUserMessages = array('errors' => array(), 'warnings' => array(), 'messages' => array());



	// Get the WordPress Root Directory for File Operations
	public function eeSFL_GetRootPath() {

		if(!defined('eeSFL_WP_ROOT')) {

			// Check transient cache — avoids a filesystem write test on every page load.
			// Cleared on plugin activation (eeSFL_Activate); expires after 24 hours.
			$eeRootPath = get_transient('eeSFL_root_path');
			if($eeRootPath !== false) {
				define('eeSFL_WP_ROOT', $eeRootPath);
				eeSFL_Debug_Log("eeSFL_WP_ROOT (cached) = " . $eeRootPath, 'Environment');
				return $eeRootPath;
			}

			eeSFL_Debug_Log("Checking file operations compatibility...", 'Environment');
			eeSFL_Debug_Log("ABSPATH = " . ABSPATH, 'Environment');

			// Get WordPress uploads directory
			$eeUploadDir = wp_upload_dir();
			if (!empty($eeUploadDir['error'])) {
				$this->eeUserMessages['error'][] = '- Upload directory error: ' . $eeUploadDir['error'];
				return false;
			}

			$eeUploadPath = str_replace('\\', '/', $eeUploadDir['basedir']); // Normalize Windows backslashes before regex
			eeSFL_Debug_Log("WordPress uploads directory: " . $eeUploadPath, 'Environment');

			// Test if we can write to the uploads directory
			$testFile = $eeUploadPath . '/sfl-write-test-' . time() . '.tmp';
			eeSFL_Debug_Log("Testing write permissions: " . $testFile, 'FileSystem');

			$write_test = eeSFL_FileSystem('put_contents', array(
				'file' => $testFile,
				'data' => 'SFL compatibility test'
			));

			if (!$write_test['success']) {
				$this->eeUserMessages['error'][] = '- Cannot write to uploads directory - hosting incompatible';
				eeSFL_Debug_Log("FILESYSTEM ERROR: Cannot write to uploads directory - " . ($write_test['error'] ?? 'Unknown error'), 'ERROR');
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Diagnostic log only, WP_Filesystem not initialized in error-check context
				eeSFL_Debug_Log("Directory permissions: readable=" . (is_readable($eeUploadPath) ? 'YES' : 'NO') . ", writable=" . (is_writable($eeUploadPath) ? 'YES' : 'NO'), 'FileSystem');
				return false;
			}

			// Clean up test file
			$cleanup_result = eeSFL_FileSystem('delete', array('file' => $testFile));
			if (!$cleanup_result['success']) {
				eeSFL_Debug_Log("Warning: Could not clean up test file " . $testFile, 'FileSystem');
			}

			eeSFL_Debug_Log("Uploads directory is writable ✓", 'Environment');
			eeSFL_Debug_Log("File system permissions verified successfully", 'FileSystem');

			// Simple approach: if uploads contains wp-content/uploads, extract the root
			if (preg_match('#(.+)/wp-content/uploads#', $eeUploadPath, $matches)) {
				$eeRootPath = $matches[1] . '/';
				eeSFL_Debug_Log("Extracted root from uploads path: " . $eeRootPath, 'Environment');
			} else {
				// Fallback: use ABSPATH (works for most standard installations)
				$eeRootPath = ABSPATH;
				eeSFL_Debug_Log("Using standard ABSPATH: " . $eeRootPath, 'Environment');
			}

			set_transient('eeSFL_root_path', $eeRootPath, DAY_IN_SECONDS);
			define('eeSFL_WP_ROOT', $eeRootPath);
			eeSFL_Debug_Log("eeSFL_WP_ROOT = " . $eeRootPath, 'Environment');

			return $eeRootPath;

		} else {
			return eeSFL_WP_ROOT;
		}
	}

    // Settings & Environment ---------------------------

    // The List ID
    public $eeListID = 1; // SFLA May Create Additional Lists

    // Settings for the Current List
    public $eeListSettings = array();

    // List Template
    public $eeDefaultListSettings = array( // An array of file list settings arrays

		// List Settings
		'ListTitle' => 'Main File List', // List Title (Not currently used)
		'FileListDir' => 'wp-content/uploads/simple-file-list/', // List Directory Name (relative to site root)
		'UseCache' => 'OFF', // Re-Scan Interval: Each, Hour, Day, OFF
		'UseCacheCron' => 'NO', // Use the Wordpress Cron-like System, or not
		'ShowList' => 'YES', // Show the File List (YES, ADMIN, USER, NO)
		'ShowListStyle' => 'TABLE', // TABLE, TILES or FLEX
		'ShowListTheme' => 'LIGHT', // LIGHT, DRK or NONE
		'AdminRole' => 5, // Who can access settings, based on WP role (5 = Admin ... 1 = Subscriber)
		'ShowFileThumb' => 'YES', // Display the File Thumbnail Column (YES or NO)
		'ShowFileDate' => 'YES', // Display the File Date Column (YES or NO)
		'ShowFileDateAs' => 'Changed', // Which date to show: Added or Changed
		'ShowFileSize' => 'YES', // Display the File Size Column (YES or NO)
		'LabelThumb' => 'Thumb', // Label for the thumbnail column
		'LabelName' => 'Name', // Label for the file name column
		'LabelDate' => 'Date', // Label for the file date column
		'LabelSize' => 'Size', // Label for the file size column
		'LabelDesc' => 'Description', // Label for the file description
		'LabelOwner' => 'Submitter', // Label for the file owner
		'SortBy' => 'Name', // Sort By (Name, Added, Changed, Size, Random)
		'SortOrder' => 'Ascending', // Descending or Ascending
		'ShowSearch' => 'NO', // Show the search form on the front end (always shown in wp-admin)
		'FilesPerPage' => 0, // Files per page; 0 shows all files on one page
		'ShowFileDateAs' => 'Changed', // Which Date shows in the Display
		'MaxSize' => 131072, // (25GB) The maximum size of the list

		// Display Settings
		'GenerateImgThumbs' => 'YES', // Create thumbnail images for images if possible.
		'GeneratePDFThumbs' => 'NO', // Create thumbnail images for PDFs if possible.
		'GenerateVideoThumbs' => 'NO', // Create thumbnail images for videos if possible.
		'PreserveName' => 'YES', // Show the original file name if it had to be sanitized.
		'ShowFileDesc' => 'YES', // Display the File Description (YES or NO)
		'ShowFileActions' => 'YES', // Display the File Action Links Section (below each file name) (YES or NO)
		'ShowFileOpen' => 'YES', // Show this operation
		'ShowFileDownload' => 'YES', // Show this operation
		'ShowFileCopyLink' => 'YES', // Show this operation
		'ShowFileExtension' => 'YES', // Show the file extension, or not.
		'ShowHeader' => 'YES', // Show the File List's Table Header (YES or NO)
		'ShowUploadLimits' => 'YES', // Show the upload limitations text.
		'ShowSubmitterInfo' => 'NO', // Show who uploaded the file (name linked to their email)
		'ShowSubmitterEmail' => 'NO', // Show the submitter's email address as a clickable mailto link
		'AllowFrontManage' => 'NO', // Allow front-side users to manage files (YES or NO)
		'SmoothScroll' => 'YES', // Use the awesome and cool JavaScript smooth scroller after an upload or folder click

		// Downloads
		'AllowBulkFileDownload' => 'NO', // Allow front-end users to download more than one file at a time

		// Upload Settings
		'AllowUploads' => 'NO', // Allow File Uploads (YES, ADMIN, USER, NO); front-end uploads are an explicit opt-in
		'UploadLimit' => 10, // Limit Files Per Upload Job (Quantity)
		'UploadMaxFileSize' => 8, // Maximum Size per File (MB)
		'FileFormats' => 'jpg, jpeg, png, tif, pdf, mov, mp4, mp3, zip', // Allowed Formats
		'AllowOverwrite' => 'NO', // Number new files with same name, or just overwrite.
		'UploadConfirm' => 'YES', // Show the upload confirmation screen, or go right back to the list.
		'UploadPosition' => 'Above', // Above or Below the list
		'GetUploaderDesc' => 'NO', // Show the Description Form
		'GetUploaderInfo' => 'NO', // Show the User Info Form

		// Notifications
		'Notify' => 'NO', // Send Notifications (YES or NO)

		// Email Sharing (integrated from extension)
		'AllowFrontSend' => 'NO', // Allow users to send file links via email
		'BccFileSender' => 'NO', // BCC sender on their own emails
		'NotifyTo' => '', // Send Notification Email Here (Defaults to WP Admin Email)
		'NotifyCc' => '', // Send Copies of Notification Emails Here
		'NotifyBcc' => '', // Send Blind Copies of Notification Emails Here
		'NotifyFrom' => '', // The sender email (reply-to) (Defaults to WP Admin Email)
		'NotifyFromName' => 'Simple File List', // The nice name of the sender
		'NotifySubject' => 'File Upload Notice', // The subject line
		'NotifyMessage' => '', // The notice message's body

		// Media Player Settings
		'AudioEnabled' => 'YES', // Enable inline audio player
		'AudioHeight' => 20, // Audio player height in pixels

		// Extensions will add to this as needed
	);


	// Get Settings for Specified List
	// The list ID often comes from a request (eeSFL_ID, eeListID, id). Only
	// list 1 restores its defaults when its settings are missing; any other
	// ID without stored settings is unknown and yields an empty array, so a
	// request can never create a new list with the default settings.
    public function eeSFL_GetSettings($eeListID) {

		$eeListID = is_numeric($eeListID) ? (int) $eeListID : 0;
		$eeStored = $eeListID >= 1 ? get_option('eeSFL_Settings_' . $eeListID) : FALSE;

		if(is_array($eeStored)) {
			// Merge with defaults to ensure all keys exist
			$this->eeListSettings = array_merge($this->eeDefaultListSettings, $eeStored);
		} elseif($eeListID === 1) {
			$this->eeUserMessages['warnings'][] = 'No Settings Found. Restoring the defaults ...';
			update_option('eeSFL_Settings_1', $this->eeDefaultListSettings); // The settings are gone, so reset to defaults.
			$this->eeListSettings = $this->eeDefaultListSettings;
		} else {
			eeSFL_Debug_Log("Unknown list ID: " . $eeListID, 'Settings');
			$this->eeListSettings = array();
			return $this->eeListSettings;
		}

		$this->eeListSettings['FileListURL'] = $this->eeEnvironment['wpSiteURL'] . $this->eeListSettings['FileListDir']; // The Full URL

		// Folder settings from the old Pro version (there are no subfolders)
		unset($this->eeListSettings['ShowBreadCrumb'], $this->eeListSettings['FoldersFirst'], $this->eeListSettings['AllowFolderDownload'], $this->eeListSettings['ShowFolderSize']);

		// Set up temp directory constants (path only — no disk write).
		// Always initialized here because admins always have bulk download available
		// regardless of the AllowBulkFileDownload setting.
		// Actual directory creation is deferred to eeSFL_EnsureTempDirectory() at point of use.
		$this->eeSFL_TempDirectory();
		$this->eeSFL_EnsureTempDirectory(); // Create on every list load — not just on rescan or download

		return $this->eeListSettings;
	}




	// Environment Details
	public $eeEnvironment = array();

	// Get Environment
    public function eeSFL_GetEnv() {

	    global $eeSFLU;

	    $eeEnv = array();

	    // Detect OS
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		    $eeEnv['eeOS'] = 'WINDOWS';
		} else {
		    $eeEnv['eeOS'] = 'LINUX';
		}

		// Detect Web Server
		if(!function_exists('apache_get_version')) {
		    $eeEnv['eeWebServer'] = isset($_SERVER["SERVER_SOFTWARE"]) ? sanitize_text_field(wp_unslash($_SERVER["SERVER_SOFTWARE"])) : 'Unknown';
		} else {
			$eeEnv['eeWebServer'] = 'Apache';
		}

		$eeEnv['wpSiteURL'] = get_option('siteurl') . '/'; // This Wordpress Website
		$eeEnv['wpPluginsURL'] = plugins_url() . '/'; // The Wordpress Plugins Location

		$eeEnv['pluginURL'] = plugins_url() . '/' . eeSFL_PluginSlug . '/';
		$eeEnv['pluginDir'] = WP_PLUGIN_DIR . '/' . eeSFL_PluginSlug . '/';

		$wpUploadArray = wp_upload_dir();
		$wpUploadDir = $wpUploadArray['basedir'];
		$eeEnv['wpUploadDir'] = $wpUploadDir . '/'; // The Wordpress Uploads Location
		$eeEnv['wpUploadURL'] = $wpUploadArray['baseurl'] . '/';

		$eeEnv['FileListDefaultDir'] = str_replace($this->eeSFL_GetRootPath(), '', $eeEnv['wpUploadDir'] . eeSFL_FileListDefaultDir); // The default file list location

		$eeEnv['php_version'] = phpversion(); // PHP Version

		$eeEnv['php_max_execution_time'] = ini_get('max_execution_time');

		$eeEnv['php_memory_limit'] = ini_get('memory_limit');

		$eeEnv['the_max_upload_size'] = $eeSFLU->eeSFL_ActualUploadMax();

		$eeEnv['supported'] = get_option('eeSFL_Supported'); // Server technologies available (i.e. FFMPEG)

		$eeEnv['wpUserID'] = get_current_user_id();

		// Check Server technologies available (i.e. ffMpeg)
		$eeSupported = get_option('eeSFL_Supported');

		if(is_array($eeSupported)) {

			if( in_array('GhostScript', $eeSupported) ) {
				$eeEnv['GhostScript'] = 'YES';
			}
			if( in_array('ffMpeg', $eeSupported) ) {
				$eeEnv['ffMpeg'] = 'YES';
			}
		}

		ksort($eeEnv);

		$this->eeEnvironment = $eeEnv;
    }





	/**
	 * Set up the temporary directory for ZIP downloads and debug logs.
	 * Defines eeSFL_TempDir and eeSFL_TempUrl constants and sets eeTempDirectory.
	 * Migrates any existing files from the old wp-content location if present.
	 * Does NOT create the directory — call eeSFL_EnsureTempDirectory() at point of use.
	 */
	public function eeSFL_TempDirectory() {

		$new_dir = wp_upload_dir()['basedir'] . '/simple-file-list-temp-files';
		$new_url = wp_upload_dir()['baseurl']  . '/simple-file-list-temp-files';

		if(!defined('eeSFL_TempDir')) { define('eeSFL_TempDir', $new_dir); }
		if(!defined('eeSFL_TempUrl')) { define('eeSFL_TempUrl', $new_url); }

		$this->eeTempDirectory = eeSFL_TempDir;

		// Migrate files from old wp-content location if it still exists
		// We can remove this in 6.2.3
		$old_dir = WP_CONTENT_DIR . '/simple-file-list-temp-files';
		if(is_dir($old_dir) && realpath($old_dir) !== realpath($new_dir)) {

			wp_mkdir_p($new_dir); // Need it to exist for the copy
			$all_moved = true;

			foreach(glob($old_dir . '/*') ?: array() as $old_file) {
				if(is_file($old_file)) {
					$dest = $new_dir . '/' . basename($old_file);
					if(copy($old_file, $dest)) {
						wp_delete_file($old_file);
					} else {
						$all_moved = false;
					}
				}
			}

			// Remove old directory if now empty
			if($all_moved && count(glob($old_dir . '/*') ?: array()) === 0) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Migration block (removed in 6.2.3), WP_Filesystem not initialized here
				@rmdir($old_dir);
				eeSFL_Debug_Log('Temp directory migrated from wp-content to uploads.', 'General');
			} else {
				eeSFL_Debug_Log('Temp directory migration incomplete — old directory kept.', 'General');
			}
		}

		return $this->eeTempDirectory;
	}





	/**
	 * Ensure the temp directory exists at the point of use (ZIP downloads, etc.).
	 * Creates the directory if needed. Shows an admin notice and returns FALSE
	 * if the directory cannot be written so the caller can abort gracefully.
	 *
	 * @return bool TRUE if the directory is ready, FALSE if it could not be created.
	 */
	public function eeSFL_EnsureTempDirectory() {

		// eeTempDirectory may be empty if AllowBulkFileDownload was
		// both NO when settings loaded (e.g. admin triggering a folder download on a list
		// that hasn't enabled the feature). Initialize it now so we have a valid path.
		if(empty($this->eeTempDirectory)) { $this->eeSFL_TempDirectory(); }

		if(is_dir($this->eeTempDirectory)) {
			$index_file = trailingslashit($this->eeTempDirectory) . 'index.html';
			if(!file_exists($index_file)) {
				eeSFL_FileSystem('put_contents', array('file' => $index_file, 'data' => '<!-- Simple File List -->'));
			}
			return TRUE;
		} // Already exists, nothing to do

		// The temp dir always lives inside wp-content/uploads which is natively writable.
		// Use wp_mkdir_p() directly rather than eeSFL_FileListDirCheck(), which expects a
		// path relative to eeSFL_WP_ROOT — a conversion that breaks on managed hosts like
		// Pressable where ABSPATH (/wordpress/core/x.x.x/) differs from the uploads root.
		if(wp_mkdir_p($this->eeTempDirectory)) {
			$index_file = trailingslashit($this->eeTempDirectory) . 'index.html';
			if(!file_exists($index_file)) {
				eeSFL_FileSystem('put_contents', array('file' => $index_file, 'data' => '<!-- Simple File List -->'));
			}
			eeSFL_Debug_Log('Temp directory created: ' . $this->eeTempDirectory, 'General');
			return TRUE;
		}

		eeSFL_Debug_Log('CRITICAL: Cannot create temp directory: ' . $this->eeTempDirectory, 'ERROR');
		$upload_base = wp_upload_dir()['basedir'];
		add_action('admin_notices', function() use ($upload_base) {
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p><strong>' . esc_html__('Simple File List:', 'simple-file-list') . '</strong> ' .
				esc_html__('The temporary directory could not be created inside your uploads folder. ZIP downloads will be unavailable until write permissions are granted to:', 'simple-file-list') .
				' <code>' . esc_html($upload_base) . '</code></p>';
			echo '</div>';
		});
		return FALSE;
	}





	// FILES ----------------

	// All Files and Folders for a Given List (Big)
	public $eeAllFiles = array();

	// Files and Folders to Display (Small)
	public $eeDisplayFiles = array();

	// Original and Sanitized Names
	public $eeSanitizedFiles = array();

	// The Current File to Consider
	public $eeFileArray = array();
	public $eeIsFile = FALSE;
	public $eeFilePath = FALSE;
	public $eeFileURL = FALSE;
	public $eeFileThumbURL = FALSE;
	public $eeFileName = FALSE;
	public $eeFileExt = FALSE;
	public $eeRealFileName = FALSE;
	public $eeFileMIME = FALSE;
	public $eeFileNiceName = FALSE;
	public $eeFileDescription = FALSE;
	public $eeFileDateAdded = FALSE;
	public $eeFileDateChanged = FALSE;
	public $eeFileDate = FALSE;
	public $eeFileSize = FALSE;
	public $eeFileOwner = FALSE;
	public $eeFileSubmitterEmail = FALSE;
	public $eeFileSubmitterName = FALSE;
	public $eeFileSubmitterComments = FALSE;

	public $eeAccessUsers = array(); // SFLA
	public $eeAccessRole = FALSE;

	// Total Counts
	public $eeFileCount = 0;
	public $eeItemCount = 0;


    // File Array Template
    public $eeFileTemplate = array(

		0 => array( // The File ID (We copy this to the array on-the-fly when sorting)
			'FilePath' => '', // Path to file, relative to the FileListDir
		    'FileExt' => '', // The file extension
		    'FileMIME' => '', // The file's MIME type
			'FileSize' => 0, // The size of the file
			'FileDateAdded' => '', // Date the file was added to the list
			'FileDateChanged' => '', // Last date the file was renamed or otherwise changed
			'FileDescription' => '', // A short description of the file
			'FileNiceName' => '', // A name that will replace the actual file name
			'FileOwner' => '', // The logged-in user who added the file
			'SubmitterName' => '', // The full name of who added the file
			'SubmitterEmail' => '', // Their email
			'SubmitterComments' => '', // What they said

			'AccessUsers' => array(), // SFLA - Array of User IDs
			'AccessRole' => '' // SFLA - Role, ID (Min) or String Name (Match)
		)
    );




    public function eeSFL_GetFileList($eeForceSort = FALSE) {

	    global $eeURL;

		if(!empty($eeURL)) {
			$eeURL = remove_query_arg('eeReScan', $eeURL);
		}

	    $eeDoScan = TRUE; // Always scan on each page load

		if($eeDoScan) {

			$this->eeSFL_UpdateFileListArray($this->eeListID); // Scan the Disk

		} else {

			eeSFL_Debug_Log("Getting File List Array from the Database ...", 'List', $this->eeListID);
			$db_start_time = microtime(true);
			$this->eeAllFiles = get_option('eeSFL_FileList_' . $this->eeListID); // Get from the database
			$db_execution_time = round((microtime(true) - $db_start_time) * 1000, 2);

			if(empty($this->eeAllFiles)) {
				eeSFL_Debug_Log("Database returned empty result for List ID " . $this->eeListID . " (query: {$db_execution_time}ms) - scanning disk", 'DATABASE', $this->eeListID);
				$this->eeSFL_UpdateFileListArray($this->eeListID); // Scan the Disk
			} else {
				$file_count = is_array($this->eeAllFiles) ? count($this->eeAllFiles) : 0;
				eeSFL_Debug_Log("Database query completed: {$file_count} files retrieved in {$db_execution_time}ms", 'DATABASE', $this->eeListID);
			}
		}

		// Always sort on load so the list reflects current sort settings,
		// even after an edit that only updates the DB without re-sorting.
		$this->eeSFL_SortFiles($this->eeListSettings['SortBy'], $this->eeListSettings['SortOrder']);

		return TRUE;
	}





    public function eeSFL_ReturnFileActions($eeFileID, $eeFileArray) {

		global $eeSFLA, $eeSFLE;

		$eeAdmin = is_admin();

		 $eeOutput = '

		<small class="eeSFL_ListFileActions">';

		// Open Action
		if($eeAdmin OR $this->eeListSettings['ShowFileOpen'] == 'YES') {

			if(in_array($this->eeFileExt, $this->eeOpenableFileFormats)) {

				 $eeOutput .= '
				<a class="eeSFL_FileOpen" href="' . esc_url($this->eeFileURL) . '" ';

				$eeOutput .= 'target="_blank"';

				 $eeOutput .= '>' . __('Open', 'simple-file-list') . '</a>';
			}
		}

		// File Download
		if($eeAdmin OR $this->eeListSettings['ShowFileDownload'] == 'YES') {

			// A protected list's handler sends the file as a download itself
			$eeDownloadURL = eeSFL_FileURL($this->eeListID, $this->eeListSettings, $this->eeFilePath, array('download' => TRUE));

			 $eeOutput .= '
			<a class="eeSFL_FileDownload" href="' . esc_url($eeDownloadURL) . '" download="' . esc_attr($this->eeRealFileName) . '">' . __('Download', 'simple-file-list') . '</a>';

		}

		// Copy Link Action
		if($eeAdmin OR $this->eeListSettings['ShowFileCopyLink'] == 'YES') {

			 $eeOutput .= '
			<a class="eeSFL_CopyLinkToClipboard" onclick="eeSFL_CopyLinkToClipboard(\''  . esc_js($this->eeFileURL) .   '\')" href="#">' . __('Copy Link', 'simple-file-list') . '</a>';
		}


		// Extension Check
		if($eeSFLE AND $this->eeIsFile AND $this->eeListRun == 1) {

			if(isset($this->eeListSettings['AllowFrontSend'])) {
				if($this->eeListSettings['AllowFrontSend'] == 'YES') {
					 $eeOutput .= '
					<a href="" onclick="eeSFLE_SendFile(' . $eeFileID . ')">' . __('Send', 'simple-file-list') . '</a>';
				}
			}
		}


		// Front-End Manage or Admin
		if( ($eeAdmin OR $this->eeListSettings['AllowFrontManage'] == 'YES') AND $this->eeListRun == 1) {

			 $eeOutput .= '
			<a href="#" onclick="eeSFL_OpenEditModal(' . $eeFileID . ')">' . __('Edit', 'simple-file-list') . '</a>
			<a href="#" onclick="eeSFL_DeleteFile(' . $eeFileID . ')">' . __('Delete', 'simple-file-list') . '</a>';

		}


		// Who may open this file (wp-admin)
		if($eeAdmin AND $this->eeIsFile) {

			$eeAccessURL = admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=access&eeListID=' . $this->eeListID . '&eeFile=' . rawurlencode($this->eeFilePath));
			$eeHasGrant = !eeSFL_GrantIsEmpty(eeSFL_FileGrant($eeFileArray));

			 $eeOutput .= '
			<a href="' . esc_url($eeAccessURL) . '">' . ($eeHasGrant ? esc_html__('Access (limited)', 'simple-file-list') : esc_html__('Access', 'simple-file-list')) . '</a>';
		}

		// Extension Check
		if($eeSFLA) {

			if($eeAdmin OR $this->eeListSettings['AllowCopyToList'] == 'YES') {
				 $eeOutput .= '
				<a id="eeSFLA_FileCopy_' . $eeFileID . '" onclick="eeSFLA_OpenCopyModal(' . $eeFileID . ');" href="#" >' . __('Copy To', 'simple-file-list') . '</a>';
			}

			if((isset($this->eeListSettings['Mode']) && $this->eeListSettings['Mode'] == 'USER') OR
			   (isset($this->eeListSettings['Mode']) && $this->eeListSettings['Mode'] == 'RESTRICTED')) {

				$eeAccessUsers = '';
				if(is_array($this->eeAccessUsers)) { $eeAccessUsers = implode(',', $this->eeAccessUsers); }

				$eeApplyToChildren = isset($eeFileArray['ApplyToChildren']) ? $eeFileArray['ApplyToChildren'] : 'NO';

				 $eeOutput .= '
				<span class="eeHide eeSFLA_Access_Users">' . $eeAccessUsers . '</span>
				<span class="eeHide eeSFLA_Access_Role">' . $this->eeAccessRole . '</span>
				<span class="eeHide eeSFLA_ApplyToChildren">' . $eeApplyToChildren . '</span>';

				// Show Access button only for list owner or admin (backend or bypass)
				if($eeAdmin OR (isset($eeSFLA) && $eeSFLA->eeSFLA_IsListOwner)) {
					// Check if this item inherits from a parent folder with ApplyToChildren
					$isInherited = false;
					if(function_exists('eeSFLA_FolderHasApplyToChildren') && eeSFLA_FolderHasApplyToChildren()) {
						$isInherited = true;
					}

					// Only show Access button if not inherited from parent folder
					if(!$isInherited) {
						 $eeOutput .= '<a id="eeSFLA_FileAccess_' . $eeFileID . '" onclick="eeSFLA_OpenAccessModal(' . $eeFileID . ');" href="#" >' . __('Access', 'simple-file-list');
						 $eeOutput .= '<span class="eeSFLS_HasAccess_Accent">' . eeSFLA_HasAccessHTML($eeFileArray) . '</span>';
						 $eeOutput .= '</a>';
					}
				}
			}
		}


		// File Details to Pass to the Editor

		$eeArray = explode(' ', $this->eeFileDateAdded);
		$eeDateAdded = $eeArray[0];
		$eeArray = explode(' ', $this->eeFileDateChanged);
		$eeDateChanged = $eeArray[0];

		 $eeOutput .= '

		<span class="eeHide eeSFL_FileSize">' . $this->eeFileSize . '</span>
		<span class="eeHide eeSFL_FileDateAdded">' . $eeDateAdded . '</span>
		<span class="eeHide eeSFL_FileDateAddedNice">' . date_i18n( get_option('date_format'), strtotime( $eeDateAdded ) ) . '</span>
		<span class="eeHide eeSFL_FileDateChanged">' . $eeDateChanged . '</span>
		<span class="eeHide eeSFL_FileDateChangedNice">' . date_i18n( get_option('date_format'), strtotime( $eeDateChanged ) ) . '</span>

		</small>'; // Close File List Actions Links

		return  $eeOutput;

    }





    // Update the details of an item - Accepts a referenced array or list ID
    public function eeSFL_UpdateFileDetail($eeFilePath, $eeDetail, $eeNewInfo) {  // FilePath, What's changing, New Info

	    foreach( $this->eeAllFiles as $eeKey => $eeFileArray ) {
			if( $eeFileArray['FilePath'] == $eeFilePath) { // Look for this file
				$this->eeAllFiles[$eeKey][$eeDetail] = $eeNewInfo; // Update this detail
			}
		}

		// Update the Database
		$this->eeSFL_UpdateMainFileArray(false);

		// A rename moves the thumbnail along
		if($eeDetail == 'FilePath') {
			$this->eeSFL_UpdateThumbnail($eeFilePath, $eeNewInfo);
		}

		return TRUE;
	}



    public function eeSFL_ProcessFileArray($eeFileArray, $eeHideName = FALSE, $eeHideType = FALSE) {

	    global $eeSFLA;

	    if( is_admin() ) { $eeAdmin = TRUE; } else { $eeAdmin = FALSE; }

	    if( is_array($eeFileArray) ) {

			// Files with their own access grant are only listed for the people in it
			if( !eeSFL_UserCanReadFile($this->eeListID, $this->eeListSettings, $eeFileArray, get_current_user_id()) ) { return FALSE; }

			// Internal file tracking removed - was debug only

			// Assign values to our properties

			// The File Info
			$this->eeFilePath = $eeFileArray['FilePath']; // Path relative to FileListDir
			$this->eeFileName = basename($eeFileArray['FilePath']); // This name might change
			$this->eeRealFileName = $this->eeFileName; // Never changed
			$this->eeFileExt = $eeFileArray['FileExt']; // Just the name
			$this->eeFileURL = eeSFL_FileURL($this->eeListID, $this->eeListSettings, $this->eeFilePath); // Clickable URL (the download handler for a protected list)
			$this->eeFileSize = $this->eeSFL_FormatFileSize($eeFileArray['FileSize']); // Formatted Size
			$this->eeFileDateAdded = $eeFileArray['FileDateAdded'];
			$this->eeFileDateChanged = $eeFileArray['FileDateChanged'];
			if(isset($eeFileArray['FileMIME'])) {
				$this->eeFileMIME = $eeFileArray['FileMIME'];
			} else {
				$this->eeFileMIME = 'no/mime';
			}




			// Reset These
			$this->eeIsFile = FALSE;
			$this->eeFileNiceName = FALSE;
			$this->eeFileDescription = FALSE;
			$this->eeFileSubmitterComments = FALSE;
			$this->eeFileOwner = FALSE;
			$this->eeFileSubmitterEmail = FALSE;
			$this->eeFileSubmitterName = FALSE;
			$this->eeFileSubmitterComments = FALSE;
			$this->eeAccessUsers = FALSE;
			$this->eeAccessRole = FALSE;


			// LEGACY - Skip names hidden via shortcode
			if($eeHideName) { // Expecting a comma delimited string of file names
				$eeArray = explode(',', $eeHideName);
				foreach( $eeArray as $eeKey => $eeValue ) {
					if($eeValue == $this->eeFilePath) { return FALSE; } // File
				}
			}


			// Must Be a File
			if( strpos($eeFileArray['FilePath'], '.') ) { // This is a File

				$this->eeIsFile = TRUE;

				$this->eeFileCount++; // Bump the file count

				// Skip types hidden via shortcode
				if($eeHideType) { // Expecting a comma deleimited string of extensions
					if(strpos($eeHideType, $this->eeFileExt) OR strpos($eeHideType, $this->eeFileExt) === 0 ) {
						return FALSE;
					}
				}

				// Thumbnail
				$eeThumbSet = FALSE;
				$eeHasCreatedThumb = FALSE;
				if( in_array($this->eeFileExt,  $this->eeDynamicImageThumbFormats) AND $this->eeListSettings['GenerateImgThumbs'] == 'YES' ) { $eeHasCreatedThumb = TRUE; }
				if( in_array($this->eeFileExt,  $this->eeDynamicVideoThumbFormats) AND isset($this->eeEnvironment['ffMpeg']) AND $this->eeListSettings['GenerateVideoThumbs'] == 'YES' ) { $eeHasCreatedThumb = TRUE; }
				if( $this->eeFileExt == 'pdf' AND isset($this->eeEnvironment['GhostScript']) AND $this->eeListSettings['GeneratePDFThumbs'] == 'YES' ) { $eeHasCreatedThumb = TRUE; }

				if($eeHasCreatedThumb) { // Images use .jpg files

					$eePathParts = pathinfo($this->eeFilePath);

					if($eePathParts['dirname'] AND $eePathParts['dirname'] != '.') { $eeFolder = $eePathParts['dirname'] . '/'; } else { $eeFolder = ''; }

					$eeFileThumbPath = $this->eeSFL_GetRootPath() . $this->eeListSettings['FileListDir'] . $eeFolder . '_eeSFL_Thumbnails/thumb_' . $eePathParts['filename'] . '.jpg';

					$thumb_check = eeSFL_FileSystem('exists', array('file' => $eeFileThumbPath));
					if( $thumb_check['data'] && eeSFL_ListIsProtected($this->eeListSettings) ) {
						$this->eeFileThumbURL = eeSFL_ProtectedFileURL($this->eeListID, $this->eeFilePath, array('thumb' => TRUE));
						$eeThumbSet = TRUE;
					} elseif( $thumb_check['data'] ) {
						$eeFileThumbURL = $this->eeListSettings['FileListURL'];
						if($eePathParts['dirname']) { $eeFileThumbURL .= $eePathParts['dirname'] . '/'; }
						$this->eeFileThumbURL = $eeFileThumbURL . '_eeSFL_Thumbnails/thumb_' . $eePathParts['filename'] . '.jpg';
						$eeThumbSet = TRUE;
					}
				}

				if(!$eeThumbSet) {

					// Use our awesome .svg files
					if( !in_array($this->eeFileExt, $this->eeDefaultThumbFormats) ) { $eeDefaultThumb = 'default.svg'; } // What the heck is this?
							else { $eeDefaultThumb = $this->eeFileExt . '.svg'; } // Use our sweet icon

					$this->eeFileThumbURL = $this->eeEnvironment['pluginURL'] . 'images/thumbnails/' . $eeDefaultThumb;

				}

			} else {

				return FALSE;
			}



			// File Nice Name
			if( isset($eeFileArray['FileNiceName']) ) {
				if( strlen($eeFileArray['FileNiceName']) >= 1 ) {
					// html_entity_decode fixes values previously corrupted by esc_textarea() on save
					$this->eeFileNiceName = html_entity_decode($eeFileArray['FileNiceName'], ENT_QUOTES, 'UTF-8');
					$this->eeFileName = $this->eeFileNiceName;
				}
			}

			if($this->eeFileNiceName === FALSE) {

				// Strip the Extension?
				if(!$eeAdmin AND $this->eeListSettings['ShowFileExtension'] == 'NO' AND $this->eeIsFile) {
					$eePathParts = pathinfo($this->eeRealFileName);
					$this->eeFileName = $eePathParts['filename'];
				}

				// LEGACY - Replace hyphens with spaces?
				if(isset($this->eeListSettings['PreserveSpaces'])) {
					if(!$eeAdmin AND $this->eeListSettings['PreserveSpaces'] == 'YES') {
						$this->eeFileName = eeSFL_PreserveSpaces($this->eeRealFileName);
					}
				}
			}

			if( isset($eeFileArray['FileDescription']) ) {
				// html_entity_decode fixes values previously corrupted by esc_textarea() on save
				$this->eeFileDescription = html_entity_decode($eeFileArray['FileDescription'], ENT_QUOTES, 'UTF-8');
			}

			if( isset($eeFileArray['SubmitterComments']) ) {
				if(!$this->eeFileDescription) {
					$this->eeFileDescription = $eeFileArray['SubmitterComments']; // Show the submitter comment if no desc
					$this->eeFileSubmitterComments = $eeFileArray['SubmitterComments']; // Use on back-end
				}
			}

			// File Dates and the Display Date
			if($this->eeListSettings['ShowFileDateAs'] == 'Changed') {
				$this->eeFileDate = date_i18n( get_option('date_format'), strtotime( $this->eeFileDateChanged ) );
			} else {
				$this->eeFileDate = date_i18n( get_option('date_format'), strtotime( $this->eeFileDateAdded ) );
			}

			// Submitter Info
			if(isset($eeFileArray['FileOwner'])) { // User or Public

				if( is_numeric($eeFileArray['FileOwner']) ) {
					$this->eeFileOwner = $eeFileArray['FileOwner']; // The User ID
					$wpUserData = get_userdata($this->eeFileOwner);
					if(!empty($wpUserData->user_email)) {
						$this->eeFileSubmitterEmail = $wpUserData->user_email;
						$this->eeFileSubmitterName = $wpUserData->first_name . ' ' . $wpUserData->last_name;
					}
				}

			} elseif( isset($eeFileArray['SubmitterName']) AND isset($eeFileArray['SubmitterEmail']) ) {

				$this->eeFileSubmitterName = $eeFileArray['SubmitterName'];
				$this->eeFileSubmitterEmail = $eeFileArray['SubmitterEmail'];

			}

			// Extension Check
			if($eeSFLA) {
				if(isset($this->eeListSettings['Mode']) && $this->eeListSettings['Mode'] != 'NORMAL' AND $this->eeIsFile) {
					// Build file access URL (no nonce — links are shareable/persistent)
					$this->eeFileURL = add_query_arg(array(
						'list' => $this->eeListID,
						'file' => $eeFileArray['FilePath'],
					), $this->eeEnvironment['wpSiteURL'] . 'ee-get-file/');
				}
				if(isset($eeFileArray['AccessUsers'])) { $this->eeAccessUsers = $eeFileArray['AccessUsers']; }
				if(isset($eeFileArray['AccessRole'])) { $this->eeAccessRole = $eeFileArray['AccessRole']; }
			}
		}

		$eeMessages = array($eeFileArray);
		do_action('eeSFL_Hook_Listed', $eeMessages);

	    return TRUE; // Properties have been updated
	}







	// Build a New File/Folder Array (for an upload or new file found)
	public function eeSFL_BuildFileArray($eeFilePath, $eeFileArray = FALSE) { // Path relative to site root

		$eePathParts = pathinfo($eeFilePath);
		$eeSiteRoot = $this->eeSFL_GetRootPath();

		$file_check = eeSFL_FileSystem('exists', array('file' => $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFilePath));
		if( $file_check['data'] ) {

			if( !is_array($eeFileArray) ) {
				$eeFileArray = $this->eeFileTemplate[0]; // Get the file array template
			}

			$eeFileArray['FilePath'] = $eeFilePath; // Path to file, relative to the list root

			if(isset($eePathParts['extension'])) {
				$eeExt = strtolower($eePathParts['extension']);
			} else {
				$eeExt = '';
			}
			$eeFileArray['FileExt'] = $eeExt; // The file extension

			if(function_exists('mime_content_type')) {
				$eeFileArray['FileMIME'] = mime_content_type($eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFilePath); // MIME Type
			} else {
				$eeFileArray['FileMIME'] = 'no/mime';
			}

			$file_size_result = eeSFL_FileSystem('filesize', array('file' => $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFilePath));
			$eeFileArray['FileSize'] = $file_size_result['success'] ? $file_size_result['data'] : 0;

			if(empty($eeFileArray['FileDateAdded'])) {
				$eeFileArray['FileDateAdded'] = wp_date("Y-m-d H:i:s");
			}

			$file_time_result = eeSFL_FileSystem('filemtime', array('file' => $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFilePath));
			$eeFileArray['FileDateChanged'] = wp_date("Y-m-d H:i:s", $file_time_result['success'] ? $file_time_result['data'] : time());

			if( strlen($eeFileArray['FilePath']) ) { // 02/21 - If FilePath is empty, sort doesn't work? But why would that be empty.
				return $eeFileArray;
			}
		}

		return FALSE;
	}


	/**
	 * Universal method to safely update the file array
	 * This function prevents duplicate FilePath entries when adding new items
	 * When $eeNewFileArray is FALSE, simply saves existing array without adding anything
	 * Automatically saves the updated array to the database
	 *
	 * @param array|bool $eeNewFileArray The new file/folder array to be added, or FALSE to just save existing array
	 * @return bool|int Returns FALSE if invalid input, TRUE if just saving, or array key if updated/added
	 */
	public function eeSFL_UpdateMainFileArray($eeNewFileArray = FALSE) {

	// Ensure we have a valid list ID
	if (empty($this->eeListID)) {
		return false;
	}

	// Initialize file array if needed (only if not set, not if empty)
	if (!isset($this->eeAllFiles)) {
		$this->eeAllFiles = get_option('eeSFL_FileList_' . $this->eeListID, array());
	}	// If $eeNewFileArray is FALSE, just save the existing array and return
	if ($eeNewFileArray === false) {
		update_option('eeSFL_FileList_' . $this->eeListID, $this->eeAllFiles);
		return true;
	}

	// Handle empty arrays (valid for bulk delete operations that clear all files)
	if (is_array($eeNewFileArray) && empty($eeNewFileArray)) {
		$this->eeAllFiles = array(); // Set to empty array
		update_option('eeSFL_FileList_' . $this->eeListID, $this->eeAllFiles);
		return true;
	}

	// Validate input for adding new items (non-empty arrays must have FilePath)
	if (!is_array($eeNewFileArray) || !isset($eeNewFileArray['FilePath'])) {
		return false;
	}		$eeNewFilePath = $eeNewFileArray['FilePath'];

		// Check for existing entry with same FilePath
		$eeExistingKey = false;
		foreach ($this->eeAllFiles as $eeKey => $eeExistingArray) {
			if (isset($eeExistingArray['FilePath']) && $eeExistingArray['FilePath'] === $eeNewFilePath) {
				$eeExistingKey = $eeKey;
				break;
			}
		}

		if ($eeExistingKey !== false) {
			// Duplicate found - update existing entry
			$this->eeAllFiles[$eeExistingKey] = $eeNewFileArray;
			$eeReturnKey = $eeExistingKey;
		} else {
			// No duplicate - safe to add new entry
			$this->eeAllFiles[] = $eeNewFileArray;
			$eeReturnKey = array_key_last($this->eeAllFiles);
		}

		// Save the updated array to database
		update_option('eeSFL_FileList_' . $this->eeListID, $this->eeAllFiles);

		return $eeReturnKey;
	}



    // Scan the real files and create or update array as needed.
    public function eeSFL_UpdateFileListArray() {

	    global $eeSFLU, $eeSFL_Tasks, $eeSFLA;

		$start_memory = memory_get_usage();
		$peak_memory = memory_get_peak_usage();
		eeSFL_Debug_Log("Re-Indexing the File List - Memory: " . round($start_memory/1024/1024, 2) . "MB (peak: " . round($peak_memory/1024/1024, 2) . "MB)", 'ReIndexing');

	    if(empty($this->eeListSettings)) {
		    $this->eeListSettings = get_option('eeSFL_Settings_' . $this->eeListID);
	    }

	    // Double-check the Disk Directory
	    if( !eeSFL_FileListDirCheck($this->eeListSettings['FileListDir']) ) { return FALSE; }

		// Check where ZIPs to be downloaded are kept temporarily (only if temp dir is in use)
		if(!empty($this->eeTempDirectory) && !is_dir($this->eeTempDirectory)) {
			wp_mkdir_p($this->eeTempDirectory);
		}

	    // Look for files in this directory and delete the ones older than 1 hour
	    $dir_list_result = !empty($this->eeTempDirectory) ? eeSFL_FileSystem('dirlist', array('path' => $this->eeTempDirectory)) : array('success' => false, 'data' => array());
	    $eeTempFiles = $dir_list_result['success'] && is_array($dir_list_result['data']) ? array_keys($dir_list_result['data']) : array();
	    if(count($eeTempFiles)) {
		    foreach( $eeTempFiles as $eeKey => $eeTempFile){
			    if(strpos($eeTempFile, '.') !== 0) { // Nothing up or hidden
				    if($eeTempFile != 'index.html') { // Don't delete this
					    $eeTempFile = $this->eeTempDirectory . '/' . $eeTempFile; // Make full path
					    $file_time_result = eeSFL_FileSystem('filemtime', array('file' => $eeTempFile));
					    if( $file_time_result['success'] && time() - $file_time_result['data'] > 3600 ) { // file older than 1 hour
							if( !eeSFL_FileSystem('is_dir', array('path' => $eeTempFile))['data'] ) {
								eeSFL_FileSystem('delete', array('file' => $eeTempFile));
							}
					    }
				    }
			    }
			}
		}

	    // Get the File List Array
	    $this->eeAllFiles = get_option('eeSFL_FileList_' . $this->eeListID);
	    if(!is_array($this->eeAllFiles)) { $this->eeAllFiles = array(); }

		// The files in the list folder (no subfolders)
		$eeSFL_FileScanArray = array();
		$eeSiteRoot = $this->eeSFL_GetRootPath();
		$dir_result = eeSFL_FileSystem('dirlist', array('path' => $eeSiteRoot . $this->eeListSettings['FileListDir'], 'include_hidden' => false, 'recursive' => false));
		if($dir_result['success'] && is_array($dir_result['data'])) {
			foreach($dir_result['data'] as $eeEntryName => $eeEntryInfo) {
				if(isset($eeEntryInfo['type']) && $eeEntryInfo['type'] === 'f' && $eeEntryName !== 'index.html') {
					$eeSFL_FileScanArray[] = $eeEntryName;
				}
			}
		}
		eeSFL_Debug_Log("Directory scan found " . count($eeSFL_FileScanArray) . " file(s)", 'ReIndexing');

		// No List in the DB, Creating New...
	    if( !count($this->eeAllFiles) ) {

			eeSFL_Debug_Log("No List Found! Creating from scratch...", 'General');

			if(count($eeSFL_FileScanArray)) {

				foreach( $eeSFL_FileScanArray as $eeKey => $eeFilePath) {

					// Add the new item
					$eeNewArray = $this->eeSFL_BuildFileArray($eeFilePath); // Path relative to FileListDir

					if( isset($eeNewArray['FilePath']) ) {

						if( isset($this->eeSanitizedFiles[$eeFilePath]) && $this->eeListSettings['PreserveName'] == 'YES' ) {
							$eeNewArray['FileNiceName'] = basename($this->eeSanitizedFiles[$eeFilePath]);
						}

						$this->eeAllFiles[] = $eeNewArray;
					}
				}
			}

		} else { // Update file info

			// Check to be sure each file is there...
			foreach( $this->eeAllFiles as $eeKey => $eeFileSet) {

				if( isset($eeFileSet['FilePath']) ) {

					// Build full path
					$eeSiteRoot = $this->eeSFL_GetRootPath();
					$eeFile = $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFileSet['FilePath'];

					if( eeSFL_FileSystem('is_file', array('file' => $eeFile))['data'] ) { // Update file size

						// Update file size
						$file_size_result = eeSFL_FileSystem('filesize', array('file' => $eeFile));
						$this->eeAllFiles[$eeKey]['FileSize'] = $file_size_result['success'] ? $file_size_result['data'] : 0;

						// LEGACY as of 6.0 - Remove this down the road
						if(isset($this->eeAllFiles[$eeKey]['ItemCount'])) { unset($this->eeAllFiles[$eeKey]['ItemCount']); }

					} else { // Get rid of it (also folders left by the old Pro version)

						eeSFL_Debug_Log("Removing: " . $eeFile, 'General');

						unset($this->eeAllFiles[$eeKey]);

						// Custom Hook
						array_unshift($eeFileSet, 'File Not Found');
						do_action('eeSFL_Hook_Removed', $eeFileSet);

						continue;
					}

					// MIME Type
					if(!isset($this->eeAllFiles[$eeKey]['FileMIME'])) {
						if(function_exists('mime_content_type')) {
							$this->eeAllFiles[$eeKey]['FileMIME'] = mime_content_type($eeFile); // MIME Type
						} else {
							$this->eeAllFiles[$eeKey]['FileMIME'] = 'no/mime';
						}
					}

					// Update modification date
					$file_time_result = eeSFL_FileSystem('filemtime', array('file' => $eeFile));
					$this->eeAllFiles[$eeKey]['FileDateChanged'] = wp_date("Y-m-d H:i:s", $file_time_result['success'] ? $file_time_result['data'] : time());

					// Merge-in Default File Attributes
					$this->eeAllFiles[$eeKey] = array_merge($this->eeFileTemplate[0], $this->eeAllFiles[$eeKey]);

				} else {
					unset($this->eeAllFiles[$eeKey]); // If no FilePath, get rid of it.
				}
			}

			if(count($eeSFL_FileScanArray)) {

				// Check if any new files have been added
				foreach( $eeSFL_FileScanArray as $eeKey => $eeFile ) {

					$eeFound = FALSE;

					// Look for this file in our array
					foreach( $this->eeAllFiles as $eeKey2 => $eeFileArray ) {

						if($eeFile == $eeFileArray['FilePath']) { $eeFound = TRUE; break; } // Found this file, on to the next.
					}

					if($eeFound === FALSE) { // New Item Found

						eeSFL_Debug_Log("New Item Found: " . $eeFile, 'General');

						// Build a new file array
						$eeNewArray = $this->eeSFL_BuildFileArray($eeFile); // Path relative to FileListDir

						if( isset($eeNewArray['FilePath']) ) {

							if( isset($this->eeSanitizedFiles[$eeFile]) && $this->eeListSettings['PreserveName'] == 'YES' ) {
								$eeNewArray['FileNiceName'] = basename($this->eeSanitizedFiles[$eeFile]);
							}

							$this->eeAllFiles[] = $eeNewArray;

							// Custom Hook
							array_unshift($eeNewArray, 'New File Found');
							do_action('eeSFL_Hook_Added', $eeNewArray);
						}

					}
				}
			}
		}


		// Finish Up
		if(count($this->eeAllFiles)) {

			eeSFL_Debug_Log("Finishing Up ...", 'General');

			// Sort - Passing a reference to the file array
		    $this->eeSFL_SortFiles($this->eeListSettings['SortBy'], $this->eeListSettings['SortOrder']);

		    // Remove any duplicates
			$this->eeAllFiles = array_map("unserialize", array_unique(array_map("serialize", $this->eeAllFiles)));

			// Remove empty array keys to reduce array size
		    foreach( $this->eeAllFiles as $eeFileID => $eeArray) {

		    	foreach( $eeArray as $eeName => $eeValue) {

		    		if( empty($eeValue) AND $eeValue !== 0 ) {
			    		unset( $this->eeAllFiles[$eeFileID][$eeName] );
		    		}
		    	}
		    }

		    // Check Thumbnails...
		    $eeTasks = get_option('eeSFL_Tasks') ?: array();

		    // Ensure the task array exists for this list ID
		    if (!isset($eeTasks[$this->eeListID])) {
		        $eeTasks[$this->eeListID] = array();
		    }

		    // Check if GenerateThumbs is set, default to 'NO' if not
		    $generateThumbs = isset($eeTasks[$this->eeListID]['GenerateThumbs']) ? $eeTasks[$this->eeListID]['GenerateThumbs'] : 'NO';

		    if( $generateThumbs != 'YES' AND $this->eeListSettings['ShowFileThumb'] == 'YES' ) { // Don't do thumbnails if that Cron is running

			    eeSFL_Debug_Log("Checking Thumbnails ...", 'General');

			    eeSFL_CheckSupported();

				// Check for and create thumbnail if needed...
				if( $this->eeListSettings['GeneratePDFThumbs'] == 'YES' OR $this->eeListSettings['GenerateImgThumbs'] == 'YES' OR $this->eeListSettings['GenerateVideoThumbs'] == 'YES' ) {

					// Check if Background setting exists, default to 'NO' if not set
					$backgroundMode = isset($eeSFL_Tasks[$this->eeListID]['Background']) ? $eeSFL_Tasks[$this->eeListID]['Background'] : 'NO';

					if($backgroundMode == 'YES') {

						eeSFL_Debug_Log("Setting Thumbnail Check Cron Job ...", 'General');

						$eeSFL_Tasks[$this->eeListID]['GenerateThumbs'] = 'YES';

					} else {

						// Check all thumbs now
						foreach( $this->eeAllFiles as $eeKey => $eeFile ) {

							if(is_string($eeFile['FilePath'])) {
								$this->eeSFL_CheckThumbnail($eeFile['FilePath'], $this->eeListSettings);
							}
						}

						$eeSFL_Tasks[$this->eeListID]['GenerateThumbs'] = 'NO';
					}

					update_option('eeSFL_Tasks', $eeSFL_Tasks);

				} else {
			    	eeSFL_Debug_Log("Skipped Thumbnail Checks", 'General');
		    	}

		    } else {
			    eeSFL_Debug_Log("Skipped Thumbnail Checks", 'General');
		    }

		    // Check for Environment Changes
		    $eeActual = $eeSFLU->eeSFL_ActualUploadMax();
			if( $this->eeListSettings['UploadMaxFileSize'] > $eeActual ) {
				$this->eeListSettings['UploadMaxFileSize'] = $eeActual;
				update_option('eeSFL_Settings_' . $this->eeListID, $this->eeListSettings); // Set to Actual Max
			}

			$end_memory = memory_get_usage();
			$memory_used = $end_memory - $start_memory;
			$peak_memory_final = memory_get_peak_usage();

			if($memory_used > 5242880) { // Log if re-indexing uses more than 5MB
				eeSFL_Debug_Log("Memory spike detected: Re-indexing used " . round($memory_used/1024/1024, 2) . "MB additional memory", 'Performance', $this->eeListID);
			}

			eeSFL_Debug_Log("Re-Index Completed - Final memory: " . round($end_memory/1024/1024, 2) . "MB (peak: " . round($peak_memory_final/1024/1024, 2) . "MB)", 'ReIndexing');

		    if($this->eeListSettings['UseCacheCron'] == 'NO') {

			    $eeExpire = 0;
			    if($this->eeListSettings['UseCache'] == 'HOUR') {
				    $eeExpire = 3600;
			    } elseif($this->eeListSettings['UseCache'] == 'DAY') {
				    $eeExpire = 86400;
				}

				if($eeExpire) {
					set_transient('eeSFL_Scan_' . $this->eeListID, 'Good', $eeExpire);
				}
		    }

			// Update the DB
		    $this->eeSFL_UpdateMainFileArray(false);

		    if($eeSFLA) { eeSFLA_FileViewerCheck(); }

			// Add Custom Hook
			$eeMessages[] = 'Disk Scan Completed';
			$eeMessages[] = $this->eeAllFiles;
			do_action('eeSFL_Hook_Scanned', $eeMessages);

		    return TRUE;

		} else {

			$this->eeAllFiles = array(); // No files found

			return FALSE;
		}
    }




	// Move, Rename or Delete a thumbnail - Expects path relative to FileListDir
	public function eeSFL_UpdateThumbnail($eeFileFrom, $eeFileTo) {

		$eePathPartsFrom = pathinfo($eeFileFrom);

		if(isset($eePathPartsFrom['extension'])) { // Files only

			if($eePathPartsFrom['extension'] == 'pdf'
				OR in_array($eePathPartsFrom['extension'], $this->eeDynamicImageThumbFormats)
					OR in_array($eePathPartsFrom['extension'], $this->eeDynamicVideoThumbFormats) ) {

				// All thumbs are JPGs
				if($eePathPartsFrom['extension'] != 'jpg') {
					$eeFileFrom = str_replace('.' . $eePathPartsFrom['extension'], '.jpg', $eeFileFrom);
					$eeFileTo = str_replace('.' . $eePathPartsFrom['extension'], '.jpg', $eeFileTo);
				}

				$eeThumbFrom = $this->eeSFL_GetRootPath() . $this->eeListSettings['FileListDir'];

				if($eePathPartsFrom['dirname'] != '.') { $eeThumbFrom .= $eePathPartsFrom['dirname']; }

				$eeThumbFrom .= '/_eeSFL_Thumbnails/thumb_' . basename($eeFileFrom);

				if( eeSFL_FileSystem('is_file', array('file' => $eeThumbFrom))['data'] ) {

					if(!$eeFileTo) { // Delete the thumb

						$eeDeleteResult = eeSFL_FileSystem('delete', array('file' => $eeThumbFrom));
						if($eeDeleteResult['success']) {

							eeSFL_Debug_Log("Deleted Thumbnail For: " . basename($eeFileFrom), 'General');

							return;
						}

					} else { // Move / Rename

						$eePathPartsTo = pathinfo($eeFileTo);

						$eeThumbTo = $this->eeSFL_GetRootPath() . $this->eeListSettings['FileListDir'] . $eePathPartsTo['dirname'] . '/_eeSFL_Thumbnails';

						if(!eeSFL_FileSystem('is_dir', array('path' => $eeThumbTo))['data']) { eeSFL_FileSystem('mkdir', array('path' => $eeThumbTo)); }

						$eeThumbTo .= '/thumb_' . basename($eeFileTo);

						if(eeSFL_FileSystem('move', array('from' => $eeThumbFrom, 'to' => $eeThumbTo))['success']) { // Do nothing on failure

							eeSFL_Debug_Log("Thumbnail Updated For: " . basename($eeFileFrom), 'General');

							return;
						}
					}
				}
			}
		}
	}




	// Check Thumbnail and Create if Needed
	public function eeSFL_CheckThumbnail($eeFilePath) { // Expects FilePath relative to FileListDir & the List's Settings Array

		$eePathParts = pathinfo($eeFilePath);
		$eeFileNameOnly = $eePathParts['filename'];
		if(isset($eePathParts['extension'])) {
			$eeFileExt = $eePathParts['extension'];
		} else {
			$eeFileExt = '';
		}
		// If file is at the root of FileListDir, pathinfo returns dirname='.' which produces './' — normalize to empty string
		$eeRawDirname = $eePathParts['dirname'];
		$eeFileSubPath = ($eeRawDirname === '.' || $eeRawDirname === '') ? '' : rtrim($eeRawDirname, '/') . '/';
		$eeSiteRoot = $this->eeSFL_GetRootPath();
		$eeFileFullPath = $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFilePath;
		$eeThumbsPath = $eeSiteRoot . $this->eeListSettings['FileListDir'] . $eeFileSubPath . '_eeSFL_Thumbnails/';
		$eeThumbFileToCheck = 'thumb_' . $eeFileNameOnly . '.jpg';

		// Check for the _eeSFL_Thumbnails directory
		if( !eeSFL_FileSystem('is_dir', array('path' => $eeThumbsPath))['data'] ) {
			if( !eeSFL_FileSystem('mkdir', array('path' => $eeThumbsPath))['success'] ) {
				eeSFL_Debug_Log('!!!! Cannot create the _eeSFL_Thumbnails directory: ' . $eeThumbsPath, 'General', $this->eeListID);
				return FALSE;
			}
		}

		// Is there already a thumb?
		if(eeSFL_FileSystem('is_file', array('file' => $eeThumbsPath . $eeThumbFileToCheck))['data']) {
			// eeSFL_Debug_Log("Found: thumb_" . $eeFileNameOnly . '.jpg', 'General');
			return TRUE; // Checked Okay
		}

		// Image Files
		if(in_array($eeFileExt, $this->eeDynamicImageThumbFormats) AND $this->eeListSettings['GenerateImgThumbs'] == 'YES') { // Just for known image files...

			// Make sure it's really an image
			if( getimagesize($eeFileFullPath) ) {

				if(strpos($eeFileFullPath, '.')) {

					// Else We Generate ...
					eeSFL_Debug_Log("Missing: thumb_" . $eeFileNameOnly . '.jpg', 'General');

					if( $this->eeSFL_CreateThumbnailImage($eeFileFullPath) ) {
						return TRUE;
					}
				}

			} else { // Not an image, be gone with you!

				eeSFL_FileSystem('delete', array('file' => $eeFileFullPath));
				$this->eeUserMessages['errors'][] = '!!!! ' . __('Corrupt Image File Deleted', 'simple-file-list') . ': ' . basename($eeFileFullPath);
				return TRUE;
			}
		}


		// Video Files
		if(in_array($eeFileExt, $this->eeDynamicVideoThumbFormats) AND $this->eeListSettings['GenerateVideoThumbs'] == 'YES' AND isset($this->eeEnvironment['ffMpeg']) ) {

			if($this->eeSFL_CreateVideoThumbnail($eeFileFullPath)) {
				return TRUE;
			}
		}


		// PDF Files
		if($eeFileExt == 'pdf' AND $this->eeListSettings['GeneratePDFThumbs'] == 'YES' AND isset($this->eeEnvironment['GhostScript']) ) {

			if($this->eeSFL_CreatePDFThumbnail($eeFileFullPath)) {
				return TRUE;
			}
		}
	}




	// Create Image Thumbnail
	private function eeSFL_CreateThumbnailImage($eeInputFileCompletePath) { // Expects Full Path

		if( !eeSFL_FileSystem('is_file', array('file' => $eeInputFileCompletePath))['data'] ) {
			eeSFL_Debug_Log(" !!!! Source File Not Found", 'General');
			return FALSE;
		}

		eeSFL_Debug_Log("Creating Thumbnail Image for " . basename($eeInputFileCompletePath), 'Thumbnails');

		// All The Path Parts
		$eePathParts = pathinfo($eeInputFileCompletePath);
		$eeFileNameOnly = $eePathParts['filename'];
		$eeFileExt = $eePathParts['extension'];

		// Sub-Directory Path
		$eeCompleteDir = $eePathParts['dirname'] . '/';

		// The Destination
		// PDF and Video temp files are created in the _eeSFL_Thumbnails dir - Strip that part of the path so it's not doubled.
		if(!strpos($eeCompleteDir, '_eeSFL_Thumbnails/')) {
			$eeThumbsPath = $eeCompleteDir . '_eeSFL_Thumbnails/';
		} else {
			$eeThumbsPath = $eeCompleteDir;
		}



		// The Source
		$eeImageMemoryNeeded = 0;
		$eeImageSizeLimit = 0;
		$file_size_result = eeSFL_FileSystem('filesize', array('file' => $eeInputFileCompletePath));
		$eeFileSize = $file_size_result['success'] ? $file_size_result['data'] : 0;
		$eeSizeCheck = getimagesize($eeInputFileCompletePath);
        $eeSizeCheck['memory-limit'] = preg_replace("/[^0-9]/", "", ini_get('memory_limit') ) * 1048576;
	    $eeSizeCheck['memory-usage'] = memory_get_usage();

		if(isset($eeSizeCheck['bits'])) {
	        $eeImageMemoryNeeded = ($eeSizeCheck[0] * $eeSizeCheck[1] * $eeSizeCheck['bits']) / 8;
	        $eeImageSizeLimit = ( $eeSizeCheck['memory-limit'] - $eeSizeCheck['memory-usage'] ) * .2;
        }

        if($eeImageMemoryNeeded > $eeImageSizeLimit) { // It's too big for Wordpress

			if( strpos($eeFileNameOnly, 'temp_') === 0 ) { // These are PDF thumbs
				$eeDefaultThumbIcon = $this->eeEnvironment['pluginDir'] . 'images/thumbnails/default_pdf.jpg';
			} else {
				$eeDefaultThumbIcon = $this->eeEnvironment['pluginDir'] . 'images/thumbnails/default_image.jpg';
			}

			$eeFileNameOnly = str_replace('temp_', '', $eeFileNameOnly); // Strip the temp term if needed
			if(strlen($eeFileNameOnly) > 240) { // Cap stem so thumb filename fits within 255-char filesystem limit
				$eeFileNameOnly = substr($eeFileNameOnly, 0, 240);
			}
			$eeNewThumb = $eeThumbsPath . 'thumb_' . $eeFileNameOnly . '.jpg';

			eeSFL_FileSystem('copy', array('from' => $eeDefaultThumbIcon, 'to' => $eeNewThumb)); // Use our default image file icon

			eeSFL_Debug_Log("Image was too large. Default thumbnail will be used for: " . basename($eeInputFileCompletePath), 'General');

			return TRUE;

		} else { // Create thumbnail

			// Thank Wordpress for this easyness.
			$eeFileImage = wp_get_image_editor($eeInputFileCompletePath); // Try to open the file

	        if (!is_wp_error($eeFileImage)) { // Image File Opened

	            $eeFileImage->resize($this->eeFileThumbSize, $this->eeFileThumbSize, TRUE); // Create the thumbnail

	            $eeFileNameOnly = str_replace('temp_', '', $eeFileNameOnly); // Strip the temp term

			// Ensure the thumbnail filename component doesn't exceed the filesystem 255-byte limit.
			// 'thumb_' (6) + name + '.jpg' (4) = 10 chars overhead; cap stem at 240 chars to stay safe.
			if(strlen($eeFileNameOnly) > 240) {
				$eeFileNameOnly = substr($eeFileNameOnly, 0, 240);
			}

	            $eeFileImage->save($eeThumbsPath . 'thumb_' . $eeFileNameOnly . '.jpg'); // Save the file

			eeSFL_Debug_Log("Thumbnail Created.", 'Thumbnails');

	            return TRUE;

	        } else { // Cannot open

		        eeSFL_Debug_Log("Bad Image File Deleted: " . basename($eeInputFileCompletePath), 'General');

		        return FALSE;
	        }
		}
	}




	private function eeSFL_CreateVideoThumbnail($eeFileFullPath) { // Expects Full Path

		// SECURITY: only absolute paths reach the shell, so a name can never be read as a command-line option
		if( !path_is_absolute($eeFileFullPath) ) { return FALSE; }

		// All The Path Parts
		$eePathParts = pathinfo($eeFileFullPath);
		$eeFileNameOnly = $eePathParts['filename'];
		$eeFileExt = $eePathParts['extension'];
		$eeCompleteDir = $eePathParts['dirname'] . '/';
		$eeThumbsPath = $eeCompleteDir . '_eeSFL_Thumbnails/';

		if(eeSFL_FileSystem('is_dir', array('path' => $eeThumbsPath))['data']) {

			// Create a temporary file
			$eeScreenshot = $eeThumbsPath . 'temp_' . $eeFileNameOnly . '.png';

			// Create a full-sized image at the one-second mark
			// SECURITY: escape paths — files placed in the list directory outside the uploader keep their raw names.
			$eeCommand = 'ffmpeg -i ' . escapeshellarg($eeFileFullPath) . ' -ss 00:00:01.000 -vframes 1 ' . escapeshellarg($eeScreenshot);

			shell_exec($eeCommand);

			if(eeSFL_FileSystem('is_file', array('file' => $eeScreenshot))['data']) { // Resize down to $this->eeFileThumbSize

				if( $this->eeSFL_CreateThumbnailImage($eeScreenshot) ) {
					eeSFL_FileSystem('delete', array('file' => $eeScreenshot)); // Delete the screeshot file
					return TRUE;
				} else {
					eeSFL_FileSystem('delete', array('file' => $eeScreenshot)); // Delete the screeshot file anyway
					return FALSE;
				}

			} else {

				// FFmpeg FAILED !!!
				eeSFL_Debug_Log("FFmpeg could not create a screenshot for " . basename($eeScreenshot), 'General');
				return FALSE;
			}
		}

		eeSFL_Debug_Log('!!!! There is no _eeSFL_Thumbnails directory: ' . $eeThumbsPath, 'General', $this->eeListID);

		return FALSE;
	}




	// Generate PDF Thumbnails
	private function eeSFL_CreatePDFThumbnail($eeFileFullPath) { // Expects Full Path

		// SECURITY: only absolute paths reach the shell, so a name can never be read as a command-line option
		if( !path_is_absolute($eeFileFullPath) ) { return FALSE; }

		eeSFL_Debug_Log("Generating PDF Thumbnail...", 'General');

		$eePathParts = pathinfo($eeFileFullPath);
		$eeFileNameOnly = $eePathParts['filename'];
		$eeFileExt = $eePathParts['extension'];
		$eeCompleteDir = $eePathParts['dirname'] . '/';
		$eeThumbsPath = $eeCompleteDir . '_eeSFL_Thumbnails/';
		$eeTempFile = 'temp_' . $eeFileNameOnly . '.jpg'; // The converted pdf file - A temporary file
		$eeTempFileFullPath = $eeThumbsPath . $eeTempFile;

		if($eeFileExt != 'pdf') { return FALSE; }

		if( isset($this->eeEnvironment['GhostScript']) ) {

			// eeSFL_Debug_Log("GhostScript is Installed", 'General');

			// Check Size and set image resolution higher for smaller sizes.
			$file_size_result = eeSFL_FileSystem('filesize', array('file' => $eeFileFullPath));
			$eeFileSize = $file_size_result['success'] ? $file_size_result['data'] : 0;
			if($eeFileSize >= 8388608) { // Greater than 8 MB
				$eeResolution = '72';
				$eeBits = '2';
				$eeQuality = '60';
				$eeQFactor = '.25';
			} elseif($eeFileSize < 8388608 AND $eeFileSize > 2097152) { // Less than 8MB but larger than 2 MB
				$eeResolution = '150';
				$eeBits = '2';
				$eeQuality = '75';
				$eeQFactor = '.5';
			} else { // Less than 2 MB
				$eeResolution = '300';
				$eeBits = '4';
				$eeQuality = '90';
				$eeQFactor = '.75';
			}

			// GhostScript Operations
			$temp_file_check = eeSFL_FileSystem('exists', array('file' => $eeTempFileFullPath));
			if( !$temp_file_check['data'] ) { // Might be there already.

				// Check PDF Validity
				// SECURITY: escape paths — files placed in the list directory outside the uploader keep their raw names.
				$eeCommand = 'gs -dSAFER -dNOPAUSE -dBATCH -sDEVICE=nullpage ' . escapeshellarg($eeFileFullPath);

				// Run the Command. Drum roll please
				exec( $eeCommand, $eeCommandOutput, $eeReturnVal );

				if($eeReturnVal === 0) { // Zero == No Errors

					// The command. AVOID LINE BREAKS
					// $eeCommand = 'gs -dNOPAUSE -sDEVICE=png16m -dGraphicsAlphaBits=' . $eeBits . ' -dTextAlphaBits=' . $eeBits . ' -r' . $eeResolution . ' -dFirstPage=1 -dLastPage=1 -sOutputFile=' . $eeTempFileFullPath . ' ' . $eeFileFullPath;
					$eeCommand = 'gs -dSAFER -dNOPAUSE -dBATCH -sDEVICE=jpeg -dJPEGQ=' . $eeQuality . ' -dQFactor=' . $eeQFactor . ' -r' . $eeResolution . ' -dFirstPage=1 -dLastPage=1 -sOutputFile=' . escapeshellarg($eeTempFileFullPath) . ' ' . escapeshellarg($eeFileFullPath);

					// Run the Command. Drum roll please
					exec( $eeCommand, $eeCommandOutput, $eeReturnVal );

				} else {

					// GhostScript logging removed - was debug only

					eeSFL_Debug_Log('FILE NOT READABLE: ' . basename($eeFileFullPath), 'ERROR', $this->eeListID);
					eeSFL_Debug_Log("!!!! PDF NOT READABLE: " . basename($eeFileFullPath), 'ERROR', $this->eeListID);
					return FALSE;
				}
			}

			// Confirm the file is there
			$temp_file_check = eeSFL_FileSystem('exists', array('file' => $eeTempFileFullPath));
			if($temp_file_check['data']) {

				if($this->eeSFL_CreateThumbnailImage($eeTempFileFullPath)) {

					eeSFL_Debug_Log("Created the PDF Thumbnail for " . basename($eeFileFullPath), 'General');

					eeSFL_FileSystem('delete', array('file' => $eeTempFileFullPath)); // Delete the temp PNG file

					return TRUE;

				} else {

					eeSFL_Debug_Log("!!!! FAILED to Create the PDF Thumbnail for " . basename($eeFileFullPath), 'ERROR');

					eeSFL_FileSystem('delete', array('file' => $eeTempFileFullPath));

					return FALSE;
				}

			} elseif(eeSFL_FileSystem('is_file', array('file' => $eeTempFileFullPath))['data']) {

				eeSFL_FileSystem('delete', array('file' => $eeTempFileFullPath)); // Delete the corrupt temp file;

				return FALSE;

			} else {

				eeSFL_Debug_Log("!!!! PDF to PNG FAILED for " . basename($eeFileFullPath), 'General');

				return FALSE;
			}
		}

		return FALSE;
	}




	// Move the sort item to the array key and then sort. Preserve the key (File ID) in a new element
	public function eeSFL_SortFiles($eeSortBy, $eeSortOrder) {

		if(empty($this->eeAllFiles)) { return; }

		$file_count = count($this->eeAllFiles);
		$sort_start_time = microtime(true);
		eeSFL_Debug_Log("Sorting the File Array - {$file_count} files by {$eeSortBy} ({$eeSortOrder})", 'General');

		// Legacy check
		if( !array_key_exists(1, $this->eeAllFiles) ) {
			$this->eeAllFiles = array_values($this->eeAllFiles); // Reset the keys to numbers
		}

		if($eeSortBy == 'Random') {
			return shuffle($this->eeAllFiles);
		} elseif($eeSortBy == 'Size') {
			$eeSort = 'FileSize';
		} elseif($eeSortBy == 'Added') {
			$eeSort = 'FileDateAdded';
		} elseif($eeSortBy == 'Changed') {
			$eeSort = 'FileDateChanged';
		} else {
			$eeSort = 'FilePath'; // Name
		}

		if($eeSortOrder == 'Descending') { $eeOrder = SORT_DESC; }
			else { $eeOrder = SORT_ASC; }

		// exit($eeSort . ' - ' . $eeOrder);

		// Sort
		$eeArray1 = array_column($this->eeAllFiles, $eeSort);

		$eeArray2 = array_column($this->eeAllFiles, 'FileExt');

		// Sort Multi-Dimesional Array Like a Pro
		// All three arrays must be the same length — array_column skips entries where
		// the key is absent (e.g. corrupted DB rows missing FileDateChanged or FileExt),
		// so guard against eeAllFiles being longer than either column array.
		$eeFileCount = count($this->eeAllFiles);
		if( count($eeArray1) === $eeFileCount && count($eeArray2) === $eeFileCount ) {
			array_multisort($eeArray1, $eeOrder, SORT_NATURAL|SORT_FLAG_CASE, $eeArray2, SORT_ASC, $this->eeAllFiles);
		}

		$sort_execution_time = round((microtime(true) - $sort_start_time) * 1000, 2);

		if($sort_execution_time > 500) { // Log if sorting takes more than 500ms
			eeSFL_Debug_Log("SLOW OPERATION: File sorting took {$sort_execution_time}ms for {$file_count} files", 'PERFORMANCE', $this->eeListID);
		}

		eeSFL_Debug_Log("Files Sorted: " . $eeSortBy . ' (' . $eeSortOrder . ') in ' . $sort_execution_time . 'ms', 'General');
	}










	// Email -------

	public $eeNotifyMessageDefault = 'Greetings,' . PHP_EOL . PHP_EOL .
    	'You should know that a file has been uploaded to your website.' . PHP_EOL . PHP_EOL .

    		'[file-list]' . PHP_EOL . PHP_EOL .

    		'File List: [web-page]' . PHP_EOL . PHP_EOL;


	// Send the notification email
	public function eeSFL_NotificationEmail($eeSFL_UploadJob) {

		global $eeSFLA;

		eeSFL_Debug_Log("Class Method Called: eeSFL_NotificationEmail()", 'General');

		$eeAdminEmail = $this->eeListSettings['NotifyTo'];

		if($eeSFL_UploadJob) {

			// Build the Message Body
			$eeSFL_Body = $this->eeListSettings['NotifyMessage']; // Get the template


			$eeSFL_Body = str_replace('[file-list]', $eeSFL_UploadJob, $eeSFL_Body); // Add files
			$eeSFL_Body = str_replace('[web-page]', get_permalink(), $eeSFL_Body); // Add location

			// Get Form Input?
			if(isset($_POST['eeSFL_Email']) && !empty($_POST['eeSFL_Email'])) {

				// Verify nonce for upload form submission (Missing nonce security)
				if (!wp_verify_nonce(isset($_POST['ee-simple-file-list-upload-form-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-upload-form-nonce'])) : '', 'ee-simple-file-list-upload-form') && !is_admin()) {
					// Skip form input processing if nonce verification fails
					eeSFL_Debug_Log("Email form input skipped: Invalid nonce", 'General');
				} else {

					$eeSFL_Body .= PHP_EOL . PHP_EOL . __('Uploader Information', 'simple-file-list') . PHP_EOL;

					$eeSFL_Name = esc_textarea(substr(sanitize_text_field(isset($_POST['eeSFL_Name']) ? wp_unslash($_POST['eeSFL_Name']) : ''), 0, 64));
					$eeSFL_Name = wp_strip_all_tags($eeSFL_Name);
					if($eeSFL_Name) {
						$eeSFL_Body .= __('Uploaded By', 'simple-file-list') . ': ' . ucwords($eeSFL_Name) . " - ";
					}

					$eeSFL_Email = filter_var(sanitize_email(isset($_POST['eeSFL_Email']) ? wp_unslash($_POST['eeSFL_Email']) : ''), FILTER_VALIDATE_EMAIL);
					$eeSFL_Body .= strtolower($eeSFL_Email) . PHP_EOL;
					$eeSFL_ReplyTo = $eeSFL_Name . ' <' . $eeSFL_Email . '>';

					$eeSFL_Comments = esc_textarea(substr(sanitize_text_field(isset($_POST['eeSFL_Comments']) ? wp_unslash($_POST['eeSFL_Comments']) : ''), 0, 5012));
					$eeSFL_Comments = wp_strip_all_tags($eeSFL_Comments);
					if($eeSFL_Comments) {
						$eeSFL_Body .= PHP_EOL . $eeSFL_Comments . PHP_EOL . PHP_EOL;
					}
				}
			}

			if($this->eeListSettings['NotifyFrom']) {
				$eeSFL_NotifyFrom = $this->eeListSettings['NotifyFrom'];
			} else {
				$eeSFL_NotifyFrom = get_option('admin_email');
			}

			if($this->eeListSettings['NotifyFromName']) {
				$eeSFL_AdminName = $this->eeListSettings['NotifyFromName'];
			} else {
				$eeSFL_AdminName = $this->eePluginName;
			}

			if($this->eeListSettings['NotifySubject']) {
				$eeSFL_Subject = stripslashes( $this->eeListSettings['NotifySubject'] );
			} else {
				$eeSFL_Subject = __('File Upload Notice', 'simple-file-list');
			}

			$eeSFL_Headers = "From: " . stripslashes( $this->eeListSettings['NotifyFromName'] ) . " <$eeSFL_NotifyFrom>" . PHP_EOL .
				"Return-Path: $eeSFL_NotifyFrom" . PHP_EOL . "Reply-To: $eeSFL_NotifyFrom";

			$eeSFL_HeadersCC = '';

			if($this->eeListSettings['NotifyCc']) {
				$eeSFL_HeadersCC .= PHP_EOL . "CC:" . $this->eeListSettings['NotifyCc'];
			}

			if($this->eeListSettings['NotifyBcc']) {
				$eeSFL_HeadersCC .= PHP_EOL . "BCC:" . $this->eeListSettings['NotifyBcc'];
				if($eeSFLA) {
					if($eeSFLA->eeSFLA_Settings['BCC'] == 'YES' AND $this->eeListID > 1) { // Append if needed
						$eeSFL_HeadersCC .= ',' . $eeAdminEmail;
					}
				}
			}

			if($eeSFLA) {
				if($eeSFLA->eeSFLA_Settings['BCC'] == 'YES') {
					$eeMainListSettings = get_option('eeSFL_Settings_1');
					if($this->eeListSettings['NotifyBcc']) {
						$eeSFL_HeadersCC .= ',' . $eeMainListSettings['NotifyTo']; // Add to existing BCC
					} else {
						$eeSFL_HeadersCC .= PHP_EOL . "BCC:" . $eeMainListSettings['NotifyTo']; // Set as BCC
					}
				}
			}

			// Must be an array
			if(is_array($this->eeListSettings['NotifyTo'])) { $eeTo = $this->eeListSettings['NotifyTo']; }
				else { $eeTo = array($this->eeListSettings['NotifyTo']); }


			if( !strpos($eeTo[0], '@') ) { // Not an actual email address

				if($eeSFLA) {

					$eeArray = array();

					if( $eeTo[0] == 'All' ) { // Send to all list users

						$eeUsers = $eeSFLA->eeSFLA_GetMinRoleUsers($this->eeListSettings['ListRole'], FALSE, $this->eeListSettings['ListMatchMode']);

						foreach( $eeUsers as $eeKey => $eeUser) { // Build array of addresses

							$eeArray[] = $eeUser['Email'];
						}

						$eeTo = $eeArray;

					} elseif( is_numeric($eeTo[0]) ) {

						foreach( $eeTo as $eeKey => $eeID ){

							$eeUserInfo = get_userdata($eeID);

							$eeArray[] = $eeUserInfo->user_email;
						}

						$eeTo = $eeArray;

					} else {

						$eeTo = array($eeAdminEmail); // Send to Admin
					}
				}
			}

			$eeTo = array_unique($eeTo); // Remove duplicates

			foreach( $eeTo as $eeKey => $eeThisTo) {

				if( strpos($eeThisTo, '@') ) {

					if( wp_mail($eeThisTo, $eeSFL_Subject, $eeSFL_Body, $eeSFL_Headers . $eeSFL_HeadersCC) ) { // SEND IT

						$eeSFL_HeadersCC = '';
						$eeSent = TRUE;

						eeSFL_Debug_Log("Notification Email SENT", 'General');

					} else {

						$this->eeUserMessages['errors'][] = 'Notification Email FAILED';
						$eeSent = FALSE;
					}
				}
			}

			if($eeSent) {
				return 'SUCCESS';
			}
		}
	}





	// Sanitize Email Addresses
	public function eeSFL_SanitizeEmailString($eeAddresses) { // Can be one or more addresses, comma deliniated

		global $eeSFL;

		$eeAddressSanitized = '';

		if(strpos($eeAddresses, ',')) { // Multiple Addresses

			$eeSFL_Addresses = explode(',', $eeAddresses);

			$eeSFL_AddressesString = '';

			foreach($eeSFL_Addresses as $add){

				$add = trim($add);

				if(filter_var(sanitize_email($add), FILTER_VALIDATE_EMAIL)) {

					$eeSFL_AddressesString .= $add . ',';

				} else {
					$this->eeUserMessages['errors'][] = $add . ' - ' . __('This is not a valid email address.', 'simple-file-list');
				}
			}

			$eeAddressSanitized = substr($eeSFL_AddressesString, 0, -1); // Remove last comma


		} elseif(filter_var(sanitize_email($eeAddresses), FILTER_SANITIZE_EMAIL)) { // Only one address

			$add = $eeAddresses;

			if(filter_var(sanitize_email($add), FILTER_VALIDATE_EMAIL)) {

				$eeAddressSanitized = $add;

			} else {

				$this->eeUserMessages['errors'][] = $add . ' - ' . __('This is not a valid email address.', 'simple-file-list');
			}

		} else {

			$eeAddressSanitized = ''; // Anything but a good email gets null.
		}

		return $eeAddressSanitized;
	}






	// Upload Info Form Display
	public function eeSFL_UploadInfoForm() {

		$eeName = '';
		$eeEmail = '';

		$wpUserObj = wp_get_current_user();

		if($wpUserObj) {
			$eeName = $wpUserObj->first_name . ' ' . $wpUserObj->last_name;
			$eeEmail = $wpUserObj->user_email;
		}

		 $eeOutput = '<div id="eeUploadInfoForm">';

			if(!$eeEmail) {

				 $eeOutput .= '<label for="eeSFL_Name">' . __('Name', 'simple-file-list') . ':</label>
					<input type="text" name="eeSFL_Name" value="" id="eeSFL_Name" size="64" maxlength="64" />
						<label for="eeSFL_Email">' . __('Email', 'simple-file-list') . ':</label>
							<input type="text" name="eeSFL_Email" value="" id="eeSFL_Email" size="64" maxlength="128" />';
			}

			 $eeOutput .= '<label for="eeSFL_Comments">' . __('Description', 'simple-file-list') . ':</label>';

			 $eeOutput .= '<textarea placeholder="' . __('Add an optional description', 'simple-file-list') . '" name="eeSFL_Comments" id="eeSFL_Comments" rows="5" cols="64" maxlength="5012"></textarea>';

			if($eeEmail) {  $eeOutput .= '<p>' . __('Submitter:', 'simple-file-list') . ' ' . $eeName . ' (' . $eeEmail . ')</p>'; }

			if($eeEmail) {
				 $eeOutput .= '<input type="hidden" id="eeSFL_Name" name="eeSFL_Name" value="' . $eeName . '" />
					<input type="hidden" id="eeSFL_Email" name="eeSFL_Email" value="' . $eeEmail . '" />';
			}

			 $eeOutput .= '</div>';

		return  $eeOutput;

	}




	// Return the general size of a file in a nice format.
	public function eeSFL_FormatFileSize($eeFileSizeBytes) {

	    $bytes = $eeFileSizeBytes;
	    $kilobyte = 1024;
	    $megabyte = $kilobyte * 1024;
	    $gigabyte = $megabyte * 1024;
	    $terabyte = $gigabyte * 1024;
	    $precision = 2;

	    if (($bytes >= 0) && ($bytes < $kilobyte)) {
	        return $bytes . ' B';

	    } elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
	        return round($bytes / $kilobyte, $precision) . ' KB';

	    } elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
	        return round($bytes / $megabyte, $precision) . ' MB';

	    } elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
	        return round($bytes / $gigabyte, $precision) . ' GB';

	    } elseif ($bytes >= $terabyte) {
	        return round($bytes / $terabyte, $precision) . ' TB';
	    } else {
	        return $bytes . ' B';
	    }
	}



	// Make sure the file name is acceptable
	public function eeSFL_SanitizeFileName($eeSFL_FileName) {

		// Step 1: Strip Unicode bidirectional override/control characters (RTL override spoofing)
		$eeSFL_FileName = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $eeSFL_FileName);

		// Step 2: Apply WordPress sanitization first - handles security, encoding, and most edge cases
		$eeSFL_FileName = sanitize_file_name($eeSFL_FileName);

		// Step 4: Post-process the WordPress result to fix plugin-specific issues
		$eeSFL_PathParts = pathinfo($eeSFL_FileName);
		$eeSFL_FileNameAlone = $eeSFL_PathParts['filename'];
		$eeSFL_Extension = strtolower($eeSFL_PathParts['extension'] ?? ''); // pathinfo() omits 'extension' when filename has no dot

		// Step 5: Fix multiple consecutive hyphens created by WordPress sanitization
		// WordPress often converts problematic characters to hyphens, leading to doubles
		$eeSFL_FileNameAlone = preg_replace('/-+/', '-', $eeSFL_FileNameAlone);

		// Step 6: Handle edge cases where WordPress removes too much
		// If filename became empty or too short, provide a fallback
		if(empty($eeSFL_FileNameAlone) || strlen($eeSFL_FileNameAlone) < 1) {
			$eeSFL_FileNameAlone = 'file-' . time(); // Timestamp-based fallback
		}

		// Step 7: Ensure filename doesn't start or end with hyphens/underscores
		$eeSFL_FileNameAlone = trim($eeSFL_FileNameAlone, '-_');

		// Step 8: Final safety check - if still empty, use fallback
		if(empty($eeSFL_FileNameAlone)) {
			$eeSFL_FileNameAlone = 'sanitized-file-' . time();
		}

		// Step 9: Reassemble the filename
		$eeSFL_FileName = $eeSFL_FileNameAlone . '.' . $eeSFL_Extension;

	    return $eeSFL_FileName;
	}




	// Check if a file already exists, then number it so file will not be over-written.
	public function eeSFL_CheckForDuplicateFile($eeSFL_FilePathAdded) { // Path from site root

		$eeSiteRoot = $this->eeSFL_GetRootPath();
		$eePathInfo = pathinfo($eeSFL_FilePathAdded);
		$eeFileName = $eePathInfo['basename'];
		$eeNameOnly = $eePathInfo['filename'];
		$eeExtension = strtolower($eePathInfo['extension']);
		$eeDir = dirname($eeSFL_FilePathAdded) . '/';
		$eeFolderPath = str_replace($this->eeListSettings['FileListDir'], '', $eeDir);
		$eeCopyLimit = 1000; // File copies limit

		if(empty($this->eeAllFiles)) {
			$this->eeAllFiles = get_option('eeSFL_FileList_' . $this->eeListID) ?: array();
		}

		foreach($this->eeAllFiles as $eeFileArray) { // Loop through file array and look for a match.

			if( $eeFolderPath . $eeFileName == $eeFileArray['FilePath'] ) { // Duplicate found

				eeSFL_Debug_Log("Duplicate Item Found: " . $eeFolderPath . $eeFileName, 'General');

				if( eeSFL_FileSystem('is_file', array('file' => $eeSiteRoot . $eeSFL_FilePathAdded))['data'] ) { // Confirm the file is really there

					for ($i = 1; $i <= $eeCopyLimit; $i++) { // Look for existing copies

						$eeFileName = $eeNameOnly . '_' . $i . '.' . $eeExtension; // Indicate the copy number

						if(!eeSFL_FileSystem('is_file', array('file' => $eeSiteRoot . $eeDir . $eeFileName))['data']) { break; } // We're done.
					}
				}
			}
		}

		return $eeDir . $eeFileName; // Return the full path from site root
	}



	// Detect upward path traversal
	public function eeSFL_DetectUpwardTraversal($eeFilePath) { // Relative to site root

		eeSFL_Debug_Log("Traversal check started for: " . $eeFilePath, 'Security', $this->eeListID);

		// Decode URL-encoded characters
		$eeFilePath = urldecode($eeFilePath);
		eeSFL_Debug_Log("After urldecode: " . $eeFilePath, 'Security', $this->eeListID);

		// Convert all directory separators to '/'
		$eeFilePath = str_replace('\\', '/', $eeFilePath);

		// Normalize the path: replace double or multiple slashes with a single slash
		$eeFilePath = preg_replace('~/+~', '/', $eeFilePath);
		eeSFL_Debug_Log("After normalization: " . $eeFilePath, 'Security', $this->eeListID);

		// Check for '..' after decoding and normalization
		if (strpos($eeFilePath, '..') !== FALSE) {
			$this->eeUserMessages['errors'][] = 'Potential directory traversal detected.';
			eeSFL_Debug_Log("FAILED: Contains '..'", 'Security', $this->eeListID);
		}

		// Construct the full path and resolve to a real path
		$eeSiteRoot = $this->eeSFL_GetRootPath(); // Use verified site root — ABSPATH differs from site root on managed hosts (e.g. Pressable)
		$eeUserPath = str_replace('\\', '/', $eeSiteRoot . dirname($eeFilePath));
		eeSFL_Debug_Log("User path (dirname): " . $eeUserPath, 'Security', $this->eeListID);

		$eeRealPath = realpath($eeUserPath);
		eeSFL_Debug_Log("Real path: " . ($eeRealPath ? $eeRealPath : 'FALSE'), 'Security', $this->eeListID);

		// Ensure paths are valid
		if ($eeRealPath === FALSE || $eeUserPath === FALSE) {
			$this->eeUserMessages['errors'][] = 'Invalid path detected.';
			eeSFL_Debug_Log("FAILED: Invalid path (realpath returned FALSE)", 'Security', $this->eeListID);
		}

		// Convert real path directory separator for consistency
		$eeRealPath = str_replace('\\', '/', $eeRealPath);

		// Check if the real path starts with the intended base directory
		$eeSiteRootNorm = str_replace('\\', '/', $eeSiteRoot);
		eeSFL_Debug_Log("Site root: " . $eeSiteRootNorm, 'Security', $this->eeListID);

		if (strpos($eeRealPath, $eeSiteRootNorm) !== 0) {
			$this->eeUserMessages['errors'][] = 'Potential directory traversal detected.';
			eeSFL_Debug_Log("FAILED: Real path doesn't start with site root", 'Security', $this->eeListID);
		}

		// SECURITY: Also verify the resolved path is within the configured FileListDir,
		// not just within ABSPATH. This prevents symlinks inside the list directory
		// from pointing to other directories under the site root.
		if ( !empty($this->eeListSettings['FileListDir']) ) {
			$eeFileListDirReal = realpath($eeSiteRoot . $this->eeListSettings['FileListDir']);
			if ($eeFileListDirReal !== FALSE) {
				$eeFileListDirReal = str_replace('\\', '/', $eeFileListDirReal);
				// Compare with a trailing slash so a sibling such as "simple-file-list-old/" doesn't pass as inside.
				if (strpos(rtrim($eeRealPath, '/') . '/', rtrim($eeFileListDirReal, '/') . '/') !== 0) {
					$this->eeUserMessages['errors'][] = 'Path is outside the configured file list directory.';
					eeSFL_Debug_Log("FAILED: Real path is outside FileListDir", 'Security', $this->eeListID);
				}
			}
		}

		if (!empty($this->eeUserMessages['errors'])) {
			wp_die('Error 99 - Directory Traversal Check Failure');
		}

		// If all checks passed, no traversal detected
		eeSFL_Debug_Log("Traversal check passed.", 'General');

		return TRUE;
	}




	// Get the current URL
	public function eeSFL_GetThisURL($eeIncludeQuery = TRUE) {

		// Use WordPress built-in functions to get the current URL
		if (is_admin()) {
			// Admin pages: use REQUEST_URI directly
			$eeURL = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

			// Build full URL with protocol and host
			$eeProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
			$eeHost = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
			$eeURL = $eeProtocol . $eeHost . $eeURL;
		} else {
			// Frontend: use WordPress function
			$eeURL = add_query_arg(array());
		}

		// Remove unwanted parameters
		$eeURL = remove_query_arg('eeReScan', $eeURL);
		$eeURL = remove_query_arg('ee', $eeURL);

		// Remove query string if requested
		if ($eeIncludeQuery === FALSE) {
			$eeURL = strtok($eeURL, '?');
		}

		eeSFL_Debug_Log("eeSFL_GetThisURL() returning: " . $eeURL, 'URL', $this->eeListID);

		return $eeURL;
	}





	// This method should return the results of an operation; success, warning or failure.
	public function eeSFL_ResultsNotification() {

		$eeGo = eeSFL_Go;

		 $eeOutput = '';

		$eeLogParts = array('errors' => 'error', 'warnings' => 'warning', 'messages' => 'success');

		foreach($eeLogParts as $eePart => $eeType) {

			if(!empty($this->eeUserMessages[$eePart])) {

				 $eeOutput .= '<div class="';

				if( is_admin() ) {
					 $eeOutput .=  'notice notice-' . $eeType . ' is-dismissible';
				} else {
					 $eeOutput .= 'eeSFL_ResultsNotification eeSFL_ResultsNotification_' . $eePart;
				}

				 $eeOutput .= '">
				<ul>';

				foreach($this->eeUserMessages[$eePart] as $eeValue) { // We can go two-deep arrays

					if(is_array($eeValue)) {
						foreach ($eeValue as $eeValue2) {
							 $eeOutput .= '
							<li>' . $eeValue2 . '</li>' . PHP_EOL;
						}
					} else {
						 $eeOutput .= '
						<li>' . $eeValue . '</li>' . PHP_EOL;
					}
				}
				 $eeOutput .= '
				</ul>
				</div>';

				$this->eeUserMessages[$eePart] = array(); // Clear this part of the array

			}
		}

		return  $eeOutput;

	}


} // END Class

?>