<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin database/schema migrations.
 *
 * The application domain currently uses WordPress user metadata rather than
 * a custom application table. Migration version 4 therefore establishes
 * and verifies the application framework version without introducing
 * unnecessary database tables.
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
        /*
         * Foundation migration.
         *
         * No custom tables were required at this stage.
         */
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
            Config::STATUS_VERSION,
            false
        );
    }

    /**
     * Migration to schema version 4.
     *
     * Establishes the wholesale application domain framework.
     *
     * Application records are represented through native WordPress user
     * metadata in V1, so no custom application table is required.
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
     * Ensure all state required by the current schema exists.
     *
     * This protects against installations where the database version was
     * advanced but one of the associated framework options was not persisted.
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
            self::migrate_to_4();
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