<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable customer pricing context.
 *
 * Pricing eligibility is deliberately based on account state and capability,
 * not email address, username, display name, or any other contact identity.
 */
final class CustomerContext {

    /**
     * User ID.
     */
    private int $user_id;

    /**
     * Authenticated state.
     */
    private bool $authenticated;

    /**
     * Wholesale status.
     */
    private string $wholesale_status;

    /**
     * Approved capability state.
     */
    private bool $approved_capability;

    /**
     * Constructor.
     *
     * @param int|null $user_id Optional user ID. Defaults to current user.
     */
    public function __construct(
        ?int $user_id = null
    ) {
        $resolved_user_id = null !== $user_id
            ? $user_id
            : get_current_user_id();

        $this->user_id = max(
            0,
            (int) $resolved_user_id
        );

        $this->authenticated = $this->user_id > 0;

        /*
         * WholesaleStatus is the canonical domain-level source for
         * wholesale status. Do not read the underlying user meta directly.
         */
        $this->wholesale_status = $this->authenticated
            ? WholesaleStatus::get( $this->user_id )
            : Config::DEFAULT_STATUS;

        $this->approved_capability = $this->authenticated
            && user_can(
                $this->user_id,
                RoleManager::CAPABILITY
            );
    }

    /**
     * Get user ID.
     *
     * @return int
     */
    public function get_user_id(): int {
        return $this->user_id;
    }

    /**
     * Determine whether the customer is authenticated.
     *
     * @return bool
     */
    public function is_authenticated(): bool {
        return $this->authenticated;
    }

    /**
     * Get wholesale status.
     *
     * @return string
     */
    public function get_wholesale_status(): string {
        return $this->wholesale_status;
    }

    /**
     * Determine whether wholesale status is approved.
     *
     * @return bool
     */
    public function is_wholesale_approved(): bool {
        return Config::STATUS_APPROVED === $this->wholesale_status;
    }

    /**
     * Determine whether the approved wholesale capability exists.
     *
     * @return bool
     */
    public function has_approved_capability(): bool {
        return $this->approved_capability;
    }

    /**
     * Determine whether wholesale pricing is eligible.
     *
     * Both state and capability are mandatory.
     *
     * @return bool
     */
    public function can_use_wholesale_pricing(): bool {
        return $this->authenticated
            && $this->is_wholesale_approved()
            && $this->approved_capability;
    }
}