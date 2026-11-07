<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Check for POST, Capability and Nonce
if(current_user_can('manage_options') && isset($_POST['eePost']) && sanitize_text_field(wp_unslash($_POST['eePost'])) && check_admin_referer( 'avpvh-filelist-settings', 'avpvh-filelist-settings-nonce')) {

	avpvh_filelist_debug_log("Updating the List Settings", 'General');

	// List Title
	if(isset($_POST['eeListTitle'])) {
		$eeString = sanitize_text_field(wp_unslash($_POST['eeListTitle']));
		if($eeString) { $eeSFL->eeListSettings['ListTitle'] = $eeString; }
	}


	if( isset($_POST['eeFileListDir']) ) {

		if( $_POST['eeFileListDir'] != $eeSFL->eeListSettings['FileListDir'] ) {

			$old_directory = $eeSFL->eeListSettings['FileListDir'];
			$new_directory = sanitize_text_field(wp_unslash($_POST['eeFileListDir']));
			$user_id = get_current_user_id();

			avpvh_filelist_debug_log("SETTINGS CHANGE: FileListDir changed from '$old_directory' to '$new_directory' by user $user_id", 'Settings', $eeSFL->eeListID);

			$eeFileListDir = avpvh_filelist_validate_file_list_dir($new_directory); // Sanitize and Validate the Path

			$eeOverlap = $eeFileListDir ? avpvh_filelist_find_overlapping_list($eeFileListDir, $eeSFL->eeListID) : 0;

			if($eeOverlap) {

				/* translators: %d: ID of the other file list */
				$eeSFL->eeUserMessages['warnings'][] = sprintf(__('Not saved: this folder overlaps the folder of list %d. Each list needs its own folder.', 'avpvh-filelist'), $eeOverlap);

			} elseif($eeFileListDir) {

				if( avpvh_filelist_file_list_dir_check($eeFileListDir) ) { // Check / Create the Dir

					$eeSFL->eeListSettings['FileListDir'] = $eeFileListDir;
					avpvh_filelist_debug_log("Directory change successful: new path validated and created", 'Settings', $eeSFL->eeListID);

				} else {

					$eeSFL->eeUserMessages['warnings'][] = __('Cannot create the file directory. The folder was not changed.', 'avpvh-filelist');
				}

			} else {

				$eeSFL->eeUserMessages['warnings'][] = __('Not Saved', 'avpvh-filelist');
				$eeSFL->eeUserMessages['warnings'][] = __('Choose a different file list directory.', 'avpvh-filelist');
			}
		}


		// Who may see the list when "Show To" is "Specific users, roles or groups"
		list($eeListGrant, $eeGrantErrors) = avpvh_filelist_grant_from_post('eeListAccess');
		$eeSFL->eeListSettings['ListAccess'] = $eeListGrant;
		foreach( $eeGrantErrors as $eeError ) { $eeSFL->eeUserMessages['warnings'][] = $eeError; }

		// Serve files through the download handler (set before avpvh_filelist_limit_dir_access() below)
		$eeSFL->eeListSettings['ProtectFiles'] = avpvh_filelist_process_checkbox_input('ProtectFiles');

		if(isset($_POST['eeShowList'])) {

			$eeShowList = sanitize_text_field(wp_unslash($_POST['eeShowList']));

			if($eeShowList == 'YES') { $eeSFL->eeListSettings['ShowList'] = 'YES'; }
				elseif($eeShowList == 'USER') { $eeSFL->eeListSettings['ShowList'] = 'USER'; } // Show only to logged in users
				 elseif($eeShowList == 'GRANTED') { $eeSFL->eeListSettings['ShowList'] = 'GRANTED'; } // Show only to the users, roles and groups in ListAccess
				  elseif($eeShowList == 'ADMIN') { $eeSFL->eeListSettings['ShowList'] = 'ADMIN'; } // Show only to logged in Admins
					else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }

			if( $eeSFL->eeListSettings['ShowList'] == 'GRANTED' && avpvh_filelist_grant_is_empty($eeListGrant) ) {
				$eeSFL->eeUserMessages['warnings'][] = __('No users, roles or groups are chosen, so only the people who manage this list can see it.', 'avpvh-filelist');
			}

			avpvh_filelist_limit_dir_access($eeSFL->eeListSettings['ShowList']);
		}

		if(isset($_POST['eeAdminRole'])) {

			if($_POST['eeAdminRole'] == '1') { $eeSFL->eeListSettings['AdminRole'] = '1'; }
				elseif($_POST['eeAdminRole'] == '3') { $eeSFL->eeListSettings['AdminRole'] = '3'; }
					elseif($_POST['eeAdminRole'] == '4') { $eeSFL->eeListSettings['AdminRole'] = '4'; }
						elseif($_POST['eeAdminRole'] == '5') { $eeSFL->eeListSettings['AdminRole'] = '5'; }
								else { $eeSFL->eeListSettings['AdminRole'] = '2'; } // Default to Contributors
		}
	}


	// YES/NO Checkboxes
	$eeCheckboxes = array(

		'ShowFileThumb'
		,'ShowFileDate'
		,'ShowFileSize'
		,'ShowFileOpen'
		,'ShowFileDownload'
		,'ShowFileCopyLink'
		,'ShowFileDesc'
		,'ShowHeader'
		,'SmoothScroll'
		,'ShowSubmitterInfo'
		,'ShowSubmitterEmail'
		,'PreserveName'
		,'ShowFileExtension'
		,'GenerateImgThumbs'
		,'GeneratePDFThumbs'
		,'GenerateVideoThumbs'
			,'AllowBulkFileDownload'
		,'AudioEnabled'
		,'ShowSearch'
	);

	$eeCheckboxes[] = 'AllowFrontManage';

	foreach( $eeCheckboxes as $eeTerm ) { // "ee" is added in the function
		$eeSFL->eeListSettings[$eeTerm] = avpvh_filelist_process_checkbox_input($eeTerm);
	}

	$eeTextInputs = array(
		'LabelThumb'
		,'LabelName'
		,'LabelDate'
		,'LabelSize'
		,'LabelDesc'
		,'LabelOwner'
		,'ShowListStyle'
		,'ShowListTheme'
		,'SortBy'
		,'ShowFileDateAs'
		,'AudioHeight'
	);

	foreach( $eeTextInputs as $eeTerm ) {
		$eeSFL->eeListSettings[$eeTerm] = avpvh_filelist_process_text_input($eeTerm);
	}

	// Files per page: 0 shows everything on one page
	$eeSFL->eeListSettings['FilesPerPage'] = min(500, absint(avpvh_filelist_process_text_input('FilesPerPage')));


	if(!empty($_POST['eeSortOrder'])) {
		$eeSFL->eeListSettings['SortOrder'] = 'Descending';
	} else {
		$eeSFL->eeListSettings['SortOrder'] = 'Ascending';
	}



	// echo '<pre>'; print_r($eeSFL->eeUserMessages); echo '</pre>'; exit;

	// Update DB
	if( empty($eeSFL->eeUserMessages['errors']) AND empty($eeSFL->eeUserMessages['warnings']) ) {

		// Sort for Sanity
		ksort($eeSFL->eeListSettings);

		update_option('avpvh_filelist_settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings);

		$eeSFL->update_file_list_array(); // Re-Populate $eeSFL->eeAllFiles

		$eeSFL->eeUserMessages['messages'][] = __('List Settings Saved', 'avpvh-filelist');

	}
}

