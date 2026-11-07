<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



// Begin Output
 $eeOutput = '
<!-- BEGIN SFL ADMIN -->

<div class="wrap eeSFL">
<main class="eeSFL_Admin" id="eeSFL_AdminMain">

	<header class="eeClearFix">';

		 $eeOutput .= '

		<div id="eeSFL_HeaderMeta">
			<a href="https://github.com/grmt/avpvh-filelist" target="_blank" rel="noopener">
				<img src="' . $eeSFL->eeEnvironment['pluginURL'] . '/images/icon-128x128.png" alt="AV-PvH File List ' . __('Logo', 'avpvh-filelist') . '" title="AV-PvH File List" /></a>
			<div>
				<p class="heading">' . AVPVH_FLST_NAME . '</p>
				<p class="eeTagLine">' . __('Easy File Sharing for WordPress', 'avpvh-filelist') . '</p>
				<p class="eeHeaderLinks">
					<a href="https://github.com/grmt/avpvh-filelist#readme" target="_blank" rel="noopener">' . __('Documentation', 'avpvh-filelist') . '</a><a href="https://github.com/grmt/avpvh-filelist/issues" target="_blank" rel="noopener">' . __('Get Support', 'avpvh-filelist') . '</a>';

				 $eeOutput .= '</p>
			</div>
		</div>


		<div id="eeSFL_HeaderTools">';

		// List switcher, when this user manages more than one list
		$eeManageable = avpvh_flst_manageable_list_ids();
		if(count($eeManageable) > 1) {
			$eeOutput .= '
			<form method="get" class="eeSFL_ListNavigation">
				<input type="hidden" name="page" value="' . esc_attr(AVPVH_FLST_SLUG) . '" />
				<input type="hidden" name="tab" value="list" />
				<select name="eeListID" onchange="this.form.submit()" aria-label="' . esc_attr__('Choose List', 'avpvh-filelist') . '">';
			foreach($eeManageable as $eeNavID) {
				$eeNavSettings = get_option('avpvh_flst_settings_' . $eeNavID, array());
				$eeOutput .= '
					<option value="' . (int) $eeNavID . '"' . selected($eeNavID, (int) $eeSFL->eeListID, false) . '>' . esc_html(stripslashes($eeNavSettings['ListTitle'] ?? '')) . '</option>';
			}
			$eeOutput .= '
				</select>
				<noscript><button type="submit" class="button">' . esc_html__('Go', 'avpvh-filelist') . '</button></noscript>
			</form>';
		}

		// Verify nonce for admin tab navigation (Recommended security practice)
		$eeTabNonceValid = isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ee-admin-navigation');
		if (isset($_GET['tab']) && !$eeTabNonceValid) {
			avpvh_flst_debug_log("Admin tab navigation nonce verification failed for security logging", 'Admin');
		}

		if(!isset($_GET['tab'])) { $eeShowShortcodeBlock = TRUE; } else { $eeShowShortcodeBlock = FALSE; }
		if(isset($_GET['tab'])) { if($_GET['tab'] == 'list') { $eeShowShortcodeBlock = TRUE; } }

		if($eeShowShortcodeBlock) { // Only show this on the list tab

			 $eeOutput .= '

			<div class="eeShortCodeOps">

			<div class="eeFlex">

				<input class="eeFlex3" type="text" name="eeSFL_ShortCode" value="[eeSFL';

			if((int) $eeSFL->eeListID !== 1) {  $eeOutput .= ' list=\'' . (int) $eeSFL->eeListID . '\''; }
			 $eeOutput .= ']" id="eeSFL_ShortCode"><button id="eeCopytoClipboard" class="button eeFlex1">' . __('Copy', 'avpvh-filelist') . '</button>

			</div>

			<p><small>' . __('Place this shortcode on a page, post or widget.', 'avpvh-filelist') . '</small></p>

			</div>';

		}

	 $eeOutput .= '

	</div>

	</header>

';

// User Messaging
 $eeOutput .= $eeSFL->results_notification();

?>