<?php

use PHPUnit\Framework\TestCase;

/**
 * Access rules, grants, LDAP filter helpers and the download resolver.
 */
class AccessTest extends TestCase {

	private $eeRoot;

	protected function setUp(): void {

		$GLOBALS['eeTestOptions'] = array();
		$GLOBALS['eeTestFilters'] = array();
		$GLOBALS['eeTestUsers'] = array(
			1 => array('login' => 'admin', 'email' => 'admin@example.org', 'roles' => array('administrator'), 'caps' => array('activate_plugins', 'manage_options', 'read')),
			2 => array('login' => 'jan', 'email' => 'jan@example.org', 'roles' => array('subscriber'), 'caps' => array('read')),
			3 => array('login' => 'piet', 'email' => 'piet@example.org', 'roles' => array('editor'), 'caps' => array('read', 'edit_posts')),
		);

		// Directory groups, by user, through the public filter
		$GLOBALS['eeTestFilters']['avpvh_flst_user_groups'] = function($eeGroups, $eeUserID) {
			return array(2 => array('Leden'), 3 => array('bestuur', 'leden'))[$eeUserID] ?? $eeGroups;
		};

		$this->eeRoot = SFL_TEST_ROOT . '/site-' . uniqid() . '/';
		mkdir($this->eeRoot . 'files/_eeSFL_Thumbnails', 0777, true);
		file_put_contents($this->eeRoot . 'files/open.pdf', 'open');
		file_put_contents($this->eeRoot . 'files/secret.pdf', 'secret');
		file_put_contents($this->eeRoot . 'files/_eeSFL_Thumbnails/thumb_secret.jpg', 'thumb');
		file_put_contents($this->eeRoot . 'outside.txt', 'outside');

		$GLOBALS['eeTestOptions']['avpvh_flst_list_ids'] = array(1);
		$GLOBALS['eeTestOptions']['avpvh_flst_settings_1'] = array('FileListDir' => 'files/', 'ShowList' => 'USER', 'ProtectFiles' => 'YES', 'AdminRole' => 5);
		$GLOBALS['eeTestOptions']['avpvh_flst_files_1'] = array(
			array('FilePath' => 'open.pdf', 'FileExt' => 'pdf'),
			array('FilePath' => 'secret.pdf', 'FileExt' => 'pdf', 'Access' => array('groups' => array('bestuur'))),
		);
	}

	public function test_ldap_escape_follows_rfc4515() {
		$this->assertSame('a\\2ab\\28c\\29d\\5ce\\00', avpvh_flst_ldap_escape("a*b(c)d\\e\0"));
	}

	public function test_ldap_filter_escapes_every_placeholder() {
		$this->assertSame(
			'(|(uid=jan\\2a)(mail=x\\29\\28uid=\\2a))',
			avpvh_flst_ldap_filter('(|(uid={login})(mail={email}))', array('login' => 'jan*', 'email' => 'x)(uid=*'))
		);
	}

	public function test_entry_values_reads_ldap_get_entries_format() {
		$eeEntries = array(
			'count' => 2,
			0 => array('cn' => array('count' => 1, 0 => 'Bestuur'), 'dn' => 'cn=Bestuur,ou=groups'),
			1 => array('cn' => array('count' => 2, 0 => 'leden', 1 => 'members'), 'dn' => 'cn=leden,ou=groups'),
		);
		$this->assertSame(array('Bestuur', 'leden', 'members'), avpvh_flst_ldap_entry_values($eeEntries, 'CN'));
		$this->assertSame(array(), avpvh_flst_ldap_entry_values(array('count' => 0), 'cn'));
	}

	public function test_group_names_are_normalised() {
		$this->assertSame(array('bestuur', 'leden'), avpvh_flst_normalise_group_names(array(' Leden ', 'bestuur', 'LEDEN', '', array('x'))));
	}

	public function test_allowed_groups_restrict_offered_groups() {
		$this->assertSame(array('leden'), avpvh_flst_filter_allowed_groups(array('leden', 'it-beheer'), array('AllowedGroups' => 'Leden, bestuur')));
		$this->assertSame(array('it-beheer', 'leden'), avpvh_flst_filter_allowed_groups(array('leden', 'it-beheer'), array('AllowedGroups' => '')));
	}

	public function test_ldap_bind_password_supports_secret_file() {
		$this->assertFalse(avpvh_flst_ldap_password_in_config());
		$this->assertSame('from-db', avpvh_flst_ldap_bind_password(array('BindPassword' => 'from-db')));

		$eeSecretFile = $this->eeRoot . 'ldap-secret.txt';
		file_put_contents($eeSecretFile, "  from-secret-file\n");
		define('AVPVH_FLST_LDAP_BIND_PASSWORD_FILE', $eeSecretFile);

		$this->assertTrue(avpvh_flst_ldap_password_in_config());
		$this->assertSame('from-secret-file', avpvh_flst_ldap_bind_password(array('BindPassword' => 'from-db')));
	}

