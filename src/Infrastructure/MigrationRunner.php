<?php

namespace WholesaleOrdering\Infrastructure;

use WholesaleOrdering\Security\DocumentSecurity;

defined( 'ABSPATH' ) || exit;

final class MigrationRunner {
    public static function run(): void {
        $installed_version = (int) get_option( Config::OPTION_DB_VERSION, 0 );
        $target_version = Config::DB_VERSION;
        if ( $installed_version >= $target_version ) {
            self::ensure_current_state();
            return;
        }
        if ( $installed_version < 1 ) self::migrate_to_1();
        if ( $installed_version < 2 ) self::migrate_to_2();
        if ( $installed_version < 3 ) self::migrate_to_3();
        if ( $installed_version < 4 ) self::migrate_to_4();
        if ( $installed_version < 5 ) self::migrate_to_5();
        if ( $installed_version < 6 ) self::migrate_to_6();
        update_option( Config::OPTION_DB_VERSION, $target_version, false );
        self::ensure_current_state();
    }

    private static function migrate_to_1(): void {}

    private static function migrate_to_2(): void { RoleManager::install(); }

    private static function migrate_to_3(): void {
        update_option( Config::OPTION_STATUS_VERSION, Config::STATUS_VERSION, false );
    }

    private static function migrate_to_4(): void {
        update_option( Config::OPTION_APPLICATION_VERSION, Config::APPLICATION_VERSION, false );
    }

    private static function migrate_to_5(): void {
        update_option( Config::OPTION_PRODUCT_PRICING_VERSION, Config::PRODUCT_PRICING_VERSION, false );
    }

    private static function migrate_to_6(): void {
        // Move legacy public supporting-document attachments into the secure boundary.
        DocumentSecurity::migrate_legacy_documents();
    }

    private static function ensure_current_state(): void {
        if ( (int) get_option( Config::OPTION_STATUS_VERSION, 0 ) < Config::STATUS_VERSION ) {
            update_option( Config::OPTION_STATUS_VERSION, Config::STATUS_VERSION, false );
        }
        if ( (int) get_option( Config::OPTION_APPLICATION_VERSION, 0 ) < Config::APPLICATION_VERSION ) {
            update_option( Config::OPTION_APPLICATION_VERSION, Config::APPLICATION_VERSION, false );
        }
        if ( (int) get_option( Config::OPTION_PRODUCT_PRICING_VERSION, 0 ) < Config::PRODUCT_PRICING_VERSION ) {
            self::migrate_to_5();
        }
        RoleManager::install();
    }

    private function __construct() {}
}
