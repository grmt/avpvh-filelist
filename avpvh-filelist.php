<?php

/**
 * @package AV-PvH File List
 */
/*
Plugin Name: AV-PvH File List
Plugin URI: https://github.com/grmt/avpvh-filelist
Description: File lists with uploads, access control, search and paging. Based on Simple File List by Mitchell Bennis (simplefilelist.com); not affiliated with the original author.
Author: grmt
Version: 1.0.0
Requires at least: 7.1
Requires PHP: 8.4
Author URI: https://github.com/grmt
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: avpvh-filelist
Domain Path: /languages
Update URI: https://github.com/grmt/avpvh-filelist
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once __DIR__ . '/includes/ee-github-updater.php';

// CONSTANTS
if(!defined('AVPVH_FLST_VERSION')) { define('AVPVH_FLST_VERSION', '1.0.0'); }
define('AVPVH_FLST_NAME', 'AV-PvH File List');
define('AVPVH_FLST_SLUG', 'avpvh-filelist');
define('AVPVH_FLST_PRODUCT', 'Free');
define('AVPVH_FLST_MENU_TITLE', 'File List');

// Common
define('AVPVH_FLST_PREFIX', 'avpvh_flst');
define('AVPVH_FLST_DEFAULT_DIR', 'avpvh-filelist/'); // Default Upload Directory
define('AVPVH_FLST_GO', wp_date('Y-m-d h:m:s') ); // Log Entry Key


// GLOBAL VARIABLES
$eeSFL = new stdClass(); // Our Main Object
$eeSFLU = new stdClass(); // Our Upload Class
$eeSFLE = FALSE; // Email Sharing Object
$eeSFLM = TRUE; // Media Player
$eeSFL_Upload = FALSE; // File Uploading
$eeSFL_Thumbs = FALSE; // Thumbnail Creation and Management Object
$eeSFL_HideName = FALSE;
$eeSFL_HideType = FALSE;
$eeSFL_VarsForJS = array(); // Translated strings we pass to JavaScript
$eeSFL_StartTime = 0;
$eeSFL_MemoryUsedStart = 0;
$eeSFL_Extensions = array();



// =============================================================================
// Plugin Setup
// =============================================================================
function avpvh_flst_setup() {

	global $eeSFL, $eeSFL_VarsForJS, $eeSFLE, $eeSFLU;

	// Admin-only startup tasks: plugin state checks, conflicting plugin deactivation, debug tools.
	// Guarded here to avoid loading wp-admin/includes/plugin.php on every frontend page load.
	if(is_admin()) {

		if(!function_exists('is_plugin_active')) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Purge stale registration options for extensions that are no longer installed/active.
		// This prevents leftover NAG/NO options from showing bogus alerts after an extension is removed.
		$eeSFL_ExtensionPlugins = array(
			'eeSFLS' => 'ee-simple-file-list-search/ee-simple-file-list-search.php',
			'eeSFLA' => 'ee-simple-file-list-access/ee-simple-file-list-access.php',
			'eeSFLE' => 'ee-simple-file-list-email/ee-simple-file-list-email.php',
		);
		foreach ($eeSFL_ExtensionPlugins as $eeExtPrefix => $eeExtPlugin) {
			if (!is_plugin_active($eeExtPlugin)) {
				delete_option($eeExtPrefix . '_Registration');
				delete_transient($eeExtPrefix . '_RegCheck');
			}
		}
		unset($eeSFL_ExtensionPlugins, $eeExtPrefix, $eeExtPlugin);

		// Load debug logging functions from Tools plugin (debug console output is in Tools plugin)
		// Only load if the Tools plugin is actually active
		if (is_plugin_active('ee-simple-file-list-tools/ee-simple-file-list-tools.php')) {
			$tools_debug_file = WP_PLUGIN_DIR . '/ee-simple-file-list-tools/includes/ee-tools-debug.php';
			if (file_exists($tools_debug_file)) {
				include_once($tools_debug_file);
			}
		}

		// Deactivate the Pro version if needed
		$eePlugin = 'ee-simple-file-list-pro/ee-simple-file-list-pro.php';
		if( is_plugin_active($eePlugin) ) {
			deactivate_plugins($eePlugin);
		}

		// Deactivate the old Email extension if needed (functionality now integrated into core)
		$eePlugin = 'ee-simple-file-list-email/ee-simple-file-list-email.php';
		if( is_plugin_active($eePlugin) ) {
			deactivate_plugins($eePlugin);
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
					esc_html__('AV-PvH File List:', 'avpvh-filelist') . '</strong> ' .
					esc_html__('The Email extension has been automatically deactivated because email functionality is now integrated into core.', 'avpvh-filelist') .
					'</p></div>';
			});
		}

		// Deactivate the old Media extension if needed (functionality now integrated into core)
		$eePlugin = 'ee-simple-file-list-media/ee-simple-file-list-media.php';
		if( is_plugin_active($eePlugin) ) {
			deactivate_plugins($eePlugin);
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
					esc_html__('AV-PvH File List:', 'avpvh-filelist') . '</strong> ' .
					esc_html__('The Media extension has been automatically deactivated because media functionality is now integrated into core.', 'avpvh-filelist') .
					'</p></div>';
			});
		}

		// Deactivate the old add-ons whose features are now built in: multiple lists and file access, and search
		foreach( array(
			'ee-simple-file-list-access/ee-simple-file-list-access.php' => __('File Access Manager', 'avpvh-filelist'),
			'ee-simple-file-list-search/ee-simple-file-list-search.php' => __('Search', 'avpvh-filelist'),
		) as $eePlugin => $eeAddOnName ) {
			if( is_plugin_active($eePlugin) ) {
				deactivate_plugins($eePlugin);
				add_action( 'admin_notices', function() use ($eeAddOnName) {
					echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
						esc_html__('AV-PvH File List:', 'avpvh-filelist') . '</strong> ' .
						/* translators: %s: name of the old add-on */
						esc_html(sprintf(__('The %s add-on has been deactivated because its features are now built in. Its lists and file access are kept.', 'avpvh-filelist'), $eeAddOnName)) .
						'</p></div>';
				});
			}
		}

	} // end is_admin()

	// Define debug logging stub (no-op on frontend; real implementation provided by Tools plugin above)
	if(!function_exists('avpvh_flst_debug_log')) { function avpvh_flst_debug_log($eeString, $eeCategory = '', $eeListID = 0) { return FALSE; } }
	avpvh_flst_debug_log("AV-PvH File List v" . AVPVH_FLST_VERSION . " initializing", 'Loading');

	// Translation strings to pass to javascript as eesfl_vars
	$eeProtocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
	$eeSFL_VarsForJS = array(
		'ajaxurl' => admin_url( 'admin-ajax.php', $eeProtocol ),
		'eeEditText' => __('Edit', 'avpvh-filelist'), // Edit link text
		'eeConfirmDeleteText' => __('Are you sure you want to delete this?', 'avpvh-filelist'), // Delete confirmation
		'eeCancelText' => __('Cancel', 'avpvh-filelist'),
		'eeCopyLinkText' => __('The Link Has Been Copied', 'avpvh-filelist'),
		'eeUploadLimitText' => __('Upload Limit', 'avpvh-filelist'),
		'eeFileTooLargeText' => __('This file is too large', 'avpvh-filelist'),
		'eeFileNoSizeText' => __('This file is empty', 'avpvh-filelist'),
		'eeFileNotAllowedText' => __('This file type is not allowed', 'avpvh-filelist'),
		'eeUploadErrorText' => __('Upload Failed', 'avpvh-filelist'),
		'eePleaseWaitText' => __('Please Wait', 'avpvh-filelist'),
		'eeFilesSelected' =>  __('Files Selected', 'avpvh-filelist'),

		// Back-End Only
		'eeShowText' => __('Show', 'avpvh-filelist'), // Shortcode Builder
		'eeHideText' => __('Hide', 'avpvh-filelist'),

		// Extensions
		'eeChooseListText' => __('Choose List', 'avpvh-filelist'),
	);


	// Get Class
	if(!class_exists('eeSFL')) {

		// Get Functions File
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-functions.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-lists.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-groups.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-access.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-search.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-migrate.php');

		// A site moving over from Simple File List: before anything reads the settings
		avpvh_flst_migrate_legacy();
		if( is_admin() ) {
			include_once(plugin_dir_path(__FILE__) . 'includes/ee-access-admin.php');
		}

		if(!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
			include_once(plugin_dir_path(__FILE__) . 'includes/ee-front-end.php');
		}

		// Main Class
		require_once(plugin_dir_path(__FILE__) . 'includes/ee-class.php');
		$eeSFL = new Avpvh_Flst_Main();

		// Load Upload Class (needed by GetEnv)
		require_once(plugin_dir_path(__FILE__) . 'includes/ee-class-uploads.php');
		$eeSFLU = new Avpvh_Flst_Uploads();

		// Initialize environment (requires $eeSFLU)
		$eeSFL->get_env();

		// Can we go on?
		$eeResult = $eeSFL->get_root_path();
		if($eeResult === FALSE) {

			avpvh_flst_debug_log("CRITICAL: Root path determination failed - hosting incompatibility", 'ERROR');

			add_action('admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p><strong>' . esc_html__('AV-PvH File List error:', 'avpvh-filelist') . '</strong> ' . esc_html__('This plugin cannot determine the correct file system paths on your hosting environment.', 'avpvh-filelist') . '</p>';
				echo '<p>' . esc_html__('This typically occurs on managed WordPress hosting platforms. Please contact support with your hosting provider details.', 'avpvh-filelist') . '</p>';
				echo '<p>' . esc_html__('This plugin will not work under this configuration.', 'avpvh-filelist') . '</p>';
				echo '</div>';
			});

			return; // Stop plugin execution
		}

		// Initialize performance tracking
		$eeSFL_StartTime = round( microtime(true) - (isset($_SERVER["REQUEST_TIME_FLOAT"]) ? sanitize_text_field(wp_unslash($_SERVER["REQUEST_TIME_FLOAT"])) : microtime(true)), 3);
		$eeSFL_MemoryUsedStart = memory_get_usage();

		// Load Email Sharing Class only when needed — on the frontend it is lazy-loaded
		// in the shortcode if AllowFrontSend is enabled.
		$eeNeedsEmailClass = is_admin()
			|| isset($_POST['eeSFLE_Send'])
			|| (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && sanitize_key(wp_unslash($_POST['action'])) === 'avpvh_flst_sendfile_job');
		if($eeNeedsEmailClass) {
			require_once(plugin_dir_path(__FILE__) . 'includes/ee-class-send.php');
			$eeSFLE = new Avpvh_Flst_Send();
		}

		// Set List ID: the main list, or in wp-admin the list chosen with ?eeListID=
		$eeSFL->eeListID = 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing; avpvh_flst_admin_list_id() checks access
		if( is_admin() && isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === AVPVH_FLST_SLUG ) {
			$eeSFL->eeListID = avpvh_flst_admin_list_id() ?: 1;
		}

		// Load Settings
		$eeSFL->get_settings($eeSFL->eeListID);

		// Email File Send Check
		if( is_object($eeSFLE) && method_exists($eeSFLE, 'send_files_email') && isset($_POST['eeSFLE_Send']) ) {
			if (!check_ajax_referer( 'eeSFL_SendNonce', 'eeSecurity', FALSE )) {
				// Reject — nonce missing or invalid in all contexts including admin-ajax
			} else {
				$eeSFLE->send_files_email(); // Sending Files
			}
		}

		// Install or Update if Needed
		if( is_admin() ) {
			avpvh_flst_version_check();
			avpvh_flst_debug_log("Version check completed.", 'Loading');
		}

		avpvh_flst_debug_log('- List ID = ' . $eeSFL->eeListID . ' --> Setup Loaded.', 'General', $eeSFL->eeListID);
		avpvh_flst_debug_log("Setup Complete for List ID: " . $eeSFL->eeListID, 'Loading');
		avpvh_flst_debug_log("Final memory usage: " . round(memory_get_usage()/1024/1024, 2) . "MB", 'Loading');

	} else {
		avpvh_flst_debug_log("eeSFL class already exists, skipping initialization", 'Loading');
	}

	return TRUE;
}


