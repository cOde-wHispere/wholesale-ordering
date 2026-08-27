<?php

namespace WholesaleOrdering;

use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\Logger;
use WholesaleOrdering\Infrastructure\MigrationRunner;
use WholesaleOrdering\Infrastructure\Requirements;
use WholesaleOrdering\Pricing\WooCommercePricingIntegration;
use WholesaleOrdering\Products\ProductFields;
use WholesaleOrdering\Security\PricingLeakageProtection;
use WholesaleOrdering\Cart\CartIntegration;
use WholesaleOrdering\Checkout\CheckoutIntegration;
use WholesaleOrdering\Orders\OrderIntegration;


defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap class.
 */
final class Plugin {

    /**
     * Prevent initialization more than once.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        if ( ! Requirements::php_compatible() ) {
            Logger::error(
                'Plugin initialization aborted: PHP version requirement not satisfied.',
                array(
                    'required' => '8.3',
                    'current'  => PHP_VERSION,
                )
            );

            add_action(
                'admin_notices',
                array( self::class, 'php_missing_notice' )
            );

            return;
        }

        /*
         * WooCommerce may not yet be loaded when this plugin bootstrap
         * file executes. Dependency checks therefore complete on the
         * plugins_loaded hook.
         */
        add_action(
            'plugins_loaded',
            array( self::class, 'plugins_loaded' ),
            20
        );
    }

    /**
     * Finalize plugin initialization after WordPress plugins load.
     *
     * @return void
     */
    public static function plugins_loaded(): void {
        if ( ! Requirements::woocommerce_available() ) {
            Logger::warning(
                'Wholesale Ordering initialization skipped: WooCommerce is not available.'
            );

            add_action(
                'admin_notices',
                array( self::class, 'woocommerce_missing_notice' )
            );

            return;
        }

        self::register_runtime();

        update_option(
            Config::OPTION_VERSION,
            Config::VERSION,
            false
        );

        Logger::info(
            'Wholesale Ordering plugin initialized.',
            array(
                'version' => Config::VERSION,
            )
        );
    }

    /**
     * Register runtime services and hooks.
     *
     * @return void
     */
    private static function register_runtime(): void {
        /*
         * Database/schema and domain framework state.
         */
        MigrationRunner::run();

        /*
         * Product administration fields and metadata persistence.
         */
        ProductFields::register();

        /*
         * Authoritative WooCommerce pricing integration.
         *
         * This service is responsible for applying the PricingService
         * decision to WooCommerce product prices, price HTML, cart totals
         * and variation AJAX responses.
         */
        $pricing_integration = new WooCommercePricingIntegration();

        $pricing_integration->register();
        (new CartIntegration())->register();
        (new CheckoutIntegration())->register();
        (new OrderIntegration())->register();

        /*
         * Secondary exposure protection.
         *
         * This protects REST responses, structured data and authenticated
         * request caching from leaking customer-specific wholesale prices.
         */
        PricingLeakageProtection::register();
    }

    /**
     * Display a PHP dependency notice.
     *
     * @return void
     */
    public static function php_missing_notice(): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p>
                <strong>Wholesale Ordering</strong>
                requires PHP 8.3 or higher.
            </p>
        </div>
        <?php
    }

    /**
     * Display a WooCommerce dependency notice.
     *
     * @return void
     */
    public static function woocommerce_missing_notice(): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p>
                <strong>Wholesale Ordering</strong>
                requires WooCommerce to be installed and active.
            </p>
        </div>
        <?php
    }

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate(): void {
        if ( ! Requirements::php_compatible() ) {
            wp_die(
                esc_html__(
                    'Wholesale Ordering requires PHP 8.3 or higher.',
                    'wholesale-ordering'
                ),
                esc_html__(
                    'Plugin activation failed',
                    'wholesale-ordering'
                ),
                array(
                    'back_link' => true,
                )
            );
        }

        /*
         * WooCommerce does not need to be loaded during activation.
         * Runtime dependency enforcement occurs during plugins_loaded.
         */
        update_option(
            Config::OPTION_VERSION,
            Config::VERSION,
            false
        );

        MigrationRunner::run();

        Logger::info(
            'Wholesale Ordering plugin activated.',
            array(
                'version' => Config::VERSION,
            )
        );
    }

    /**
     * Deactivate the plugin.
     *
     * Deactivation must not remove business data.
     *
     * @return void
     */
    public static function deactivate(): void {
        Logger::info(
            'Wholesale Ordering plugin deactivated.',
            array(
                'version' => Config::VERSION,
            )
        );
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}