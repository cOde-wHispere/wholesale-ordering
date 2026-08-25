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
     * Migration to schema version 3.
     *
     * Establishes the wholesale customer status framework.
     *
     * @return void
     */
    private static function migrate_to_3(): void {
        update_option(
            Config::OPTION_STATUS_VERSION,
            1,
            false
        );
    }

    /**
     * Ensure state introduced by the current schema is present.
     *
     * This protects against a database where the schema version was
     * advanced but an associated framework option was not persisted.
     *
     * @return void
     */
    private static function ensure_current_state(): void {
        $status_version = (int) get_option(
            Config::OPTION_STATUS_VERSION,
            0
        );

        if ( $status_version < 1 ) {
            self::migrate_to_3();
        }
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}
