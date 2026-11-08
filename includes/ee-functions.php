<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List, based on Simple File List - Copyright 2026 Mitchell Bennis (original author, https://simplefilelist.com) and grmt
// Maintained by grmt, not affiliated with the original author: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



// NEW - WP FileSystem Interface
/**
 * WordPress Filesystem Interface Function
 *
 * This function provides a unified interface to WordPress filesystem operations,
 * replacing native PHP filesystem functions with WordPress-compliant alternatives.
 * Uses WP_Filesystem API for proper security, permissions, and hosting compatibility.
 *
 * @param string $mode The filesystem operation to perform
 * @param array $params Parameters for the operation (varies by mode)
 * @return array Returns array with 'success' boolean and 'data' or 'error'
 *
 * Supported operations:
 *
 * copy - Copy a file
 *   avpvh_flst_file_system('copy', array('from' => '/source/path', 'to' => '/dest/path', 'overwrite' => true))
 *
 * move - Move/rename a file
 *   avpvh_flst_file_system('move', array('from' => '/source/path', 'to' => '/dest/path', 'overwrite' => true))
 *
 * delete - Delete a file or directory
 *   avpvh_flst_file_system('delete', array('file' => '/path/to/file'))
 *
 * exists - Check if file or directory exists
 *   avpvh_flst_file_system('exists', array('file' => '/path/to/check'))
 *
 * is_file - Check if path is a file
 *   avpvh_flst_file_system('is_file', array('file' => '/path/to/check'))
 *
 * is_dir - Check if path is a directory
 *   avpvh_flst_file_system('is_dir', array('path' => '/path/to/check'))
 *
 * mkdir - Create directory
 *   avpvh_flst_file_system('mkdir', array('path' => '/path/dir/', 'chmod' => 0755))
 *
 * get_contents - Read file contents
 *   avpvh_flst_file_system('get_contents', array('file' => '/path/to/file'))
 *
 * put_contents - Write file contents
 *   avpvh_flst_file_system('put_contents', array('file' => '/path/to/file', 'data' => 'content', 'mode' => 0644))
 *
 * dirlist - List directory contents
 *   avpvh_flst_file_system('dirlist', array('path' => '/path/dir/', 'include_hidden' => false, 'recursive' => false))
 *
 * filesize - Get file size in bytes
 *   avpvh_flst_file_system('filesize', array('file' => '/path/to/file'))
 *
 * filemtime - Get file modification time (Unix timestamp)
 *   avpvh_flst_file_system('filemtime', array('file' => '/path/to/file'))
 *
 * touch - Update file access and modification times (creates file if it doesn't exist)
 *   avpvh_flst_file_system('touch', array('file' => '/path/to/file', 'time' => 1234567890, 'atime' => 1234567890))
 */
function avpvh_flst_file_system($mode, $params = array()) {

	// SECURITY: Every path must resolve inside the WordPress install. This is the single
	// chokepoint for all file operations, so callers cannot be tricked into touching files
	// elsewhere via ../ sequences or symlinks. Uploaded temp files are the only exception.
	foreach ( array('from', 'to', 'file', 'path') as $eeKey ) {
		if ( ! isset($params[$eeKey]) ) { continue; }
		if ( $eeKey === 'from' && is_uploaded_file($params['from']) ) { continue; }
		if ( ! avpvh_flst_is_allowed_path($params[$eeKey]) ) {
			avpvh_flst_debug_log("BLOCKED path outside allowed roots: " . $params[$eeKey], 'Security');
			return array('success' => false, 'data' => null, 'error' => 'Path is outside the allowed directories');
		}
	}

	// Try to initialize WP_Filesystem (preferred — Plugin Check compliant)
	// On managed hosts (e.g. Pressable, Kinsta, WP Engine) WP_Filesystem() returns false
	// because no FTP/SSH credentials are available at runtime. In that case we fall through
	// to native PHP equivalents which work fine since the web server has direct write access.
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $eeUseWPFS = WP_Filesystem();
    global $wp_filesystem;

    switch($mode) {

        case 'copy':
            if ( $eeUseWPFS ) {
                return array('success' => $wp_filesystem->copy($params['from'], $params['to'], $params['overwrite'] ?? true), 'data' => null);
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.copy_copy -- WP_Filesystem unavailable on managed hosts
            return array('success' => copy($params['from'], $params['to']), 'data' => null);

        case 'move':
            if ( $eeUseWPFS ) {
                return array('success' => $wp_filesystem->move($params['from'], $params['to'], $params['overwrite'] ?? true), 'data' => null);
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem unavailable on managed hosts
            return array('success' => rename($params['from'], $params['to']), 'data' => null);

        case 'delete':
            $recursive = isset($params['recursive']) ? $params['recursive'] : true;
            if ( $eeUseWPFS ) {
                return array('success' => $wp_filesystem->delete($params['file'], $recursive), 'data' => null);
            }
            if ( is_dir($params['file']) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem unavailable on managed hosts; this is already the fallback branch
                return array('success' => $recursive ? wp_delete_directory($params['file']) : rmdir($params['file']), 'data' => null);
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WP_Filesystem unavailable on managed hosts
            return array('success' => unlink($params['file']), 'data' => null);

        case 'exists':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->exists($params['file']));
            }
            return array('success' => true, 'data' => file_exists($params['file']));

        case 'is_file':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->is_file($params['file']));
            }
            return array('success' => true, 'data' => is_file($params['file']));

        case 'is_dir':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->is_dir($params['path']));
            }
            return array('success' => true, 'data' => is_dir($params['path']));

        case 'mkdir':
            if ( $eeUseWPFS ) {
                return array('success' => $wp_filesystem->mkdir($params['path'], $params['chmod'] ?? FS_CHMOD_DIR), 'data' => null);
            }
            return array('success' => wp_mkdir_p($params['path']), 'data' => null);

        case 'get_contents':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->get_contents($params['file']));
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP_Filesystem unavailable on managed hosts
            $data = file_get_contents($params['file']);
            return array('success' => $data !== false, 'data' => $data);

        case 'put_contents':
            if ( $eeUseWPFS ) {
                return array('success' => $wp_filesystem->put_contents($params['file'], $params['data'], $params['mode'] ?? FS_CHMOD_FILE), 'data' => null);
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem unavailable on managed hosts
            return array('success' => file_put_contents($params['file'], $params['data']) !== false, 'data' => null);

        case 'dirlist':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->dirlist($params['path'], $params['include_hidden'] ?? false, $params['recursive'] ?? false));
            }
            // Native fallback: build same array structure as WP_Filesystem->dirlist()
            $eeDir = $params['path'];
            if ( ! is_dir($eeDir) ) {
                return array('success' => false, 'data' => array());
            }
            $eeItems = array();
            // phpcs:ignore WordPress.WP.AlternativeFunctions.dir_opendir -- WP_Filesystem unavailable on managed hosts
            $eeHandle = opendir($eeDir);
            if ( $eeHandle ) {
                $eeIncludeHidden = $params['include_hidden'] ?? false;
                // phpcs:ignore WordPress.WP.AlternativeFunctions.dir_readdir -- WP_Filesystem unavailable on managed hosts
                while ( false !== ( $eeEntry = readdir($eeHandle) ) ) {
                    if ( $eeEntry === '.' || $eeEntry === '..' ) { continue; }
                    if ( ! $eeIncludeHidden && strpos($eeEntry, '.') === 0 ) { continue; }
                    $eeFullPath = trailingslashit($eeDir) . $eeEntry;
                    $eeItems[$eeEntry] = array(
                        'name' => $eeEntry,
                        'type' => is_dir($eeFullPath) ? 'd' : 'f',
                        'size' => is_file($eeFullPath) ? filesize($eeFullPath) : 0,
                    );
                }
                closedir($eeHandle);
            }
            return array('success' => true, 'data' => $eeItems);

        case 'filesize':
            if ( $eeUseWPFS ) {
                return array('success' => true, 'data' => $wp_filesystem->size($params['file']));
            }
            $eeSize = file_exists($params['file']) ? filesize($params['file']) : false;
            return array('success' => $eeSize !== false, 'data' => $eeSize);

        case 'filemtime':
            // Strip trailing slashes before calling filemtime — PHP returns false for
            // directory paths with a trailing forward slash on Windows (WAMP/XAMPP).
            $eeMtimePath = rtrim($params['file'], '/\\');
            if ( $eeUseWPFS ) {
                $eeMtime = $wp_filesystem->mtime($eeMtimePath);
                return array('success' => $eeMtime !== false, 'data' => $eeMtime);
            }
            $eeMtime = file_exists($eeMtimePath) ? filemtime($eeMtimePath) : false;
            return array('success' => $eeMtime !== false, 'data' => $eeMtime);

        case 'touch':
            $eeFile = $params['file'];
            $eeTime = isset($params['time']) ? $params['time'] : time();
            $eeAtime = isset($params['atime']) ? $params['atime'] : $eeTime;
            if ( $eeUseWPFS && ! $wp_filesystem->exists($eeFile) ) {
                $wp_filesystem->put_contents($eeFile, '', FS_CHMOD_FILE);
            } elseif ( ! $eeUseWPFS && ! file_exists($eeFile) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem unavailable on managed hosts
                file_put_contents($eeFile, '');
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- No WP alternative for touch()
            $eeResult = touch($eeFile, $eeTime, $eeAtime);
            return array('success' => $eeResult, 'data' => null);

        case 'dirsize':
            if ( ! isset($params['path']) ) {
                return array('success' => false, 'error' => 'Path parameter required');
            }
            if ( ! is_dir($params['path']) ) {
                return array('success' => false, 'error' => 'Path is not a directory');
            }
            $calculateSize = function($dir) use (&$calculateSize) {
                $total = 0;
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) { $total += $file->getSize(); }
                }
                return $total;
            };
            return array('success' => true, 'data' => $calculateSize($params['path']));

        default:
            return array('success' => false, 'error' => 'Unknown filesystem operation: ' . $mode);
    }
}




