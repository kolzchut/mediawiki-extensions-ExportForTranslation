<?php

/**
 * @Note We do not worry about translating "header transclusions" for non-existing articles -
 *       that is the responsibility of the translation manager. All titles in the project list
 *       now have their "dependencies" (titles it transcludes from) marked
 */

namespace MediaWiki\Extension\ExportForTranslation;

use Config;
use ConfigException;
use ErrorPageError;
use Language;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserOptionsLookup;
use MWException;
use RequestContext;
use TextContent;
use Title;
use TranslationManager\StatusItem;
use WikiPage;

class Exporter {
	private const EXTENSION_NAME = 'ExportForTranslation';

	/** @var string[] */
	private const TEXT_TEMPLATES = [
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

	private Config $config;
	private WikiPageFactory $wikiPageFactory;
	private RevisionStore $revisionStore;
	private Language $contentLanguage;
	private UserOptionsLookup $userOptionsLookup;
	private HeaderTranslationsLoader $headerTranslationsLoader;

	public function __construct(
		Config $config,
		WikiPageFactory $wikiPageFactory,
		RevisionStore $revisionStore,
		Language $contentLanguage,
		UserOptionsLookup $userOptionsLookup,
		HeaderTranslationsLoader $headerTranslationsLoader
	) {
		$this->config = $config;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->revisionStore = $revisionStore;
		$this->contentLanguage = $contentLanguage;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->headerTranslationsLoader = $headerTranslationsLoader;
	}

	/**
	 * @return string|null
	 * @throws MWException
	 */
	public function getLanguagePreference(): ?string {
		$user = RequestContext::getMain()->getUser();
		$language = $this->userOptionsLookup->getOption( $user, 'translationmanager-language' );
		$language = $language ?? $this->getConfigVar( 'ExportForTranslationDefaultLanguage' );
		if ( !StatusItem::isValidLanguage( $language ) ) {
			throw new MWException( "Invalid target language '$language' for translation export!" );
		}

		return $language;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	protected function getConfigVar( string $name ) {
		try {
			$value = $this->config->get( $name );
		} catch ( ConfigException $e ) {
			$value = null;
		}

		return $value;
	}

	/**
	 * @param array $translationArray
	 * @param string $langCode
	 * @return array
	 */
	private function getHeadersForLanguage( array $translationArray, string $langCode ): array {
		$result = [];
		foreach ( $translationArray as $key => $translations ) {
			if ( isset( $translations[$langCode] ) ) {
				$result[] = $translations[$langCode];
			}
		}
		return $result;
	}

	/**
	 * Load the content of a given page (by name), do our misc. transformations & add metadata
	 *
	 * @param Title $title
	 * @param int|null $rev_id
	 * @param string|null $lang ISO 639-1 language code
	 *
	 * @return null|string
	 * @throws MWException
	 * @throws ErrorPageError if the requested revision, or any revision of the page, does not exist,
	 *  if the requested revision belongs to a different page, or if the revision's text is hidden
	 *  or is not text
	 */
	public function export( Title $title, ?int $rev_id = null, ?string $lang = null ): ?string {
		$targetLanguage = $lang ?? $this->getLanguagePreference();
		$revision = $rev_id
			? $this->revisionStore->getRevisionById( $rev_id )
			: $this->revisionStore->getRevisionByTitle( $title );
		if ( $revision === null && $rev_id ) {
			throw new ErrorPageError( 'errorpagetitle', 'exportfortranslation-error-revision-not-found',
				[ $rev_id ]
			);
		}
		if ( $revision !== null && $rev_id && $revision->getPageId() !== $title->getArticleID() ) {
			throw new ErrorPageError( 'errorpagetitle', 'exportfortranslation-error-revision-wrong-page',
				[ $rev_id, $title->getPrefixedText() ]
			);
		}
		if ( $revision === null ) {
			throw new ErrorPageError( 'errorpagetitle', 'exportfortranslation-error-page-has-no-revision',
				[ $title->getPrefixedText() ]
			);
		}
		// Hidden (revision-deleted) text is never exported: translators are not
		// necessarily allowed to see it.
		if ( !$revision->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
			throw new ErrorPageError( 'errorpagetitle', 'exportfortranslation-error-revision-text-hidden',
				[ $revision->getId(), $title->getPrefixedText() ]
			);
		}
		$content = $revision->getContent( SlotRecord::MAIN );
		if ( !( $content instanceof TextContent ) ) {
			throw new ErrorPageError( 'errorpagetitle', 'exportfortranslation-error-revision-not-text',
				[ $revision->getId(), $title->getPrefixedText() ]
			);
		}
		$wikitext = $content->getText();

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		$linkTitles = $this->getPageLinks( $wikiPage );
		$linkPageIds = array_map(
			static function ( Title $t ) {
				return $t->getArticleID();
			},
			$linkTitles
		);

		// Also get the translation for the current page
		$linkPageIds[] = $wikiPage->getId();

		$sourceLanguageCode = $this->contentLanguage->getCode();

		// Translate headers
		try {
			$headerTranslations = $this->headerTranslationsLoader->load();
			$needles = $this->getHeadersForLanguage( $headerTranslations, $sourceLanguageCode );
			$replacements = $this->getHeadersForLanguage( $headerTranslations, $targetLanguage );

			if ( !empty( $needles ) && !empty( $replacements ) ) {
				// Translate regular headers
				$wikitext = $this->transform( $wikitext, $needles, $replacements, 'header' );

				// Translate headers in transclusions
				$wikitext = $this->transform( $wikitext, $needles, $replacements, 'header-transclusion' );
			}
		} catch ( MWException $e ) {
			// Log the error but continue without header translations
			wfLogWarning( 'Failed to load or validate translation headers: ' . $e->getMessage() );
		}

		// Translate regular links
		$linkTranslations = StatusItem::getSuggestionsByIds(
			$targetLanguage, 'title', $linkPageIds
		);
		$needles = array_keys( $linkTranslations );
		$replacements = array_values( $linkTranslations );
		// Do title transformation in links
		$wikitext = $this->transform( $wikitext, $needles, $replacements, 'title' );
		// Do title transformation in transclusions
		$wikitext = $this->transform( $wikitext, $needles, $replacements, 'title-transclusion' );

		// Add metadata as comment
		$wikitext = $this->getArticleMetadata( $title, $linkTranslations ) . $wikitext;
		$wikitext .= PHP_EOL . self::makeLanguageLinkToSource( $title );

		return $wikitext;
	}

	/**
	 * @param WikiPage $wikiPage
	 *
	 * @return Title[]
	 */
	private function getPageLinks( WikiPage $wikiPage ): array {
		$pageLinks = [];
		$pageCategories = [];

		$title = $wikiPage->getTitle();

		if ( $title->exists() ) {
			$pageLinks = $title->getLinksFrom();
			// Returns TitleArray
			$pageCategories = iterator_to_array( $wikiPage->getCategories() );
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
	private static function makeLanguageLinkToSource( Title $title ): string {
		$hebrewName = $title->getFullText();
		return '[[he:' . $hebrewName . ']]' . PHP_EOL;
	}

	/**
	 * Return the article's metadata as a template
	 *
	 * @param Title $title
	 * @param string[] $linkTranslations
	 *
	 * @return string
	 */
	private function getArticleMetadata( Title $title, array $linkTranslations ): string {
		$hebrewName = $title->getFullText();
		$metadata = '{{נתוני תרגום' . PHP_EOL;
		$metadata .= '|שם=' . $hebrewName . PHP_EOL;
		$targetName = $linkTranslations[ $hebrewName ] ?? null;
		$metadata .= '|שם מתורגם=' . $targetName . PHP_EOL;

		$wikipage = $this->wikiPageFactory->newFromID( $title->getArticleID() );
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
	private static function transform( string $wikitext, array $needles, array $replacements, string $type ): string {
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
	private static function getTemplateForType( string $type ): string {
		return self::TEXT_TEMPLATES[ $type ];
	}

	/**
	 * @param string &$needle
	 * @param int|string $key
	 * @param string $type
	 *
	 * @return bool
	 */
	private static function doParamReplacement( string &$needle, $key, string $type ): bool {
		$template = self::getTemplateForType( $type );

		// Only for search strings
		if ( !str_contains( $type, 'replacement' ) ) {
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
	public static function getRevIdFromText( string $text ): ?int {
		$matches = [];
		preg_match( '/rev_id\s*=\s*(\d+)/', $text, $matches );

		return isset( $matches[1] ) ? (int)$matches[1] : null;
	}

}
