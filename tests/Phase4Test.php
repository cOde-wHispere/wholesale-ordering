<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;
use WholesaleOrdering\Cart\CartIntegration;
use WholesaleOrdering\Checkout\CheckoutIntegration;
use WholesaleOrdering\Checkout\CheckoutValidator;
use WholesaleOrdering\Orders\ReorderService;
use WholesaleOrdering\Pricing\PricingService;
use WholesaleOrdering\Pricing\WooCommercePricingIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 4 automated tests.
 *
 * These tests cover:
 *
 * - authoritative pricing integration;
 * - cart price mutation ownership;
 * - classic checkout validation ownership;
 * - Store API validation registration;
 * - native WooCommerce reorder integration;
 * - wholesale price validation.
 */
final class Phase4Test extends TestCase {

	/**
	 * Skip integration tests when WordPress/WooCommerce is unavailable.
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
				'Phase 4 integration tests require WordPress and WooCommerce.'
			);
		}
	}

	/**
	 * Pricing integration must register the authoritative product-price
	 * filters without registering cart-price mutation.
	 *
	 * @return void
	 */
	public function test_pricing_integration_registers_authoritative_price_filters(): void {
		$integration = new WooCommercePricingIntegration();

		$integration->register();

		$this->assertNotFalse(
			has_filter(
				'woocommerce_product_get_price',
				array( $integration, 'filter_product_price' )
			)
		);

		$this->assertNotFalse(
			has_filter(
				'woocommerce_product_variation_get_price',
				array( $integration, 'filter_product_price' )
			)
		);

		$this->assertNotFalse(
			has_filter(
				'woocommerce_get_price_html',
				array( $integration, 'filter_price_html' )
			)
		);

		$this->assertNotFalse(
			has_filter(
				'woocommerce_available_variation',
				array( $integration, 'filter_available_variation' )
			)
		);

		$this->assertFalse(
			has_action(
				'woocommerce_before_calculate_totals',
				array( $integration, 'recalculate_cart_prices' )
			)
		);
	}

	/**
	 * CartIntegration is the only Phase 4 plugin owner of cart price mutation.
	 *
	 * @return void
	 */
	public function test_cart_integration_is_the_only_cart_price_mutation_owner(): void {
		$cart_integration    = new CartIntegration();
		$pricing_integration = new WooCommercePricingIntegration();

		$cart_integration->register();
		$pricing_integration->register();

		$this->assertNotFalse(
			has_action(
				'woocommerce_before_calculate_totals',
				array( $cart_integration, 'recalculate_prices' )
			)
		);

		$this->assertFalse(
			has_action(
				'woocommerce_before_calculate_totals',
				array( $pricing_integration, 'recalculate_cart_prices' )
			)
		);

		global $wp_filter;

		$cart_price_callbacks = array();

		if (
			isset( $wp_filter['woocommerce_before_calculate_totals'] )
			&& $wp_filter['woocommerce_before_calculate_totals'] instanceof \WP_Hook
		) {
			foreach (
				$wp_filter['woocommerce_before_calculate_totals']->callbacks
				as $callbacks
			) {
				foreach ( $callbacks as $callback ) {
					if (
						! isset( $callback['function'] )
						|| ! is_array( $callback['function'] )
						|| ! isset( $callback['function'][0] )
						|| ! is_object( $callback['function'][0] )
					) {
						continue;
					}

					if ( $callback['function'][0] instanceof CartIntegration ) {
						$cart_price_callbacks[] = $callback['function'];
					}
				}
			}
		}

		$this->assertCount(
			1,
			$cart_price_callbacks,
			'CartIntegration must register exactly one Wholesale Ordering cart-price mutation callback.'
		);

		$this->assertSame(
			'recalculate_prices',
			$cart_price_callbacks[0][1]
		);
	}

	/**
	 * CheckoutIntegration must not own the classic checkout boundary.
	 *
	 * Classic checkout is exclusively owned by CheckoutValidator.
	 *
	 * @return void
	 */
	public function test_checkout_integration_does_not_register_classic_checkout_hooks(): void {
		$integration = new CheckoutIntegration();

		$integration->register();

		$this->assertFalse(
			has_action(
				'woocommerce_check_cart_items',
				array( $integration, 'validate_classic_cart' )
			)
		);

		$this->assertFalse(
			has_action(
				'woocommerce_after_checkout_validation',
				array( $integration, 'validate_classic_checkout' )
			)
		);
	}

