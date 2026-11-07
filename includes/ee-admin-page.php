<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Admin-Side Display
function avpvh_flst_back_end() {

	$eeAdmin = is_admin(); // Should be TRUE here
	if(!$eeAdmin) { return FALSE; }

	global $eeSFL, $eeSFL_Tasks;
	global $eeSFLU, $eeSFLE, $eeSFLM; // Extensions

	avpvh_flst_debug_log("Loading Back-End Display ...", 'Admin');

	// Load the settings - SFLA will auto-initialize if needed during ee-ini.php
	$eeSFL->eeListSettings = $eeSFL->get_settings($eeSFL->eeListID); // Get the Settings

	$eeURL = $eeSFL->get_this_url();
	avpvh_flst_debug_log("Current URL: " . $eeURL, 'Admin', $eeSFL->eeListID);

	$eeForceSort = FALSE; // Only used in shortcode
	$eeConfirm = FALSE;


	if($eeSFLE) {
		if( !isset($eeSFL->eeListSettings['AllowFrontSend']) ) {
			$eeArray = array_merge( $eeSFL->eeListSettings, $eeSFLE->eeSettingsDefault );
			avpvh_flst_debug_log('- File Sending Settings Not Found for List ID ' . $eeSFL->eeListID . '. Applying Defaults...', 'Admin', $eeSFL->eeListID);
			update_option('avpvh_flst_settings_' . $eeSFL->eeListID , $eeArray);
			$eeSFL->eeListSettings = $eeArray;
			unset($eeArray);
		}
	}

	// The Admin Header
	include('ee-admin-header.php');

	// TABS -------------

	// Get the new tab's query string value. We will only use values to display tabs that we are expecting.
	if( isset( $_GET[ 'tab' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation param, not form submission
		$active_tab = esc_js(sanitize_text_field(wp_unslash($_GET[ 'tab' ]))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation param, not form submission
	} else {
		$active_tab = 'list';
	}

	 $eeOutput .= '<h2 class="nav-tab-wrapper">';

	// Main Tabs -------

	// File List
	 $eeOutput .= '

	<span class="nav-tab-wrapper-left">';

	$eeMultipleLists = count(avpvh_flst_manageable_list_ids()) > 1;
	$eeListName = $eeMultipleLists ? esc_html(stripslashes($eeSFL->eeListSettings['ListTitle'] ?? '')) : __('File List', 'avpvh-filelist');

	// All File Lists
	if(current_user_can('manage_options')) {
		$eeOutput .= '<a href="?page=' . AVPVH_FLST_SLUG . '&tab=lists" id="eeSFL_TabAllFileLists" class="nav-tab' . ($active_tab == 'lists' ? ' eeActiveTab' : '') . '">' . __('All File Lists', 'avpvh-filelist') . '</a>';
	}

	// This List
	$eeOutput .= '
	<a href="?page=' . AVPVH_FLST_SLUG . '&tab=list&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabFileList" class="nav-tab' . ($active_tab == 'list' ? ' eeActiveTab' : '') . '">' . $eeListName . '</a>';

	// Who may open which file of this list
	$eeOutput .= '
	<a href="?page=' . AVPVH_FLST_SLUG . '&tab=access&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabFileAccess" class="nav-tab' . ($active_tab == 'access' ? ' eeActiveTab' : '') . '">' . __('File Access', 'avpvh-filelist') . '</a>';

	// Settings
	if(current_user_can('manage_options')) {
		$eeOutput .= '
		<a href="?page=' . AVPVH_FLST_SLUG . '&tab=settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabSettings" class="nav-tab' . ($active_tab == 'settings' ? ' eeActiveTab' : '') . '">' . __('List Settings', 'avpvh-filelist') . '</a>';

		$eeOutput .= '
		<a href="?page=' . AVPVH_FLST_SLUG . '&tab=create" id="eeSFL_TabCreate" class="nav-tab' . ($active_tab == 'create' ? ' eeActiveTab' : '') . '">' . __('Create List', 'avpvh-filelist') . '</a>';

		$eeOutput .= '
		<a href="?page=' . AVPVH_FLST_SLUG . '&tab=groups" id="eeSFL_TabGroups" class="nav-tab' . ($active_tab == 'groups' ? ' eeActiveTab' : '') . '">' . __('Groups', 'avpvh-filelist') . '</a>';
	}


     $eeOutput .= '

    </span>
    <span class="nav-tab-wrapper-right">';


    // Link to Support Form
     $eeOutput .= '
    <a href="https://github.com/grmt/avpvh-filelist/issues" class="nav-tab" target="_blank" rel="noopener">' . __('Get Help', 'avpvh-filelist') . ' &rarr;</a>

    </span>';

	 $eeOutput .= '</h2>'; // END Main Tabs

    if($eeMultipleLists AND $active_tab == 'settings') {
	     $eeOutput .= '<p id="eeSFL_ListSettingsTitle">'. $eeListName . ' | ' . __('Settings', 'avpvh-filelist') . '</p>';
    }

    // Tab Content =============================================================

	if($active_tab == 'lists' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= avpvh_flst_all_lists_page();

	} elseif($active_tab == 'create' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= avpvh_flst_create_list_page();

	} elseif($active_tab == 'access') {

		$eeOutput .= avpvh_flst_file_access_page(); // Checks the list's admin capability itself

	} elseif($active_tab == 'groups' && current_user_can('manage_options')) {

		$eeOutput .= avpvh_flst_groups_page();

	} elseif($active_tab == 'list') {

		// Upload Check
		$eeSFL_Uploaded = $eeSFLU->upload_check($eeSFL->eeListRun);

		if( empty($eeSearchResultCount) ) {

			 $eeOutput .= '

			<section class="eeSFL_Settings">
			<div id="uploadFilesDiv" class="eeSettingsTile eeAdminUploadForm">';


			// The Upload Form
			 $eeOutput .= $eeSFLU->upload_form();

			 $eeOutput .= '</div>

			<div class="eeSettingsTile">
			<div class="eeColInline">';

			// If showing just-uploaded files
			if($eeSFL_Uploaded) {

				 $eeOutput .= '

				<a href="' . admin_url() . '?page=' . AVPVH_FLST_SLUG . '&eeListID=' . $eeSFL->eeListID . '" class="button eeButton" id="eeSFL_BacktoFilesButton">&larr; ' . __('Back to the Files', 'avpvh-filelist') . '</a>';

			} else {

				 $eeOutput .= '

			<div class="eeColHalfLeft">
				<a class="eeHide button eeFlex1" id="eeSFL_UploadFilesButtonSwap">' . __('Cancel Upload', 'avpvh-filelist') . '</a>
				<a href="#" class="button eeFlex1" id="eeSFL_UploadFilesButton">' . __('Upload Files', 'avpvh-filelist') . '</a>
			</div>

			<div class="eeColHalfRight">';

				// Get the File Array
				$eeSFL->get_file_list($eeSFL->eeListID, FALSE);

			// Check Array and Get File Count
			if( is_array($eeSFL->eeAllFiles) ) {

				// Count Files
				foreach($eeSFL->eeAllFiles as $eeFileArray) {
					if(strpos($eeFileArray['FilePath'], '.')) {
						$eeSFL->eeFileCount++;
					}
				}

				// Calc Date Last Changed
				$eeArray = array();
					foreach( $eeSFL->eeAllFiles as $eeKey => $eeFileArray) { $eeArray[] = $eeFileArray['FileDateAdded']; }
					rsort($eeArray); // Most recent at the top

					 $eeOutput .= '
					<small>';

				if($eeMultipleLists) {
						 $eeOutput .= '<strong>' . $eeListName . '</strong><br />';
					}

					 $eeOutput .= $eeSFL->eeFileCount . ' ' . __('Files', 'avpvh-filelist') .  ' - ' . __('Sorted by', 'avpvh-filelist') . ' ' . ucwords($eeSFL->eeListSettings['SortBy']);

					if($eeSFL->eeListSettings['SortOrder'] == 'Ascending') {  $eeOutput .= ' &uarr;'; } else {  $eeOutput .= ' &darr;'; }

					if(isset($eeArray[0])) {
						 $eeOutput .= '<br />' .
							__('Last Changed', 'avpvh-filelist') . ': ' . date_i18n( get_option('date_format'), strtotime( $eeArray[0] ) );
					}

					 $eeOutput .= '</small>';

					unset($eeArray);

				} else {

					$eeSFL->eeAllFiles = array();
				}

				 $eeOutput .= '</div>';
			}

			 $eeOutput .= '

				</div></div>

			</section>';

		}

		include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-display.php'); // The File List

		// Capture dev output BEFORE clearing arrays (using hook for Tools plugin)
		ob_start();
		do_action('avpvh_flst_debug_output');
		$eeDevOutputFileList = ob_get_clean();
		avpvh_flst_debug_log("Captured dev output for file list tab [length:" . strlen($eeDevOutputFileList) . "]", 'Admin', $eeSFL->eeListID);

		$eeSFL->eeAllFiles = array();
		$eeSFL->eeDisplayFiles = array();

	} elseif($active_tab == 'settings' && current_user_can('manage_options')) {

		// Sub Tabs
		if( isset( $_GET[ 'subtab' ] ) ) { $active_subtab = esc_js(sanitize_text_field(wp_unslash($_GET['subtab']))); } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation param, not form submission
			else { $active_subtab = 'list_settings'; }

    	 $eeOutput .= '

    	<h2 class="nav-tab-wrapper">
    	<span class="ee-nav-sub-tabs">';


		// List Settings
		 $eeOutput .= '<a href="?page=' . AVPVH_FLST_SLUG . '&tab=settings&subtab=list_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabListSettings" class="nav-tab ';
		if($active_subtab == 'list_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'list_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('File List Settings', 'avpvh-filelist') . '</a>';

	    // Upload Settings
		 $eeOutput .= '<a href="?page=' . AVPVH_FLST_SLUG . '&tab=settings&subtab=uploader_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabUploads" class="nav-tab ';
		if($active_subtab == 'uploader_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'uploader_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('File Upload Settings', 'avpvh-filelist') . '</a>';

	    // Notification Settings
		 $eeOutput .= '<a href="?page=' . AVPVH_FLST_SLUG . '&tab=settings&subtab=email_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabNotice" class="nav-tab ';
		if($active_subtab == 'email_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'email_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('Notification Settings', 'avpvh-filelist') . '</a>';

	     $eeOutput .= '

	    </span>
	    </h2>

	    <section class="eeSFL_Settings">';

		if($active_subtab == 'uploader_settings') {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-upload-settings.php'); // The Uploader Settings

		} elseif($active_subtab == 'email_settings') {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-email-settings.php'); // The Notifications Settings


		}  else {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-settings.php'); // The File List Settings
		}

		 $eeOutput .= '

		</section>';

	} // END Tab Content

	include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-admin-footer.php');

	avpvh_flst_debug_log("Admin SFL Display Completed", 'Admin');

	// Add development output for testing and debugging
	// First add the file list dev output (if we're on file list tab)
	if (isset($eeDevOutputFileList)) {
		avpvh_flst_debug_log("Adding file list dev output to page", 'Admin', $eeSFL->eeListID);
		 $eeOutput .= $eeDevOutputFileList;
	} else {
		// Otherwise add current dev output (settings tab, etc.) using hook for Tools plugin
		avpvh_flst_debug_log("Adding current dev output to page (no file list)", 'Admin', $eeSFL->eeListID);
		ob_start();
		do_action('avpvh_flst_debug_output');
		 $eeOutput .= ob_get_clean();
	}

	// Output the page
	echo  $eeOutput; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --  $eeOutput contains pre-sanitized HTML content

}

?>