// Settings Display =========================================

avpvh_filelist_debug_log("Loading: List Settings", 'General');

// User Messaging
 $eeOutput .= $eeSFL->results_notification();

// Begin the Form
 $eeOutput .= '

<form action="' . $eeSFL->get_this_url() . '" method="post" id="eeSFL_Settings">
<input type="hidden" name="eePost" value="TRUE" />
<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />';

 $eeOutput .= wp_nonce_field( 'avpvh-filelist-settings', 'avpvh-filelist-settings-nonce', TRUE, FALSE);

 $eeOutput .= '<div class="eeColInline eeSettingsTile">

	<div class="eeColHalfLeft">

		<h1>' . __('File List Settings', 'avpvh-filelist') . '</h1>

	</div>

	<div class="eeColHalfRight">

		<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

	</div>

</div>

<div class="eeColFull eeSettingsTile">

	<h2>' . __('List Location', 'avpvh-filelist') . '</h2>

		<fieldset>

		<p><label class="eeBlock" for="eeListTitle">' . __('File List Name', 'avpvh-filelist') . '</label>
		<input type="text" name="eeListTitle" value="' . stripslashes($eeSFL->eeListSettings['ListTitle']) . '" class="eeFullWidth" id="eeListTitle" /></p>
		<div class="eeNote">' . __('The name of this file list.', 'avpvh-filelist') . '</div>

		</fieldset>
		<fieldset>

		<p><label class="eeBlock" for="eeFileListDir">' . __('File List Directory', 'avpvh-filelist') . '</label>';


		 $eeOutput .= '

		<input class="eeFullWidth" type="text" name="eeFileListDir" value="' . $eeSFL->eeListSettings['FileListDir'] . '" id="eeFileListDir" /></p>
		<div class="eeNote">' . __('This must be relative to your Wordpress home folder.', 'avpvh-filelist') . '<br />
			* ' . __('Default Location', 'avpvh-filelist') . ': <em>wp-content/uploads/avpvh-filelist/</em><br />
			* ' . __('The directory you enter will be created if it does not exist.', 'avpvh-filelist') . '<br />
			* ' . __('If you change this later, information such as file descriptions will be lost.', 'avpvh-filelist') . '
		</div>';

 $eeOutput .= '

