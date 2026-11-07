<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtaccessAndListedItemTest extends TestCase {

	private const FORMATS = 'jpg, jpeg, png, tif, pdf, mov, mp4, mp3, zip'; // Plugin default

	/** The RewriteRule pattern exactly as avpvh_flst_limit_dir_access() writes it, as a PHP regex. */
	private static function rulePattern(string $eeFormats): string {
		$eeTemplate = file_get_contents(dirname(__DIR__) . '/includes/htaccess-template.txt');
		$eeTemplate = str_replace('FILE_TYPES', avpvh_flst_htaccess_file_types($eeFormats), $eeTemplate);
		preg_match('/^RewriteRule (\S+) - \[([^\]]*)\]/m', $eeTemplate, $eeMatch);
		$eeFlags = stripos($eeMatch[2], 'NC') !== false ? 'i' : '';
		return '/' . str_replace('/', '\/', $eeMatch[1]) . '/' . $eeFlags;
	}

	public function testFileTypesAreTrimmedLowercasedAndEscaped(): void {
		$this->assertSame('jpg|pdf|tar\.gz', avpvh_flst_htaccess_file_types(' JPG ,pdf,, tar.gz,jpg '));
		$this->assertSame('', avpvh_flst_htaccess_file_types(' , '));
	}

	public static function blockedProvider(): array {
		return array(
			'first type (was unprotected)' => array('photo.jpg', true),
			'middle type'                  => array('doc.pdf', true),
			'last type'                    => array('archive.zip', true),
			'upper case'                   => array('PHOTO.JPG', true),
			'unlisted type'                => array('notes.txt', false),
			'type as a prefix only'        => array('archive.zipx', false),
			'last type minus a letter'     => array('archive.zi', false),
		);
	}

	#[DataProvider('blockedProvider')]
	public function testRuleBlocksEveryConfiguredType(string $eeFile, bool $eeBlocked): void {
		$this->assertSame($eeBlocked, (bool) preg_match(self::rulePattern(self::FORMATS), $eeFile));
	}

	public function testListedItem(): void {
		$eeFiles = array(
			array('FilePath' => 'doc.pdf'),
			array('FilePath' => 'Folder/'),
		);
		$this->assertTrue(avpvh_flst_is_listed_item($eeFiles, 'doc.pdf'));
		$this->assertTrue(avpvh_flst_is_listed_item($eeFiles, 'Folder'));
		$this->assertFalse(avpvh_flst_is_listed_item($eeFiles, '.htaccess'));
		$this->assertFalse(avpvh_flst_is_listed_item($eeFiles, 'DOC.pdf'));
		$this->assertFalse(avpvh_flst_is_listed_item($eeFiles, ''));
		$this->assertFalse(avpvh_flst_is_listed_item(false, 'doc.pdf'));
		$this->assertSame(array('FilePath' => 'doc.pdf'), avpvh_flst_listed_item($eeFiles, 'doc.pdf'));
		$this->assertSame(array(), avpvh_flst_listed_item($eeFiles, '.htaccess'));
	}
}
