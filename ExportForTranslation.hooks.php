<?php
/**
 * Hooks for ExportForTranslation extension
 *
 * @file
 * @ingroup Extensions
 */

class ExportForTranslationHooks {
	public static function onSkinTemplateNavigation( SkinTemplate &$sktemplate, array &$links ) {
		global $wgExportForTranslationNamespaces;

		$title = $sktemplate->getRelevantTitle();
		if (
			is_array( $wgExportForTranslationNamespaces ) &&
			in_array( $title->getNamespace(), $wgExportForTranslationNamespaces ) &&
			$title->exists() &&
			$sktemplate->getUser()->isAllowed( 'export-for-translation' )
		) {
			self::addButtonToToolbar( $sktemplate, $links );
		}

		return true;
	}

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

