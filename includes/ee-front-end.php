<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Shortcode
function eeSFL_FrontEnd($atts, $content = null) { // Shortcode Usage: [eeSFL]

	if(has_filter('wpautop')) {
		remove_filter( 'the_content', 'wpautop' ); // This will break SFL
	}

	global $eeSFL, $eeSFL_UploadFormRun, $eeSFL_VarsForJS;
    global $eeSFLF, $eeSFLU, $eeSFLS, $eeSFLA, $eeSFLE, $eeSFLM; // Extensions

    eeSFL_Debug_Log("Shortcode Function Loading ...", 'Shortcode');
	eeSFL_Debug_Log('URL: ' . $eeSFL->eeSFL_GetThisURL(), 'Shortcode', $eeSFL->eeListID);

	$eeAdmin = is_admin();
	if($eeAdmin) { return; } // Don't execute shortcode on page editor

    $eeSFL_Uploaded = FALSE;

	 $eeOutput = '';

    // Over-Riding Shortcode Attributes
	// Always merge with defaults even if no atts provided
	$atts = shortcode_atts( array( // Use lowercase att names only
			'list' => '', // List ID, default 1
			'showlist' => '', // YES, ADMIN, USER or NO
			'style' => '', // TABLE, TILES or FLEX
			'theme' => '', // LIGHT, DARK or NONE
			'allowuploads' => '', // YES, ADMIN, USER or NO
			'showthumb' => '', // YES or NO
			'showdate' => '', // YES or NO
			'showsize' => '', // YES or NO
			'showheader' => '', // YES or NO
			'showactions' => '', // YES or NO
			'sortby' => '', // Name, Added, Changed, Size, or Random
			'sortorder' => '', // Descending or Ascending
			'hidetype' => '', // Hide file types
			'hidename' => '', // Hide the name matches
			'getdesc' => '', // YES or NO to show the upload description input
			'getinfo' => '', // YES or NO to show the upload user info inputs
		), $atts );

	// Extract attributes into variables
	extract($atts); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- keys are limited to the shortcode_atts() whitelist above

	// Which list: [eeSFL list="2"]. The settings are reloaded for every shortcode, so one
	// shortcode's list or attribute overrides never carry over to the next one on the page.
	$eeShortcodeListID = absint($list) ?: 1;
	if( !eeSFL_ListExists($eeShortcodeListID) || !$eeSFL->eeSFL_GetSettings($eeShortcodeListID) ) {
		return current_user_can('manage_options') ? '[' . esc_html__('List Not Found', 'simple-file-list') . ']' : '';
	}
	$eeSFL->eeListID = $eeShortcodeListID;

	if($atts && array_filter($atts)) { // Only log if actual attributes were provided

		// Show the Shortcode in the Log
		$eeShortcode = '[eeSFL';
		$eeShortcodeAtts = array_filter($atts);
		foreach( $eeShortcodeAtts as $eeAtt => $eeValue) { $eeShortcode .= ' ' . $eeAtt . '="' . $eeValue . '"'; }
		$eeShortcode .= ']';
		eeSFL_Debug_Log('- Shortcode: ' . $eeShortcode, 'Shortcode', $eeSFL->eeListID);

		 $eeOutput .= '
		<!-- Shortcode: ' . esc_html(str_replace('--', '', $eeShortcode)) . ' List Run: #' . intval($eeSFL->eeListRun) . ' -->';

		extract($atts); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- keys are limited to the shortcode_atts() whitelist above

		eeSFL_Debug_Log("Frontend shortcode processing - List: $eeShortcodeListID", 'Shortcode', $eeShortcodeListID);

		// SECURITY: Shortcode overrides for showlist/allowuploads.
		// Overrides that are equally or more restrictive than the stored setting always apply.
		// Overrides that would expand access beyond the stored setting require manage_options,
		// preventing Author-level users from exposing restricted content.
		// Access levels: YES=4, USER=3, GRANTED=2, ADMIN=1, NO=0
		$eeAccessLevels = ['YES' => 4, 'USER' => 3, 'GRANTED' => 2, 'ADMIN' => 1, 'NO' => 0];

		if($showlist) {
			$eeShowlistUpper = strtoupper($showlist);
			$eeCurrentLevel = $eeAccessLevels[$eeSFL->eeListSettings['ShowList']] ?? 3;
			$eeOverrideLevel = $eeAccessLevels[$eeShowlistUpper] ?? 0;
			if($eeOverrideLevel <= $eeCurrentLevel || current_user_can('manage_options')) {
				$eeSFL->eeListSettings['ShowList'] = $eeShowlistUpper;
			}
		}
		if($allowuploads) {
			$eeAllowuploadsUpper = strtoupper($allowuploads);
			$eeCurrentLevel = $eeAccessLevels[$eeSFL->eeListSettings['AllowUploads']] ?? 3;
			$eeOverrideLevel = $eeAccessLevels[$eeAllowuploadsUpper] ?? 0;
			if($eeOverrideLevel <= $eeCurrentLevel || current_user_can('manage_options')) {
				$eeSFL->eeListSettings['AllowUploads'] = $eeAllowuploadsUpper;
			}
		}

		if($style) { $eeSFL->eeListSettings['ShowListStyle'] = strtoupper($style); }
		if($theme) { $eeSFL->eeListSettings['ShowListTheme'] = strtoupper($theme); }
		if($showthumb) { $eeSFL->eeListSettings['ShowFileThumb'] = strtoupper($showthumb); }
		if($showdate) { $eeSFL->eeListSettings['ShowFileDate'] = strtoupper($showdate); }
		if($showsize) { $eeSFL->eeListSettings['ShowFileSize'] = strtoupper($showsize); }
		if($showheader) { $eeSFL->eeListSettings['ShowHeader'] = strtoupper($showheader); }
		if($showactions) { $eeSFL->eeListSettings['ShowFileActions'] = strtoupper($showactions); }
		if($getdesc !== '') { $eeSFL->eeListSettings['GetUploaderDesc'] = strtoupper($getdesc); }
		if($getinfo !== '') { $eeSFL->eeListSettings['GetUploaderInfo'] = strtoupper($getinfo); }

		// Force a re-sort of the file list array if a shortcode attribute was used
		if($sortby OR $sortorder) {

			if( $sortby != $eeSFL->eeListSettings['SortBy'] OR $sortorder != $eeSFL->eeListSettings['SortOrder'] ) {
				$eeForceSort = TRUE;
				$eeSFL->eeListSettings['SortBy'] = ucwords($sortby);
				$eeSFL->eeListSettings['SortOrder'] = ucwords($sortorder);
			} else {
				$eeForceSort = FALSE;
			}
		}

		// LEGACY - Info Not Published
		if($hidename) { $eeSFL_HideName = $hidename; } else { $eeSFL_HideName = FALSE; }
		if($hidetype) { $eeSFL_HideType = strtolower($hidetype); } else { $eeSFL_HideType = FALSE; }


		// Both "folder" and "showfolder" will work. (Pro only — handled by eeSFL_ProShortcodeAtts in pro/ee-pro-functions.php)
		if(function_exists('eeSFL_ProShortcodeAtts')) {
			$eeProResult = eeSFL_ProShortcodeAtts($folder, $showfolder, $paged, $filecount, $search);
			if($eeProResult === '') { return ''; }
		}
	}

	// Who Can Upload? (Evaluated before eeSFL_UploadCheck so unauthorized POSTs are rejected)
	switch ($eeSFL->eeListSettings['AllowUploads']) {
	    case 'YES':
	        break; // Show It
	    case 'USER':
	        // Show It If...
	        if( get_current_user_id() ) { break; } else { $eeSFL->eeListSettings['AllowUploads'] = 'NO'; }
	    case 'ADMIN':
	        // Show It If...
	        if(current_user_can('manage_options')) { break; } else { $eeSFL->eeListSettings['AllowUploads'] = 'NO'; }
	        break;
		default:
			$eeSFL->eeListSettings['AllowUploads'] = 'NO'; // Show Nothing
	}

	$eeDependents = array('jquery'); // Requires jQuery

	if($eeSFL->eeListRun == 1) {

	    if($eeSFL->eeListSettings['AllowFrontManage'] != 'NO') {
	    	wp_enqueue_script('ee-simple-file-list-js-edit-file', plugin_dir_url(__FILE__) . 'js/ee-edit-file.js', $eeDependents, eeSFL_Version, TRUE);
		}

		// Media Player Script and Variables
		$eeSFLM = TRUE; // Enable media player
		if($eeSFLM) {
			wp_enqueue_script('ee-simple-file-list-js-media', plugin_dir_url(dirname(__FILE__)) . 'js/ee-media-scripts-footer.js', $eeDependents, eeSFL_Version, TRUE);
			$eeSFLM_VarsForJS = array(
				'eePlayLabel' => __('Play', 'simple-file-list'),
				'eeBrowserWarning' => __('Browser is Not Compatible', 'simple-file-list'),
				'eeAudioEnabled' => $eeSFL->eeListSettings['AudioEnabled'],
				'eeAudioHeight' => $eeSFL->eeListSettings['AudioHeight']
			);
			wp_localize_script( 'ee-simple-file-list-js-media', 'eeSFLM_Vars', $eeSFLM_VarsForJS );
		}


		// List Theme CSS
	    if($eeSFL->eeListSettings['ShowListTheme'] == 'DARK') {
			wp_enqueue_style('ee-simple-file-list-css-theme-dark');
		} elseif($eeSFL->eeListSettings['ShowListTheme'] == 'LIGHT') {
			wp_enqueue_style('ee-simple-file-list-css-theme-light');
		}

	    // List Style CSS
	    if($eeSFL->eeListSettings['ShowListStyle'] == 'FLEX') {
			wp_enqueue_style('ee-simple-file-list-css-flex');
		} elseif($eeSFL->eeListSettings['ShowListStyle'] == 'TILES') {
			wp_enqueue_style('ee-simple-file-list-css-tiles');
		} else {
			wp_enqueue_style('ee-simple-file-list-css-table');
		}

		// Upload Check (only if uploads are permitted)
		if($eeSFL->eeListSettings['AllowUploads'] != 'NO') {
			$eeSFL_Uploaded = $eeSFLU->eeSFL_UploadCheck($eeSFL->eeListRun);
		}

	}

	// Extension Check
	if($eeSFLA) {
	    eeSFL_Debug_Log('- Current User ID: ' . $eeSFL->eeEnvironment['wpUserID'], 'Shortcode', $eeSFL->eeListID);

	    // Initialize the variable to prevent undefined variable warnings
	    $eeSFLA_ShowTheList = TRUE; // Default to show list

	    if( !$showlist ) { // Shortcode setting over-rides access
			include(WP_PLUGIN_DIR . '/ee-simple-file-list-access/includes/eeSFLA_FrontsideFirewall.php');
		}
	}
	// Begin Front-End List Display ==================================================================


	$eeShowUploadForm = FALSE;

	if(!$eeSFL_Uploaded AND $eeSFL->eeListSettings['AllowUploads'] != 'NO' AND !$eeSFL_UploadFormRun AND !(isset($_POST['eeSFLS_Searching']) && sanitize_text_field(wp_unslash($_POST['eeSFLS_Searching'])) && wp_verify_nonce(isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '', 'ee-search-form'))) {

		eeSFL_Debug_Log("Upload form enabled - AllowUploads: " . $eeSFL->eeListSettings['AllowUploads'], 'Shortcode', $eeSFL->eeListID);
		wp_enqueue_style('ee-simple-file-list-css-upload');
		wp_enqueue_script('ee-simple-file-list-js-uploader', plugin_dir_url(__FILE__) . 'js/ee-uploader.js', $eeDependents , eeSFL_Version, TRUE);
		$eeSFL_UploadFormRun = TRUE;
		$eeShowUploadForm = TRUE;
	}

	// Resolve the current folder before rendering the upload form so uploads go to the
	// correct folder regardless of UploadPosition (Above or Below).
	if($eeSFLF) { $eeSFLF->eeSFL_ResolveCurrentFolder(); }

	if($eeShowUploadForm AND $eeSFL->eeListSettings['UploadPosition'] == 'Above') {
		 $eeOutput .= $eeSFLU->eeSFL_UploadForm();
	}

	// Extension Check
	if($eeSFLA) {
		if($eeSFLA_ShowTheList === FALSE) {
			$eeSFL->eeListSettings['ShowList'] = 'NO'; // We cannot go on.
		}
	}

	// Who Can View the List?
	switch ($eeSFL->eeListSettings['ShowList']) {
	    case 'YES':
	        break; // Show It
	    case 'RESTRICTED':
	        break; // Show It By File (eeSFLA)
	    case 'GRANTED':
	        // Only the users, roles and groups in the list's grant
	        if( eeSFL_UserCanSeeList($eeSFL->eeListID, $eeSFL->eeListSettings, get_current_user_id()) ) { break; }
	        $eeSFL->eeListSettings['ShowList'] = 'NO';
	        break;
	    case 'USER':
	        // Show It If...
	        if( get_current_user_id() ) { break; } else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }
	    case 'ADMIN':
	        // Show It If...
	        if(current_user_can('manage_options')) { break; } else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }
	        break;
		default:
			$eeSFL->eeListSettings['ShowList'] = 'NO'; // Show Nothing
	}

	if($eeSFL->eeListSettings['ShowList'] != 'NO') {

		// Lazy-load the email class if email sharing is enabled and it was not loaded at setup
		if((!is_object($eeSFLE) || !method_exists($eeSFLE, 'eeSFLE_EmailSendForm')) && ($eeSFL->eeListSettings['AllowFrontSend'] ?? 'NO') === 'YES') {
			require_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-class-send.php');
			$eeSFLE = new eeSFLE_class();
		}

		eeSFL_Debug_Log("Displaying frontend list - Mode: " . ($eeSFL->eeListSettings['Mode'] ?? 'Unknown'), 'Shortcode', $eeSFL->eeListID);
		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display.php'); // The List is Loaded Here -------------
	} else {
		eeSFL_Debug_Log("List display blocked (ShowList = NO)", 'Shortcode', $eeSFL->eeListID);
	}

	if($eeShowUploadForm AND $eeSFL->eeListSettings['UploadPosition'] == 'Below') {
		 $eeOutput .= $eeSFLU->eeSFL_UploadForm();
	}

	// Smooth Scrolling is AWESOME!
	if( isset($_REQUEST['ee']) AND $eeSFL->eeListSettings['SmoothScroll'] == 'YES' ) {
		 $eeOutput .= '<script>eeSFL_ScrollToIt();</script>'; }

	$eeSFL->eeListRun++;

	eeSFL_Debug_Log("SFL Display Completed", 'Shortcode', $eeSFL->eeListID);

	// Hook for debug output (used by Simple File List Tools plugin)
	ob_start();
	do_action('eeSFL_Hook_DebugOutput');
	 $eeOutput .= ob_get_clean();

	// Give it back
	$eeSFL->eeAllFiles = array();
	$eeSFL->eeDisplayFiles = array();

	// Output the page
	return  $eeOutput; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --  $eeOutput contains pre-sanitized HTML content
}

?>