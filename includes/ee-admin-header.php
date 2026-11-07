<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



// Begin Output
 $eeOutput = '
<!-- BEGIN SFL ADMIN -->

<div class="wrap eeSFL">
<main class="eeSFL_Admin" id="eeSFL_AdminMain">

	<header class="eeClearFix">';

		 $eeOutput .= '

		<div id="eeSFL_HeaderMeta">
			<a href="https://get.simplefilelist.com/index.php" target="_blank">
				<img src="' . $eeSFL->eeEnvironment['pluginURL'] . '/images/icon-128x128.png" alt="Simple File List ' . __('Logo', 'simple-file-list') . '" title="Simple File List" /></a>
			<div>
				<p class="heading">' . eeSFL_PluginName . '</p>
				<p class="eeTagLine">' . __('Easy File Sharing for WordPress', 'simple-file-list') . '</p>
				<p class="eeHeaderLinks">
					<a href="https://simplefilelist.com/documentation/" target="_blank">' . __('Documentation', 'simple-file-list') . '</a><a href="https://simplefilelist.com/get-support/" target="_blank">' . __('Get Support', 'simple-file-list') . '</a>';
					if(defined('eeSFL_Pro')) {
						 $eeOutput .= '<a href="https://account.simplefilelist.com/" target="_blank">' . __('My Account', 'simple-file-list') . '</a>';
					} else {
						 $eeOutput .= '<a href="https://get.simplefilelist.com/" target="_blank">' . __('Get Pro Version', 'simple-file-list') . '</a>';
					}

				 $eeOutput .= '</p>
			</div>
		</div>


		<div id="eeSFL_HeaderTools">';

		// List switcher, when this user manages more than one list
		$eeManageable = eeSFL_ManageableListIDs();
		if(count($eeManageable) > 1) {
			$eeOutput .= '
			<form method="get" class="eeSFL_ListNavigation">
				<input type="hidden" name="page" value="' . esc_attr(eeSFL_PluginSlug) . '" />
				<input type="hidden" name="tab" value="list" />
				<select name="eeListID" onchange="this.form.submit()" aria-label="' . esc_attr__('Choose List', 'simple-file-list') . '">';
			foreach($eeManageable as $eeNavID) {
				$eeNavSettings = get_option('eeSFL_Settings_' . $eeNavID, array());
				$eeOutput .= '
					<option value="' . (int) $eeNavID . '"' . selected($eeNavID, (int) $eeSFL->eeListID, false) . '>' . esc_html(stripslashes($eeNavSettings['ListTitle'] ?? '')) . '</option>';
			}
			$eeOutput .= '
				</select>
				<noscript><button type="submit" class="button">' . esc_html__('Go', 'simple-file-list') . '</button></noscript>
			</form>';
		}

		// Verify nonce for admin tab navigation (Recommended security practice)
		$eeTabNonceValid = isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ee-admin-navigation');
		if (isset($_GET['tab']) && !$eeTabNonceValid) {
			eeSFL_Debug_Log("Admin tab navigation nonce verification failed for security logging", 'Admin');
		}

		if(!isset($_GET['tab'])) { $eeShowShortcodeBlock = TRUE; } else { $eeShowShortcodeBlock = FALSE; }
		if(isset($_GET['tab'])) { if($_GET['tab'] == 'list') { $eeShowShortcodeBlock = TRUE; } }

		if($eeShowShortcodeBlock) { // Only show this on the list tab

			 $eeOutput .= '

			<div class="eeShortCodeOps">

			<div class="eeFlex">

				<input class="eeFlex3" type="text" name="eeSFL_ShortCode" value="[eeSFL';

			if((int) $eeSFL->eeListID !== 1) {  $eeOutput .= ' list=\'' . (int) $eeSFL->eeListID . '\''; }
			if(isset($_GET['eeFolder'])) {
				// Verify nonce for folder navigation (Recommended security practice)
				$eeFolderNonceValid = isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ee-admin-navigation');
				if (!$eeFolderNonceValid) {
					eeSFL_Debug_Log("Admin folder navigation nonce verification failed for security logging", 'Admin');
				}
				$eeShortcodeFolder = esc_js(sanitize_text_field(wp_unslash($_GET['eeFolder'])));
				 $eeOutput .= ' showfolder=\'' . $eeShortcodeFolder . '\'';
			}				 $eeOutput .= ']" id="eeSFL_ShortCode"><button id="eeCopytoClipboard" class="button eeFlex1">' . __('Copy', 'simple-file-list') . '</button>

			</div>

			<p><small>' . __('Place this shortcode on a page, post or widget.', 'simple-file-list') . '</small></p>

			</div>';

		}

	 $eeOutput .= '

	</div>

	</header>

';

// User Messaging
 $eeOutput .= $eeSFL->eeSFL_ResultsNotification();

?>