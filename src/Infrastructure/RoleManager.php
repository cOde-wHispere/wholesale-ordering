<?php

namespace WholesaleOrdering\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the wholesale customer role and capability model.
 */
final class RoleManager {

    /**
     * Wholesale customer role slug.
     */
    public const ROLE = 'approved_wholesale_customer';

    /**
     * Capability used to determine wholesale eligibility.
     */
    public const CAPABILITY = 'approved_wholesale_customer';

    /**
     * Install or repair the wholesale role and capability.
     *
     * This operation is intentionally idempotent.
     *
     * @return void
     */
    public static function install(): void {
        $role = get_role( self::ROLE );

        if ( null === $role ) {
            add_role(
                self::ROLE,
                __( 'Approved Wholesale Customer', 'wholesale-ordering' ),
                array(
                    'read'            => true,
                    self::CAPABILITY => true,
                )
            );

            return;
        }

        if ( ! $role->has_cap( 'read' ) ) {
            $role->add_cap( 'read' );
        }

        if ( ! $role->has_cap( self::CAPABILITY ) ) {
            $role->add_cap( self::CAPABILITY );
        }
    }

    /**
     * Determine whether a user has wholesale capability.
     *
     * @param int|null $user_id User ID. Defaults to current user.
     *
     * @return bool
     */
    public static function user_can_wholesale( ?int $user_id = null ): bool {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( $user_id <= 0 ) {
            return false;
        }

        return user_can(
            $user_id,
            self::CAPABILITY
        );
    }

    /**
     * Remove the plugin-owned wholesale role.
     *
     * This is intentionally not called during normal deactivation.
     *
     * @return void
     */
    public static function uninstall(): void {
        remove_role( self::ROLE );
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}
