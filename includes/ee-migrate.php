<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List - Moving a site over from Simple File List
// Copyright 2026 grmt, part of AV-PvH File List: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

// This plugin was Simple File List (folder simple-file-list, options eeSFL_*, shortcode [eeSFL]).
// On the first request after it is installed, avpvh_flst_migrate_legacy():
//  - copies the old options to their new names (the old ones stay, so a rollback still works),
//  - rewrites [eeSFL] to [avpvh_filelist] in post content and text and block widgets,
//  - deactivates the old plugin.
// [eeSFL] stays registered as well, for places the migration doesn't reach (page builder data).
// The list folders, their files and the download links don't change.


/**
 * The new name for an option of Simple File List, or '' when it isn't carried over
 * (logs, versions and task flags of the old plugin).
 *
 * @param string $eeOld
 * @return string
 */
function avpvh_flst_new_option_name($eeOld) {

	$eeFixed = array(
		'eeSFL_ListIDs'        => 'avpvh_flst_list_ids',
		'eeSFL_NextListID'     => 'avpvh_flst_next_list_id',
		'eeSFL_AccessSettings' => 'avpvh_flst_access_settings',
		'eeSFL_LDAP'           => 'avpvh_flst_ldap',
		'eeSFL_Supported'      => 'avpvh_flst_supported',
		'eeSFL_Confirm'        => 'avpvh_flst_confirm',
		'eeSFL_Dismiss'        => 'avpvh_flst_dismiss',
	);
	if( isset($eeFixed[$eeOld]) ) { return $eeFixed[$eeOld]; }

	if( preg_match('/^eeSFL_Settings_(\d+)$/', $eeOld, $eeMatch) ) { return 'avpvh_flst_settings_' . $eeMatch[1]; }
	if( preg_match('/^eeSFL_FileList_(\d+)$/', $eeOld, $eeMatch) ) { return 'avpvh_flst_files_' . $eeMatch[1]; }

	return '';
}


/**
 * [eeSFL ...] and [/eeSFL] become [avpvh_filelist ...] and [/avpvh_filelist].
 *
 * @param string $eeContent
 * @return string
 */
function avpvh_flst_rewrite_shortcodes($eeContent) {
	return preg_replace('/\[(\/?)eeSFL(?=[\s\]\/])/', '[$1avpvh_filelist', (string) $eeContent);
}


/**
 * Rewrite the shortcode in every string of a (nested) array.
 *
 * @param mixed $eeValue
 * @return mixed
 */
function avpvh_flst_rewrite_shortcodes_deep($eeValue) {
	if( is_array($eeValue) ) { return array_map('avpvh_flst_rewrite_shortcodes_deep', $eeValue); }
	return is_string($eeValue) ? avpvh_flst_rewrite_shortcodes($eeValue) : $eeValue;
}


/**
 * Rewrite [eeSFL] in post content (not revisions) and in text and block widgets.
 *
 * @return int Number of posts and widget options changed
 */
function avpvh_flst_migrate_shortcodes() {

	global $wpdb;
	$eeChanged = 0;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration over all posts
	$eePosts = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE %s",
		'%' . $wpdb->esc_like('[eeSFL') . '%'
	));

	foreach( (array) $eePosts as $eePost ) {
		$eeNew = avpvh_flst_rewrite_shortcodes($eePost->post_content);
		if( $eeNew === $eePost->post_content ) { continue; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- direct update: no new revision, no save hooks
		$wpdb->update($wpdb->posts, array('post_content' => $eeNew), array('ID' => (int) $eePost->ID));
		clean_post_cache((int) $eePost->ID);
		$eeChanged++;
	}

	foreach( array('widget_text', 'widget_block', 'widget_custom_html') as $eeOption ) {
		$eeWidgets = get_option($eeOption);
		if( !is_array($eeWidgets) ) { continue; }
		$eeNew = avpvh_flst_rewrite_shortcodes_deep($eeWidgets);
		if( $eeNew !== $eeWidgets ) {
			update_option($eeOption, $eeNew);
			$eeChanged++;
		}
	}

	return $eeChanged;
}


/**
 * Move a site over from Simple File List, once. Runs before anything reads the settings.
 *
 * @return void
 */
function avpvh_flst_migrate_legacy() {

	if( get_option('avpvh_flst_migrated') ) { return; }

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration; options are found by prefix
	$eeRows = $wpdb->get_results($wpdb->prepare(
		"SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like('eeSFL_') . '%'
	));

	$eeCopied = 0;
	foreach( (array) $eeRows as $eeRow ) {
		$eeNew = avpvh_flst_new_option_name($eeRow->option_name);
		if( !$eeNew || get_option($eeNew, null) !== null ) { continue; } // Not carried over, or already there
		$eeValue = get_option($eeRow->option_name);
		if( is_array($eeValue) && ($eeValue['NotifyFromName'] ?? '') === 'Simple File List' ) {
			$eeValue['NotifyFromName'] = 'AV-PvH File List'; // The old default sender name
		}
		add_option($eeNew, $eeValue, '', in_array($eeRow->autoload, array('no', 'off'), TRUE) ? 'no' : 'yes');
		$eeCopied++;
	}

	// An existing install, not a new one: the new-install path would reset list 1's settings
	if( $eeCopied && get_option('avpvh_flst_settings_1') ) {
		update_option('avpvh_flst_version', AVPVH_FLST_VERSION);
	}

	$eeShortcodes = avpvh_flst_migrate_shortcodes();

	// The old plugin would show the same lists a second time, from its own copy of the settings
	if( !function_exists('is_plugin_active') ) { include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); }
	$eeOldPlugin = 'simple-file-list/simple-file-list.php';
	$eeDeactivated = is_plugin_active($eeOldPlugin);
	if( $eeDeactivated ) { deactivate_plugins($eeOldPlugin); }

	update_option('avpvh_flst_migrated', array(
		'version'     => AVPVH_FLST_VERSION,
		'time'        => time(),
		'options'     => $eeCopied,
		'shortcodes'  => $eeShortcodes,
		'deactivated' => $eeDeactivated,
		'notice'      => ($eeCopied || $eeShortcodes || $eeDeactivated),
	), 'no');

	avpvh_flst_debug_log('Moved over from Simple File List: ' . $eeCopied . ' options, ' . $eeShortcodes . ' posts or widgets', 'Updating');
}


/**
 * Tell an administrator once what the move from Simple File List did.
 *
 * @return void
 */
function avpvh_flst_migration_notice() {

	$eeMigrated = get_option('avpvh_flst_migrated');
	if( !is_array($eeMigrated) || empty($eeMigrated['notice']) || !current_user_can('manage_options') ) { return; }

	$eeMigrated['notice'] = FALSE;
	update_option('avpvh_flst_migrated', $eeMigrated, 'no');

	echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('AV-PvH File List:', 'avpvh-filelist') . '</strong> ' .
		esc_html(sprintf(
			/* translators: 1: number of settings and lists, 2: number of pages, posts or widgets */
			__('Moved over from Simple File List: %1$d settings and lists, and the shortcode in %2$d pages, posts or widgets. The Simple File List plugin is deactivated and can be deleted; its data is kept.', 'avpvh-filelist'),
			(int) $eeMigrated['options'],
			(int) $eeMigrated['shortcodes']
		)) . '</p></div>';
}
add_action('admin_notices', 'avpvh_flst_migration_notice');
