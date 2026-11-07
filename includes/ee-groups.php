<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Group membership from an LDAP directory
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html
//
// Access can be granted to directory groups. The groups of a WordPress user are looked up in
// LDAP by login or e-mail address and cached for 15 minutes. Without LDAP, or when the lookup
// fails, a user is in no groups: group grants then simply don't match (fail closed).
//
// Other code may supply or adjust groups through the filters
//   simple_file_list_user_groups      (string[] $groups, int $user_id)
//   simple_file_list_available_groups (string[] $groups)
//
// The bind password can be kept out of the database by defining it in wp-config.php:
//   define( 'EESFL_LDAP_BIND_PASSWORD', '...' );



/**
 * @return array<string, string>
 */
function eeSFL_LDAPDefaults() {
	return array(
		'Enabled'         => 'NO',
		'URI'             => '', // ldap://host:389 or ldaps://host:636
		'StartTLS'        => 'NO',
		'BindDN'          => '', // Empty: anonymous bind
		'BindPassword'    => '',
		'UserBase'        => '',
		'UserFilter'      => '(|(uid={login})(mail={email}))',
		'GroupBase'       => '',
		'GroupFilter'     => '(&(objectClass=groupOfNames)(member={dn}))',
		'GroupListFilter' => '(objectClass=groupOfNames)',
		'GroupAttribute'  => 'cn',
		'AllowedGroups'   => '', // Comma separated; empty allows every group found
	);
}


/**
 * @return array<string, string>
 */
function eeSFL_LDAPSettings() {
	$eeStored = get_option('eeSFL_LDAP', array());
	return array_merge(eeSFL_LDAPDefaults(), is_array($eeStored) ? $eeStored : array());
}


/**
 * Is the bind password defined in wp-config.php?
 *
 * @return bool
 */
function eeSFL_LDAPPasswordInConfig() {
	return defined('EESFL_LDAP_BIND_PASSWORD');
}


/**
 * @param array $eeSettings
 * @return string
 */
function eeSFL_LDAPBindPassword($eeSettings) {
	return eeSFL_LDAPPasswordInConfig() ? (string) constant('EESFL_LDAP_BIND_PASSWORD') : (string) ($eeSettings['BindPassword'] ?? '');
}


/**
 * Is group lookup switched on and usable?
 *
 * @param array|null $eeSettings
 * @return bool
 */
function eeSFL_LDAPEnabled($eeSettings = null) {
	$eeSettings = $eeSettings ?? eeSFL_LDAPSettings();
	return ($eeSettings['Enabled'] ?? 'NO') === 'YES' && ($eeSettings['URI'] ?? '') !== '' && function_exists('ldap_connect');
}


/**
 * Escape a value for use inside an LDAP search filter (RFC 4515).
 *
 * @param string $eeValue
 * @return string
 */
function eeSFL_LDAPEscape($eeValue) {
	return strtr((string) $eeValue, array(
		'\\' => '\\5c',
		'*'  => '\\2a',
		'('  => '\\28',
		')'  => '\\29',
		"\0" => '\\00',
	));
}


/**
 * Fill {placeholders} in a filter template with escaped values.
 *
 * @param string               $eeTemplate e.g. (|(uid={login})(mail={email}))
 * @param array<string,string> $eeValues   e.g. ['login' => 'jan', 'email' => 'jan@example.org']
 * @return string
 */
function eeSFL_LDAPFilter($eeTemplate, $eeValues) {
	$eeReplace = array();
	foreach( $eeValues as $eeKey => $eeValue ) {
		$eeReplace['{' . $eeKey . '}'] = eeSFL_LDAPEscape($eeValue);
	}
	return strtr((string) $eeTemplate, $eeReplace);
}


/**
 * Group names, trimmed, lower case (LDAP names are case-insensitive), unique and sorted.
 *
 * @param mixed $eeNames
 * @return string[]
 */
function eeSFL_NormaliseGroupNames($eeNames) {

	$eeOut = array();

	foreach( (array) $eeNames as $eeName ) {
		if( !is_scalar($eeName) ) { continue; }
		$eeName = strtolower(trim((string) $eeName));
		if( $eeName !== '' ) { $eeOut[$eeName] = $eeName; }
	}

	$eeOut = array_values($eeOut);
	sort($eeOut);

	return $eeOut;
}


/**
 * The AllowedGroups setting as a list. Empty means: no restriction.
 *
 * @param array $eeSettings
 * @return string[]
 */
function eeSFL_LDAPAllowedGroups($eeSettings) {
	return eeSFL_NormaliseGroupNames(preg_split('/[\s,]+/', (string) ($eeSettings['AllowedGroups'] ?? '')));
}


/**
 * Keep only the allowed groups, when the setting restricts them.
 *
 * @param string[] $eeGroups
 * @param array    $eeSettings
 * @return string[]
 */
function eeSFL_FilterAllowedGroups($eeGroups, $eeSettings) {

	$eeGroups = eeSFL_NormaliseGroupNames($eeGroups);
	$eeAllowed = eeSFL_LDAPAllowedGroups($eeSettings);

	return $eeAllowed ? array_values(array_intersect($eeGroups, $eeAllowed)) : $eeGroups;
}


