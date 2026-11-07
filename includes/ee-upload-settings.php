<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


eeSFL_Debug_Log("Loaded: Upload Settings", 'General');

// Check for POST, Capability and Nonce
if(current_user_can('manage_options') && isset($_POST['eePost']) && !empty(sanitize_text_field(wp_unslash($_POST['eePost']))) AND check_admin_referer( 'ee-simple-file-list-upload-settings', 'ee-simple-file-list-upload-settings-nonce')) {

	if(!$eeSFLA) {

		if(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'YES') {

			$eeSFL->eeListSettings['AllowUploads'] = 'YES';

		} elseif(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'USER') { // Only logged in users

			 $eeSFL->eeListSettings['AllowUploads'] = 'USER';

		} elseif(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'ADMIN') { // Only logged in users

			 $eeSFL->eeListSettings['AllowUploads'] = 'ADMIN';

		} else {
			$eeSFL->eeListSettings['AllowUploads'] = 'NO';
		}
	}

	// File Number Limit
	$eeSFL->eeListSettings['UploadLimit'] = isset($_POST['eeUploadLimit']) ? filter_var(sanitize_text_field(wp_unslash($_POST['eeUploadLimit'])), FILTER_VALIDATE_INT) : 0;
	if(!$eeSFL->eeListSettings['UploadLimit'] OR $eeSFL->eeListSettings['UploadLimit'] > 999 ) { $eeSFL->eeListSettings['UploadLimit'] = $eeSFL->eeDefaultUploadLimit; }

	// Maximum File Size
	if(isset($_POST['eeUploadMaxFileSize'])) {

		$eeSFL_UploadMaxFileSize = filter_var(sanitize_text_field(wp_unslash($_POST['eeUploadMaxFileSize'])), FILTER_VALIDATE_INT);

		// Can't be more than the system allows.
		if(!$eeSFL->eeListSettings['UploadMaxFileSize'] OR $eeSFL->eeListSettings['UploadMaxFileSize'] > $eeSFL->eeEnvironment['the_max_upload_size']) {
			$eeSFL->eeListSettings['UploadMaxFileSize'] = $eeSFL->eeEnvironment['the_max_upload_size'];
		} else {
			$eeSFL->eeListSettings['UploadMaxFileSize'] = $eeSFL_UploadMaxFileSize;
		}
	}

	if(!$eeSFL->eeListSettings['UploadMaxFileSize'] OR !is_numeric($eeSFL->eeListSettings['UploadMaxFileSize'] )) {
		$eeSFL_UploadMaxFileSize = 1;
	}

	// File Formats
	if(isset($_POST['eeFileFormats']) && !empty(sanitize_text_field(wp_unslash($_POST['eeFileFormats'])))) { // Strip all but what we need for the comma list of file extensions

		$eeFileFormatsIN = preg_replace("/[^a-z0-9,]/i", "", sanitize_text_field(wp_unslash($_POST['eeFileFormats'])));
		$eeFileFormatsIN = explode(',', $eeFileFormatsIN);
		$eeFileFormatsOK = '';
		foreach( $eeFileFormatsIN as $eeKey => $eeValue){
			$eeValue = trim($eeValue);
			if(in_array($eeValue, $eeSFL->eeForbiddenTypes)) {
				$eeSFL->eeUserMessages['errors'][] = __('This file type is not allowed', 'simple-file-list') . ': ' . $eeValue;
			} elseif($eeValue) {
				$eeFileFormatsOK .= $eeValue . ',';
			}
		}
		$eeSFL->eeListSettings['FileFormats'] = substr($eeFileFormatsOK, 0, -1);
	}


	// YES/NO Checkboxes
	$eeCheckboxes = array(
		'AllowOverwrite'
		,'ShowUploadLimits'
		,'GetUploaderInfo'
		,'GetUploaderDesc'
		,'UploadConfirm'
	);
	foreach( $eeCheckboxes as $eeTerm ) {
		$eeSFL->eeListSettings[$eeTerm] = eeSFL_ProcessCheckboxInput($eeTerm);
	}

	// Show Above or Below List
	if(isset($_POST['eeUploadPosition']) && $_POST['eeUploadPosition'] == 'Above') { $eeSFL->eeListSettings['UploadPosition'] = 'Above'; }
		else { $eeSFL->eeListSettings['UploadPosition'] = 'Below'; }


	// Update DB
	update_option('eeSFL_Settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings );

	$eeSFL->eeUserMessages['messages'][] = __('Upload Settings Saved', 'simple-file-list');
}

// Settings Display =========================================

// User Messaging
 $eeOutput .= $eeSFL->eeSFL_ResultsNotification();

 $eeOutput .= '

<form action="' . $eeSFL->eeSFL_GetThisURL() . '" method="post" id="eeSFL_Settings">
<input type="hidden" name="eePost" value="TRUE" />';
 $eeOutput .= wp_nonce_field( 'ee-simple-file-list-upload-settings', 'ee-simple-file-list-upload-settings-nonce', TRUE, FALSE);
 $eeOutput .= '

<div class="eeColInline eeSettingsTile">

	<div class="eeColHalfLeft">

		<h1>' . __('File Upload Settings', 'simple-file-list') . '</h1>

	</div>

	<div class="eeColHalfRight">

		<input class="button" type="submit" name="submit" value="' . __('SAVE', 'simple-file-list') . '" />

	</div>

</div>

<div class="eeColumns">

	<!-- Left Column -->

	<div class="eeColLeft"><div class="eeSettingsTile">

		<h2>' . __('File Upload Restrictions', 'simple-file-list') . '</h2>';

		if(!$eeSFLA) {

		 $eeOutput .= '

		<fieldset>
		<legend>' . __('Who Can Upload Files', 'simple-file-list') . '</legend>
		<div><label>' . __('Restrict To', 'simple-file-list') . '
		<select name="eeAllowUploads" id="eeAllowUploads">

			<option value="YES" style="background-color:#FFFF00;"';

			if($eeSFL->eeListSettings['AllowUploads'] == 'YES') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Anyone Can Upload', 'simple-file-list') . ' !!!</option>

			<option value="USER"';

			if($eeSFL->eeListSettings['AllowUploads'] == 'USER') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Only Logged in Users Can Upload', 'simple-file-list') . '</option>

			<option value="ADMIN"';

			if($eeSFL->eeListSettings['AllowUploads'] == 'ADMIN') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Only Logged in Admins Can Upload', 'simple-file-list') . '</option>

			<option value="NO"';

			if($eeSFL->eeListSettings['AllowUploads'] == 'NO') {  $eeOutput .= ' selected'; }

			 $eeOutput .= '>' . __('Hide the Front Side Uploader Completely', 'simple-file-list') . '</option>

		</select></label></div>

		<div class="eeNote">' . __('Allow anyone to upload, only logged-in users, administrators or nobody.', 'simple-file-list') . ' <strong>' . __('Please use "Anyone Can Upload" with Caution', 'simple-file-list') . '</strong></div>

		</fieldset>';

		} else {

			 $eeOutput .= '<p>' . __('This setting has moved to the List Access Settings tab.', 'simple-file-list') . '</p>
			<a class="button" href="' . $eeSFL->eeSFL_GetThisURL(FALSE) . '?page=' . eeSFL_PluginSlug . '&tab=settings&subtab=list_access&eeListID=' . $eeSFL->eeListID . '">' . __('Go There', 'simple-file-list') . '</a>';
		}

		 $eeOutput .= '</div>


		<div class="eeSettingsTile">

		<h2>' . __('Allow File Overwrite', 'simple-file-list') . '</h2>

		<fieldset>
		<legend>' . __('Overwrite or Save as New', 'simple-file-list') . '</legend>
		<div><label>' . __('Overwrite', 'simple-file-list') . '
		<input type="checkbox" name="eeAllowOverwrite" value="YES" id="eeAllowOverwrite"';

		if( @$eeSFL->eeListSettings['AllowOverwrite'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Do not save new copies of files with the same name. Existing files will be overwritten.', 'simple-file-list') . '</div>

		</fieldset>

		</div>



		<div class="eeSettingsTile">

		<h2>' . __('Upload Submitter Information', 'simple-file-list') . '</h2>

		<fieldset>

		<legend>' . __('Upload Description', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Input', 'simple-file-list') . '
		<input type="checkbox" name="eeGetUploaderDesc" value="YES" id="eeGetUploaderDesc"';

		if( $eeSFL->eeListSettings['GetUploaderDesc'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display an input allowing the submitter to add a text description of the file upload.', 'simple-file-list') . '</div>

		</fieldset>


		<fieldset>

		<legend>' . __('Submitter Information', 'simple-file-list') . '</legend>
		<div><label>' . __('Require', 'simple-file-list') . '
		<input type="checkbox" name="eeGetUploaderInfo" value="YES" id="eeGetUploaderInfo"';

		if( $eeSFL->eeListSettings['GetUploaderInfo'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display a form with name and email which is required to be filled out.', 'simple-file-list') . '<br />
		' . __('If the user is logged in the form will not appear. The name and email address will be automatically captured from the user data.', 'simple-file-list') . '</div>

		</fieldset>

		</div>

	</div>



	<!-- Right Column -->

	<div class="eeColRight">

		<div class="eeSettingsTile">

		<h2>' . __('Upload Job Limits', 'simple-file-list') . '</h2>

		<fieldset>
		<legend>' . __('Maximum Files Limit', 'simple-file-list') . '</legend>
		<div><label>' . __('File Limit', 'simple-file-list') . '
		<input type="number" min="1" max="100" step="1" name="eeUploadLimit" value="' . $eeSFL->eeListSettings['UploadLimit'] . '" id="eeUploadLimit" /></label></div>

		<div class="eeNote">' . __('The maximum number of files that may be uploaded per submission.', 'simple-file-list') . '</div>

		</fieldset>';

		// Maximum File Size
		if(!$eeSFL->eeListSettings['UploadMaxFileSize']) { $eeSFL->eeListSettings['UploadMaxFileSize'] = $eeSFL->eeEnvironment['the_max_upload_size']; }

		 $eeOutput .= '

		<fieldset>
		<legend>' . __('Maximum File Size', 'simple-file-list') . '</legend>
		<div><label>' . __('Size', 'simple-file-list') . ' (MB)
		<input type="number" min="1" max="' . $eeSFL->eeEnvironment['the_max_upload_size']. '" step="1" name="eeUploadMaxFileSize" value="' . $eeSFL->eeListSettings['UploadMaxFileSize'] . '" id="eeUploadMaxFileSize" /></label></div>

		<div class="eeNote">' . __('Your hosting limits the maximum file upload size to', 'simple-file-list') . ' <strong>' . $eeSFL->eeEnvironment['the_max_upload_size']. ' MB</strong>.</div>

		</fieldset>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Allowed File Types', 'simple-file-list') . '</h2>

		<fieldset>
		<legend>File Extensions</legend>
		<div><label>' . __('File Types', 'simple-file-list') . '<br />
		<textarea name="eeFileFormats" id="eeFormats" cols="32" rows="3" >' . $eeSFL->eeListSettings['FileFormats'] . '</textarea></label></div>

		<div class="eeNote">' . __('Only use the file types you absolutely need, such as', 'simple-file-list') . ' jpg, jpeg, png, pdf, mp4, etc</div>

		</fieldset>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Display', 'simple-file-list') . '</h2>

		<fieldset>
		<legend>' . __('Upload Form Position', 'simple-file-list') . '</legend>
		<div><label>' . __('Position', 'simple-file-list') . '<select name="eeUploadPosition" id="eeUploadPosition">
			<option value="">' . __('Choose Position', 'simple-file-list') . '</option>

			<option value="Above"';
			if($eeSFL->eeListSettings['UploadPosition'] == 'Above') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Above', 'simple-file-list') . '</option>

			<option value="Below"';
			if($eeSFL->eeListSettings['UploadPosition'] == 'Below') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Below', 'simple-file-list') . '</option>
		</select></label></div>

		<div class="eeNote">' . __('Choose to show the upload form either above or below the file list.', 'simple-file-list') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Confirmation', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Results', 'simple-file-list') . '
		<input type="checkbox" name="eeUploadConfirm" value="YES" id="eeUploadConfirm"';

		if( $eeSFL->eeListSettings['UploadConfirm'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Show a resulting list of the files uploaded, or proceed directly back to the file list.', 'simple-file-list') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Show Upload Limits', 'simple-file-list') . '</legend>
		<div><label>' . __('Show Limits', 'simple-file-list') . '
		<input type="checkbox" name="eeShowUploadLimits" value="YES" id="eeShowUploadLimits"';

		if( $eeSFL->eeListSettings['ShowUploadLimits'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display upload limitations on the front-end, such as size and types allowed.', 'simple-file-list') . '</div>

		</fieldset>

		</div>

	</div>

</div>


<div class="eeColInline eeSettingsTile">

	<input class="button" type="submit" name="submit" value="' . __('SAVE', 'simple-file-list') . '" />

</div>

</form>';

?>