// =============================================================================
// WORDPRESS HOOKS REGISTRATION
// =============================================================================
// Note: These hooks must be registered after avpvh_flst_setup() runs and loads all includes

// Core Initialization
add_action('init', 'avpvh_flst_setup'); // Main setup - loads all classes and functions
add_action('init', function() { // Protected downloads, after avpvh_flst_setup() has found the site root
	if( function_exists('avpvh_flst_handle_file_request') ) { avpvh_flst_handle_file_request(); }
}, 20);
add_action('init', 'avpvh_flst_textdomain'); // Load textdomain at init to comply with WordPress 6.7+ requirements

// Asset Management
add_action('init', 'avpvh_flst_register_assets'); // Register scripts and styles
add_action('wp_enqueue_scripts', 'avpvh_flst_enqueue'); // Frontend assets
add_action('admin_enqueue_scripts', 'avpvh_flst_admin_head'); // Admin assets

// SFL Dashboard detection meta tag
add_action('wp_head', function() { echo '<meta name="avpvh-filelist" content="' . esc_attr(AVPVH_FLST_VERSION) . '">' . "\n"; });

// Admin Interface
add_action('admin_menu', 'avpvh_flst_admin_menu'); // Add admin menu
add_action('current_screen', 'avpvh_flst_set_admin_title'); // Set admin page title
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'avpvh_flst_action_plugin_links'); // Plugin page links