/**
 * Values of one attribute from an ldap_get_entries() result.
 *
 * @param array  $eeEntries
 * @param string $eeAttribute
 * @return string[]
 */
function eeSFL_LDAPEntryValues($eeEntries, $eeAttribute) {

	$eeAttribute = strtolower($eeAttribute); // ldap_get_entries() lower-cases attribute names
	$eeValues = array();

	$eeCount = (int) ($eeEntries['count'] ?? 0);
	for( $i = 0; $i < $eeCount; $i++ ) {
		$eeEntry = $eeEntries[$i] ?? array();
		$eeAttr = $eeEntry[$eeAttribute] ?? array();
		$eeAttrCount = (int) ($eeAttr['count'] ?? 0);
		for( $j = 0; $j < $eeAttrCount; $j++ ) {
			if( isset($eeAttr[$j]) ) { $eeValues[] = (string) $eeAttr[$j]; }
		}
	}

	return $eeValues;
}



/**
 * A connection to the configured directory. Every method fails closed: on any error it returns
 * null (or false) and keeps the reason in $eeError.
 */
class eeSFL_LDAP_Directory {

	/** @var array */
	private $eeSettings;

	/** @var resource|\LDAP\Connection|false|null */
	private $eeLink = null;

	/** @var string */
	public $eeError = '';

	/**
	 * @param array $eeSettings
	 */
	public function __construct($eeSettings) {
		$this->eeSettings = $eeSettings;
	}

