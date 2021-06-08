<?php

/**
 * @Note We do not worry about translating "header transclusions" for non-existing articles -
 *       that is the responsibility of the translation manager. All titles in the project list
 *       now have their "dependencies" (titles it transcludes from) marked
 */

use TranslationManager\TranslationManagerStatus;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class ExportForTranslation {
	private static $textTemplates = [
		'header'                          => '/\=\=\s*(%s)\s*\=\=/',
		'header-replacement'              => '== %s ==',
		'header-transclusion'             => '/#%s(}}|\s*\|)/',
		'header-transclusion-replacement' => '#%s$1',
		// A link starts with [, but can end with ], | or # - beyond either it's just text
		'title'                           => '/\[\[\s*?%s\s*([\|\]#])/',
		'title-replacement'               => '[[%s$1',
		'title-transclusion'              => '/\{\{\s*הטמעת כותרת\s*\|\s*%s\#/',
		'title-transclusion-replacement'  => '{{הטמעת כותרת | %s#'
	];

	private static array $linkTranslations = [];

	/**
	 * Load the content of a given page (by name), do our misc. transformations & add metadata
	 *
	 * @param Title $title
	 * @param int $rev_id
	 *
	 * @return null|string
	 */
	public static function export( Title $title, $rev_id = null ) {
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

		$targetLanguage = $GLOBALS[ 'wgExportForTranslationDefaultLanguage' ];

		$revision =  $rev_id ? $revisionStore->getRevisionById( $rev_id ) : $revisionStore->getRevisionByTitle( $title );
		$wikitext = $revision->getContent( SlotRecord::MAIN )->getText();

		$wikiPage = WikiPage::factory( $title );

		$linkTitles = self::getPageLinks( $wikiPage );
		$linkPageIds = array_map(
			function( Title $t ) {
				return $t->getArticleID();
			},
			$linkTitles
		);

		// Also get the translation for the current page
		array_push( $linkPageIds, $wikiPage->getId() );

		// Translate headers
		$headersTargetMsg = wfMessage( 'exportfortranslation-headers-list-' . $targetLanguage );
		if ( $headersTargetMsg->exists() ) {
			$needles = explode(
				"\n", wfMessage( 'exportfortranslation-headers-list-he' )->text()
			);
			$replacements = explode( "\n", $headersTargetMsg->text() );

			// Translate regular headers
			$wikitext = self::transform( $wikitext, $needles, $replacements, 'header' );

			// Translate headers in transclusions
			$wikitext = self::transform( $wikitext, $needles, $replacements, 'header-transclusion' );
		}

		// Translate regular links
		self::$linkTranslations = TranslationManagerStatus::getSuggestionsByIds(
			$targetLanguage, 'title', $linkPageIds
		);
		$needles = array_keys( self::$linkTranslations );
		$replacements = array_values( self::$linkTranslations );
		// Do title transformation in links
		$wikitext = self::transform( $wikitext, $needles, $replacements, 'title' );
		// Do title transformation in transclusions
		$wikitext = self::transform( $wikitext, $needles, $replacements, 'title-transclusion' );

		// Add metadata as comment
		$wikitext = self::getArticleMetadata( $title ) . $wikitext;
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
	 * Return the article's metadata as a template
	 *
	 * @param Title $title
	 *
	 * @return string
	 */
	private static function getArticleMetadata( Title $title ) {
		$hebrewName = $title->getFullText();
		$metadata = '{{נתוני תרגום' . PHP_EOL;
		$metadata .= '|שם=' . $hebrewName . PHP_EOL;
		$targetName = self::$linkTranslations[ $hebrewName ] ?? null;
		$metadata .= '|שם מתורגם=' . $targetName . PHP_EOL;

		$wikipage = WikiPage::newFromID( $title->getArticleID() );
		$lastmod = $wikipage->getTimestamp();
		$metadata .= '|תאריך עדכון מקור=' . $lastmod . PHP_EOL;
		$metadata .= '|rev_id=' . $wikipage->getLatest() . PHP_EOL;
		$metadata .= '}}' . PHP_EOL;

		return $metadata;
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

	/**
	 * This function expects the text we previously exported; it then tries to find the revision ID
	 * inside that text.
	 *
	 * It should be kept compatible with the way getArticleMetadata() adds that revision ID, and
	 * hopefully backwards-compatible as well.
	 *
	 * @param string $text
	 *
	 * @return int|null
	 */
	public static function getRevIdFromText( string $text ) {
		$matches = [];
		preg_match( '/rev_id\s*=\s*(\d+)/', $text, $matches  );

		return isset( $matches[1] ) ? (int)$matches[1] : null;
	}

}

