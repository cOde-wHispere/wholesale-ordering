<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/** Immutable customer pricing context. */
final class CustomerContext {
    private int $user_id;
    private bool $authenticated;
    private string $wholesale_status;
    private bool $approved_capability;

    public function __construct( ?int $user_id = null ) {
        $resolved_user_id = null !== $user_id ? $user_id : get_current_user_id();
        $this->user_id = max( 0, (int) $resolved_user_id );
        $this->authenticated = $this->user_id > 0;
        $this->wholesale_status = $this->authenticated
            ? WholesaleStatus::get( $this->user_id )
            : Config::DEFAULT_STATUS;
        $this->approved_capability = $this->authenticated
            && user_can( $this->user_id, RoleManager::CAPABILITY );
    }

    public function get_user_id(): int { return $this->user_id; }
    public function is_authenticated(): bool { return $this->authenticated; }
    public function get_wholesale_status(): string { return $this->wholesale_status; }
    public function is_wholesale_approved(): bool { return Config::STATUS_APPROVED === $this->wholesale_status; }
    public function has_approved_capability(): bool { return $this->approved_capability; }
    public function can_use_wholesale_pricing(): bool {
        return $this->authenticated && $this->is_wholesale_approved() && $this->approved_capability;
    }
}
