<?php
/**
 * Hooks for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

namespace ExportForTranslation;

use MediaWiki\MediaWikiServices;
use SkinTemplate;
use SpecialPage;

class Hooks {
	/**
	 * @param SkinTemplate &$sktemplate The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 */
	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		global $wgExportForTranslationNamespaces;

		$title = $sktemplate->getRelevantTitle();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if (
			is_array( $wgExportForTranslationNamespaces ) &&
			in_array( $title->getNamespace(), $wgExportForTranslationNamespaces ) &&
			$title->exists() &&
			$permissionManager->userHasRight( $sktemplate->getUser(), 'export-for-translation' )
		) {
			self::addButtonToToolbar( $sktemplate, $links );
		}
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
}