	public function test_grant_is_normalised() {
		$this->assertSame(
			array('users' => array(2, 3), 'roles' => array('editor'), 'groups' => array('leden')),
			avpvh_flst_normalise_grant(array('users' => array('3', 2, 0, -1, 2), 'roles' => array('Editor', '</>'), 'groups' => array('Leden')))
		);
		$this->assertTrue(avpvh_flst_grant_is_empty(array('users' => array(0), 'roles' => array(''))));
		$this->assertTrue(avpvh_flst_grant_is_empty('nonsense'));
	}

	public function test_legacy_add_on_grants_are_read() {
		$this->assertSame(array(5), avpvh_flst_file_grant(array('AccessUsers' => array(5), 'AccessRole' => ''))['users']);
		$this->assertSame(array('author'), avpvh_flst_file_grant(array('AccessRole' => 'author'))['roles']);
		$this->assertSame(array('administrator', 'editor'), avpvh_flst_file_grant(array('AccessRole' => '4'))['roles']);
	}

	public function test_user_matches_grant_by_id_role_or_group() {
		$this->assertTrue(avpvh_flst_user_matches_grant(array('users' => array(2)), 2));
		$this->assertTrue(avpvh_flst_user_matches_grant(array('roles' => array('editor')), 3));
		$this->assertTrue(avpvh_flst_user_matches_grant(array('groups' => array('leden')), 2)); // "Leden" from the directory
		$this->assertFalse(avpvh_flst_user_matches_grant(array('groups' => array('bestuur')), 2));
		$this->assertFalse(avpvh_flst_user_matches_grant(array('users' => array(0)), 0)); // Anonymous never matches
	}

	public function test_list_visibility() {
		$eeSettings = array('ShowList' => 'GRANTED', 'ListAccess' => array('groups' => array('bestuur')), 'AdminRole' => 5);
		$this->assertTrue(avpvh_flst_user_can_see_list(1, $eeSettings, 3));
		$this->assertFalse(avpvh_flst_user_can_see_list(1, $eeSettings, 2));
		$this->assertFalse(avpvh_flst_user_can_see_list(1, $eeSettings, 0));
		$this->assertTrue(avpvh_flst_user_can_see_list(1, $eeSettings, 1)); // Manages the list

		$this->assertTrue(avpvh_flst_user_can_see_list(1, array('ShowList' => 'USER'), 2));
		$this->assertFalse(avpvh_flst_user_can_see_list(1, array('ShowList' => 'USER'), 0));
		$this->assertFalse(avpvh_flst_user_can_see_list(1, array('ShowList' => 'ADMIN'), 3));
		$this->assertFalse(avpvh_flst_user_can_see_list(1, array('ShowList' => 'NO'), 3));
		$this->assertTrue(avpvh_flst_user_can_see_list(1, array('ShowList' => 'YES'), 0));
	}

	public function test_file_grant_narrows_the_list_audience() {
		$eeSettings = $GLOBALS['eeTestOptions']['avpvh_flst_settings_1'];
		$eeSecret = $GLOBALS['eeTestOptions']['avpvh_flst_files_1'][1];

		$this->assertTrue(avpvh_flst_user_can_read_file(1, $eeSettings, $eeSecret, 3));
		$this->assertFalse(avpvh_flst_user_can_read_file(1, $eeSettings, $eeSecret, 2));
		$this->assertTrue(avpvh_flst_user_can_read_file(1, $eeSettings, $eeSecret, 1));

		// In the file's grant, but not allowed to see the list at all
		$eeSettings['ShowList'] = 'NO';
		$this->assertFalse(avpvh_flst_user_can_read_file(1, $eeSettings, $eeSecret, 3));
	}

	public function test_changing_a_file_needs_front_management_and_read_access() {
		$eeSettings = $GLOBALS['eeTestOptions']['avpvh_flst_settings_1'];
		$eeSecret = $GLOBALS['eeTestOptions']['avpvh_flst_files_1'][1];
		$eeOpen = $GLOBALS['eeTestOptions']['avpvh_flst_files_1'][0];

		// Front-end management off: only the list's managers
		$this->assertTrue(avpvh_flst_user_can_change_file(1, $eeSettings, $eeSecret, 1));
		$this->assertFalse(avpvh_flst_user_can_change_file(1, $eeSettings, $eeOpen, 2));

		// On: only items the user may read
		$eeSettings['AllowFrontManage'] = 'YES';
		$this->assertTrue(avpvh_flst_user_can_change_file(1, $eeSettings, $eeOpen, 2));
		$this->assertFalse(avpvh_flst_user_can_change_file(1, $eeSettings, $eeSecret, 2));
		$this->assertTrue(avpvh_flst_user_can_change_file(1, $eeSettings, $eeSecret, 3));
		$this->assertFalse(avpvh_flst_user_can_change_file(1, $eeSettings, $eeOpen, 0)); // List is for logged-in users

		// A list nobody but its managers may see
		$eeSettings['ShowList'] = 'NO';
		$this->assertFalse(avpvh_flst_user_can_change_file(1, $eeSettings, $eeOpen, 3));
		$this->assertTrue(avpvh_flst_user_can_change_file(1, $eeSettings, $eeOpen, 1));
	}

