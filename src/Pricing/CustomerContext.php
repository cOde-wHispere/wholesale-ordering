<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable customer pricing context.
 *
 * Pricing eligibility is based on authentication, canonical wholesale status,
 * and the approved-wholesale capability.
 *
 * V1 pricing:
 *
 * - Guest: 0% discount.
 * - Registered customer: 2% discount.
 * - Approved wholesale customer: 4% discount.
 * - Pending/rejected/suspended: 0% discount.
 *
 * The registered and wholesale discounts are always calculated independently
 * from the WooCommerce Regular Price.
 */
final class CustomerContext {

    /**
     * User ID.
     *
     * @var int
     */
    private int $user_id;

    /**
     * Whether the customer is authenticated.
     *
     * @var bool
     */
    private bool $authenticated;

    /**
     * Canonical wholesale status.
     *
     * @var string
     */
    private string $wholesale_status;

    /**
     * Whether the approved wholesale capability exists.
     *
     * @var bool
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
         * WholesaleStatus is the canonical domain source.
         * Never read the underlying user meta directly here.
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
     * Get the current user ID.
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
     * Get the canonical wholesale status.
     *
     * @return string
     */
    public function get_wholesale_status(): string {
        return $this->wholesale_status;
    }

    /**
     * Determine whether the customer is a registered customer.
     *
     * A registered customer is authenticated but does not have active
     * approved-wholesale pricing.
     *
     * This includes pending, rejected and suspended accounts.
     *
     * @return bool
     */
    public function is_registered_customer(): bool {
        return $this->authenticated;
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
     * Determine whether the customer is an approved wholesale customer.
     *
     * Both canonical status and capability are mandatory.
     *
     * @return bool
     */
    public function can_use_wholesale_pricing(): bool {
        return $this->authenticated
            && $this->is_wholesale_approved()
            && $this->approved_capability;
    }

    /**
     * Determine the V1 discount rate.
     *
     * Rates are returned as decimal fractions:
     *
     * - 0.00 = no discount.
     * - 0.02 = 2% discount.
     * - 0.04 = 4% discount.
     *
     * @return string
     */
    public function get_discount_rate(): string {
        if ( $this->can_use_wholesale_pricing() ) {
            return '0.04';
        }

        if ( $this->is_registered_customer() ) {
            return '0.02';
        }

        return '0.00';
    }

    /**
     * Determine whether this context receives customer-specific pricing.
     *
     * @return bool
     */
    public function has_customer_discount(): bool {
        return '0.00' !== $this->get_discount_rate();
    }
}