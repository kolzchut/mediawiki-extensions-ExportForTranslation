<?php
/**
 * Hooks for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\ExportForTranslation;

use MediaWiki\Config\Config;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\MediaWikiServices;
use SkinTemplate;
use SpecialPage;

class Hooks implements SkinTemplateNavigation__UniversalHook {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param SkinTemplate &$sktemplate
	 * @param array &$links
	 *
	 * @return void
	 * @throws \MWException
	 */
	public static function addButtonToToolbar( SkinTemplate &$sktemplate, array &$links ) {
		$title = $sktemplate->getRelevantTitle();
		$exportPage = SpecialPage::getTitleFor(
			'ExportForTranslation', $title->getFullText()
		);
		$links[ 'actions' ][ 'exportfortranslation' ] = [
			'text' => $sktemplate->msg( 'exportfortranslation-btn' )->text(),
			'href' => $exportPage->getLocalURL()
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$exportForTranslationNamespaces = $this->config->get( 'ExportForTranslationNamespaces' );

		$title = $sktemplate->getRelevantTitle();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if (
			is_array( $exportForTranslationNamespaces ) &&
			in_array( $title->getNamespace(), $exportForTranslationNamespaces ) &&
			$title->exists() &&
			$permissionManager->userHasRight( $sktemplate->getUser(), 'export-for-translation' )
		) {
			self::addButtonToToolbar( $sktemplate, $links );
		}
	}
}
