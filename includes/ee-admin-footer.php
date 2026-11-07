<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


 $eeOutput .= '

<footer class="eeClearFix">';

	 $eeOutput .= '<p id="eeFooterImportant" class="eeHide">' . __('IMPORTANT: Allowing the public to upload files to your web server comes with risk.', 'simple-file-list') . ' ' .
	__('Please go to Upload Settings and ensure that you only use the file types that you absolutely need.', 'simple-file-list') . ' ' .
	__('Open each file submitted carefully.', 'simple-file-list') . '</p>

	<a href="https://simplefilelist.com/documentation/" target="_blank">' . __('Plugin Documentation', 'simple-file-list') . '</a>
	<a href="https://simplefilelist.com/?pr=free" target="_blank">' . __('Plugin Website', 'simple-file-list') . '</a>
	<a href="https://simplefilelist.com/give-feedback/?pr=free" target="_blank">' . __('Give Feedback', 'simple-file-list') . '</a>
	<a class="eeCaution" href="#" id="eeFooterImportantLink">' . __('Caution', 'simple-file-list') . '</a>

	<br class="eeClear" />

	<p class="ee-plugin-version">' . __('Plugin Version', 'simple-file-list') . ': ' . eeSFL_Version;

	if( defined('eeSFLA_Version') ) {  $eeOutput .= '<br />

		' . __('Access Extension', 'simple-file-list') . ': ' . eeSFLA_Version;
	}

	if( defined('eeSFLE_Version') ) {  $eeOutput .= '<br />

		' . __('Email Extension', 'simple-file-list') . ': ' . eeSFLE_Version;
	}

	 $eeOutput .= '</p>

</footer>
</main><!-- END .eeSFL_Admin -->
</div><!-- END .wrap -->
<!-- END SFL ADMIN -->


';

$_POST = array();

?>