<?php

use PHPUnit\Framework\TestCase;

/**
 * A page view adjusts a list's settings for its visitor (uploads off, shortcode attributes).
 * Saving one setting must store only that setting, never those adjustments.
 */
class SaveSettingTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['eeTestOptions'] = array('avpvh_flst_settings_2' => array('AllowUploads' => 'ADMIN', 'UploadMaxFileSize' => 64));
		$GLOBALS['eeSFL'] = new stdClass();
		$GLOBALS['eeSFL']->eeListID = 2;
		$GLOBALS['eeSFL']->eeListSettings = array('AllowUploads' => 'NO', 'UploadMaxFileSize' => 64); // As a visitor sees it
	}

	public function test_only_the_one_setting_is_stored() {
		$this->assertTrue(avpvh_flst_save_setting(2, 'UploadMaxFileSize', 32));
		$this->assertSame(array('AllowUploads' => 'ADMIN', 'UploadMaxFileSize' => 32), $GLOBALS['eeTestOptions']['avpvh_flst_settings_2']);
	}

	public function test_unknown_list_stores_nothing() {
		$this->assertFalse(avpvh_flst_save_setting(9, 'UploadMaxFileSize', 32));
		$this->assertArrayNotHasKey('avpvh_flst_settings_9', $GLOBALS['eeTestOptions']);
	}
}
