<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

// The Main File List Display Page Used Both Front and Back


eeSFL_Debug_Log("Loading File List Display ...", 'List');

// Initialization is such a sensation
$eeReScan = FALSE;
$eeProceed = FALSE;
$eeSFL_ListClass = 'eeSFL'; // The basic list's CSS class. Extensions might change this.
$eeClass = ''; // Meaning, CSS class
$eeSFL_AllowFrontManage = 'NO'; // Front-side freedom
$eeUploadedFiles = array();
$eeForceSort = FALSE; // Force re-sort of file list
if(empty($eeURL)) { $eeURL = $eeSFL->eeSFL_GetThisURL(); }
$eeDateFormat = get_option('date_format');
$eeMessages = array('File List Loading');
$eeShowSearch = FALSE; // Search and paging, set below
$eeSearchQuery = '';
$eeListTotal = 0;
$eeMatchCount = 0;
$eePaging = array();

if(!isset($eeSFL_HideName)) { $eeSFL_HideName = FALSE; }
if(!isset($eeSFL_HideType)) { $eeSFL_HideType = FALSE; }

// Who is accessing this list?
$eeThisUser = get_current_user_id();
eeSFL_Debug_Log('- USER ID: ' . $eeThisUser , 'List', $eeSFL->eeListID);

// Load the file list
if( empty($eeSFL->eeAllFiles) OR $eeForceSort) { $eeSFL->eeSFL_GetFileList($eeForceSort); }

// echo '<pre>(' . $eeSFL->eeListID . ') '; print_r($eeSFL->eeListSettings); echo '</pre>'; // exit;
// echo '<pre>'; print_r($eeSFL->eeAllFiles); echo '</pre>'; exit;

eeSFL_Debug_Log('- LIST RUN #' . $eeSFL->eeListRun, 'List', $eeSFL->eeListID);

// Process Bulk Ops
include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-ops-bar-process.php');


// Upload Results
if( $eeSFL_Uploaded ) { // Reduce down to upload Results

	foreach( $eeSFL->eeAllFiles as $eeKey => $eeFileArray ) {

		if( in_array($eeFileArray['FilePath'], $eeSFLU->eeUploadedFiles) ) {
			$eeSFL->eeDisplayFiles[$eeKey] = $eeFileArray;
		}
	}

	if(count($eeSFL->eeDisplayFiles) == 0) {
		$eeSFL->eeAllFiles = array();
		$eeSFL->eeUserMessages['errors'][] = 'Upload Processing Issue. The file was likely uploaded, but you will need to refresh the list to see it.';
		$eeSFL->eeUserMessages['errors'][] = $eeSFLU->eeUploadedFiles;
	}

} else { // Reduce full list down to just the items for display <<<---------------------------

	eeSFL_Debug_Log('Scanning File Array...', 'List', $eeSFL->eeListID);

	// Only files directly in the list folder (folders and their files from the old Pro version are skipped)
	foreach( $eeSFL->eeAllFiles as $eeKey => $eeFileArray ) {
		if( strpos($eeFileArray['FilePath'], '/') === FALSE AND $eeFileArray['FileExt'] != 'folder' ) {
			$eeSFL->eeDisplayFiles[$eeKey] = $eeFileArray; // Add and maintain key
		}
	}

	unset($eeKey);
	unset($eeFileArray);

	// Only what this visitor will see, so the counts and pages are right
	foreach( $eeSFL->eeDisplayFiles as $eeKey => $eeFileArray ) {
		if( !strpos($eeFileArray['FilePath'], '.')
			|| !eeSFL_UserCanReadFile($eeSFL->eeListID, $eeSFL->eeListSettings, $eeFileArray, get_current_user_id())
			|| ( $eeSFL_HideName && in_array($eeFileArray['FilePath'], array_map('trim', explode(',', $eeSFL_HideName)), TRUE) )
			|| ( $eeSFL_HideType && in_array($eeFileArray['FileExt'], array_map('trim', explode(',', strtolower($eeSFL_HideType))), TRUE) ) ) {
			unset($eeSFL->eeDisplayFiles[$eeKey]);
		}
	}
	unset($eeKey, $eeFileArray);

	// Search: always in wp-admin, on the front end when the list or shortcode turns it on
	$eeShowSearch = $eeAdmin || ($eeSFL->eeListSettings['ShowSearch'] ?? 'NO') == 'YES';
	$eeSearchQuery = $eeShowSearch ? eeSFL_SearchQuery($eeSFL->eeListID) : '';
	$eeListTotal = count($eeSFL->eeDisplayFiles);

	if( $eeSearchQuery !== '' ) {
		$eeSFL->eeDisplayFiles = eeSFL_FilterFiles($eeSFL->eeDisplayFiles, $eeSearchQuery);
		eeSFL_Debug_Log('- Search "' . $eeSearchQuery . '": ' . count($eeSFL->eeDisplayFiles) . ' of ' . $eeListTotal, 'List', $eeSFL->eeListID);
	}

	$eeMatchCount = count($eeSFL->eeDisplayFiles);

	// Paging
	$eePaging = eeSFL_Paginate($eeMatchCount, (int) ($eeSFL->eeListSettings['FilesPerPage'] ?? 0), eeSFL_PageQuery($eeSFL->eeListID));
	if( $eePaging['length'] ) {
		$eeSFL->eeDisplayFiles = array_slice($eeSFL->eeDisplayFiles, $eePaging['offset'], $eePaging['length'], TRUE); // Keep the keys
	}

}

