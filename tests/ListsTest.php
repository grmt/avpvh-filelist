<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The list registry: IDs, folder overlap, new-list defaults and admin access per list.
 */
final class ListsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['eeTestOptions'] = array('avpvh_filelist_list_ids' => array(1));
		$GLOBALS['eeTestCaps'] = array();
	}

	public function testListOneAlwaysExists(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(3, 2);
		$this->assertSame(array(1, 2, 3), avpvh_filelist_get_list_ids());
		$this->assertTrue(avpvh_filelist_list_exists(1));
		$this->assertTrue(avpvh_filelist_list_exists('2'));
		$this->assertFalse(avpvh_filelist_list_exists(4));
		$this->assertFalse(avpvh_filelist_list_exists('abc'));
	}

	public function testDeletedIdsAreNeverReused(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(1, 2, 3);
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_3'] = array('FileListDir' => 'a/');
		$this->assertTrue(avpvh_filelist_delete_list(3));
		$this->assertSame(array(1, 2), avpvh_filelist_get_list_ids());
		$this->assertArrayNotHasKey('avpvh_filelist_settings_3', $GLOBALS['eeTestOptions']);
		// The counter was never used yet; it must still skip past 3
		$GLOBALS['eeTestOptions']['avpvh_filelist_next_list_id'] = 4;
		$this->assertSame(4, avpvh_filelist_next_list_id());
		$this->assertSame(5, avpvh_filelist_next_list_id());
	}

	public function testNextIdStartsAfterExistingLists(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(1, 7);
		$this->assertSame(8, avpvh_filelist_next_list_id());
	}

	public function testListOneCannotBeDeleted(): void {
		$this->assertFalse(avpvh_filelist_delete_list(1));
		$this->assertFalse(avpvh_filelist_delete_list(9)); // Unknown
	}

	public static function overlapProvider(): array {
		return array(
			'same folder'          => array('wp-content/uploads/a/', 'wp-content/uploads/a/', true),
			'same, other slashes'  => array('/wp-content/uploads/a', 'wp-content\\uploads\\a\\', true),
			'same, other case'     => array('wp-content/uploads/A/', 'wp-content/uploads/a/', true),
			'inside'               => array('wp-content/uploads/a/b/', 'wp-content/uploads/a/', true),
			'contains'             => array('wp-content/uploads/', 'wp-content/uploads/a/', true),
			'sibling'              => array('wp-content/uploads/a/', 'wp-content/uploads/b/', false),
			'shared name prefix'   => array('wp-content/uploads/files/', 'wp-content/uploads/files-2/', false),
			'site root'            => array('', 'wp-content/uploads/a/', true),
		);
	}

	#[DataProvider('overlapProvider')]
	public function testDirsOverlap(string $eeA, string $eeB, bool $eeExpected): void {
		$this->assertSame($eeExpected, avpvh_filelist_dirs_overlap($eeA, $eeB));
		$this->assertSame($eeExpected, avpvh_filelist_dirs_overlap($eeB, $eeA));
	}

	public function testFindOverlappingListSkipsItself(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(1, 2);
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_1'] = array('FileListDir' => 'wp-content/uploads/simple-file-list/');
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_2'] = array('FileListDir' => 'wp-content/uploads/board/');
		$this->assertSame(1, avpvh_filelist_find_overlapping_list('wp-content/uploads/simple-file-list/sub/'));
		$this->assertSame(0, avpvh_filelist_find_overlapping_list('wp-content/uploads/board/', 2));
		$this->assertSame(0, avpvh_filelist_find_overlapping_list('wp-content/uploads/members/'));
	}

	public function testNewListStartsClosed(): void {
		$eeBase = array('ShowList' => 'YES', 'AllowUploads' => 'YES', 'AllowFrontManage' => 'YES', 'SortBy' => 'Name', 'FileListURL' => 'https://x/');
		$eeNew = avpvh_filelist_new_list_settings($eeBase, 'Board', 'wp-content/uploads/board/');
		$this->assertSame('Board', $eeNew['ListTitle']);
		$this->assertSame('wp-content/uploads/board/', $eeNew['FileListDir']);
		$this->assertSame('ADMIN', $eeNew['ShowList']);
		$this->assertSame('NO', $eeNew['AllowUploads']);
		$this->assertSame('NO', $eeNew['AllowFrontManage']);
		$this->assertSame('Name', $eeNew['SortBy'], 'other settings are copied');
		$this->assertArrayNotHasKey('FileListURL', $eeNew);
	}

	public function testManageableListsFollowEachListsAdminRole(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(1, 2, 3);
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_1'] = array('AdminRole' => 5); // activate_plugins
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_2'] = array('AdminRole' => 2); // edit_posts
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_3'] = array('AdminRole' => 4); // edit_others_pages
		$GLOBALS['eeTestCaps'] = array('read', 'edit_posts');

		$this->assertSame(array(2), avpvh_filelist_manageable_list_ids());
		$this->assertSame('edit_posts', avpvh_filelist_menu_capability());
	}

	public function testAdminListFallsBackToAnAllowedList(): void {
		$GLOBALS['eeTestOptions']['avpvh_filelist_list_ids'] = array(1, 2);
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_1'] = array('AdminRole' => 5);
		$GLOBALS['eeTestOptions']['avpvh_filelist_settings_2'] = array('AdminRole' => 2);
		$GLOBALS['eeTestCaps'] = array('edit_posts');

		$_GET['eeListID'] = '1'; // Not allowed for this user
		$this->assertSame(2, avpvh_filelist_admin_list_id());

		$_GET['eeListID'] = '2';
		$_POST['eeListID'] = '1'; // A posted list is checked the same way
		$this->assertSame(2, avpvh_filelist_admin_list_id());

		$GLOBALS['eeTestCaps'] = array();
		$this->assertSame(0, avpvh_filelist_admin_list_id());

		unset($_GET['eeListID'], $_POST['eeListID']);
	}
}
