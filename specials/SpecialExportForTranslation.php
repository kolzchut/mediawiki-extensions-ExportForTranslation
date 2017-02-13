<?php
/**
 * SpecialPage for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

class SpecialExportForTranslation extends FormSpecialPage {
	private $doExport;

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
		$this->doExport = false;
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
		if ( empty( $formData['title'] ) ) {
			return false;
		}

		$title = Title::newFromText( $formData['title'] );
		if ( !$title->exists() ) {
			return Status::newFatal( 'exportfortranslation-title-non-existant', $title->getFullText() );
		}

		$request = $this->getRequest();
		$response = $request->response();
		$this->getOutput()->disable();

		$response->header( "Content-type: text/plain; charset=utf-8" );
		$response->header( "X-Robots-Tag: noindex,nofollow" );

		$wikitext = ExportForTranslation::export( $title->getFullText() );
		$filename = $title->getDBkey() . '-' . wfTimestampNow() . '.txt';

		$response->header( "Content-disposition: attachment;filename={$filename}" );
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

	function getFormFields() {
		$formDescriptor = [
			'title' => [
				'type' => 'title',
				'label-message' => 'exportform-field-title',
				'placeholder' => $this->msg( 'exportform-field-title-placeholder' )->text(),
				'required' => true,

			]
		];

		return $formDescriptor;
	}
}
