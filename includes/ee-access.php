<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Who may see a list and read its files, and the protected download handler
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html
//
// A list's "Show To" setting decides who sees the list. ShowList = GRANTED limits it to the
// users, roles and directory groups in the list's ListAccess grant.
//
// A file can carry its own grant (the 'Access' key of its entry in eeSFL_FileList_{ID}). Such a
// file is only listed for, and only served to, the people in its grant, within the audience of
// the list. A file without a grant is for everyone who sees the list. The people who manage the
// list in wp-admin (its Admin Role) always see every file.
//
// With "Protect files" on (ProtectFiles = YES) file links point to this plugin's download handler
// (?sfl-list=N&sfl-file=path), which checks access on every request. Direct URLs to the folder
// must then be blocked by the web server: this plugin writes a deny-all .htaccess for Apache and
// LiteSpeed; nginx needs a location block (shown in the settings).



/**
 * @return array{users: int[], roles: string[], groups: string[]}
 */
function eeSFL_EmptyGrant() {
	return array('users' => array(), 'roles' => array(), 'groups' => array());
}


/**
 * Clean up a grant: user IDs as positive ints, role slugs, lower-case group names.
 *
 * @param mixed $eeGrant
 * @return array{users: int[], roles: string[], groups: string[]}
 */
function eeSFL_NormaliseGrant($eeGrant) {

	$eeGrant = is_array($eeGrant) ? $eeGrant : array();

	$eeUsers = array_values(array_unique(array_filter(array_map('intval', (array) ($eeGrant['users'] ?? array())), function($eeID) { return $eeID > 0; })));
	sort($eeUsers);

	$eeRoles = array();
	foreach( (array) ($eeGrant['roles'] ?? array()) as $eeRole ) {
		$eeRole = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $eeRole));
		if( $eeRole !== '' ) { $eeRoles[$eeRole] = $eeRole; }
	}
	$eeRoles = array_values($eeRoles);
	sort($eeRoles);

	return array(
		'users'  => $eeUsers,
		'roles'  => $eeRoles,
		'groups' => eeSFL_NormaliseGroupNames($eeGrant['groups'] ?? array()),
	);
}


/**
 * @param array $eeGrant
 * @return bool
 */
function eeSFL_GrantIsEmpty($eeGrant) {
	$eeGrant = eeSFL_NormaliseGrant($eeGrant);
	return !$eeGrant['users'] && !$eeGrant['roles'] && !$eeGrant['groups'];
}


/**
 * The grant of a file. Also reads what the old File Access Manager add-on stored: AccessUsers
 * (user IDs) and AccessRole (a role name, or a minimum level 1-5).
 *
 * @param array $eeFileArray
 * @return array{users: int[], roles: string[], groups: string[]}
 */
function eeSFL_FileGrant($eeFileArray) {

	$eeGrant = eeSFL_NormaliseGrant($eeFileArray['Access'] ?? array());

	if( !empty($eeFileArray['AccessUsers']) && is_array($eeFileArray['AccessUsers']) ) {
		$eeGrant['users'] = array_merge($eeGrant['users'], $eeFileArray['AccessUsers']);
	}

	$eeLegacyRole = $eeFileArray['AccessRole'] ?? '';
	if( is_numeric($eeLegacyRole) && (int) $eeLegacyRole >= 1 ) {
		// A minimum level: that role and every role above it
		$eeLadder = array('subscriber', 'contributor', 'author', 'editor', 'administrator');
		$eeGrant['roles'] = array_merge($eeGrant['roles'], array_slice($eeLadder, min(5, (int) $eeLegacyRole) - 1));
	} elseif( is_string($eeLegacyRole) && $eeLegacyRole !== '' ) {
		$eeGrant['roles'][] = $eeLegacyRole;
	}

	return eeSFL_NormaliseGrant($eeGrant);
}


/**
 * Is this user in the grant (by ID, role or directory group)?
 *
 * @param array $eeGrant
 * @param int   $eeUserID
 * @return bool
 */
