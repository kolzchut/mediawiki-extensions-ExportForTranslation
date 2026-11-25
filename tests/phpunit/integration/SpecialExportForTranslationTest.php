<?php

namespace MediaWiki\Extension\ExportForTranslation\Tests\Integration;

use MediaWiki\Extension\ExportForTranslation\Exporter;
use MediaWiki\Extension\ExportForTranslation\SpecialExportForTranslation;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use PermissionsError;
use SpecialPageTestBase;
use Title;

/**
 * The export link (a GET with the page as subpage) must enforce the same
 * restriction as the form, and never send a page its requester cannot read.
 *
 * @group Database
 * @covers \MediaWiki\Extension\ExportForTranslation\SpecialExportForTranslation
 * @covers \MediaWiki\Extension\ExportForTranslation\Exporter::export
 */
class SpecialExportForTranslationTest extends SpecialPageTestBase {

	private const EXPORTED_TEXT = 'exported wikitext for the test';

	/** @var Exporter|null A stand-in for the export service; null uses the real one */
	private ?Exporter $exporter = null;

	/** @inheritDoc */
	protected function newSpecialPage() {
		return new SpecialExportForTranslation(
			$this->exporter ?? $this->getServiceContainer()->getService( 'ExportForTranslation.Exporter' )
		);
	}

	/**
	 * An Exporter that returns a fixed text, so the special page can be tested on its own
	 *
	 * @param bool $expectCall
	 * @return Exporter
	 */
	private function stubExporter( bool $expectCall ): Exporter {
		$exporter = $this->createMock( Exporter::class );
		$exporter->expects( $expectCall ? $this->once() : $this->never() )
			->method( 'export' )
			->with( $this->isInstanceOf( Title::class ), null, 'ar', $this->isInstanceOf( Authority::class ) )
			->willReturn( self::EXPORTED_TEXT );
		return $exporter;
	}

	/**
	 * @param string $subPage
	 * @param Authority $performer
	 * @return array [ string $html, \MediaWiki\Request\FauxResponse $response ]
	 */
	private function executeGet( string $subPage, Authority $performer ): array {
		return $this->executeSpecialPage(
			$subPage,
			new FauxRequest( [ 'language' => 'ar' ], false ),
			null,
			$performer
		);
	}

	private function translator(): Authority {
		return $this->getTestUser( [ 'translator' ] )->getAuthority();
	}

	public function testAnonymousGetIsRefusedAndSendsNoFile() {
		$page = $this->getExistingTestPage( 'ExportForTranslation anonymous test' );
		$this->exporter = $this->stubExporter( false );

		// The stub's never() above asserts that no file is produced
		$this->expectException( PermissionsError::class );
		$this->executeGet( $page->getTitle()->getPrefixedText(), $this->getServiceContainer()
			->getUserFactory()->newAnonymous()
		);
	}

	public function testUserWithoutTheRightIsRefused() {
		$page = $this->getExistingTestPage( 'ExportForTranslation plain user test' );
		$this->exporter = $this->stubExporter( false );

		$this->expectException( PermissionsError::class );
		$this->executeGet( $page->getTitle()->getPrefixedText(), $this->getTestUser()->getAuthority() );
	}

	public function testUserWithTheRightGetsTheFile() {
		$page = $this->getExistingTestPage( 'ExportForTranslation translator test' );
		$this->exporter = $this->stubExporter( true );

		[ $body, $response ] = $this->executeGet( $page->getTitle()->getPrefixedText(), $this->translator() );

		// The output page is disabled, so what the executor captured is the file itself
		$this->assertSame( self::EXPORTED_TEXT, $body );
		$this->assertStringStartsWith( 'text/plain', $response->getHeader( 'Content-Type' ) );
		$this->assertStringStartsWith( 'attachment', $response->getHeader( 'Content-Disposition' ) );
	}

	public function testPageTheUserCannotReadIsRefused() {
		$title = Title::makeTitle( NS_PROJECT, 'ExportForTranslation restricted test' );
		$this->getExistingTestPage( $title );
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

		// The real export service: it must refuse on its own, whoever calls it
		$this->expectException( PermissionsError::class );
		$this->executeGet( $title->getPrefixedText(), $this->translator() );
	}
}
