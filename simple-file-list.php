<?php

/**
 * @package Simple File List
 */
/*
Plugin Name: Simple File List (grmt)
Plugin URI: https://github.com/grmt/simple-file-list
Description: File lists with uploads, access control, search and paging. Maintained by grmt; based on Simple File List by Mitchell Bennis (simplefilelist.com), not affiliated with the original author.
Author: grmt
Version: 6.3.13-grmt.1
Author URI: https://github.com/grmt
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: simple-file-list
Domain Path: /languages
Update URI: https://github.com/grmt/simple-file-list
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once __DIR__ . '/includes/ee-github-updater.php';

// CONSTANTS
if(!defined('eeSFL_Version')) { define('eeSFL_Version', '6.3.13-grmt.1'); }
define('eeSFL_PluginName', 'Simple File List');
define('eeSFL_PluginSlug', 'simple-file-list');
define('eeSFL_Product', 'Free');
define('eeSFL_PluginMenuTitle', 'File List');

// Common
define('eeSFL_Prefix', 'eeSFL');
define('eeSFL_FileListDefaultDir', 'simple-file-list/'); // Default Upload Directory
define('eeSFL_Go', wp_date('Y-m-d h:m:s') ); // Log Entry Key


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
$eeSFLA = FALSE; // Access Object — initialised by ee-simple-file-list-access when active
$eeSFL_Extensions = array();



// =============================================================================
// Plugin Setup
// =============================================================================
function eeSFL_Setup() {

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
					esc_html__('Simple File List:', 'simple-file-list') . '</strong> ' .
					esc_html__('The Email extension has been automatically deactivated because email functionality is now integrated into core.', 'simple-file-list') .
					'</p></div>';
			});
		}

		// Deactivate the old Media extension if needed (functionality now integrated into core)
		$eePlugin = 'ee-simple-file-list-media/ee-simple-file-list-media.php';
		if( is_plugin_active($eePlugin) ) {
			deactivate_plugins($eePlugin);
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
					esc_html__('Simple File List:', 'simple-file-list') . '</strong> ' .
					esc_html__('The Media extension has been automatically deactivated because media functionality is now integrated into core.', 'simple-file-list') .
					'</p></div>';
			});
		}

	} // end is_admin()

	// Define debug logging stub (no-op on frontend; real implementation provided by Tools plugin above)
	if(!function_exists('eeSFL_Debug_Log')) { function eeSFL_Debug_Log($eeString, $eeCategory = '', $eeListID = 0) { return FALSE; } }
	eeSFL_Debug_Log("Simple File List v" . eeSFL_Version . " initializing", 'Loading');

	// Translation strings to pass to javascript as eesfl_vars
	$eeProtocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
	$eeSFL_VarsForJS = array(
		'ajaxurl' => admin_url( 'admin-ajax.php', $eeProtocol ),
		'eeEditText' => __('Edit', 'simple-file-list'), // Edit link text
		'eeConfirmDeleteText' => __('Are you sure you want to delete this?', 'simple-file-list'), // Delete confirmation
		'eeCancelText' => __('Cancel', 'simple-file-list'),
		'eeCopyLinkText' => __('The Link Has Been Copied', 'simple-file-list'),
		'eeUploadLimitText' => __('Upload Limit', 'simple-file-list'),
		'eeFileTooLargeText' => __('This file is too large', 'simple-file-list'),
		'eeFileNoSizeText' => __('This file is empty', 'simple-file-list'),
		'eeFileNotAllowedText' => __('This file type is not allowed', 'simple-file-list'),
		'eeUploadErrorText' => __('Upload Failed', 'simple-file-list'),
		'eePleaseWaitText' => __('Please Wait', 'simple-file-list'),
		'eeFilesSelected' =>  __('Files Selected', 'simple-file-list'),

		// Back-End Only
		'eeShowText' => __('Show', 'simple-file-list'), // Shortcode Builder
		'eeHideText' => __('Hide', 'simple-file-list'),

		// Extensions
		'eeChooseListText' => __('Choose List', 'simple-file-list'),
	);


	// Get Class
	if(!class_exists('eeSFL')) {

		// Get Functions File
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-functions.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-lists.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-groups.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-access.php');
		include_once(plugin_dir_path(__FILE__) . 'includes/ee-search.php');
		if( is_admin() ) {
			include_once(plugin_dir_path(__FILE__) . 'includes/ee-access-admin.php');
		}

		if(!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
			include_once(plugin_dir_path(__FILE__) . 'includes/ee-front-end.php');
		}

		// Main Class
		require_once(plugin_dir_path(__FILE__) . 'includes/ee-class.php');
		$eeSFL = new eeSFL_MainClass();

		// Load Upload Class (needed by GetEnv)
		require_once(plugin_dir_path(__FILE__) . 'includes/ee-class-uploads.php');
		$eeSFLU = new eeSFL_UploadClass();

		// Initialize environment (requires $eeSFLU)
		$eeSFL->eeSFL_GetEnv();

		// Can we go on?
		$eeResult = $eeSFL->eeSFL_GetRootPath();
		if($eeResult === FALSE) {

			eeSFL_Debug_Log("CRITICAL: Root path determination failed - hosting incompatibility", 'ERROR');

			add_action('admin_notices', function() {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p><strong>' . esc_html__('Simple File List Error:', 'simple-file-list') . '</strong> ' . esc_html__('This plugin cannot determine the correct file system paths on your hosting environment.', 'simple-file-list') . '</p>';
				echo '<p>' . esc_html__('This typically occurs on managed WordPress hosting platforms. Please contact support with your hosting provider details.', 'simple-file-list') . '</p>';
				echo '<p>' . esc_html__('This plugin will not work under this configuration.', 'simple-file-list') . '</p>';
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
			|| (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && sanitize_key(wp_unslash($_POST['action'])) === 'simplefilelist_sendfile_job');
		if($eeNeedsEmailClass) {
			require_once(plugin_dir_path(__FILE__) . 'includes/ee-class-send.php');
			$eeSFLE = new eeSFLE_class();
		}

		// Set List ID: the main list, or in wp-admin the list chosen with ?eeListID=
		$eeSFL->eeListID = 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing; eeSFL_AdminListID() checks access
		if( is_admin() && isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === eeSFL_PluginSlug ) {
			$eeSFL->eeListID = eeSFL_AdminListID() ?: 1;
		}

		// Load Settings
		$eeSFL->eeSFL_GetSettings($eeSFL->eeListID);

		// Email File Send Check
		if( is_object($eeSFLE) && method_exists($eeSFLE, 'eeSFLE_SendFilesEmail') && isset($_POST['eeSFLE_Send']) ) {
			if (!check_ajax_referer( 'eeSFL_SendNonce', 'eeSecurity', FALSE )) {
				// Reject — nonce missing or invalid in all contexts including admin-ajax
			} else {
				$eeSFLE->eeSFLE_SendFilesEmail(); // Sending Files
			}
		}

		// Install or Update if Needed
		if( is_admin() ) {
			eeSFL_VersionCheck();
			eeSFL_Debug_Log("Version check completed.", 'Loading');
		}

		eeSFL_Debug_Log('- List ID = ' . $eeSFL->eeListID . ' --> Setup Loaded.', 'General', $eeSFL->eeListID);
		eeSFL_Debug_Log("Setup Complete for List ID: " . $eeSFL->eeListID, 'Loading');
		eeSFL_Debug_Log("Final memory usage: " . round(memory_get_usage()/1024/1024, 2) . "MB", 'Loading');

	} else {
		eeSFL_Debug_Log("eeSFL class already exists, skipping initialization", 'Loading');
	}

	return TRUE;
}


// =============================================================================
// WORDPRESS HOOKS REGISTRATION
// =============================================================================
// Note: These hooks must be registered after eeSFL_Setup() runs and loads all includes

// Core Initialization
add_action('init', 'eeSFL_Setup'); // Main setup - loads all classes and functions
add_action('init', function() { // Protected downloads, after eeSFL_Setup() has found the site root
	if( function_exists('eeSFL_HandleFileRequest') ) { eeSFL_HandleFileRequest(); }
}, 20);
add_action('init', 'eeSFL_Textdomain'); // Load textdomain at init to comply with WordPress 6.7+ requirements

// Asset Management
add_action('init', 'eeSFL_RegisterAssets'); // Register scripts and styles
add_action('wp_enqueue_scripts', 'eeSFL_Enqueue'); // Frontend assets
add_action('admin_enqueue_scripts', 'eeSFL_AdminHead'); // Admin assets

// SFL Dashboard detection meta tag
add_action('wp_head', function() { echo '<meta name="simple-file-list" content="' . esc_attr(eeSFL_Version) . '">' . "\n"; });

// Admin Interface
add_action('admin_menu', 'eeSFL_AdminMenu'); // Add admin menu
add_action('current_screen', 'eeSFL_SetAdminTitle'); // Set admin page title
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'eeSFL_ActionPluginLinks'); // Plugin page links

// AJAX Handlers
add_action('wp_ajax_simplefilelist_upload_job', 'simplefilelist_upload_job');
add_action('wp_ajax_nopriv_simplefilelist_upload_job', 'simplefilelist_upload_job');
add_action('wp_ajax_simplefilelist_edit_job', 'simplefilelist_edit_job');
add_action('wp_ajax_nopriv_simplefilelist_edit_job', 'simplefilelist_edit_job');
add_action('wp_ajax_simplefilelist_sendfile_job', 'simplefilelist_sendfile_job');
add_action('wp_ajax_nopriv_simplefilelist_sendfile_job', 'simplefilelist_sendfile_job');
add_action('wp_ajax_simplefilelist_confirm', 'simplefilelist_confirm');
add_action('wp_ajax_simplefilelist_dismiss', 'simplefilelist_dismiss'); // Acknowledge new features

// Shortcode
add_shortcode('eeSFL', 'eeSFL_FrontEnd'); // [eeSFL] shortcode

// Language Enabler
function eeSFL_Textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for private plugins
    load_plugin_textdomain( 'simple-file-list', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}


// Plugin Activation
function eeSFL_Activate() {

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	// Clear cached root path so it is re-verified after activation or update
	delete_transient('eeSFL_root_path');

	// Deactivate the Pro version if needed
	$eePlugin = 'ee-simple-file-list-pro/ee-simple-file-list-pro.php';
	if( is_plugin_active($eePlugin) ) {
		deactivate_plugins($eePlugin);
	}

	return TRUE;
}
register_activation_hook( __FILE__, 'eeSFL_Activate' );



// Upon Deactivation...
function eeSFL_Deactivate() {
	// Nothing needed for Free version
}
register_deactivation_hook( __FILE__, 'eeSFL_Deactivate' );


?>
