<?php

namespace MediaWiki\Extension\ExportForTranslation;

use FormatJson;
use JsonSchema\Validator;
use MediaWiki\Page\WikiPageFactory;
use MWException;
use Title;

class HeaderTranslationsLoader {

	private WikiPageFactory $wikiPageFactory;

	public function __construct( WikiPageFactory $wikiPageFactory ) {
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Returns the JSON schema for the translation headers structure.
	 * Schema: { "headerKey": { "langCode": "translation string", ... }, ... }
	 */
	private static function getSchema(): object {
		return (object)[
			'type' => 'object',
			'minProperties' => 1,
			'additionalProperties' => (object)[
				'type' => 'object',
				'minProperties' => 1,
				'patternProperties' => (object)[
					'^[a-z]{2}(-[a-z]{2})?$' => (object)[
						'type' => 'string',
						'minLength' => 1,
					],
				],
				'additionalProperties' => false,
			],
		];
	}

	/**
	 * Validates the JSON structure of the translation headers against the schema.
	 *
	 * @param array $data The decoded JSON data
	 * @return array An array with 'valid' => bool and 'errors' => string[]
	 */
	private static function validate( array $data ): array {
		$validator = new Validator();
		// json-schema requires an object, not an array
		$dataObject = json_decode( json_encode( $data ) );
		$validator->validate( $dataObject, self::getSchema() );

		if ( $validator->isValid() ) {
			return [ 'valid' => true, 'errors' => [] ];
		}

		$errors = array_map(
			static function ( array $error ): string {
				$property = $error['property'] ? $error['property'] . ': ' : '';
				return $property . $error['message'];
			},
			$validator->getErrors()
		);

		return [ 'valid' => false, 'errors' => $errors ];
	}

	/**
	 * Load and validate the headers JSON from the MediaWiki page.
	 *
	 * @param string $pageName The MediaWiki page containing the JSON
	 * @return array The validated JSON data
	 * @throws MWException If the page does not exist, JSON is invalid, or structure is invalid
	 */
	public function load( string $pageName = 'MediaWiki:ExportForTranslationHeaders.json' ): array {
		$title = Title::newFromText( $pageName );

		if ( !$title || !$title->exists() ) {
			throw new MWException( "Headers JSON page '$pageName' does not exist" );
		}

		$page = $this->wikiPageFactory->newFromTitle( $title );
		$content = $page->getContent()->getNativeData();

		$status = FormatJson::parse( $content, FormatJson::FORCE_ASSOC );
		if ( !$status->isGood() ) {
			throw new MWException( "Invalid JSON in '$pageName': " . $status->getWikiText() );
		}

		$data = $status->getValue();
		$validationResult = self::validate( $data );

		if ( !$validationResult['valid'] ) {
			$errorMsg = implode( "; ", $validationResult['errors'] );
			throw new MWException( "Invalid headers structure in '$pageName': $errorMsg" );
		}

		return $data;
	}

}