function eeSFL_UserMatchesGrant($eeGrant, $eeUserID) {

	$eeUserID = (int) $eeUserID;
	if( $eeUserID <= 0 ) { return FALSE; }

	$eeGrant = eeSFL_NormaliseGrant($eeGrant);

	if( in_array($eeUserID, $eeGrant['users'], true) ) { return TRUE; }

	if( $eeGrant['roles'] ) {
		$eeUser = get_userdata($eeUserID);
		if( $eeUser && array_intersect($eeGrant['roles'], (array) $eeUser->roles) ) { return TRUE; }
	}

	// Last: this may ask the directory
	if( $eeGrant['groups'] && array_intersect($eeGrant['groups'], eeSFL_UserGroups($eeUserID)) ) { return TRUE; }

	return FALSE;
}


/**
 * Does this user manage the list in wp-admin (its Admin Role)?
 *
 * @param int $eeListID
 * @param int $eeUserID
 * @return bool
 */
function eeSFL_UserManagesList($eeListID, $eeUserID) {
	return (int) $eeUserID > 0 && user_can((int) $eeUserID, eeSFL_ListAdminCapability($eeListID));
}


/**
 * @param array $eeSettings
 * @return bool
 */
function eeSFL_ListIsProtected($eeSettings) {
	return ($eeSettings['ProtectFiles'] ?? 'NO') === 'YES';
}


/**
 * May this user see the list?
 *
 * @param int   $eeListID
 * @param array $eeSettings
 * @param int   $eeUserID
 * @return bool
 */
function eeSFL_UserCanSeeList($eeListID, $eeSettings, $eeUserID) {

	$eeUserID = (int) $eeUserID;

	if( eeSFL_UserManagesList($eeListID, $eeUserID) ) { return TRUE; }

	switch ( $eeSettings['ShowList'] ?? 'YES' ) {
		case 'YES':     return TRUE;
		case 'USER':    return $eeUserID > 0;
		case 'ADMIN':   return $eeUserID > 0 && user_can($eeUserID, 'manage_options');
		case 'GRANTED': return eeSFL_UserMatchesGrant($eeSettings['ListAccess'] ?? array(), $eeUserID);
		default:        return FALSE;
	}
}


/**
 * May this user see and download this file?
 *
 * @param int   $eeListID
 * @param array $eeSettings
 * @param array $eeFileArray
 * @param int   $eeUserID
 * @return bool
 */
function eeSFL_UserCanReadFile($eeListID, $eeSettings, $eeFileArray, $eeUserID) {

	if( eeSFL_UserManagesList($eeListID, $eeUserID) ) { return TRUE; }
	if( !eeSFL_UserCanSeeList($eeListID, $eeSettings, $eeUserID) ) { return FALSE; }

	$eeGrant = eeSFL_FileGrant($eeFileArray);

	return eeSFL_GrantIsEmpty($eeGrant) || eeSFL_UserMatchesGrant($eeGrant, $eeUserID);
}


/**
 * May this user rename, delete, move or edit this item?
 * List managers always may; others only with front-end management on, and only items they may read.
 *
 * @param int   $eeListID
 * @param array $eeSettings
 * @param array $eeFileArray
 * @param int   $eeUserID
 * @return bool
 */
function eeSFL_UserCanChangeFile($eeListID, $eeSettings, $eeFileArray, $eeUserID) {

	if( eeSFL_UserManagesList($eeListID, $eeUserID) ) { return TRUE; }
	if( ($eeSettings['AllowFrontManage'] ?? 'NO') !== 'YES' ) { return FALSE; }

	return eeSFL_UserCanReadFile($eeListID, $eeSettings, $eeFileArray, $eeUserID);
}


/**
 * The handler URL of a file in a protected list.
 *
 * @param int    $eeListID
 * @param string $eeFilePath Relative to the list folder
 * @param array  $eeOptions  'download' => bool (save instead of open), 'thumb' => bool
 * @return string
 */
function eeSFL_ProtectedFileURL($eeListID, $eeFilePath, $eeOptions = array()) {

	$eeURL = home_url('/') . '?sfl-list=' . (int) $eeListID;

	if( !empty($eeOptions['thumb']) ) { $eeURL .= '&sfl-thumb=1'; }
	if( !empty($eeOptions['download']) ) { $eeURL .= '&sfl-dl=1'; }

	// Keep the path last, so the URL still ends in the file's extension
	return $eeURL . '&sfl-file=' . str_replace('%2F', '/', rawurlencode($eeFilePath));
}


