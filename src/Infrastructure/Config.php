<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin configuration and domain constants.
 */
final class Config {

    /**
     * Plugin version.
     */
    public const VERSION = '0.1.0';

    /**
     * Current database/schema version.
     *
     * Version 5 establishes the product/pricing framework.
     */
    public const DB_VERSION = 5;

    /**
     * WordPress option storing the installed plugin version.
     */
    public const OPTION_VERSION = 'wholesale_ordering_version';

    /**
     * WordPress option storing the database schema version.
     */
    public const OPTION_DB_VERSION = 'wholesale_ordering_db_version';

    /**
     * WordPress option storing the wholesale status framework version.
     */
    public const OPTION_STATUS_VERSION = 'wholesale_ordering_status_version';

    /**
     * WordPress option storing the wholesale application framework version.
     */
    public const OPTION_APPLICATION_VERSION = 'wholesale_ordering_application_version';

    /**
     * WordPress option storing the wholesale product/pricing framework version.
     */
    public const OPTION_PRODUCT_PRICING_VERSION = 'wholesale_ordering_product_pricing_version';

    /**
     * Current wholesale status framework version.
     */
    public const STATUS_VERSION = 1;

    /**
     * Current wholesale application framework version.
     */
    public const APPLICATION_VERSION = 1;

    /**
     * Current wholesale product/pricing framework version.
     */
    public const PRODUCT_PRICING_VERSION = 1;

    /**
     * Wholesale customer status: pending.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Wholesale customer status: approved.
     */
    public const STATUS_APPROVED = 'approved';

    /**
     * Wholesale customer status: rejected.
     */
    public const STATUS_REJECTED = 'rejected';

    /**
     * Wholesale customer status: suspended.
     */
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Default wholesale customer status.
     */
    public const DEFAULT_STATUS = self::STATUS_PENDING;

    /**
     * Return all supported wholesale customer statuses.
     *
     * @return array<int, string>
     */
    public static function wholesale_statuses(): array {
        return array(
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_SUSPENDED,
        );
    }

    /**
     * Determine whether a wholesale status is supported.
     *
     * @param mixed $status Status value.
     *
     * @return bool
     */
    public static function is_wholesale_status( $status ): bool {
        return is_string( $status )
            && in_array(
                $status,
                self::wholesale_statuses(),
                true
            );
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}