	/**
	 * Connect and bind.
	 *
	 * @return bool
	 */
	public function eeConnect() {

		if( $this->eeLink ) { return TRUE; }

		if( !function_exists('ldap_connect') ) {
			$this->eeError = __('The PHP LDAP extension is not installed on this server.', 'simple-file-list');
			return FALSE;
		}

		$eeURI = trim((string) $this->eeSettings['URI']);
		if( !preg_match('#^ldaps?://#i', $eeURI) ) {
			$this->eeError = __('The server address must start with ldap:// or ldaps://', 'simple-file-list');
			return FALSE;
		}

		$eeLink = ldap_connect($eeURI);
		if( !$eeLink ) {
			$this->eeError = __('The server address is not valid.', 'simple-file-list');
			return FALSE;
		}

		ldap_set_option($eeLink, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($eeLink, LDAP_OPT_REFERRALS, 0);
		ldap_set_option($eeLink, LDAP_OPT_NETWORK_TIMEOUT, 5);
		if( defined('LDAP_OPT_TIMELIMIT') ) { ldap_set_option($eeLink, LDAP_OPT_TIMELIMIT, 10); }

		if( ($this->eeSettings['StartTLS'] ?? 'NO') === 'YES' && !@ldap_start_tls($eeLink) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the error is reported below
			$this->eeError = __('StartTLS failed:', 'simple-file-list') . ' ' . ldap_error($eeLink);
			return FALSE;
		}

		$eeBindDN = trim((string) $this->eeSettings['BindDN']);
		$eeBound = $eeBindDN === ''
			? @ldap_bind($eeLink) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the error is reported below
			: @ldap_bind($eeLink, $eeBindDN, eeSFL_LDAPBindPassword($this->eeSettings)); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if( !$eeBound ) {
			$this->eeError = __('Could not log in to the directory:', 'simple-file-list') . ' ' . ldap_error($eeLink);
			return FALSE;
		}

		$this->eeLink = $eeLink;
		return TRUE;
	}

	/**
	 * @param string   $eeBase
	 * @param string   $eeFilter
	 * @param string[] $eeAttributes
	 * @return array|null ldap_get_entries() result
	 */
	private function eeSearch($eeBase, $eeFilter, $eeAttributes) {

		if( !$this->eeConnect() ) { return null; }

		$eeResult = @ldap_search($this->eeLink, $eeBase, $eeFilter, $eeAttributes); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the error is reported below
		if( !$eeResult ) {
			$this->eeError = __('Search failed:', 'simple-file-list') . ' ' . ldap_error($this->eeLink);
			return null;
		}

		$eeEntries = ldap_get_entries($this->eeLink, $eeResult);
		return is_array($eeEntries) ? $eeEntries : null;
	}

	/**
	 * The DN of the account with this login or e-mail address. Exactly one match is required.
	 *
	 * @param string $eeLogin
	 * @param string $eeEmail
	 * @return string|null
	 */
	public function eeUserDN($eeLogin, $eeEmail) {

		$eeFilter = eeSFL_LDAPFilter($this->eeSettings['UserFilter'], array('login' => $eeLogin, 'email' => $eeEmail));
		$eeEntries = $this->eeSearch($this->eeSettings['UserBase'], $eeFilter, array('dn'));
		if( $eeEntries === null ) { return null; }

		if( (int) ($eeEntries['count'] ?? 0) !== 1 ) {
			$this->eeError = sprintf(
				/* translators: %d: number of directory accounts found */
				__('Expected one matching account, found %d.', 'simple-file-list'),
				(int) ($eeEntries['count'] ?? 0)
			);
			return null;
		}

		return (string) $eeEntries[0]['dn'];
	}

	/**
	 * Group names of a WordPress user.
	 *
	 * @param string $eeLogin
	 * @param string $eeEmail
	 * @return string[]|null
	 */
	public function eeUserGroups($eeLogin, $eeEmail) {

		$eeDN = $this->eeUserDN($eeLogin, $eeEmail);
		if( $eeDN === null ) { return null; }

		$eeFilter = eeSFL_LDAPFilter($this->eeSettings['GroupFilter'], array('dn' => $eeDN, 'login' => $eeLogin, 'email' => $eeEmail));
		$eeAttribute = $this->eeSettings['GroupAttribute'] ?: 'cn';
		$eeEntries = $this->eeSearch($this->eeSettings['GroupBase'], $eeFilter, array($eeAttribute));
		if( $eeEntries === null ) { return null; }

		return eeSFL_NormaliseGroupNames(eeSFL_LDAPEntryValues($eeEntries, $eeAttribute));
	}

	/**
	 * Every group under the group base.
	 *
	 * @return string[]|null
	 */
	public function eeAllGroups() {

		$eeAttribute = $this->eeSettings['GroupAttribute'] ?: 'cn';
		$eeEntries = $this->eeSearch($this->eeSettings['GroupBase'], $this->eeSettings['GroupListFilter'], array($eeAttribute));
		if( $eeEntries === null ) { return null; }

		return eeSFL_NormaliseGroupNames(eeSFL_LDAPEntryValues($eeEntries, $eeAttribute));
	}

	public function __destruct() {
		if( $this->eeLink ) { @ldap_unbind($this->eeLink); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- closing only
	}
}



/**
 * Cache key part that changes whenever the LDAP settings change, so saving them drops old results.
 *
 * @param array $eeSettings
 * @return string
 */
function eeSFL_LDAPCacheVersion($eeSettings) {
	unset($eeSettings['BindPassword']);
	return substr(md5(wp_json_encode($eeSettings)), 0, 10);
}


/**
 * The groups a WordPress user is in, from LDAP (cached 15 minutes) and the
 * simple_file_list_user_groups filter.
 *
 * @param int $eeUserID
 * @return string[]
 */
function eeSFL_UserGroups($eeUserID) {

	static $eeMemo = array();

	$eeUserID = (int) $eeUserID;
	if( $eeUserID <= 0 ) { return array(); }
	if( isset($eeMemo[$eeUserID]) ) { return $eeMemo[$eeUserID]; }

	$eeGroups = array();
	$eeSettings = eeSFL_LDAPSettings();

	if( eeSFL_LDAPEnabled($eeSettings) ) {

		$eeKey = 'eeSFL_groups_' . eeSFL_LDAPCacheVersion($eeSettings) . '_' . $eeUserID;
		$eeCached = get_transient($eeKey);

		if( is_array($eeCached) ) {
			$eeGroups = $eeCached;
		} else {
			$eeUser = get_userdata($eeUserID);
			$eeFound = null;
			if( $eeUser ) {
				$eeDirectory = new eeSFL_LDAP_Directory($eeSettings);
				$eeFound = $eeDirectory->eeUserGroups((string) $eeUser->user_login, (string) $eeUser->user_email);
				if( $eeFound === null ) {
					eeSFL_Debug_Log('LDAP groups for user ' . $eeUserID . ' unavailable: ' . $eeDirectory->eeError, 'Access');
				}
			}
			$eeGroups = $eeFound ?? array();
			// A failed lookup is retried after a minute rather than locking someone out for 15
			set_transient($eeKey, $eeGroups, $eeFound === null ? MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
		}
	}

	$eeGroups = eeSFL_NormaliseGroupNames(apply_filters('simple_file_list_user_groups', $eeGroups, $eeUserID));
	$eeMemo[$eeUserID] = $eeGroups;

	return $eeGroups;
}


/**
 * The groups an admin can grant access to: every directory group (cached 15 minutes),
 * limited by AllowedGroups, plus the simple_file_list_available_groups filter.
 *
 * @return string[]
 */
function eeSFL_AvailableGroups() {

	$eeGroups = array();
	$eeSettings = eeSFL_LDAPSettings();

	if( eeSFL_LDAPEnabled($eeSettings) ) {

		$eeKey = 'eeSFL_groups_' . eeSFL_LDAPCacheVersion($eeSettings) . '_all';
		$eeCached = get_transient($eeKey);

		if( is_array($eeCached) ) {
			$eeGroups = $eeCached;
		} else {
			$eeDirectory = new eeSFL_LDAP_Directory($eeSettings);
			$eeFound = $eeDirectory->eeAllGroups();
			$eeGroups = $eeFound ?? array();
			set_transient($eeKey, $eeGroups, $eeFound === null ? MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
		}

		$eeGroups = eeSFL_FilterAllowedGroups($eeGroups, $eeSettings);
	}

	return eeSFL_NormaliseGroupNames(apply_filters('simple_file_list_available_groups', $eeGroups));
}