/**
 * The URL to give out for a file: the handler for a protected list, the folder URL otherwise.
 *
 * @param int    $eeListID
 * @param array  $eeSettings
 * @param string $eeFilePath
 * @param array  $eeOptions See eeSFL_ProtectedFileURL()
 * @return string
 */
function eeSFL_FileURL($eeListID, $eeSettings, $eeFilePath, $eeOptions = array()) {

	global $eeSFL;

	if( eeSFL_ListIsProtected($eeSettings) ) {
		return eeSFL_ProtectedFileURL($eeListID, $eeFilePath, $eeOptions);
	}

	return $eeSFL->eeEnvironment['wpSiteURL'] . $eeSettings['FileListDir'] . $eeFilePath;
}



/**
 * Site-wide settings for protected downloads.
 *
 * @return array{AccelPrefix: string}
 */
function eeSFL_AccessSettings() {
	$eeStored = get_option('eeSFL_AccessSettings', array());
	return array_merge(array('AccelPrefix' => ''), is_array($eeStored) ? $eeStored : array());
}


/**
 * Decide what a download request gets.
 *
 * Anonymous visitors get 401 (log in) for anything they can't read, logged-in users 404, also
 * when the file exists: the answer must not reveal which files are there.
 *
 * @param int    $eeListID
 * @param string $eeFilePath Relative to the list folder
 * @param bool   $eeThumb    The file's generated thumbnail instead of the file
 * @param int    $eeUserID
 * @param string $eeRoot     Site root path, with trailing slash
 * @return array{status: int, file?: string, name?: string}
 */
function eeSFL_ResolveFileRequest($eeListID, $eeFilePath, $eeThumb, $eeUserID, $eeRoot) {

	$eeDenied = array('status' => (int) $eeUserID > 0 ? 404 : 401);

	$eeListID = (int) $eeListID;
	$eeFilePath = ltrim(str_replace('\\', '/', (string) $eeFilePath), '/');

	if( $eeFilePath === '' || strpos($eeFilePath, "\0") !== FALSE || !eeSFL_ListExists($eeListID) ) { return $eeDenied; }
	if( in_array('..', explode('/', $eeFilePath), true) ) { return $eeDenied; }

	$eeSettings = get_option('eeSFL_Settings_' . $eeListID);
	if( !is_array($eeSettings) || empty($eeSettings['FileListDir']) ) { return $eeDenied; }

	// Only files the list knows about
	$eeFileArray = null;
	foreach( (array) get_option('eeSFL_FileList_' . $eeListID, array()) as $eeItem ) {
		if( is_array($eeItem) && ($eeItem['FilePath'] ?? null) === $eeFilePath && ($eeItem['FileExt'] ?? '') !== 'folder' ) {
			$eeFileArray = $eeItem;
			break;
		}
	}
	if( $eeFileArray === null ) { return $eeDenied; }

	if( !eeSFL_UserCanReadFile($eeListID, $eeSettings, $eeFileArray, $eeUserID) ) { return $eeDenied; }

	$eeListDir = $eeRoot . trim($eeSettings['FileListDir'], '/') . '/';

	if( $eeThumb ) {
		$eeParts = pathinfo($eeFilePath);
		$eeFolder = (isset($eeParts['dirname']) && $eeParts['dirname'] !== '.') ? $eeParts['dirname'] . '/' : '';
		$eeRelative = $eeFolder . '_eeSFL_Thumbnails/thumb_' . $eeParts['filename'] . '.jpg';
	} else {
		$eeRelative = $eeFilePath;
	}

	// The real file must be inside the real list folder (no symlinks pointing out)
	$eeRealDir = realpath($eeListDir);
	$eeRealFile = realpath($eeListDir . $eeRelative);

	if( $eeRealDir === FALSE || $eeRealFile === FALSE || !is_file($eeRealFile) ) { return $eeDenied; }
	if( strpos(str_replace('\\', '/', $eeRealFile), rtrim(str_replace('\\', '/', $eeRealDir), '/') . '/') !== 0 ) { return $eeDenied; }

	return array(
		'status' => 200,
		'file'   => $eeRealFile,
		'name'   => basename($eeRelative),
		'path'   => trim($eeSettings['FileListDir'], '/') . '/' . $eeRelative,
	);
}


