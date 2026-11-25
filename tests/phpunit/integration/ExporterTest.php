<?php

namespace MediaWiki\Extension\ExportForTranslation\Tests\Integration;

use MediaWiki\Extension\ExportForTranslation\Exporter;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use RequestContext;
use Title;

/**
 * The export service must refuse a page its requester cannot read, whoever calls it.
 *
 * @group Database
 * @covers \MediaWiki\Extension\ExportForTranslation\Exporter::export
 */
class ExporterTest extends MediaWikiIntegrationTestCase {

	private Title $restricted;

	protected function setUp(): void {
		parent::setUp();
		$this->restricted = Title::makeTitle( NS_PROJECT, 'ExportForTranslation restricted exporter test' );
		$this->getExistingTestPage( $this->restricted );
		// Restrict reading the namespace the way Lockdown's $wgNamespacePermissionLockdown does
		$this->setTemporaryHook( 'getUserPermissionsErrors',
			static function ( $hookTitle, $user, $action, &$result ) {
				if ( $action === 'read' && $hookTitle->getNamespace() === NS_PROJECT ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);
	}

	private function getExporter(): Exporter {
		return $this->getServiceContainer()->getService( 'ExportForTranslation.Exporter' );
	}

	public function testRefusesAPageTheGivenPerformerCannotRead() {
		$performer = $this->getTestUser( [ 'translator' ] )->getAuthority();

		$this->expectException( PermissionsError::class );
		$this->getExporter()->export( $this->restricted, null, 'ar', $performer );
	}

	public function testRefusesAPageTheRequestUserCannotReadWhenNoPerformerIsGiven() {
		// Callers that pass no performer (e.g. TranslationManager's word counter) are
		// checked against the user of the current request
		RequestContext::getMain()->setUser( $this->getTestUser( [ 'translator' ] )->getUser() );

		$this->expectException( PermissionsError::class );
		$this->getExporter()->export( $this->restricted, null, 'ar' );
	}
}
