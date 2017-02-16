<?php


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
	private static function loadPageContent( Title $title ) {
		if ( $title->exists() ) {
			$page = WikiPage::factory( $title );
			$content = $page->getRevision()->getContent();
			return ContentHandler::getContentText( $content );
		} else {
			return null;
		}
	}

	public static function export( $pageName ) {
		$title = Title::newFromText( $pageName );
		$wikitext = self::loadPageContent( $title );

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

	private static function loadData() {
		self::$headerLines = explode( "\n", wfMessage( 'exportfortranslation-headers-list' )->text() );
		self::$titleLines = explode( "\n", wfMessage( 'exportfortranslation-titles-list' )->text() );
	}

	private static function makeLanguageLinkToSource( Title $title ) {
		$hebrewName = $title->getFullText();
		return '[[he:' . $hebrewName . ']]' . PHP_EOL;
	}

	private static function getArticleMetadata( Title $title ) {
		$hebrewName = $title->getFullText();
		$metadata = 'שם הערך בעברית: ' . $hebrewName . PHP_EOL;
		$hebrewNameKey = array_search( $hebrewName, self::$titleLines );
		$arabicName = self::$titleLines[$hebrewNameKey+1];
		$metadata .= 'שם הערך בערבית: ' . $arabicName . PHP_EOL;

		$wikipage = WikiPage::newFromID( $title->getArticleID() );
		$lastmod = $wikipage->getTimestamp();
		$metadata .= 'תאריך עדכון אחרון בעברית: ' . $lastmod . PHP_EOL;
		$metadata .= 'Revision: ' . $wikipage->getLatest() . PHP_EOL;



		return $metadata;
	}

	private static function doTransformation( $wikitext, $lines, $type ) {
		$needles = [];
		$replacements = [];
		self::splitReplacementsArray( $lines, $needles, $replacements );
		array_walk( $needles, [ __CLASS__, 'doParamReplacement' ], $type );
		array_walk( $replacements, [ __CLASS__, 'doParamReplacement' ], "$type-replacement" );
		$wikitext = preg_replace( $needles, $replacements, $wikitext );

		return $wikitext;
	}

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
	 * @Note We do not worry about translating "header transclusions" for non-existing articles -
	 *       that is the responsibility of the translation manager. All titles in the project list
	 *       now have their "dependencies" (titles it transcludes from) marked
	 */

	/**
	 * @param array $combined_array
	 * @param array $source
	 * @param array $target
	 */
	private static function splitReplacementsArray(
		$combined_array, &$source, &$target
	) {
		foreach ( $combined_array as $line_num => $line ) {
			if ( $line_num % 2 === 0 ) {
				$source[] = preg_quote( $line, '/' );
			} else {
				$target[] = $line;
			}
		}
	}

}

