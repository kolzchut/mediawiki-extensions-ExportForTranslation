<?php

namespace ExportForTranslation;

use Title;
use ApiBase;
use ApiPageSet;
use ApiContinuationManager;
use TranslationManager\TranslationManagerStatus;

class ApiExportForTranslation extends ApiBase {
	/**
	 * @var ApiPageSet|null
	 */
	private $mPageSet = null;
	private $params;

	/** @var Title[] */
	private $titles;

	private function getPageSet() {
		if ( $this->mPageSet === null ) {
			$this->mPageSet = new ApiPageSet( $this );
		}
		return $this->mPageSet;
	}

	public function execute() {
		$this->checkUserRightsAny( 'export-for-translation' );

		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$continuationManager = new ApiContinuationManager( $this, [], [] );
		$this->setContinuationManager( $continuationManager );

		$pageSet = $this->getPageSet();
		$pageSet->execute();
		$titles = $pageSet->getGoodTitles(); // page_id => Title object
		$missingTitles = $pageSet->getMissingTitles(); // page_id => Title object

		if ( !count( $titles ) && ( !count( $missingTitles ) ) ) {
			$result->addValue( null, 'pages', (object)[] );
			$this->setContinuationManager( null );
			$continuationManager->setContinuationIntoResult( $this->getResult() );
			return;
		}

		$resp = [];

		foreach ( $titles as $titleId => $title ) {
			$resp[ $titleId ] = Export::exportWithMetadata( $title, $params['lang'] );
		}

		$result->addValue( null, 'pages', (object)$resp );

		$this->setContinuationManager( null );
		$continuationManager->setContinuationIntoResult( $this->getResult() );
	}

	public function getAllowedParams() {
		global $wgTranslationManagerValidLanguages;
		return [
			'lang' => [
				ApiBase::PARAM_TYPE => $wgTranslationManagerValidLanguages,
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false,
			],
			'titles' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => true
			]
		];
	}

	public function getParamDescription() {
		return [
			'titles' => 'The titles to export',
			'lang' => 'the target language code'
		];
	}

	public function getDescription() {
		return [
			'API module for exporting an article for translation.'
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'api.php?action=exportfortranslation&lang=he&titles=A_PAGENAME'
				=> 'ext-exportfortranslation-apihelp-example-export-page',
			'api.php?action=exportfortranslation&lang=he&titles=A_PAGENAME|B_PAGENAME'
				=> 'ext-exportfortranslation-apihelp-example-export-pages',
		];
	}

}
