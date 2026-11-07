<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// Simple File List - Search and paging
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

// Search and paging for a file list. Both work through GET parameters named per list
// (sfl_search_N, sfl_page_N), so results can be bookmarked and shared, several lists on one page
// keep their own state, and no nonce is involved: the request only narrows what the visitor
// may already see.


/**
 * The GET parameter names for a list.
 *
 * @param int $eeListID
 * @return array{search: string, page: string}
 */
function eeSFL_SearchParams($eeListID) {
	$eeListID = (int) $eeListID;
	return array('search' => 'sfl_search_' . $eeListID, 'page' => 'sfl_page_' . $eeListID);
}


/**
 * The search text from the request, trimmed and limited.
 *
 * @param int $eeListID
 * @return string
 */
function eeSFL_SearchQuery($eeListID) {
	$eeParam = eeSFL_SearchParams($eeListID)['search'];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter of what the visitor may already see
	$eeQuery = isset($_GET[$eeParam]) && is_string($_GET[$eeParam]) ? sanitize_text_field(wp_unslash($_GET[$eeParam])) : '';
	return eeSFL_Substr(trim($eeQuery), 200);
}


/**
 * The requested page number (1 when absent or invalid).
 *
 * @param int $eeListID
 * @return int
 */
function eeSFL_PageQuery($eeListID) {
	$eeParam = eeSFL_SearchParams($eeListID)['page'];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation
	$eePage = isset($_GET[$eeParam]) && is_string($_GET[$eeParam]) ? absint($_GET[$eeParam]) : 1;
	return max(1, $eePage);
}


/**
 * Multibyte-safe substring from the start.
 *
 * @param string $eeText
 * @param int    $eeLength
 * @return string
 */
function eeSFL_Substr($eeText, $eeLength) {
	return function_exists('mb_substr') ? mb_substr($eeText, 0, $eeLength) : substr($eeText, 0, $eeLength);
}


/**
 * Lower-case, multibyte-safe.
 *
 * @param string $eeText
 * @return string
 */
function eeSFL_Lower($eeText) {
	return function_exists('mb_strtolower') ? mb_strtolower($eeText, 'UTF-8') : strtolower($eeText);
}


/**
 * The words to look for: split on white space, lower-cased, at most 10.
 *
 * @param string $eeQuery
 * @return string[]
 */
function eeSFL_SearchTerms($eeQuery) {
	$eeTerms = preg_split('/\s+/u', eeSFL_Lower(trim((string) $eeQuery)), -1, PREG_SPLIT_NO_EMPTY);
	return array_slice(array_values(array_unique(is_array($eeTerms) ? $eeTerms : array())), 0, 10);
}


/**
 * Does a file match every search word? Looks in the file name, nice name, description and
 * submitter comments. Hyphens and underscores in names count as spaces.
 *
 * @param array    $eeFileArray
 * @param string[] $eeTerms
 * @return bool
 */
function eeSFL_FileMatchesSearch($eeFileArray, $eeTerms) {

	if( !$eeTerms ) { return TRUE; }

	$eeName = (string) ($eeFileArray['FilePath'] ?? '');
	$eeHaystack = eeSFL_Lower(implode(' ', array(
		$eeName,
		str_replace(array('-', '_', '.'), ' ', $eeName),
		(string) ($eeFileArray['FileNiceName'] ?? ''),
		html_entity_decode((string) ($eeFileArray['FileDescription'] ?? ''), ENT_QUOTES, 'UTF-8'),
		(string) ($eeFileArray['SubmitterComments'] ?? ''),
	)));

	foreach( $eeTerms as $eeTerm ) {
		if( strpos($eeHaystack, $eeTerm) === FALSE ) { return FALSE; }
	}

	return TRUE;
}


/**
 * Keep only the files matching the search, preserving keys (the bulk actions use them).
 *
 * @param array  $eeFiles
 * @param string $eeQuery
 * @return array
 */
function eeSFL_FilterFiles($eeFiles, $eeQuery) {
	$eeTerms = eeSFL_SearchTerms($eeQuery);
	if( !$eeTerms ) { return $eeFiles; }
	return array_filter($eeFiles, function($eeFileArray) use ($eeTerms) {
		return is_array($eeFileArray) && eeSFL_FileMatchesSearch($eeFileArray, $eeTerms);
	});
}


/**
 * Page arithmetic.
 *
 * @param int $eeCount   Number of items
 * @param int $eePerPage Items per page; 0 or less means one page with everything
 * @param int $eePage    Requested page, clamped to the valid range
 * @return array{page: int, pages: int, offset: int, length: int|null}
 */
function eeSFL_Paginate($eeCount, $eePerPage, $eePage) {

	$eeCount = max(0, (int) $eeCount);
	$eePerPage = (int) $eePerPage;

	if( $eePerPage < 1 ) {
		return array('page' => 1, 'pages' => 1, 'offset' => 0, 'length' => null);
	}

	$eePages = max(1, (int) ceil($eeCount / $eePerPage));
	$eePage = min(max(1, (int) $eePage), $eePages);

	return array('page' => $eePage, 'pages' => $eePages, 'offset' => ($eePage - 1) * $eePerPage, 'length' => $eePerPage);
}


