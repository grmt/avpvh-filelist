<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
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
 *   eeSFL_FileSystem('copy', array('from' => '/source/path', 'to' => '/dest/path', 'overwrite' => true))
 *
 * move - Move/rename a file
 *   eeSFL_FileSystem('move', array('from' => '/source/path', 'to' => '/dest/path', 'overwrite' => true))
 *
 * delete - Delete a file or directory
 *   eeSFL_FileSystem('delete', array('file' => '/path/to/file'))
 *
 * exists - Check if file or directory exists
 *   eeSFL_FileSystem('exists', array('file' => '/path/to/check'))
 *
 * is_file - Check if path is a file
 *   eeSFL_FileSystem('is_file', array('file' => '/path/to/check'))
 *
 * is_dir - Check if path is a directory
 *   eeSFL_FileSystem('is_dir', array('path' => '/path/to/check'))
 *
 * mkdir - Create directory
 *   eeSFL_FileSystem('mkdir', array('path' => '/path/dir/', 'chmod' => 0755))
 *
 * get_contents - Read file contents
 *   eeSFL_FileSystem('get_contents', array('file' => '/path/to/file'))
 *
 * put_contents - Write file contents
 *   eeSFL_FileSystem('put_contents', array('file' => '/path/to/file', 'data' => 'content', 'mode' => 0644))
 *
 * dirlist - List directory contents
 *   eeSFL_FileSystem('dirlist', array('path' => '/path/dir/', 'include_hidden' => false, 'recursive' => false))
 *
 * filesize - Get file size in bytes
 *   eeSFL_FileSystem('filesize', array('file' => '/path/to/file'))
 *
 * filemtime - Get file modification time (Unix timestamp)
 *   eeSFL_FileSystem('filemtime', array('file' => '/path/to/file'))
 *
 * touch - Update file access and modification times (creates file if it doesn't exist)
 *   eeSFL_FileSystem('touch', array('file' => '/path/to/file', 'time' => 1234567890, 'atime' => 1234567890))
 */
function eeSFL_FileSystem($mode, $params = array()) {

	// SECURITY: Every path must resolve inside the WordPress install. This is the single
	// chokepoint for all file operations, so callers cannot be tricked into touching files
	// elsewhere via ../ sequences or symlinks. Uploaded temp files are the only exception.
	foreach ( array('from', 'to', 'file', 'path') as $eeKey ) {
		if ( ! isset($params[$eeKey]) ) { continue; }
		if ( $eeKey === 'from' && is_uploaded_file($params['from']) ) { continue; }
		if ( ! eeSFL_IsAllowedPath($params[$eeKey]) ) {
			eeSFL_Debug_Log("BLOCKED path outside allowed roots: " . $params[$eeKey], 'Security');
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
function eeSFL_IsAllowedPath($eePath) {

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

	// Allowed roots. Avoid eeSFL_GetRootPath() here: it calls eeSFL_FileSystem() itself.
	$eeRoots = array(ABSPATH, WP_CONTENT_DIR);
	if ( defined('WP_PLUGIN_DIR') ) { $eeRoots[] = WP_PLUGIN_DIR; }
	if ( defined('eeSFL_WP_ROOT') ) {
		$eeRoots[] = eeSFL_WP_ROOT;
	} elseif ( function_exists('get_transient') && ($eeCachedRoot = get_transient('eeSFL_root_path')) ) {
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
function eeSFL_EnsureFileListDirExists($eeListID = 1) {

	global $eeSFL;

	// Get the settings for this list
	$eeSettings = get_option('eeSFL_Settings_' . $eeListID);

	// If no settings, use default directory
	if (!$eeSettings || !isset($eeSettings['FileListDir'])) {
		$eeFileListDir = WP_CONTENT_DIR . '/uploads/' . eeSFL_FileListDefaultDir;
	} else {
		$eeFileListDir = $eeSFL->eeSFL_GetRootPath() . $eeSettings['FileListDir'];
	}

	eeSFL_Debug_Log("Checking if directory exists: $eeFileListDir", 'FileSystem', $eeListID);

	// Check if directory exists
	if (!file_exists($eeFileListDir)) {

		eeSFL_Debug_Log("Directory missing, creating: $eeFileListDir", 'FileSystem', $eeListID);

		// Create directory with proper permissions
		if (wp_mkdir_p($eeFileListDir)) {

			eeSFL_Debug_Log("Directory created successfully", 'FileSystem', $eeListID);

			// Create index.html for security
			$index_file = $eeFileListDir . 'index.html';
			if (!file_exists($index_file)) {
				file_put_contents($index_file, '<!-- Simple File List -->');
			}

			// If we have settings and files in the database but directory was missing,
			// the database is now out of sync. We should trigger a re-scan.
			if ($eeSettings && get_option('eeSFL_FileList_' . $eeListID)) {
				eeSFL_Debug_Log("Directory was recreated but DB has file entries - re-scan needed", 'FileSystem', $eeListID);
				// Clear the file list array to force re-scan
				delete_option('eeSFL_FileList_' . $eeListID);
			}

			return true;
		} else {
			eeSFL_Debug_Log("ERROR: Failed to create directory", 'FileSystem', $eeListID);
			return false;
		}
	}

	eeSFL_Debug_Log("Directory already exists", 'FileSystem', $eeListID);
	return true;
}




// Update a Settings Option
function eeSFL_UpdateSetting($eeSetting, $eeValue, $eeReturn = FALSE) {

	global $eeSFL;

	if(isset($eeSFL->eeListSettings[$eeSetting])) {
		$eeSFL->eeListSettings[$eeSetting] = $eeValue;
		update_option('eeSFL_Settings_' . $eeSFL->eeListID, $eeSFL->eeListSettings);

		if($eeReturn === TRUE) {
			return $eeSFL->eeListSettings;
		}

		return TRUE;
	}

	return FALSE;
}




function eeSFL_CheckSupported($eeForce = FALSE) {

	global $eeSFL;

	// Avoid running shell_exec() on every page load if already checked
	if ( ! $eeForce && get_option('eeSFL_Supported') !== false ) {
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

	update_option('eeSFL_Supported', $eeSupported);

	return TRUE;

}





// LEGACY - Convert hyphens to spaces for display only
function eeSFL_PreserveSpaces($eeFileName) {

	$eeFileName = str_replace('-', ' ', $eeFileName);

	return $eeFileName;
}





// Add the correct URL argument operator, ? or &
function eeSFL_AppendProperUrlOp($eeURL) {

	if ( strpos($eeURL, '?') ) {
		$eeURL .= '&';
	} else {
		$eeURL .= '?';
	}

	return $eeURL;
}





// Check for the Upload Folder, Create if Needed. A newly created folder gets a copy of the
// manual as a first file, unless $eeCopyManual is FALSE (lists created from the admin).
function eeSFL_FileListDirCheck($eeFileListDir, $eeCopyManual = TRUE) {

	global $eeSFL;
	static $eeReportedDirErrors = array(); // Prevent duplicate error messages across repeated calls
	$eeCopyManualFile = FALSE;

	if(!$eeFileListDir OR substr($eeFileListDir, 0, 1) == '/' OR strpos($eeFileListDir, '../') ) {

		$eeSFL->eeUserMessages['errors'][] = __('Bad Directory Given', 'simple-file-list') . ': ' . $eeFileListDir;

		return FALSE;
	}

	// Use eeSFL_WP_ROOT — the verified site root which accounts for managed hosts
	// (e.g. Pressable) where ABSPATH points to the WP core directory rather than
	// the site root. eeSFL_GetRootPath() derives the correct path from the uploads dir.
	$eeSiteRoot = $eeSFL->eeSFL_GetRootPath();

	eeSFL_Debug_Log('- Checking: ' . $eeFileListDir, 'General', $eeSFL->eeListID);

	$dir_check = eeSFL_FileSystem('is_dir', array('path' => $eeSiteRoot . $eeFileListDir));
	if( !$dir_check['data'] ) { // Directory Changed or New Install

		eeSFL_Debug_Log("New Install or Directory Change...", 'General');

		eeSFL_Debug_Log("No Directory Found. Creating ...", 'General');

		// Create directory using WordPress filesystem (primary method — Plugin Check compliant)
		$create_result = eeSFL_FileSystem('mkdir', array('path' => $eeSiteRoot . $eeFileListDir));

		if(!$create_result['success']) {
			// WP_Filesystem mkdir failed. On managed hosts (Pressable, Kinsta, WP Engine, etc.)
			// WP_Filesystem() returns false at runtime because no FTP/SSH credentials are
			// available, even though the web server process has direct write access.
			// Fall back to wp_mkdir_p() (native PHP mkdir) directly — no pre-check needed.
			if(wp_mkdir_p($eeSiteRoot . $eeFileListDir)) {
				eeSFL_Debug_Log('Directory created via wp_mkdir_p() fallback (managed host): ' . $eeSiteRoot . $eeFileListDir, 'FileSystem');
			} else {
				// Both methods failed — show error (once per directory per page load)
				if(!isset($eeReportedDirErrors[$eeFileListDir])) {
					$eeReportedDirErrors[$eeFileListDir] = TRUE;
					if ($eeSFL->eeEnvironment['eeOS'] == 'WINDOWS') {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Windows Directory:', 'simple-file-list') . ': ' . $eeFileListDir;
					} elseif($eeSFL->eeEnvironment['eeOS'] == 'LINUX') {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Linux Directory:', 'simple-file-list') . ': ' . $eeFileListDir;
					} else {
						$eeSFL->eeUserMessages['errors'][] = __('Cannot Create Directory (Unknown OS):', 'simple-file-list') . ': ' . $eeFileListDir;
					}
				}

				eeSFL_Debug_Log("CRITICAL: Directory creation failed for: " . $eeSiteRoot . $eeFileListDir, 'FileSystem');
				eeSFL_Debug_Log("Create result: " . wp_json_encode($create_result), 'FileSystem');

				return FALSE;
			}
		} else {
			// Directory creation succeeded - log success
			eeSFL_Debug_Log("Directory creation succeeded: " . $eeSiteRoot . $eeFileListDir, 'FileSystem');
		}

		// Verify directory was created — use native is_dir() since WP_Filesystem may not
		// be available on managed hosts (same reason we added the fallback above).
		if(!is_dir($eeSiteRoot . $eeFileListDir)) {
			$eeSFL->eeUserMessages['errors'][] = __('Cannot create the upload directory', 'simple-file-list') . ': ' . $eeFileListDir;
			$eeSFL->eeUserMessages['errors'][] = __('Please check directory permissions', 'simple-file-list');

			return FALSE;

		} else {

			$eeCopyManualFile = TRUE;

			eeSFL_Debug_Log("The File List Dir Has Been Created!", 'General');
			eeSFL_Debug_Log($eeFileListDir, 'General', $eeSFL->eeListID);
		}

	} else {
		eeSFL_Debug_Log("FileListDir Looks Good", 'General');
	}

	// Check index.html, create if needed.
	if( strlen($eeFileListDir) >= 2 ) {

		$eeFile = $eeSiteRoot . $eeFileListDir . 'index.html'; // Disallow direct file indexing.

		$file_check = eeSFL_FileSystem('is_file', array('file' => $eeFile));
		if(!$file_check['data']) {

			// Get template content using WordPress filesystem
			$template_content = eeSFL_FileSystem('get_contents', array('file' => $eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-index-template.html'));

			if($template_content['success']) {
				// Write index.html using WordPress filesystem
				$write_result = eeSFL_FileSystem('put_contents', array(
					'file' => $eeFile,
					'data' => $template_content['data']
				));

				if(!$write_result['success']) {
					$eeSFL->eeUserMessages['warnings'][] = __('WARNING! Could not write file', 'simple-file-list') . ': index.html';
					$eeSFL->eeUserMessages['warnings'][] = __('Please upload a blank index file to this location to prevent unauthorized access.', 'simple-file-list');
					$eeSFL->eeUserMessages['warnings'][] = $eeSiteRoot . $eeFileListDir;
				}
			}
		}

		if($eeCopyManualFile === TRUE && $eeCopyManual) {

			// Copy the Manual to the new directory, so there's at least one file.
			$eeCopyFrom = $eeSFL->eeEnvironment['pluginDir'] . 'Simple-File-List.pdf';
			$eeCopyTo = $eeSiteRoot . $eeFileListDir . 'Simple-File-List.pdf';
			eeSFL_FileSystem('copy', array('from' => $eeCopyFrom, 'to' => $eeCopyTo));
		}
	}

	return TRUE; // Looks Good

}










// Return the size of a file in a nice format.
// Accepts a path or file size in bytes
function eeSFL_GetFileSize($eeSFL_File) {

    if( is_numeric($eeSFL_File) ) {
		$bytes = $eeSFL_File;
	} else {
		$file_check = eeSFL_FileSystem('is_file', array('file' => eeSFL_WP_ROOT . $eeSFL_File));
		if($file_check['data']) {
			$size_result = eeSFL_FileSystem('filesize', array('file' => eeSFL_WP_ROOT . $eeSFL_File));
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


function eeSFL_NormalizeSlashes($eePath) {

	if(empty($eePath)) { return ''; }

	// Normalize Windows backslashes to forward slashes
	$eePath = str_replace('\\', '/', $eePath);

	// Ensure there is no leading slash
	$eePath = ltrim($eePath, '/');

	// Ensure there is a single trailing slash
	$eePath = rtrim($eePath, '/') . '/';

	// Find/Replace // with /
	$eePath = str_replace('//', '/', $eePath);

	return $eePath;
}



function eeSFL_SanitizeFolderName($eeFolderName) {

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
function eeSFL_ValidateFileListDir($eeDir) {

	global $eeSFL;

	if(!$eeDir) {
		$eeSFL->eeUserMessages['errors'][] = __('No Path Found', 'simple-file-list');
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

			$eeSFL->eeUserMessages['errors'][] = __('Path is Not Direct', 'simple-file-list');
			$eeSFL->eeUserMessages['errors'][] = $eeDir;
			return FALSE;
		}

		$eePieces = explode('/', $eeDir);
		$eeDir = ''; // Reset for rebuilding

		foreach( $eePieces as $eePiece ) { // Rebuild as we sanitize each level

			$eePiece = eeSFL_SanitizeFolderName($eePiece);

		    if($eePiece AND strlen($eePiece) <= 255) {

			    $eeDir .= $eePiece . '/';
		    }
		}

	} else {

		$eeCheck = substr($eeDir, 0, -1); // Exclude the trailing slash
		$eeDir = eeSFL_SanitizeFolderName( $eeCheck ) . '/'; // Add it back
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

	$eeSFL->eeUserMessages['errors'][] = __('Path Failed Validation', 'simple-file-list');
	return FALSE;
}



// Yes or No Settings Checkboxes
function eeSFL_ProcessCheckboxInput($eeTerm) {

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
function eeSFL_ProcessTextInput($eeTerm, $eeType = 'text') {

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
function eeSFL_ReturnHeaderString($eeFrom, $eeCc = FALSE, $eeBcc = FALSE) {

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
function eeSFL_ProcessEmailString($eeString) {

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




// Protect file list directories form direct URL access (hotlinking)
function eeSFL_LimitDirAccess($eeMode) {

	global $eeSFL;

	if( eeSFL_ListIsProtected($eeSFL->eeListSettings) ) { return eeSFL_ProtectListDir(); } // Files go through the download handler

	// Delete and Start Over
	$eeFile = $eeSFL->eeSFL_GetRootPath() . $eeSFL->eeListSettings['FileListDir'] . '.htaccess';
	$file_check = eeSFL_FileSystem('exists', array('file' => $eeFile));
	if( $file_check['data'] ) {
		eeSFL_FileSystem('delete', array('file' => $eeFile));
	}

	if($eeMode == 'YES' OR $eeMode == 'NORMAL') { // Not Needed
		return TRUE;
	}

	// Apache / LightSpeed
	if(stripos($eeSFL->eeEnvironment['eeWebServer'], 'Apache') === 0 OR stripos($eeSFL->eeEnvironment['eeWebServer'], 'LiteSpeed') === 0 ) { // Write .htaccess

		// Check .htaccess file, create if needed.
		$eeFile = $eeSFL->eeSFL_GetRootPath() . $eeSFL->eeListSettings['FileListDir'] . '.htaccess';

		// Get template content using WordPress filesystem
		$template_content = eeSFL_FileSystem('get_contents', array('file' => plugin_dir_path(__FILE__) . 'htaccess-template.txt'));

		if(!$template_content['success']) {
			$eeSFL->eeUserMessages['errors'][] = __('ERROR: Could not read .htaccess template', 'simple-file-list');
			return FALSE;
		}

		// Assign our file types
		$eeFormats = str_replace(',', '|', $eeSFL->eeListSettings['FileFormats']);
		$eeFormats = str_replace(' ', '', $eeFormats);
		$eeString = str_replace('FILE_TYPES', $eeFormats, $template_content['data']);

		// Create .htaccess file using WordPress filesystem
		$write_result = eeSFL_FileSystem('put_contents', array(
			'file' => $eeFile,
			'data' => $eeString
		));

		if(!$write_result['success']) {
			$eeSFL->eeUserMessages['errors'][] = __('WARNING: Could not write', 'simple-file-list') . ' .htaccess';
			return FALSE;
		} else {
			eeSFL_Debug_Log(" .htaccess file has been set", 'General');
		}

	// NGINX
	} elseif( stripos($eeSFL->eeEnvironment['eeWebServer'], 'nginx') === 0 ) {

		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'simple-file-list') . ' - ' . 'Nginx ' . __('Web Server Detected', 'simple-file-list') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'simple-file-list') . '<br />
				<a href="https://kinsta.com/blog/hotlinking/#nginx" target="_blank">' . __('Learn More', 'simple-file-list') . '</a>';

	// Microsoft IIS
	} elseif( stripos($eeSFL->eeEnvironment['eeWebServer'], 'iis') OR stripos($eeSFL->eeEnvironment['eeWebServer'], 'iis') === 0  ) {

		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'simple-file-list') . ' - ' . 'IIS ' . __('Web Server Detected', 'simple-file-list') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'simple-file-list') . '<br />
				<a href="https://medium.com/@ankittyagi366/how-to-prevent-hotlinking-in-iis-server-asp-net-framework-asp-net-core-application-21900edd80d7" target="_blank">' . __('Learn More', 'simple-file-list') . '</a>';

	// Something Else
	} else {
		$eeSFL->eeUserMessages['messages'][] = '<strong>' . __('IMPORTANT', 'simple-file-list') . ' - ' . __('Unknown Web Server', 'simple-file-list') . '</strong><br />' .
			__('To prevent direct URL access to your files you must manually update your server configuration.', 'simple-file-list');
	}
}




// ============================================================================
// FILE EDITOR OPERATION FUNCTIONS
// ============================================================================

/**
 * Delete a file or folder
 *
 * @param string $eeFileName The name of the file/folder to delete
 * @param string|false $eeSubFolder The subfolder path (or FALSE if in root)
 * @return string 'SUCCESS' or error message
 */
function eeSFL_DeleteFile($eeFileName, $eeSubFolder = FALSE) {

	global $eeSFL, $eeSFLF;

	eeSFL_Debug_Log("DELETE: Starting delete operation for '$eeFileName'", 'FileOps', $eeSFL->eeListID);

	// Normalize subfolder - convert '/' or FALSE to empty string
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';

	$eeMessages = array('Deleting File');

	// Construct full file path
	$eeFilePath = eeSFL_WP_ROOT . $eeSFL->eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileName;
	eeSFL_Debug_Log("DELETE: Full path: '$eeFilePath'", 'FileOps', $eeSFL->eeListID);

	// Confinement check — ensure the resolved path stays within the list directory.
	// Guards against path traversal in both $eeSubFolder (Pro) and $eeFileName.
	$eeBaseDir = realpath(eeSFL_WP_ROOT . $eeSFL->eeListSettings['FileListDir']);
	$eeRealPath = realpath($eeFilePath);
	if( $eeBaseDir && $eeRealPath ) {
		$eeBaseDirNorm = rtrim(str_replace('\\', '/', $eeBaseDir), '/') . '/';
		$eeRealPathNorm = str_replace('\\', '/', $eeRealPath);
		if( strpos($eeRealPathNorm, $eeBaseDirNorm) !== 0 ) { // Trailing slash: siblings like "simple-file-list-old/" are outside
			eeSFL_Debug_Log("ERROR: Path traversal attempt blocked: '$eeFilePath'", 'FileOps', $eeSFL->eeListID);
			return 'ERROR: Invalid path';
		}
	}

	$eeMessages[] = $eeSFL->eeListSettings['FileListDir'] . $eeFileName;

	// Check if it's a file
	if( eeSFL_FileSystem('is_file', array('file' => $eeFilePath))['data'] ) {

		eeSFL_Debug_Log("DELETE: Item is a file, proceeding with file deletion", 'FileOps', $eeSFL->eeListID);

		$eeDeleteResult = eeSFL_FileSystem('delete', array('file' => $eeFilePath));

		if($eeDeleteResult['success']) {

		eeSFL_Debug_Log("DELETE: File deleted successfully from disk", 'FileOps', $eeSFL->eeListID);

		// Remove from array
		foreach( $eeSFL->eeAllFiles as $eeKey => $eeThisFileArray) {
			if($eeThisFileArray['FilePath'] == $eeSubFolderPath . $eeFileName) {
				unset($eeSFL->eeAllFiles[$eeKey]);
				eeSFL_Debug_Log("DELETE: Removed file from array at key: $eeKey", 'FileOps', $eeSFL->eeListID);
				break;
			}
		}

		// Reduce the parent folder's item count
		if($eeSubFolderPath) {
			foreach( $eeSFL->eeAllFiles as $eeKey => $eeThisFileArray) {
				if($eeThisFileArray['FilePath'] == $eeSubFolderPath) {
					$eeSFL->eeAllFiles[$eeKey]['ItemCount'] = $eeThisFileArray['ItemCount'] - 1;
					eeSFL_Debug_Log("DELETE: Updated parent folder item count", 'FileOps', $eeSFL->eeListID);
					break;
				}
			}
		}


		$eeSFL->eeSFL_UpdateMainFileArray(false);
		$eeSFL->eeSFL_UpdateThumbnail($eeSubFolderPath . $eeFileName, FALSE, $eeSFL->eeListID);

		// Custom Hook
		$eeMessages[] = 'File Deleted';
			do_action('eeSFL_Hook_Deleted', $eeMessages);

			eeSFL_Debug_Log("DELETE: File deletion completed successfully", 'FileOps', $eeSFL->eeListID);
			return 'SUCCESS';

		} else {
			eeSFL_Debug_Log("DELETE: File delete failed: " . $eeFileName, 'FileOps', $eeSFL->eeListID);
			return __('File Delete Failed', 'simple-file-list') . ':' . $eeFileName;
		}

	} elseif( eeSFL_FileSystem('is_dir', array('path' => $eeFilePath))['data'] ) {

	// Delete Folder
	eeSFL_Debug_Log("DELETE: Item is a folder, proceeding with folder deletion", 'FileOps', $eeSFL->eeListID);

	if($eeSFLF && $eeSFLF->eeSFLF_DeleteFolder($eeFilePath) ) {

		eeSFL_Debug_Log("DELETE: Folder deleted successfully from disk", 'FileOps', $eeSFL->eeListID);			// Remove from the array
			$eeFilePathArray = $eeSubFolderPath . $eeFileName . '/';

			foreach( $eeSFL->eeAllFiles as $eeKey => $eeThisFileArray ) {
			if( strpos($eeThisFileArray['FilePath'], $eeFilePathArray) === 0 ) {
				unset($eeSFL->eeAllFiles[$eeKey]);
			}
		}

		// Update the counts and sizes in the array
		if($eeSFLF) {
			$eeSFLF->eeSFLF_UpdateFolderSizes($eeSubFolderPath);
		}
		$eeSFL->eeSFL_UpdateMainFileArray(false);			// Custom Hook
			$eeMessages[] = 'Folder Deleted';
			do_action('eeSFL_Hook_Deleted', $eeMessages);

			eeSFL_Debug_Log("DELETE: Folder deletion completed successfully", 'FileOps', $eeSFL->eeListID);
			return 'SUCCESS';

		} else {
			eeSFL_Debug_Log("DELETE: Folder delete failed: " . $eeFilePath, 'FileOps', $eeSFL->eeListID);
			return __('Folder Delete Failed', 'simple-file-list') . ':' . $eeFilePath;
		}

	} else {
		eeSFL_Debug_Log("DELETE: Item not found or unknown type: " . $eeFilePath, 'FileOps', $eeSFL->eeListID);
		return __('Unknown Item', 'simple-file-list') . ':' . $eeFilePath;
	}
}


/**
 * Update file nice name
 *
 * @param string $eeFileName The file name
 * @param string|false $eeSubFolder The subfolder path
 * @param string $eeFileNiceNameNew The new nice name
 * @return string Success message or empty string
 */
function eeSFL_UpdateFileNiceName($eeFileName, $eeSubFolder, $eeFileNiceNameNew) {

	global $eeSFL;

	// Normalize subfolder path
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';
	eeSFL_Debug_Log("EDIT NICE NAME: Normalized subfolder from '$eeSubFolder' to '$eeSubFolderPath'", 'FileOps', $eeSFL->eeListID);

	$eeFileNiceNameNew = trim(sanitize_text_field($eeFileNiceNameNew)); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage

	if(strlen($eeFileNiceNameNew) < 1) { $eeFileNiceNameNew = ''; }

	$eeSFL->eeSFL_UpdateFileDetail($eeSubFolderPath . $eeFileName, 'FileNiceName', $eeFileNiceNameNew);

	eeSFL_Debug_Log("EDIT: Updated nice name to: '$eeFileNiceNameNew'", 'FileOps', $eeSFL->eeListID);

	return 'Nice Name: ' . $eeFileNiceNameNew;
}


/**
 * Update file description
 *
 * @param string $eeFileName The file name
 * @param string|false $eeSubFolder The subfolder path
 * @param string $eeFileDescriptionNew The new description
 * @return string Success message or empty string
 */
function eeSFL_UpdateFileDescription($eeFileName, $eeSubFolder, $eeFileDescriptionNew) {

	global $eeSFL;

	// Normalize subfolder path
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';
	eeSFL_Debug_Log("EDIT DESC: Normalized subfolder from '$eeSubFolder' to '$eeSubFolderPath'", 'FileOps', $eeSFL->eeListID);

	$eeFileDescriptionNew = trim(sanitize_text_field($eeFileDescriptionNew)); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage

	if(strlen($eeFileDescriptionNew) < 1) { $eeFileDescriptionNew = ''; }
	if(!strpos($eeFileName, '.')) { $eeFileName .= '/'; } // Folder

	$eeSFL->eeSFL_UpdateFileDetail($eeSubFolderPath . $eeFileName, 'FileDescription', $eeFileDescriptionNew);

	eeSFL_Debug_Log("EDIT: Updated description to: '$eeFileDescriptionNew'", 'FileOps', $eeSFL->eeListID);

	return 'Description: ' . $eeFileDescriptionNew;
}


/**
 * Update file date added
 *
 * @param string $eeFileName The file name
 * @param string|false $eeSubFolder The subfolder path
 * @param string $eeDate The new date (YYYY-MM-DD format)
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean, 'message' string, and optional 'additionalData' string
 */
function eeSFL_UpdateFileDateAdded($eeFileName, $eeSubFolder, $eeDate, $eeListSettings) {

	global $eeSFL;

	// Normalize subfolder path
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';
	eeSFL_Debug_Log("EDIT DATE ADDED: Normalized subfolder from '$eeSubFolder' to '$eeSubFolderPath'", 'FileOps', $eeSFL->eeListID);

	if(strlen($eeDate) < 1) {
		return array('success' => true, 'message' => '');
	}

	// Validate date
	$eeArray = explode('-', $eeDate);
	if( !checkdate( $eeArray[1], $eeArray[2], $eeArray[0]) ) {
		eeSFL_Debug_Log("EDIT: Invalid date added format: $eeDate", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => 'Bad Date, Indiana! ' . $eeArray[1] . ', ' . $eeArray[2] . ', ' . $eeArray[0]
		);
	}

	// Update the Database
	$eeSFL->eeSFL_UpdateFileDetail($eeSubFolderPath . $eeFileName, 'FileDateAdded', $eeDate . ' 00:00:00' );

	eeSFL_Debug_Log("EDIT: Updated date added to: $eeDate", 'FileOps', $eeSFL->eeListID);

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
 * @param string|false $eeSubFolder The subfolder path
 * @param string $eeDate The new date (YYYY-MM-DD format)
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean, 'message' string, and optional 'additionalData' string
 */
function eeSFL_UpdateFileDateChanged($eeFileName, $eeSubFolder, $eeDate, $eeListSettings) {

	global $eeSFL;

	// Normalize subfolder path
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';
	eeSFL_Debug_Log("EDIT DATE CHANGED: Normalized subfolder from '$eeSubFolder' to '$eeSubFolderPath'", 'FileOps', $eeSFL->eeListID);

	if(strlen($eeDate) < 1) {
		return array('success' => true, 'message' => '');
	}

	// Validate date
	$eeArray = explode('-', $eeDate);
	if( !checkdate( $eeArray[1], $eeArray[2], $eeArray[0]) ) {
		eeSFL_Debug_Log("EDIT: Invalid date changed format: $eeDate", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => 'Bad Date, Indiana! ' . $eeArray[1] . ', ' . $eeArray[2] . ', ' . $eeArray[0]
		);
	}

	// Update the File
	$eeDateTime = strtotime($eeDate);
	$eeFilePath = eeSFL_WP_ROOT . $eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileName;
	$eeFileCheck = eeSFL_FileSystem('exists', array('file' => $eeFilePath));

	if($eeFileCheck['success'] && $eeFileCheck['data'] && $eeDateTime) {

		// Touch the file
		$eeTouchResult = eeSFL_FileSystem('touch', array('file' => $eeFilePath, 'time' => $eeDateTime));

		// Update the Database
		$eeSFL->eeSFL_UpdateFileDetail($eeSubFolderPath . $eeFileName, 'FileDateChanged', $eeDate . ' 00:00:00' );

		eeSFL_Debug_Log("EDIT: Updated date changed to: $eeDate", 'FileOps', $eeSFL->eeListID);

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
 * @param string|false $eeSubFolder The subfolder path
 * @param string $eeFileNameNew The new file name
 * @param array $eeListSettings The list settings
 * @return array Array with 'success' boolean and 'message' string
 */
function eeSFL_RenameFile($eeFileName, $eeSubFolder, $eeFileNameNew, $eeListSettings) {

	global $eeSFL;

	// Normalize subfolder path
	$eeSubFolderPath = ($eeSubFolder && $eeSubFolder !== '/') ? $eeSubFolder : '';
	eeSFL_Debug_Log("EDIT RENAME: Normalized subfolder from '$eeSubFolder' to '$eeSubFolderPath'", 'FileOps', $eeSFL->eeListID);

	eeSFL_Debug_Log("EDIT: Rename requested from '$eeFileName' to '$eeFileNameNew'", 'FileOps', $eeSFL->eeListID);

	$eeFileNameNew = urldecode( $eeFileNameNew );
	$eeFileNameNew = sanitize_file_name( $eeFileNameNew );

	if( strlen($eeFileNameNew) < 1 ) {
		eeSFL_Debug_Log("EDIT: Invalid new file name", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Invalid New File Name', 'simple-file-list')
		);
	}

	if(strpos($eeFileName, '.') === FALSE) { // Folder
		$eeFileNameNew = str_replace('.', '_', $eeFileNameNew); // Prevent adding an extension
		eeSFL_Debug_Log("EDIT: Renaming folder", 'FileOps', $eeSFL->eeListID);
	} else {
		// Prevent changing file extension
		$eePathParts = pathinfo($eeFileName);
		$eeOldExtension = strtolower($eePathParts['extension']);
		$eePathParts = pathinfo($eeFileNameNew);
		$eeNewExtension = strtolower($eePathParts['extension']);
		if($eeOldExtension != $eeNewExtension) {
			eeSFL_Debug_Log("EDIT: Rename blocked - extension change not allowed", 'FileOps', $eeSFL->eeListID);
			return array(
				'success' => false,
				'message' => __('Changing the File Extension is Not Allowed', 'simple-file-list')
			);
		}
	}

	// Security check for path traversal
	$eeSFL->eeSFL_DetectUpwardTraversal($eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileNameNew );

	// Check for duplicate file
	if(eeSFL_FileSystem('is_file', array('file' => eeSFL_WP_ROOT . $eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileNameNew))['data']) {
		eeSFL_Debug_Log("EDIT: Rename blocked - duplicate name found", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Cannot Change the Name. Item with Same Name Found.', 'simple-file-list')
		);
	}

	// SECURITY: the old name comes straight from POST — it must stay inside the list directory as well.
	$eeSFL->eeSFL_DetectUpwardTraversal($eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileName );

	// Rename file on disk
	$eeFilePathOld = eeSFL_WP_ROOT . $eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileName;
	$eeFilePathNew = eeSFL_WP_ROOT . $eeListSettings['FileListDir'] . $eeSubFolderPath . $eeFileNameNew;

	$eeOldFileCheck = eeSFL_FileSystem('exists', array('file' => $eeFilePathOld));
	if(!($eeOldFileCheck['success'] && $eeOldFileCheck['data'])) {
		eeSFL_Debug_Log("EDIT: Rename failed - file not found: $eeFilePathOld", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('File Not Found', 'simple-file-list') . ': ' . basename($eeFilePathOld)
		);
	}

	if( !eeSFL_FileSystem('move', array('from' => $eeFilePathOld, 'to' => $eeFilePathNew))['success'] ) {
		eeSFL_Debug_Log("EDIT: Rename failed - could not move file", 'FileOps', $eeSFL->eeListID);
		return array(
			'success' => false,
			'message' => __('Could Not Change the Name', 'simple-file-list') . ' ' . $eeFilePathOld . ' ' . __('to', 'simple-file-list') . ' ' . $eeFilePathNew
		);
	}

	// Update database
	$eeSFL->eeSFL_UpdateFileDetail($eeSubFolderPath . $eeFileName, 'FilePath', $eeSubFolderPath . $eeFileNameNew);
	eeSFL_Debug_Log("EDIT: Rename successful", 'FileOps', $eeSFL->eeListID);

	return array(
		'success' => true,
		'message' => 'Renamed to|' . $eeListSettings['FileListDir'] . $eeFileNameNew
	);
}


// File Editor Engine
function eeSFL_FileEditor() {

	global $eeSFL;

	eeSFL_Debug_Log("=== FILE EDITOR START ===", 'Admin', 0);

	// WP Security
	$nonceCheck = check_ajax_referer( 'eeSFL_ActionNonce', 'eeSecurity', false );
	eeSFL_Debug_Log("Nonce check result: " . ($nonceCheck ? 'PASS' : 'FAIL'), 'Admin', 0);

	if( !$nonceCheck ) {
		eeSFL_Debug_Log("ERROR: Security check failed", 'Admin', 0);
		return 'ERROR 98';
	}

	// The List ID
	if( isset($_POST['eeSFL_ID']) && !empty(sanitize_text_field(wp_unslash($_POST['eeSFL_ID']))) ) {
		$eeSFL->eeListID = filter_var(sanitize_text_field(wp_unslash($_POST['eeSFL_ID'])), FILTER_VALIDATE_INT);
	} else {
		eeSFL_Debug_Log("ERROR: Missing List ID", 'Admin', 0);
		return "Missing ID";
	}

	eeSFL_Debug_Log("Processing request for List ID: " . $eeSFL->eeListID, 'Admin', $eeSFL->eeListID);
	$eeSFL->eeSFL_GetSettings($eeSFL->eeListID);
	if( empty($eeSFL->eeListSettings['FileListDir']) ) {
		eeSFL_Debug_Log("ERROR: Unknown list ID", 'Admin', 0);
		return 'Bad List ID';
	}

	// Check if we should be doing this
	// Note: is_admin() is NOT a user-identity check — it is TRUE for ALL admin-ajax.php requests,
	// including unauthenticated nopriv ones. Use current_user_can() for authorization.
	// When AllowFrontManage = YES the gate is intentionally open to all page visitors (by design).
	if( !current_user_can('manage_options') AND ($eeSFL->eeListSettings['AllowFrontManage'] ?? 'NO') != 'YES' ) {
		eeSFL_Debug_Log("ERROR: Front manage not allowed", 'Admin', $eeSFL->eeListID);
		return;
	}

	$eeSFL->eeAllFiles = get_option('eeSFL_FileList_' . $eeSFL->eeListID);

	// The Action
	if( isset($_POST['eeFileAction']) && strlen(sanitize_text_field(wp_unslash($_POST['eeFileAction']))) ) {
		$eeFileAction = sanitize_text_field(wp_unslash($_POST['eeFileAction']));
	} else {
		eeSFL_Debug_Log("ERROR: Missing action", 'Admin', $eeSFL->eeListID);
		return "Missing the Action";
	}

	// The Current File Name
	if( isset($_POST['eeFileName']) && strlen(sanitize_text_field(wp_unslash($_POST['eeFileName']))) ) {
		$eeFileName = sanitize_text_field(wp_unslash($_POST['eeFileName'])); // sanitize only — esc_textarea() would corrupt names with quotes when matching against the file array
	} else {
		eeSFL_Debug_Log("ERROR: Missing file name", 'Admin', $eeSFL->eeListID);
		return "Missing the File Name";
	}

	// SECURITY: The free version lists only the root directory, so a file name never contains a path.
	// Reject separators and dot entries: the Edit actions (dates, nice name, description) build
	// FileListDir . $eeFileName without a traversal check, so "../x" would reach files outside the list.
	if( strpbrk($eeFileName, '/\\') !== FALSE || $eeFileName === '.' || $eeFileName === '..' ) {
		eeSFL_Debug_Log("ERROR: File name contains a path: " . $eeFileName);
		return "Invalid File Name";
	}

	// Subfolder support is a Pro feature — the free version always operates on the root directory.
	// The eeSubFolder POST parameter is intentionally ignored here to eliminate the path traversal
	// attack surface entirely (CVE-2026-11911). Pro: apply path traversal sanitization wherever
	// eeSubFolder is read from POST — strip ../ sequences and validate the resolved path stays
	// within FileListDir using realpath() before passing to eeSFL_DeleteFile() / eeSFL_RenameFile().
	$eeSubFolder = FALSE;

	eeSFL_Debug_Log("Action: $eeFileAction, File: $eeFileName, SubFolder: " . ($eeSubFolder ? $eeSubFolder : 'ROOT'), 'Admin', $eeSFL->eeListID);

	// ===== DISPATCHER =====
	// Route to appropriate specialized function

	if($eeFileAction == 'Delete') {

		eeSFL_Debug_Log("Routing to eeSFL_DeleteFile()", 'Admin', $eeSFL->eeListID);
		return eeSFL_DeleteFile($eeFileName, $eeSubFolder);

	} elseif($eeFileAction == 'Edit') {

		eeSFL_Debug_Log("Routing to Edit functions", 'Admin', $eeSFL->eeListID);

		$eeMessages = array();
		$eeAdditionalData = '';

		// Nice Name
		if(isset($_POST['eeFileNiceNameNew']) && $_POST['eeFileNiceNameNew'] != 'false') {
			$eeFileNiceNameNew = trim(sanitize_text_field(wp_unslash($_POST['eeFileNiceNameNew']))); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage
			$eeResult = eeSFL_UpdateFileNiceName($eeFileName, $eeSubFolder, $eeFileNiceNameNew);
			if($eeResult) { $eeMessages[] = $eeResult; }
		}

		// Description
		if(isset($_POST['eeFileDescNew']) && $_POST['eeFileDescNew'] != 'false') {
			$eeFileDescriptionNew = trim(sanitize_text_field(wp_unslash($_POST['eeFileDescNew']))); // sanitize_text_field only — esc_textarea() is for HTML output, not DB storage
			$eeResult = eeSFL_UpdateFileDescription($eeFileName, $eeSubFolder, $eeFileDescriptionNew);
			if($eeResult) { $eeMessages[] = $eeResult; }
		}

		// Date Added
		if(isset($_POST['eeFileDateAdded'])) {
			$eeDate = preg_replace("/[^0-9-]/", "", sanitize_text_field(wp_unslash($_POST['eeFileDateAdded'])));
			$eeResult = eeSFL_UpdateFileDateAdded($eeFileName, $eeSubFolder, $eeDate, $eeSFL->eeListSettings);
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
			$eeResult = eeSFL_UpdateFileDateChanged($eeFileName, $eeSubFolder, $eeDate, $eeSFL->eeListSettings);
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
			$eeResult = eeSFL_RenameFile($eeFileName, $eeSubFolder, $eeFileNameNew, $eeSFL->eeListSettings);
			if(!$eeResult['success']) {
				return $eeResult['message'];
			}
			if($eeResult['message']) {
				$eeMessages[] = $eeResult['message'];
			}
		}

		// Custom Hook
		do_action('eeSFL_Hook_Edited', $eeMessages);

		eeSFL_Debug_Log("Edit operation completed successfully", 'Admin', $eeSFL->eeListID);
		return 'SUCCESS' . $eeAdditionalData;

	} else {
		eeSFL_Debug_Log("ERROR: Unknown action: $eeFileAction", 'Admin', $eeSFL->eeListID);
		return;
	}
}



// Plugin Version Check
function eeSFL_VersionCheck() {

	global $wpdb, $eeSFL, $eeSFLU;

	if( !$eeSFL ) { return FALSE; }

	$eeSettings = $eeSFL->eeDefaultListSettings; // Start from scratch and merge-in existing

	// Search for a current or previous Free installation
	$eeInstalled = get_option('eeSFL_Version');      // Current Free 6.x
	if(!$eeInstalled) { $eeInstalled = get_option('eeSFL_BASE_Version'); } // Free 6.1.18

	if( $eeInstalled AND version_compare($eeInstalled, eeSFL_Version, '==') ) {

		eeSFL_Debug_Log("Nice! SFL is Up-to-Date :-)", 'Updating');

		eeSFL_EnsureFileListDirExists(1);

		return TRUE;

	} elseif(!$eeInstalled) { // New Install

		eeSFL_Debug_Log("New Installation", 'Updating');

		// Check the File List Directory
		$eeResult = eeSFL_ValidateFileListDir( $eeSettings['FileListDir'] );

		if($eeResult) {

			if(eeSFL_FileListDirCheck($eeResult)) {

				eeSFL_Debug_Log("The File List Directory is Good", 'Updating');

				// Update Database
				ksort($eeSettings); // Sort for sanity

				eeSFL_Debug_Log("Updating the Database...", 'Updating');

				$eeSettings['UploadMaxFileSize'] = $eeSFLU->eeSFL_ActualUploadMax();
				$eeSettings['NotifyMessage'] = $eeSFL->eeNotifyMessageDefault;

				$eeSFL->eeListID = 1;
				$eeSFL->eeListSettings = $eeSettings;

				update_option('eeSFL_Settings_1', $eeSettings);
				update_option('eeSFL_Version', eeSFL_Version);

				// Add First File - The Documentation
				$eeCopyFrom = dirname(dirname(__FILE__)) . '/Simple-File-List.pdf';
				$eeCopyTo = $eeSFL->eeSFL_GetRootPath() . $eeSettings['FileListDir'] . 'Simple-File-List.pdf';
				return TRUE;

			} else {

				return FALSE;
			}
		}

		return FALSE;

	}

	// Update to Newer Version (from Free 6.1.18)
	eeSFL_Debug_Log('- Updating SFL Free from ' . $eeInstalled . ' to ' . eeSFL_Version . ' ...', 'Updating');

	// Merge existing list 1 settings into defaults
	$eeSettings_v6 = get_option('eeSFL_Settings_1');
	if($eeSettings_v6) {
		$eeSettings = array_merge($eeSettings, $eeSettings_v6);
	}

	eeSFL_Debug_Log("Merging-In New Settings ...", 'Updating');

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
	$eeResult = eeSFL_ValidateFileListDir( $eeSettings['FileListDir'] );
	if($eeResult) {
		eeSFL_FileListDirCheck($eeResult);
	}

	// Update Database
	ksort($eeSettings); // Sort for sanity
	update_option('eeSFL_Settings_1', $eeSettings);

	// Clean up old options
	delete_option('eeSFL_Tasks');
	delete_option('eeSFL_Crons');
	delete_option('eeSFL_BASE_Version'); // Replaced by eeSFL_Version
	delete_option('eeSFL_TheLog');
	delete_option('eeSFL_FREE_Log');

	// Delete ALL transients on version update - reset all temporary data
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_eeSFL%' OR option_name LIKE '\_transient\_timeout\_eeSFL%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk LIKE-pattern DELETE; no WP API equivalent; caching N/A for DELETE
	eeSFL_Debug_Log("All SFL transients cleared on version update", 'Updating');

	// Thumbnail directory migration (.thumbnails → _eeSFL_Thumbnails)
	// The old dot-prefix dir was blocked by nginx on managed hosts (Pressable, etc.)
	if ( version_compare( $eeInstalled, '6.2.2.5', '<' ) ) {

		eeSFL_Debug_Log( 'Migrating .thumbnails → _eeSFL_Thumbnails ...', 'Updating' );

		$eeFileListDir = isset( $eeSettings['FileListDir'] ) ? $eeSettings['FileListDir'] : '';

		if ( $eeFileListDir ) {

			$eeOldThumbDir = eeSFL_WP_ROOT . $eeFileListDir . '.thumbnails/';
			$eeNewThumbDir = eeSFL_WP_ROOT . $eeFileListDir . '_eeSFL_Thumbnails/';

			$eeOldExists = eeSFL_FileSystem( 'is_dir', array( 'path' => $eeOldThumbDir ) );

			if ( $eeOldExists && ! empty( $eeOldExists['data'] ) ) {

				$eeMkResult = wp_mkdir_p( $eeNewThumbDir );
				if ( $eeMkResult ) {

					$eeDirContents = eeSFL_FileSystem( 'dirlist', array( 'path' => $eeOldThumbDir, 'include_hidden' => TRUE, 'recursive' => FALSE ) );

					if ( $eeDirContents && ! empty( $eeDirContents['data'] ) && is_array( $eeDirContents['data'] ) ) {
						foreach ( $eeDirContents['data'] as $eeThumbFile => $eeFileInfo ) {
							eeSFL_FileSystem( 'move', array(
								'from' => $eeOldThumbDir . $eeThumbFile,
								'to'   => $eeNewThumbDir . $eeThumbFile,
							) );
						}
					}

					// Delete the old hidden directory
					eeSFL_FileSystem( 'delete', array( 'file' => $eeOldThumbDir, 'recursive' => TRUE ) );
					eeSFL_Debug_Log( '.thumbnails → _eeSFL_Thumbnails migration complete', 'Updating' );
					$eeSFL->eeUserMessages['Updating'][] = '- Thumbnail dir migrated from .thumbnails to _eeSFL_Thumbnails';

				} else {
					eeSFL_Debug_Log( 'Failed to create _eeSFL_Thumbnails dir at: ' . $eeNewThumbDir, 'Updating' );
				}

			} else {
				eeSFL_Debug_Log( 'No .thumbnails dir found, skipping', 'Updating' );
			}
		}
	}

	eeSFL_EnsureFileListDirExists(1);

	$eeSFL->eeUserMessages['Updating'][] = '- Plugin database at version ' . eeSFL_Version;
	update_option('eeSFL_Version', eeSFL_Version);

	return TRUE;
}



// Send File via Email AJAX Handler
function simplefilelist_sendfile_job() {

	global $eeSFLE;

	if(!is_object($eeSFLE) || !method_exists($eeSFLE, 'eeSFLE_SendFilesEmail')) {
		echo 'ERROR: Email module not loaded';
		wp_die();
	}

	$eeResult = $eeSFLE->eeSFLE_SendFilesEmail();

	echo esc_html($eeResult);

	wp_die();

}


function simplefilelist_confirm() {

	if( !current_user_can('manage_options') ) { wp_die(); }

	delete_option('eeSFL_Confirm');

	wp_die();

}


function simplefilelist_dismiss() {

	if( !current_user_can('manage_options') ) { wp_die(); }

	delete_option('eeSFL_Dismiss');

	wp_die();

}


function simplefilelist_edit_job() {

	$eeResult = eeSFL_FileEditor();

	echo esc_html($eeResult);

	wp_die();

}


function simplefilelist_upload_job() {

	global $eeSFLU;

	// Add debugging for PHP upload configuration
	if (function_exists('eeSFL_Debug_Log')) {
		$postMaxSize = ini_get('post_max_size');
		$uploadMaxFilesize = ini_get('upload_max_filesize');
		$postContentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;

		eeSFL_Debug_Log("=== UPLOAD JOB STARTED ===", 'Upload');
		eeSFL_Debug_Log("PHP post_max_size: $postMaxSize", 'Upload');
		eeSFL_Debug_Log("PHP upload_max_filesize: $uploadMaxFilesize", 'Upload');
		eeSFL_Debug_Log("Actual POST Content-Length: " . number_format($postContentLength) . " bytes", 'Upload');

		// Convert post_max_size to bytes for comparison
		$postMaxBytes = wp_convert_hr_to_bytes($postMaxSize);
		if ($postContentLength > $postMaxBytes) {
			eeSFL_Debug_Log("WARNING: POST content exceeds PHP post_max_size limit by " . number_format($postContentLength - $postMaxBytes) . " bytes", 'UPLOAD');
			eeSFL_Debug_Log("Consider increasing PHP post_max_size in php.ini or server configuration", 'Upload');
		}
	}

	$eeResult = $eeSFLU->eeSFL_FileUploader();

	if (function_exists('eeSFL_Debug_Log')) {
		$user_id = get_current_user_id();
		$workflow_status = ($eeResult === 'SUCCESS') ? 'completed successfully' : 'failed';

		eeSFL_Debug_Log("Upload result: $eeResult", 'Upload');
		eeSFL_Debug_Log("User workflow: Upload $workflow_status by user $user_id", 'UX');

		if ($eeResult === 'SUCCESS') {
			eeSFL_Debug_Log("Next steps: File will be indexed and displayed in list", 'UX');
		} else {
			eeSFL_Debug_Log("User may need assistance with upload issue: $eeResult", 'UX');
		}

		eeSFL_Debug_Log("=== UPLOAD JOB COMPLETED ===", 'Upload');
	}

	echo esc_html($eeResult);

	wp_die();

}



function eeSFL_RegisterAssets() {

	// Register All CSS
    wp_register_style( 'ee-simple-file-list-css', plugin_dir_url(__DIR__) . 'css/styles.css', '', eeSFL_Version);
	wp_register_style( 'ee-simple-file-list-css-theme-dark', plugins_url('css/styles-theme-dark.css', __DIR__), '', eeSFL_Version );
	wp_register_style( 'ee-simple-file-list-css-theme-light', plugins_url('css/styles-theme-light.css', __DIR__), '', eeSFL_Version );
    wp_register_style( 'ee-simple-file-list-css-flex', plugins_url('css/styles-flex.css', __DIR__), '', eeSFL_Version );
    wp_register_style( 'ee-simple-file-list-css-tiles', plugins_url('css/styles-tiles.css', __DIR__), '', eeSFL_Version );
	wp_register_style( 'ee-simple-file-list-css-table', plugins_url('css/styles-table.css', __DIR__), '', eeSFL_Version );
	wp_register_style( 'ee-simple-file-list-css-upload', plugins_url('css/styles-upload-form.css', __DIR__), '', eeSFL_Version );
	wp_register_style( 'ee-simple-file-list-css-media', plugins_url('css/ee-media-styles.css', __DIR__), '', eeSFL_Version );

	// Register JavaScripts
	wp_register_script( 'ee-simple-file-list-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', array(), eeSFL_Version, false );
	wp_register_script( 'ee-simple-file-list-js-footer', plugin_dir_url(__DIR__) . 'js/ee-footer.js', array(), eeSFL_Version, true );
	wp_register_script( 'ee-simple-file-list-js-edit-file', plugin_dir_url(__DIR__) . 'js/ee-edit-file.js', array(), eeSFL_Version, true );
	wp_register_script( 'ee-simple-file-list-js-uploader', plugin_dir_url(__DIR__) . 'js/ee-uploader.js', array(), eeSFL_Version, true );
	// wp_register_script( 'ee-simple-file-list-js-media', plugin_dir_url(__DIR__) . 'js/ee-media-scripts-footer.js', array(), eeSFL_Version, true );
	wp_register_script( 'ee-simple-file-list-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', array(), eeSFL_Version, true );

}


function eeSFL_ActionPluginLinks( $links ) {

	$eeLinks = array(
		'<a href="' . admin_url( 'admin.php?page=' . eeSFL_PluginSlug ) . '">' . __('Admin List', 'simple-file-list') . '</a>',
		'<a href="' . admin_url( 'admin.php?page=' . eeSFL_PluginSlug . '&tab=settings' ) . '">' . __('Settings', 'simple-file-list') . '</a>'
	);
	return array_merge( $links, $eeLinks );
}


// Load Front-side <head>
function eeSFL_Enqueue() {

	global $eeSFL_VarsForJS;

	$eeDependents = array('jquery'); // Requires jQuery
	wp_enqueue_style('ee-simple-file-list-css');
	wp_enqueue_style('ee-simple-file-list-css-media');
	wp_enqueue_script('ee-simple-file-list-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', $eeDependents, eeSFL_Version, false); // Head
	wp_enqueue_script('ee-simple-file-list-js-foot', plugin_dir_url(__DIR__) . 'js/ee-footer.js', $eeDependents, eeSFL_Version, TRUE); // Footer
	wp_enqueue_script('ee-simple-file-list-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', $eeDependents, eeSFL_Version, TRUE); // Email
	wp_localize_script( 'ee-simple-file-list-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

	// Pass variables
	wp_localize_script( 'ee-simple-file-list-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

}



// Custom Hooks
function eeSFL_UploadCompleted() {
    do_action('eeSFL_UploadCompleted'); // To be fired post-upload
}
function eeSFL_UploadCompletedAdmin() {
    do_action('eeSFL_UploadCompletedAdmin'); // To be fired post-upload
}



// Admin <head>
function eeSFL_AdminHead($eeHook) {

	global $eeSFL, $eeSFL_VarsForJS, $eeSFLA;

	$deps = array('jquery');

	// wp_die($eeHook); // Check the hook
    $eeHooks = array(
    	'toplevel_page_' . eeSFL_PluginSlug,
    	'file-list-pro_page_ee-simple-file-list-access'
    );

    if(in_array($eeHook, $eeHooks)) {

        // Admin Styles
        wp_enqueue_style( 'ee-simple-file-list-css-admin', plugins_url('css/admin.css', __DIR__), '', eeSFL_Version );

        // CSS
        wp_enqueue_style( 'ee-simple-file-list-css', plugins_url('css/styles.css', __DIR__), '', eeSFL_Version );

        // List Style - Table Only
        wp_enqueue_style( 'ee-simple-file-list-css-table', plugins_url('css/styles-table.css', __DIR__), '', eeSFL_Version );

        // Media Player CSS
        wp_enqueue_style( 'ee-simple-file-list-css-media', plugins_url('css/ee-media-styles.css', __DIR__), '', eeSFL_Version );

        // Javascript
        wp_enqueue_script('ee-simple-file-list-js-head', plugin_dir_url(__DIR__) . 'js/ee-head.js', $deps, eeSFL_Version, FALSE);
		wp_enqueue_script('ee-simple-file-list-js-back', plugin_dir_url(__DIR__) . 'js/ee-back.js', $deps, eeSFL_Version, FALSE);
        wp_enqueue_script('ee-simple-file-list-js-foot', plugin_dir_url(__DIR__) . 'js/ee-footer.js', $deps, eeSFL_Version, TRUE);
        wp_enqueue_script('ee-simple-file-list-js-edit-file', plugin_dir_url(__DIR__) . 'js/ee-edit-file.js',$deps, eeSFL_Version, TRUE);
        wp_enqueue_script('ee-simple-file-list-js-uploader', plugin_dir_url(__DIR__) . 'js/ee-uploader.js', $deps, eeSFL_Version, TRUE);
        wp_enqueue_script('ee-simple-file-list-js-media', plugin_dir_url(__DIR__) . 'js/ee-media-scripts-footer.js', $deps, eeSFL_Version, TRUE);
        wp_enqueue_script('ee-simple-file-list-js-email', plugin_dir_url(__DIR__) . 'js/ee-email.js', $deps, eeSFL_Version, TRUE);

		// Pass variables
		wp_localize_script('ee-simple-file-list-js-head', 'eeSFL_JS', array( 'pluginsUrl' => plugins_url() ) );
		wp_localize_script( 'ee-simple-file-list-js-foot', 'eesfl_vars', $eeSFL_VarsForJS );

		// Pass variables for media player in admin
		$eeSFLM_VarsForJS = array(
			'eePlayLabel' => __('Play', 'simple-file-list'),
			'eeBrowserWarning' => __('Browser is Not Compatible', 'simple-file-list'),
			'eeAudioEnabled' => 'YES', // Default to enabled in admin
			'eeAudioHeight' => 20 // Default height
		);

		// Use actual settings if available
		if ($eeSFL && isset($eeSFL->eeListSettings['AudioEnabled'])) {
			$eeSFLM_VarsForJS['eeAudioEnabled'] = $eeSFL->eeListSettings['AudioEnabled'];
			$eeSFLM_VarsForJS['eeAudioHeight'] = $eeSFL->eeListSettings['AudioHeight'];
		}

		wp_localize_script( 'ee-simple-file-list-js-media', 'eeSFLM_Vars', $eeSFLM_VarsForJS );


		if($eeSFLA) {

			$eeSFLA_URL = plugins_url() . '/'. $eeSFLA->eeSFLA_Slug . '/';

			// exit($eeSFLA_URL);

			wp_enqueue_style( 'eesfla-style', plugins_url( $eeSFLA_URL . 'css/style.css', __DIR__ ), '', eeSFLA_Version );
			wp_enqueue_script('eesfla-head-js', plugins_url( $eeSFLA_URL . 'js/eeSFLA_scripts-head.js', __DIR__), $deps, eeSFLA_Version, false );
			wp_enqueue_script('eesfla-footer-js', plugins_url( $eeSFLA_URL . 'js/eeSFLA_scripts-footer.js', __DIR__), $deps, eeSFLA_Version, true );
		}


    }
}



// Admin Pages
function eeSFL_AdminMenu() {

	global $eeSFL, $eeSFLA;

	// Only include when accessing the plugin admin pages
	if( isset($_GET['page']) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page param for admin menu routing, not form submission

		 $eeOutput = '<!-- Simple File List Admin -->';
		eeSFL_Debug_Log("Admin Menu Loading ...", 'Loading');

		include_once($eeSFL->eeEnvironment['pluginDir'] . 'includes/ee-admin-page.php'); // Admin's List Management Page

	}

	// Admin Menu Visibility: anyone who may manage at least one list
	$eeCapability = eeSFL_MenuCapability();

	// Publish capability as a global so companion plugins (e.g. Tools) can use it
	global $eeSFL_Capability;
	$eeSFL_Capability = $eeCapability;

	// The Admin Menu
	$eeAdminPageHook = add_menu_page(
		__('Simple File List', 'simple-file-list'), // Page Title - Defined at the top of this file
		__('File List', 'simple-file-list'), // Menu Title
		$eeCapability, // User status reguired to see the menu
		eeSFL_PluginSlug, // Slug
		'eeSFL_BackEnd', // Function that displays the menu page
		'dashicons-index-card' // Icon used
	);

}


// =============================================================================
// SET ADMIN PAGE TITLE
// =============================================================================

// Set Admin Title
function eeSFL_SetAdminTitle() {

	global $title;
	$current_screen = get_current_screen();

	if ($current_screen && $current_screen->id === 'toplevel_page_' . eeSFL_PluginSlug) {
		$title = __('Simple File List', 'simple-file-list'); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- setting the admin page title is the purpose of this function
	}
}

// =============================================================================
// ADMIN DASHBOARD WIDGET
// =============================================================================

// The capability for managing list 1 (its AdminRole setting).
function eeSFL_GetAdminCapability() {
	return eeSFL_ListAdminCapability(1);
}


// Returns the raw file list array for a given list ID.
// Used by companion plugins (e.g. Tools) to inspect the stored file array.
function eeSFL_GetRawFileList( $list_id = 1 ) {
	$list_id = absint( $list_id );
	if ( $list_id < 1 ) { $list_id = 1; }
	$file_list = get_option( 'eeSFL_FileList_' . $list_id );
	return is_array( $file_list ) ? $file_list : array();
}






?>