/**
 * Check that a path resolves inside the WordPress install (site root, ABSPATH or wp-content).
 *
 * The path does not need to exist yet: the nearest existing parent is resolved with
 * realpath() (which also follows symlinks) and the remaining segments must not climb up.
 *
 * @param string $eePath Absolute path to check
 * @return bool True if the path is inside an allowed root
 */
function avpvh_flst_is_allowed_path($eePath) {

	if ( ! is_string($eePath) || $eePath === '' || strpos($eePath, "\0") !== false ) {
		return false;
	}

	$eePath = str_replace('\\', '/', $eePath);
	$eeRemainder = array();
	$eeExisting = rtrim($eePath, '/');

	// Walk up to the nearest existing ancestor
	while ( $eeExisting !== '' && ! file_exists($eeExisting) ) {
		$eeSegment = basename($eeExisting);
		if ( $eeSegment === '..' ) { return false; }
		array_unshift($eeRemainder, $eeSegment);
		$eeParent = dirname($eeExisting);
		if ( $eeParent === $eeExisting ) { break; }
		$eeExisting = $eeParent;
	}

	$eeReal = realpath($eeExisting === '' ? '/' : $eeExisting);
	if ( $eeReal === false ) { return false; }
	$eeReal = rtrim(str_replace('\\', '/', $eeReal), '/') . '/';

	// Allowed roots. Avoid get_root_path() here: it calls avpvh_flst_file_system() itself.
	$eeRoots = array(ABSPATH, WP_CONTENT_DIR);
	if ( defined('WP_PLUGIN_DIR') ) { $eeRoots[] = WP_PLUGIN_DIR; }
	if ( defined('AVPVH_FLST_WP_ROOT') ) {
		$eeRoots[] = AVPVH_FLST_WP_ROOT;
	} elseif ( function_exists('get_transient') && ($eeCachedRoot = get_transient('avpvh_flst_root_path')) ) {
		$eeRoots[] = $eeCachedRoot;
	}

	foreach ( $eeRoots as $eeRoot ) {
		$eeRootReal = realpath($eeRoot);
		if ( $eeRootReal === false ) { continue; }
		$eeRootReal = rtrim(str_replace('\\', '/', $eeRootReal), '/') . '/';
		if ( strpos($eeReal, $eeRootReal) === 0 ) {
			return true;
		}
	}

	return false;
}




/**
 * Ensure File List Directory Exists
 *
 * Creates the file list directory if it doesn't exist, even if database entries remain.
 * This prevents the issue where deleting the directory but keeping DB entries leaves
 * the system in an inconsistent state.
 *
 * @param int $eeListID The list ID to check (default: 1)
 * @return bool True if directory exists or was created successfully
 */
function avpvh_flst_ensure_file_list_dir_exists($eeListID = 1) {

	global $eeSFL;

	// Get the settings for this list
	$eeSettings = get_option('avpvh_flst_settings_' . $eeListID);

	// If no settings, use default directory
	if (!$eeSettings || !isset($eeSettings['FileListDir'])) {
		$eeFileListDir = WP_CONTENT_DIR . '/uploads/' . AVPVH_FLST_DEFAULT_DIR;
	} else {
		$eeFileListDir = $eeSFL->get_root_path() . $eeSettings['FileListDir'];
	}

	avpvh_flst_debug_log("Checking if directory exists: $eeFileListDir", 'FileSystem', $eeListID);

	// Check if directory exists
	if (!file_exists($eeFileListDir)) {

		avpvh_flst_debug_log("Directory missing, creating: $eeFileListDir", 'FileSystem', $eeListID);

		// Create directory with proper permissions
		if (wp_mkdir_p($eeFileListDir)) {

			avpvh_flst_debug_log("Directory created successfully", 'FileSystem', $eeListID);

			// Create index.html for security
			$index_file = $eeFileListDir . 'index.html';
			if (!file_exists($index_file)) {
				file_put_contents($index_file, '<!-- Simple File List -->');
			}

			// If we have settings and files in the database but directory was missing,
			// the database is now out of sync. We should trigger a re-scan.
			if ($eeSettings && get_option('avpvh_flst_files_' . $eeListID)) {
				avpvh_flst_debug_log("Directory was recreated but DB has file entries - re-scan needed", 'FileSystem', $eeListID);
				// Clear the file list array to force re-scan
				delete_option('avpvh_flst_files_' . $eeListID);
			}

			return true;
		} else {
			avpvh_flst_debug_log("ERROR: Failed to create directory", 'FileSystem', $eeListID);
			return false;
		}
	}

	avpvh_flst_debug_log("Directory already exists", 'FileSystem', $eeListID);
	return true;
}




/**
 * Change one stored setting of a list. Reads the stored settings rather than saving
 * $eeSFL->eeListSettings, which a page view adjusts for its visitor (uploads off,
 * shortcode attributes): those adjustments must never be stored.
 *
 * @param int    $eeListID
 * @param string $eeKey
 * @param mixed  $eeValue
 * @return bool
 */
function avpvh_flst_save_setting($eeListID, $eeKey, $eeValue) {
	$eeStored = get_option('avpvh_flst_settings_' . (int) $eeListID);
	if( !is_array($eeStored) ) { return FALSE; }
	$eeStored[$eeKey] = $eeValue;
	return update_option('avpvh_flst_settings_' . (int) $eeListID, $eeStored);
}




function avpvh_flst_check_supported($eeForce = FALSE) {

	global $eeSFL;

	// Avoid running shell_exec() on every page load if already checked
	if ( ! $eeForce && get_option('avpvh_flst_supported') !== false ) {
		return TRUE;
	}

	// Check for supported technologies
	$eeSupported = array();

    // Check for ffMpeg
    if(function_exists('shell_exec')) {

		if(shell_exec('ffmpeg -version')) {
			$eeSupported[] = 'ffMpeg';
			$eeSFL->eeUserMessages['Supported'][] = 'Supported: ffMpeg';
		}
    } else {
	    $eeSFL->eeUserMessages['Trouble'] = '---> shell_exec() NOT SUPPORTED';
    }

    if($eeSFL->eeEnvironment['eeOS'] != 'WINDOWS') {

		// Check for GhostScript (wp_get_image_editor handles image resizing — no Imagick requirement)
		if($eeSFL->eeEnvironment['eeOS'] == 'LINUX') { // TO DO - Make it work for IIS

			if(function_exists('shell_exec')) {

				$phpExt = 'gs'; // <<<---- This will be different for Windows
				if(shell_exec($phpExt . ' --version') >= 1.0) { // <<<---- This will be different for Windows too
					$eeSupported[] = 'GhostScript';
					$eeSFL->eeUserMessages['Supported'][] = 'Supported: GhostScript';
				}
			}
		}
	}

	update_option('avpvh_flst_supported', $eeSupported);

	return TRUE;

}





// LEGACY - Convert hyphens to spaces for display only
function avpvh_flst_preserve_spaces($eeFileName) {

	$eeFileName = str_replace('-', ' ', $eeFileName);

	return $eeFileName;
}





// Add the correct URL argument operator, ? or &
function avpvh_flst_append_proper_url_op($eeURL) {

	if ( strpos($eeURL, '?') ) {
		$eeURL .= '&';
	} else {
		$eeURL .= '?';
	}

	return $eeURL;
}