/**
 * Parse a Range header for a single byte range.
 *
 * @param string $eeHeader e.g. "bytes=0-1023"
 * @param int    $eeSize
 * @return array{0: int, 1: int}|null|false [start, end] inclusive; null: whole file; false: not satisfiable
 */
function eeSFL_ParseRange($eeHeader, $eeSize) {

	if( !preg_match('/^bytes=(\d*)-(\d*)$/', trim((string) $eeHeader), $eeMatch) ) { return null; } // Ignore what we don't support
	if( $eeMatch[1] === '' && $eeMatch[2] === '' ) { return null; }
	if( $eeSize <= 0 ) { return FALSE; }

	if( $eeMatch[1] === '' ) { // The last N bytes
		$eeLength = min((int) $eeMatch[2], $eeSize);
		if( $eeLength <= 0 ) { return FALSE; }
		return array($eeSize - $eeLength, $eeSize - 1);
	}

	$eeStart = (int) $eeMatch[1];
	$eeEnd = $eeMatch[2] === '' ? $eeSize - 1 : min((int) $eeMatch[2], $eeSize - 1);

	if( $eeStart >= $eeSize || $eeStart > $eeEnd ) { return FALSE; }

	return array($eeStart, $eeEnd);
}


/**
 * File types a browser may show inline. Everything else is sent as a download: an uploaded
 * HTML or SVG file opened inline would run its scripts on this site.
 *
 * @return string[]
 */
function eeSFL_InlineExtensions() {
	return array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'mp3', 'm4a', 'ogg', 'oga', 'wav', 'flac', 'mp4', 'm4v', 'webm', 'ogv', 'mov', 'txt');
}


/**
 * Content-Disposition header value with an ASCII fallback and the UTF-8 name.
 *
 * @param string $eeName
 * @param bool   $eeInline
 * @return string
 */
function eeSFL_ContentDisposition($eeName, $eeInline) {
	// One _ per character; per byte when the name is not valid UTF-8
	$eeAscii = preg_replace('/[^\x20-\x7E]|["\\\\]/u', '_', $eeName) ?? preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $eeName);
	return ($eeInline ? 'inline' : 'attachment') . '; filename="' . $eeAscii . '"; filename*=UTF-8\'\'' . rawurlencode($eeName);
}


/**
 * Send a resolved file and stop.
 *
 * @param array $eeResolved From eeSFL_ResolveFileRequest()
 * @param bool  $eeDownload Save instead of open
 */
