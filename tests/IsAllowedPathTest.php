<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsAllowedPathTest extends TestCase {

	private static string $eeList;

	public static function setUpBeforeClass(): void {
		self::$eeList = WP_CONTENT_DIR . '/uploads/simple-file-list/';
		@mkdir(self::$eeList, 0777, true);
		@mkdir(SFL_TEST_ROOT . '/outside', 0777, true);
		@symlink(SFL_TEST_ROOT . '/outside', self::$eeList . 'evil-link');
		touch(self::$eeList . 'doc.pdf');
	}

	public static function pathProvider(): array {
		return array(
			'existing file'              => array('doc.pdf', true),
			'new nested file'            => array('new/sub/doc.pdf', true),
			'list dir itself'            => array('', true),
			'climb out via existing dir' => array('../../../../outside/x', false),
			'climb out via missing dir'  => array('nope/../../../../../etc/passwd', false),
			'symlink out of the list'    => array('evil-link/x.txt', false),
			'null byte'                  => array("a\0b", false),
		);
	}

	#[DataProvider('pathProvider')]
	public function testRelativeToListDir(string $eeRelative, bool $eeExpected): void {
		$this->assertSame($eeExpected, avpvh_filelist_is_allowed_path(self::$eeList . $eeRelative));
	}

	public function testAbsolutePathOutsideInstallIsRejected(): void {
		$this->assertFalse(avpvh_filelist_is_allowed_path('/etc/passwd'));
	}

	public function testEmptyPathIsRejected(): void {
		$this->assertFalse(avpvh_filelist_is_allowed_path(''));
	}

	public function testFileSystemRefusesPathsOutsideInstall(): void {
		$eeResult = avpvh_filelist_file_system('get_contents', array('file' => '/etc/passwd'));
		$this->assertFalse($eeResult['success']);
	}
}