// Check for the Upload Folder, Create if Needed
function avpvh_flst_file_list_dir_check($eeFileListDir) {

	global $eeSFL;
	static $eeReportedDirErrors = array(); // Prevent duplicate error messages across repeated calls

	if(!$eeFileListDir OR substr($eeFileListDir, 0, 1) == '/' OR strpos($eeFileListDir, '../') ) {

		$eeSFL->eeUserMessages['errors'][] = __('Bad Directory Given', 'avpvh-filelist') . ': ' . $eeFileListDir;

		return FALSE;
	}

	// Use AVPVH_FLST_WP_ROOT — the verified site root which accounts for managed hosts
	// (e.g. Pressable) where ABSPATH points to the WP core directory rather than
	// the site root. get_root_path() derives the correct path from the uploads dir.
	$eeSiteRoot = $eeSFL->get_root_path();

	avpvh_flst_debug_log('- Checking: ' . $eeFileListDir, 'General', $eeSFL->eeListID);

	$dir_check = avpvh_flst_file_system('is_dir', array('path' => $eeSiteRoot . $eeFileListDir));
	if( !$dir_check['data'] ) { // Directory Changed or New Install

		avpvh_flst_debug_log("New Install or Directory Change...", 'General');

		avpvh_flst_debug_log("No Directory Found. Creating ...", 'General');

		// Create directory using WordPress filesystem (primary method — Plugin Check compliant)
		$create_result = avpvh_flst_file_system('mkdir', array('path' => $eeSiteRoot . $eeFileListDir));

		if(!$create_result['success']) {
			// WP_Filesystem mkdir failed. On managed hosts (Pressable, Kinsta, WP Engine, etc.)
			// WP_Filesystem() returns false at runtime because no FTP/SSH credentials are
			// available, even though the web server process has direct write access.
			// Fall back to wp_mkdir_p() (native PHP mkdir) directly — no pre-check needed.
			if(wp_mkdir_p($eeSiteRoot . $eeFileListDir)) {
				avpvh_flst_debug_log('Directory created via wp_mkdir_p() fallback (managed host): ' . $eeSiteRoot . $eeFileListDir, 'FileSystem');
			} else {
				// Both methods failed — show error (once per directory per page load)
				if(!isset($eeReportedDirErrors[$eeFileListDir])) {
					$eeReportedDirErrors[$eeFileListDir] = TRUE;
					if ($eeSFL->eeEnvironment['eeOS'] == 'WINDOWS') {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Windows Directory:', 'avpvh-filelist') . ': ' . $eeFileListDir;
					} elseif($eeSFL->eeEnvironment['eeOS'] == 'LINUX') {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Linux Directory:', 'avpvh-filelist') . ': ' . $eeFileListDir;
					} else {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Directory (Unknown OS):', 'avpvh-filelist') . ': ' . $eeFileListDir;
					}
				}

				avpvh_flst_debug_log("CRITICAL: Directory creation failed for: " . $eeSiteRoot . $eeFileListDir, 'FileSystem');
				avpvh_flst_debug_log("Create result: " . wp_json_encode($create_result), 'FileSystem');

				return FALSE;
			}
		} else {
			// Directory creation succeeded - log success
			avpvh_flst_debug_log("Directory creation succeeded: " . $eeSiteRoot . $eeFileListDir, 'FileSystem');
		}

		// Verify directory was created — use native is_dir() since WP_Filesystem may not
		// be available on managed hosts (same reason we added the fallback above).
		if(!is_dir($eeSiteRoot . $eeFileListDir)) {
			$eeSFL->eeUserMessages['errors'][] = __('Cannot create the upload directory', 'avpvh-filelist') . ': ' . $eeFileListDir;
			$eeSFL->eeUserMessages['errors'][] = __('Please check directory permissions', 'avpvh-filelist');

			return FALSE;

		} else {

			avpvh_flst_debug_log("The File List Dir Has Been Created!", 'General');
			avpvh_flst_debug_log($eeFileListDir, 'General', $eeSFL->eeListID);
		}

	} else {
		avpvh_flst_debug_log("FileListDir Looks Good", 'General');
	}

	// Check index.html, create if needed.
	if( strlen($eeFileListDir) >= 2 ) {

		$eeFile = $eeSiteRoot . $eeFileListDir . 'index.html'; // Disallow direct file indexing.

		$file_check = avpvh_flst_file_system('is_file', array('file' => $eeFile));
		if(!$file_check['data']) {

			// Get template content using WordPress filesystem
			$template_content = avpvh_flst_file_system('get_contents', array('file' => $eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-index-template.html'));

			if($template_content['success']) {
				// Write index.html using WordPress filesystem
				$write_result = avpvh_flst_file_system('put_contents', array(
					'file' => $eeFile,
					'data' => $template_content['data']
				));

				if(!$write_result['success']) {
					$eeSFL->eeUserMessages['warnings'][] = __('WARNING! Could not write file', 'avpvh-filelist') . ': index.html';
					$eeSFL->eeUserMessages['warnings'][] = __('Please upload a blank index file to this location to prevent unauthorized access.', 'avpvh-filelist');
					$eeSFL->eeUserMessages['warnings'][] = $eeSiteRoot . $eeFileListDir;
				}
			}
		}
	}

	return TRUE; // Looks Good

}










// Return the size of a file in a nice format.
// Accepts a path or file size in bytes
function avpvh_flst_get_file_size($eeSFL_File) {

    if( is_numeric($eeSFL_File) ) {
		$bytes = $eeSFL_File;
	} else {
		$file_check = avpvh_flst_file_system('is_file', array('file' => AVPVH_FLST_WP_ROOT . $eeSFL_File));
		if($file_check['data']) {
			$size_result = avpvh_flst_file_system('filesize', array('file' => AVPVH_FLST_WP_ROOT . $eeSFL_File));
			$bytes = $size_result['success'] ? $size_result['data'] : 0;
		} else {
			return FALSE;
		}
	}

    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;
    $terabyte = $gigabyte * 1024;
    $precision = 2;

    if (($bytes >= 0) && ($bytes < $kilobyte)) {
        return $bytes . ' B';

    } elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
        return round($bytes / $kilobyte, $precision) . ' KB';

    } elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
        return round($bytes / $megabyte, $precision) . ' MB';

    } elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
        return round($bytes / $gigabyte, $precision) . ' GB';

    } elseif ($bytes >= $terabyte) {
        return round($bytes / $terabyte, $precision) . ' TB';
    } else {
        return $bytes . ' B';
    }
}


function avpvh_flst_sanitize_folder_name($eeFolderName) {

	$eeFolderName = str_replace('/', '-', $eeFolderName); // Slash to Hyphen
	$eeFolderName = str_replace(' ', '-', $eeFolderName); // Spaces to Hyphen
    $eeFolderName = str_replace('.', '-', $eeFolderName); // Dots to Hyphens
    $eeFolderName = str_replace('\'', '', $eeFolderName); // Strip apostrphes
	$eeFolderName = str_replace('^', '-', $eeFolderName); // Carrot to Hyphen
	$eeFolderName = str_replace('@', '-at-', $eeFolderName); // Spaces to -at-
	$eeFolderName = str_replace('&', 'and', $eeFolderName); // Make the & sign literal

	$eeFolderName = sanitize_file_name($eeFolderName);

    return $eeFolderName;
}



// Sanitize & Validate the File List Directory Path String
function avpvh_flst_validate_file_list_dir($eeDir) {

	global $eeSFL;

	if(!$eeDir) {
		$eeSFL->eeUserMessages['errors'][] = __('No Path Found', 'avpvh-filelist');
		return FALSE;
	}

	// Replace Backslashes with Slashes
	$eeDir = str_replace('\\', '/', $eeDir);

	// Check if eeDir has a leading slash. We don't want that.
	if($eeDir[0] == '/') {  $eeDir = substr($eeDir, 1); }

	// Check if eeDir has a trailing slash. We do want that.
	$eeChar = substr($eeDir, -1);
	if($eeChar != '/') {  $eeDir .= '/'; }

	// Sanitize each foldername in the path
	if( substr_count($eeDir, '/') > 1 ) {

		// Traversal Prevention
		$eeCheck = str_replace('../', '', $eeDir );

		if($eeCheck != $eeDir) {

			$eeSFL->eeUserMessages['errors'][] = __('Path is Not Direct', 'avpvh-filelist');
			$eeSFL->eeUserMessages['errors'][] = $eeDir;
			return FALSE;
		}

		$eePieces = explode('/', $eeDir);
		$eeDir = ''; // Reset for rebuilding

		foreach( $eePieces as $eePiece ) { // Rebuild as we sanitize each level

			$eePiece = avpvh_flst_sanitize_folder_name($eePiece);

		    if($eePiece AND strlen($eePiece) <= 255) {

			    $eeDir .= $eePiece . '/';
		    }
		}

	} else {

		$eeCheck = substr($eeDir, 0, -1); // Exclude the trailing slash
		$eeDir = avpvh_flst_sanitize_folder_name( $eeCheck ) . '/'; // Add it back
	}

	if($eeDir) {

		// This are locations NOT Allowed
		if(strpos($eeDir, '.') === 0
		OR strpos($eeDir, 'p-admin/')
		OR strpos($eeDir, 'p-includes/')
		OR strpos($eeDir, 'p-content/themes/')
		OR $eeDir == '/' OR $eeDir == '-/' OR $eeDir == '--/'
		OR $eeDir == 'wp-content/'
		OR $eeDir == 'wp-content/plugins/'
		OR $eeDir == 'wp-content/uploads/' ) {

			$eeSFL->eeUserMessages['errors'][] = 'This File List Location is Not Allowed';
			$eeSFL->eeUserMessages['errors'][] = $eeDir;

			return FALSE;
		}

		return $eeDir; // Good to go

	}

	$eeSFL->eeUserMessages['errors'][] = __('Path Failed Validation', 'avpvh-filelist');
	return FALSE;
}



