<?php

namespace WholesaleOrdering\Customers;

use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/**

* Manages the canonical wholesale status of WordPress users.
*
* Wholesale status is independent of authentication, account identity and
* role assignment.
*
* This class is the single domain-level access point for reading and writing
* a user's wholesale status. Other services must use get() rather than
* reading the underlying user metadata directly.
*
* Approval/eligibility additionally requires the plugin-owned wholesale
* capability and is exposed through is_approved().
*
* The user's email address remains the WordPress account/login/contact
* identity and is not used as a wholesale-status lookup key.
  */
  final class WholesaleStatus {

  /**

  * User meta key storing the wholesale status.
  *
  * This key is intentionally private so callers cannot bypass the
  * canonical status abstraction.
    */
    private const META_KEY = '_wholesale_ordering_status';

  /**

  * Get a user's canonical wholesale status.
  *
  * Users without an explicitly stored status, or users with an invalid
  * stored status, resolve to the configured default status.
  *
  * Callers must use this method rather than reading META_KEY directly.
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

  * Set a user's canonical wholesale status.
  *
  * This operation is idempotent. If the requested status is already the
  * authoritative stored status, the method returns true without writing.
  *
  * This method changes status only. It does not grant or remove wholesale
  * capabilities. Application workflow services are responsible for
  * coordinating status transitions with access, review records and
  * notifications.
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

    /*

    * Compare against the canonical getter rather than duplicating the
    * fallback/validation rules in the write path.
      */
      if ( self::get( $user_id ) === $status ) {
      /*

      * If the requested status is the default but no explicit meta
      * exists, the domain state is already correct. There is no need
      * to create redundant metadata.
        */
        return true;
        }

    $result = update_user_meta(
    $user_id,
    self::META_KEY,
    $status
    );

    if ( false === $result ) {
    /*
    * WordPress may return false when an update did not succeed.
    * Re-read the canonical value so the return value reflects the
    * authoritative stored/domain state.
    */
    return self::get( $user_id ) === $status;
    }

    return self::get( $user_id ) === $status;
    }

  /**

  * Determine whether a user is an approved wholesale customer.
  *
  * Wholesale eligibility requires both:
  *
  * 1. Canonical wholesale status is approved.
  * 2. The plugin-owned wholesale capability is present.
  *
  * Status alone does not grant wholesale pricing.
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

  * Determine whether a status value is supported by the domain.
  *
  * @param mixed $status Status value.
  *
  * @return bool
    */
    public static function is_valid( $status ): bool {
    return Config::is_wholesale_status(
    $status
    );
    }

  /**

  * Determine whether a user has an explicitly stored wholesale status.
  *
  * This method is intentionally separate from get().
  *
  * get() answers the domain question:
  * "What is this user's effective wholesale status?"
  *
  * has_stored_status() answers the persistence question:
  * "Does this user have an explicit status value stored?"
  *
  * Application and pricing logic should normally use get(), not this
  * method.
  *
  * @param int $user_id User ID.
  *
  * @return bool
    */
    public static function has_stored_status( int $user_id ): bool {
    if ( $user_id <= 0 ) {
    return false;
    }

    $status = get_user_meta(
    $user_id,
    self::META_KEY,
    true
    );

    return self::is_valid( $status );
    }

  /**

  * Reset the stored wholesale status for a user.
  *
  * After reset, get() resolves to Config::DEFAULT_STATUS.
  *
  * The operation is idempotent: an already-reset user is considered
  * successfully reset.
  *
  * @param int $user_id User ID.
  *
  * @return bool True when no explicit wholesale status remains.
    */
    public static function reset( int $user_id ): bool {
    if ( $user_id <= 0 ) {
    return false;
    }

    /*

    * If no valid explicit status exists, the domain is already in its
    * default state. Treat reset as successful.
      */
      if ( ! self::has_stored_status( $user_id ) ) {
      return self::get( $user_id ) === Config::DEFAULT_STATUS;
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
    private function __construct() {}}