// echo '<pre>'; print_r($eeSFL->eeDisplayFiles); echo '</pre>'; exit;


// DISPLAY ===================================================

 $eeOutput .= '

<span id="eeSFL_FileListTop"><!-- Simple File List - File List Top --></span>

<div class="eeSFL"';

if($eeSFL->eeListRun == 1) { $eeOutput .= ' id="eeSFL"'; } // 3/20 - Legacy for user CSS

 $eeOutput .= '>';

// User Messaging
 $eeOutput .= $eeSFL->eeSFL_ResultsNotification();


// Page Setup ----------
 $eeOutput .= '
<span class="eeHide" id="eeSFL_ID">' . $eeSFL->eeListID . '</span>

<script>
	var eeSFL_ThisURL = "' . esc_js($eeURL) . '";
	var eeSFL_ListID = ' . $eeSFL->eeListID . ';
	var eeSFL_PluginURL = "' . esc_js($eeSFL->eeEnvironment['pluginURL']) . '";
	var eeSFL_FileListDir = "' . esc_js($eeSFL->eeListSettings['FileListDir']) . '";
	var eeSFL_ShowListStyle = "' . esc_js($eeSFL->eeListSettings['ShowListStyle']) . '";
</script>

';


// Uploaded Files
if(!is_admin() AND $eeSFL_Uploaded) {

	// Back to the list: the page without parameters
	$eeBackURL = strtok($eeURL, '?') . '?ee=1';

	 $eeOutput .= '<p class="eeSFL_ListMeta"><a href="' . $eeBackURL . '" class="button eeButton" id="eeSFL_BacktoFilesButton">&larr; ' .
		__('Back to the Files', 'simple-file-list') . '</a></p>';

	$eeSendFilesArray = $eeSFL->eeDisplayFiles; // Restrict to just what was uploaded
}


// echo '<pre>'; print_r($eeSFL->eeDisplayFiles); echo '</pre>'; exit;

// Search form, with the result count while searching
if( !empty($eeShowSearch) && ($eeListTotal > 0 || $eeSearchQuery !== '') ) {

	$eeOutput .= eeSFL_SearchFormHTML($eeSFL->eeListID, $eeSearchQuery, $eeURL);

	if( $eeSearchQuery !== '' ) {
		$eeOutput .= '<p class="eeSFL_SearchResults">' . ( $eeMatchCount
			/* translators: 1: number of files, 2: the search text */
			? esc_html(sprintf(_n('%1$d file matches “%2$s”.', '%1$d files match “%2$s”.', $eeMatchCount, 'simple-file-list'), $eeMatchCount, $eeSearchQuery))
			/* translators: %s: the search text */
			: esc_html(sprintf(__('No files match “%s”.', 'simple-file-list'), $eeSearchQuery)) ) . '</p>';
	}
}

