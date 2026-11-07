<?php

use PHPUnit\Framework\TestCase;

/**
 * Moving a site over from Simple File List: option names and the shortcode.
 */
class MigrateTest extends TestCase {

	/**
	 * @dataProvider provide_option_names
	 */
	public function test_new_option_name($eeOld, $eeNew) {
		$this->assertSame($eeNew, avpvh_filelist_new_option_name($eeOld));
	}

	public static function provide_option_names() {
		return array(
			'list ids'          => array('eeSFL_ListIDs', 'avpvh_filelist_list_ids'),
			'next list id'      => array('eeSFL_NextListID', 'avpvh_filelist_next_list_id'),
			'list settings'     => array('eeSFL_Settings_12', 'avpvh_filelist_settings_12'),
			'file list'         => array('eeSFL_FileList_3', 'avpvh_filelist_files_3'),
			'ldap'              => array('eeSFL_LDAP', 'avpvh_filelist_ldap'),
			'access settings'   => array('eeSFL_AccessSettings', 'avpvh_filelist_access_settings'),
			'version: not kept' => array('eeSFL_Version', ''),
			'log: not kept'     => array('eeSFL_TheLog', ''),
			'not a list number' => array('eeSFL_Settings_x', ''),
		);
	}

	/**
	 * @dataProvider provide_content
	 */
	public function test_rewrite_shortcodes($eeOld, $eeNew) {
		$this->assertSame($eeNew, avpvh_filelist_rewrite_shortcodes($eeOld));
	}

	public static function provide_content() {
		return array(
			'plain'              => array('[eeSFL]', '[avpvh_filelist]'),
			'attributes'         => array('<p>[eeSFL list="2" showlist="YES"]</p>', '<p>[avpvh_filelist list="2" showlist="YES"]</p>'),
			'self-closing'       => array('[eeSFL /]', '[avpvh_filelist /]'),
			'closing tag'        => array('[eeSFL][/eeSFL]', '[avpvh_filelist][/avpvh_filelist]'),
			'block'              => array("<!-- wp:shortcode -->\n[eeSFL list=\"1\"]\n<!-- /wp:shortcode -->", "<!-- wp:shortcode -->\n[avpvh_filelist list=\"1\"]\n<!-- /wp:shortcode -->"),
			'other shortcode'    => array('[eeSFLX] [eeSFL_old]', '[eeSFLX] [eeSFL_old]'),
			'two on one page'    => array('[eeSFL list="1"] [eeSFL list="2"]', '[avpvh_filelist list="1"] [avpvh_filelist list="2"]'),
		);
	}

	public function test_rewrite_reaches_nested_widget_settings() {
		$eeWidgets = array(2 => array('title' => 'Files', 'text' => '[eeSFL list="2"]'), '_multiwidget' => 1);
		$eeNew = avpvh_filelist_rewrite_shortcodes_deep($eeWidgets);
		$this->assertSame('[avpvh_filelist list="2"]', $eeNew[2]['text']);
		$this->assertSame(1, $eeNew['_multiwidget']);
	}
}
