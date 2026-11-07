<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Multiple file lists
// Copyright 2026 grmt, part of Simple File List (grmt): https://github.com/grmt/simple-file-list
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
function eeSFL_GetListIDs() {

	$eeIDs = get_option('eeSFL_ListIDs');

	if( !is_array($eeIDs) ) {
		$eeIDs = eeSFL_DiscoverListIDs();
		update_option('eeSFL_ListIDs', $eeIDs);
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
function eeSFL_DiscoverListIDs() {

	global $wpdb;

	$eeIDs = array(1);

	if( !isset($wpdb) || !is_object($wpdb) ) { return $eeIDs; }

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time discovery, result is stored in eeSFL_ListIDs
	$eeNames = $wpdb->get_col( $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('eeSFL_Settings_') . '%') );

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
function eeSFL_ListExists($eeListID) {
	return is_numeric($eeListID) && in_array((int) $eeListID, eeSFL_GetListIDs(), true);
}


/**
 * Reserve the next unused list ID. IDs only ever go up, also after a list is deleted.
 *
 * @return int
 */
function eeSFL_NextListID() {

	$eeNext = (int) get_option('eeSFL_NextListID', 0);
	$eeNext = max($eeNext, max(eeSFL_GetListIDs()) + 1);

	update_option('eeSFL_NextListID', $eeNext + 1);

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
function eeSFL_DirsOverlap($eeDirA, $eeDirB) {

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
function eeSFL_FindOverlappingList($eeDir, $eeExceptID = 0) {

	foreach( eeSFL_GetListIDs() as $eeID ) {

		if( $eeID === (int) $eeExceptID ) { continue; }

		$eeSettings = get_option('eeSFL_Settings_' . $eeID);
		if( !empty($eeSettings['FileListDir']) && eeSFL_DirsOverlap($eeDir, $eeSettings['FileListDir']) ) {
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
function eeSFL_NewListSettings($eeBase, $eeTitle, $eeDir) {

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
function eeSFL_CreateList($eeTitle, $eeDir) {

	global $eeSFL;

	$eeTitle = trim(sanitize_text_field($eeTitle));
	if( $eeTitle === '' ) { return __('Give the list a name.', 'simple-file-list'); }

	$eeDir = eeSFL_ValidateFileListDir($eeDir);
	if( !$eeDir ) { return __('This folder cannot be used.', 'simple-file-list'); }

	$eeOverlap = eeSFL_FindOverlappingList($eeDir);
	if( $eeOverlap ) {
		/* translators: %d: ID of the existing file list */
		return sprintf(__('This folder overlaps the folder of list %d. Each list needs its own folder.', 'simple-file-list'), $eeOverlap);
	}

	if( !eeSFL_FileListDirCheck($eeDir, FALSE) ) { return __('The folder could not be created.', 'simple-file-list'); }

	$eeBase = array_merge($eeSFL->eeDefaultListSettings, (array) get_option('eeSFL_Settings_1', array()));
	$eeSettings = eeSFL_NewListSettings($eeBase, $eeTitle, $eeDir);

	$eeListID = eeSFL_NextListID();
	update_option('eeSFL_Settings_' . $eeListID, $eeSettings);

	$eeIDs = eeSFL_GetListIDs();
	$eeIDs[] = $eeListID;
	update_option('eeSFL_ListIDs', $eeIDs);

	// Protect the new folder for its (admins only) visibility setting
	eeSFL_WithList($eeListID, function() use ($eeSettings) {
		eeSFL_LimitDirAccess($eeSettings['ShowList']);
	});

	eeSFL_Debug_Log("Created list $eeListID: $eeTitle ($eeDir)", 'Lists', $eeListID);

	return $eeListID;
}


/**
 * Remove a file list. The files stay on disk; only the list's settings and cache go.
 *
 * @param int $eeListID
 * @return bool
 */
function eeSFL_DeleteList($eeListID) {

	$eeListID = (int) $eeListID;
	if( $eeListID <= 1 || !eeSFL_ListExists($eeListID) ) { return FALSE; }

	delete_option('eeSFL_Settings_' . $eeListID);
	delete_option('eeSFL_FileList_' . $eeListID);

	$eeIDs = array_values(array_diff(eeSFL_GetListIDs(), array($eeListID)));
	update_option('eeSFL_ListIDs', $eeIDs);

	eeSFL_Debug_Log("Deleted list $eeListID (files kept on disk)", 'Lists', $eeListID);

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
function eeSFL_WithList($eeListID, $eeCallback) {

	global $eeSFL;

	$eeSavedID = $eeSFL->eeListID;
	$eeSavedSettings = $eeSFL->eeListSettings;

	$eeSFL->eeSFL_GetSettings($eeListID);
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
function eeSFL_ListAdminCapability($eeListID = 1) {

	$eeSettings = get_option('eeSFL_Settings_' . (int) $eeListID, array());
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
function eeSFL_ManageableListIDs() {

	return array_values(array_filter(eeSFL_GetListIDs(), function($eeID) {
		return current_user_can(eeSFL_ListAdminCapability($eeID));
	}));
}


/**
 * The capability for the admin menu: the least privileged AdminRole of any list, so everyone
 * who manages at least one list sees the menu. Each page then checks the list it shows.
 *
 * @return string
 */
function eeSFL_MenuCapability() {

	$eeRank = array('read' => 1, 'edit_posts' => 2, 'publish_posts' => 3, 'edit_others_pages' => 4, 'activate_plugins' => 5);
	$eeBest = 'activate_plugins';

	foreach( eeSFL_GetListIDs() as $eeID ) {
		$eeCap = eeSFL_ListAdminCapability($eeID);
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
function eeSFL_AdminListID() {

	$eeAllowed = eeSFL_ManageableListIDs();
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
