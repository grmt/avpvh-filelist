<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List - Multiple file lists
// Copyright 2026 grmt, part of AV-PvH File List: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html
//
// Each list keeps its settings in eeSFL_Settings_{ID} and its file cache in eeSFL_FileList_{ID},
// as before. This file adds the registry of list IDs on top of that.
//
// IDs are never reused: a shortcode such as [eeSFL list="3"] left on a page after list 3 was
// deleted must not start showing a new, unrelated list.



/**
 * IDs of all file lists, ascending. List 1 always exists.
 *
 * The first call on a site discovers lists left by the old File Access Manager add-on
 * (eeSFL_Settings_N options) so they appear without any migration step.
 *
 * @return int[]
 */
function avpvh_flst_get_list_ids() {

	$eeIDs = get_option('avpvh_flst_list_ids');

	if( !is_array($eeIDs) ) {
		$eeIDs = avpvh_flst_discover_list_ids();
		update_option('avpvh_flst_list_ids', $eeIDs);
	}

	$eeIDs = array_map('intval', $eeIDs);
	$eeIDs[] = 1;
	$eeIDs = array_values(array_unique(array_filter($eeIDs, function($eeID) { return $eeID >= 1; })));
	sort($eeIDs);

	return $eeIDs;
}


/**
 * Find list IDs from existing eeSFL_Settings_N options.
 *
 * @return int[]
 */
function avpvh_flst_discover_list_ids() {

	global $wpdb;

	$eeIDs = array(1);

	if( !isset($wpdb) || !is_object($wpdb) ) { return $eeIDs; }

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time discovery, result is stored in eeSFL_ListIDs
	$eeNames = $wpdb->get_col( $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('avpvh_flst_settings_') . '%') );

	foreach( (array) $eeNames as $eeName ) {
		if( preg_match('/^eeSFL_Settings_(\d+)$/', $eeName, $eeMatch) ) {
			$eeIDs[] = (int) $eeMatch[1];
		}
	}

	return array_values(array_unique($eeIDs));
}


/**
 * @param mixed $eeListID
 * @return bool
 */
function avpvh_flst_list_exists($eeListID) {
	return is_numeric($eeListID) && in_array((int) $eeListID, avpvh_flst_get_list_ids(), true);
}


/**
 * Reserve the next unused list ID. IDs only ever go up, also after a list is deleted.
 *
 * @return int
 */
function avpvh_flst_next_list_id() {

	$eeNext = (int) get_option('avpvh_flst_next_list_id', 0);
	$eeNext = max($eeNext, max(avpvh_flst_get_list_ids()) + 1);

	update_option('avpvh_flst_next_list_id', $eeNext + 1);

	return $eeNext;
}


/**
 * Do two list folders overlap? Equal folders overlap, and so does a folder inside another:
 * the outer list would otherwise show (and expose) the inner list's files.
 *
 * @param string $eeDirA Folder relative to the site root
 * @param string $eeDirB Folder relative to the site root
 * @return bool
 */
function avpvh_flst_dirs_overlap($eeDirA, $eeDirB) {

	$eeNormalise = function($eeDir) {
		$eeDir = trim(str_replace('\\', '/', (string) $eeDir), '/');
		return $eeDir === '' ? '' : strtolower($eeDir) . '/';
	};

	$eeA = $eeNormalise($eeDirA);
	$eeB = $eeNormalise($eeDirB);

	if( $eeA === '' || $eeB === '' ) { return TRUE; } // The site root contains everything

	return strpos($eeA, $eeB) === 0 || strpos($eeB, $eeA) === 0;
}


/**
 * The first list (other than $eeExceptID) whose folder overlaps $eeDir, or 0.
 *
 * @param string $eeDir
 * @param int    $eeExceptID
 * @return int
 */
function avpvh_flst_find_overlapping_list($eeDir, $eeExceptID = 0) {

	foreach( avpvh_flst_get_list_ids() as $eeID ) {

		if( $eeID === (int) $eeExceptID ) { continue; }

		$eeSettings = get_option('avpvh_flst_settings_' . $eeID);
		if( !empty($eeSettings['FileListDir']) && avpvh_flst_dirs_overlap($eeDir, $eeSettings['FileListDir']) ) {
			return $eeID;
		}
	}

	return 0;
}


/**
 * Settings for a new list: list 1's settings with a new title and folder. The new list starts
 * closed (admins only, no uploads, no front-end management) until its settings are reviewed.
 *
 * @param array  $eeBase  Settings to start from (list 1's)
 * @param string $eeTitle
 * @param string $eeDir   Validated folder relative to the site root
 * @return array
 */
function avpvh_flst_new_list_settings($eeBase, $eeTitle, $eeDir) {

	$eeSettings = is_array($eeBase) ? $eeBase : array();

	$eeSettings['ListTitle'] = $eeTitle;
	$eeSettings['FileListDir'] = $eeDir;
	$eeSettings['ShowList'] = 'ADMIN';
	$eeSettings['AllowUploads'] = 'NO';
	$eeSettings['AllowFrontManage'] = 'NO';

	unset($eeSettings['FileListURL']); // Derived from FileListDir when the settings load

	return $eeSettings;
}


/**
 * Create a file list.
 *
 * @param string $eeTitle
 * @param string $eeDir Folder relative to the site root; created if missing
 * @return int|string The new list ID, or an error message
 */
