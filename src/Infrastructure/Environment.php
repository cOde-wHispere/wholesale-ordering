<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Provides information about the current runtime environment.
 */
final class Environment {

    /**
     * Get the WordPress version.
     *
     * @return string
     */
    public static function wordpress_version(): string {
        global $wp_version;

        return isset( $wp_version ) ? (string) $wp_version : '';
    }

    /**
     * Get the PHP version.
     *
     * @return string
     */
    public static function php_version(): string {
        return PHP_VERSION;
    }

    /**
     * Get the WooCommerce version.
     *
     * @return string
     */
    public static function woocommerce_version(): string {
        if ( defined( 'WC_VERSION' ) ) {
            return WC_VERSION;
        }

        return '';
    }

    /**
     * Determine whether WordPress is in debug mode.
     *
     * @return bool
     */
    public static function debug_enabled(): bool {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
}