function eeSFL_SendFile($eeResolved, $eeDownload) {

	$eeFile = $eeResolved['file'];
	$eeName = $eeResolved['name'];
	$eeSize = (int) filesize($eeFile);
	$eeExt = strtolower(pathinfo($eeName, PATHINFO_EXTENSION));
	$eeType = wp_check_filetype($eeName);
	$eeInline = !$eeDownload && in_array($eeExt, eeSFL_InlineExtensions(), true);

	while( ob_get_level() ) { ob_end_clean(); }

	header('Content-Type: ' . ($eeInline && $eeType['type'] ? $eeType['type'] : 'application/octet-stream'));
	header('Content-Disposition: ' . eeSFL_ContentDisposition($eeName, $eeInline));
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: private, no-store');
	header('Accept-Ranges: bytes');

	// Let nginx send the file from an internal location
	$eeAccel = trim(eeSFL_AccessSettings()['AccelPrefix']);
	if( $eeAccel !== '' ) {
		$eeEncoded = implode('/', array_map('rawurlencode', explode('/', $eeResolved['path'])));
		header('X-Accel-Redirect: ' . rtrim($eeAccel, '/') . '/' . $eeEncoded);
		exit;
	}

	$eeRange = isset($_SERVER['HTTP_RANGE']) ? eeSFL_ParseRange(sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE'])), $eeSize) : null;

	if( $eeRange === FALSE ) {
		status_header(416);
		header('Content-Range: bytes */' . $eeSize);
		exit;
	}

	list($eeStart, $eeEnd) = $eeRange ?? array(0, $eeSize - 1);
	$eeLength = $eeSize > 0 ? $eeEnd - $eeStart + 1 : 0;

	if( $eeRange ) {
		status_header(206);
		header('Content-Range: bytes ' . $eeStart . '-' . $eeEnd . '/' . $eeSize);
	} else {
		status_header(200);
	}
	header('Content-Length: ' . $eeLength);

	$eeMethod = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
	if( $eeMethod === 'HEAD' || $eeLength === 0 ) { exit; }

	// phpcs:disable WordPress.WP.AlternativeFunctions -- streaming a large file in chunks; WP_Filesystem has no partial reads
	$eeHandle = fopen($eeFile, 'rb');
	if( !$eeHandle ) { exit; }
	fseek($eeHandle, $eeStart);

	$eeRemaining = $eeLength;
	while( $eeRemaining > 0 && !feof($eeHandle) && !connection_aborted() ) {
		$eeChunk = fread($eeHandle, min(1048576, $eeRemaining));
		if( $eeChunk === FALSE ) { break; }
		echo $eeChunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file contents
		$eeRemaining -= strlen($eeChunk);
		flush();
	}
	fclose($eeHandle);
	// phpcs:enable

	exit;
}


/**
 * The download handler: ?sfl-list=N&sfl-file=path[&sfl-dl=1][&sfl-thumb=1]
 */
function eeSFL_HandleFileRequest() {

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- links are shareable; access is checked per user
	if( !isset($_GET['sfl-list'], $_GET['sfl-file']) ) { return; }

	$eeListID = absint(wp_unslash($_GET['sfl-list']));
	$eeFilePath = (string) wp_unslash($_GET['sfl-file']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a path; validated against the stored file list
	$eeThumb = !empty($_GET['sfl-thumb']);
	$eeDownload = !empty($_GET['sfl-dl']);
	// phpcs:enable

	nocache_headers();

	$eeRoot = defined('eeSFL_WP_ROOT') ? eeSFL_WP_ROOT : ABSPATH;
	$eeResolved = eeSFL_ResolveFileRequest($eeListID, $eeFilePath, $eeThumb, get_current_user_id(), $eeRoot);

	if( $eeResolved['status'] === 401 ) {
		$eeHere = eeSFL_ProtectedFileURL($eeListID, $eeFilePath, array('download' => $eeDownload, 'thumb' => $eeThumb));
		wp_safe_redirect(wp_login_url($eeHere));
		exit;
	}

	if( $eeResolved['status'] !== 200 ) {
		status_header(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo esc_html__('File not found.', 'simple-file-list');
		exit;
	}

	eeSFL_Debug_Log('Serving ' . $eeResolved['path'] . ' to user ' . get_current_user_id(), 'Access', $eeListID);

	eeSFL_SendFile($eeResolved, $eeDownload);
}



/**
 * Block direct URLs to a protected list's folder. Apache and LiteSpeed get a deny-all .htaccess;
 * other servers get a message with what to configure.
 *
 * @return bool
 */
function eeSFL_ProtectListDir() {

	global $eeSFL;

	$eeDir = $eeSFL->eeSFL_GetRootPath() . $eeSFL->eeListSettings['FileListDir'];
	$eeServer = (string) ($eeSFL->eeEnvironment['eeWebServer'] ?? '');

	if( stripos($eeServer, 'Apache') === 0 || stripos($eeServer, 'LiteSpeed') === 0 ) {

		$eeRules = "# Simple File List: files in this list are served by WordPress, which checks access.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

		$eeWrite = eeSFL_FileSystem('put_contents', array('file' => $eeDir . '.htaccess', 'data' => $eeRules));

		if( !$eeWrite['success'] ) {
			$eeSFL->eeUserMessages['errors'][] = __('WARNING: Could not write', 'simple-file-list') . ' .htaccess';
			return FALSE;
		}
		return TRUE;
	}

	$eeSFL->eeUserMessages['messages'][] = '<strong>' . esc_html__('Block direct access to this folder on your web server', 'simple-file-list') . '</strong><br />'
		. esc_html__('Files are now served through WordPress, but their folder is still reachable by direct URL until your server blocks it. For nginx:', 'simple-file-list')
		. '<br /><code>location ^~ /' . esc_html(trim($eeSFL->eeListSettings['FileListDir'], '/')) . '/ { deny all; }</code>';

	return TRUE;
}
