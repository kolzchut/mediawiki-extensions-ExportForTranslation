<?php

namespace ExportForTranslation;

use FormatJson;
use MWException;
use Title;
use WikiPage;

class HeadersJsonValidator {
	/** @var string Path to the JSON schema file */
	private const SCHEMA_PATH = __DIR__ . '/HeaderTranslations.schema.json';

	/**
	 * Validates the JSON structure of the translation headers using JSON Schema
	 *
	 * @param array $data The decoded JSON data
	 * @return array An array with 'valid' => bool and 'errors' => array of error messages
	 */
	public static function validate( array $data ): array {
		$result = [
			'valid' => true,
			'errors' => []
		];

		// Check if data is empty
		if ( empty( $data ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Translation headers JSON is empty';
			return $result;
		}

		// Use JsonSchema validation if available
		if ( class_exists( '\JsonSchema\Validator' ) ) {
			return self::validateWithJsonSchema( $data );
		} else {
			// Fallback to manual validation
			return self::validateManually( $data );
		}
	}

	/**
	 * Validate using JsonSchema library
	 *
	 * @param array $data The data to validate
	 * @return array Validation result
	 */
	private static function validateWithJsonSchema( array $data ): array {
		$result = [
			'valid' => true,
			'errors' => []
		];

		try {
			// Check if JsonSchema\Validator exists and is usable
			if ( !class_exists( '\JsonSchema\Validator' ) ) {
				throw new \Exception( 'JsonSchema Validator class not found' );
			}

			// Create validator
			$validator = new \JsonSchema\Validator();

			// Load schema
			$schema = self::getJsonSchema();

			// Convert PHP array to stdClass for validation
			$dataObject = json_decode( json_encode( $data ) );

			// Validate
			$validator->validate( $dataObject, $schema );

			if ( !$validator->isValid() ) {
				$result['valid'] = false;
				foreach ( $validator->getErrors() as $error ) {
					$result['errors'][] = sprintf(
						"[%s] %s",
						$error['property'],
						$error['message']
					);
				}
			}
		} catch ( \Exception $e ) {
			$result['valid'] = false;
			$result['errors'][] = 'Schema validation error: ' . $e->getMessage();
			// Fallback to manual validation
			$manualResult = self::validateManually( $data );
			$result['errors'] = array_merge( $result['errors'], $manualResult['errors'] );
		}

		return $result;
	}

	/**
	 * Get the JSON schema either from file or inline
	 *
	 * @return \stdClass The schema object
	 */
	private static function getJsonSchema() {
		// Try to load from file first
		if ( file_exists( self::SCHEMA_PATH ) ) {
			$schemaContent = file_get_contents( self::SCHEMA_PATH );
			if ( $schemaContent ) {
				$schema = json_decode( $schemaContent );
				if ( $schema ) {
					return $schema;
				}
			}
		}

		// Fallback to inline schema
		return json_decode( json_encode( [
			'$schema' => 'http://json-schema.org/draft-07/schema#',
			'type' => 'object',
			'description' => 'Schema for ExportForTranslation headers translations',
			'additionalProperties' => [
				'type' => 'object',
				'description' => 'Translation mappings for a header',
				'patternProperties' => [
					'^[a-z]{2}(-[a-z]{2})?$' => [
						'type' => 'string',
						'minLength' => 1,
						'description' => 'Translated header text in a specific language'
					]
				],
				'additionalProperties' => false,
				'minProperties' => 1
			],
			'minProperties' => 1
		] ) );
	}

	/**
	 * Manual validation fallback
	 *
	 * @param array $data The data to validate
	 * @return array Validation result
	 */
	private static function validateManually( array $data ): array {
		$result = [
			'valid' => true,
			'errors' => []
		];

		// Check basic structure: each key should contain language mappings
		foreach ( $data as $headerKey => $translations ) {
			if ( !is_array( $translations ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Header '$headerKey' does not contain a translations array";
				continue;
			}

			// Check that each translation is a string and non-empty
			foreach ( $translations as $langCode => $translation ) {
				// Validate language code format
				if ( !preg_match( '/^[a-z]{2}(-[a-z]{2})?$/', $langCode ) ) {
					$result['valid'] = false;
					$result['errors'][] = "Invalid language code '$langCode' for header '$headerKey'";
				}

				if ( !is_string( $translation ) ) {
					$result['valid'] = false;
					$result['errors'][] = "Translation for '$headerKey' in language '$langCode' is not a string";
				} elseif ( trim( $translation ) === '' ) {
					$result['valid'] = false;
					$result['errors'][] = "Translation for '$headerKey' in language '$langCode' is empty";
				}
			}

			// Check if at least one language is defined
			if ( empty( $translations ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Header '$headerKey' has no language translations";
			}
		}

		return $result;
	}

	/**
	 * Load and validate the headers JSON from the MediaWiki page
	 *
	 * @param string $pageName The MediaWiki page containing the JSON
	 * @return array The validated JSON data
	 * @throws MWException If the JSON is invalid
	 */
	public static function loadAndValidate( string $pageName = 'MediaWiki:ExportForTranslationHeaders.json' ): array {
		$title = Title::newFromText( $pageName );

		if ( !$title || !$title->exists() ) {
			throw new MWException( "Headers JSON page '$pageName' does not exist" );
		}

		$page = WikiPage::factory( $title );
		$content = $page->getContent()->getNativeData();

		// Check if JSON is valid
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

	/**
	 * Validate content on save
	 *
	 * @param string $content The content to validate
	 * @return array An array with 'valid' => bool and 'errors' => array of error messages
	 */
	public static function validateContent( string $content ): array {
		// Check if JSON is valid
		$status = FormatJson::parse( $content, FormatJson::FORCE_ASSOC );
		if ( !$status->isGood() ) {
			return [
				'valid' => false,
				'errors' => [ "Invalid JSON: " . $status->getWikiText() ]
			];
		}

		$data = $status->getValue();
		return self::validate( $data );
	}

}
