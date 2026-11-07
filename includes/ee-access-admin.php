<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// AV-PvH File List - Admin screens for access: grant picker, "File Access" tab, "Groups" tab
// Copyright 2026 grmt, part of AV-PvH File List: https://github.com/grmt/avpvh-filelist
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



/**
 * One-line description of a grant, for tables.
 *
 * @param array $eeGrant
 * @return string Plain text
 */
function avpvh_flst_grant_summary($eeGrant) {

	$eeGrant = avpvh_flst_normalise_grant($eeGrant);
	$eeParts = array();

	if( $eeGrant['users'] ) {
		$eeNames = array();
		foreach( $eeGrant['users'] as $eeID ) {
			$eeUser = get_userdata($eeID);
			$eeNames[] = $eeUser ? $eeUser->user_login : '#' . $eeID;
		}
		$eeParts[] = __('Users:', 'avpvh-filelist') . ' ' . implode(', ', $eeNames);
	}
	if( $eeGrant['roles'] ) { $eeParts[] = __('Roles:', 'avpvh-filelist') . ' ' . implode(', ', $eeGrant['roles']); }
	if( $eeGrant['groups'] ) { $eeParts[] = __('Groups:', 'avpvh-filelist') . ' ' . implode(', ', $eeGrant['groups']); }

	return implode(' · ', $eeParts);
}


/**
 * Form fields to choose users, roles and groups. Posted as $eePrefix[users|roles|groups|groups_extra].
 *
 * @param string $eePrefix
 * @param array  $eeGrant
 * @return string HTML
 */
function avpvh_flst_grant_picker($eePrefix, $eeGrant) {

	$eeGrant = avpvh_flst_normalise_grant($eeGrant);

	// Users, as logins
	$eeLogins = array();
	foreach( $eeGrant['users'] as $eeID ) {
		$eeUser = get_userdata($eeID);
		if( $eeUser ) { $eeLogins[] = $eeUser->user_login; }
	}

	$eeOutput = '
	<div class="eeSFL_GrantPicker">

	<p><label class="eeBlock" for="' . esc_attr($eePrefix) . '_users"><strong>' . esc_html__('Users', 'avpvh-filelist') . '</strong></label>
	<textarea class="eeFullWidth" rows="2" name="' . esc_attr($eePrefix) . '[users]" id="' . esc_attr($eePrefix) . '_users">' . esc_textarea(implode(', ', $eeLogins)) . '</textarea></p>
	<div class="eeNote">' . esc_html__('User names or e-mail addresses, separated by commas.', 'avpvh-filelist') . '</div>

	<p><strong>' . esc_html__('Roles', 'avpvh-filelist') . '</strong></p><p>';

	foreach( wp_roles()->get_names() as $eeRole => $eeRoleName ) {
		$eeOutput .= '
		<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="' . esc_attr($eePrefix) . '[roles][]" value="' . esc_attr($eeRole) . '"'
			. (in_array($eeRole, $eeGrant['roles'], true) ? ' checked="checked"' : '') . ' /> ' . esc_html(translate_user_role($eeRoleName)) . '</label>';
	}

	$eeOutput .= '</p>

	<p><strong>' . esc_html__('Groups', 'avpvh-filelist') . '</strong></p>';

	$eeAvailable = avpvh_flst_available_groups();
	$eeExtra = array_diff($eeGrant['groups'], $eeAvailable); // Granted, but not (or no longer) offered

	if( $eeAvailable ) {
		$eeOutput .= '<p>';
		foreach( $eeAvailable as $eeGroup ) {
			$eeOutput .= '
			<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="' . esc_attr($eePrefix) . '[groups][]" value="' . esc_attr($eeGroup) . '"'
				. (in_array($eeGroup, $eeGrant['groups'], true) ? ' checked="checked"' : '') . ' /> ' . esc_html($eeGroup) . '</label>';
		}
		$eeOutput .= '</p>';
	} elseif( current_user_can('manage_options') ) {
		$eeOutput .= '<div class="eeNote">' . esc_html__('No directory groups found.', 'avpvh-filelist')
			. ' <a href="' . esc_url(admin_url('admin.php?page=' . AVPVH_FLST_SLUG . '&tab=groups')) . '">' . esc_html__('Set up LDAP', 'avpvh-filelist') . '</a></div>';
	}

	$eeOutput .= '
	<p><label class="eeBlock" for="' . esc_attr($eePrefix) . '_groups_extra">' . esc_html__('Other groups', 'avpvh-filelist') . '</label>
	<input type="text" class="eeFullWidth" name="' . esc_attr($eePrefix) . '[groups_extra]" id="' . esc_attr($eePrefix) . '_groups_extra" value="' . esc_attr(implode(', ', $eeExtra)) . '" /></p>
	<div class="eeNote">' . esc_html__('Group names, separated by commas. For groups the directory does not list, or groups supplied by another plugin.', 'avpvh-filelist') . '</div>

	</div>';

	return $eeOutput;
}


