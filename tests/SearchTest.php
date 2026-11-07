<?php

use PHPUnit\Framework\TestCase;

/**
 * Search matching, paging arithmetic and the form and link markup.
 */
class SearchTest extends TestCase {

	private function files() {
		return array(
			3 => array('FilePath' => 'Notulen-bestuur-2026.pdf', 'FileNiceName' => '', 'FileDescription' => 'Vergadering maart'),
			5 => array('FilePath' => 'ledenbrief.txt', 'FileNiceName' => 'Brief aan de leden', 'FileDescription' => ''),
			8 => array('FilePath' => 'foto_zomer.jpg', 'FileNiceName' => '', 'FileDescription' => 'Café &amp; terras', 'SubmitterComments' => 'gemaakt door Jan'),
		);
	}

	public function test_terms_are_split_lowercased_and_limited() {
		$this->assertSame(array('notulen', 'maart'), eeSFL_SearchTerms("  Notulen \t MAART notulen "));
		$this->assertSame(array(), eeSFL_SearchTerms('   '));
		$this->assertCount(10, eeSFL_SearchTerms(implode(' ', range(1, 20))));
		$this->assertSame(array('café'), eeSFL_SearchTerms('CAFÉ'));
	}

	/**
	 * @dataProvider provide_searches
	 */
	public function test_filter_matches_every_word_and_keeps_keys($eeQuery, $eeExpectedKeys) {
		$this->assertSame($eeExpectedKeys, array_keys(eeSFL_FilterFiles($this->files(), $eeQuery)));
	}

	public static function provide_searches() {
		return array(
			'empty shows all'            => array('', array(3, 5, 8)),
			'file name'                  => array('notulen', array(3)),
			'hyphens count as spaces'    => array('bestuur 2026', array(3)),
			'nice name'                  => array('brief leden', array(5)),
			'description'                => array('maart', array(3)),
			'entities decoded'           => array('café & terras', array(8)),
			'submitter comments'         => array('jan', array(8)),
			'extension'                  => array('pdf', array(3)),
			'every word must match'      => array('notulen zomer', array()),
			'case insensitive'           => array('LEDENBRIEF', array(5)),
		);
	}

	public function test_paginate() {
		$this->assertSame(array('page' => 1, 'pages' => 1, 'offset' => 0, 'length' => null), eeSFL_Paginate(50, 0, 3));
		$this->assertSame(array('page' => 2, 'pages' => 3, 'offset' => 10, 'length' => 10), eeSFL_Paginate(25, 10, 2));
		$this->assertSame(3, eeSFL_Paginate(25, 10, 99)['page']); // Clamped to the last page
		$this->assertSame(1, eeSFL_Paginate(25, 10, -4)['page']);
		$this->assertSame(1, eeSFL_Paginate(0, 10, 2)['pages']); // An empty list is one page
	}

	public function test_page_numbers_have_gaps() {
		$this->assertSame(array(1, 2, 3), eeSFL_PageNumbers(1, 3));
		$this->assertSame(array(1, 2, 3, 0, 10), eeSFL_PageNumbers(1, 10));
		$this->assertSame(array(1, 0, 3, 4, 5, 6, 7, 0, 10), eeSFL_PageNumbers(5, 10));
	}

	public function test_query_reads_the_list_parameters() {
		$_GET = array('sfl_search_2' => '  <b>notulen</b> ', 'sfl_page_2' => '3', 'sfl_page_1' => 'x');
		$this->assertSame('notulen', eeSFL_SearchQuery(2));
		$this->assertSame('', eeSFL_SearchQuery(1));
		$this->assertSame(3, eeSFL_PageQuery(2));
		$this->assertSame(1, eeSFL_PageQuery(1));
		$_GET = array('sfl_search_1' => array('x'));
		$this->assertSame('', eeSFL_SearchQuery(1));
		$_GET = array();
	}

	public function test_search_form_keeps_page_parameters_and_escapes() {
		$eeHTML = eeSFL_SearchFormHTML(1, '"><script>', '/?page_id=4&sfl_page_1=2&sfl_search_1=x&ee=1');
		$this->assertStringContainsString('name="page_id" value="4"', $eeHTML);
		$this->assertStringNotContainsString('name="sfl_page_1"', $eeHTML); // A new search starts on page 1
		$this->assertStringNotContainsString('name="ee"', $eeHTML);
		$this->assertStringNotContainsString('<script>', $eeHTML);
		$this->assertStringContainsString('Show All', $eeHTML);
	}

	public function test_pagination_links() {
		$this->assertSame('', eeSFL_PaginationHTML(1, eeSFL_Paginate(5, 10, 1), '/?page_id=4'));
		$eeHTML = eeSFL_PaginationHTML(1, eeSFL_Paginate(25, 10, 2), '/?page_id=4&sfl_search_1=a&sfl_page_1=2');
		$this->assertStringContainsString('href="/?page_id=4&amp;sfl_search_1=a"', $eeHTML); // Page 1 drops the parameter
		$this->assertStringContainsString('sfl_page_1=3', $eeHTML);
		$this->assertStringContainsString('aria-current="page">2<', $eeHTML);
	}
}
