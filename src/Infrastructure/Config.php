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
    public const DB_VERSION = 1;

    /**
     * WordPress option storing the installed plugin version.
     */
    public const OPTION_VERSION = 'wholesale_ordering_version';

    /**
     * WordPress option storing the database schema version.
     */
    public const OPTION_DB_VERSION = 'wholesale_ordering_db_version';

    /**
     * Private constructor.
     */
    private function __construct() {}
}