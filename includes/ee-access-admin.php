<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Admin screens for access: grant picker, "File Access" tab, "Groups" tab
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html



/**
 * One-line description of a grant, for tables.
 *
 * @param array $eeGrant
 * @return string Plain text
 */
function eeSFL_GrantSummary($eeGrant) {

	$eeGrant = eeSFL_NormaliseGrant($eeGrant);
	$eeParts = array();

	if( $eeGrant['users'] ) {
		$eeNames = array();
		foreach( $eeGrant['users'] as $eeID ) {
			$eeUser = get_userdata($eeID);
			$eeNames[] = $eeUser ? $eeUser->user_login : '#' . $eeID;
		}
		$eeParts[] = __('Users:', 'simple-file-list') . ' ' . implode(', ', $eeNames);
	}
	if( $eeGrant['roles'] ) { $eeParts[] = __('Roles:', 'simple-file-list') . ' ' . implode(', ', $eeGrant['roles']); }
	if( $eeGrant['groups'] ) { $eeParts[] = __('Groups:', 'simple-file-list') . ' ' . implode(', ', $eeGrant['groups']); }

	return implode(' · ', $eeParts);
}


/**
 * Form fields to choose users, roles and groups. Posted as $eePrefix[users|roles|groups|groups_extra].
 *
 * @param string $eePrefix
 * @param array  $eeGrant
 * @return string HTML
 */
function eeSFL_GrantPicker($eePrefix, $eeGrant) {

	$eeGrant = eeSFL_NormaliseGrant($eeGrant);

	// Users, as logins
	$eeLogins = array();
	foreach( $eeGrant['users'] as $eeID ) {
		$eeUser = get_userdata($eeID);
		if( $eeUser ) { $eeLogins[] = $eeUser->user_login; }
	}

	$eeOutput = '
	<div class="eeSFL_GrantPicker">

	<p><label class="eeBlock" for="' . esc_attr($eePrefix) . '_users"><strong>' . esc_html__('Users', 'simple-file-list') . '</strong></label>
	<textarea class="eeFullWidth" rows="2" name="' . esc_attr($eePrefix) . '[users]" id="' . esc_attr($eePrefix) . '_users">' . esc_textarea(implode(', ', $eeLogins)) . '</textarea></p>
	<div class="eeNote">' . esc_html__('User names or e-mail addresses, separated by commas.', 'simple-file-list') . '</div>

	<p><strong>' . esc_html__('Roles', 'simple-file-list') . '</strong></p><p>';

	foreach( wp_roles()->get_names() as $eeRole => $eeRoleName ) {
		$eeOutput .= '
		<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="' . esc_attr($eePrefix) . '[roles][]" value="' . esc_attr($eeRole) . '"'
			. (in_array($eeRole, $eeGrant['roles'], true) ? ' checked="checked"' : '') . ' /> ' . esc_html(translate_user_role($eeRoleName)) . '</label>';
	}

	$eeOutput .= '</p>

	<p><strong>' . esc_html__('Groups', 'simple-file-list') . '</strong></p>';

	$eeAvailable = eeSFL_AvailableGroups();
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
		$eeOutput .= '<div class="eeNote">' . esc_html__('No directory groups found.', 'simple-file-list')
			. ' <a href="' . esc_url(admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=groups')) . '">' . esc_html__('Set up LDAP', 'simple-file-list') . '</a></div>';
	}

	$eeOutput .= '
	<p><label class="eeBlock" for="' . esc_attr($eePrefix) . '_groups_extra">' . esc_html__('Other groups', 'simple-file-list') . '</label>
	<input type="text" class="eeFullWidth" name="' . esc_attr($eePrefix) . '[groups_extra]" id="' . esc_attr($eePrefix) . '_groups_extra" value="' . esc_attr(implode(', ', $eeExtra)) . '" /></p>
	<div class="eeNote">' . esc_html__('Group names, separated by commas. For groups the directory does not list, or groups supplied by another plugin.', 'simple-file-list') . '</div>

	</div>';

	return $eeOutput;
}


/**
 * Read a grant posted by eeSFL_GrantPicker(). The caller checks the nonce.
 *
 * @param string $eePrefix
 * @return array{0: array, 1: string[]} The grant, and error messages (unknown users)
 */
