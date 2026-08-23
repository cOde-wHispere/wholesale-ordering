<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Checks whether the environment satisfies plugin requirements.
 */
final class Requirements {

    /**
     * Required PHP version.
     */
    private const REQUIRED_PHP = '8.3';

    /**
     * Check PHP compatibility.
     *
     * @return bool
     */
    public static function php_compatible(): bool {
        return version_compare( PHP_VERSION, self::REQUIRED_PHP, '>=' );
    }

    /**
     * Check whether WooCommerce is available.
     *
     * @return bool
     */
    public static function woocommerce_available(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Check all runtime requirements.
     *
     * @return bool
     */
    public static function is_satisfied(): bool {
        return self::php_compatible() && self::woocommerce_available();
    }
}