// Conditions for File Operations Display -------------

$eeShowOps = FALSE; // Assume No
$eeShowingResults = FALSE;

if( $eeAdmin ) {
	$eeShowOps = TRUE; // Always show to on back-side
} else {
	if( $eeSFL->eeListSettings['AllowFrontManage'] == 'YES' AND $eeSFL->eeListRun == 1 ) { $eeShowOps = TRUE; }
}

// Never show for upload confirmation
if( isset($_POST['eeSFL_Upload']) ) {
	// Verify nonce for upload form submission (Missing nonce security)
	if (!wp_verify_nonce(isset($_POST['ee-simple-file-list-upload-form-nonce']) ? sanitize_text_field(wp_unslash($_POST['ee-simple-file-list-upload-form-nonce'])) : '', 'ee-simple-file-list-upload-form') && !is_admin()) {
		// Skip upload confirmation processing if nonce verification fails.
		// SECURITY: keep $eeShowOps as decided above — forcing it on exposed the management bar to any visitor.
		$eeShowingResults = FALSE;
	} else {
		$eeShowingResults = TRUE;
		$eeShowOps = FALSE;
	}
	$eeSFL->eeListSettings['AllowFrontManage'] = 'NO';
	$eeSFL->eeListSettings['AllowBulkFileDownload'] = 'NO';
}

