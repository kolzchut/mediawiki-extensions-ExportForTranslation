<?php
/**
 * SpecialPage for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

namespace ExportForTranslation;

use FormSpecialPage;
use Title;
use HTMLForm;
use TranslationManager\TranslationManagerStatus;

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
	 * @return bool status
	 */
	public function onSubmit( array $formData ) {
		// Note: validation of title existance is already done automagically in HTMLTitleTextField
		$title =  Title::newFromText( $formData['title'] );
		$this->sendFile( $title );

		return true;
	}

	/**
	 * @param Title $title
	 *
	 * @return bool
	 */
	protected function sendFile( $title ) {
		$request = $this->getRequest();
		$response = $request->response();
		$this->getOutput()->disable();
		$language = $request->getVal( 'language',
			$this->getUser()->getOption( 'translationmanager-language' )
		);

		if ( !TranslationManagerStatus::isValidLanguage( $language ) ) {
			throw new MWException( 'Invalid target language for translation export!' );
		}

		$exportData = Export::exportWithMetadata( $title, $language );
		$metadata = self::formatMetadataForHumans( $exportData );
		$exportText = self::makeHtmlComment( $metadata ) . $exportData['text'];
		$filename        = $title->getDBkey() . '-' . $language . '-' . wfTimestampNow() . '.txt';
		$filenameEncoded = rawurlencode( $filename );

		$response->header( "Content-type: text/plain; charset=utf-8" );
		$response->header( "X-Robots-Tag: noindex,nofollow" );
		$response->header(
			"Content-disposition: attachment;filename={$filenameEncoded};filename*=UTF-8''{$filenameEncoded}"
		);
		$response->header( 'Cache-Control: no-cache, no-store, max-age=0, must-revalidate' );
		$response->header( 'Pragma: no-cache' );
		$response->header( 'Content-Length: ' . strlen( $exportText ) );
		$response->header( 'Connection: close' );

		echo $exportText;
		return true;
	}

	protected function getGroupName() {
		return 'pagetools';
	}

	protected function getMessagePrefix() {
		return 'exportfortranslation-special';
	}

	protected function getFormFields() {
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

	protected function formatMetadataForHumans( $data ) {
		$text[] = 'שם הערך בעברית: ' . $data['originalTitle'];
		$text[] = 'שפת יעד לתרגום: ' . \Language::fetchLanguageName( $data['targetLanguage'] );

		if ( isset( $data['existingTargetTitle'] ) ) {
			$text[] = 'קיים כבר תרגום: ' . $data[ 'existingTargetTitle' ];
		} elseif ( isset( $data['suggestedTargetTitle'] ) ) {
			$text[] = 'השם המוצע לתרגום: ' . $data[ 'suggestedTargetTitle' ];
		} else {
			$text[] = 'אין לערך תרגום קיים או שם מוצע לתרגום';
		}

		$text[] = 'תאריך עדכון אחרון של הערך: ' . $data['lastUpdated'];
		$text[] = 'Revision: ' . $data['revision'];

		$text = implode( PHP_EOL, $text );
		return $text;

	}

	protected static function makeHtmlComment( $text ) {
		return '<!--' . PHP_EOL . $text . PHP_EOL . '-->' . PHP_EOL;
	}
}