// Yes or No Settings Checkboxes
function avpvh_flst_process_checkbox_input($eeTerm) {

	// Verify admin permissions for settings form submission
	// Note: Calling contexts should handle nonce verification before calling this function
	if (!current_user_can('manage_options')) {
		return 'NO'; // Default to NO if insufficient permissions
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tools function, nonce verification handled by calling context
	$eeValue = isset($_POST['ee' . $eeTerm]) ? sanitize_text_field(wp_unslash($_POST['ee' . $eeTerm])) : '';

	if($eeValue == 'YES') { return 'YES'; } else { return 'NO'; }
}



// Settings Text Inputs
function avpvh_flst_process_text_input($eeTerm, $eeType = 'text') {

	// Verify admin permissions for settings form submission
	// Note: Calling contexts should handle nonce verification before calling this function
	if (!current_user_can('manage_options')) {
		return ''; // Return empty string if insufficient permissions
	}

	$eeValue = '';

	if($eeType == 'email') {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tools function, nonce verification handled by calling context
		$eeValue = filter_var(sanitize_email(isset($_POST['ee' . $eeTerm]) ? wp_unslash($_POST['ee' . $eeTerm]) : ''), FILTER_VALIDATE_EMAIL);

	} elseif($eeType == 'textarea') {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tools function, nonce verification handled by calling context
		$eeValue = esc_textarea(sanitize_textarea_field(isset($_POST['ee' . $eeTerm]) ? wp_unslash($_POST['ee' . $eeTerm]) : ''));

	} else {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Tools function, nonce verification handled by calling context
		$eeValue = wp_strip_all_tags(isset($_POST['ee' . $eeTerm]) ? wp_unslash($_POST['ee' . $eeTerm]) : '');
		$eeValue = esc_textarea(sanitize_text_field($eeValue));
	}

	return $eeValue;
}



// Return a formatted header string
function avpvh_flst_return_header_string($eeFrom, $eeCc = FALSE, $eeBcc = FALSE) {

	$eeAdminEmail = get_option('admin_email');

	$eeHeaders = 'From: ' . get_option('blogname') . ' < ' . $eeAdminEmail . ' >'  . PHP_EOL;

	if($eeCc) { $eeHeaders .= "CC: " . $eeCc . PHP_EOL; }

	if($eeBcc) { $eeHeaders .= "BCC: " . $eeBcc . PHP_EOL; }

	if( !filter_var($eeFrom, FILTER_VALIDATE_EMAIL) ) {
		$eeFrom = $eeAdminEmail;
	}

	$eeHeaders .= "Return-Path: " . $eeAdminEmail . PHP_EOL .
		"Reply-To: " . $eeFrom . PHP_EOL;

	return $eeHeaders;

}




// Process a raw input of email addresses
// Can be a single address or a comma sep list
function avpvh_flst_process_email_string($eeString) {

	$eeString = sanitize_text_field($eeString);

	if( strpos($eeString, ',') ) { // More than one address?

		$eeArray = explode(',', $eeString);

		$eeAddresses = ''; // Reset

		foreach( $eeArray as $eeEmail) {

			$eeEmail = filter_var(sanitize_email($eeEmail), FILTER_VALIDATE_EMAIL);

			if($eeEmail) {

				$eeAddresses .= $eeEmail . ','; // Reassemble validated addresses
			}
		}

		$eeAddresses = substr($eeAddresses, 0, -1); // Strip the last comma

	} else {

		$eeAddresses = filter_var(sanitize_email($eeString), FILTER_VALIDATE_EMAIL);
	}

	if( strpos($eeAddresses, '@') ) {

		return $eeAddresses;

	} else {

		return FALSE;
	}
}




/**
 * Turn the FileFormats setting ("jpg, PDF ,zip") into a regex alternation ("jpg|pdf|zip")
 * for the RewriteRule in htaccess-template.txt. Each type is regex-escaped; empties are dropped.
 *
 * @param string $eeFileFormats Comma separated list of extensions
 * @return string Pipe separated, escaped extensions ('' if none)
 */
function avpvh_flst_htaccess_file_types($eeFileFormats) {

	$eeTypes = array();
	foreach( explode(',', (string) $eeFileFormats) as $eeType ) {
		$eeType = strtolower(trim($eeType));
		if($eeType !== '') { $eeTypes[] = preg_quote($eeType, '/'); }
	}

	return implode('|', array_unique($eeTypes));
}


/**
 * Is $eeName an item in the stored file list? The free version lists only the root
 * directory, so a listed item's FilePath is its bare name (folders left by the old Pro version carry a trailing slash).
 *
 * @param mixed  $eeAllFiles The stored file list array
 * @param string $eeName     The bare file name
 * @return bool
 */
function avpvh_flst_is_listed_item($eeAllFiles, $eeName) {
	return avpvh_flst_listed_item($eeAllFiles, $eeName) !== array();
}


// The stored entry of a listed item, or an empty array when it is not in the list.
function avpvh_flst_listed_item($eeAllFiles, $eeName) {

	if( !is_array($eeAllFiles) || !is_string($eeName) || $eeName === '' ) { return array(); }

	foreach( $eeAllFiles as $eeThisFileArray ) {
		if( isset($eeThisFileArray['FilePath']) && rtrim($eeThisFileArray['FilePath'], '/') === $eeName ) {
			return $eeThisFileArray;
		}
	}

	return array();
}


// Protect file list directories form direct URL access (hotlinking)
function avpvh_flst_limit_dir_access($eeMode) {

	global $eeSFL;

	if( avpvh_flst_list_is_protected($eeSFL->eeListSettings) ) { return avpvh_flst_protect_list_dir(); } // Files go through the download handler

	// Delete and Start Over
	$eeFile = $eeSFL->get_root_path() . $eeSFL->eeListSettings['FileListDir'] . '.htaccess';
	$file_check = avpvh_flst_file_system('exists', array('file' => $eeFile));
	if( $file_check['data'] ) {
		avpvh_flst_file_system('delete', array('file' => $eeFile));
	}

	if($eeMode == 'YES' OR $eeMode == 'NORMAL') { // Not Needed
		return TRUE;
	}

	// Apache / LightSpeed
	if(stripos($eeSFL->eeEnvironment['eeWebServer'], 'Apache') === 0 OR stripos($eeSFL->eeEnvironment['eeWebServer'], 'LiteSpeed') === 0 ) { // Write .htaccess

		// Check .htaccess file, create if needed.
		$eeFile = $eeSFL->get_root_path() . $eeSFL->eeListSettings['FileListDir'] . '.htaccess';

		// Get template content using WordPress filesystem
		$template_content = avpvh_flst_file_system('get_contents', array('file' => plugin_dir_path(__FILE__) . 'htaccess-template.txt'));

		if(!$template_content['success']) {
			$eeSFL->eeUserMessages['errors'][] = __('ERROR: Could not read .htaccess template', 'avpvh-filelist');
			return FALSE;
		}

		// Assign our file types
		$eeFormats = avpvh_flst_htaccess_file_types($eeSFL->eeListSettings['FileFormats']);
		if($eeFormats === '') {
			$eeSFL->eeUserMessages['errors'][] = __('ERROR: No file types to protect', 'avpvh-filelist');
			return FALSE;
		}
		$eeString = str_replace('FILE_TYPES', $eeFormats, $template_content['data']);

		// Create .htaccess file using WordPress filesystem
		$write_result = avpvh_flst_file_system('put_contents', array(
			'file' => $eeFile,
			'data' => $eeString
		));

		if(!$write_result['success']) {
			$eeSFL->eeUserMessages['errors'][] = __('WARNING: Could not write', 'avpvh-filelist') . ' .htaccess';
			return FALSE;
		} else {
			avpvh_flst_debug_log(" .htaccess file has been set", 'General');
		}

	// NGINX
	} elseif( stripos($eeSFL->eeEnvironment['eeWebServer'], 'nginx') === 0 ) {

		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'avpvh-filelist') . ' - ' . 'Nginx ' . __('Web Server Detected', 'avpvh-filelist') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'avpvh-filelist') . '<br />
				<a href="https://kinsta.com/blog/hotlinking/#nginx" target="_blank">' . __('Learn More', 'avpvh-filelist') . '</a>';

	// Microsoft IIS
	} elseif( stripos($eeSFL->eeEnvironment['eeWebServer'], 'iis') OR stripos($eeSFL->eeEnvironment['eeWebServer'], 'iis') === 0  ) {

		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'avpvh-filelist') . ' - ' . 'IIS ' . __('Web Server Detected', 'avpvh-filelist') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'avpvh-filelist') . '<br />
				<a href="https://medium.com/@ankittyagi366/how-to-prevent-hotlinking-in-iis-server-asp-net-framework-asp-net-core-application-21900edd80d7" target="_blank">' . __('Learn More', 'avpvh-filelist') . '</a>';

	// Something Else
	} else {
		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'avpvh-filelist') . ' - ' . __('Unknown Web Server', 'avpvh-filelist') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'avpvh-filelist');
	}
}




// ============================================================================
// FILE EDITOR OPERATION FUNCTIONS
// ============================================================================

/**
 * Delete a file
 *
 * @param string $eeFileName The name of the file to delete
 * @return string 'SUCCESS' or error message
 */
