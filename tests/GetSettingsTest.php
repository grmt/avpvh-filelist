<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if ( ! function_exists('get_option') ) {
	function get_option($eeName, $eeDefault = false) { return $GLOBALS['eeTestOptions'][$eeName] ?? $eeDefault; }
}
if ( ! function_exists('update_option') ) {
	function update_option($eeName, $eeValue, $eeAutoload = null) { $GLOBALS['eeTestOptions'][$eeName] = $eeValue; return true; }
}
require_once dirname(__DIR__) . '/includes/ee-class.php';

/**
 * A list ID from a request must never create a list: only list 1 restores
 * its defaults, and new installs do not allow front-end uploads.
 */
final class GetSettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['eeTestOptions'] = array();
	}

	public static function unknownIdProvider(): array {
		return array(
			'second list' => array(2),
			'string id'   => array('7'),
			'zero'        => array(0),
			'negative'    => array(-1),
			'not numeric' => array('abc'),
		);
	}

	#[DataProvider('unknownIdProvider')]
	public function testUnknownListCreatesNothing(int|string $eeListID): void {
		$eeSFL = new eeSFL_MainClass();
		$this->assertSame(array(), $eeSFL->eeSFL_GetSettings($eeListID));
		$this->assertSame(array(), $eeSFL->eeListSettings);
		$this->assertSame(array(), $GLOBALS['eeTestOptions'], 'no option may be written');
	}

	public function testUploadsAreOffByDefault(): void {
		$eeSFL = new eeSFL_MainClass();
		$this->assertSame('NO', $eeSFL->eeDefaultListSettings['AllowUploads']);
	}
}
