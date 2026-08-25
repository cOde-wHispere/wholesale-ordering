<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin database/schema migrations.
 *
 * The application and product/pricing domains currently use native
 * WordPress/WooCommerce entities and protected metadata rather than custom
 * tables.
 */
final class MigrationRunner {

    /**
     * Run all pending migrations.
     *
     * Migrations are intentionally idempotent.
     *
     * @return void
     */
    public static function run(): void {
        $installed_version = (int) get_option(
            Config::OPTION_DB_VERSION,
            0
        );

        $target_version = Config::DB_VERSION;

        if ( $installed_version >= $target_version ) {
            self::ensure_current_state();

            return;
        }

        if ( $installed_version < 1 ) {
            self::migrate_to_1();
        }

        if ( $installed_version < 2 ) {
            self::migrate_to_2();
        }

        if ( $installed_version < 3 ) {
            self::migrate_to_3();
        }

        if ( $installed_version < 4 ) {
            self::migrate_to_4();
        }

        if ( $installed_version < 5 ) {
            self::migrate_to_5();
        }

        update_option(
            Config::OPTION_DB_VERSION,
            $target_version,
            false
        );

        self::ensure_current_state();
    }

    /**
     * Migration to schema version 1.
     *
     * @return void
     */
    private static function migrate_to_1(): void {
        /*
         * Foundation migration.
         *
         * No custom tables were required at this stage.
         */
    }

    /**
     * Migration to schema version 2.
     *
     * @return void
     */
    private static function migrate_to_2(): void {
        RoleManager::install();
    }

    /**
     * Migration to schema version 3.
     *
     * @return void
     */
    private static function migrate_to_3(): void {
        update_option(
            Config::OPTION_STATUS_VERSION,
            Config::STATUS_VERSION,
            false
        );
    }

    /**
     * Migration to schema version 4.
     *
     * @return void
     */
    private static function migrate_to_4(): void {
        update_option(
            Config::OPTION_APPLICATION_VERSION,
            Config::APPLICATION_VERSION,
            false
        );
    }

    /**
     * Migration to schema version 5.
     *
     * Establishes the product/pricing framework version.
     *
     * Product pricing uses WooCommerce product entities and protected
     * metadata, so no custom pricing table is introduced.
     *
     * @return void
     */
    private static function migrate_to_5(): void {
        update_option(
            Config::OPTION_PRODUCT_PRICING_VERSION,
            Config::PRODUCT_PRICING_VERSION,
            false
        );
    }

    /**
     * Ensure all current framework state exists.
     *
     * @return void
     */
    private static function ensure_current_state(): void {
        $status_version = (int) get_option(
            Config::OPTION_STATUS_VERSION,
            0
        );

        if ( $status_version < Config::STATUS_VERSION ) {
            update_option(
                Config::OPTION_STATUS_VERSION,
                Config::STATUS_VERSION,
                false
            );
        }

        $application_version = (int) get_option(
            Config::OPTION_APPLICATION_VERSION,
            0
        );

        if ( $application_version < Config::APPLICATION_VERSION ) {
            update_option(
                Config::OPTION_APPLICATION_VERSION,
                Config::APPLICATION_VERSION,
                false
            );
        }

        $product_pricing_version = (int) get_option(
            Config::OPTION_PRODUCT_PRICING_VERSION,
            0
        );

        if ( $product_pricing_version < Config::PRODUCT_PRICING_VERSION ) {
            self::migrate_to_5();
        }

        /*
         * Repair the role/capability model as part of current-state
         * verification. This is intentionally idempotent.
         */
        RoleManager::install();
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}