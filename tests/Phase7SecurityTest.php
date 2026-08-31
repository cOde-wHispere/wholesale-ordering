<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;
use WholesaleOrdering\Audit\AuditLog;
use WholesaleOrdering\Security\DocumentSecurity;
use WholesaleOrdering\Security\PricingLeakageProtection;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 7 security integration-boundary tests.
 *
 * These tests verify that the security services exist and are registered
 * against the established application boundaries.
 *
 * Runtime authorization and price-leakage verification still requires the
 * manual six-state matrix against a running WordPress/WooCommerce site.
 */
final class Phase7SecurityTest extends TestCase {

	/**
	 * Skip when WordPress/WooCommerce is unavailable.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if (
			! function_exists( 'add_action' )
			|| ! function_exists( 'add_filter' )
			|| ! class_exists( '\WooCommerce' )
		) {
			$this->markTestSkipped(
				'Phase 7 integration tests require WordPress and WooCommerce.'
			);
		}
	}

	/**
	 * Verify Phase 7 services expose registration boundaries.
	 *
	 * @return void
	 */
	public function test_phase7_security_services_exist(): void {
		$this->assertTrue(
			class_exists( AuditLog::class )
		);

		$this->assertTrue(
			class_exists( DocumentSecurity::class )
		);

		$this->assertTrue(
			class_exists( PricingLeakageProtection::class )
		);
	}

	/**
	 * Verify the audit service registers its lifecycle hooks.
	 *
	 * @return void
	 */
	public function test_audit_log_registers_high_value_events(): void {
		AuditLog::register();

		$this->assertNotFalse(
			has_action(
				'wholesale_ordering_application_approved',
				array( AuditLog::class, 'record_application_approved' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'wholesale_ordering_application_rejected',
				array( AuditLog::class, 'record_application_rejected' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'wholesale_ordering_application_suspended',
				array( AuditLog::class, 'record_application_suspended' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'wholesale_ordering_application_reactivated',
				array( AuditLog::class, 'record_application_reactivated' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'woocommerce_order_refunded',
				array( AuditLog::class, 'record_refund' )
			)
		);
	}

	/**
	 * Verify permission-change auditing is registered.
	 *
	 * @return void
	 */
	public function test_permission_changes_are_audited(): void {
		AuditLog::register();

		$this->assertNotFalse(
			has_action(
				'set_user_role',
				array( AuditLog::class, 'record_role_change' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'add_user_role',
				array( AuditLog::class, 'record_role_added' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'remove_user_role',
				array( AuditLog::class, 'record_role_removed' )
			)
		);
	}

	/**
	 * Verify product price auditing is registered.
	 *
	 * @return void
	 */
	public function test_price_change_auditing_is_registered(): void {
		AuditLog::register();

		$this->assertNotFalse(
			has_action(
				'added_post_meta',
				array( AuditLog::class, 'record_price_meta_change' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'updated_post_meta',
				array( AuditLog::class, 'record_price_meta_change' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'deleted_post_meta',
				array( AuditLog::class, 'record_price_meta_change' )
			)
		);
	}

	/**
	 * Verify the existing pricing leakage protection remains registered.
	 *
	 * @return void
	 */
	public function test_pricing_leakage_protection_remains_available(): void {
		PricingLeakageProtection::register();

		$this->assertTrue(
			class_exists( PricingLeakageProtection::class )
		);
	}

	/**
	 * Verify secure document service remains available.
	 *
	 * @return void
	 */
	public function test_document_security_remains_available(): void {
		$this->assertTrue(
			class_exists( DocumentSecurity::class )
		);

		$this->assertTrue(
			method_exists(
				DocumentSecurity::class,
				'validate_upload'
			)
		);

		$this->assertTrue(
			method_exists(
				DocumentSecurity::class,
				'store_upload'
			)
		);
	}
}