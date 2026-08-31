<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;
use WholesaleOrdering\Account\Account;
use WholesaleOrdering\Frontend\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 6 integration-boundary tests.
 *
 * These tests verify that Phase 6 registers against WooCommerce/WordPress
 * boundaries rather than replacing the commerce engine.
 */
final class Phase6Test extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        if (
            ! function_exists( 'add_action' )
            || ! function_exists( 'add_filter' )
            || ! class_exists( '\\WooCommerce' )
        ) {
            $this->markTestSkipped(
                'Phase 6 integration tests require WordPress and WooCommerce.'
            );
        }
    }

    public function test_frontend_registers_responsive_catalog_and_quantity_boundaries(): void {
        Frontend::register();

        $this->assertNotFalse(
            has_action(
                'wp_enqueue_scripts',
                array( Frontend::class, 'enqueue_assets' )
            )
        );

        $this->assertNotFalse(
            has_action(
                'woocommerce_before_shop_loop',
                array( Frontend::class, 'render_catalog_tools' )
            )
        );

        $this->assertNotFalse(
            has_filter(
                'woocommerce_quantity_input_args',
                array( Frontend::class, 'filter_quantity_input_args' )
            )
        );

        $this->assertNotFalse(
            has_filter(
                'body_class',
                array( Frontend::class, 'add_body_class' )
            )
        );
    }

    public function test_account_registers_wholesale_status_endpoint_and_order_documents(): void {
        Account::register();

        $this->assertNotFalse(
            has_action(
                'init',
                array( Account::class, 'register_endpoint' )
            )
        );

        $this->assertNotFalse(
            has_filter(
                'woocommerce_account_menu_items',
                array( Account::class, 'add_menu_item' )
            )
        );

        $this->assertNotFalse(
            has_action(
                'woocommerce_account_wholesale-status_endpoint',
                array( Account::class, 'render_wholesale_status' )
            )
        );

        $this->assertNotFalse(
            has_action(
                'woocommerce_view_order',
                array( Account::class, 'render_order_documents' )
            )
        );
    }

    public function test_phase6_does_not_register_a_second_price_engine(): void {
        Frontend::register();

        $this->assertFalse(
            has_filter(
                'woocommerce_product_get_price',
                array( Frontend::class, 'filter_product_price' )
            )
        );

        $this->assertFalse(
            has_filter(
                'woocommerce_get_price_html',
                array( Frontend::class, 'filter_price_html' )
            )
        );
    }
}