function avpvh_flst_delete_file($eeFileName) {

	global $eeSFL;

	avpvh_flst_debug_log("DELETE: Starting delete operation for '$eeFileName'", 'FileOps', $eeSFL->eeListID);


	$eeMessages = array('Deleting File');

	// Construct full file path
	$eeFilePath = AVPVH_FLST_WP_ROOT . $eeSFL->eeListSettings['FileListDir'] . $eeFileName;
	avpvh_flst_debug_log("DELETE: Full path: '$eeFilePath'", 'FileOps', $eeSFL->eeListID);

	// Confinement check — ensure the resolved path stays within the list directory.
	// Guards against path traversal in $eeFileName.
	$eeBaseDir = realpath(AVPVH_FLST_WP_ROOT . $eeSFL->eeListSettings['FileListDir']);
	$eeRealPath = realpath($eeFilePath);
	if( $eeBaseDir && $eeRealPath ) {
		$eeBaseDirNorm = rtrim(str_replace('\\', '/', $eeBaseDir), '/') . '/';
		$eeRealPathNorm = str_replace('\\', '/', $eeRealPath);
		if( strpos($eeRealPathNorm, $eeBaseDirNorm) !== 0 ) { // Trailing slash: siblings like "simple-file-list-old/" are outside
			avpvh_flst_debug_log("ERROR: Path traversal attempt blocked: '$eeFilePath'", 'FileOps', $eeSFL->eeListID);
			return 'ERROR: Invalid path';
		}
	}

	$eeMessages[] = $eeSFL->eeListSettings['FileListDir'] . $eeFileName;

	// Check if it's a file
	if( avpvh_flst_file_system('is_file', array('file' => $eeFilePath))['data'] ) {

		avpvh_flst_debug_log("DELETE: Item is a file, proceeding with file deletion", 'FileOps', $eeSFL->eeListID);

		$eeDeleteResult = avpvh_flst_file_system('delete', array('file' => $eeFilePath));

		if($eeDeleteResult['success']) {

		avpvh_flst_debug_log("DELETE: File deleted successfully from disk", 'FileOps', $eeSFL->eeListID);

		// Remove from array
		foreach( $eeSFL->eeAllFiles as $eeKey => $eeThisFileArray) {
			if($eeThisFileArray['FilePath'] == $eeFileName) {
				unset($eeSFL->eeAllFiles[$eeKey]);
				avpvh_flst_debug_log("DELETE: Removed file from array at key: $eeKey", 'FileOps', $eeSFL->eeListID);
				break;
			}
		}

		$eeSFL->update_main_file_array(false);
		$eeSFL->update_thumbnail($eeFileName, FALSE, $eeSFL->eeListID);

		// Custom Hook
		$eeMessages[] = 'File Deleted';
			do_action('avpvh_flst_deleted', $eeMessages);

			avpvh_flst_debug_log("DELETE: File deletion completed successfully", 'FileOps', $eeSFL->eeListID);
			return 'SUCCESS';

		} else {
			avpvh_flst_debug_log("DELETE: File delete failed: " . $eeFileName, 'FileOps', $eeSFL->eeListID);
			return __('File Delete Failed', 'avpvh-filelist') . ':' . $eeFileName;
		}

	} else {
		avpvh_flst_debug_log("DELETE: Item not found or unknown type: " . $eeFilePath, 'FileOps', $eeSFL->eeListID);
		return __('Unknown Item', 'avpvh-filelist') . ':' . $eeFilePath;
	}
}


/**
 * Update file nice name
 *
 * @param string $eeFileName The file name
 * @param string $eeFileNiceNameNew The new nice name
 * @return string Success message or empty string
 */
function avpvh_flst_update_file_nice_name($eeFileName, $eeFileNiceNameNew) {

	global $eeSFL;

	$eeFileNiceNameNew = trim(sanitize_text_field($eeFileNiceNameNew)); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage

	if(strlen($eeFileNiceNameNew) < 1) { $eeFileNiceNameNew = ''; }

	$eeSFL->update_file_detail($eeFileName, 'FileNiceName', $eeFileNiceNameNew);

	avpvh_flst_debug_log("EDIT: Updated nice name to: '$eeFileNiceNameNew'", 'FileOps', $eeSFL->eeListID);

	return 'Nice Name: ' . $eeFileNiceNameNew;
}


/**
 * Update file description
 *
 * @param string $eeFileName The file name
 * @param string $eeFileDescriptionNew The new description
 * @return string Success message or empty string
 */
function avpvh_flst_update_file_description($eeFileName, $eeFileDescriptionNew) {

	global $eeSFL;

	$eeFileDescriptionNew = trim(sanitize_text_field($eeFileDescriptionNew)); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage

	if(strlen($eeFileDescriptionNew) < 1) { $eeFileDescriptionNew = ''; }

	$eeSFL->update_file_detail($eeFileName, 'FileDescription', $eeFileDescriptionNew);

	avpvh_flst_debug_log("EDIT: Updated description to: '$eeFileDescriptionNew'", 'FileOps', $eeSFL->eeListID);

	return 'Description: ' . $eeFileDescriptionNew;
}


/**
 * Update file date added
 *
 * @param string $eeFileName The file name
 * @param string $eeDate The new date (YYYY-MM-DD format)
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean, 'message' string, and optional 'additionalData' string
 */
function avpvh_flst_update_file_date_added($eeFileName, $eeDate, $eeListSettings) {

	global $eeSFL;

	if(strlen($eeDate) < 1) {
		return array('success' => true, 'message' => '');
	}

	// Validate date
	$eeArray = explode('-', $eeDate);
	if( !checkdate( $eeArray[1], $eeArray[2], $eeArray[0]) ) {
		avpvh_flst_debug_log("EDIT: Invalid date added format: $eeDate", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => 'Bad Date, Indiana! ' . $eeArray[1] . ', ' . $eeArray[2] . ', ' . $eeArray[0]
		);
	}

	// Update the Database
	$eeSFL->update_file_detail($eeFileName, 'FileDateAdded', $eeDate . ' 00:00:00' );

	avpvh_flst_debug_log("EDIT: Updated date added to: $eeDate", 'FileOps', $eeSFL->eeListID);

	$eeAdditionalData = '';
	if($eeListSettings['ShowFileDateAs'] == 'Added') {
		$eeAdditionalData = '|Date=' . date_i18n( get_option('date_format'), strtotime( $eeDate ) );
	}

	return array('success' => true, 'message' => '', 'additionalData' => $eeAdditionalData);
}


/**
 * Update file date changed
 *
 * @param string $eeFileName The file name
 * @param string $eeDate The new date (YYYY-MM-DD format)
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean, 'message' string, and optional 'additionalData' string
 */
function avpvh_flst_update_file_date_changed($eeFileName, $eeDate, $eeListSettings) {

	global $eeSFL;

	if(strlen($eeDate) < 1) {
		return array('success' => true, 'message' => '');
	}

	// Validate date
	$eeArray = explode('-', $eeDate);
	if( !checkdate( $eeArray[1], $eeArray[2], $eeArray[0]) ) {
		avpvh_flst_debug_log("EDIT: Invalid date changed format: $eeDate", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => 'Bad Date, Indiana! ' . $eeArray[1] . ', ' . $eeArray[2] . ', ' . $eeArray[0]
		);
	}

	// Update the File
	$eeDateTime = strtotime($eeDate);
	$eeFilePath = AVPVH_FLST_WP_ROOT . $eeListSettings['FileListDir'] . $eeFileName;
	$eeFileCheck = avpvh_flst_file_system('exists', array('file' => $eeFilePath));

	if($eeFileCheck['success'] && $eeFileCheck['data'] && $eeDateTime) {

		// Touch the file
		$eeTouchResult = avpvh_flst_file_system('touch', array('file' => $eeFilePath, 'time' => $eeDateTime));

		// Update the Database
		$eeSFL->update_file_detail($eeFileName, 'FileDateChanged', $eeDate . ' 00:00:00' );

		avpvh_flst_debug_log("EDIT: Updated date changed to: $eeDate", 'FileOps', $eeSFL->eeListID);

		$eeAdditionalData = '';
		if($eeListSettings['ShowFileDateAs'] == 'Changed') {
			$eeAdditionalData = '|Date=' . date_i18n( get_option('date_format'), strtotime( $eeDate ) );
		}

		return array('success' => true, 'message' => '', 'additionalData' => $eeAdditionalData);
	}

	return array('success' => true, 'message' => '');
}


/**
 * Rename a file
 *
 * @param string $eeFileName The current file name
 * @param string $eeFileNameNew The new file name
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean and 'message' string
 */
function avpvh_flst_rename_file($eeFileName, $eeFileNameNew, $eeListSettings) {

	global $eeSFL;

	avpvh_flst_debug_log("EDIT: Rename requested from '$eeFileName' to '$eeFileNameNew'", 'FileOps', $eeSFL->eeListID);

	$eeFileNameNew = urldecode( $eeFileNameNew );
	$eeFileNameNew = sanitize_file_name( $eeFileNameNew );

	if( strlen($eeFileNameNew) < 1 ) {
		avpvh_flst_debug_log("EDIT: Invalid new file name", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Invalid New File Name', 'avpvh-filelist')
		);
	}

	// Prevent changing file extension
	$eeOldExtension = strtolower(pathinfo($eeFileName, PATHINFO_EXTENSION));
	$eeNewExtension = strtolower(pathinfo($eeFileNameNew, PATHINFO_EXTENSION));
	if($eeOldExtension != $eeNewExtension) {
		avpvh_flst_debug_log("EDIT: Rename blocked - extension change not allowed", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Changing the File Extension is Not Allowed', 'avpvh-filelist')
		);
	}

	// Security check for path traversal
	$eeSFL->detect_upward_traversal($eeListSettings['FileListDir'] . $eeFileNameNew );

	// Check for duplicate file
	if(avpvh_flst_file_system('is_file', array('file' => AVPVH_FLST_WP_ROOT . $eeListSettings['FileListDir'] . $eeFileNameNew))['data']) {
		avpvh_flst_debug_log("EDIT: Rename blocked - duplicate name found", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Cannot Change the Name. Item with Same Name Found.', 'avpvh-filelist')
		);
	}

	// SECURITY: the old name comes straight from POST — it must stay inside the list directory as well.
	$eeSFL->detect_upward_traversal($eeListSettings['FileListDir'] . $eeFileName );

	// Rename file on disk
	$eeFilePathOld = AVPVH_FLST_WP_ROOT . $eeListSettings['FileListDir'] . $eeFileName;
	$eeFilePathNew = AVPVH_FLST_WP_ROOT . $eeListSettings['FileListDir'] . $eeFileNameNew;

	$eeOldFileCheck = avpvh_flst_file_system('exists', array('file' => $eeFilePathOld));
	if(!($eeOldFileCheck['success'] && $eeOldFileCheck['data'])) {
		avpvh_flst_debug_log("EDIT: Rename failed - file not found: $eeFilePathOld", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('File Not Found', 'avpvh-filelist') . ': ' . basename($eeFilePathOld)
		);
	}

	if( !avpvh_flst_file_system('move', array('from' => $eeFilePathOld, 'to' => $eeFilePathNew))['success'] ) {
		avpvh_flst_debug_log("EDIT: Rename failed - could not move file", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Could Not Change the Name', 'avpvh-filelist') . ' ' . $eeFilePathOld . ' ' . __('to', 'avpvh-filelist') . ' ' . $eeFilePathNew
		);
	}

	// Update database
	$eeSFL->update_file_detail($eeFileName, 'FilePath', $eeFileNameNew);
	avpvh_flst_debug_log("EDIT: Rename successful", 'FileOps', $eeSFL->eeListID);

	return array(
		'success' => true,
		'message' => 'Renamed to|' . $eeListSettings['FileListDir'] . $eeFileNameNew
	);
}


