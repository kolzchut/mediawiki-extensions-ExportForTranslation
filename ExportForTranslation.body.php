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
		'header-replacement'              => '== %s ==',
		'header-transclusion'             => '/#%s(}}|\s*\|)/',
		'header-transclusion-replacement' => '#%s$1',
		// A link starts with [, but can end with ], | or # - beyond either it's just text
		'title'                           => '/\[\[\s*?%s\s*([\|\]#])/',
		'title-replacement'               => '[[%s$1',
		'category-title'                  => '/\[\[קטגוריה:\s*%s\s*\]/',
		'category-title-replacement'      => '[[קטגוריה:%s]',
		'title-transclusion'              => '/\{\{\s*הטמעת כותרת\s*\|\s*%s\#/',
		'title-transclusion-replacement'  => '{{הטמעת כותרת | %s#'
	];

	private static array $linkTranslations = [];

	/**
	 * Load the content of a given page (by name), do our misc. transformations & add metadata
	 *
	 * @param $pageName
	 *
	 * @return null|string
	 */
	public static function export( $pageName ) {
		$targetLanguage = $GLOBALS[ 'wgExportForTranslationDefaultLanguage' ];

		$title = Title::newFromText( $pageName );
		$wikiPage = WikiPage::factory( $title );
		$wikitext = $wikiPage->getContent()->getNativeData();

		$linkTitles = self::getPageLinks( $wikiPage );
		$linkPageIds = array_map(
			function( Title $t ) {
				return $t->getArticleID();
			},
			$linkTitles
		);

		// Translate headers
		$headersTargetMsg = wfMessage( 'exportfortranslation-headers-list-' . $targetLanguage );
		if ( $headersTargetMsg->exists() ) {
			$headersNeedles = explode(
				"\n", wfMessage( 'exportfortranslation-headers-list-he' )->text()
			);
			$headersReplacements = explode( "\n", $headersTargetMsg->text() );

			// Translate regular headers
			$wikitext = self::transform( $wikitext, $headersNeedles, $headersReplacements, 'header' );

			// Translate headers in transclusions
			$wikitext = self::transform( $wikitext, $headersNeedles, $headersReplacements, 'header-transclusion' );
		}

		// Translate regular links
		self::$linkTranslations = TranslationManagerStatus::getSuggestionsByIds(
			$targetLanguage, 'title', $linkPageIds
		);
		$linkNeedles = array_keys( self::$linkTranslations );
		$linkReplacements = array_values( self::$linkTranslations );
		// Do title transformation in links
		$wikitext = self::transform( $wikitext, $linkNeedles, $linkReplacements, 'title' );
		// Do title transformation in transclusions
		$wikitext = self::transform( $wikitext, $linkNeedles, $linkReplacements, 'title-transclusion' );
		// Do title transformation in *category links*, using the regular titles, not the category langlinks
		$wikitext = self::transform( $wikitext, $linkNeedles, $linkReplacements, 'category-title' );

		// Add metadata as comment
		$wikitext = self::makeHtmlComment( self::getArticleMetadata( $title ) ) . $wikitext;
		$wikitext .= PHP_EOL . self::makeLanguageLinkToSource( $title );

		return $wikitext;
	}

	/**
	 * @param WikiPage $wikiPage
	 *
	 * @return Title[]
	 */
	private static function getPageLinks( WikiPage $wikiPage ) {
		$pageLinks = [];
		$pageCategories = [];

		$title = $wikiPage->getTitle();

		if ( $title->exists() ) {
			$pageLinks = $title->getLinksFrom();
			$pageCategories = iterator_to_array( $wikiPage->getCategories() ); // Returns TitleArray
		}

		return array_merge( $pageLinks, $pageCategories );
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
		$metadata = 'שם הערך המקורי: ' . $hebrewName . PHP_EOL;
		$targetName = self::$linkTranslations[ $hebrewName ] ?? null;

		if ( $targetName !== null ) {
			$metadata .= 'שם הערך המתורגם: ' . $targetName . PHP_EOL;
		} else {
			$metadata .= 'אין לשם ערך זה תרגום קיים' . PHP_EOL;
		}

		$wikipage = WikiPage::newFromID( $title->getArticleID() );
		$lastmod = $wikipage->getTimestamp();
		$metadata .= 'תאריך עדכון אחרון של הערך המקורי: ' . $lastmod . PHP_EOL;
		$metadata .= 'Revision: ' . $wikipage->getLatest();

		return $metadata;
	}

	protected static function makeHtmlComment( $text ) {
		return '<!--' . PHP_EOL . $text . PHP_EOL . '-->' . PHP_EOL;
	}

	/**
	 * Do the actual replacements
	 *
	 * @param string $wikitext
	 * @param array $needles
	 * @param array $replacements
	 * @param string $type
	 *
	 * @return string
	 */
	private static function transform( $wikitext, $needles, $replacements, $type ) {
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

		// Only for search strings
		if ( strpos( $type, 'replacement' ) === false ) {
			// Escape the string, including regex delimiter
			$needle = preg_quote( $needle, '/' );

			// Ignore extra spaces, because MW ignores them when creating links
			$needle = str_replace( ' ', '\s*', $needle );
		}


		$needle = sprintf( $template, $needle );
		return true;
	}

}

