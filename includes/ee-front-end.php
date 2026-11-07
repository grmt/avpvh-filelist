<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Shortcode
function avpvh_flst_front_end($atts, $content = null) { // Shortcode Usage: [eeSFL]

	if(has_filter('wpautop')) {
		remove_filter( 'the_content', 'wpautop' ); // This will break SFL
	}

	global $eeSFL, $eeSFL_UploadFormRun, $eeSFL_VarsForJS;
    global $eeSFLU, $eeSFLE, $eeSFLM; // Extensions

    avpvh_flst_debug_log("Shortcode Function Loading ...", 'Shortcode');
	avpvh_flst_debug_log('URL: ' . $eeSFL->get_this_url(), 'Shortcode', $eeSFL->eeListID);

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
			'search' => '', // YES or NO to show the search form
			'filecount' => '', // Files per page; 0 shows all
			'paged' => '', // NO to show all files on one page
		), $atts );

	// Extract attributes into variables
	extract($atts); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- keys are limited to the shortcode_atts() whitelist above

	// Which list: [eeSFL list="2"]. The settings are reloaded for every shortcode, so one
	// shortcode's list or attribute overrides never carry over to the next one on the page.
	$eeShortcodeListID = absint($list) ?: 1;
	if( !avpvh_flst_list_exists($eeShortcodeListID) || !$eeSFL->get_settings($eeShortcodeListID) ) {
		return current_user_can('manage_options') ? '[' . esc_html__('List Not Found', 'avpvh-filelist') . ']' : '';
	}
	$eeSFL->eeListID = $eeShortcodeListID;

	if($atts && array_filter($atts)) { // Only log if actual attributes were provided

		// Show the Shortcode in the Log
		$eeShortcode = '[eeSFL';
		$eeShortcodeAtts = array_filter($atts);
		foreach( $eeShortcodeAtts as $eeAtt => $eeValue) { $eeShortcode .= ' ' . $eeAtt . '="' . $eeValue . '"'; }
		$eeShortcode .= ']';
		avpvh_flst_debug_log('- Shortcode: ' . $eeShortcode, 'Shortcode', $eeSFL->eeListID);

		 $eeOutput .= '
		<!-- Shortcode: ' . esc_html(str_replace('--', '', $eeShortcode)) . ' List Run: #' . intval($eeSFL->eeListRun) . ' -->';

		extract($atts); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- keys are limited to the shortcode_atts() whitelist above

		avpvh_flst_debug_log("Frontend shortcode processing - List: $eeShortcodeListID", 'Shortcode', $eeShortcodeListID);

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
		if($search !== '') { $eeSFL->eeListSettings['ShowSearch'] = strtoupper($search) == 'YES' ? 'YES' : 'NO'; }
		if($filecount !== '') { $eeSFL->eeListSettings['FilesPerPage'] = min(500, absint($filecount)); }
		if(strtoupper($paged) == 'NO') { $eeSFL->eeListSettings['FilesPerPage'] = 0; }

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

	}

	// Who Can Upload? (Evaluated before upload_check so unauthorized POSTs are rejected)
	switch ($eeSFL->eeListSettings['AllowUploads']) {
	    case 'YES':
	        break; // Show It
	    case 'USER':
	        // Show It If...
	        if( get_current_user_id() ) { break; } else { $eeSFL->eeListSettings['AllowUploads'] = 'NO'; }
	    case 'ADMIN':
	        // Show It If...
	        if(current_user_can('manage_options') || avpvh_flst_user_manages_list($eeSFL->eeListID, get_current_user_id())) { break; } else { $eeSFL->eeListSettings['AllowUploads'] = 'NO'; }
	        break;
		default:
			$eeSFL->eeListSettings['AllowUploads'] = 'NO'; // Show Nothing
	}

	$eeDependents = array('jquery'); // Requires jQuery

	if($eeSFL->eeListRun == 1) {

	    if($eeSFL->eeListSettings['AllowFrontManage'] != 'NO') {
	    	wp_enqueue_script('avpvh-filelist-js-edit-file', plugin_dir_url(__FILE__) . 'js/ee-edit-file.js', $eeDependents, AVPVH_FLST_VERSION, TRUE);
		}

		// Media Player Script and Variables
		$eeSFLM = TRUE; // Enable media player
		if($eeSFLM) {
			wp_enqueue_script('avpvh-filelist-js-media', plugin_dir_url(dirname(__FILE__)) . 'js/ee-media-scripts-footer.js', $eeDependents, AVPVH_FLST_VERSION, TRUE);
			$eeSFLM_VarsForJS = array(
				'eePlayLabel' => __('Play', 'avpvh-filelist'),
				'eeBrowserWarning' => __('Browser is Not Compatible', 'avpvh-filelist'),
				'eeAudioEnabled' => $eeSFL->eeListSettings['AudioEnabled'],
				'eeAudioHeight' => $eeSFL->eeListSettings['AudioHeight']
			);
			wp_localize_script( 'avpvh-filelist-js-media', 'eeSFLM_Vars', $eeSFLM_VarsForJS );
		}


		// List Theme CSS
	    if($eeSFL->eeListSettings['ShowListTheme'] == 'DARK') {
			wp_enqueue_style('avpvh-filelist-css-theme-dark');
		} elseif($eeSFL->eeListSettings['ShowListTheme'] == 'LIGHT') {
			wp_enqueue_style('avpvh-filelist-css-theme-light');
		}

	    // List Style CSS
	    if($eeSFL->eeListSettings['ShowListStyle'] == 'FLEX') {
			wp_enqueue_style('avpvh-filelist-css-flex');
		} elseif($eeSFL->eeListSettings['ShowListStyle'] == 'TILES') {
			wp_enqueue_style('avpvh-filelist-css-tiles');
		} else {
			wp_enqueue_style('avpvh-filelist-css-table');
		}

		// Upload Check (only if uploads are permitted)
		if($eeSFL->eeListSettings['AllowUploads'] != 'NO') {
			$eeSFL_Uploaded = $eeSFLU->upload_check($eeSFL->eeListRun);
		}

	}

	// Begin Front-End List Display ==================================================================


	$eeShowUploadForm = FALSE;

	if(!$eeSFL_Uploaded AND $eeSFL->eeListSettings['AllowUploads'] != 'NO' AND !$eeSFL_UploadFormRun) {

		avpvh_flst_debug_log("Upload form enabled - AllowUploads: " . $eeSFL->eeListSettings['AllowUploads'], 'Shortcode', $eeSFL->eeListID);
		wp_enqueue_style('avpvh-filelist-css-upload');
		wp_enqueue_script('avpvh-filelist-js-uploader', plugin_dir_url(__FILE__) . 'js/ee-uploader.js', $eeDependents , AVPVH_FLST_VERSION, TRUE);
		$eeSFL_UploadFormRun = TRUE;
		$eeShowUploadForm = TRUE;
	}

	if($eeShowUploadForm AND $eeSFL->eeListSettings['UploadPosition'] == 'Above') {
		 $eeOutput .= $eeSFLU->upload_form();
	}


	// Who Can View the List?
	switch ($eeSFL->eeListSettings['ShowList']) {
	    case 'YES':
	        break; // Show It
	    case 'GRANTED':
	        // Only the users, roles and groups in the list's grant
	        if( avpvh_flst_user_can_see_list($eeSFL->eeListID, $eeSFL->eeListSettings, get_current_user_id()) ) { break; }
	        $eeSFL->eeListSettings['ShowList'] = 'NO';
	        break;
	    case 'USER':
	        // Show It If...
	        if( get_current_user_id() ) { break; } else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }
	    case 'ADMIN':
	        // Show It If...
	        if(current_user_can('manage_options') || avpvh_flst_user_manages_list($eeSFL->eeListID, get_current_user_id())) { break; } else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }
	        break;
		default:
			$eeSFL->eeListSettings['ShowList'] = 'NO'; // Show Nothing
	}

	if($eeSFL->eeListSettings['ShowList'] != 'NO') {

		// Lazy-load the email class if email sharing is enabled and it was not loaded at setup
		if((!is_object($eeSFLE) || !method_exists($eeSFLE, 'email_send_form')) && ($eeSFL->eeListSettings['AllowFrontSend'] ?? 'NO') === 'YES') {
			require_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-class-send.php');
			$eeSFLE = new Avpvh_Flst_Send();
		}

		avpvh_flst_debug_log("Displaying frontend list - ShowList: " . $eeSFL->eeListSettings['ShowList'], 'Shortcode', $eeSFL->eeListID);
		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display.php'); // The List is Loaded Here -------------
	} else {
		avpvh_flst_debug_log("List display blocked (ShowList = NO)", 'Shortcode', $eeSFL->eeListID);
	}

	if($eeShowUploadForm AND $eeSFL->eeListSettings['UploadPosition'] == 'Below') {
		 $eeOutput .= $eeSFLU->upload_form();
	}

	// Smooth Scrolling is AWESOME!
	if( isset($_REQUEST['ee']) AND $eeSFL->eeListSettings['SmoothScroll'] == 'YES' ) {
		 $eeOutput .= '<script>eeSFL_ScrollToIt();</script>'; }

	$eeSFL->eeListRun++;

	avpvh_flst_debug_log("SFL Display Completed", 'Shortcode', $eeSFL->eeListID);

	// Hook for debug output (used by Simple File List Tools plugin)
	ob_start();
	do_action('avpvh_flst_debug_output');
	 $eeOutput .= ob_get_clean();

	// Give it back
	$eeSFL->eeAllFiles = array();
	$eeSFL->eeDisplayFiles = array();

	// Output the page
	return  $eeOutput; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --  $eeOutput contains pre-sanitized HTML content
}

?>