<?php

/**
 * @Note We do not worry about translating "header transclusions" for non-existing articles -
 *       that is the responsibility of the translation manager. All titles in the project list
 *       now have their "dependencies" (titles it transcludes from) marked
 */

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

	private static $headerLines, $titleLines;

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

		$wikitext = self::getArticleMetadata( $title ) . $wikitext;
		$wikitext .= PHP_EOL . self::makeLanguageLinkToSource( $title );

		return $wikitext;
	}

	/**
	 * Load data from on-wiki messages into class members
	 */
	private static function loadData() {
		self::$headerLines = explode( "\n", wfMessage( 'exportfortranslation-headers-list' )->text() );

		$titleLinesMsg = wfMessage( 'exportfortranslation-titles-list' );
		self::$titleLines = $titleLinesMsg->isDisabled() ? [] : explode( "\n", $titleLinesMsg->text() );
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
		$metadata .= 'Revision: ' . $wikipage->getLatest() . PHP_EOL;

		return $metadata;
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

