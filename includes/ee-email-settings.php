<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


avpvh_filelist_debug_log("Loaded: Email Settings", 'General');

// echo '<pre>'; print_r($_POST); echo '</pre>'; exit;

// Check for POST, Capability and Nonce
if(current_user_can('manage_options') && isset($_POST['eePost']) && !empty(sanitize_text_field(wp_unslash($_POST['eePost']))) AND check_admin_referer( 'avpvh-filelist-settings', 'avpvh-filelist-settings-nonce')) {

	// YES/NO Checkboxes
	$eeCheckboxes = array(
		'Notify',
		'AllowFrontSend',
		'BccFileSender'
	);
	foreach( $eeCheckboxes as $eeTerm){
		$eeSFL->eeListSettings[$eeTerm] = avpvh_filelist_process_checkbox_input($eeTerm);
	}


	$eeDelivery = array('To', 'Cc', 'Bcc');

	foreach( $eeDelivery as $eeField ) {

		if( isset($_POST['eeNotify' . $eeField]) && strpos(sanitize_text_field(wp_unslash($_POST['eeNotify' . $eeField])), '@') ) {

			$eeAddresses = $eeSFL->sanitize_email_string(sanitize_text_field(wp_unslash($_POST['eeNotify' . $eeField])));
			$eeSFL->eeListSettings['Notify' . $eeField] = $eeAddresses;

		} elseif(!isset($_POST['eeNotify' . $eeField]) || empty(sanitize_text_field(wp_unslash($_POST['eeNotify' . $eeField])))) {

			$eeSFL->eeListSettings['Notify' . $eeField] = '';

		}
	}

	// Message Options
	$eeTextInputs = array(
		'NotifyFrom'
		,'NotifyFromName'
		,'NotifySubject'
	);
	foreach( $eeTextInputs as $eeTerm ) {
		$eeSFL->eeListSettings[$eeTerm] = avpvh_filelist_process_text_input($eeTerm);
	}

	$eeSFL->eeListSettings['NotifyMessage'] = avpvh_filelist_process_text_input('NotifyMessage', 'textarea');

	if(!$eeSFL->eeListSettings['NotifyMessage']) {
		$eeSFL->eeListSettings['NotifyMessage'] = $eeSFL->eeNotifyMessageDefault;
	}

	// Update DB
	update_option('avpvh_filelist_settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings );

	$eeSFL->eeUserMessages['messages'][] = __('Notification Settings Saved', 'avpvh-filelist');
}



// Settings Display =========================================

// User Messaging
 $eeOutput .= $eeSFL->results_notification();

// Begin the Form
 $eeOutput .= '

<form action="' . $eeSFL->get_this_url() . '" method="post" id="eeSFL_Settings">
<input type="hidden" name="eePost" value="TRUE" />
<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />';
 $eeOutput .= wp_nonce_field( 'avpvh-filelist-settings', 'avpvh-filelist-settings-nonce', TRUE, FALSE);

 $eeOutput .= '

<div class="eeColInline eeSettingsTile">

	<div class="eeColHalfLeft">

		<h1>' . __('Notifications Settings', 'avpvh-filelist') . '</h1>

	</div>

	<div class="eeColHalfRight">

		<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

	</div>

</div>

<div class="eeSettingsTile">

	<h2>' . __('Email File Sending', 'avpvh-filelist') . '</h2>

	<fieldset>

		<legend>' . __('Allow File Sending', 'avpvh-filelist') . '</legend>

		<div><label for="eeAllowFrontSend">' . __('Allow', 'avpvh-filelist') . '
		<input type="checkbox" name="eeAllowFrontSend" value="YES" id="eeAllowFrontSend"';

		if( $eeSFL->eeListSettings['AllowFrontSend'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Allow front-side users to send email messages containing links to files.', 'avpvh-filelist') . '</div>

	</fieldset>

	<fieldset>

		<legend>' . __('Blind Copy Self', 'avpvh-filelist') . '</legend>

		<div><label for="eeBccFileSender">BCC ' . __('Self', 'avpvh-filelist') . '
		<input type="checkbox" name="eeBccFileSender" value="YES" id="eeBccFileSender"';

		if( $eeSFL->eeListSettings['BccFileSender'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

		 $eeOutput .= ' /></label></div>

		<div class="eeNote">' . __('Send a hidden copy of the message to the sender\'s reply address.', 'avpvh-filelist') . ' (BCC)</div>

	</fieldset>

</div>


<div class="eeSettingsTile">

	<h2>' . __('Notifications', 'avpvh-filelist') . '</h2>';

	if(empty($eeSFL->eeListSettings['NotifyTo'])) {
		$eeSFL->eeListSettings['NotifyTo'] = get_option('admin_email');
	}
	if(empty($eeSFL->eeListSettings['NotifyFrom'])) {
		$eeSFL->eeListSettings['NotifyFrom'] = get_option('admin_email');
	}

	 $eeOutput .= '

	<fieldset>
	<legend>' . __('Enable Notifications', 'avpvh-filelist') . '</legend>
	<div><label>' . __('Enable', 'avpvh-filelist') . '<input type="checkbox" name="eeNotify" value="YES" id="eeNotify"';
	if($eeSFL->eeListSettings['Notify'] == 'YES') {  $eeOutput .= ' checked'; }
	 $eeOutput .= ' /></label></div>

	<div class="eeNote">' . __('Send an email notification when a file is uploaded on the front-side of the website.', 'avpvh-filelist') . '</div>

	</fieldset>

</div>




<div class="eeColumns">

	<!-- Left Column -->

	<div class="eeColLeft">

		<div class="eeSettingsTile">

		<h2>' . __('Notice Recipients', 'avpvh-filelist') . '</h2>

		<fieldset>';


		 $eeOutput .= '

		<div><label class="eeBlock">' . __('Notice Email', 'avpvh-filelist') . '
		<input type="text" name="eeNotifyTo" value="' . $eeSFL->eeListSettings['NotifyTo'] . '" id="eeNotifyTo" /></label></div>
		<div class="eeNote">' . __('Send an email here whenever a file is uploaded.', 'avpvh-filelist') . '</div>';


		// For All List Types
		 $eeOutput .= '

		<div><label class="eeBlock">' . __('Copy to Email', 'avpvh-filelist') . '<br />
		<input type="text" name="eeNotifyCc" value="' . $eeSFL->eeListSettings['NotifyCc'] . '" id="eeNotifyCc" /></label></div>
		<div class="eeNote">' . __('Copy all notice emails here.', 'avpvh-filelist') . '</div>

		<div><label class="eeBlock">' . __('Blind Copy to Email', 'avpvh-filelist') . '<br />
		<input class="eeFullWidth" type="text" name="eeNotifyBcc" value="' . $eeSFL->eeListSettings['NotifyBcc'] . '" id="eeNotifyBcc" /></label></div>
		<div class="eeNote">' . __('Blind copy all notice emails here.', 'avpvh-filelist') . '</div>
		<div class="eeNote">* ' . __('Separate multiple addresses with a comma.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>

	</div>


	<!-- Right Column -->

	<div class="eeColRight">

		<div class="eeSettingsTile">

		<h2>' . __('Message Details', 'avpvh-filelist') . '</h2>

		<fieldset>

		<div><label class="eeBlock">' . __('Your Name', 'avpvh-filelist') . '<br />
		<input class="eeFullWidth" type="text" name="eeNotifyFromName" value="' . stripslashes($eeSFL->eeListSettings['NotifyFromName']) . '" id="eeNotifyFromName" /></label></div>
		<div class="eeNote">' . __('The visible name in the From field.', 'avpvh-filelist') . '</div>

		<div><label class="eeBlock">' . __('Reply Address', 'avpvh-filelist') . '<br />
		<input class="eeFullWidth" type="email" name="eeNotifyFrom" value="' . $eeSFL->eeListSettings['NotifyFrom'] . '" id="eeNotifyFrom" /></label></div>
		<div class="eeNote">' . __('The notification message\'s reply-to address.', 'avpvh-filelist') . '</div>

		</fieldset>

		</div>

	</div>

</div>




<div class="eeSettingsTile">

<fieldset>

<h2>' . __('Message Details', 'avpvh-filelist') . '</h2>';

 $eeOutput .= '

<div><label class="eeBlock">' . __('Message Subject', 'avpvh-filelist') . '<br />
<input class="eeFullWidth" type="text" name="eeNotifySubject" value="' . stripslashes($eeSFL->eeListSettings['NotifySubject']) . '" id="eeNotifySubject" /></label></div>

<div class="eeNote">' . __('The notification message subject line.', 'avpvh-filelist') . '</div>

<div><label class="eeBlock">' . __('Message Body', 'avpvh-filelist') . '<br />
<textarea class="eeFullWidth" name="eeNotifyMessage" id="eeNotifyMessage" cols="64" rows="12" >' . stripslashes($eeSFL->eeListSettings['NotifyMessage']) . '</textarea></label></div>

<div class="eeNote">' . __('This is the text for all file upload notification messages.', 'avpvh-filelist') . ' ' . __('To insert links to the files, use this shortcode:', 'avpvh-filelist') . ' [file-list]' . ' '  . __('To insert a link pointing to the file list page, use this shortcode:', 'avpvh-filelist') . ' [web-page]</div>

</fieldset>

</div>







<div class="eeColInline eeSettingsTile">

	<input class="button" type="submit" name="submit" value="' . __('SAVE', 'avpvh-filelist') . '" />

</div>


</form>

';

?>