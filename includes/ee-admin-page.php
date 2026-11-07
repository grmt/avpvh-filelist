<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Admin-Side Display
function eeSFL_BackEnd() {

	$eeAdmin = is_admin(); // Should be TRUE here
	if(!$eeAdmin) { return FALSE; }

	global $eeSFL, $eeSFL_Tasks;
	global $eeSFLU, $eeSFLE, $eeSFLM; // Extensions

	eeSFL_Debug_Log("Loading Back-End Display ...", 'Admin');

	// Load the settings - SFLA will auto-initialize if needed during ee-ini.php
	$eeSFL->eeListSettings = $eeSFL->eeSFL_GetSettings($eeSFL->eeListID); // Get the Settings

	$eeURL = $eeSFL->eeSFL_GetThisURL();
	eeSFL_Debug_Log("Current URL: " . $eeURL, 'Admin', $eeSFL->eeListID);

	$eeForceSort = FALSE; // Only used in shortcode
	$eeConfirm = FALSE;


	if($eeSFLE) {
		if( !isset($eeSFL->eeListSettings['AllowFrontSend']) ) {
			$eeArray = array_merge( $eeSFL->eeListSettings, $eeSFLE->eeSFLE_SettingsDefault );
			eeSFL_Debug_Log('- File Sending Settings Not Found for List ID ' . $eeSFL->eeListID . '. Applying Defaults...', 'Admin', $eeSFL->eeListID);
			update_option('eeSFL_Settings_' . $eeSFL->eeListID , $eeArray);
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

	$eeMultipleLists = count(eeSFL_ManageableListIDs()) > 1;
	$eeListName = $eeMultipleLists ? esc_html(stripslashes($eeSFL->eeListSettings['ListTitle'] ?? '')) : __('File List', 'simple-file-list');

	// All File Lists
	if(current_user_can('manage_options')) {
		$eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=lists" id="eeSFL_TabAllFileLists" class="nav-tab' . ($active_tab == 'lists' ? ' eeActiveTab' : '') . '">' . __('All File Lists', 'simple-file-list') . '</a>';
	}

	// This List
	$eeOutput .= '
	<a href="?page=' . eeSFL_PluginSlug . '&tab=list&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabFileList" class="nav-tab' . ($active_tab == 'list' ? ' eeActiveTab' : '') . '">' . $eeListName . '</a>';

	// Who may open which file of this list
	$eeOutput .= '
	<a href="?page=' . eeSFL_PluginSlug . '&tab=access&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabFileAccess" class="nav-tab' . ($active_tab == 'access' ? ' eeActiveTab' : '') . '">' . __('File Access', 'simple-file-list') . '</a>';

	// Settings
	if(current_user_can('manage_options')) {
		$eeOutput .= '
		<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabSettings" class="nav-tab' . ($active_tab == 'settings' ? ' eeActiveTab' : '') . '">' . __('List Settings', 'simple-file-list') . '</a>';

		$eeOutput .= '
		<a href="?page=' . eeSFL_PluginSlug . '&tab=create" id="eeSFL_TabCreate" class="nav-tab' . ($active_tab == 'create' ? ' eeActiveTab' : '') . '">' . __('Create List', 'simple-file-list') . '</a>';

		$eeOutput .= '
		<a href="?page=' . eeSFL_PluginSlug . '&tab=groups" id="eeSFL_TabGroups" class="nav-tab' . ($active_tab == 'groups' ? ' eeActiveTab' : '') . '">' . __('Groups', 'simple-file-list') . '</a>';
	}


     $eeOutput .= '

    </span>
    <span class="nav-tab-wrapper-right">';


    // Link to Support Form
     $eeOutput .= '
    <a href="https://github.com/grmt/simple-file-list/issues" class="nav-tab" target="_blank" rel="noopener">' . __('Get Help', 'simple-file-list') . ' &rarr;</a>

    </span>';

	 $eeOutput .= '</h2>'; // END Main Tabs

    if($eeMultipleLists AND $active_tab == 'settings') {
	     $eeOutput .= '<p id="eeSFL_ListSettingsTitle">'. $eeListName . ' | ' . __('Settings', 'simple-file-list') . '</p>';
    }

    // Tab Content =============================================================

	if($active_tab == 'lists' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= eeSFL_AllListsPage();

	} elseif($active_tab == 'create' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= eeSFL_CreateListPage();

	} elseif($active_tab == 'access') {

		$eeOutput .= eeSFL_FileAccessPage(); // Checks the list's admin capability itself

	} elseif($active_tab == 'groups' && current_user_can('manage_options')) {

		$eeOutput .= eeSFL_GroupsPage();

	} elseif($active_tab == 'list') {

		// Upload Check
		$eeSFL_Uploaded = $eeSFLU->eeSFL_UploadCheck($eeSFL->eeListRun);

		if( empty($eeSearchResultCount) ) {

			 $eeOutput .= '

			<section class="eeSFL_Settings">
			<div id="uploadFilesDiv" class="eeSettingsTile eeAdminUploadForm">';


			// The Upload Form
			 $eeOutput .= $eeSFLU->eeSFL_UploadForm();

			 $eeOutput .= '</div>

			<div class="eeSettingsTile">
			<div class="eeColInline">';

			// If showing just-uploaded files
			if($eeSFL_Uploaded) {

				 $eeOutput .= '

				<a href="' . admin_url() . '?page=' . eeSFL_PluginSlug . '&eeListID=' . $eeSFL->eeListID . '" class="button eeButton" id="eeSFL_BacktoFilesButton">&larr; ' . __('Back to the Files', 'simple-file-list') . '</a>';

			} else {

				 $eeOutput .= '

			<div class="eeColHalfLeft">
				<a class="eeHide button eeFlex1" id="eeSFL_UploadFilesButtonSwap">' . __('Cancel Upload', 'simple-file-list') . '</a>
				<a href="#" class="button eeFlex1" id="eeSFL_UploadFilesButton">' . __('Upload Files', 'simple-file-list') . '</a>
			</div>

			<div class="eeColHalfRight">';

				// Get the File Array
				$eeSFL->eeSFL_GetFileList($eeSFL->eeListID, FALSE);

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

					 $eeOutput .= $eeSFL->eeFileCount . ' ' . __('Files', 'simple-file-list') .  ' - ' . __('Sorted by', 'simple-file-list') . ' ' . ucwords($eeSFL->eeListSettings['SortBy']);

					if($eeSFL->eeListSettings['SortOrder'] == 'Ascending') {  $eeOutput .= ' &uarr;'; } else {  $eeOutput .= ' &darr;'; }

					if(isset($eeArray[0])) {
						 $eeOutput .= '<br />' .
							__('Last Changed', 'simple-file-list') . ': ' . date_i18n( get_option('date_format'), strtotime( $eeArray[0] ) );
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
		do_action('eeSFL_Hook_DebugOutput');
		$eeDevOutputFileList = ob_get_clean();
		eeSFL_Debug_Log("Captured dev output for file list tab [length:" . strlen($eeDevOutputFileList) . "]", 'Admin', $eeSFL->eeListID);

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
		 $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=list_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabListSettings" class="nav-tab ';
		if($active_subtab == 'list_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'list_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('File List Settings', 'simple-file-list') . '</a>';

	    // Upload Settings
		 $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=uploader_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabUploads" class="nav-tab ';
		if($active_subtab == 'uploader_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'uploader_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('File Upload Settings', 'simple-file-list') . '</a>';

	    // Notification Settings
		 $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=email_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabNotice" class="nav-tab ';
		if($active_subtab == 'email_settings') { $eeOutput .= '  eeActiveTab ';}
	    $active_subtab == 'email_settings' ? 'nav-tab-active' : '';
	     $eeOutput .= $active_subtab . '">' . __('Notification Settings', 'simple-file-list') . '</a>';

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

	eeSFL_Debug_Log("Admin SFL Display Completed", 'Admin');

	// Add development output for testing and debugging
	// First add the file list dev output (if we're on file list tab)
	if (isset($eeDevOutputFileList)) {
		eeSFL_Debug_Log("Adding file list dev output to page", 'Admin', $eeSFL->eeListID);
		 $eeOutput .= $eeDevOutputFileList;
	} else {
		// Otherwise add current dev output (settings tab, etc.) using hook for Tools plugin
		eeSFL_Debug_Log("Adding current dev output to page (no file list)", 'Admin', $eeSFL->eeListID);
		ob_start();
		do_action('eeSFL_Hook_DebugOutput');
		 $eeOutput .= ob_get_clean();
	}

	// Output the page
	echo  $eeOutput; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --  $eeOutput contains pre-sanitized HTML content

}

?>