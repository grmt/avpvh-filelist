<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Admin-Side Display
function eeSFL_BackEnd() {

	$eeAdmin = is_admin(); // Should be TRUE here
	if(!$eeAdmin) { return FALSE; }

	global $eeSFL, $eeSFL_Tasks;
	global $eeSFLF, $eeSFLU, $eeSFLS, $eeSFLA, $eeSFLE, $eeSFLM; // Extensions

	eeSFL_Debug_Log("Loading Back-End Display ...", 'Admin');

	// Load the settings - SFLA will auto-initialize if needed during ee-ini.php
	$eeSFL->eeListSettings = $eeSFL->eeSFL_GetSettings($eeSFL->eeListID); // Get the Settings

	$eeURL = $eeSFL->eeSFL_GetThisURL();
	eeSFL_Debug_Log("Current URL: " . $eeURL, 'Admin', $eeSFL->eeListID);

	$eeForceSort = FALSE; // Only used in shortcode
	$eeConfirm = FALSE;

	if($eeSFLS) {
		if( !isset($eeSFL->eeListSettings['EnableSearch']) ) {
			$eeArray = array_merge( $eeSFL->eeListSettings, $eeSFLS->eeSFLS_SettingsDefault );
			eeSFL_Debug_Log('- Search Settings Not Found for List ID ' . $eeSFL->eeListID . '. Applying Defaults...', 'Admin', $eeSFL->eeListID);
			update_option('eeSFL_Settings_' . $eeSFL->eeListID , $eeArray);
			$eeSFL->eeListSettings = $eeArray;
			unset($eeArray);
		}
	}

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
		// Default tab logic - if SFLA exists but no SFLA settings found, go to settings tab for first-time setup
		if ($eeSFLA && $eeSFLA->eeSFLA_FirstRun === TRUE) {
			$active_tab = 'settings';
		} else {
			$active_tab = 'list';
		}
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

	// Settings
	if(current_user_can('manage_options')) {
		$eeOutput .= '
		<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabSettings" class="nav-tab' . ($active_tab == 'settings' ? ' eeActiveTab' : '') . '">' . __('List Settings', 'simple-file-list') . '</a>';

		$eeOutput .= '
		<a href="?page=' . eeSFL_PluginSlug . '&tab=create" id="eeSFL_TabCreate" class="nav-tab' . ($active_tab == 'create' ? ' eeActiveTab' : '') . '">' . __('Create List', 'simple-file-list') . '</a>';
	}


     $eeOutput .= '

    </span>
    <span class="nav-tab-wrapper-right">';


	// Get Pro Version
     $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=getpro" id="eeSFL_TabGetPro" class="nav-tab tabSupport ';
	if($active_tab == 'getpro') { $eeOutput .= '  eeActiveTab '; }
	 $eeOutput .= $active_tab == 'getpro' ? 'nav-tab-active' : '';
	 $eeOutput .= '">' . __('Get Pro Version', 'simple-file-list') . '</a>';



    // Link to Support Form
     $eeOutput .= '
    <a href="https://simplefilelist.com/get-support/" class="nav-tab" target="_blank">' . __('Get Help', 'simple-file-list') . ' &rarr;</a>

    </span>';

	 $eeOutput .= '</h2>'; // END Main Tabs

    if($eeMultipleLists AND $active_tab == 'settings') {
	     $eeOutput .= '<p id="eeSFLA_ListSettingsTitle">'. $eeListName . ' | ' . __('Settings', 'simple-file-list') . '</p>';
    }

    // Tab Content =============================================================

	if($active_tab == 'lists' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= eeSFL_AllListsPage();

	} elseif($active_tab == 'create' && current_user_can('manage_options')) {

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-lists-admin.php');
		$eeOutput .= eeSFL_CreateListPage();

	} elseif($active_tab == 'list') {

		// Upload Check
		$eeSFL_Uploaded = $eeSFLU->eeSFL_UploadCheck($eeSFL->eeListRun);

		if( empty($eeSearchResultCount) ) {

			 $eeOutput .= '

			<section class="eeSFL_Settings">
			<div id="uploadFilesDiv" class="eeSettingsTile eeAdminUploadForm">';

			// Resolve the current folder before rendering the upload form (always above list in admin)
			if($eeSFLF) { $eeSFLF->eeSFL_ResolveCurrentFolder(); }

			// The Upload Form
			 $eeOutput .= $eeSFLU->eeSFL_UploadForm();

			 $eeOutput .= '</div>

			<div class="eeSettingsTile">
			<div class="eeColInline">';

			// If showing just-uploaded files
			if($eeSFL_Uploaded) {

				 $eeOutput .= '

				<a href="' . admin_url() . '?page=' . eeSFL_PluginSlug . '&eeListID=' . $eeSFL->eeListID . '&eeFolder=' . urlencode(rtrim($eeSFL->eeCurrentFolder, '/')) . '" class="button eeButton" id="eeSFL_BacktoFilesButton">&larr; ' . __('Back to the Files', 'simple-file-list') . '</a>';

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

				// Count Files and Folders
				if($eeSFLF) {
					$eeSFLF->eeSFLF_CountFilesAndFolders();
				} else {
					foreach($eeSFL->eeAllFiles as $eeFileArray) {
						if(strpos($eeFileArray['FilePath'], '.')) {
							$eeSFL->eeFileCount++;
						} else {
							$eeSFL->eeFolderCount++;
						}
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

					 $eeOutput .= $eeSFL->eeFileCount . ' ' . __('Files', 'simple-file-list') . ' &amp; ' . $eeSFL->eeFolderCount . ' ' . __('Folders', 'simple-file-list') .  ' - ' . __('Sorted by', 'simple-file-list') . ' ' . ucwords($eeSFL->eeListSettings['SortBy']);

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
			else { if($eeSFLA) {  $active_subtab = 'list_access'; } else { $active_subtab = 'list_settings'; } }

    	 $eeOutput .= '

    	<h2 class="nav-tab-wrapper">
    	<span class="ee-nav-sub-tabs">';

		// Extension Check
		if($eeSFLA) {
			if( $eeSFL->eeListSettings['MaxSize'] ) {
				 $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=list_access&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabAccess" class="nav-tab ';
				if($active_subtab == 'list_access') { $eeOutput .= '  eeActiveTab ';}
			    $active_subtab == 'list_access' ? 'nav-tab-active' : '';
			     $eeOutput .= $active_subtab . '">' . __('List Access Settings', 'simple-file-list') . '</a>';
			} else {
				$eeSFLA = FALSE;
			}
		}

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

	    // Extension Settings
		if(defined('eeSFL_Pro')) {
			 $eeOutput .= '<a href="?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=extension_settings&eeListID=' . $eeSFL->eeListID . '" id="eeSFL_TabExts" class="nav-tab ';
			if($active_subtab == 'extension_settings') { $eeOutput .= '  eeActiveTab ';}
			$active_subtab == 'extension_settings' ? 'nav-tab-active' : '';
			 $eeOutput .= $active_subtab . '">' . __('Extension Settings', 'simple-file-list') . '</a>';
		}

	     $eeOutput .= '

	    </span>
	    </h2>

	    <section class="eeSFL_Settings">';

		if($eeSFLA AND $active_subtab == 'list_access') { // Extension Check

			include(WP_PLUGIN_DIR . '/ee-simple-file-list-access/includes/eeSFLA_ListAccessSettingsDisplay.php');

		} elseif($active_subtab == 'uploader_settings') {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-upload-settings.php'); // The Uploader Settings

		} elseif($active_subtab == 'email_settings') {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-email-settings.php'); // The Notifications Settings

		} elseif(defined('eeSFL_Pro') AND $active_subtab == 'extension_settings') {

			include($eeSFL->eeEnvironment['pluginDir'] . 'pro/ee-extension-settings.php'); // Extension Settings

		}  else {

			include($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-list-settings.php'); // The File List Settings
		}

		 $eeOutput .= '

		</section>';

	} elseif($active_tab == 'getpro') { // Get Pro Version Tab

		 $eeOutput .= '
		<section class="eeSFL_Settings" style="background-color:#f9f9f9; color:#1d2327;">
		<div style="max-width:860px; margin:0 auto; padding:10px 0 40px;">

			<div style="text-align:center; padding:30px 20px 20px;">
				<h2 style="font-size:28px; margin-bottom:10px;">' . __('Upgrade to Simple File List Pro', 'simple-file-list') . '</h2>
				<p style="font-size:16px; color:#555; margin-bottom:24px;">' . __('Unlock powerful Pro-only features and optional extensions with a single one-time purchase.', 'simple-file-list') . '</p>
				<a href="https://get.simplefilelist.com" target="_blank" class="button button-primary" style="font-size:18px; padding:14px 44px; height:auto; line-height:1.5; border-radius:4px;">' . __('Get Simple File List Pro &rarr;', 'simple-file-list') . '</a>
				<p style="margin-top:12px;"><small><a href="https://demo.simplefilelist.com" target="_blank">' . __('View the Full Demo', 'simple-file-list') . ' &rarr;</a></small></p>
			</div>

			<hr style="margin:30px 0;" />

			<h3 style="text-align:center; font-size:18px; margin-bottom:20px;">' . __('Pro Features', 'simple-file-list') . '</h3>

			<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:0 10px;">

				<div class="eeSettingsTile">
					<h3>&#128193; ' . __('Sub-Folder Creation', 'simple-file-list') . '</h3>
					<p>' . __('Create and manage sub-folders directly within your file list to keep files organized however you need.', 'simple-file-list') . '</p>
				</div>

				<div class="eeSettingsTile">
					<h3>&#128190; ' . __('Folder Downloads', 'simple-file-list') . '</h3>
					<p>' . __('Let users download an entire folder as a single ZIP archive with one click.', 'simple-file-list') . '</p>
				</div>

				<div class="eeSettingsTile">
					<h3>&#128337; ' . __('List Re-Scan Interval', 'simple-file-list') . '</h3>
					<p>' . __('Control how often the file list re-scans the disk &mdash; on every page load, on a schedule, or only when you manually trigger it. Essential for large lists.', 'simple-file-list') . '</p>
				</div>

				<div class="eeSettingsTile">
					<h3>&#128200; ' . __('Admin Dashboard Widget', 'simple-file-list') . '</h3>
					<p>' . __('A dashboard widget shows the most recently added files with a direct link to jump straight to that location in the file list &mdash; so you always know what\'s new.', 'simple-file-list') . '</p>
				</div>

			</div>

			<hr style="margin:30px 0;" />

			<h3 style="text-align:center; font-size:18px; margin-bottom:20px;">' . __('Pro Extensions', 'simple-file-list') . '</h3>

			<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:0 10px;">

				<div class="eeSettingsTile">
					<h3>&#128196; ' . __('File Access Manager', 'simple-file-list') . '</h3>
					<p>' . __('Create additional file lists, each with independent settings and directories. Restrict access by WordPress user or user role &mdash; ideal for client portals, intranets and member sites.', 'simple-file-list') . '</p>
					<p><a href="https://simplefilelist.com/file-access-manager/" target="_blank">' . __('Learn More', 'simple-file-list') . ' &rarr;</a></p>
				</div>

				<div class="eeSettingsTile">
					<h3>&#128269; ' . __('Search &amp; Pagination', 'simple-file-list') . '</h3>
					<p>' . __('Add a search form so visitors can search file names, descriptions and date ranges. Pagination breaks long file lists into manageable pages.', 'simple-file-list') . '</p>
					<p><a href="https://simplefilelist.com/add-search-pagination/" target="_blank">' . __('Learn More', 'simple-file-list') . ' &rarr;</a></p>
				</div>

			</div>

			<hr style="margin:30px 0;" />

			<div style="text-align:center; padding:10px 20px 30px;">
				<p style="font-size:15px; color:#555; margin-bottom:20px;">' . __('One-time purchase. Includes all future updates. Registered to your domain.', 'simple-file-list') . '</p>
				<a href="https://get.simplefilelist.com" target="_blank" class="button button-primary" style="font-size:18px; padding:14px 44px; height:auto; line-height:1.5; border-radius:4px;">' . __('Get Simple File List Pro &rarr;', 'simple-file-list') . '</a>
			</div>

		</div>
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