	/**
	 * CheckoutValidator must be the single Wholesale Ordering classic checkout
	 * validation boundary.
	 *
	 * @return void
	 */
	public function test_checkout_validator_is_the_single_classic_checkout_boundary(): void {
		$integration = new CheckoutIntegration();
		$validator   = new CheckoutValidator();

		$integration->register();
		$validator->register();

		$this->assertNotFalse(
			has_action(
				'woocommerce_after_checkout_validation',
				array( $validator, 'validate_checkout' )
			)
		);

		global $wp_filter;

		$wholesale_callbacks = array();

		if (
			isset( $wp_filter['woocommerce_after_checkout_validation'] )
			&& $wp_filter['woocommerce_after_checkout_validation'] instanceof \WP_Hook
		) {
			foreach (
				$wp_filter['woocommerce_after_checkout_validation']->callbacks
				as $callbacks
			) {
				foreach ( $callbacks as $callback ) {
					if (
						! isset( $callback['function'] )
						|| ! is_array( $callback['function'] )
						|| ! isset( $callback['function'][0] )
						|| ! is_object( $callback['function'][0] )
					) {
						continue;
					}

					if (
						$callback['function'][0] instanceof CheckoutValidator
						|| $callback['function'][0] instanceof CheckoutIntegration
					) {
						$wholesale_callbacks[] = $callback['function'];
					}
				}
			}
		}

		$this->assertCount(
			1,
			$wholesale_callbacks,
			'Wholesale Ordering must register exactly one classic checkout validation callback.'
		);

		$this->assertInstanceOf(
			CheckoutValidator::class,
			$wholesale_callbacks[0][0]
		);

		$this->assertSame(
			'validate_checkout',
			$wholesale_callbacks[0][1]
		);
	}

	/**
	 * CheckoutIntegration must continue to protect the Store API boundary.
	 *
	 * @return void
	 */
	public function test_checkout_integration_registers_store_api_validation(): void {
		$integration = new CheckoutIntegration();

		$integration->register();

		$this->assertNotFalse(
			has_action(
				'woocommerce_store_api_cart_errors',
				array( $integration, 'validate_store_api_cart' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'woocommerce_store_api_validate_cart_item',
				array( $integration, 'validate_store_api_cart_item' )
			)
		);
	}

	/**
	 * Native reorder integration must use WooCommerce's order-again filter.
	 *
	 * @return void
	 */
	public function test_native_reorder_integration_is_registered(): void {
		$service = new ReorderService();

		$service->register();

		$this->assertNotFalse(
			has_filter(
				'woocommerce_valid_order_statuses_for_order_again',
				array( $service, 'valid_order_statuses' )
			)
		);

		$this->assertNotFalse(
			has_action(
				'woocommerce_ordered_again',
				array( $service, 'after_native_reorder' )
			)
		);
	}

	/**
	 * V1 reorder policy remains completed orders.
	 *
	 * @return void
	 */
	public function test_native_reorder_policy_is_completed_only(): void {
		$service = new ReorderService();

		$this->assertSame(
			array( 'completed' ),
			$service->valid_order_statuses(
				array(
					'completed',
					'processing',
					'on-hold',
				)
			)
		);
	}

	/**
	 * Empty wholesale prices are invalid.
	 *
	 * @return void
	 */
	public function test_empty_wholesale_price_is_invalid(): void {
		$service = new PricingService();

		$this->assertFalse(
			$service->isValidWholesalePrice( '' )
		);

		$this->assertFalse(
			$service->isValidWholesalePrice( null )
		);
	}

	/**
	 * Non-numeric wholesale prices are invalid.
	 *
	 * @return void
	 */
	public function test_non_numeric_wholesale_price_is_invalid(): void {
		$service = new PricingService();

		$this->assertFalse(
			$service->isValidWholesalePrice( 'not-a-price' )
		);
	}

	/**
	 * Negative wholesale prices are invalid.
	 *
	 * @return void
	 */
	public function test_negative_wholesale_price_is_invalid(): void {
		$service = new PricingService();

		$this->assertFalse(
			$service->isValidWholesalePrice( -1 )
		);

		$this->assertFalse(
			$service->isValidWholesalePrice( '-10.00' )
		);
	}

	/**
	 * Zero is valid according to the product field rules.
	 *
	 * @return void
	 */
	public function test_zero_wholesale_price_is_valid(): void {
		$service = new PricingService();

		$this->assertTrue(
			$service->isValidWholesalePrice( 0 )
		);

		$this->assertTrue(
			$service->isValidWholesalePrice( '0.00' )
		);
	}

	/**
	 * Positive wholesale prices are valid.
	 *
	 * @return void
	 */
	public function test_positive_wholesale_price_is_valid(): void {
		$service = new PricingService();

		$this->assertTrue(
			$service->isValidWholesalePrice( 90 )
		);

		$this->assertTrue(
			$service->isValidWholesalePrice( '90.00' )
		);
	}
}