/**
 * Read a grant posted by avpvh_flst_grant_picker(). The caller checks the nonce.
 *
 * @param string $eePrefix
 * @return array{0: array, 1: string[]} The grant, and error messages (unknown users)
 */
function avpvh_flst_grant_from_post($eePrefix) {

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the caller checks the nonce; every field is sanitized below
	$eePosted = isset($_POST[$eePrefix]) && is_array($_POST[$eePrefix]) ? wp_unslash($_POST[$eePrefix]) : array();

	$eeGrant = avpvh_flst_empty_grant();
	$eeErrors = array();

	foreach( preg_split('/[\s,;]+/', sanitize_textarea_field((string) ($eePosted['users'] ?? ''))) as $eeName ) {
		if( $eeName === '' ) { continue; }
		$eeUser = is_email($eeName) ? get_user_by('email', $eeName) : get_user_by('login', $eeName);
		if( $eeUser ) {
			$eeGrant['users'][] = (int) $eeUser->ID;
		} else {
			/* translators: %s: a user name or e-mail address */
			$eeErrors[] = sprintf(__('Unknown user: %s', 'avpvh-filelist'), $eeName);
		}
	}

	$eeRoles = array_keys(wp_roles()->get_names());
	foreach( (array) ($eePosted['roles'] ?? array()) as $eeRole ) {
		$eeRole = sanitize_key($eeRole);
		if( in_array($eeRole, $eeRoles, true) ) { $eeGrant['roles'][] = $eeRole; }
	}

	foreach( (array) ($eePosted['groups'] ?? array()) as $eeGroup ) {
		$eeGrant['groups'][] = sanitize_text_field($eeGroup);
	}
	foreach( explode(',', sanitize_text_field((string) ($eePosted['groups_extra'] ?? ''))) as $eeGroup ) {
		$eeGrant['groups'][] = $eeGroup;
	}

	return array(avpvh_flst_normalise_grant($eeGrant), $eeErrors);
}


/**
 * Store a file's grant in the list's file array.
 *
 * @param int    $eeListID
 * @param string $eeFilePath
 * @param array  $eeGrant
 * @return bool FALSE when the file is not in the list
 */
function avpvh_flst_save_file_grant($eeListID, $eeFilePath, $eeGrant) {

	$eeFiles = get_option('avpvh_flst_files_' . (int) $eeListID, array());
	if( !is_array($eeFiles) ) { return FALSE; }

	foreach( $eeFiles as $eeKey => $eeFileArray ) {
		if( is_array($eeFileArray) && ($eeFileArray['FilePath'] ?? null) === $eeFilePath ) {
			$eeFiles[$eeKey]['Access'] = avpvh_flst_normalise_grant($eeGrant);
			// What the old add-on stored is now part of 'Access'
			$eeFiles[$eeKey]['AccessUsers'] = array();
			$eeFiles[$eeKey]['AccessRole'] = '';
			update_option('avpvh_flst_files_' . (int) $eeListID, $eeFiles);
			return TRUE;
		}
	}

	return FALSE;
}


/**
 * The "File Access" tab: who may open each file of the current list.
 *
 * @return string HTML
 */
