<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtaccessAndListedItemTest extends TestCase {

	private const FORMATS = 'jpg, jpeg, png, tif, pdf, mov, mp4, mp3, zip'; // Plugin default

	/** The RewriteRule pattern exactly as eeSFL_LimitDirAccess() writes it, as a PHP regex. */
	private static function rulePattern(string $eeFormats): string {
		$eeTemplate = file_get_contents(dirname(__DIR__) . '/includes/htaccess-template.txt');
		$eeTemplate = str_replace('FILE_TYPES', eeSFL_HtaccessFileTypes($eeFormats), $eeTemplate);
		preg_match('/^RewriteRule (\S+) - \[([^\]]*)\]/m', $eeTemplate, $eeMatch);
		$eeFlags = stripos($eeMatch[2], 'NC') !== false ? 'i' : '';
		return '/' . str_replace('/', '\/', $eeMatch[1]) . '/' . $eeFlags;
	}

	public function testFileTypesAreTrimmedLowercasedAndEscaped(): void {
		$this->assertSame('jpg|pdf|tar\.gz', eeSFL_HtaccessFileTypes(' JPG ,pdf,, tar.gz,jpg '));
		$this->assertSame('', eeSFL_HtaccessFileTypes(' , '));
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
		$this->assertTrue(eeSFL_IsListedItem($eeFiles, 'doc.pdf'));
		$this->assertTrue(eeSFL_IsListedItem($eeFiles, 'Folder'));
		$this->assertFalse(eeSFL_IsListedItem($eeFiles, '.htaccess'));
		$this->assertFalse(eeSFL_IsListedItem($eeFiles, 'DOC.pdf'));
		$this->assertFalse(eeSFL_IsListedItem($eeFiles, ''));
		$this->assertFalse(eeSFL_IsListedItem(false, 'doc.pdf'));
	}
}