	public function test_protected_url_keeps_the_extension_last() {
		$this->assertSame(
			'https://example.org/?sfl-list=2&sfl-dl=1&sfl-file=sub/my%20file%26x.pdf',
			avpvh_flst_protected_file_url(2, 'sub/my file&x.pdf', array('download' => TRUE))
		);
	}

	public function test_resolve_serves_a_readable_file() {
		$eeResult = avpvh_flst_resolve_file_request(1, 'open.pdf', FALSE, 2, $this->eeRoot);
		$this->assertSame(200, $eeResult['status']);
		$this->assertSame(realpath($this->eeRoot . 'files/open.pdf'), $eeResult['file']);
		$this->assertSame('files/open.pdf', $eeResult['path']);
	}

	public function test_resolve_hides_files_without_access() {
		$this->assertSame(404, avpvh_flst_resolve_file_request(1, 'secret.pdf', FALSE, 2, $this->eeRoot)['status']);
		$this->assertSame(401, avpvh_flst_resolve_file_request(1, 'secret.pdf', FALSE, 0, $this->eeRoot)['status']);
		$this->assertSame(200, avpvh_flst_resolve_file_request(1, 'secret.pdf', FALSE, 3, $this->eeRoot)['status']);
	}

	public function test_thumbnail_needs_access_to_its_file() {
		$this->assertSame(404, avpvh_flst_resolve_file_request(1, 'secret.pdf', TRUE, 2, $this->eeRoot)['status']);
		$eeResult = avpvh_flst_resolve_file_request(1, 'secret.pdf', TRUE, 3, $this->eeRoot);
		$this->assertSame(200, $eeResult['status']);
		$this->assertSame('thumb_secret.jpg', $eeResult['name']);
	}

	/**
	 * @dataProvider provide_bad_requests
	 */
	public function test_resolve_rejects_paths_outside_the_list($eeListID, $eePath) {
		$this->assertSame(404, avpvh_flst_resolve_file_request($eeListID, $eePath, FALSE, 1, $this->eeRoot)['status']);
	}

	public static function provide_bad_requests() {
		return array(
			'not in the list'  => array(1, 'outside.txt'),
			'traversal'        => array(1, '../outside.txt'),
			'nested traversal' => array(1, 'x/../../outside.txt'),
			'null byte'        => array(1, "open.pdf\0.txt"),
			'unknown list'     => array(7, 'open.pdf'),
			'empty'            => array(1, ''),
		);
	}

	public function test_resolve_refuses_a_listed_file_that_is_a_symlink_out() {
		if( !function_exists('symlink') || !@symlink($this->eeRoot . 'outside.txt', $this->eeRoot . 'files/link.txt') ) {
			$this->markTestSkipped('No symlinks here');
		}
		$GLOBALS['eeTestOptions']['avpvh_flst_files_1'][] = array('FilePath' => 'link.txt', 'FileExt' => 'txt');
		$this->assertSame(404, avpvh_flst_resolve_file_request(1, 'link.txt', FALSE, 1, $this->eeRoot)['status']);
	}

	/**
	 * @dataProvider provide_ranges
	 */
	public function test_parse_range($eeHeader, $eeExpected) {
		$this->assertSame($eeExpected, avpvh_flst_parse_range($eeHeader, 1000));
	}

	public static function provide_ranges() {
		return array(
			'first bytes'   => array('bytes=0-99', array(0, 99)),
			'open end'      => array('bytes=900-', array(900, 999)),
			'suffix'        => array('bytes=-100', array(900, 999)),
			'end clamped'   => array('bytes=990-5000', array(990, 999)),
			'past the end'  => array('bytes=1000-', FALSE),
			'reversed'      => array('bytes=50-10', FALSE),
			'multi range'   => array('bytes=0-1,5-6', null),
			'other unit'    => array('items=0-1', null),
			'empty'         => array('', null),
		);
	}

	public function test_content_disposition_is_header_safe() {
		$this->assertSame(
			'attachment; filename="r_sum_ _x_.pdf"; filename*=UTF-8\'\'r%C3%A9sum%C3%A9%20%22x%22.pdf',
			avpvh_flst_content_disposition('résumé "x".pdf', FALSE)
		);
		$this->assertStringStartsWith('inline;', avpvh_flst_content_disposition('a.pdf', TRUE));
	}

	public function test_scriptable_types_are_never_inline() {
		foreach( array('html', 'htm', 'svg', 'xml', 'js', 'xhtml') as $eeExt ) {
			$this->assertNotContains($eeExt, avpvh_flst_inline_extensions());
		}
	}
}
