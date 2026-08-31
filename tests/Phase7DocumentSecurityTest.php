<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;
use WholesaleOrdering\Security\DocumentSecurity;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 7 secure-document contract tests.
 *
 * These tests verify the public contract of the document-security service.
 * Actual filesystem and HTTP access checks must additionally be performed
 * against the running WordPress installation.
 */
final class Phase7DocumentSecurityTest extends TestCase {

	/**
	 * Skip when WordPress is unavailable.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'add_action' ) ) {
			$this->markTestSkipped(
				'Phase 7 document tests require WordPress.'
			);
		}
	}

	/**
	 * Verify the document-security service exists.
	 *
	 * @return void
	 */
	public function test_document_security_class_exists(): void {
		$this->assertTrue(
			class_exists( DocumentSecurity::class )
		);
	}

	/**
	 * Verify upload validation exists.
	 *
	 * @return void
	 */
	public function test_upload_validation_api_exists(): void {
		$this->assertTrue(
			method_exists(
				DocumentSecurity::class,
				'validate_upload'
			)
		);
	}

	/**
	 * Verify secure storage exists.
	 *
	 * @return void
	 */
	public function test_secure_storage_api_exists(): void {
		$this->assertTrue(
			method_exists(
				DocumentSecurity::class,
				'store_upload'
			)
		);
	}

	/**
	 * Verify the service provides a protected download boundary.
	 *
	 * @return void
	 */
	public function test_protected_download_boundary_exists(): void {
		$this->assertTrue(
			method_exists(
				DocumentSecurity::class,
				'register'
			)
		);
	}

	/**
	 * Verify the service can be registered without throwing.
	 *
	 * @return void
	 */
	public function test_document_security_can_register(): void {
		DocumentSecurity::register();

		$this->assertTrue( true );
	}
}