<?php

namespace MediaWiki\Extension\ExportForTranslation;

use MediaWiki\MediaWikiServices;

return [
	'ExportForTranslation.Exporter' => static function ( MediaWikiServices $services ): Exporter {
		return new Exporter(
			$services->getConfigFactory()->makeConfig( 'ExportForTranslation' ),
			$services->getWikiPageFactory(),
			$services->getRevisionStore(),
			$services->getContentLanguage(),
			$services->getUserOptionsLookup(),
			new HeaderTranslationsLoader( $services->getWikiPageFactory() )
		);
	},
];
