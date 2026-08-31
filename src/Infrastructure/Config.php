<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

final class Config {
    public const VERSION = '0.1.0';
    public const DB_VERSION = 6;
    public const OPTION_VERSION = 'wholesale_ordering_version';
    public const OPTION_DB_VERSION = 'wholesale_ordering_db_version';
    public const OPTION_STATUS_VERSION = 'wholesale_ordering_status_version';
    public const OPTION_APPLICATION_VERSION = 'wholesale_ordering_application_version';
    public const OPTION_PRODUCT_PRICING_VERSION = 'wholesale_ordering_product_pricing_version';
    public const STATUS_VERSION = 1;
    public const APPLICATION_VERSION = 1;
    public const PRODUCT_PRICING_VERSION = 1;
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const DEFAULT_STATUS = self::STATUS_PENDING;

    public static function wholesale_statuses(): array {
        return array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SUSPENDED );
    }

    public static function is_wholesale_status( $status ): bool {
        return is_string( $status ) && in_array( $status, self::wholesale_statuses(), true );
    }

    private function __construct() {}
}