// File Editor Engine
function avpvh_flst_file_editor() {

	global $eeSFL;

	avpvh_flst_debug_log("=== FILE EDITOR START ===", 'Admin', 0);

	// WP Security
	$nonceCheck = check_ajax_referer( 'eeSFL_ActionNonce', 'eeSecurity', false );
	avpvh_flst_debug_log("Nonce check result: " . ($nonceCheck ? 'PASS' : 'FAIL'), 'Admin', 0);

	if( !$nonceCheck ) {
		avpvh_flst_debug_log("ERROR: Security check failed", 'Admin', 0);
		return 'ERROR 98';
	}

	// The List ID
	if( isset($_POST['eeSFL_ID']) && !empty(sanitize_text_field(wp_unslash($_POST['eeSFL_ID']))) ) {
		$eeSFL->eeListID = filter_var(sanitize_text_field(wp_unslash($_POST['eeSFL_ID'])), FILTER_VALIDATE_INT);
	} else {
		avpvh_flst_debug_log("ERROR: Missing List ID", 'Admin', 0);
		return "Missing ID";
	}

	avpvh_flst_debug_log("Processing request for List ID: " . $eeSFL->eeListID, 'Admin', $eeSFL->eeListID);
	$eeSFL->get_settings($eeSFL->eeListID);
	if( empty($eeSFL->eeListSettings['FileListDir']) ) {
		avpvh_flst_debug_log("ERROR: Unknown list ID", 'Admin', 0);
		return 'Bad List ID';
	}

	// Check if we should be doing this
	// Note: is_admin() is NOT a user-identity check — it is TRUE for ALL admin-ajax.php requests,
	// including unauthenticated nopriv ones. Authorise the user, per item below.
	// List managers may always act; with AllowFrontManage = YES, visitors may act on items they may read.
	if( !avpvh_flst_user_manages_list($eeSFL->eeListID, get_current_user_id()) AND ($eeSFL->eeListSettings['AllowFrontManage'] ?? 'NO') != 'YES' ) {
		avpvh_flst_debug_log("ERROR: Front manage not allowed", 'Admin', $eeSFL->eeListID);
		return;
	}

	$eeSFL->eeAllFiles = get_option('avpvh_flst_files_' . $eeSFL->eeListID);

	// The Action
	if( isset($_POST['eeFileAction']) && strlen(sanitize_text_field(wp_unslash($_POST['eeFileAction']))) ) {
		$eeFileAction = sanitize_text_field(wp_unslash($_POST['eeFileAction']));
	} else {
		avpvh_flst_debug_log("ERROR: Missing action", 'Admin', $eeSFL->eeListID);
		return "Missing the Action";
	}

	// The Current File Name
	if( isset($_POST['eeFileName']) && strlen(sanitize_text_field(wp_unslash($_POST['eeFileName']))) ) {
		$eeFileName = sanitize_text_field(wp_unslash($_POST['eeFileName'])); // sanitize only — esc_textarea() would corrupt names with quotes when matching against the file array
	} else {
		avpvh_flst_debug_log("ERROR: Missing file name", 'Admin', $eeSFL->eeListID);
		return "Missing the File Name";
	}

	// SECURITY: A list holds only the files directly in its folder, so a file name never contains a path.
	// Reject separators and dot entries: the Edit actions (dates, nice name, description) build
	// FileListDir . $eeFileName without a traversal check, so "../x" would reach files outside the list.
	if( strpbrk($eeFileName, '/\\') !== FALSE || $eeFileName === '.' || $eeFileName === '..' ) {
		avpvh_flst_debug_log("ERROR: File name contains a path: " . $eeFileName);
		return "Invalid File Name";
	}

	// SECURITY: Only act on items that are in this list. Without this, anyone allowed to manage
	// files could delete or rename unlisted files in the directory, such as the .htaccess that
	// protects it (".htaccess" -> "x.htaccess" passes the extension check in avpvh_flst_rename_file()).
	if( !avpvh_flst_is_listed_item($eeSFL->eeAllFiles, $eeFileName) ) {
		avpvh_flst_debug_log("ERROR: File is not in the list: " . $eeFileName, 'Admin', $eeSFL->eeListID);
		return "File Not Found";
	}

	// SECURITY: Front-end management never reaches items the user may not read (list or per-file access).
	// Answer as for an unlisted item, so restricted names are not confirmed.
	if( !avpvh_flst_user_can_change_file($eeSFL->eeListID, $eeSFL->eeListSettings, avpvh_flst_listed_item($eeSFL->eeAllFiles, $eeFileName), get_current_user_id()) ) {
		avpvh_flst_debug_log("ERROR: No access to file: " . $eeFileName, 'Admin', $eeSFL->eeListID);
		return "File Not Found";
	}

	avpvh_flst_debug_log("Action: $eeFileAction, File: $eeFileName", 'Admin', $eeSFL->eeListID);

	// ===== DISPATCHER =====
	// Route to appropriate specialized function

	if($eeFileAction == 'Delete') {

		avpvh_flst_debug_log("Routing to eeSFL_DeleteFile()", 'Admin', $eeSFL->eeListID);
		return avpvh_flst_delete_file($eeFileName);

	} elseif($eeFileAction == 'Edit') {

		avpvh_flst_debug_log("Routing to Edit functions", 'Admin', $eeSFL->eeListID);

		$eeMessages = array();
		$eeAdditionalData = '';

		// Nice Name
		if(isset($_POST['eeFileNiceNameNew']) && $_POST['eeFileNiceNameNew'] != 'false') {
			$eeFileNiceNameNew = trim(sanitize_text_field(wp_unslash($_POST['eeFileNiceNameNew']))); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage
			$eeResult = avpvh_flst_update_file_nice_name($eeFileName, $eeFileNiceNameNew);
			if($eeResult) { $eeMessages[] = $eeResult; }
		}

		// Description
		if(isset($_POST['eeFileDescNew']) && $_POST['eeFileDescNew'] != 'false') {
			$eeFileDescriptionNew = trim(sanitize_text_field(wp_unslash($_POST['eeFileDescNew']))); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage
			$eeResult = avpvh_flst_update_file_description($eeFileName, $eeFileDescriptionNew);
			if($eeResult) { $eeMessages[] = $eeResult; }
		}

		// Date Added
		if(isset($_POST['eeFileDateAdded'])) {
			$eeDate = preg_replace("/[^0-9-]/", "", sanitize_text_field(wp_unslash($_POST['eeFileDateAdded'])));
			$eeResult = avpvh_flst_update_file_date_added($eeFileName, $eeDate, $eeSFL->eeListSettings);
			if(!$eeResult['success']) {
				return $eeResult['message'];
			}
			if(!empty($eeResult['additionalData'])) {
				$eeAdditionalData = $eeResult['additionalData'];
			}
		}

		// Date Changed
		if(isset($_POST['eeFileDateChanged'])) {
			$eeDate = preg_replace("/[^0-9-]/", "", sanitize_text_field(wp_unslash($_POST['eeFileDateChanged'])));
			$eeResult = avpvh_flst_update_file_date_changed($eeFileName, $eeDate, $eeSFL->eeListSettings);
			if(!$eeResult['success']) {
				return $eeResult['message'];
			}
			if(!empty($eeResult['additionalData'])) {
				$eeAdditionalData = $eeResult['additionalData'];
			}
		}

		// Rename (do last)
		if( isset($_POST['eeFileNameNew']) && strlen(sanitize_text_field(wp_unslash($_POST['eeFileNameNew']))) >= 1 ) {
			$eeFileNameNew = sanitize_text_field(wp_unslash($_POST['eeFileNameNew']));
			$eeResult = avpvh_flst_rename_file($eeFileName, $eeFileNameNew, $eeSFL->eeListSettings);
			if(!$eeResult['success']) {
				return $eeResult['message'];
			}
			if($eeResult['message']) {
				$eeMessages[] = $eeResult['message'];
			}
		}

		// Custom Hook
		do_action('avpvh_flst_edited', $eeMessages);

		avpvh_flst_debug_log("Edit operation completed successfully", 'Admin', $eeSFL->eeListID);
		return 'SUCCESS' . $eeAdditionalData;

	} else {
		avpvh_flst_debug_log("ERROR: Unknown action: $eeFileAction", 'Admin', $eeSFL->eeListID);
		return;
	}
}