function avpvh_flst_create_list($eeTitle, $eeDir) {

	global $eeSFL;

	$eeTitle = trim(sanitize_text_field($eeTitle));
	if( $eeTitle === '' ) { return __('Give the list a name.', 'avpvh-filelist'); }

	$eeDir = avpvh_flst_validate_file_list_dir($eeDir);
	if( !$eeDir ) { return __('This folder cannot be used.', 'avpvh-filelist'); }

	$eeOverlap = avpvh_flst_find_overlapping_list($eeDir);
	if( $eeOverlap ) {
		/* translators: %d: ID of the existing file list */
		return sprintf(__('This folder overlaps the folder of list %d. Each list needs its own folder.', 'avpvh-filelist'), $eeOverlap);
	}

	if( !avpvh_flst_file_list_dir_check($eeDir) ) { return __('The folder could not be created.', 'avpvh-filelist'); }

	$eeBase = array_merge($eeSFL->eeDefaultListSettings, (array) get_option('avpvh_flst_settings_1', array()));
	$eeSettings = avpvh_flst_new_list_settings($eeBase, $eeTitle, $eeDir);

	$eeListID = avpvh_flst_next_list_id();
	update_option('avpvh_flst_settings_' . $eeListID, $eeSettings);

	$eeIDs = avpvh_flst_get_list_ids();
	$eeIDs[] = $eeListID;
	update_option('avpvh_flst_list_ids', $eeIDs);

	// Protect the new folder for its (admins only) visibility setting
	avpvh_flst_with_list($eeListID, function() use ($eeSettings) {
		avpvh_flst_limit_dir_access($eeSettings['ShowList']);
	});

	avpvh_flst_debug_log("Created list $eeListID: $eeTitle ($eeDir)", 'Lists', $eeListID);

	return $eeListID;
}


/**
 * Remove a file list. The files stay on disk; only the list's settings and cache go.
 *
 * @param int $eeListID
 * @return bool
 */
function avpvh_flst_delete_list($eeListID) {

	$eeListID = (int) $eeListID;
	if( $eeListID <= 1 || !avpvh_flst_list_exists($eeListID) ) { return FALSE; }

	delete_option('avpvh_flst_settings_' . $eeListID);
	delete_option('avpvh_flst_files_' . $eeListID);

	$eeIDs = array_values(array_diff(avpvh_flst_get_list_ids(), array($eeListID)));
	update_option('avpvh_flst_list_ids', $eeIDs);

	avpvh_flst_debug_log("Deleted list $eeListID (files kept on disk)", 'Lists', $eeListID);

	return TRUE;
}


/**
 * Run $eeCallback with $eeSFL switched to another list, then switch back. Many helpers read
 * the current list from the global object.
 *
 * @param int      $eeListID
 * @param callable $eeCallback
 * @return mixed The callback's return value
 */
function avpvh_flst_with_list($eeListID, $eeCallback) {

	global $eeSFL;

	$eeSavedID = $eeSFL->eeListID;
	$eeSavedSettings = $eeSFL->eeListSettings;

	$eeSFL->get_settings($eeListID);
	$eeSFL->eeListID = (int) $eeListID;

	try {
		return $eeCallback();
	} finally {
		$eeSFL->eeListID = $eeSavedID;
		$eeSFL->eeListSettings = $eeSavedSettings;
	}
}


/**
 * The capability needed to manage a list in wp-admin, from its AdminRole setting.
 *
 * @param int $eeListID
 * @return string
 */
function avpvh_flst_list_admin_capability($eeListID = 1) {

	$eeSettings = get_option('avpvh_flst_settings_' . (int) $eeListID, array());
	$eeAdminRole = isset($eeSettings['AdminRole']) ? (int) $eeSettings['AdminRole'] : 5;

	switch ( $eeAdminRole ) {
		case 1: return 'read';
		case 2: return 'edit_posts';
		case 3: return 'publish_posts';
		case 4: return 'edit_others_pages';
		case 5: return 'activate_plugins';
		default: return 'edit_posts';
	}
}


/**
 * The lists the current user may manage in wp-admin.
 *
 * @return int[]
 */
function avpvh_flst_manageable_list_ids() {

	return array_values(array_filter(avpvh_flst_get_list_ids(), function($eeID) {
		return current_user_can(avpvh_flst_list_admin_capability($eeID));
	}));
}


/**
 * The capability for the admin menu: the least privileged AdminRole of any list, so everyone
 * who manages at least one list sees the menu. Each page then checks the list it shows.
 *
 * @return string
 */
function avpvh_flst_menu_capability() {

	$eeRank = array('read' => 1, 'edit_posts' => 2, 'publish_posts' => 3, 'edit_others_pages' => 4, 'activate_plugins' => 5);
	$eeBest = 'activate_plugins';

	foreach( avpvh_flst_get_list_ids() as $eeID ) {
		$eeCap = avpvh_flst_list_admin_capability($eeID);
		if( $eeRank[$eeCap] < $eeRank[$eeBest] ) { $eeBest = $eeCap; }
	}

	return $eeBest;
}


/**
 * The list to show in wp-admin: the requested eeListID if the user may manage it, otherwise
 * the first list they may manage.
 *
 * @return int 0 when the user may not manage any list
 */
function avpvh_flst_admin_list_id() {

	$eeAllowed = avpvh_flst_manageable_list_ids();
	if( !$eeAllowed ) { return 0; }

	// A posted form names the list it was shown for; that wins over the URL, so settings are
	// always saved to the right list. Each form handler checks its own nonce.
	// phpcs:disable WordPress.Security.NonceVerification -- routing only, access is checked against $eeAllowed
	if( isset($_POST['eeListID']) ) {
		$eeRequested = absint(wp_unslash($_POST['eeListID']));
	} else {
		$eeRequested = isset($_GET['eeListID']) ? absint(wp_unslash($_GET['eeListID'])) : 0;
	}
	// phpcs:enable

	return in_array($eeRequested, $eeAllowed, true) ? $eeRequested : $eeAllowed[0];
}