function avpvh_flst_file_access_page() {

	global $eeSFL;

	$eeListID = (int) $eeSFL->eeListID;
	if( !current_user_can(avpvh_flst_list_admin_capability($eeListID)) ) { return ''; }

	$eeMessages = '';
	$eeTabURL = admin_url('admin.php?page=' . AVPVH_FLST_SLUG . '&tab=access&eeListID=' . $eeListID);

	// Save
	if( isset($_POST['eeSFL_AccessFile']) && check_admin_referer('eeSFL_FileAccess_' . $eeListID, 'eeSFL_FileAccessNonce') ) {

		$eeFilePath = (string) wp_unslash($_POST['eeSFL_AccessFile']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared exactly against the stored file list

		if( isset($_POST['eeSFL_AccessClear']) ) {
			$eeGrant = avpvh_flst_empty_grant();
			$eeErrors = array();
		} else {
			list($eeGrant, $eeErrors) = avpvh_flst_grant_from_post('eeSFL_FileGrant');
		}

		if( avpvh_flst_save_file_grant($eeListID, $eeFilePath, $eeGrant) ) {
			$eeMessages .= '<div class="notice notice-success"><p>' . esc_html__('Access saved.', 'avpvh-filelist') . '</p></div>';
		} else {
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html__('This file is not in the list.', 'avpvh-filelist') . '</p></div>';
		}
		foreach( $eeErrors as $eeError ) {
			$eeMessages .= '<div class="notice notice-warning"><p>' . esc_html($eeError) . '</p></div>';
		}
	}

	$eeFiles = array();
	foreach( (array) get_option('avpvh_flst_files_' . $eeListID, array()) as $eeFileArray ) {
		if( is_array($eeFileArray) && isset($eeFileArray['FilePath']) && ($eeFileArray['FileExt'] ?? '') !== 'folder' ) {
			$eeFiles[$eeFileArray['FilePath']] = $eeFileArray;
		}
	}
	ksort($eeFiles, SORT_NATURAL | SORT_FLAG_CASE);

	$eeOutput = $eeMessages . '<section class="eeSFL_Settings">';

	if( !avpvh_flst_list_is_protected($eeSFL->eeListSettings) ) {
		$eeOutput .= '<div class="notice notice-warning inline"><p>'
			. esc_html__('This list does not protect its files: a file with limited access is hidden from the list, but anyone with its direct URL can still open it. Turn on "Protect files" in the list settings.', 'avpvh-filelist')
			. '</p></div>';
	}

	$eeOutput .= '<p>' . esc_html__('A file without its own access is open to everyone who can see the list. A file with access set is only listed for, and only opens for, the users, roles and groups chosen here. People who manage this list in wp-admin always see every file.', 'avpvh-filelist') . '</p>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selects which file's form to show
	$eeEditPath = isset($_GET['eeFile']) ? (string) wp_unslash($_GET['eeFile']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared exactly against the stored file list

	if( $eeEditPath !== '' && isset($eeFiles[$eeEditPath]) ) {

		$eeOutput .= '
		<form method="post" action="' . esc_url($eeTabURL . '&eeFile=' . rawurlencode($eeEditPath)) . '">
			' . wp_nonce_field('eeSFL_FileAccess_' . $eeListID, 'eeSFL_FileAccessNonce', true, false) . '
			<input type="hidden" name="eeListID" value="' . (int) $eeListID . '" />
			<input type="hidden" name="eeSFL_AccessFile" value="' . esc_attr($eeEditPath) . '" />
			<h2>' . esc_html($eeEditPath) . '</h2>
			' . avpvh_flst_grant_picker('eeSFL_FileGrant', avpvh_flst_file_grant($eeFiles[$eeEditPath])) . '
			<p>
				<button type="submit" class="button button-primary">' . esc_html__('Save Access', 'avpvh-filelist') . '</button>
				<button type="submit" name="eeSFL_AccessClear" value="1" class="button">' . esc_html__('Open to Everyone Who Sees the List', 'avpvh-filelist') . '</button>
				<a class="button-link" href="' . esc_url($eeTabURL) . '">' . esc_html__('Back to all files', 'avpvh-filelist') . '</a>
			</p>
		</form>';

		return $eeOutput . '</section>';
	}

	$eeOutput .= '
	<table class="widefat striped">
	<thead><tr><th>' . esc_html__('File', 'avpvh-filelist') . '</th><th>' . esc_html__('Access', 'avpvh-filelist') . '</th><th></th></tr></thead>
	<tbody>';

	if( !$eeFiles ) {
		$eeOutput .= '<tr><td colspan="3">' . esc_html__('This list has no files.', 'avpvh-filelist') . '</td></tr>';
	}

	foreach( $eeFiles as $eePath => $eeFileArray ) {
		$eeGrant = avpvh_flst_file_grant($eeFileArray);
		$eeOutput .= '
		<tr>
			<td>' . esc_html($eePath) . '</td>
			<td>' . (avpvh_flst_grant_is_empty($eeGrant) ? '<em>' . esc_html__('Everyone who sees the list', 'avpvh-filelist') . '</em>' : esc_html(avpvh_flst_grant_summary($eeGrant))) . '</td>
			<td><a class="button button-small" href="' . esc_url($eeTabURL . '&eeFile=' . rawurlencode($eePath)) . '">' . esc_html__('Change', 'avpvh-filelist') . '</a></td>
		</tr>';
	}

	return $eeOutput . '
	</tbody>
	</table>
	</section>';
}


/**
 * Strip control characters, keep everything else (LDAP filters contain <, >, = and &).
 *
 * @param mixed $eeValue
 * @return string
 */
function avpvh_flst_clean_setting_text($eeValue) {
	return trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $eeValue));
}


