<?php
// Minimal WordPress stand-ins so plugin functions can be unit tested without a WP install.

require dirname(__DIR__) . '/vendor/autoload.php';

$eeTestRoot = sys_get_temp_dir() . '/sfl-tests-' . getmypid();
define('SFL_TEST_ROOT', $eeTestRoot);
define('ABSPATH', $eeTestRoot . '/wp/');
define('WP_CONTENT_DIR', $eeTestRoot . '/wp/wp-content');
define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');

if ( ! is_dir(WP_PLUGIN_DIR) ) {
	mkdir(WP_PLUGIN_DIR, 0777, true);
}

function get_transient($eeKey) { return false; }
function avpvh_filelist_debug_log($eeString, $eeCategory = '', $eeListID = 0) { return false; }

// Options live in $GLOBALS['eeTestOptions']; tests reset it in setUp().
$GLOBALS['eeTestOptions'] = array();
function get_option($eeName, $eeDefault = false) { return $GLOBALS['eeTestOptions'][$eeName] ?? $eeDefault; }
function update_option($eeName, $eeValue, $eeAutoload = null) { $GLOBALS['eeTestOptions'][$eeName] = $eeValue; return true; }
function delete_option($eeName) { unset($GLOBALS['eeTestOptions'][$eeName]); return true; }

function absint($eeValue) { return abs((int) $eeValue); }
function wp_unslash($eeValue) { return is_string($eeValue) ? stripslashes($eeValue) : $eeValue; }

// Capabilities of the "current user" live in $GLOBALS['eeTestCaps'].
$GLOBALS['eeTestCaps'] = array();
function current_user_can($eeCap) { return in_array($eeCap, $GLOBALS['eeTestCaps'], true); }

// Users live in $GLOBALS['eeTestUsers']: ID => ['login', 'email', 'roles', 'caps'].
$GLOBALS['eeTestUsers'] = array();
function get_userdata($eeID) {
	$eeUser = $GLOBALS['eeTestUsers'][(int) $eeID] ?? null;
	if( !$eeUser ) { return false; }
	return (object) array('ID' => (int) $eeID, 'user_login' => $eeUser['login'] ?? '', 'user_email' => $eeUser['email'] ?? '', 'roles' => $eeUser['roles'] ?? array());
}
function user_can($eeID, $eeCap) { return in_array($eeCap, $GLOBALS['eeTestUsers'][(int) $eeID]['caps'] ?? array(), true); }

// Filters: tests may put a callable in $GLOBALS['eeTestFilters'][$eeHook].
$GLOBALS['eeTestFilters'] = array();
function apply_filters($eeHook, $eeValue, ...$eeArgs) {
	return isset($GLOBALS['eeTestFilters'][$eeHook]) ? $GLOBALS['eeTestFilters'][$eeHook]($eeValue, ...$eeArgs) : $eeValue;
}

function set_transient($eeKey, $eeValue, $eeExpiration = 0) { return true; }
function home_url($eePath = '') { return 'https://example.org' . $eePath; }
function wp_json_encode($eeData) { return json_encode($eeData); }
function __($eeText, $eeDomain = 'default') { return $eeText; }
if( !function_exists('sanitize_text_field') ) { function sanitize_text_field($eeValue) { return trim(strip_tags((string) $eeValue)); } }
function esc_html($eeText) { return htmlspecialchars((string) $eeText, ENT_QUOTES); }
function esc_attr($eeText) { return htmlspecialchars((string) $eeText, ENT_QUOTES); }
function esc_url($eeURL) { return htmlspecialchars((string) $eeURL, ENT_QUOTES); }
function esc_html__($eeText, $eeDomain = 'default') { return esc_html($eeText); }
function esc_attr__($eeText, $eeDomain = 'default') { return esc_attr($eeText); }
function wp_parse_url($eeURL, $eeComponent = -1) { return parse_url($eeURL, $eeComponent); }
function add_query_arg($eeKey, $eeValue, $eeURL) {
	$eeParts = explode('?', $eeURL, 2);
	parse_str($eeParts[1] ?? '', $eeArgs);
	$eeArgs[$eeKey] = $eeValue;
	return $eeParts[0] . '?' . http_build_query($eeArgs);
}
function remove_query_arg($eeKeys, $eeURL) {
	$eeParts = explode('?', $eeURL, 2);
	parse_str($eeParts[1] ?? '', $eeArgs);
	foreach( (array) $eeKeys as $eeKey ) { unset($eeArgs[$eeKey]); }
	return $eeParts[0] . ($eeArgs ? '?' . http_build_query($eeArgs) : '');
}
if( !defined('MINUTE_IN_SECONDS') ) { define('MINUTE_IN_SECONDS', 60); }

require dirname(__DIR__) . '/includes/ee-functions.php';
require dirname(__DIR__) . '/includes/ee-groups.php';
require dirname(__DIR__) . '/includes/ee-access.php';
require dirname(__DIR__) . '/includes/ee-lists.php';
require dirname(__DIR__) . '/includes/ee-search.php';
if (!function_exists('add_action')) { function add_action($eeHook, $eeCallback, $eePriority = 10, $eeArgs = 1) { return true; } }
require dirname(__DIR__) . '/includes/ee-migrate.php';