</fieldset>

</div>


<div class="eeColumns">

	<!-- Left Column -->

	<div class="eeColLeft">

		<div class="eeSettingsTile">

		<h2>' . __('File List Access', 'avpvh-filelist') . '</h2>';


		 $eeOutput .= '<fieldset>

		<legend>' . __('Front-End Display', 'avpvh-filelist') . '</legend>

		<div><label for="eeShowList">' . __('Show To', 'avpvh-filelist') . '</label>

		<select name="eeShowList" id="eeShowList">

			<option value="YES"';

			if($eeSFL->eeListSettings['ShowList'] == 'YES') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Everyone', 'avpvh-filelist') . '</option>

			<option value="USER"';

			if($eeSFL->eeListSettings['ShowList'] == 'USER') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Only Logged in Users', 'avpvh-filelist') . '</option>

			<option value="GRANTED"';

			if($eeSFL->eeListSettings['ShowList'] == 'GRANTED') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Specific Users, Roles or Groups', 'avpvh-filelist') . '</option>

			<option value="ADMIN"';

			if($eeSFL->eeListSettings['ShowList'] == 'ADMIN') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Only Logged in Admins', 'avpvh-filelist') . '</option>

			<option value="NO"';

			if($eeSFL->eeListSettings['ShowList'] == 'NO') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Hide Completely', 'avpvh-filelist') . '</option>

		</select></div>
		<div class="eeNote">' . __('Determine who you will show the front-side list to.', 'avpvh-filelist') . '</div>

		<div id="eeSFL_ListAccessPicker"' . ($eeSFL->eeListSettings['ShowList'] == 'GRANTED' ? '' : ' style="display:none"') . '>'
			. avpvh_filelist_grant_picker('eeListAccess', $eeSFL->eeListSettings['ListAccess'] ?? array()) . '
		</div>
		<script>
		document.getElementById("eeShowList").addEventListener("change", function() {
			document.getElementById("eeSFL_ListAccessPicker").style.display = this.value === "GRANTED" ? "" : "none";
		});
		</script>

		</fieldset>
		<fieldset>

		<legend>' . __('Protect Files', 'avpvh-filelist') . '</legend>

		<div><label for="eeProtectFiles">' . __('Protect', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeProtectFiles" value="YES" id="eeProtectFiles"' . (avpvh_filelist_list_is_protected($eeSFL->eeListSettings) ? ' checked="checked"' : '') . ' /></div>

		<div class="eeNote">' . __('Serve files through WordPress, which checks on every download who may open the file. Needed for "Show To" and per-file access to protect the files themselves, not just the list. The folder must then be blocked for direct access: done automatically on Apache, the settings page shows what to add for nginx.', 'avpvh-filelist') . '</div>

		</fieldset>
		<fieldset>

		<legend>' . __('Back-End Access', 'avpvh-filelist') . '</legend>

		<div><label for="eeAdminRole">' . __('Choose Role', 'avpvh-filelist') . '</label>

		<select name="eeAdminRole" id="eeAdminRole">

			<option value="1"'; // 1

			if($eeSFL->eeListSettings['AdminRole'] == '1') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Subscribers and Above', 'avpvh-filelist') . '</option>


			<option value="2"'; // 2

			if($eeSFL->eeListSettings['AdminRole'] == '2') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Contributers and Above', 'avpvh-filelist') . '</option>


			<option value="3"'; // 3

			if($eeSFL->eeListSettings['AdminRole'] == '3') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Authors and Above', 'avpvh-filelist') . '</option>


			<option value="4"'; // 4

			if($eeSFL->eeListSettings['AdminRole'] == '4') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Editors and Above', 'avpvh-filelist') . '</option>


			<option value="5"'; // 5

			if($eeSFL->eeListSettings['AdminRole'] == '5') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Admins Only', 'avpvh-filelist') . '</option>

		</select></div>

		<div class="eeNote">' . __('Determine who can access the back-side settings.', 'avpvh-filelist') . '</div>

		</fieldset>

		<fieldset>

		<legend>' . __('Front-End Management', 'avpvh-filelist') . '</legend>

		<div><label for="eeAllowFrontManage">' . __('Allow', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeAllowFrontManage" value="YES" id="eeAllowFrontManage"';

		if( $eeSFL->eeListSettings['AllowFrontManage'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Allow file deletion, file renaming, editing descriptions and dates.', 'avpvh-filelist') . '</div>

		<div id="eeAllowFrontManageWarning" class="eeNote" style="color:#b32d2e;font-weight:bold;' . ($eeSFL->eeListSettings['AllowFrontManage'] == 'YES' ? '' : 'display:none;') . '">&#9888; ' . __('Warning: Front-End Management is enabled. Any visitor who can access this page will be able to delete, rename, and edit files. Restrict access to this page using WordPress page protection or a members-only plugin.', 'avpvh-filelist') . '</div>

		<script>
		document.getElementById("eeAllowFrontManage").addEventListener("change", function() {
			document.getElementById("eeAllowFrontManageWarning").style.display = this.checked ? "" : "none";
		});
		</script>

		</fieldset>';





		 $eeOutput .= '</div>

		<div class="eeSettingsTile">

		<h2>' . __('File List Style', 'avpvh-filelist') . '</h2>


		<fieldset>
		<legend>File List Type</legend>

		<p><label for="eeShowListStyle">' . __('Style', 'avpvh-filelist') . '</label>

		<select name="eeShowListStyle" id="eeShowListStyle">

			<option value="TABLE"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'TABLE') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Standard Table Display', 'avpvh-filelist') . '</option>

			<option value="TILES"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'TILES') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Tiles Displayed in Columns', 'avpvh-filelist') . '</option>

			<option value="FLEX"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'FLEX') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Flexible List Display', 'avpvh-filelist') . '</option>

		</select></p>
		<div class="eeNote">' . __('Choose the style of the file list: Table, Tiles or Flex.', 'avpvh-filelist') . '</div>

		</fieldset>



		<fieldset>
		<legend>File List Theme</legend>

		<p><label for="eeShowListTheme">' . __('Show', 'avpvh-filelist') . '</label>

		<select name="eeShowListTheme" id="eeShowListTheme">

			<option value="LIGHT"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'LIGHT') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Light Theme', 'avpvh-filelist') . '</option>

			<option value="DARK"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'DARK') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Dark Theme', 'avpvh-filelist') . '</option>

			<option value="NONE"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'NONE') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('No Theme', 'avpvh-filelist') . '</option>

		</select></p>
		<div class="eeNote">' . __('Choose the color theme of the file list', 'avpvh-filelist') . ': Light, Dark, or None.'  . __('This will rely upon your theme colors', 'avpvh-filelist') . '</div>

		</fieldset>


		</div>






		<div class="eeSettingsTile">

		<h2>' . __('File Sorting and Order', 'avpvh-filelist') . '</h2>

		<p><label for="eeSortList">' . __('Sort By', 'avpvh-filelist') . ':</label>

		<select name="eeSortBy" id="eeSortList">

			<option value="Name"';

			if($eeSFL->eeListSettings['SortBy'] == 'Name') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('File Name', 'avpvh-filelist') . '</option>


			<option value="Added"';

			if($eeSFL->eeListSettings['SortBy'] == 'Added') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Date File Added', 'avpvh-filelist') . '</option>


			<option value="Changed"';

			if($eeSFL->eeListSettings['SortBy'] == 'Changed') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Date File Changed', 'avpvh-filelist') . '</option>


			<option value="Size"';

			if($eeSFL->eeListSettings['SortBy'] == 'Size') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('File Size', 'avpvh-filelist') . '</option>


			<option value="Random"';

			if($eeSFL->eeListSettings['SortBy'] == 'Random') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Random', 'avpvh-filelist') . '</option>

		</select></p>

		<p><label for="eeSortOrder">' . __('Reverse Order', 'avpvh-filelist') . ':</label>
		<input type="checkbox" name="eeSortOrder" value="Descending" id="eeSortOrder"';

		if( $eeSFL->eeListSettings['SortOrder'] == 'Descending') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /> &darr; ' . __('Descending', 'avpvh-filelist') . '</p>

		<div class="eeNote">' . __('Sort the list by name, date, file size, or randomly.', 'avpvh-filelist') . ' ' . __('Check the box to reverse the sort order.', 'avpvh-filelist') . '</div>

		</div>

		<div class="eeSettingsTile">

		<h2>' . __('Search and Pages', 'avpvh-filelist') . '</h2>

		<p><label for="eeShowSearch">' . __('Search', 'avpvh-filelist') . ':</label>
		<input type="checkbox" name="eeShowSearch" value="YES" id="eeShowSearch"' . (($eeSFL->eeListSettings['ShowSearch'] ?? 'NO') == 'YES' ? ' checked="checked"' : '') . ' /> ' . __('Show a search form above the list', 'avpvh-filelist') . '</p>

		<p><label for="eeFilesPerPage">' . __('Files Per Page', 'avpvh-filelist') . ':</label>
		<input type="number" min="0" max="500" step="1" name="eeFilesPerPage" value="' . (int) ($eeSFL->eeListSettings['FilesPerPage'] ?? 0) . '" id="eeFilesPerPage" /></p>

		<div class="eeNote">' . __('Search looks in file names, nice names and descriptions; every word must match. It is always available in wp-admin.', 'avpvh-filelist') . ' ' . __('Set the number of files per page to 0 to show all files on one page.', 'avpvh-filelist') . ' ' . __('Shortcode: search="YES", filecount="25", paged="NO".', 'avpvh-filelist') . '</div>

		</div>';


		 $eeOutput .= '<div class="eeSettingsTile">

		<h2>' . __('Thumbnail Generation', 'avpvh-filelist') . '</h2>

		<p>' . __('You can choose to generate small representative images of large images, PDF files and videos files.', 'avpvh-filelist') . '</p>

		<fieldset>

		<p><label for="eeGenerateImgThumbs">' . __('Image Thumbnails', 'avpvh-filelist') . ':</label>

		<input id="eeGenerateImgThumbs" type="checkbox" name="eeGenerateImgThumbs" value="YES"';

		$eeSupported = get_option('avpvh_filelist_supported');
		if( !is_array($eeSupported) ) { $eeSupported = array(); }
		$eeMissing = array();

		if( $eeSFL->eeListSettings['GenerateImgThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' />
			<div class="eeNote">' . __('Read an image file and create a small thumbnail image.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>

		<p><label for="eeGeneratePDFThumbs">' . __('PDF Thumbnails', 'avpvh-filelist') . ':</label>

		<input id="eeGeneratePDFThumbs" type="checkbox" name="eeGeneratePDFThumbs" value="YES"';
		if( $eeSFL->eeListSettings['GeneratePDFThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }
	if( !isset($eeSFL->eeEnvironment['GhostScript']) OR $eeSFL->eeEnvironment['eeOS'] == 'WINDOWS' ) {  $eeOutput .= ' disabled="disabled"'; }
	 $eeOutput .= ' /> ';

	if( !in_array('GhostScript' , $eeSupported) ) {
		$eeMissing[] = 'GhostScript is Not Installed. PDF thumbnails cannot be created.';
	}

		if( $eeSFL->eeEnvironment['eeOS'] == 'WINDOWS' ) {
			$eeMissing[] .= ' <em>Windows: ' . __('Not yet supported for PDF thumbnails.', 'avpvh-filelist') . '</em>';
		}

		 $eeOutput .= '</p>
		<div class="eeNote">' . __('Read a PDF file and create a representative thumbnail image based on the first page.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>

		<p><label for="eeGenerateVideoThumbs">' . __('Video Thumbnails', 'avpvh-filelist') . ':</label>

		<input id="eeGenerateVideoThumbs" type="checkbox" name="eeGenerateVideoThumbs" value="YES"';
		if( $eeSFL->eeListSettings['GenerateVideoThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }
		if( !isset($eeSFL->eeEnvironment['ffMpeg']) ) {  $eeOutput .= ' disabled="disabled"'; }
		 $eeOutput .= ' /> ';

		if( !isset($eeSFL->eeEnvironment['ffMpeg']) ) {
			$eeMissing[] = __('Video thumbnails will not be created because ffMpeg is not Installed.', 'avpvh-filelist');
		}

		 $eeOutput .= '</p>

		<div class="eeNote">' . __('Read a video file and create a representative thumbnail image at the 1 second mark.', 'avpvh-filelist') . '</div>';

		if(count($eeMissing)) {

			 $eeOutput .= '
			<br />
			<div class="eeNote">';

			foreach( $eeMissing as $eeKey => $eeValue) {
				 $eeOutput .= '<small>&rarr; ' . $eeValue . '</small><br />';
			}

			 $eeOutput .= '</div>';
		}

		 $eeOutput .= '</fieldset>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Smooth-Scroll', 'avpvh-filelist') . '</h2>

		<p><label for="eeSmoothScroll">' . __('Use Smooth-Scroll', 'avpvh-filelist') . ':</label>
		<input type="checkbox" name="eeSmoothScroll" value="YES" id="eeSmoothScroll"';

		if( $eeSFL->eeListSettings['SmoothScroll'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></p>

		<div class="eeNote">' . __('Uses a JavaScript effect to scroll down to the top of the list after an action. This can be helpful if the list is not located close to the top of the page.', 'avpvh-filelist') . '</div>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Media Player', 'avpvh-filelist') . '</h2>

		<fieldset>

			<legend>' . __('Enable In-Line Audio Player', 'avpvh-filelist') . '</legend>

			<div>
			<label for="eeAudioEnabled">' . __('Enabled', 'avpvh-filelist') . ':</label>
			<input type="checkbox" name="eeAudioEnabled" id="eeAudioEnabled" value="YES" ';

			if($eeSFL->eeListSettings['AudioEnabled'] == 'YES') {  $eeOutput .= 'checked="checked"'; }

			 $eeOutput .= ' />
			</div>

			<div class="eeNote">' . __('Show the audio player beneath the file name', 'avpvh-filelist') . '</div>


		</fieldset>

		<fieldset>

			<legend>' . __('Choose the Audio Player Height', 'avpvh-filelist') . '</legend>

			<div><label for="eeAudioHeight">' . __('Height', 'avpvh-filelist') . ':</label>
			<input type="number" name="eeAudioHeight" id="eeAudioHeight" value="' . $eeSFL->eeListSettings['AudioHeight'] . '" />
			</div>

			<div class="eeNote">' . __('Define the height of the audio player in pixels.', 'avpvh-filelist') . ' ' . __('Set to zero to ignore this value.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>


	</div>






	<!-- Right Column -->

	<div class="eeColRight">';

	$eeOutput .= '

		<div class="eeSettingsTile">

		<h2>' . __('File Actions', 'avpvh-filelist') . '</h2>


		<fieldset>
		<legend>' . __('Show Open Action', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Action', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeShowFileOpen" value="YES" id="eeShowFileOpen"';

		if( $eeSFL->eeListSettings['ShowFileOpen'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Display the Open File link. If the browser cannot open the file, it will prompt the user to download.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Show Download Action', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Action', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeShowFileDownload" value="YES" id="eeShowFileDownload"';

		if( $eeSFL->eeListSettings['ShowFileDownload'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('The browser will prompt the user to download the file.', 'avpvh-filelist') . '</div>

		</fieldset>



		<fieldset>
		<legend>' . __('Show Copy Action', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Action', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeShowFileCopyLink" value="YES" id="eeShowFileCopyLink"';

		if( $eeSFL->eeListSettings['ShowFileCopyLink'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Copies the file URL to the user clipboard.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Bulk File Download', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Allow', 'avpvh-filelist') . '</label>
		<input type="checkbox" name="eeAllowBulkFileDownload" value="YES" id="eeAllowBulkFileDownload"';

		if( !empty($eeSFL->eeListSettings['AllowBulkFileDownload']) && $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') { $eeOutput .= ' checked="checked"'; }

		$eeOutput .= ' /></div>

		<div class="eeNote">' . __('Allow users to select and download multiple files as a ZIP archive.', 'avpvh-filelist') . '</div>

		</fieldset>


		</div>


		<div class="eeSettingsTile">

		<h2>' . __('File List Display', 'avpvh-filelist') . '</h2>

		<fieldset>
		<legend>' . __('File Thumbnail', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowFileThumb" value="YES" id="eeShowFileThumb"';
		if($eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelThumb" value="';
		if( isset($eeSFL->eeListSettings['LabelThumb']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelThumb']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show file thumbnail images.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Name', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input checked disabled type="checkbox" name="eeShowFileName" value="YES" id="eeShowFileName" />
		<input type="text" name="eeLabelName" value="';
		if( isset($eeSFL->eeListSettings['LabelName']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelName']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show file name. This cannot be disabled.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Date', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowFileDate" value="YES" id="eeShowFileDate"';
		if($eeSFL->eeListSettings['ShowFileDate'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input class="eeFortyPercent" type="text" name="eeLabelDate" value="';
		if( isset($eeSFL->eeListSettings['LabelDate'])) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelDate']); }
		 $eeOutput .= '" />

		<select name="eeShowFileDateAs" id="eeShowFileDateAs">
			<option value="">' . __('Date Type', 'avpvh-filelist') . '</option>

			<option value="Added"';
			if($eeSFL->eeListSettings['ShowFileDateAs'] == 'Added') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Added', 'avpvh-filelist') . '</option>

			<option value="Changed"';
			if($eeSFL->eeListSettings['ShowFileDateAs'] == 'Changed') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Changed', 'avpvh-filelist') . '</option>
		</select></div>

		<div class="eeNote">Show the file date, either last changed or when added to the list.</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Size', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowFileSize" value="YES" id="eeShowFileSize"';
		if($eeSFL->eeListSettings['ShowFileSize'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelSize" value="';
		if( isset($eeSFL->eeListSettings['LabelSize']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelSize']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Limit the file information to display on the front-side file list. Enter a custom label if needed.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Description', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowFileDesc" value="YES" id="eeShowFileDesc"';
		if($eeSFL->eeListSettings['ShowFileDesc'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelDesc" value="';
		if( isset($eeSFL->eeListSettings['LabelDesc']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelDesc']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show a description of the file, which can include keywords and special characters not allowed within the file name.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Submitter', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowSubmitterInfo" value="YES" id="eeShowSubmitterInfo"';
		if($eeSFL->eeListSettings['ShowSubmitterInfo'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelOwner" value="';

		// echo '<pre>'; print_r($eeSFL->eeDefaultListSettings); echo '</pre>'; exit;

		if( $eeSFL->eeListSettings['LabelOwner'] ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelOwner']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show the name of the user who uploaded the file on the front-end.', 'avpvh-filelist') . '</div>

		<div><label>' . __('Show Email Link', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowSubmitterEmail" value="YES" id="eeShowSubmitterEmail"';
		if($eeSFL->eeListSettings['ShowSubmitterEmail'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show the submitter\'s email address as a clickable link.', 'avpvh-filelist') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Table Header', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowHeader" value="YES" id="eeShowHeader"';
		if($eeSFL->eeListSettings['ShowHeader'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show or hide the file table header.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Extension', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eeShowFileExtension" value="YES" id="eeShowFileExtension"';
		if($eeSFL->eeListSettings['ShowFileExtension'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show or hide the file extension.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Preserve File Name', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show', 'avpvh-filelist') . '</label><input type="checkbox" name="eePreserveName" value="YES" id="eePreserveName"';
		if($eeSFL->eeListSettings['PreserveName'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Files with illegal characters are renamed to ensure good URLs.', 'avpvh-filelist') . ' ' .
			__('This setting will preserve and show the original name as the Nice Name.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>

	</div>

</div>


<div class="eeColInline eeSettingsTile">

	<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

</div>

</form>';

?>