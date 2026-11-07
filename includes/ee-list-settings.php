<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


// Check for POST, Capability and Nonce
if(current_user_can('manage_options') && isset($_POST['eePost']) && sanitize_text_field(wp_unslash($_POST['eePost'])) && check_admin_referer( 'ee-simple-file-list-settings', 'ee-simple-file-list-settings-nonce')) {

	eeSFL_Debug_Log("Updating the List Settings", 'General');

	// List Title
	if(isset($_POST['eeListTitle'])) {
		$eeString = sanitize_text_field(wp_unslash($_POST['eeListTitle']));
		if($eeString) { $eeSFL->eeListSettings['ListTitle'] = $eeString; }
	}


	if($eeSFLA) {

		include(WP_PLUGIN_DIR . '/ee-simple-file-list-access/includes/eeSFLA_ListSettingsProcess.php');

	} elseif( isset($_POST['eeFileListDir']) ) {

		if( $_POST['eeFileListDir'] != $eeSFL->eeListSettings['FileListDir'] ) {

			$old_directory = $eeSFL->eeListSettings['FileListDir'];
			$new_directory = sanitize_text_field(wp_unslash($_POST['eeFileListDir']));
			$user_id = get_current_user_id();

			eeSFL_Debug_Log("SETTINGS CHANGE: FileListDir changed from '$old_directory' to '$new_directory' by user $user_id", 'Settings', $eeSFL->eeListID);

			$eeFileListDir = eeSFL_ValidateFileListDir($new_directory); // Sanitize and Validate the Path

			$eeOverlap = $eeFileListDir ? eeSFL_FindOverlappingList($eeFileListDir, $eeSFL->eeListID) : 0;

			if($eeOverlap) {

				/* translators: %d: ID of the other file list */
				$eeSFL->eeUserMessages['warnings'][] = sprintf(__('Not saved: this folder overlaps the folder of list %d. Each list needs its own folder.', 'simple-file-list'), $eeOverlap);

			} elseif($eeFileListDir) {

				if( eeSFL_FileListDirCheck($eeFileListDir) ) { // Check / Create the Dir

					$eeSFL->eeListSettings['FileListDir'] = $eeFileListDir;
					eeSFL_Debug_Log("Directory change successful: new path validated and created", 'Settings', $eeSFL->eeListID);

				} else {

					$eeSFL->eeUserMessages['warnings'][] = __('Cannot create the file directory. The folder was not changed.', 'simple-file-list');
				}

			} else {

				$eeSFL->eeUserMessages['warnings'][] = __('Not Saved', 'simple-file-list');
				$eeSFL->eeUserMessages['warnings'][] = __('Choose a different file list directory.', 'simple-file-list');
			}
		}


		// Who may see the list when "Show To" is "Specific users, roles or groups"
		list($eeListGrant, $eeGrantErrors) = eeSFL_GrantFromPost('eeListAccess');
		$eeSFL->eeListSettings['ListAccess'] = $eeListGrant;
		foreach( $eeGrantErrors as $eeError ) { $eeSFL->eeUserMessages['warnings'][] = $eeError; }

		// Serve files through the download handler (set before eeSFL_LimitDirAccess() below)
		$eeSFL->eeListSettings['ProtectFiles'] = eeSFL_ProcessCheckboxInput('ProtectFiles');

		if(isset($_POST['eeShowList'])) {

			$eeShowList = sanitize_text_field(wp_unslash($_POST['eeShowList']));

			if($eeShowList == 'YES') { $eeSFL->eeListSettings['ShowList'] = 'YES'; }
				elseif($eeShowList == 'USER') { $eeSFL->eeListSettings['ShowList'] = 'USER'; } // Show only to logged in users
				 elseif($eeShowList == 'GRANTED') { $eeSFL->eeListSettings['ShowList'] = 'GRANTED'; } // Show only to the users, roles and groups in ListAccess
				  elseif($eeShowList == 'ADMIN') { $eeSFL->eeListSettings['ShowList'] = 'ADMIN'; } // Show only to logged in Admins
					else { $eeSFL->eeListSettings['ShowList'] = 'NO'; }

			if( $eeSFL->eeListSettings['ShowList'] == 'GRANTED' && eeSFL_GrantIsEmpty($eeListGrant) ) {
				$eeSFL->eeUserMessages['warnings'][] = __('No users, roles or groups are chosen, so only the people who manage this list can see it.', 'simple-file-list');
			}

			eeSFL_LimitDirAccess($eeSFL->eeListSettings['ShowList']);
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

	if(!$eeSFLA) { $eeCheckboxes[] = 'AllowFrontManage'; } // Otherwise this moves to the File Access Settings tab

	foreach( $eeCheckboxes as $eeTerm ) { // "ee" is added in the function
		$eeSFL->eeListSettings[$eeTerm] = eeSFL_ProcessCheckboxInput($eeTerm);
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
		$eeSFL->eeListSettings[$eeTerm] = eeSFL_ProcessTextInput($eeTerm);
	}

	// Files per page: 0 shows everything on one page
	$eeSFL->eeListSettings['FilesPerPage'] = min(500, absint(eeSFL_ProcessTextInput('FilesPerPage')));


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

		update_option('eeSFL_Settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings);

		$eeSFL->eeSFL_UpdateFileListArray(); // Re-Populate $eeSFL->eeAllFiles

		$eeSFL->eeUserMessages['messages'][] = __('List Settings Saved', 'simple-file-list');

	}
}

// Settings Display =========================================

eeSFL_Debug_Log("Loading: List Settings", 'General');

// User Messaging
 $eeOutput .= $eeSFL->eeSFL_ResultsNotification();

// Begin the Form
 $eeOutput .= '

<form action="' . $eeSFL->eeSFL_GetThisURL() . '" method="post" id="eeSFL_Settings">
<input type="hidden" name="eePost" value="TRUE" />
<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />';

 $eeOutput .= wp_nonce_field( 'ee-simple-file-list-settings', 'ee-simple-file-list-settings-nonce', TRUE, FALSE);

 $eeOutput .= '<div class="eeColInline eeSettingsTile">

	<div class="eeColHalfLeft">

		<h1>' . __('File List Settings', 'simple-file-list') . '</h1>

	</div>

	<div class="eeColHalfRight">

		<input class="button" type="submit" name="submit" value="' . __('SAVE', 'simple-file-list') . '" />

	</div>

</div>

<div class="eeColFull eeSettingsTile">

	<h2>' . __('List Location', 'simple-file-list') . '</h2>

		<fieldset>

		<p><label class="eeBlock" for="eeListTitle">' . __('File List Name', 'simple-file-list') . '</label>
		<input type="text" name="eeListTitle" value="' . stripslashes($eeSFL->eeListSettings['ListTitle']) . '" class="eeFullWidth" id="eeListTitle" /></p>
		<div class="eeNote">' . __('The name of this file list.', 'simple-file-list') . '</div>

		</fieldset>
		<fieldset>

		<p><label class="eeBlock" for="eeFileListDir">' . __('File List Directory', 'simple-file-list') . '</label>';

		if($eeSFLA) {

			 $eeOutput .= '

			<input class="eeFullWidth" disabled="disabled" type="text" name="eeFileListDirDisabled" value="' . $eeSFL->eeListSettings['FileListDir'] . '" id="eeFileListDir" />
			<input type="hidden" name="eeFileListDir" value="' . $eeSFL->eeListSettings['FileListDir'] . '" /></p>';

			if($eeSFL->eeListID == 1) {
				 $eeOutput .= '<div class="eeNote">' . __('To change this setting, deactivate the plugin', 'simple-file-list') . ' File Access Manager.</div>';
			} else {
				 $eeOutput .= '<div class="eeNote">' . __('This cannot be changed. Create a new list instead.', 'simple-file-list') . '</div>';
			}

		} else {

			 $eeOutput .= '

			<input class="eeFullWidth" type="text" name="eeFileListDir" value="' . $eeSFL->eeListSettings['FileListDir'] . '" id="eeFileListDir" /></p>
			<div class="eeNote">' . __('This must be relative to your Wordpress home folder.', 'simple-file-list') . '<br />
				* ' . __('Default Location', 'simple-file-list') . ': <em>wp-content/uploads/simple-file-list/</em><br />
				* ' . __('The directory you enter will be created if it does not exist.', 'simple-file-list') . '<br />
				* ' . __('If you change this later, information such as file descriptions will be lost.', 'simple-file-list') . '
			</div>';
		}

 $eeOutput .= '

</fieldset>

</div>


<div class="eeColumns">

	<!-- Left Column -->

	<div class="eeColLeft">

		<div class="eeSettingsTile">

		<h2>' . __('File List Access', 'simple-file-list') . '</h2>';

		// This moves to the File Access Settings tab if extension is installed
		if(!$eeSFLA)  {

			 $eeOutput .= '<fieldset>

			<legend>' . __('Front-End Display', 'simple-file-list') . '</legend>

			<div><label for="eeShowList">' . __('Show To', 'simple-file-list') . '</label>

			<select name="eeShowList" id="eeShowList">

				<option value="YES"';

				if($eeSFL->eeListSettings['ShowList'] == 'YES') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Everyone', 'simple-file-list') . '</option>

				<option value="USER"';

				if($eeSFL->eeListSettings['ShowList'] == 'USER') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Only Logged in Users', 'simple-file-list') . '</option>

				<option value="GRANTED"';

				if($eeSFL->eeListSettings['ShowList'] == 'GRANTED') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Specific Users, Roles or Groups', 'simple-file-list') . '</option>

				<option value="ADMIN"';

				if($eeSFL->eeListSettings['ShowList'] == 'ADMIN') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Only Logged in Admins', 'simple-file-list') . '</option>

				<option value="NO"';

				if($eeSFL->eeListSettings['ShowList'] == 'NO') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Hide Completely', 'simple-file-list') . '</option>

			</select></div>
			<div class="eeNote">' . __('Determine who you will show the front-side list to.', 'simple-file-list') . '</div>

			<div id="eeSFL_ListAccessPicker"' . ($eeSFL->eeListSettings['ShowList'] == 'GRANTED' ? '' : ' style="display:none"') . '>'
				. eeSFL_GrantPicker('eeListAccess', $eeSFL->eeListSettings['ListAccess'] ?? array()) . '
			</div>
			<script>
			document.getElementById("eeShowList").addEventListener("change", function() {
				document.getElementById("eeSFL_ListAccessPicker").style.display = this.value === "GRANTED" ? "" : "none";
			});
			</script>

			</fieldset>
			<fieldset>

			<legend>' . __('Protect Files', 'simple-file-list') . '</legend>

			<div><label for="eeProtectFiles">' . __('Protect', 'simple-file-list') . '</label>
			<input type="checkbox" name="eeProtectFiles" value="YES" id="eeProtectFiles"' . (eeSFL_ListIsProtected($eeSFL->eeListSettings) ? ' checked="checked"' : '') . ' /></div>

			<div class="eeNote">' . __('Serve files through WordPress, which checks on every download who may open the file. Needed for "Show To" and per-file access to protect the files themselves, not just the list. The folder must then be blocked for direct access: done automatically on Apache, the settings page shows what to add for nginx.', 'simple-file-list') . '</div>

			</fieldset>
			<fieldset>

			<legend>' . __('Back-End Access', 'simple-file-list') . '</legend>

			<div><label for="eeAdminRole">' . __('Choose Role', 'simple-file-list') . '</label>

			<select name="eeAdminRole" id="eeAdminRole">

				<option value="1"'; // 1

				if($eeSFL->eeListSettings['AdminRole'] == '1') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Subscribers and Above', 'simple-file-list') . '</option>


				<option value="2"'; // 2

				if($eeSFL->eeListSettings['AdminRole'] == '2') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Contributers and Above', 'simple-file-list') . '</option>


				<option value="3"'; // 3

				if($eeSFL->eeListSettings['AdminRole'] == '3') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Authors and Above', 'simple-file-list') . '</option>


				<option value="4"'; // 4

				if($eeSFL->eeListSettings['AdminRole'] == '4') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Editors and Above', 'simple-file-list') . '</option>


				<option value="5"'; // 5

				if($eeSFL->eeListSettings['AdminRole'] == '5') {  $eeOutput .= ' selected'; }

				 $eeOutput .= '>' . __('Admins Only', 'simple-file-list') . '</option>

			</select></div>

			<div class="eeNote">' . __('Determine who can access the back-side settings.', 'simple-file-list') . '</div>

			</fieldset>

			<fieldset>

			<legend>' . __('Front-End Management', 'simple-file-list') . '</legend>

			<div><label for="eeAllowFrontManage">' . __('Allow', 'simple-file-list') . '</label>
			<input type="checkbox" name="eeAllowFrontManage" value="YES" id="eeAllowFrontManage"';

			if( $eeSFL->eeListSettings['AllowFrontManage'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

			 $eeOutput .= ' /></div>

			<div class="eeNote">' . __('Allow file deletion, file renaming, editing descriptions and dates.', 'simple-file-list') . '</div>

			<div id="eeAllowFrontManageWarning" class="eeNote" style="color:#b32d2e;font-weight:bold;' . ($eeSFL->eeListSettings['AllowFrontManage'] == 'YES' ? '' : 'display:none;') . '">&#9888; ' . __('Warning: Front-End Management is enabled. Any visitor who can access this page will be able to delete, rename, and edit files. Restrict access to this page using WordPress page protection or a members-only plugin.', 'simple-file-list') . '</div>

			<script>
			document.getElementById("eeAllowFrontManage").addEventListener("change", function() {
				document.getElementById("eeAllowFrontManageWarning").style.display = this.checked ? "" : "none";
			});
			</script>

			</fieldset>';

		} else {

			 $eeOutput .= '

			<p>' . __('These settings have moved to the List Access Settings tab.', 'simple-file-list') . '</p>
			<a class="button" href="' . $eeSFL->eeSFL_GetThisURL(FALSE) . '?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=list_access&eeListID=' . $eeSFL->eeListID . '">' . __('Go There', 'simple-file-list') . '</a>';
		}




		 $eeOutput .= '</div>

		<div class="eeSettingsTile">

		<h2>' . __('File List Style', 'simple-file-list') . '</h2>


		<fieldset>
		<legend>File List Type</legend>

		<p><label for="eeShowListStyle">' . __('Style', 'simple-file-list') . '</label>

		<select name="eeShowListStyle" id="eeShowListStyle">

			<option value="TABLE"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'TABLE') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Standard Table Display', 'simple-file-list') . '</option>

			<option value="TILES"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'TILES') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Tiles Displayed in Columns', 'simple-file-list') . '</option>

			<option value="FLEX"';

			if($eeSFL->eeListSettings['ShowListStyle'] == 'FLEX') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Flexible List Display', 'simple-file-list') . '</option>

		</select></p>
		<div class="eeNote">' . __('Choose the style of the file list: Table, Tiles or Flex.', 'simple-file-list') . '</div>

		</fieldset>



		<fieldset>
		<legend>File List Theme</legend>

		<p><label for="eeShowListTheme">' . __('Show', 'simple-file-list') . '</label>

		<select name="eeShowListTheme" id="eeShowListTheme">

			<option value="LIGHT"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'LIGHT') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Light Theme', 'simple-file-list') . '</option>

			<option value="DARK"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'DARK') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Dark Theme', 'simple-file-list') . '</option>

			<option value="NONE"';

			if($eeSFL->eeListSettings['ShowListTheme'] == 'NONE') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('No Theme', 'simple-file-list') . '</option>

		</select></p>
		<div class="eeNote">' . __('Choose the color theme of the file list', 'simple-file-list') . ': Light, Dark, or None.'  . __('This will rely upon your theme colors', 'simple-file-list') . '</div>

		</fieldset>


		</div>






		<div class="eeSettingsTile">

		<h2>' . __('File Sorting and Order', 'simple-file-list') . '</h2>

		<p><label for="eeSortList">' . __('Sort By', 'simple-file-list') . ':</label>

		<select name="eeSortBy" id="eeSortList">

			<option value="Name"';

			if($eeSFL->eeListSettings['SortBy'] == 'Name') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('File Name', 'simple-file-list') . '</option>


			<option value="Added"';

			if($eeSFL->eeListSettings['SortBy'] == 'Added') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Date File Added', 'simple-file-list') . '</option>


			<option value="Changed"';

			if($eeSFL->eeListSettings['SortBy'] == 'Changed') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Date File Changed', 'simple-file-list') . '</option>


			<option value="Size"';

			if($eeSFL->eeListSettings['SortBy'] == 'Size') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('File Size', 'simple-file-list') . '</option>


			<option value="Random"';

			if($eeSFL->eeListSettings['SortBy'] == 'Random') {  $eeOutput .=  ' selected'; }

			 $eeOutput .= '>' . __('Random', 'simple-file-list') . '</option>

		</select></p>

		<p><label for="eeSortOrder">' . __('Reverse Order', 'simple-file-list') . ':</label>
		<input type="checkbox" name="eeSortOrder" value="Descending" id="eeSortOrder"';

		if( $eeSFL->eeListSettings['SortOrder'] == 'Descending') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /> &darr; ' . __('Descending', 'simple-file-list') . '</p>

		<div class="eeNote">' . __('Sort the list by name, date, file size, or randomly.', 'simple-file-list') . ' ' . __('Check the box to reverse the sort order.', 'simple-file-list') . '</div>

		</div>

		<div class="eeSettingsTile">

		<h2>' . __('Search and Pages', 'simple-file-list') . '</h2>

		<p><label for="eeShowSearch">' . __('Search', 'simple-file-list') . ':</label>
		<input type="checkbox" name="eeShowSearch" value="YES" id="eeShowSearch"' . (($eeSFL->eeListSettings['ShowSearch'] ?? 'NO') == 'YES' ? ' checked="checked"' : '') . ' /> ' . __('Show a search form above the list', 'simple-file-list') . '</p>

		<p><label for="eeFilesPerPage">' . __('Files Per Page', 'simple-file-list') . ':</label>
		<input type="number" min="0" max="500" step="1" name="eeFilesPerPage" value="' . (int) ($eeSFL->eeListSettings['FilesPerPage'] ?? 0) . '" id="eeFilesPerPage" /></p>

		<div class="eeNote">' . __('Search looks in file names, nice names and descriptions; every word must match. It is always available in wp-admin.', 'simple-file-list') . ' ' . __('Set the number of files per page to 0 to show all files on one page.', 'simple-file-list') . ' ' . __('Shortcode: search="YES", filecount="25", paged="NO".', 'simple-file-list') . '</div>

		</div>';


		 $eeOutput .= '<div class="eeSettingsTile">

		<h2>' . __('Thumbnail Generation', 'simple-file-list') . '</h2>

		<p>' . __('You can choose to generate small representative images of large images, PDF files and videos files.', 'simple-file-list') . '</p>

		<fieldset>

		<p><label for="eeGenerateImgThumbs">' . __('Image Thumbnails', 'simple-file-list') . ':</label>

		<input id="eeGenerateImgThumbs" type="checkbox" name="eeGenerateImgThumbs" value="YES"';

		$eeSupported = get_option('eeSFL_Supported');
		if( !is_array($eeSupported) ) { $eeSupported = array(); }
		$eeMissing = array();

		if( $eeSFL->eeListSettings['GenerateImgThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' />
			<div class="eeNote">' . __('Read an image file and create a small thumbnail image.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>

		<p><label for="eeGeneratePDFThumbs">' . __('PDF Thumbnails', 'simple-file-list') . ':</label>

		<input id="eeGeneratePDFThumbs" type="checkbox" name="eeGeneratePDFThumbs" value="YES"';
		if( $eeSFL->eeListSettings['GeneratePDFThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }
	if( !isset($eeSFL->eeEnvironment['GhostScript']) OR $eeSFL->eeEnvironment['eeOS'] == 'WINDOWS' ) {  $eeOutput .= ' disabled="disabled"'; }
	 $eeOutput .= ' /> ';

	if( !in_array('GhostScript' , $eeSupported) ) {
		$eeMissing[] = 'GhostScript is Not Installed. PDF thumbnails cannot be created.';
	}

		if( $eeSFL->eeEnvironment['eeOS'] == 'WINDOWS' ) {
			$eeMissing[] .= ' <em>Windows: ' . __('Not yet supported for PDF thumbnails.', 'simple-file-list') . '</em>';
		}

		 $eeOutput .= '</p>
		<div class="eeNote">' . __('Read a PDF file and create a representative thumbnail image based on the first page.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>

		<p><label for="eeGenerateVideoThumbs">' . __('Video Thumbnails', 'simple-file-list') . ':</label>

		<input id="eeGenerateVideoThumbs" type="checkbox" name="eeGenerateVideoThumbs" value="YES"';
		if( $eeSFL->eeListSettings['GenerateVideoThumbs'] == 'YES' ) {  $eeOutput .= ' checked="checked"'; }
		if( !isset($eeSFL->eeEnvironment['ffMpeg']) ) {  $eeOutput .= ' disabled="disabled"'; }
		 $eeOutput .= ' /> ';

		if( !isset($eeSFL->eeEnvironment['ffMpeg']) ) {
			$eeMissing[] = __('Video thumbnails will not be created because ffMpeg is not Installed.', 'simple-file-list');
		}

		 $eeOutput .= '</p>

		<div class="eeNote">' . __('Read a video file and create a representative thumbnail image at the 1 second mark.', 'simple-file-list') . '</div>';

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

		<h2>' . __('Smooth-Scroll', 'simple-file-list') . '</h2>

		<p><label for="eeSmoothScroll">' . __('Use Smooth-Scroll', 'simple-file-list') . ':</label>
		<input type="checkbox" name="eeSmoothScroll" value="YES" id="eeSmoothScroll"';

		if( $eeSFL->eeListSettings['SmoothScroll'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></p>

		<div class="eeNote">' . __('Uses a JavaScript effect to scroll down to the top of the list after an action. This can be helpful if the list is not located close to the top of the page.', 'simple-file-list') . '</div>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Media Player', 'simple-file-list') . '</h2>

		<fieldset>

			<legend>' . __('Enable In-Line Audio Player', 'simple-file-list') . '</legend>

			<div>
			<label for="eeAudioEnabled">' . __('Enabled', 'simple-file-list') . ':</label>
			<input type="checkbox" name="eeAudioEnabled" id="eeAudioEnabled" value="YES" ';

			if($eeSFL->eeListSettings['AudioEnabled'] == 'YES') {  $eeOutput .= 'checked="checked"'; }

			 $eeOutput .= ' />
			</div>

			<div class="eeNote">' . __('Show the audio player beneath the file name', 'simple-file-list') . '</div>


		</fieldset>

		<fieldset>

			<legend>' . __('Choose the Audio Player Height', 'simple-file-list') . '</legend>

			<div><label for="eeAudioHeight">' . __('Height', 'simple-file-list') . ':</label>
			<input type="number" name="eeAudioHeight" id="eeAudioHeight" value="' . $eeSFL->eeListSettings['AudioHeight'] . '" />
			</div>

			<div class="eeNote">' . __('Define the height of the audio player in pixels.', 'simple-file-list') . ' ' . __('Set to zero to ignore this value.', 'simple-file-list') . '</div>

		</fieldset>

		</div>


	</div>






	<!-- Right Column -->

	<div class="eeColRight">';

	$eeOutput .= '

		<div class="eeSettingsTile">

		<h2>' . __('File Actions', 'simple-file-list') . '</h2>


		<fieldset>
		<legend>' . __('Show Open Action', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Action', 'simple-file-list') . '</label>
		<input type="checkbox" name="eeShowFileOpen" value="YES" id="eeShowFileOpen"';

		if( $eeSFL->eeListSettings['ShowFileOpen'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Display the Open File link. If the browser cannot open the file, it will prompt the user to download.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Show Download Action', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Action', 'simple-file-list') . '</label>
		<input type="checkbox" name="eeShowFileDownload" value="YES" id="eeShowFileDownload"';

		if( $eeSFL->eeListSettings['ShowFileDownload'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('The browser will prompt the user to download the file.', 'simple-file-list') . '</div>

		</fieldset>



		<fieldset>
		<legend>' . __('Show Copy Action', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Action', 'simple-file-list') . '</label>
		<input type="checkbox" name="eeShowFileCopyLink" value="YES" id="eeShowFileCopyLink"';

		if( $eeSFL->eeListSettings['ShowFileCopyLink'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Copies the file URL to the user clipboard.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Bulk File Download', 'simple-file-list') . '</legend>
		<div><label>' . __('Allow', 'simple-file-list') . '</label>
		<input type="checkbox" name="eeAllowBulkFileDownload" value="YES" id="eeAllowBulkFileDownload"';

		if( !empty($eeSFL->eeListSettings['AllowBulkFileDownload']) && $eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') { $eeOutput .= ' checked="checked"'; }

		$eeOutput .= ' /></div>

		<div class="eeNote">' . __('Allow users to select and download multiple files as a ZIP archive.', 'simple-file-list') . '</div>

		</fieldset>


		</div>


		<div class="eeSettingsTile">

		<h2>' . __('File List Display', 'simple-file-list') . '</h2>

		<fieldset>
		<legend>' . __('File Thumbnail', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowFileThumb" value="YES" id="eeShowFileThumb"';
		if($eeSFL->eeListSettings['ShowFileThumb'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelThumb" value="';
		if( isset($eeSFL->eeListSettings['LabelThumb']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelThumb']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show file thumbnail images.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Name', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input checked disabled type="checkbox" name="eeShowFileName" value="YES" id="eeShowFileName" />
		<input type="text" name="eeLabelName" value="';
		if( isset($eeSFL->eeListSettings['LabelName']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelName']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show file name. This cannot be disabled.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Date', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowFileDate" value="YES" id="eeShowFileDate"';
		if($eeSFL->eeListSettings['ShowFileDate'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input class="eeFortyPercent" type="text" name="eeLabelDate" value="';
		if( isset($eeSFL->eeListSettings['LabelDate'])) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelDate']); }
		 $eeOutput .= '" />

		<select name="eeShowFileDateAs" id="eeShowFileDateAs">
			<option value="">' . __('Date Type', 'simple-file-list') . '</option>

			<option value="Added"';
			if($eeSFL->eeListSettings['ShowFileDateAs'] == 'Added') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Added', 'simple-file-list') . '</option>

			<option value="Changed"';
			if($eeSFL->eeListSettings['ShowFileDateAs'] == 'Changed') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Changed', 'simple-file-list') . '</option>
		</select></div>

		<div class="eeNote">Show the file date, either last changed or when added to the list.</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Size', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowFileSize" value="YES" id="eeShowFileSize"';
		if($eeSFL->eeListSettings['ShowFileSize'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelSize" value="';
		if( isset($eeSFL->eeListSettings['LabelSize']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelSize']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Limit the file information to display on the front-side file list. Enter a custom label if needed.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Description', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowFileDesc" value="YES" id="eeShowFileDesc"';
		if($eeSFL->eeListSettings['ShowFileDesc'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelDesc" value="';
		if( isset($eeSFL->eeListSettings['LabelDesc']) ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelDesc']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show a description of the file, which can include keywords and special characters not allowed within the file name.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Submitter', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowSubmitterInfo" value="YES" id="eeShowSubmitterInfo"';
		if($eeSFL->eeListSettings['ShowSubmitterInfo'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' />
		<input type="text" name="eeLabelOwner" value="';

		// echo '<pre>'; print_r($eeSFL->eeDefaultListSettings); echo '</pre>'; exit;

		if( $eeSFL->eeListSettings['LabelOwner'] ) {  $eeOutput .= stripslashes($eeSFL->eeListSettings['LabelOwner']); }
		 $eeOutput .= '" /></div>

		<div class="eeNote">' . __('Show the name of the user who uploaded the file on the front-end.', 'simple-file-list') . '</div>

		<div><label>' . __('Show Email Link', 'simple-file-list') . '</label><input type="checkbox" name="eeShowSubmitterEmail" value="YES" id="eeShowSubmitterEmail"';
		if($eeSFL->eeListSettings['ShowSubmitterEmail'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show the submitter\'s email address as a clickable link.', 'simple-file-list') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Table Header', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowHeader" value="YES" id="eeShowHeader"';
		if($eeSFL->eeListSettings['ShowHeader'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show or hide the file table header.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('File Extension', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eeShowFileExtension" value="YES" id="eeShowFileExtension"';
		if($eeSFL->eeListSettings['ShowFileExtension'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Show or hide the file extension.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>
		<legend>' . __('Preserve File Name', 'simple-file-list') . '</legend>
		<div><label>' . __('Show', 'simple-file-list') . '</label><input type="checkbox" name="eePreserveName" value="YES" id="eePreserveName"';
		if($eeSFL->eeListSettings['PreserveName'] == 'YES') {  $eeOutput .= ' checked'; }
		 $eeOutput .= ' /></div>

		<div class="eeNote">' . __('Files with illegal characters are renamed to ensure good URLs.', 'simple-file-list') . ' ' .
			__('This setting will preserve and show the original name as the Nice Name.', 'simple-file-list') . '</div>

		</fieldset>

		</div>

	</div>

</div>


<div class="eeColInline eeSettingsTile">

	<input class="button" type="submit" name="submit" value="' . __('SAVE', 'simple-file-list') . '" />

</div>

</form>';

?>