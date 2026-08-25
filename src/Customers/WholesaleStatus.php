<?php

namespace WholesaleOrdering\Customers;

use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the wholesale status of WordPress users.
 *
 * Wholesale status is independent of authentication and role assignment.
 *
 * The class is intentionally limited to reading and writing the user's
 * wholesale status. Approval/rejection/suspension workflows are responsible
 * for coordinating status changes with capabilities, audit logging and
 * notifications.
 */
final class WholesaleStatus {

    /**
     * User meta key storing the wholesale status.
     */
    private const META_KEY = '_wholesale_ordering_status';

    /**
     * Get a user's wholesale status.
     *
     * Users without an explicitly stored status, or users with an invalid
     * stored status, resolve to the default pending status.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return string
     */
    public static function get( int $user_id ): string {
        if ( $user_id <= 0 ) {
            return Config::DEFAULT_STATUS;
        }

        $status = get_user_meta(
            $user_id,
            self::META_KEY,
            true
        );

        if ( ! self::is_valid( $status ) ) {
            return Config::DEFAULT_STATUS;
        }

        return $status;
    }

    /**
     * Set a user's wholesale status.
     *
     * This operation is idempotent. If the requested status is already
     * stored, the method still returns true.
     *
     * This method does not grant or remove wholesale capabilities.
     * Authorization transitions belong to the application workflow.
     *
     * @param int    $user_id User ID.
     * @param string $status  Wholesale status.
     *
     * @return bool True when the requested status is established.
     */
    public static function set( int $user_id, string $status ): bool {
        if ( $user_id <= 0 || ! self::is_valid( $status ) ) {
            return false;
        }

        $current_status = get_user_meta(
            $user_id,
            self::META_KEY,
            true
        );

        if ( $current_status === $status ) {
            return true;
        }

        $result = update_user_meta(
            $user_id,
            self::META_KEY,
            $status
        );

        if ( false === $result ) {
            /*
             * update_user_meta() may return false when the database update
             * failed, or when the value could not be changed.
             *
             * Re-read the value so the method's contract reflects the
             * authoritative stored state.
             */
            return self::get( $user_id ) === $status;
        }

        return true;
    }

    /**
     * Determine whether a user has an approved wholesale status.
     *
     * Wholesale eligibility requires both:
     *
     * 1. Approved wholesale status.
     * 2. The plugin-owned wholesale capability.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    public static function is_approved( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        if ( self::get( $user_id ) !== Config::STATUS_APPROVED ) {
            return false;
        }

        return user_can(
            $user_id,
            RoleManager::CAPABILITY
        );
    }

    /**
     * Determine whether a status value is valid.
     *
     * @param mixed $status Status value.
     *
     * @return bool
     */
    public static function is_valid( $status ): bool {
        return is_string( $status )
            && in_array(
                $status,
                Config::wholesale_statuses(),
                true
            );
    }

    /**
     * Remove the stored wholesale status for a user.
     *
     * The user subsequently resolves to the default pending status.
     *
     * The operation is idempotent: removing an already absent status is
     * considered successful.
     *
     * @param int $user_id User ID.
     *
     * @return bool True when no explicit wholesale status remains.
     */
    public static function reset( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        $result = delete_user_meta(
            $user_id,
            self::META_KEY
        );

        if ( false === $result ) {
            return false;
        }

        return self::get( $user_id ) === Config::DEFAULT_STATUS;
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}