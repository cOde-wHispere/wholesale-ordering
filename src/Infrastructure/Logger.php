<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin logging abstraction.
 */
final class Logger {

    /**
     * Log an informational message.
     *
     * @param string $message Message.
     * @param array  $context Context data.
     *
     * @return void
     */
    public static function info( string $message, array $context = [] ): void {
        self::write( 'INFO', $message, $context );
    }

    /**
     * Log a warning.
     *
     * @param string $message Message.
     * @param array  $context Context data.
     *
     * @return void
     */
    public static function warning( string $message, array $context = [] ): void {
        self::write( 'WARNING', $message, $context );
    }

    /**
     * Log an error.
     *
     * @param string $message Message.
     * @param array  $context Context data.
     *
     * @return void
     */
    public static function error( string $message, array $context = [] ): void {
        self::write( 'ERROR', $message, $context );
    }

    /**
     * Write to the PHP error log.
     *
     * This is intentionally a minimal foundation.
     * WooCommerce logging integration will be added separately.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context.
     *
     * @return void
     */
    private static function write( string $level, string $message, array $context ): void {
        $suffix = '';

        if ( ! empty( $context ) ) {
            $suffix = ' ' . wp_json_encode( $context );
        }

        error_log(
            sprintf(
                '[Wholesale Ordering] [%s] %s%s',
                $level,
                $message,
                $suffix
            )
        );
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}