// Plugin Version Check
function avpvh_flst_version_check() {

	global $wpdb, $eeSFL, $eeSFLU;

	if( !$eeSFL ) { return FALSE; }

	$eeSettings = $eeSFL->eeDefaultListSettings; // Start from scratch and merge-in existing

	// Search for a current or previous Free installation
	$eeInstalled = get_option('avpvh_flst_version');      // Current Free 6.x
	if(!$eeInstalled) { $eeInstalled = get_option('avpvh_flst_base_version'); } // Free 6.1.18

	if( $eeInstalled AND version_compare($eeInstalled, AVPVH_FLST_VERSION, '==') ) {

		avpvh_flst_debug_log("Nice! SFL is Up-to-Date :-)", 'Updating');

		avpvh_flst_ensure_file_list_dir_exists(1);

		return TRUE;

	} elseif(!$eeInstalled) { // New Install

		avpvh_flst_debug_log("New Installation", 'Updating');

		// Check the File List Directory
		$eeResult = avpvh_flst_validate_file_list_dir( $eeSettings['FileListDir'] );

		if($eeResult) {

			if(avpvh_flst_file_list_dir_check($eeResult)) {

				avpvh_flst_debug_log("The File List Directory is Good", 'Updating');

				// Update Database
				ksort($eeSettings); // Sort for sanity

				avpvh_flst_debug_log("Updating the Database...", 'Updating');

				$eeSettings['UploadMaxFileSize'] = $eeSFLU->actual_upload_max();
				$eeSettings['NotifyMessage'] = $eeSFL->eeNotifyMessageDefault;

				$eeSFL->eeListID = 1;
				$eeSFL->eeListSettings = $eeSettings;

				update_option('avpvh_flst_settings_1', $eeSettings);
				update_option('avpvh_flst_version', AVPVH_FLST_VERSION);

				return TRUE;

			} else {

				return FALSE;
			}
		}

		return FALSE;

	}

	// Update to Newer Version (from Free 6.1.18)
	avpvh_flst_debug_log('- Updating SFL Free from ' . $eeInstalled . ' to ' . AVPVH_FLST_VERSION . ' ...', 'Updating');

	// Merge existing list 1 settings into defaults
	$eeSettings_v6 = get_option('avpvh_flst_settings_1');
	if($eeSettings_v6) {
		$eeSettings = array_merge($eeSettings, $eeSettings_v6);
	}

	avpvh_flst_debug_log("Merging-In New Settings ...", 'Updating');

	// Merge any new default settings into existing
	$eeSettings = array_merge( $eeSFL->eeDefaultListSettings, $eeSettings );

	// 6.1 SortBy migration
	if($eeSettings['SortBy'] == 'Date') { $eeSettings['SortBy'] = 'Added'; }
	if($eeSettings['SortBy'] == 'DateMod') { $eeSettings['SortBy'] = 'Changed'; }

	// Cache setting migrations
	if(isset($eeSettings['ExpireTime'])) {
		$eeSettings['UseCache'] = $eeSettings['ExpireTime'];
		unset($eeSettings['ExpireTime']);
		if(is_numeric($eeSettings['UseCache'])) { $eeSettings['UseCache'] = 'YES'; }
	}
	if(isset($eeSettings['UseCache']) && $eeSettings['UseCache'] == 'YES') {
		$eeSettings['UseCache'] = 'DAY';
		$eeSettings['UseCacheCron'] = 'NO';
	}

	// Media settings — ensure defaults exist
	if(!isset($eeSettings['AudioEnabled'])) { $eeSettings['AudioEnabled'] = 'YES'; }
	if(!isset($eeSettings['AudioHeight'])) { $eeSettings['AudioHeight'] = 20; }

	// ShowFileDescription → ShowFileDesc rename
	if(isset($eeSettings['ShowFileDescription'])) {
		$eeSettings['ShowFileDesc'] = $eeSettings['ShowFileDescription'];
		unset($eeSettings['ShowFileDescription']);
	}

	// Check the File List Directory
	$eeResult = avpvh_flst_validate_file_list_dir( $eeSettings['FileListDir'] );
	if($eeResult) {
		avpvh_flst_file_list_dir_check($eeResult);
	}

	// Update Database
	ksort($eeSettings); // Sort for sanity
	update_option('avpvh_flst_settings_1', $eeSettings);

	// Clean up old options
	delete_option('avpvh_flst_tasks');
	delete_option('avpvh_flst_crons');
	delete_option('avpvh_flst_base_version'); // Replaced by AVPVH_FLST_VERSION
	delete_option('avpvh_flst_log');
	delete_option('avpvh_flst_free_log');

	// Delete ALL transients on version update - reset all temporary data
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_avpvh\_flst\_%' OR option_name LIKE '\_transient\_timeout\_avpvh\_flst\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk LIKE-pattern DELETE; no WP API equivalent; caching N/A for DELETE
	avpvh_flst_debug_log("All SFL transients cleared on version update", 'Updating');

	// (Simple File List 6.2.2.5 moved thumbnails from .thumbnails/ to _eeSFL_Thumbnails/. Every site
	// is past that, and version numbers restarted at 1.0.0, so that step is gone.)

	avpvh_flst_ensure_file_list_dir_exists(1);

	$eeSFL->eeUserMessages['Updating'][] = '- Plugin database at version ' . AVPVH_FLST_VERSION;
	update_option('avpvh_flst_version', AVPVH_FLST_VERSION);

	return TRUE;
}



// Send File via Email AJAX Handler
function avpvh_flst_sendfile_job() {

	global $eeSFLE;

	if(!is_object($eeSFLE) || !method_exists($eeSFLE, 'send_files_email')) {
		echo 'ERROR: Email module not loaded';
		wp_die();
	}

	$eeResult = $eeSFLE->send_files_email();

	echo esc_html($eeResult);

	wp_die();

}


function avpvh_flst_confirm() {

	if( !current_user_can('manage_options') ) { wp_die(); }

	delete_option('avpvh_flst_confirm');

	wp_die();

}


function avpvh_flst_dismiss() {

	if( !current_user_can('manage_options') ) { wp_die(); }

	delete_option('avpvh_flst_dismiss');

	wp_die();

}


function avpvh_flst_edit_job() {

	$eeResult = avpvh_flst_file_editor();

	echo esc_html($eeResult);

	wp_die();

}


function avpvh_flst_upload_job() {

	global $eeSFLU;

	// Add debugging for PHP upload configuration
	if (function_exists('avpvh_flst_debug_log')) {
		$postMaxSize = ini_get('post_max_size');
		$uploadMaxFilesize = ini_get('upload_max_filesize');
		$postContentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;

		avpvh_flst_debug_log("=== UPLOAD JOB STARTED ===", 'Upload');
		avpvh_flst_debug_log("PHP post_max_size: $postMaxSize", 'Upload');
		avpvh_flst_debug_log("PHP upload_max_filesize: $uploadMaxFilesize", 'Upload');
		avpvh_flst_debug_log("Actual POST Content-Length: " . number_format($postContentLength) . " bytes", 'Upload');

		// Convert post_max_size to bytes for comparison
		$postMaxBytes = wp_convert_hr_to_bytes($postMaxSize);
		if ($postContentLength > $postMaxBytes) {
			avpvh_flst_debug_log("WARNING: POST content exceeds PHP post_max_size limit by " . number_format($postContentLength - $postMaxBytes) . " bytes", 'UPLOAD');
			avpvh_flst_debug_log("Consider increasing PHP post_max_size in php.ini or server configuration", 'Upload');
		}
	}

	$eeResult = $eeSFLU->file_uploader();

	if (function_exists('avpvh_flst_debug_log')) {
		$user_id = get_current_user_id();
		$workflow_status = ($eeResult === 'SUCCESS') ? 'completed successfully' : 'failed';

		avpvh_flst_debug_log("Upload result: $eeResult", 'Upload');
		avpvh_flst_debug_log("User workflow: Upload $workflow_status by user $user_id", 'UX');

		if ($eeResult === 'SUCCESS') {
			avpvh_flst_debug_log("Next steps: File will be indexed and displayed in list", 'UX');
		} else {
			avpvh_flst_debug_log("User may need assistance with upload issue: $eeResult", 'UX');
		}

		avpvh_flst_debug_log("=== UPLOAD JOB COMPLETED ===", 'Upload');
	}

	echo esc_html($eeResult);

	wp_die();

}