/**
 * The page numbers to show: first, last, and two either side of the current page,
 * with 0 standing for a gap.
 *
 * @param int $eePage
 * @param int $eePages
 * @return int[]
 */
function eeSFL_PageNumbers($eePage, $eePages) {

	$eeShow = array();
	for( $i = 1; $i <= $eePages; $i++ ) {
		if( $i === 1 || $i === $eePages || abs($i - $eePage) <= 2 ) { $eeShow[] = $i; }
	}

	$eeOut = array();
	$eePrev = 0;
	foreach( $eeShow as $i ) {
		if( $eePrev && $i > $eePrev + 1 ) { $eeOut[] = 0; }
		$eeOut[] = $i;
		$eePrev = $i;
	}

	return $eeOut;
}


/**
 * The search form. A GET form drops the query string of its action, so the current
 * parameters (page_id, the admin page, ...) are carried as hidden fields.
 *
 * @param int    $eeListID
 * @param string $eeQuery
 * @param string $eeBaseURL The page URL
 * @return string
 */
function eeSFL_SearchFormHTML($eeListID, $eeQuery, $eeBaseURL) {

	$eeParams = eeSFL_SearchParams($eeListID);
	$eeAction = strtok($eeBaseURL, '?');

	$eeHidden = '';
	$eeQueryString = wp_parse_url($eeBaseURL, PHP_URL_QUERY);
	if( $eeQueryString ) {
		parse_str($eeQueryString, $eeArgs);
		foreach( $eeArgs as $eeKey => $eeValue ) {
			if( in_array($eeKey, $eeParams, TRUE) || !is_scalar($eeValue) ) { continue; }
			if( preg_match('/^sfl[-_]|^ee/', $eeKey) && $eeKey !== 'eeListID' ) { continue; } // Our own one-off parameters
			$eeHidden .= '<input type="hidden" name="' . esc_attr($eeKey) . '" value="' . esc_attr((string) $eeValue) . '" />';
		}
	}

	$eeOutput = '
	<form class="eeSFL_SearchForm" role="search" method="get" action="' . esc_url($eeAction) . '">' . $eeHidden . '
		<label class="screen-reader-text" for="eeSFL_Search_' . (int) $eeListID . '">' . esc_html__('Search the files', 'simple-file-list') . '</label>
		<input type="search" id="eeSFL_Search_' . (int) $eeListID . '" name="' . esc_attr($eeParams['search']) . '" value="' . esc_attr($eeQuery) . '" placeholder="' . esc_attr__('Search the files', 'simple-file-list') . '" maxlength="200" />
		<button type="submit" class="button">' . esc_html__('Search', 'simple-file-list') . '</button>';

	if( $eeQuery !== '' ) {
		$eeOutput .= '
		<a class="eeSFL_SearchReset" href="' . esc_url(remove_query_arg(array_values($eeParams), $eeBaseURL)) . '">' . esc_html__('Show All', 'simple-file-list') . '</a>';
	}

	$eeOutput .= '
	</form>';

	return $eeOutput;
}


/**
 * Previous / numbered / next links. Empty when everything fits on one page.
 *
 * @param int    $eeListID
 * @param array  $eePaging  From eeSFL_Paginate()
 * @param string $eeBaseURL The page URL, including any search
 * @return string
 */
function eeSFL_PaginationHTML($eeListID, $eePaging, $eeBaseURL) {

	if( $eePaging['pages'] < 2 ) { return ''; }

	$eeParam = eeSFL_SearchParams($eeListID)['page'];
	$eePage = $eePaging['page'];
	$eeLink = function($eeNumber, $eeText, $eeClass) use ($eeParam, $eeBaseURL) {
		$eeURL = $eeNumber > 1 ? add_query_arg($eeParam, $eeNumber, $eeBaseURL) : remove_query_arg($eeParam, $eeBaseURL);
		return '<a class="' . esc_attr($eeClass) . '" href="' . esc_url($eeURL) . '">' . $eeText . '</a>';
	};

	$eeOutput = '
	<nav class="eeSFL_Pagination" aria-label="' . esc_attr__('File list pages', 'simple-file-list') . '">';

	if( $eePage > 1 ) {
		$eeOutput .= $eeLink($eePage - 1, '&larr; ' . esc_html__('Previous', 'simple-file-list'), 'eeSFL_PagePrev');
	}

	foreach( eeSFL_PageNumbers($eePage, $eePaging['pages']) as $eeNumber ) {
		if( $eeNumber === 0 ) {
			$eeOutput .= '<span class="eeSFL_PageGap">&hellip;</span>';
		} elseif( $eeNumber === $eePage ) {
			$eeOutput .= '<span class="eeSFL_PageCurrent" aria-current="page">' . (int) $eeNumber . '</span>';
		} else {
			$eeOutput .= $eeLink($eeNumber, (string) (int) $eeNumber, 'eeSFL_PageNumber');
		}
	}

	if( $eePage < $eePaging['pages'] ) {
		$eeOutput .= $eeLink($eePage + 1, esc_html__('Next', 'simple-file-list') . ' &rarr;', 'eeSFL_PageNext');
	}

	$eeOutput .= '
	</nav>';

	return $eeOutput;
}
