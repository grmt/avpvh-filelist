<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - "All File Lists" and "Create List" admin tabs
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



/**
 * The "All File Lists" tab: every list with its folder, shortcode and file count. Handles deletes.
 *
 * @return string HTML
 */
function eeSFL_AllListsPage() {

	if( !current_user_can('manage_options') ) { return ''; }

	$eeMessages = '';

	if( isset($_POST['eeSFL_DeleteList']) && check_admin_referer('eeSFL_DeleteList', 'eeSFL_DeleteListNonce') ) {
		$eeDeleteID = absint(wp_unslash($_POST['eeSFL_DeleteList']));
		if( eeSFL_DeleteList($eeDeleteID) ) {
			/* translators: %d: list ID */
			$eeMessages .= '<div class="notice notice-success"><p>' . esc_html(sprintf(__('List %d was removed. Its files are still on the server.', 'simple-file-list'), $eeDeleteID)) . '</p></div>';
		} else {
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html__('This list cannot be removed.', 'simple-file-list') . '</p></div>';
		}
	}

	$eeOutput = $eeMessages . '
	<section class="eeSFL_Settings">
	<table class="widefat striped eeSFL_AllLists">
	<thead><tr>
		<th>' . esc_html__('ID', 'simple-file-list') . '</th>
		<th>' . esc_html__('Name', 'simple-file-list') . '</th>
		<th>' . esc_html__('Folder', 'simple-file-list') . '</th>
		<th>' . esc_html__('Shortcode', 'simple-file-list') . '</th>
		<th>' . esc_html__('Items', 'simple-file-list') . '</th>
		<th></th>
	</tr></thead>
	<tbody>';

	foreach( eeSFL_GetListIDs() as $eeID ) {

		$eeSettings = get_option('eeSFL_Settings_' . $eeID, array());
		$eeFiles = get_option('eeSFL_FileList_' . $eeID, array());
		$eeShortcode = $eeID === 1 ? '[eeSFL]' : '[eeSFL list="' . $eeID . '"]';
		$eeListURL = admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=list&eeListID=' . $eeID);
		$eeSettingsURL = admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=settings&eeListID=' . $eeID);

		$eeOutput .= '
		<tr>
			<td>' . (int) $eeID . '</td>
			<td><a href="' . esc_url($eeListURL) . '"><strong>' . esc_html(stripslashes($eeSettings['ListTitle'] ?? '')) . '</strong></a></td>
			<td><code>' . esc_html($eeSettings['FileListDir'] ?? '') . '</code></td>
			<td><code>' . esc_html($eeShortcode) . '</code></td>
			<td>' . (is_array($eeFiles) ? count($eeFiles) : 0) . '</td>
			<td>
				<a class="button button-small" href="' . esc_url($eeSettingsURL) . '">' . esc_html__('Settings', 'simple-file-list') . '</a>';

		if( $eeID !== 1 ) {
			$eeOutput .= '
				<form method="post" style="display:inline" onsubmit="return confirm(\'' . esc_js(__('Remove this list? The files stay on the server.', 'simple-file-list')) . '\');">
					' . wp_nonce_field('eeSFL_DeleteList', 'eeSFL_DeleteListNonce', true, false) . '
					<button type="submit" name="eeSFL_DeleteList" value="' . (int) $eeID . '" class="button button-small button-link-delete">' . esc_html__('Remove', 'simple-file-list') . '</button>
				</form>';
		}

		$eeOutput .= '
			</td>
		</tr>';
	}

	$eeOutput .= '
	</tbody>
	</table>
	<p class="description">' . esc_html__('Removing a list deletes its settings, not its files. A removed list ID is never reused, so old shortcodes for it stay empty.', 'simple-file-list') . '</p>
	</section>';

	return $eeOutput;
}



/**
 * The "Create List" tab.
 *
 * @return string HTML
 */
function eeSFL_CreateListPage() {

	global $eeSFL;

	if( !current_user_can('manage_options') ) { return ''; }

	$eeTitle = '';
	$eeDir = '';
	$eeMessages = '';

	if( isset($_POST['eeSFL_CreateList']) && check_admin_referer('eeSFL_CreateList', 'eeSFL_CreateListNonce') ) {

		$eeTitle = isset($_POST['eeSFL_ListTitle']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_ListTitle'])) : '';
		$eeDir = isset($_POST['eeSFL_ListDir']) ? sanitize_text_field(wp_unslash($_POST['eeSFL_ListDir'])) : '';

		$eeResult = eeSFL_CreateList($eeTitle, $eeDir);

		if( is_int($eeResult) ) {
			$eeSettingsURL = admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=settings&eeListID=' . $eeResult);
			$eeMessages .= '<div class="notice notice-success"><p>'
				/* translators: %d: list ID */
				. esc_html(sprintf(__('List %d was created. It is visible to admins only and does not accept uploads until you change its settings.', 'simple-file-list'), $eeResult))
				. ' <a href="' . esc_url($eeSettingsURL) . '">' . esc_html__('Open its settings', 'simple-file-list') . ' &rarr;</a></p></div>';
			$eeTitle = '';
			$eeDir = '';
		} else {
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html($eeResult) . '</p>';
			foreach( (array) ($eeSFL->eeUserMessages['errors'] ?? array()) as $eeError ) {
				$eeMessages .= '<p>' . esc_html($eeError) . '</p>';
			}
			$eeMessages .= '</div>';
			$eeSFL->eeUserMessages['errors'] = array();
		}
	}

	$eeNextID = max((int) get_option('eeSFL_NextListID', 0), max(eeSFL_GetListIDs()) + 1);
	$eeSuggestedDir = 'wp-content/uploads/simple-file-list-' . $eeNextID . '/';

	return $eeMessages . '
	<section class="eeSFL_Settings">
	<form method="post" action="' . esc_url(admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=create')) . '">
		' . wp_nonce_field('eeSFL_CreateList', 'eeSFL_CreateListNonce', true, false) . '

		<fieldset>
		<p><label class="eeBlock" for="eeSFL_ListTitle">' . esc_html__('List Name', 'simple-file-list') . '</label>
		<input type="text" class="eeFullWidth" name="eeSFL_ListTitle" id="eeSFL_ListTitle" value="' . esc_attr($eeTitle) . '" required /></p>
		</fieldset>

		<fieldset>
		<p><label class="eeBlock" for="eeSFL_ListDir">' . esc_html__('Folder', 'simple-file-list') . '</label>
		<input type="text" class="eeFullWidth" name="eeSFL_ListDir" id="eeSFL_ListDir" value="' . esc_attr($eeDir ?: $eeSuggestedDir) . '" required /></p>
		<div class="eeNote">' . esc_html__('Relative to your WordPress home folder. It is created if it does not exist, and may not be inside (or contain) the folder of another list.', 'simple-file-list') . '</div>
		</fieldset>

		<p class="description">' . esc_html__('The new list starts with the settings of the main list, but is visible to admins only and accepts no uploads until you change its settings.', 'simple-file-list') . '</p>

		<p><button type="submit" name="eeSFL_CreateList" value="1" class="button button-primary">' . esc_html__('Create List', 'simple-file-list') . '</button></p>
	</form>
	</section>';
}
