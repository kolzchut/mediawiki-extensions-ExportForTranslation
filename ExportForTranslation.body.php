<?php

/**
 * @Note We do not worry about translating "header transclusions" for non-existing articles -
 *       that is the responsibility of the translation manager.
 */

namespace ExportForTranslation;

use TranslationManager\TranslationManagerStatus;
use Title;
use WikiPage;
use ContentHandler;

class Export {

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

	private static $headerLines, $titleLines, $interlanguageLines, $language;

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
	 * B/C function - transformText() now does the real work
	 *
	 * @param int|string $pageName
	 *
	 * @return null|string
	 */
	public static function export( $pageName, $language ) {
		if ( $pageName instanceof Title ) {
			$title = $pageName;
		} elseif ( is_int( $pageName ) ) {
			$title = Title::newFromID( $pageName );
		} else {
			$title = Title::newFromText( $pageName );
		}
		if ( !$title->exists() ) {
			return null;
		}
		return self::transformText( $title, $language );
	}

	/**
	 * Do a regular export and add metadata
	 *
	 * @param Title $title
	 *
	 * @return null|array
	 */
	public static function exportWithMetadata( $title, $language ) {
		$result = self::getMetadata( $title, $language );
		$result['text'] = self::transformText( $title, $language );

		return $result;
	}

	/**
	 * @param Title $title
	 * @param string $language
	 *
	 * @return array
	 */
	public static function getMetadata( $title, $language ) {
		$statusObject = new TranslationManagerStatus( $title->getArticleID(), $language );
		$wikipage = WikiPage::factory( $title );
		$metadata = [
			'originalTitle' => $title->getFullText(),
			'targetLanguage' => $language,
			'suggestedTargetTitle' => $statusObject->getSuggestedTranslation(),
			'existingTargetTitle' => $statusObject->getActualTranslation(),
			'articleType' => $statusObject->getArticleType(),
			'revision' => $wikipage->getLatest(),
			'lastUpdated' => $wikipage->getTimestamp()
		];

		return $metadata;

	}



	/**
	 * Load the content of a given Title, do our misc transformations
	 *
	 * @param Title $title
	 *
	 * @return null|string
	 */
	private static function transformText( $title, $language ) {
		$wikitext = self::getPageContent( $title );
		self::$language = $language;
		self::loadData();

		// Prepare headers transformation.
		$wikitext = self::doTransformation( $wikitext, self::$headerLines, 'header' );
		// Prepare headers transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, self::$headerLines, 'header-transclusion' );
		// Prepare titles transformation in links
		$wikitext = self::doTransformation( $wikitext, self::$titleLines, 'title' );
		// Prepare titles transformation in transclusions
		$wikitext = self::doTransformation( $wikitext, self::$titleLines, 'title-transclusion' );

		$wikitext .= PHP_EOL . self::makeLanguageLinkToSource( $title );

		return $wikitext;

	}

	/**
	 * Load data from on-wiki messages into class members
	 */
	private static function loadData() {
		self::$headerLines = explode( "\n",
			wfMessage( 'exportfortranslation-headers-list' )->inLanguage( self::$language )->text()
		);

		self::$titleLines = self::getAllTranslationSuggestions();
		self::$interlanguageLines = self::getAllInterlanguageLinks();

		// Merge interlanguage lines with the suggested titles
		self::$titleLines = array_merge( self::$interlanguageLines, self::$titleLines );
	}

	private static function getAllTranslationSuggestions() {
		static $translationSuggestions;
		$rows = TranslationManagerStatus::getAllSuggestions( self::$language );
		foreach ( $rows as $row ) {
			$translationSuggestions[] = strtr( $row->page_title, '_', ' ' );
			$translationSuggestions[] = $row->suggested_name;
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
			'll_lang' => self::$language,
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
	 * Do replacements of Hebrew text into their targat language
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

