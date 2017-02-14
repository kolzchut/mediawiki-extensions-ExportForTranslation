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

	private static $titles, $headers;

	/**
	 * @param Title $title
	 *
	 * @return null|string
	 */
	private static function loadPageContent( $title ) {
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


		$headerLines = explode( "\n", wfMessage( 'exportfortranslation-headers-list' )->text() );
		$titleLines = explode( "\n", wfMessage( 'exportfortranslation-titles-list' )->text() );


		// Prepare headers transformation.
		$wikitext = self::doTransformation( $wikitext, $headerLines, 'header' );
		// Prepare headers transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, $headerLines, 'header-transclusion' );
		// Prepare titles transformation in links
		$wikitext = self::doTransformation( $wikitext, $titleLines, 'title' );
		// Prepare titles transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, $titleLines, 'title-transclusion' );


		return $wikitext;
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

