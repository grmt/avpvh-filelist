<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The list registry: IDs, folder overlap, new-list defaults and admin access per list.
 */
final class ListsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['eeTestOptions'] = array('eeSFL_ListIDs' => array(1));
		$GLOBALS['eeTestCaps'] = array();
	}

	public function testListOneAlwaysExists(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(3, 2);
		$this->assertSame(array(1, 2, 3), eeSFL_GetListIDs());
		$this->assertTrue(eeSFL_ListExists(1));
		$this->assertTrue(eeSFL_ListExists('2'));
		$this->assertFalse(eeSFL_ListExists(4));
		$this->assertFalse(eeSFL_ListExists('abc'));
	}

	public function testDeletedIdsAreNeverReused(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(1, 2, 3);
		$GLOBALS['eeTestOptions']['eeSFL_Settings_3'] = array('FileListDir' => 'a/');
		$this->assertTrue(eeSFL_DeleteList(3));
		$this->assertSame(array(1, 2), eeSFL_GetListIDs());
		$this->assertArrayNotHasKey('eeSFL_Settings_3', $GLOBALS['eeTestOptions']);
		// The counter was never used yet; it must still skip past 3
		$GLOBALS['eeTestOptions']['eeSFL_NextListID'] = 4;
		$this->assertSame(4, eeSFL_NextListID());
		$this->assertSame(5, eeSFL_NextListID());
	}

	public function testNextIdStartsAfterExistingLists(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(1, 7);
		$this->assertSame(8, eeSFL_NextListID());
	}

	public function testListOneCannotBeDeleted(): void {
		$this->assertFalse(eeSFL_DeleteList(1));
		$this->assertFalse(eeSFL_DeleteList(9)); // Unknown
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
		$this->assertSame($eeExpected, eeSFL_DirsOverlap($eeA, $eeB));
		$this->assertSame($eeExpected, eeSFL_DirsOverlap($eeB, $eeA));
	}

	public function testFindOverlappingListSkipsItself(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(1, 2);
		$GLOBALS['eeTestOptions']['eeSFL_Settings_1'] = array('FileListDir' => 'wp-content/uploads/simple-file-list/');
		$GLOBALS['eeTestOptions']['eeSFL_Settings_2'] = array('FileListDir' => 'wp-content/uploads/board/');
		$this->assertSame(1, eeSFL_FindOverlappingList('wp-content/uploads/simple-file-list/sub/'));
		$this->assertSame(0, eeSFL_FindOverlappingList('wp-content/uploads/board/', 2));
		$this->assertSame(0, eeSFL_FindOverlappingList('wp-content/uploads/members/'));
	}

	public function testNewListStartsClosed(): void {
		$eeBase = array('ShowList' => 'YES', 'AllowUploads' => 'YES', 'AllowFrontManage' => 'YES', 'SortBy' => 'Name', 'FileListURL' => 'https://x/');
		$eeNew = eeSFL_NewListSettings($eeBase, 'Board', 'wp-content/uploads/board/');
		$this->assertSame('Board', $eeNew['ListTitle']);
		$this->assertSame('wp-content/uploads/board/', $eeNew['FileListDir']);
		$this->assertSame('ADMIN', $eeNew['ShowList']);
		$this->assertSame('NO', $eeNew['AllowUploads']);
		$this->assertSame('NO', $eeNew['AllowFrontManage']);
		$this->assertSame('Name', $eeNew['SortBy'], 'other settings are copied');
		$this->assertArrayNotHasKey('FileListURL', $eeNew);
	}

	public function testManageableListsFollowEachListsAdminRole(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(1, 2, 3);
		$GLOBALS['eeTestOptions']['eeSFL_Settings_1'] = array('AdminRole' => 5); // activate_plugins
		$GLOBALS['eeTestOptions']['eeSFL_Settings_2'] = array('AdminRole' => 2); // edit_posts
		$GLOBALS['eeTestOptions']['eeSFL_Settings_3'] = array('AdminRole' => 4); // edit_others_pages
		$GLOBALS['eeTestCaps'] = array('read', 'edit_posts');

		$this->assertSame(array(2), eeSFL_ManageableListIDs());
		$this->assertSame('edit_posts', eeSFL_MenuCapability());
	}

	public function testAdminListFallsBackToAnAllowedList(): void {
		$GLOBALS['eeTestOptions']['eeSFL_ListIDs'] = array(1, 2);
		$GLOBALS['eeTestOptions']['eeSFL_Settings_1'] = array('AdminRole' => 5);
		$GLOBALS['eeTestOptions']['eeSFL_Settings_2'] = array('AdminRole' => 2);
		$GLOBALS['eeTestCaps'] = array('edit_posts');

		$_GET['eeListID'] = '1'; // Not allowed for this user
		$this->assertSame(2, eeSFL_AdminListID());

		$_GET['eeListID'] = '2';
		$_POST['eeListID'] = '1'; // A posted list is checked the same way
		$this->assertSame(2, eeSFL_AdminListID());

		$GLOBALS['eeTestCaps'] = array();
		$this->assertSame(0, eeSFL_AdminListID());

		unset($_GET['eeListID'], $_POST['eeListID']);
	}
}
