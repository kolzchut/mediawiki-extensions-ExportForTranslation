<?php

/**
 * @Note We do not worry about translating "header transclusions" for non-existing articles -
 *       that is the responsibility of the translation manager. All titles in the project list
 *       now have their "dependencies" (titles it transcludes from) marked
 */

use TranslationManager\TranslationManagerStatus;

class ExportForTranslation {

	private static $textTemplates = [
		'header'                          => '/\=\=\s*(%s)\s*\=\=/',
		'header-replacement'              => '== %s == <!-- $1 -->',
		'header-transclusion'             => '/#%s(}}|\s*\|)/',
		'header-transclusion-replacement' => '#%s$1',
		'title'                           => '/(\[\[)\s?%s\s*([\|\]])/',
		'title-replacement'               => '$1%s$2',
		'title-transclusion'              => '/\{\{\s*הטמעת כותרת\s*\|\s*%s\#/',
		'title-transclusion-replacement'  => '{{הטמעת כותרת | %s#'
	];

	private static $headerLines, $titleLines, $interlanguageLines;

	/**
	 * @param Title $title
	 *
	 * @return null|string
	 */
	private static function getPageContent( Title $title ) {
		if ( $title->exists() ) {
			$page = WikiPage::factory( $title );
			$content = $page->getRevision()->getContent();
			return ContentHandler::getContentText( $content );
		} else {
			return null;
		}
	}

	/**
	 * Load the content of a given page (by name), do our misc. transformations & add metadata
	 *
	 * @param $pageName
	 *
	 * @return null|string
	 */
	public static function export( $pageName ) {
		$title = Title::newFromText( $pageName );
		$wikitext = self::getPageContent( $title );

		self::loadData();

		// Prepare headers transformation.
		$wikitext = self::doTransformation( $wikitext, self::$headerLines, 'header' );
		// Prepare headers transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, self::$headerLines, 'header-transclusion' );
		// Prepare titles transformation in links
		$wikitext = self::doTransformation( $wikitext, self::$titleLines, 'title' );
		// Prepare titles transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, self::$titleLines, 'title-transclusion' );

		$wikitext = self::makeHtmlComment( self::getArticleMetadata( $title ) ) . $wikitext;
		$wikitext .= PHP_EOL . self::makeLanguageLinkToSource( $title );

		return $wikitext;
	}

	/**
	 * Load data from on-wiki messages into class members
	 */
	private static function loadData() {
		self::$headerLines = explode( "\n", wfMessage( 'exportfortranslation-headers-list' )->text() );

		self::$titleLines = self::getAllTranslationSuggestions();
		self::$interlanguageLines = self::getAllInterlanguageLinks();

		// Merge interlanguage lines with the suggested titles
		self::$titleLines = array_merge( self::$interlanguageLines, self::$titleLines );
	}

	private static function getAllTranslationSuggestions() {
		static $translationSuggestions = [];
		$rows = TranslationManagerStatus::getAllSuggestions();
		foreach ( $rows as $row ) {
			$translationSuggestions[] = strtr( $row->page_title, '_', ' ' );
			$translationSuggestions[] = $row->suggested_translation;
		}

		return $translationSuggestions;
	}

	private static function getAllInterlanguageLinks() {
		$tables = [ 'page', 'langlinks' ];
		$fields = [
			'namespace' => 'page_namespace',
			'origin' => 'page_title',
			'target' => 'll_title'
		];
		$conds = [
			'll_lang' => 'ar',
			'page_namespace' => NS_MAIN,
			'page_is_redirect' => 0
		];
		$join_conds = [ 'langlinks' => [ 'INNER JOIN', 'll_from = page_id' ] ];

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( $tables, $fields, $conds, __METHOD__, [], $join_conds );

		$lines = [];
		foreach ( $res as $row ) {
			$lines[] = Title::newFromDBkey( $row->origin )->getPrefixedText();
			$lines[] = $row->target;
		}

		return $lines;
	}

	/**
	 * Make an interlanguage link back to the original page
	 *
	 * @param Title $title
	 *
	 * @return string
	 */
	private static function makeLanguageLinkToSource( Title $title ) {
		$hebrewName = $title->getFullText();
		return '[[he:' . $hebrewName . ']]' . PHP_EOL;
	}

	/**
	 * @param Title $title
	 *
	 * @return string
	 */
	private static function getArticleMetadata( Title $title ) {
		$hebrewName = $title->getFullText();
		$metadata = 'שם הערך בעברית: ' . $hebrewName . PHP_EOL;
		$hebrewNameKey = array_search( $hebrewName, self::$titleLines );

		if ( $hebrewNameKey !== false ) {
			$arabicName = self::$titleLines[ $hebrewNameKey + 1 ];
			$metadata   .= 'שם הערך בערבית: ' . $arabicName . PHP_EOL;
		} else {
			$metadata .= 'אין לשם ערך זה תרגום קיים לערבית' . PHP_EOL;
		}

		$wikipage = WikiPage::newFromID( $title->getArticleID() );
		$lastmod = $wikipage->getTimestamp();
		$metadata .= 'תאריך עדכון אחרון בעברית: ' . $lastmod . PHP_EOL;
		$metadata .= 'Revision: ' . $wikipage->getLatest();

		return $metadata;
	}

	protected static function makeHtmlComment( $text ) {
		return '<!--' . PHP_EOL . $text . PHP_EOL . '-->' . PHP_EOL;
	}

	/**
	 * Do replacements of Hebrew text into their Arabic translation
	 *
	 * @param string $wikitext
	 * @param array $lines
	 * @param string $type
	 *
	 * @return string
	 */
	private static function doTransformation( $wikitext, $lines, $type ) {
		$needles = [];
		$replacements = [];
		self::splitReplacementsArray( $lines, $needles, $replacements );
		array_walk( $needles, [ __CLASS__, 'doParamReplacement' ], $type );
		array_walk( $replacements, [ __CLASS__, 'doParamReplacement' ], "$type-replacement" );
		$wikitext = preg_replace( $needles, $replacements, $wikitext );

		return $wikitext;
	}

	/**
	 * @param string $type
	 *
	 * @return string
	 */
	private static function getTemplateForType( $type ) {
		return self::$textTemplates[ $type ];
	}

	/**
	 * @param string $needle
	 * @param int|string $key
	 * @param string $type
	 *
	 * @return bool
	 */
	private static function doParamReplacement( &$needle, $key, $type ) {
		$template = self::getTemplateForType( $type );
		$needle = sprintf( $template, $needle );
		return true;
	}

	/**
	 * Receive an array containing both texts and replacements
	 * (odd are texts, even are replacements) and splits it into two arrays
	 *
	 * @param array $combined_array
	 * @param array $source
	 * @param array $target
	 */
	private static function splitReplacementsArray( $combined_array, &$source, &$target ) {
		foreach ( $combined_array as $line_num => $line ) {
			if ( $line_num % 2 === 0 ) {
				$source[] = preg_quote( $line, '/' );
			} else {
				$target[] = $line;
			}
		}
	}

}