function avpvh_flst_register_assets() {

	// Register All CSS
    wp_register_style( 'avpvh-filelist-css', plugin_dir_url(__DIR__) . 'css/styles.css', '', AVPVH_FLST_VERSION);
	wp_register_style( 'avpvh-filelist-css-theme-dark', plugins_url('css/styles-theme-dark.css', __DIR__), '', AVPVH_FLST_VERSION );
	wp_register_style( 'avpvh-filelist-css-theme-light', plugins_url('css/styles-theme-light.css', __DIR__), '', AVPVH_FLST_VERSION );
    wp_register_style( 'avpvh-filelist-css-flex', plugins_url('css/styles-flex.css', __DIR__), '', AVPVH_FLST_VERSION );
    wp_register_style( 'avpvh-filelist-css-tiles', plugins_url('css/styles-tiles.css', __DIR__), '', AVPVH_FLST_VERSION );
	wp_register_style( 'avpvh-filelist-css-table', plugins_url('css/styles-table.css', __DIR__), '', AVPVH_FLST_VERSION );
	wp_register_style( 'avpvh-filelist-css-upload', plugins_url('css/styles-upload-form.css', __DIR__), '', AVPVH_FLST_VERSION );
	wp_register_style( 'avpvh-filelist-css-media', plugins_url('css/ee-media-styles.css', __DIR__), '', AVPVH_FLST_VERSION );

	// Register JavaScripts
	wp_register_script( 'avpvh-filelist-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', array(), AVPVH_FLST_VERSION, false );
	wp_register_script( 'avpvh-filelist-js-footer', plugin_dir_url(__DIR__) . 'js/ee-footer.js', array(), AVPVH_FLST_VERSION, true );
	wp_register_script( 'avpvh-filelist-js-edit-file', plugin_dir_url(__DIR__) . 'js/ee-edit-file.js', array(), AVPVH_FLST_VERSION, true );
	wp_register_script( 'avpvh-filelist-js-uploader', plugin_dir_url(__DIR__) . 'js/ee-uploader.js', array(), AVPVH_FLST_VERSION, true );
	// wp_register_script( 'avpvh-filelist-js-media', plugin_dir_url(__DIR__) . 'js/ee-media-scripts-footer.js', array(), AVPVH_FLST_VERSION, true );
	wp_register_script( 'avpvh-filelist-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', array(), AVPVH_FLST_VERSION, true );

}


function avpvh_flst_action_plugin_links( $links ) {

	$eeLinks = array(
		'<a href="' . admin_url( 'admin.php?page=' . AVPVH_FLST_SLUG ) . '">' . __('Admin List', 'avpvh-filelist') . '</a>',
		'<a href="' . admin_url( 'admin.php?page=' . AVPVH_FLST_SLUG . '&tab=settings' ) . '">' . __('Settings', 'avpvh-filelist') . '</a>'
	);
	return array_merge( $links, $eeLinks );
}


// Load Front-side <head>
function avpvh_flst_enqueue() {

	global $eeSFL_VarsForJS;

	$eeDependents = array('jquery'); // Requires jQuery
	wp_enqueue_style('avpvh-filelist-css');
	wp_enqueue_style('avpvh-filelist-css-media');
	wp_enqueue_script('avpvh-filelist-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', $eeDependents, AVPVH_FLST_VERSION, false); // Head
	wp_enqueue_script('avpvh-filelist-js-foot', plugin_dir_url(__DIR__) . 'js/ee-footer.js', $eeDependents, AVPVH_FLST_VERSION, TRUE); // Footer
	wp_enqueue_script('avpvh-filelist-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', $eeDependents, AVPVH_FLST_VERSION, TRUE); // Email
	wp_localize_script( 'avpvh-filelist-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

	// Pass variables
	wp_localize_script( 'avpvh-filelist-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

}



// Custom Hooks
function avpvh_flst_upload_completed() {
    do_action('avpvh_flst_upload_completed'); // To be fired post-upload
}
function avpvh_flst_upload_completed_admin() {
    do_action('avpvh_flst_upload_completed_admin'); // To be fired post-upload
}



// Admin <head>
function avpvh_flst_admin_head($eeHook) {

	global $eeSFL, $eeSFL_VarsForJS;

	$deps = array('jquery');

	// wp_die($eeHook); // Check the hook
    $eeHooks = array(
    	'toplevel_page_' . AVPVH_FLST_SLUG,
    	'file-list-pro_page_ee-simple-file-list-access'
    );

    if(in_array($eeHook, $eeHooks)) {

        // Admin Styles
        wp_enqueue_style( 'avpvh-filelist-css-admin', plugins_url('css/admin.css', __DIR__), '', AVPVH_FLST_VERSION );

        // CSS
        wp_enqueue_style( 'avpvh-filelist-css', plugins_url('css/styles.css', __DIR__), '', AVPVH_FLST_VERSION );

        // List Style - Table Only
        wp_enqueue_style( 'avpvh-filelist-css-table', plugins_url('css/styles-table.css', __DIR__), '', AVPVH_FLST_VERSION );

        // Media Player CSS
        wp_enqueue_style( 'avpvh-filelist-css-media', plugins_url('css/ee-media-styles.css', __DIR__), '', AVPVH_FLST_VERSION );

        // Javascript
        wp_enqueue_script('avpvh-filelist-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', $deps, AVPVH_FLST_VERSION, FALSE);
		wp_enqueue_script('avpvh-filelist-js-back', plugin_dir_url(__DIR__) . 'js/ee-back.js', $deps, AVPVH_FLST_VERSION, FALSE);
        wp_enqueue_script('avpvh-filelist-js-foot', plugin_dir_url(__DIR__) . 'js/ee-footer.js', $deps, AVPVH_FLST_VERSION, TRUE);
        wp_enqueue_script('avpvh-filelist-js-edit-file', plugin_dir_url(__DIR__) . 'js/ee-edit-file.js',$deps, AVPVH_FLST_VERSION, TRUE);
        wp_enqueue_script('avpvh-filelist-js-uploader', plugin_dir_url(__DIR__) . 'js/ee-uploader.js', $deps, AVPVH_FLST_VERSION, TRUE);
        wp_enqueue_script('avpvh-filelist-js-media', plugin_dir_url(__DIR__) . 'js/ee-media-scripts-footer.js', $deps, AVPVH_FLST_VERSION, TRUE);
        wp_enqueue_script('avpvh-filelist-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', $deps, AVPVH_FLST_VERSION, TRUE);

		// Pass variables
		wp_localize_script('avpvh-filelist-js-head', 'eeSFL_JS', array( 'pluginsUrl' => plugins_url() ) );
		wp_localize_script( 'avpvh-filelist-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

		// Pass variables for media player in admin
		$eeSFLM_VarsForJS = array(
			'eePlayLabel' => __('Play', 'avpvh-filelist'),
			'eeBrowserWarning' => __('Browser is Not Compatible', 'avpvh-filelist'),
			'eeAudioEnabled' => 'YES', // Default to enabled in admin
			'eeAudioHeight' => 20 // Default height
		);

		// Use actual settings if available
		if ($eeSFL && isset($eeSFL->eeListSettings['AudioEnabled'])) {
			$eeSFLM_VarsForJS['eeAudioEnabled'] = $eeSFL->eeListSettings['AudioEnabled'];
			$eeSFLM_VarsForJS['eeAudioHeight'] = $eeSFL->eeListSettings['AudioHeight'];
		}

		wp_localize_script( 'avpvh-filelist-js-media', 'eeSFLM_Vars', $eeSFLM_VarsForJS );




    }
}



// Admin Pages
function avpvh_flst_admin_menu() {

	global $eeSFL;

	// Only include when accessing the plugin admin pages
	if( isset($_GET['page']) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page param for admin menu routing, not form submission

		 $eeOutput = '<!-- Simple File List Admin -->';
		avpvh_flst_debug_log("Admin Menu Loading ...", 'Loading');

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-admin-page.php'); // Admin's List Management Page

	}

	// Admin Menu Visibility: anyone who may manage at least one list
	$eeCapability = avpvh_flst_menu_capability();

	// Publish capability as a global so companion plugins (e.g. Tools) can use it
	global $eeSFL_Capability;
	$eeSFL_Capability = $eeCapability;

	// The Admin Menu
	$eeAdminPageHook = add_menu_page(
		__('AV-PvH File List', 'avpvh-filelist'), // Page Title - Defined at the top of this file
		__('File List', 'avpvh-filelist'), // Menu Title
		$eeCapability, // User status reguired to see the menu
		AVPVH_FLST_SLUG, // Slug
		'avpvh_flst_back_end', // Function that displays the menu page
		'dashicons-index-card' // Icon used
	);

}


// =============================================================================
// SET ADMIN PAGE TITLE
// =============================================================================

// Set Admin Title
function avpvh_flst_set_admin_title() {

	global $title;
	$current_screen = get_current_screen();

	if ($current_screen && $current_screen->id === 'toplevel_page_' . AVPVH_FLST_SLUG) {
		$title = __('AV-PvH File List', 'avpvh-filelist'); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- setting the admin page title is the purpose of this function
	}
}

// =============================================================================
// ADMIN DASHBOARD WIDGET
// =============================================================================

// The capability for managing list 1 (its AdminRole setting).
function avpvh_flst_get_admin_capability() {
	return avpvh_flst_list_admin_capability(1);
}


// Returns the raw file list array for a given list ID.
// Used by companion plugins (e.g. Tools) to inspect the stored file array.
function avpvh_flst_get_raw_file_list( $list_id = 1 ) {
	$list_id = absint( $list_id );
	if ( $list_id < 1 ) { $list_id = 1; }
	$file_list = get_option( 'avpvh_flst_files_' . $list_id );
	return is_array( $file_list ) ? $file_list : array();
}






?>
