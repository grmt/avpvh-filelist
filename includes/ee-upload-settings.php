<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


avpvh_flst_debug_log("Loaded: Upload Settings", 'General');

// Check for POST, Capability and Nonce
if(current_user_can('manage_options') && isset($_POST['eePost']) && !empty(sanitize_text_field(wp_unslash($_POST['eePost']))) AND check_admin_referer( 'avpvh-filelist-upload-settings', 'avpvh-filelist-upload-settings-nonce')) {


	if(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'YES') {

		$eeSFL->eeListSettings['AllowUploads'] = 'YES';

	} elseif(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'USER') { // Only logged in users

		 $eeSFL->eeListSettings['AllowUploads'] = 'USER';

	} elseif(isset($_POST['eeAllowUploads']) && $_POST['eeAllowUploads'] == 'ADMIN') { // Only logged in users

		 $eeSFL->eeListSettings['AllowUploads'] = 'ADMIN';

	} else {
		$eeSFL->eeListSettings['AllowUploads'] = 'NO';
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
				$eeSFL->eeUserMessages['errors'][] = __('This file type is not allowed', 'avpvh-filelist') . ': ' . $eeValue;
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
		$eeSFL->eeListSettings[$eeTerm] = avpvh_flst_process_checkbox_input($eeTerm);
	}

	// Show Above or Below List
	if(isset($_POST['eeUploadPosition']) && $_POST['eeUploadPosition'] == 'Above') { $eeSFL->eeListSettings['UploadPosition'] = 'Above'; }
		else { $eeSFL->eeListSettings['UploadPosition'] = 'Below'; }


	// Update DB
	update_option('avpvh_flst_settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings );

	$eeSFL->eeUserMessages['messages'][] = __('Upload Settings Saved', 'avpvh-filelist');
}

// Settings Display =========================================

// User Messaging
 $eeOutput .= $eeSFL->results_notification();

 $eeOutput .= '

<form action="' . $eeSFL->get_this_url() . '" method="post" id="eeSFL_Settings">
<input type="hidden" name="eePost" value="TRUE" />';
 $eeOutput .= wp_nonce_field( 'avpvh-filelist-upload-settings', 'avpvh-filelist-upload-settings-nonce', TRUE, FALSE);
 $eeOutput .= '

<div class="eeColInline eeSettingsTile">

	<div class="eeColHalfLeft">

		<h1>' . __('File Upload Settings', 'avpvh-filelist') . '</h1>

	</div>

	<div class="eeColHalfRight">

		<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

	</div>

</div>

<div class="eeColumns">

	<!-- Left Column -->

	<div class="eeColLeft"><div class="eeSettingsTile">

		<h2>' . __('File Upload Restrictions', 'avpvh-filelist') . '</h2>';


	 $eeOutput .= '

	<fieldset>
	<legend>' . __('Who Can Upload Files', 'avpvh-filelist') . '</legend>
	<div><label>' . __('Restrict To', 'avpvh-filelist') . '
	<select name="eeAllowUploads" id="eeAllowUploads">

		<option value="YES" style="background-color:#FFFF00;"';

		if($eeSFL->eeListSettings['AllowUploads'] == 'YES') {  $eeOutput .= ' selected'; }

		 $eeOutput .= '>' . __('Anyone Can Upload', 'avpvh-filelist') . ' !!!</option>

		<option value="USER"';

		if($eeSFL->eeListSettings['AllowUploads'] == 'USER') {  $eeOutput .= ' selected'; }

		 $eeOutput .= '>' . __('Only Logged in Users Can Upload', 'avpvh-filelist') . '</option>

		<option value="ADMIN"';

		if($eeSFL->eeListSettings['AllowUploads'] == 'ADMIN') {  $eeOutput .= ' selected'; }

		 $eeOutput .= '>' . __('Only Logged in Admins Can Upload', 'avpvh-filelist') . '</option>

		<option value="NO"';

		if($eeSFL->eeListSettings['AllowUploads'] == 'NO') {  $eeOutput .= ' selected'; }

		 $eeOutput .= '>' . __('Hide the Front Side Uploader Completely', 'avpvh-filelist') . '</option>

	</select></label></div>

	<div class="eeNote">' . __('Allow anyone to upload, only logged-in users, administrators or nobody.', 'avpvh-filelist') . ' <strong>' . __('Please use "Anyone Can Upload" with Caution', 'avpvh-filelist') . '</strong></div>

	</fieldset>';


		 $eeOutput .= '</div>


		<div class="eeSettingsTile">

		<h2>' . __('Allow File Overwrite', 'avpvh-filelist') . '</h2>

		<fieldset>
		<legend>' . __('Overwrite or Save as New', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Overwrite', 'avpvh-filelist') . '
		<input type="checkbox" name="eeAllowOverwrite" value="YES" id="eeAllowOverwrite"';

		if( @$eeSFL->eeListSettings['AllowOverwrite'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Do not save new copies of files with the same name. Existing files will be overwritten.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>



		<div class="eeSettingsTile">

		<h2>' . __('Upload Submitter Information', 'avpvh-filelist') . '</h2>

		<fieldset>

		<legend>' . __('Upload Description', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Input', 'avpvh-filelist') . '
		<input type="checkbox" name="eeGetUploaderDesc" value="YES" id="eeGetUploaderDesc"';

		if( $eeSFL->eeListSettings['GetUploaderDesc'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display an input allowing the submitter to add a text description of the file upload.', 'avpvh-filelist') . '</div>

		</fieldset>


		<fieldset>

		<legend>' . __('Submitter Information', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Require', 'avpvh-filelist') . '
		<input type="checkbox" name="eeGetUploaderInfo" value="YES" id="eeGetUploaderInfo"';

		if( $eeSFL->eeListSettings['GetUploaderInfo'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display a form with name and email which is required to be filled out.', 'avpvh-filelist') . '<br />
		' . __('If the user is logged in the form will not appear. The name and email address will be automatically captured from the user data.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>

	</div>



	<!-- Right Column -->

	<div class="eeColRight">

		<div class="eeSettingsTile">

		<h2>' . __('Upload Job Limits', 'avpvh-filelist') . '</h2>

		<fieldset>
		<legend>' . __('Maximum Files Limit', 'avpvh-filelist') . '</legend>
		<div><label>' . __('File Limit', 'avpvh-filelist') . '
		<input type="number" min="1" max="100" step="1" name="eeUploadLimit" value="' . $eeSFL->eeListSettings['UploadLimit'] . '" id="eeUploadLimit" /></label></div>

		<div class="eeNote">' . __('The maximum number of files that may be uploaded per submission.', 'avpvh-filelist') . '</div>

		</fieldset>';

		// Maximum File Size
		if(!$eeSFL->eeListSettings['UploadMaxFileSize']) { $eeSFL->eeListSettings['UploadMaxFileSize'] = $eeSFL->eeEnvironment['the_max_upload_size']; }

		 $eeOutput .= '

		<fieldset>
		<legend>' . __('Maximum File Size', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Size', 'avpvh-filelist') . ' (MB)
		<input type="number" min="1" max="' . $eeSFL->eeEnvironment['the_max_upload_size']. '" step="1" name="eeUploadMaxFileSize" value="' . $eeSFL->eeListSettings['UploadMaxFileSize'] . '" id="eeUploadMaxFileSize" /></label></div>

		<div class="eeNote">' . __('Your hosting limits the maximum file upload size to', 'avpvh-filelist') . ' <strong>' . $eeSFL->eeEnvironment['the_max_upload_size']. ' MB</strong>.</div>

		</fieldset>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Allowed File Types', 'avpvh-filelist') . '</h2>

		<fieldset>
		<legend>File Extensions</legend>
		<div><label>' . __('File Types', 'avpvh-filelist') . '<br />
		<textarea name="eeFileFormats" id="eeFormats" cols="32" rows="3" >' . $eeSFL->eeListSettings['FileFormats'] . '</textarea></label></div>

		<div class="eeNote">' . __('Only use the file types you absolutely need, such as', 'avpvh-filelist') . ' jpg, jpeg, png, pdf, mp4, etc</div>

		</fieldset>

		</div>


		<div class="eeSettingsTile">

		<h2>' . __('Display', 'avpvh-filelist') . '</h2>

		<fieldset>
		<legend>' . __('Upload Form Position', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Position', 'avpvh-filelist') . '<select name="eeUploadPosition" id="eeUploadPosition">
			<option value="">' . __('Choose Position', 'avpvh-filelist') . '</option>

			<option value="Above"';
			if($eeSFL->eeListSettings['UploadPosition'] == 'Above') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Above', 'avpvh-filelist') . '</option>

			<option value="Below"';
			if($eeSFL->eeListSettings['UploadPosition'] == 'Below') {  $eeOutput .= ' selected="selected"'; }
			 $eeOutput .= '>' . __('Below', 'avpvh-filelist') . '</option>
		</select></label></div>

		<div class="eeNote">' . __('Choose to show the upload form either above or below the file list.', 'avpvh-filelist') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Confirmation', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Results', 'avpvh-filelist') . '
		<input type="checkbox" name="eeUploadConfirm" value="YES" id="eeUploadConfirm"';

		if( $eeSFL->eeListSettings['UploadConfirm'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Show a resulting list of the files uploaded, or proceed directly back to the file list.', 'avpvh-filelist') . '</div>

		</fieldset>

		<fieldset>
		<legend>' . __('Show Upload Limits', 'avpvh-filelist') . '</legend>
		<div><label>' . __('Show Limits', 'avpvh-filelist') . '
		<input type="checkbox" name="eeShowUploadLimits" value="YES" id="eeShowUploadLimits"';

		if( $eeSFL->eeListSettings['ShowUploadLimits'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Display upload limitations on the front-end, such as size and types allowed.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>

	</div>

</div>


<div class="eeColInline eeSettingsTile">

	<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

</div>

</form>';

?>