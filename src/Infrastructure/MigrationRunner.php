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

        if ( $installed_version < 1 ) {
            self::migrate_to_1();
        }

        if ( $installed_version < 2 ) {
            self::migrate_to_2();
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
        // No custom tables were required at this stage.
    }

    /**
     * Migration to schema version 2.
     *
     * Establishes the approved wholesale customer role and capability.
     *
     * @return void
     */
    private static function migrate_to_2(): void {
        RoleManager::install();
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}