/**
 * The "Groups" tab: the LDAP directory and how protected files are sent. Site-wide.
 *
 * @return string HTML
 */
function avpvh_flst_groups_page() {

	if( !current_user_can('manage_options') ) { return ''; }

	$eeSettings = avpvh_flst_ldap_settings();
	$eeAccess = avpvh_flst_access_settings();
	$eeMessages = '';
	$eeTestOutput = '';

	if( (isset($_POST['eeSFL_SaveGroups']) || isset($_POST['eeSFL_TestGroups'])) && check_admin_referer('eeSFL_Groups', 'eeSFL_GroupsNonce') ) {

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- avpvh_flst_clean_setting_text() sanitizes
		foreach( array('URI', 'BindDN', 'UserBase', 'UserFilter', 'GroupBase', 'GroupFilter', 'GroupListFilter', 'GroupAttribute', 'AllowedGroups') as $eeField ) {
			$eeSettings[$eeField] = avpvh_flst_clean_setting_text(wp_unslash($_POST['eeLDAP' . $eeField] ?? ''));
		}
		$eeSettings['Enabled'] = isset($_POST['eeLDAPEnabled']) ? 'YES' : 'NO';
		$eeSettings['StartTLS'] = isset($_POST['eeLDAPStartTLS']) ? 'YES' : 'NO';

		if( !avpvh_flst_ldap_password_in_config() ) {
			if( isset($_POST['eeLDAPClearPassword']) ) {
				$eeSettings['BindPassword'] = '';
			} elseif( isset($_POST['eeLDAPBindPassword']) && $_POST['eeLDAPBindPassword'] !== '' ) {
				$eeSettings['BindPassword'] = (string) wp_unslash($_POST['eeLDAPBindPassword']); // A password: stored as typed
			}
		}

		$eeAccess['AccelPrefix'] = avpvh_flst_clean_setting_text(wp_unslash($_POST['eeAccelPrefix'] ?? ''));
		if( $eeAccess['AccelPrefix'] !== '' && !preg_match('#^/[A-Za-z0-9_\-/]+$#', $eeAccess['AccelPrefix']) ) {
			$eeAccess['AccelPrefix'] = '';
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html__('The internal location must look like /protected-files/. It was cleared.', 'avpvh-filelist') . '</p></div>';
		}
		// phpcs:enable

		update_option('avpvh_flst_ldap', $eeSettings, FALSE);
		update_option('avpvh_flst_access_settings', $eeAccess);

		$eeMessages .= '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'avpvh-filelist') . '</p></div>';

		if( isset($_POST['eeSFL_TestGroups']) ) {

			$eeDirectory = new Avpvh_Flst_Ldap_Directory($eeSettings);
			$eeLines = array();

			$eeAll = $eeDirectory->eeAllGroups();
			if( $eeAll === null ) {
				$eeLines[] = '✗ ' . $eeDirectory->eeError;
			} else {
				$eeOffered = avpvh_flst_filter_allowed_groups($eeAll, $eeSettings);
				/* translators: 1: number of groups found, 2: number of groups offered */
				$eeLines[] = '✓ ' . sprintf(__('Connected. %1$d groups found, %2$d offered for access:', 'avpvh-filelist'), count($eeAll), count($eeOffered)) . ' ' . implode(', ', $eeOffered);

				$eeTestUser = sanitize_text_field(wp_unslash($_POST['eeLDAPTestUser'] ?? ''));
				if( $eeTestUser !== '' ) {
					$eeUser = is_email($eeTestUser) ? get_user_by('email', $eeTestUser) : get_user_by('login', $eeTestUser);
					$eeLogin = $eeUser ? $eeUser->user_login : $eeTestUser;
					$eeEmail = $eeUser ? $eeUser->user_email : $eeTestUser;
					$eeGroups = $eeDirectory->eeUserGroups($eeLogin, $eeEmail);
					$eeLines[] = $eeGroups === null
						? '✗ ' . $eeTestUser . ': ' . $eeDirectory->eeError
						/* translators: %s: user name */
						: '✓ ' . sprintf(__('Groups of %s:', 'avpvh-filelist'), $eeTestUser) . ' ' . ($eeGroups ? implode(', ', $eeGroups) : __('none', 'avpvh-filelist'));
				}
			}

			$eeTestOutput = '<div class="notice notice-info"><p>' . implode('<br />', array_map('esc_html', $eeLines)) . '</p></div>';
		}
	}

	$eeField = function($eeName, $eeLabel, $eeNote = '', $eeType = 'text') use ($eeSettings) {
		return '
		<fieldset>
		<p><label class="eeBlock" for="eeLDAP' . esc_attr($eeName) . '">' . esc_html($eeLabel) . '</label>
		<input type="' . esc_attr($eeType) . '" class="eeFullWidth" name="eeLDAP' . esc_attr($eeName) . '" id="eeLDAP' . esc_attr($eeName) . '" value="' . esc_attr($eeSettings[$eeName]) . '" /></p>'
		. ($eeNote ? '<div class="eeNote">' . esc_html($eeNote) . '</div>' : '') . '
		</fieldset>';
	};

	$eeOutput = $eeMessages . $eeTestOutput . '
	<section class="eeSFL_Settings">
	<form method="post" action="' . esc_url(admin_url('admin.php?page=' . AVPVH_FLST_SLUG . '&tab=groups')) . '" autocomplete="off">
	' . wp_nonce_field('eeSFL_Groups', 'eeSFL_GroupsNonce', true, false) . '

	<h2>' . esc_html__('Groups from an LDAP Directory', 'avpvh-filelist') . '</h2>
	<p>' . esc_html__('Lists and files can be opened to directory groups. A WordPress user is found in the directory by user name or e-mail address; their groups are cached for 15 minutes.', 'avpvh-filelist') . '</p>';

	if( !function_exists('ldap_connect') ) {
		$eeOutput .= '<div class="notice notice-warning inline"><p>' . esc_html__('The PHP LDAP extension is not installed on this server, so groups cannot be looked up. Users and roles still work.', 'avpvh-filelist') . '</p></div>';
	}

	$eeOutput .= '
	<fieldset>
	<p><label><input type="checkbox" name="eeLDAPEnabled" value="YES"' . ($eeSettings['Enabled'] === 'YES' ? ' checked="checked"' : '') . ' /> ' . esc_html__('Look up groups in LDAP', 'avpvh-filelist') . '</label></p>
	</fieldset>'
	. $eeField('URI', __('Server', 'avpvh-filelist'), __('For example ldaps://ldap.example.org or ldap://openldap:389', 'avpvh-filelist')) . '
	<fieldset>
	<p><label><input type="checkbox" name="eeLDAPStartTLS" value="YES"' . ($eeSettings['StartTLS'] === 'YES' ? ' checked="checked"' : '') . ' /> ' . esc_html__('Use StartTLS (for ldap://)', 'avpvh-filelist') . '</label></p>
	</fieldset>'
	. $eeField('BindDN', __('Bind DN', 'avpvh-filelist'), __('A read-only account. Leave empty for an anonymous bind.', 'avpvh-filelist'));

	if( avpvh_flst_ldap_password_in_config() ) {
		$eeOutput .= '
		<fieldset><p><strong>' . esc_html__('Bind Password', 'avpvh-filelist') . '</strong></p>
		<div class="eeNote">' . esc_html__('Set in wp-config.php (AVPVH_FLST_LDAP_BIND_PASSWORD).', 'avpvh-filelist') . '</div></fieldset>';
	} else {
		$eeOutput .= '
		<fieldset>
		<p><label class="eeBlock" for="eeLDAPBindPassword">' . esc_html__('Bind Password', 'avpvh-filelist') . '</label>
		<input type="password" class="eeFullWidth" name="eeLDAPBindPassword" id="eeLDAPBindPassword" value="" autocomplete="new-password" placeholder="' . esc_attr($eeSettings['BindPassword'] !== '' ? __('(stored, leave empty to keep)', 'avpvh-filelist') : '') . '" /></p>
		<p><label><input type="checkbox" name="eeLDAPClearPassword" value="1" /> ' . esc_html__('Remove the stored password', 'avpvh-filelist') . '</label></p>
		<div class="eeNote">' . esc_html__('Stored in the database. To keep it out of the database, define AVPVH_FLST_LDAP_BIND_PASSWORD in wp-config.php instead.', 'avpvh-filelist') . '</div>
		</fieldset>';
	}

	$eeOutput .=
	  $eeField('UserBase', __('User Search Base', 'avpvh-filelist'), __('For example ou=people,dc=example,dc=org', 'avpvh-filelist'))
	. $eeField('UserFilter', __('User Filter', 'avpvh-filelist'), __('{login} is the WordPress user name, {email} the e-mail address. Exactly one account must match.', 'avpvh-filelist'))
	. $eeField('GroupBase', __('Group Search Base', 'avpvh-filelist'), __('For example ou=groups,dc=example,dc=org', 'avpvh-filelist'))
	. $eeField('GroupFilter', __('Group Membership Filter', 'avpvh-filelist'), __('{dn} is the account found above. For posixGroup use (memberUid={login}).', 'avpvh-filelist'))
	. $eeField('GroupListFilter', __('All Groups Filter', 'avpvh-filelist'), __('Which entries to offer as groups when granting access.', 'avpvh-filelist'))
	. $eeField('GroupAttribute', __('Group Name Attribute', 'avpvh-filelist'))
	. $eeField('AllowedGroups', __('Offer Only These Groups', 'avpvh-filelist'), __('Comma separated. Leave empty to offer every group found.', 'avpvh-filelist')) . '

	<fieldset>
	<p><label class="eeBlock" for="eeLDAPTestUser">' . esc_html__('Test with User', 'avpvh-filelist') . '</label>
	<input type="text" class="eeFullWidth" name="eeLDAPTestUser" id="eeLDAPTestUser" value="" /></p>
	<div class="eeNote">' . esc_html__('Optional: a user name or e-mail address whose groups the test shows.', 'avpvh-filelist') . '</div>
	</fieldset>

	<h2>' . esc_html__('Protected Downloads', 'avpvh-filelist') . '</h2>
	<p>' . esc_html__('Files of a list with "Protect files" on are sent by WordPress after an access check. On nginx WordPress can hand the sending back to nginx, which is faster for large files.', 'avpvh-filelist') . '</p>

	<fieldset>
	<p><label class="eeBlock" for="eeAccelPrefix">' . esc_html__('nginx Internal Location (X-Accel-Redirect)', 'avpvh-filelist') . '</label>
	<input type="text" class="eeFullWidth" name="eeAccelPrefix" id="eeAccelPrefix" value="' . esc_attr($eeAccess['AccelPrefix']) . '" placeholder="/sfl-protected/" /></p>
	<div class="eeNote">' . esc_html__('Leave empty unless nginx has a matching internal location that points to the WordPress folder, for example:', 'avpvh-filelist') . '<br />
	<code>location /sfl-protected/ { internal; alias ' . esc_html(rtrim(defined('AVPVH_FLST_WP_ROOT') ? AVPVH_FLST_WP_ROOT : ABSPATH, '/')) . '/; }</code></div>
	</fieldset>

	<p>
		<button type="submit" name="eeSFL_SaveGroups" value="1" class="button button-primary">' . esc_html__('Save', 'avpvh-filelist') . '</button>
		<button type="submit" name="eeSFL_TestGroups" value="1" class="button">' . esc_html__('Save and Test', 'avpvh-filelist') . '</button>
	</p>

	</form>
	</section>';

	return $eeOutput;
}