// The List Operations Bar
if( ($eeShowOps OR $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') AND !$eeShowingResults) {
	include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-ops-bar-display.php'); // File List Managment Controls
}


$eeSFL->eeItemCount = count($eeSFL->eeDisplayFiles);
eeSFL_Debug_Log('- Listing ' . $eeSFL->eeItemCount . ' Items ...', 'List', $eeSFL->eeListID);

if( $eeSFL->eeItemCount ) {

	if($eeAdmin OR $eeSFL->eeListSettings['ShowListStyle'] == 'TABLE') {

		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display-table.php');

	} elseif($eeSFL->eeListSettings['ShowListStyle'] == 'TILES') {

		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display-tiles.php');

	} else {

		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display-flex.php');

	}

	// Page links
	if( !empty($eePaging) ) {
		$eeOutput .= eeSFL_PaginationHTML($eeSFL->eeListID, $eePaging, $eeURL);
	}


// No Files Found and Not Empty Search
} elseif( $eeSFL->eeFileCount === 0 AND $eeSearchQuery === '' ) {

	eeSFL_Debug_Log("There are no files here :-(", 'List');

	if($eeAdmin) {
		 $eeOutput .= '<div><p>&#8593; ' . __('Upload some files and they will appear here.', 'simple-file-list') . '</p></div>';
	}
}

//  $eeOutput .= '<pre>' . print_r($eeSFL->eeDisplayFiles, TRUE) . '</pre>';


// This allows javascript to access the count
 $eeOutput .= '

<p class="eeHide"><span id="eeSFL_FilesCount">' . $eeSFL->eeFileCount . '</span></p>

</div><!-- END .eeSFL -->';


// Modal Input -------------------------

 $eeOutput .= '
<span class="eeHide" id="eeSFL_ActionNonce">' . wp_create_nonce('eeSFL_ActionNonce') . '</span>
<span class="eeHide" id="eeSFL_Modal_FileID"></span>';

if($eeAdmin OR $eeSFL->eeListSettings['AllowFrontManage'] == 'YES') {

	 $eeOutput .= '

	<div class="eeSFL_Modal" id="eeSFL_Modal_EditFile">
	<div class="eeSFL_ModalBackground"></div>
	<div class="eeSFL_ModalBody">

		<button class="eeSFL_ModalClose">&times;</button>

		<h1>' . __('Edit Item', 'simple-file-list') . '</h1>

		<p class="eeSFL_ModalFilePath"></p>

		<p class="eeSFL_ModalFileDetails">' .
		__('Added', 'simple-file-list') . ': <span id="eeSFL_FileDateAdded" >???</span> | ' .
		__('Changed', 'simple-file-list') . ': <span id="eeSFL_FileDateChanged" >???</span> | ' .
		__('Size', 'simple-file-list') . ': <span id="eeSFL_FileSize">???</span>
		</p>

		<label for="eeSFL_FileNameNew">' . __('Item Name', 'simple-file-list') . '</label>
		<input type="text" id="eeSFL_FileNameNew" name="eeSFL_FileNameNew" value="??" size="64" />
		<small class="eeSFL_ModalNote">' . __('Change the name.', 'simple-file-list') . ' ' . __('Some characters are not allowed. These will be automatically replaced.', 'simple-file-list') . '</small>';

		 $eeOutput .= '<label for="eeSFL_FileNiceNameNew">' . __('File Nice Name', 'simple-file-list') . '</label>
		<input type="text" id="eeSFL_FileNiceNameNew" name="eeSFL_FileNiceNameNew" value="" size="64" />
		<small class="eeSFL_ModalNote">' . __('Enter a name that will be shown in place of the real file name.', 'simple-file-list') . ' ' . __('You may use special characters not allowed in the file name.', 'simple-file-list') . '</small>';

		 $eeOutput .= '<label for="eeSFL_FileDescriptionNew">' . __('Item Description', 'simple-file-list') . '</label>
		<textarea cols="64" rows="3" id="eeSFL_FileDescriptionNew" name="eeSFL_FileDescriptionNew"></textarea>
		<small class="eeSFL_ModalNote">' . __('Add a description.', 'simple-file-list') . ' ' . __('Use this field to describe this item and apply keywords for searching.', 'simple-file-list') . '</small>

		<h4>' . __('Item Date Added', 'simple-file-list') . '</h4>

		<div class="eeSFL_DateNew">
		<label>' . __('Year', 'simple-file-list') . '<input min="1970" max="' . gmdate('Y') . '" type="number" name="eeSFL_FileDateAddedYearNew" value="" id="eeSFL_FileDateAddedYearNew" /></label>
		<label>' . __('Month', 'simple-file-list') . '<input min="1" max="12" type="number" name="eeSFL_FileDateAddedMonthNew" value="" id="eeSFL_FileDateAddedMonthNew" /></label>
		<label>' . __('Day', 'simple-file-list') . '<input min="1" max="31" type="number" name="eeSFL_FileDateAddedDayNew" value="" id="eeSFL_FileDateAddedDayNew" /></label>
		</div>
		<small class="eeSFL_ModalNote">' . __('Change the date added to the list.', 'simple-file-list') . '</small>

		<h4>' . __('Item Date Changed', 'simple-file-list') . '</h4>

		<div class="eeSFL_DateNew">
		<label>' . __('Year', 'simple-file-list') . '<input min="1970" max="' . gmdate('Y') . '" type="number" name="eeSFL_FileDateChangedYearNew" value="" id="eeSFL_FileDateChangedYearNew" /></label>
		<label>' . __('Month', 'simple-file-list') . '<input min="1" max="12" type="number" name="eeSFL_FileDateChangedMonthNew" value="" id="eeSFL_FileDateChangedMonthNew" /></label>
		<label>' . __('Day', 'simple-file-list') . '<input min="1" max="31" type="number" name="eeSFL_FileDateChangedDayNew" value="" id="eeSFL_FileDateChangedDayNew" /></label>
		</div>
		<small class="eeSFL_ModalNote">' . __('Change date the file was last modified.', 'simple-file-list') . '</small>

		<button class="button" onclick="eeSFL_FileEditSaved()">' . __('Save', 'simple-file-list') . '</button>

	</div>
	</div>';

}

// Extension Check
if(is_object($eeSFLE) && method_exists($eeSFLE, 'eeSFLE_EmailSendForm')) {

	 $eeOutput .= $eeSFLE->eeSFLE_EmailSendForm();
}




eeSFL_Debug_Log("File Listing Completed.", 'List');

$eeMessages[] = $eeURL;
$eeMessages[] = 'Listing ' . $eeSFL->eeFileCount . ' Items';
do_action('eeSFL_Hook_Loaded', $eeMessages);

?>