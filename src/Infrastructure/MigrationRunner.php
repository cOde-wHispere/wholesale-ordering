<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin database schema migrations.
 */
final class MigrationRunner {

    /**
     * Run pending migrations.
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
            return;
        }

        /*
         * Migration 1 currently establishes the migration framework.
         *
         * Actual business tables will be introduced in later phases
         * after their respective data models have been implemented.
         */
        if ( $installed_version < 1 ) {
            self::migrate_to_1();
        }

        update_option(
            Config::OPTION_DB_VERSION,
            $target_version,
            false
        );
    }

    /**
     * Migration to schema version 1.
     *
     * @return void
     */
    private static function migrate_to_1(): void {
        // Foundation migration.
        // No custom tables are required at this stage.
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}