function eeSFL_GrantFromPost($eePrefix) {

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the caller checks the nonce; every field is sanitized below
	$eePosted = isset($_POST[$eePrefix]) && is_array($_POST[$eePrefix]) ? wp_unslash($_POST[$eePrefix]) : array();

	$eeGrant = eeSFL_EmptyGrant();
	$eeErrors = array();

	foreach( preg_split('/[\s,;]+/', sanitize_textarea_field((string) ($eePosted['users'] ?? ''))) as $eeName ) {
		if( $eeName === '' ) { continue; }
		$eeUser = is_email($eeName) ? get_user_by('email', $eeName) : get_user_by('login', $eeName);
		if( $eeUser ) {
			$eeGrant['users'][] = (int) $eeUser->ID;
		} else {
			/* translators: %s: a user name or e-mail address */
			$eeErrors[] = sprintf(__('Unknown user: %s', 'simple-file-list'), $eeName);
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

	return array(eeSFL_NormaliseGrant($eeGrant), $eeErrors);
}


/**
 * Store a file's grant in the list's file array.
 *
 * @param int    $eeListID
 * @param string $eeFilePath
 * @param array  $eeGrant
 * @return bool FALSE when the file is not in the list
 */
function eeSFL_SaveFileGrant($eeListID, $eeFilePath, $eeGrant) {

	$eeFiles = get_option('eeSFL_FileList_' . (int) $eeListID, array());
	if( !is_array($eeFiles) ) { return FALSE; }

	foreach( $eeFiles as $eeKey => $eeFileArray ) {
		if( is_array($eeFileArray) && ($eeFileArray['FilePath'] ?? null) === $eeFilePath ) {
			$eeFiles[$eeKey]['Access'] = eeSFL_NormaliseGrant($eeGrant);
			// What the old add-on stored is now part of 'Access'
			$eeFiles[$eeKey]['AccessUsers'] = array();
			$eeFiles[$eeKey]['AccessRole'] = '';
			update_option('eeSFL_FileList_' . (int) $eeListID, $eeFiles);
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
function eeSFL_FileAccessPage() {

	global $eeSFL;

	$eeListID = (int) $eeSFL->eeListID;
	if( !current_user_can(eeSFL_ListAdminCapability($eeListID)) ) { return ''; }

	$eeMessages = '';
	$eeTabURL = admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=access&eeListID=' . $eeListID);

	// Save
	if( isset($_POST['eeSFL_AccessFile']) && check_admin_referer('eeSFL_FileAccess_' . $eeListID, 'eeSFL_FileAccessNonce') ) {

		$eeFilePath = (string) wp_unslash($_POST['eeSFL_AccessFile']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared exactly against the stored file list

		if( isset($_POST['eeSFL_AccessClear']) ) {
			$eeGrant = eeSFL_EmptyGrant();
			$eeErrors = array();
		} else {
			list($eeGrant, $eeErrors) = eeSFL_GrantFromPost('eeSFL_FileGrant');
		}

		if( eeSFL_SaveFileGrant($eeListID, $eeFilePath, $eeGrant) ) {
			$eeMessages .= '<div class="notice notice-success"><p>' . esc_html__('Access saved.', 'simple-file-list') . '</p></div>';
		} else {
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html__('This file is not in the list.', 'simple-file-list') . '</p></div>';
		}
		foreach( $eeErrors as $eeError ) {
			$eeMessages .= '<div class="notice notice-warning"><p>' . esc_html($eeError) . '</p></div>';
		}
	}

	$eeFiles = array();
	foreach( (array) get_option('eeSFL_FileList_' . $eeListID, array()) as $eeFileArray ) {
		if( is_array($eeFileArray) && isset($eeFileArray['FilePath']) && ($eeFileArray['FileExt'] ?? '') !== 'folder' ) {
			$eeFiles[$eeFileArray['FilePath']] = $eeFileArray;
		}
	}
	ksort($eeFiles, SORT_NATURAL | SORT_FLAG_CASE);

	$eeOutput = $eeMessages . '<section class="eeSFL_Settings">';

	if( !eeSFL_ListIsProtected($eeSFL->eeListSettings) ) {
		$eeOutput .= '<div class="notice notice-warning inline"><p>'
			. esc_html__('This list does not protect its files: a file with limited access is hidden from the list, but anyone with its direct URL can still open it. Turn on "Protect files" in the list settings.', 'simple-file-list')
			. '</p></div>';
	}

	$eeOutput .= '<p>' . esc_html__('A file without its own access is open to everyone who can see the list. A file with access set is only listed for, and only opens for, the users, roles and groups chosen here. People who manage this list in wp-admin always see every file.', 'simple-file-list') . '</p>';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- selects which file's form to show
	$eeEditPath = isset($_GET['eeFile']) ? (string) wp_unslash($_GET['eeFile']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared exactly against the stored file list

	if( $eeEditPath !== '' && isset($eeFiles[$eeEditPath]) ) {

		$eeOutput .= '
		<form method="post" action="' . esc_url($eeTabURL . '&eeFile=' . rawurlencode($eeEditPath)) . '">
			' . wp_nonce_field('eeSFL_FileAccess_' . $eeListID, 'eeSFL_FileAccessNonce', true, false) . '
			<input type="hidden" name="eeListID" value="' . (int) $eeListID . '" />
			<input type="hidden" name="eeSFL_AccessFile" value="' . esc_attr($eeEditPath) . '" />
			<h2>' . esc_html($eeEditPath) . '</h2>
			' . eeSFL_GrantPicker('eeSFL_FileGrant', eeSFL_FileGrant($eeFiles[$eeEditPath])) . '
			<p>
				<button type="submit" class="button button-primary">' . esc_html__('Save Access', 'simple-file-list') . '</button>
				<button type="submit" name="eeSFL_AccessClear" value="1" class="button">' . esc_html__('Open to Everyone Who Sees the List', 'simple-file-list') . '</button>
				<a class="button-link" href="' . esc_url($eeTabURL) . '">' . esc_html__('Back to all files', 'simple-file-list') . '</a>
			</p>
		</form>';

		return $eeOutput . '</section>';
	}

	$eeOutput .= '
	<table class="widefat striped">
	<thead><tr><th>' . esc_html__('File', 'simple-file-list') . '</th><th>' . esc_html__('Access', 'simple-file-list') . '</th><th></th></tr></thead>
	<tbody>';

	if( !$eeFiles ) {
		$eeOutput .= '<tr><td colspan="3">' . esc_html__('This list has no files.', 'simple-file-list') . '</td></tr>';
	}

	foreach( $eeFiles as $eePath => $eeFileArray ) {
		$eeGrant = eeSFL_FileGrant($eeFileArray);
		$eeOutput .= '
		<tr>
			<td>' . esc_html($eePath) . '</td>
			<td>' . (eeSFL_GrantIsEmpty($eeGrant) ? '<em>' . esc_html__('Everyone who sees the list', 'simple-file-list') . '</em>' : esc_html(eeSFL_GrantSummary($eeGrant))) . '</td>
			<td><a class="button button-small" href="' . esc_url($eeTabURL . '&eeFile=' . rawurlencode($eePath)) . '">' . esc_html__('Change', 'simple-file-list') . '</a></td>
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
function eeSFL_CleanSettingText($eeValue) {
	return trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $eeValue));
}


/**
 * The "Groups" tab: the LDAP directory and how protected files are sent. Site-wide.
 *
 * @return string HTML
 */
function eeSFL_GroupsPage() {

	if( !current_user_can('manage_options') ) { return ''; }

	$eeSettings = eeSFL_LDAPSettings();
	$eeAccess = eeSFL_AccessSettings();
	$eeMessages = '';
	$eeTestOutput = '';

	if( (isset($_POST['eeSFL_SaveGroups']) || isset($_POST['eeSFL_TestGroups'])) && check_admin_referer('eeSFL_Groups', 'eeSFL_GroupsNonce') ) {

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- eeSFL_CleanSettingText() sanitizes
		foreach( array('URI', 'BindDN', 'UserBase', 'UserFilter', 'GroupBase', 'GroupFilter', 'GroupListFilter', 'GroupAttribute', 'AllowedGroups') as $eeField ) {
			$eeSettings[$eeField] = eeSFL_CleanSettingText(wp_unslash($_POST['eeLDAP' . $eeField] ?? ''));
		}
		$eeSettings['Enabled'] = isset($_POST['eeLDAPEnabled']) ? 'YES' : 'NO';
		$eeSettings['StartTLS'] = isset($_POST['eeLDAPStartTLS']) ? 'YES' : 'NO';

		if( !eeSFL_LDAPPasswordInConfig() ) {
			if( isset($_POST['eeLDAPClearPassword']) ) {
				$eeSettings['BindPassword'] = '';
			} elseif( isset($_POST['eeLDAPBindPassword']) && $_POST['eeLDAPBindPassword'] !== '' ) {
				$eeSettings['BindPassword'] = (string) wp_unslash($_POST['eeLDAPBindPassword']); // A password: stored as typed
			}
		}

		$eeAccess['AccelPrefix'] = eeSFL_CleanSettingText(wp_unslash($_POST['eeAccelPrefix'] ?? ''));
		if( $eeAccess['AccelPrefix'] !== '' && !preg_match('#^/[A-Za-z0-9_\-/]+$#', $eeAccess['AccelPrefix']) ) {
			$eeAccess['AccelPrefix'] = '';
			$eeMessages .= '<div class="notice notice-error"><p>' . esc_html__('The internal location must look like /protected-files/. It was cleared.', 'simple-file-list') . '</p></div>';
		}
		// phpcs:enable

		update_option('eeSFL_LDAP', $eeSettings, FALSE);
		update_option('eeSFL_AccessSettings', $eeAccess);

		$eeMessages .= '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'simple-file-list') . '</p></div>';

		if( isset($_POST['eeSFL_TestGroups']) ) {

			$eeDirectory = new eeSFL_LDAP_Directory($eeSettings);
			$eeLines = array();

			$eeAll = $eeDirectory->eeAllGroups();
			if( $eeAll === null ) {
				$eeLines[] = '✗ ' . $eeDirectory->eeError;
			} else {
				$eeOffered = eeSFL_FilterAllowedGroups($eeAll, $eeSettings);
				/* translators: 1: number of groups found, 2: number of groups offered */
				$eeLines[] = '✓ ' . sprintf(__('Connected. %1$d groups found, %2$d offered for access:', 'simple-file-list'), count($eeAll), count($eeOffered)) . ' ' . implode(', ', $eeOffered);

				$eeTestUser = sanitize_text_field(wp_unslash($_POST['eeLDAPTestUser'] ?? ''));
				if( $eeTestUser !== '' ) {
					$eeUser = is_email($eeTestUser) ? get_user_by('email', $eeTestUser) : get_user_by('login', $eeTestUser);
					$eeLogin = $eeUser ? $eeUser->user_login : $eeTestUser;
					$eeEmail = $eeUser ? $eeUser->user_email : $eeTestUser;
					$eeGroups = $eeDirectory->eeUserGroups($eeLogin, $eeEmail);
					$eeLines[] = $eeGroups === null
						? '✗ ' . $eeTestUser . ': ' . $eeDirectory->eeError
						/* translators: %s: user name */
						: '✓ ' . sprintf(__('Groups of %s:', 'simple-file-list'), $eeTestUser) . ' ' . ($eeGroups ? implode(', ', $eeGroups) : __('none', 'simple-file-list'));
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
	<form method="post" action="' . esc_url(admin_url('admin.php?page=' . eeSFL_PluginSlug . '&tab=groups')) . '" autocomplete="off">
	' . wp_nonce_field('eeSFL_Groups', 'eeSFL_GroupsNonce', true, false) . '

	<h2>' . esc_html__('Groups from an LDAP Directory', 'simple-file-list') . '</h2>
	<p>' . esc_html__('Lists and files can be opened to directory groups. A WordPress user is found in the directory by user name or e-mail address; their groups are cached for 15 minutes.', 'simple-file-list') . '</p>';

	if( !function_exists('ldap_connect') ) {
		$eeOutput .= '<div class="notice notice-warning inline"><p>' . esc_html__('The PHP LDAP extension is not installed on this server, so groups cannot be looked up. Users and roles still work.', 'simple-file-list') . '</p></div>';
	}

	$eeOutput .= '
	<fieldset>
	<p><label><input type="checkbox" name="eeLDAPEnabled" value="YES"' . ($eeSettings['Enabled'] === 'YES' ? ' checked="checked"' : '') . ' /> ' . esc_html__('Look up groups in LDAP', 'simple-file-list') . '</label></p>
	</fieldset>'
	. $eeField('URI', __('Server', 'simple-file-list'), __('For example ldaps://ldap.example.org or ldap://openldap:389', 'simple-file-list')) . '
	<fieldset>
	<p><label><input type="checkbox" name="eeLDAPStartTLS" value="YES"' . ($eeSettings['StartTLS'] === 'YES' ? ' checked="checked"' : '') . ' /> ' . esc_html__('Use StartTLS (for ldap://)', 'simple-file-list') . '</label></p>
	</fieldset>'
	. $eeField('BindDN', __('Bind DN', 'simple-file-list'), __('A read-only account. Leave empty for an anonymous bind.', 'simple-file-list'));

	if( eeSFL_LDAPPasswordInConfig() ) {
		$eeOutput .= '
		<fieldset><p><strong>' . esc_html__('Bind Password', 'simple-file-list') . '</strong></p>
		<div class="eeNote">' . esc_html__('Set in wp-config.php (EESFL_LDAP_BIND_PASSWORD).', 'simple-file-list') . '</div></fieldset>';
	} else {
		$eeOutput .= '
		<fieldset>
		<p><label class="eeBlock" for="eeLDAPBindPassword">' . esc_html__('Bind Password', 'simple-file-list') . '</label>
		<input type="password" class="eeFullWidth" name="eeLDAPBindPassword" id="eeLDAPBindPassword" value="" autocomplete="new-password" placeholder="' . esc_attr($eeSettings['BindPassword'] !== '' ? __('(stored, leave empty to keep)', 'simple-file-list') : '') . '" /></p>
		<p><label><input type="checkbox" name="eeLDAPClearPassword" value="1" /> ' . esc_html__('Remove the stored password', 'simple-file-list') . '</label></p>
		<div class="eeNote">' . esc_html__('Stored in the database. To keep it out of the database, define EESFL_LDAP_BIND_PASSWORD in wp-config.php instead.', 'simple-file-list') . '</div>
		</fieldset>';
	}

	$eeOutput .=
	  $eeField('UserBase', __('User Search Base', 'simple-file-list'), __('For example ou=people,dc=example,dc=org', 'simple-file-list'))
	. $eeField('UserFilter', __('User Filter', 'simple-file-list'), __('{login} is the WordPress user name, {email} the e-mail address. Exactly one account must match.', 'simple-file-list'))
	. $eeField('GroupBase', __('Group Search Base', 'simple-file-list'), __('For example ou=groups,dc=example,dc=org', 'simple-file-list'))
	. $eeField('GroupFilter', __('Group Membership Filter', 'simple-file-list'), __('{dn} is the account found above. For posixGroup use (memberUid={login}).', 'simple-file-list'))
	. $eeField('GroupListFilter', __('All Groups Filter', 'simple-file-list'), __('Which entries to offer as groups when granting access.', 'simple-file-list'))
	. $eeField('GroupAttribute', __('Group Name Attribute', 'simple-file-list'))
	. $eeField('AllowedGroups', __('Offer Only These Groups', 'simple-file-list'), __('Comma separated. Leave empty to offer every group found.', 'simple-file-list')) . '

	<fieldset>
	<p><label class="eeBlock" for="eeLDAPTestUser">' . esc_html__('Test with User', 'simple-file-list') . '</label>
	<input type="text" class="eeFullWidth" name="eeLDAPTestUser" id="eeLDAPTestUser" value="" /></p>
	<div class="eeNote">' . esc_html__('Optional: a user name or e-mail address whose groups the test shows.', 'simple-file-list') . '</div>
	</fieldset>

	<h2>' . esc_html__('Protected Downloads', 'simple-file-list') . '</h2>
	<p>' . esc_html__('Files of a list with "Protect files" on are sent by WordPress after an access check. On nginx WordPress can hand the sending back to nginx, which is faster for large files.', 'simple-file-list') . '</p>

	<fieldset>
	<p><label class="eeBlock" for="eeAccelPrefix">' . esc_html__('nginx Internal Location (X-Accel-Redirect)', 'simple-file-list') . '</label>
	<input type="text" class="eeFullWidth" name="eeAccelPrefix" id="eeAccelPrefix" value="' . esc_attr($eeAccess['AccelPrefix']) . '" placeholder="/sfl-protected/" /></p>
	<div class="eeNote">' . esc_html__('Leave empty unless nginx has a matching internal location that points to the WordPress folder, for example:', 'simple-file-list') . '<br />
	<code>location /sfl-protected/ { internal; alias ' . esc_html(rtrim(defined('eeSFL_WP_ROOT') ? eeSFL_WP_ROOT : ABSPATH, '/')) . '/; }</code></div>
	</fieldset>

	<p>
		<button type="submit" name="eeSFL_SaveGroups" value="1" class="button button-primary">' . esc_html__('Save', 'simple-file-list') . '</button>
		<button type="submit" name="eeSFL_TestGroups" value="1" class="button">' . esc_html__('Save and Test', 'simple-file-list') . '</button>
	</p>

	</form>
	</section>';

	return $eeOutput;
}