// AJAX Handlers
add_action('wp_ajax_avpvh_flst_upload_job', 'avpvh_flst_upload_job');
add_action('wp_ajax_nopriv_avpvh_flst_upload_job', 'avpvh_flst_upload_job');
add_action('wp_ajax_avpvh_flst_edit_job', 'avpvh_flst_edit_job');
add_action('wp_ajax_nopriv_avpvh_flst_edit_job', 'avpvh_flst_edit_job');
add_action('wp_ajax_avpvh_flst_sendfile_job', 'avpvh_flst_sendfile_job');
add_action('wp_ajax_nopriv_avpvh_flst_sendfile_job', 'avpvh_flst_sendfile_job');
add_action('wp_ajax_avpvh_flst_confirm', 'avpvh_flst_confirm');
add_action('wp_ajax_avpvh_flst_dismiss', 'avpvh_flst_dismiss'); // Acknowledge new features

// Shortcode: [avpvh_filelist]. [eeSFL] from Simple File List keeps working; the migration
// rewrites it in post content, but it may also sit in widgets or page builder data.
add_shortcode('avpvh_filelist', 'avpvh_flst_front_end');
add_shortcode('eeSFL', 'avpvh_flst_front_end');

// Language Enabler
function avpvh_flst_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for private plugins
    load_plugin_textdomain( 'avpvh-filelist', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}


// Plugin Activation
function avpvh_flst_activate() {

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	// Clear cached root path so it is re-verified after activation or update
	delete_transient('avpvh_flst_root_path');

	// Deactivate the Pro version if needed
	$eePlugin = 'ee-simple-file-list-pro/ee-simple-file-list-pro.php';
	if( is_plugin_active($eePlugin) ) {
		deactivate_plugins($eePlugin);
	}

	return TRUE;
}
register_activation_hook( __FILE__, 'avpvh_flst_activate' );



// Upon Deactivation...
function avpvh_flst_deactivate() {
	// Nothing needed for Free version
}
register_deactivation_hook( __FILE__, 'avpvh_flst_deactivate' );


?>
