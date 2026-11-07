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
function eeSFL_Debug_Log($eeString, $eeCategory = '', $eeListID = 0) { return false; }

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

require dirname(__DIR__) . '/includes/ee-functions.php';
require dirname(__DIR__) . '/includes/ee-lists.php';
