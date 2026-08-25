<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin configuration.
 */
final class Config {

    /**
     * Plugin version.
     */
    public const VERSION = '0.1.0';

    /**
     * Current database schema version.
     */
    public const DB_VERSION = 3;

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
     * Get all supported wholesale customer statuses.
     *
     * @return array
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
     * Private constructor.
     */
    private function __construct() {}
}