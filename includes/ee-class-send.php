<?php
// AV-PvH File List - File Sending, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Avpvh_Flst_Send {

	public $eeSettingsDefault = array(

		'AllowFrontSend' => 'YES',
		'BccFileSender' => 'NO'
	);


	// The Pop-Up Entry Form
	public function email_send_form() {

		global $eeSFL;

		$eeCount = is_array($eeSFL->eeAllFiles) ? count($eeSFL->eeAllFiles) : 0;

		// Send Files Overlay - Hidden until the Send link is clicked
		 $eeOutput = '

		<script>
			var eeSFL_SendNonce = "' . wp_create_nonce( "eeSFL_SendNonce" ) . '";
			var eeSFL_SendingSpinnerURL = "' . $eeSFL->eeEnvironment['pluginURL'] . 'images/sending.gif";
		</script>

		<div class="eeSFL_Modal" id="eeSFL_Modal_SendFiles">
		<div class="eeSFL_ModalBackground"></div>
		<div class="eeSFL_ModalBody">

			<button id="eeSFL_Modal_Send_Close" class="eeSFL_ModalClose">&times;</button>

			<h1>' . __('Send Files by Email', 'avpvh-filelist') . '</h1>';

			if($eeCount > 1) {
				 $eeOutput .= '<button class="button eeFont100p" onclick="eeSFLE_Send_AddMoreFiles();">' . __('Add More Files', 'avpvh-filelist') . '</button> ';
			}

			 $eeOutput .= '

			<p>' . __('Send an email with links to files. Add more files if needed.', 'avpvh-filelist') . '</p>

			<p id="eeSFLE_SendTheseFilesList">' . __('Sending:', 'avpvh-filelist') . ' <em></em></p>

			<form id="eeSFLE_SendFileForm" action="' . $eeSFL->get_this_url() . '" method="POST">

				<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />

				<fieldset id="eeSFLE_MessageDetials">

				<h2>' . __('Message Details', 'avpvh-filelist') . '</h2>

					<label for="eeSFL_SendFrom">' . __('Your Address', 'avpvh-filelist'). '</label>
					<input required type="email" name="eeSFL_SendFrom" value="" size="64" id="eeSFL_SendFrom" />
					<small class="eeSFL_ModalNote">' . __('Enter your email address.', 'avpvh-filelist') . '</small>

					<label for="eeSFL_SendTo">' . __('The TO Address', 'avpvh-filelist'). '</label>
					<input required type="email" name="eeSFL_SendTo" value="" size="64" id="eeSFL_SendTo" />
					<small class="eeSFL_ModalNote">' . __('Enter the address to send the message to.', 'avpvh-filelist') . '</small>

					<label for="eeSFL_SendCc">' . __('The CC Address', 'avpvh-filelist'). '</label>
					<input type="text" name="eeSFL_SendCc" value="" size="64" id="eeSFL_SendCc" />
					<small class="eeSFL_ModalNote">' . __('Enter an address to copy the message to.', 'avpvh-filelist') . '</small>

					<label for="eeSFL_SendSubject">' . __('The Subject', 'avpvh-filelist'). '</label>
					<input type="text" name="eeSFL_SendSubject" value="" size="64" id="eeSFL_SendSubject" />
					<small class="eeSFL_ModalNote">' . __('Enter the email subject.', 'avpvh-filelist') . '</small>

					<label for="eeSFL_SendMessage">' . __('The Message', 'avpvh-filelist'). '</label>
					<textarea name="eeSFL_SendMessage" id="eeSFL_SendMessage" cols="64" rows="5"></textarea>

					<br class="eeClear" />

					<span id="SendFilesButton">
						<button class="button" onclick="eeSFLE_DoFileSend()">' . __('Send', 'avpvh-filelist') . '</button>
					</span>

				</fieldset>';


				if( $eeCount > 1 ) {

					 $eeOutput .= '

					<fieldset id="eeSFLE_SendMoreFiles">

					<h3>' . __('Add More Files', 'avpvh-filelist') . '</h3>

				<table>
				 <tbody>';

				if(is_array($eeSFL->eeAllFiles)) {
					foreach( $eeSFL->eeAllFiles as $eeKey => $eeFileArray) {

						$eeFileNameDisplay = $eeFileArray['FilePath'];

						if($eeFileArray['FileExt'] != 'folder') { // We can't send folders, yet.

							 $eeOutput .= '

							<tr>
								<td class="eeSFLE_AddFileID_' . esc_attr($eeKey) . '"><input type="checkbox" name="eeSFLE_SendTheseFiles[]" value="' . esc_attr(urlencode($eeFileArray['FilePath'])) . '"/></td>
								<td>' . esc_html($eeFileNameDisplay) . '</td>
							</tr>';
						}
					}

					 $eeOutput .= '</tbody>
						</table>

						<p><button class="button eeInline eeFont100p" onclick="eeSFLE_Send_AddTheseFiles();">' . __('Add These Files', 'avpvh-filelist') . '</button>
							<button class="button eeInline eeFont100p" onclick="eeSFLE_Send_AddMoreCancel();">' . __('Cancel', 'avpvh-filelist') . '</button></p>

					</fieldset>';

					}

				}

			 $eeOutput .= '

			</form>

		</div>
		</div>';

		return  $eeOutput;

	}





	public function send_files_email() {

		// Nonce Check
		if( !check_ajax_referer( 'eeSFL_SendNonce', 'eeSecurity', FALSE ) ) { return 'ERROR 98 - Send Files Ajax'; }

		global $eeSFL;

		$eeFilesArray = FALSE;
		$eeFileLinks = '';
		$eeSubject = FALSE;
		$eeBcc = FALSE;

		// Get List Settings
		if(isset($_POST['id'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashing before sanitization on next line
			$eeTemp = wp_unslash($_POST['id']);
			$eeSFL->eeListID = absint(sanitize_text_field($eeTemp));
			$eeSFL->get_settings($eeSFL->eeListID);

			if(!isset($eeSFL->eeListSettings['FileListDir'])) {
				return __('Bad List ID', 'avpvh-filelist');
			}

		} else {
			return __('Missing List ID', 'avpvh-filelist');
		}

		// Verify that front-end file sending is enabled for this list
		if($eeSFL->eeListSettings['AllowFrontSend'] != 'YES') {
			avpvh_flst_debug_log("ERROR: Front-end file sending is disabled", 'Send', $eeSFL->eeListID);
			return __('File sending is not enabled', 'avpvh-filelist');
		}

		// Only people who may see the list may send its files
		if( !avpvh_flst_user_can_see_list($eeSFL->eeListID, $eeSFL->eeListSettings, get_current_user_id()) ) {
			return __('File sending is not enabled', 'avpvh-filelist');
		}

		// Rate limit email sending per IP / user (max 10 emails per 15 minutes)
		$eeCallerID = get_current_user_id() ? 'user_' . get_current_user_id() : 'ip_' . (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown');
		$eeRateKey = 'avpvh_flst_send_rate_' . substr(hash('sha256', $eeCallerID), 0, 32);
		$eeSendCount = (int) get_transient($eeRateKey);
		if( $eeSendCount >= 10 ) {
			avpvh_flst_debug_log("ERROR: Email send rate limit exceeded for " . $eeCallerID, 'Send', $eeSFL->eeListID);
			return __('Too many emails sent. Please try again later.', 'avpvh-filelist');
		}
		set_transient($eeRateKey, $eeSendCount + 1, 15 * MINUTE_IN_SECONDS);

		// From
		if(isset($_POST['from'])) { // 1 Required
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitize_email handles slashing
			$eeFrom = sanitize_email($_POST['from']);
			if(!$eeFrom) { return __('Bad Address', 'avpvh-filelist') . ': FROM'; }
		} else {
			return __('Missing Address', 'avpvh-filelist') . ': FROM';
		}

		// To
		if(isset($_POST['to'])) { // 1 Required
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitize_email handles slashing
			$eeTo = sanitize_email($_POST['to']);
			if(!$eeTo) { return __('Bad Address', 'avpvh-filelist') . ': TO'; }
		} else {
			return __('Missing Address', 'avpvh-filelist') . ': TO';
		}

		// CC
		if(isset($_POST['cc'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitize_email handles slashing
			$eeCc = sanitize_email($_POST['cc']);
		}

		// BCC ?
		if($eeSFL->eeListSettings['BccFileSender'] == 'YES') { $eeBcc = $eeFrom; }

		// Subject
		if(isset($_POST['subject'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashing before sanitization on next line
			$eeTemp = wp_unslash($_POST['subject']);
			$eeSubject = wp_strip_all_tags($eeTemp);
		}
		if(!$eeSubject) { $eeSubject = __('File Notification', 'avpvh-filelist'); }

		// Message
		if(isset($_POST['message'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashing before sanitization on next line
			$eeTemp = wp_unslash($_POST['message']);
			$eeMessage = wp_strip_all_tags($eeTemp);
		}

		// Files
		if(isset($_POST['files'])) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unslashing before sanitization on next line
			$eeTemp = wp_unslash($_POST['files']);
			$eeFiles = sanitize_text_field($eeTemp);
			if(strpos($eeFiles, ',')) {
				$eeFilesArray = explode(',', $eeFiles);
			} else {
				$eeFilesArray = array($eeFiles);
			}

		} else {
			return __('No Files to Send', 'avpvh-filelist');
		}

		if( is_array($eeFilesArray) ) { // The files array checkboxes

			// Validate requested files against stored file list
			$eeStoredFiles = get_option('avpvh_flst_files_' . $eeSFL->eeListID, array());
			if(!is_array($eeStoredFiles)) { $eeStoredFiles = array(); }

			foreach($eeFilesArray as $eeFile) {
				$eeFileTrimmed = ltrim(trim($eeFile), '/');
				if($eeFileTrimmed === '') { continue; }

				$eeMatchedPath = FALSE;
				foreach($eeStoredFiles as $eeStoredItem) {
					if(!isset($eeStoredItem['FilePath']) || ($eeStoredItem['FileExt'] ?? '') === 'folder') { continue; }
					if($eeStoredItem['FilePath'] === $eeFileTrimmed || basename($eeStoredItem['FilePath']) === $eeFileTrimmed) {
						$eeMatchedPath = $eeStoredItem['FilePath'];
						$eeMatchedItem = $eeStoredItem;
						break;
					}
				}

				if($eeMatchedPath === FALSE) { continue; }

				// Only files the sender may open themselves. For a protected list the link goes to the
				// download handler, which checks the recipient's own access when they open it.
				if( !avpvh_flst_user_can_read_file($eeSFL->eeListID, $eeSFL->eeListSettings, $eeMatchedItem, get_current_user_id()) ) { continue; }

				$eeFileLinks .= avpvh_flst_file_url($eeSFL->eeListID, $eeSFL->eeListSettings, $eeMatchedPath) . PHP_EOL . PHP_EOL;
			}

			if( $eeFileLinks === '' ) {
				return __('No Files to Send', 'avpvh-filelist');
			}

		} else {
			return __('Bad File Array', 'avpvh-filelist');
		}

		// Footer
		$eeFooter = PHP_EOL .  PHP_EOL . '';

		// The Body
		$eeMessage .= PHP_EOL .  PHP_EOL . $eeFileLinks . $eeFooter; // with Custom Footer

		// Email Headers
		$eeHeaders = avpvh_flst_return_header_string($eeFrom, $eeCc, $eeBcc);

		// TO DO -- Allow files to be attached if less than X MB
		// $eeAttached = array();

		// Send the Message
		if( wp_mail($eeTo, $eeSubject, $eeMessage, $eeHeaders)) { // , $eeAttached
			return 'SUCCESS';
		} else {
			return 'Email Failed to Send';
		}
	}








	// Settings Inputs
	public function settings_inputs_display() {

		global $eeSFL;

		 $eeOutput = '

		<div class="eeSettingsTile">

			<h2>' . __('Sending Files', 'avpvh-filelist') . '</h2>

			<fieldset>

				<legend>' . __('Allow File Sending', 'avpvh-filelist') . '</legend>

				<div><label for="eeAllowFrontSend">' . __('Allow', 'avpvh-filelist') . '</label>
				<input type="checkbox" name="eeAllowFrontSend" value="YES" id="eeAllowFrontSend"';

				if( $eeSFL->eeListSettings['AllowFrontSend'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

				 $eeOutput .= ' /></div>

				<div class="eeNote">' . __('Allow front-side users to send email messages containing links to files.', 'avpvh-filelist') . '</div>

			</fieldset>

			<fieldset>

				<legend>' . __('Blind Copy Self', 'avpvh-filelist') . '</legend>

				<div><label for="eeBccFileSender">BCC ' . __('Self', 'avpvh-filelist') . '</label>
				<input type="checkbox" name="eeBccFileSender" value="YES" id="eeBccFileSender"';

				if( $eeSFL->eeListSettings['BccFileSender'] == 'YES') {  $eeOutput .= ' checked="checked"'; }

				 $eeOutput .= ' /></div>

				<div class="eeNote">' . __('Send a hidden copy of the message to the your reply address.', 'avpvh-filelist') . ' (BCC)</div>

			</fieldset>

		</div>';

		return  $eeOutput;

	}



}


?>