<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

if($eeShowOps) {

	// Bulk Edit Input Display
	 $eeOutput .= '<div class="eeSFL_ListOpsBar">

	<form action="' . $eeURL . '" method="POST">

	<input type="hidden" name="ee" value="1" />
	<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />
	<input type="hidden" id="eeSFL_FileOpsFiles" name="eeSFL_FileOpsFiles" value="" />';

	 $eeOutput .= wp_nonce_field( 'avpvh-filelist-file-ops-bar', 'avpvh-filelist-file-ops-bar-nonce', TRUE, FALSE);

	 $eeOutput .= '

	<select id="eeSFL_FileOpsAction" name="eeSFL_FileOpsAction">
		<option value="Description" selected="selected">' . __('Apply Description', 'avpvh-filelist') . '</option>
		<option value="Download">' . __('Download Items', 'avpvh-filelist') . '</option>
		<option value="Delete" class="eeWarning">' . __('Delete Items', 'avpvh-filelist') . '</option>
	</select>

	<input type="text" id="eeSFL_FileOpsActionInput" name="eeSFL_Description" value="" placeholder="" required="required" />

	<span class="eeHide" id="eeSFL_ZipFileName">' . __('File-Archive', 'avpvh-filelist') . '-' . gmdate('Y-m-d-H-i') . '</span>
	<span class="eeHide" id="eeSFL_DeleteText">' . __('Delete all of the selected items.', 'avpvh-filelist') . '</span>
	<span class="eeHide" id="eeSFL_DescriptionPlaceholder">' . __('Insert description text here', 'avpvh-filelist') . '</span>

	<input class="button" type="submit" id="eeSFL_ListOpsBarGo" name="eeSFL_ListOpsBarGo" value="' . __('GO', 'avpvh-filelist') . '" />

	<br class="eeClearFix" />

	</form>
	</div>';


} elseif($eeSFL->eeListSettings['AllowBulkFileDownload'] == 'YES') { // Bulk Downloading Only

	// Bulk Download Input Display
	 $eeOutput .= '<div class="eeSFL_BulkDownloadBar">

	<form action="' . $eeURL . '" method="POST">

	<input type="hidden" name="ee" value="1" />
	<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />
	<input type="hidden" name="eeSFL_FileOpsAction" value="Download" />
	<input type="hidden" id="eeSFL_FileOpsFiles" name="eeSFL_FileOpsFiles" value="" />';

	 $eeOutput .= wp_nonce_field( 'avpvh-filelist-file-ops-bar', 'avpvh-filelist-file-ops-bar-nonce', TRUE, FALSE);

	 $eeOutput .= '<label for="eeSFL_FileOpsActionInput">' . __('Download Files', 'avpvh-filelist') . '</label><input required type="text" id="eeSFL_FileOpsActionInput" name="eeSFL_ZipFileName" value="' . __('File-Archive', 'avpvh-filelist') . '-' . gmdate('Y-m-d-H-i') . '" />

	<input class="button" type="submit" id="eeSFL_ListOpsBarGo" name="eeSFL_ListOpsBarGo" value="' . __('GO', 'avpvh-filelist') . '" />

	<br class="eeClearFix" />

	</form>
	</div>';



}

?>