<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html


 $eeOutput .= '

<footer class="eeClearFix">';

	 $eeOutput .= '<p id="eeFooterImportant" class="eeHide">' . __('IMPORTANT: Allowing the public to upload files to your web server comes with risk.', 'avpvh-filelist') . ' ' .
	__('Please go to Upload Settings and ensure that you only use the file types that you absolutely need.', 'avpvh-filelist') . ' ' .
	__('Open each file submitted carefully.', 'avpvh-filelist') . '</p>

	<a href="https://github.com/grmt/avpvh-filelist#readme" target="_blank" rel="noopener">' . __('Plugin Documentation', 'avpvh-filelist') . '</a>
	<a href="https://github.com/grmt/avpvh-filelist" target="_blank" rel="noopener">' . __('Plugin Website', 'avpvh-filelist') . '</a>
	<a href="https://github.com/grmt/avpvh-filelist/issues" target="_blank" rel="noopener">' . __('Give Feedback', 'avpvh-filelist') . '</a>
	<a class="eeCaution" href="#" id="eeFooterImportantLink">' . __('Caution', 'avpvh-filelist') . '</a>

	<br class="eeClear" />

	<p class="ee-plugin-credits">' . sprintf(
		/* translators: 1: link to the original author's website, 2: link to this version's repository */
		__('Based on Simple File List by Mitchell Bennis (%1$s). This version is maintained by grmt (%2$s) and is not affiliated with the original author.', 'avpvh-filelist'),
		'<a href="https://simplefilelist.com" target="_blank" rel="noopener">simplefilelist.com</a>',
		'<a href="https://github.com/grmt/avpvh-filelist" target="_blank" rel="noopener">GitHub</a>'
	) . '</p>

	<p class="ee-plugin-version">' . __('Plugin Version', 'avpvh-filelist') . ': ' . AVPVH_FILELIST_VERSION;


	if( defined('eeSFLE_Version') ) {  $eeOutput .= '<br />

		' . __('Email Extension', 'avpvh-filelist') . ': ' . eeSFLE_Version;
	}

	 $eeOutput .= '</p>

</footer>
</main><!-- END .eeSFL_Admin -->
</div><!-- END .wrap -->
<!-- END SFL ADMIN -->


';

$_POST = array();

?>