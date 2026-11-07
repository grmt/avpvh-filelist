<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/simple-file-list
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

if($eeShowOps) {

	// Bulk Edit Input Display
	 $eeOutput .= '<div class="eeSFL_ListOpsBar">

	<form action="' . $eeURL . '" method="POST">

	<input type="hidden" name="ee" value="1" />
	<input type="hidden" name="eeListID" value="' . $eeSFL->eeListID . '" />
	<input type="hidden" id="eeSFL_FileOpsFiles" name="eeSFL_FileOpsFiles" value="" />';

	 $eeOutput .= wp_nonce_field( 'ee-simple-file-list-file-ops-bar', 'ee-simple-file-list-file-ops-bar-nonce', TRUE, FALSE);

	 $eeOutput .= '

	<select id="eeSFL_FileOpsAction" name="eeSFL_FileOpsAction">
		<option value="Description" selected="selected">' . __('Apply Description', 'simple-file-list') . '</option>
		<option value="Download">' . __('Download Items', 'simple-file-list') . '</option>
		<option value="Delete" class="eeWarning">' . __('Delete Items', 'simple-file-list') . '</option>
	</select>

	<input type="text" id="eeSFL_FileOpsActionInput" name="eeSFL_Description" value="" placeholder="" required="required" />

	<span class="eeHide" id="eeSFL_ZipFileName">' . __('File-Archive', 'simple-file-list') . '-' . gmdate('Y-m-d-H-i') . '</span>
	<span class="eeHide" id="eeSFL_DeleteText">' . __('Delete all of the selected items.', 'simple-file-list') . '</span>
	<span class="eeHide" id="eeSFL_DescriptionPlaceholder">' . __('Insert description text here', 'simple-file-list') . '</span>

	<input class="button" type="submit" id="eeSFL_ListOpsBarGo" name="eeSFL_ListOpsBarGo" value="' . __('GO', 'simple-file-list') . '" />

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

	 $eeOutput .= wp_nonce_field( 'ee-simple-file-list-file-ops-bar', 'ee-simple-file-list-file-ops-bar-nonce', TRUE, FALSE);

	 $eeOutput .= '<label for="eeSFL_FileOpsActionInput">' . __('Download Files', 'simple-file-list') . '</label><input required type="text" id="eeSFL_FileOpsActionInput" name="eeSFL_ZipFileName" value="' . __('File-Archive', 'simple-file-list') . '-' . gmdate('Y-m-d-H-i') . '" />

	<input class="button" type="submit" id="eeSFL_ListOpsBarGo" name="eeSFL_ListOpsBarGo" value="' . __('GO', 'simple-file-list') . '" />

	<br class="eeClearFix" />

	</form>
	</div>';



}

?>