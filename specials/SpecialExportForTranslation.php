<?php
/**
 * SpecialPage for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialExportForTranslation extends FormSpecialPage {
	public function __construct() {
		parent::__construct( 'ExportForTranslation', 'export-for-translation' );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {
		if ( $sub !== null && !$this->getRequest()->wasPosted() ) {
			// This is a GET request to export a page. Check title validity:
			$title =  Title::newFromText( $sub );
			if ( $title->exists() ) {
				$this->sendFile( $title );
				return;
			}
		}
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'exportfortranslation-special-title' ) );

		parent::execute( $sub );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'export-submit' );
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @param array $formData
	 *
	 * @return bool|Status
	 */
	public function onSubmit( array $formData ) {
		// Note: validation of title existance is already done as part of HTMLTitleTextField
		$this->sendFile( $formData['title'] );

		return true;
	}

	/**
	 * @param Title|string $pageName
	 *
	 * @return bool
	 */
	protected function sendFile( $pageName ) {
		global $wgExportForTranslationValidLanguages;
		$request = $this->getRequest();
		$response = $request->response();
		$this->getOutput()->disable();
		$language = $request->getVal( 'language' );

		if ( empty( $language ) || !in_array( $language, $wgExportForTranslationValidLanguages ) ) {
			throw new MWException( 'Invalid target language for translation export!' );
		}

		$title = ( $pageName instanceof Title ) ? $pageName : Title::newFromText( $pageName );
		$wikitext = ExportForTranslation::export( $title->getFullText(), $language );
		$filename = $title->getDBkey() . '-' . wfTimestampNow() . '.txt';
		$filename_encoded = rawurlencode( $filename );

		$response->header( "Content-type: text/plain; charset=utf-8" );
		$response->header( "X-Robots-Tag: noindex,nofollow" );
		$response->header( "Content-disposition: attachment;filename={$filename_encoded};filename*=UTF-8''{$filename_encoded}" );
		$response->header( 'Cache-Control: no-cache, no-store, max-age=0, must-revalidate' );
		$response->header( 'Pragma: no-cache' );
		$response->header( 'Content-Length: ' . strlen( $wikitext ) );
		$response->header( 'Connection: close' );

		echo $wikitext;
		return true;
	}

	protected function getGroupName() {
		return 'pagetools';
	}

	protected function getMessagePrefix() {
		return 'exportfortranslation-special';
	}

	protected function getFormFields() {
		global $wgExportForTranslationValidLanguages;
		$languages = [];
		foreach ( $wgExportForTranslationValidLanguages as $lang ) {
			$langName = Language::fetchLanguageName( $lang );
			$languages[ $langName ] = $lang;
		}
		$formDescriptor = [
			'title' => [
				'type' => 'title',
				'label-message' => 'exportform-field-title',
				'placeholder' => $this->msg( 'exportform-field-title-placeholder' )->text(),
				'required' => true,
				'exists' => true
			],
			'language' => [
				'type' => 'select',
				'label-message' => 'exportform-field-language',
				'required' => true,
				'options' => $languages
			]
		];

		return $formDescriptor;
	}
}
