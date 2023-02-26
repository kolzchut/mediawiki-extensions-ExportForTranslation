<?php
/**
 * SpecialPage for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

namespace ExportForTranslation;

use FormSpecialPage;
use HTMLForm;
use Title;
use TranslationManager\TranslationManagerStatus;

class SpecialExportForTranslation extends FormSpecialPage {
	public function __construct() {
		parent::__construct( 'ExportForTranslation', 'export-for-translation' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		if ( $par !== null && !$this->getRequest()->wasPosted() ) {
			// This is a GET request to export a page. Check title validity:
			$title = Title::newFromText( $par );
			if ( $title->exists() ) {
				$this->sendFile( $title );
				return;
			}
		}
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'exportfortranslation-special-title' ) );

		parent::execute( $par );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'export-submit' );
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'ooui';
	}

	/** @inheritDoc */
	public function onSubmit( array $data ): bool {
		// Note: validation of title existance is already done as part of HTMLTitleTextField
		$this->sendFile( $data['title'] );

		return true;
	}

	/**
	 * @param Title|string $pageName
	 *
	 * @return bool
	 * @throws \MWException
	 */
	protected function sendFile( $pageName ): bool {
		$request = $this->getRequest();
		$response = $request->response();
		$this->getOutput()->disable();

		$language = $request->getVal( 'language',
			$this->getUser()->getOption( 'translationmanager-language' )
		);
		if ( !TranslationManagerStatus::isValidLanguage( $language ) ) {
			throw new \MWException( 'Invalid target language for translation export!' );
		}

		$title = ( $pageName instanceof Title ) ? $pageName : Title::newFromText( $pageName );
		$wikitext = Exporter::export( $title, null, $language );
		$filename = $title->getDBkey() . '-' . $language . '-' . wfTimestampNow() . '.txt';
		$filenameEncoded = rawurlencode( $filename );

		$response->header( "Content-type: text/plain; charset=utf-8" );
		$response->header( "X-Robots-Tag: noindex,nofollow" );
		$response->header(
			"Content-disposition: attachment;filename={$filenameEncoded};filename*=UTF-8''{$filenameEncoded}"
		);
		$response->header( 'Cache-Control: no-cache, no-store, max-age=0, must-revalidate' );
		$response->header( 'Pragma: no-cache' );
		$response->header( 'Content-Length: ' . strlen( $wikitext ) );
		$response->header( 'Connection: close' );

		echo $wikitext;
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'pagetools';
	}

	/** @inheritDoc */
	protected function getMessagePrefix(): string {
		return 'exportfortranslation-special';
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		$languageOptions = TranslationManagerStatus::getLanguagesForSelectField();
		$formDescriptor = [
			'title' => [
				'type' => 'title',
				'label-message' => 'exportform-field-title',
				'placeholder' => $this->msg( 'exportform-field-title-placeholder' )->text(),
				'required' => true,
				'exists' => true
			],
			'language' => [
				'name' => 'language',
				'type' => 'select',
				'label-message' => 'exportform-field-language',
				'required' => true,
				'options' => $languageOptions
			]
		];

		return $formDescriptor;
	}
}
