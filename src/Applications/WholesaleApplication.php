<?php

namespace WholesaleOrdering\Applications;

use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a wholesale customer application.
 *
 * Authentication remains a WordPress concern.
 * Wholesale eligibility remains a separate application/status concern.
 *
 * This object represents persisted application state and does not perform
 * persistence itself.
 */
final class WholesaleApplication {

    /**
     * Application data.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Application data.
     */
    public function __construct( array $data ) {
        $this->data = $data;
    }

    /**
     * Get WordPress user ID.
     */
    public function get_user_id(): int {
        return (int) ( $this->data['user_id'] ?? 0 );
    }

    /**
     * Get application status.
     */
    public function get_status(): string {
        $status = $this->data['status'] ?? Config::DEFAULT_STATUS;

        return Config::is_wholesale_status( $status )
            ? $status
            : Config::DEFAULT_STATUS;
    }

    /**
     * Get first name.
     */
    public function get_first_name(): string {
        return (string) ( $this->data['first_name'] ?? '' );
    }

    /**
     * Get last name.
     */
    public function get_last_name(): string {
        return (string) ( $this->data['last_name'] ?? '' );
    }

    /**
     * Get email.
     */
    public function get_email(): string {
        return (string) ( $this->data['email'] ?? '' );
    }

    /**
     * Get company/trading name.
     */
    public function get_company_name(): string {
        return (string) ( $this->data['company_name'] ?? '' );
    }

    /**
     * Get phone.
     */
    public function get_phone(): string {
        return (string) ( $this->data['phone'] ?? '' );
    }

    /**
     * Get billing address.
     *
     * @return array<string, string>
     */
    public function get_billing_address(): array {
        return $this->get_address( 'billing_address' );
    }

    /**
     * Get delivery address.
     *
     * @return array<string, string>
     */
    public function get_delivery_address(): array {
        return $this->get_address( 'delivery_address' );
    }

    /**
     * Get tax/VAT number.
     */
    public function get_tax_number(): string {
        return (string) ( $this->data['tax_number'] ?? '' );
    }

    /**
     * Get business registration number.
     */
    public function get_business_registration_number(): string {
        return (string) (
            $this->data['business_registration_number'] ?? ''
        );
    }

    /**
     * Get business type.
     */
    public function get_business_type(): string {
        return (string) ( $this->data['business_type'] ?? '' );
    }

    /**
     * Get website/social profile.
     */
    public function get_website(): string {
        return (string) ( $this->data['website'] ?? '' );
    }

    /**
     * Get supporting document attachment ID.
     */
    public function get_supporting_document_id(): int {
        return (int) (
            $this->data['supporting_document_id'] ?? 0
        );
    }

    /**
     * Get consent timestamp.
     */
    public function get_consent_at(): string {
        return (string) ( $this->data['consent_at'] ?? '' );
    }

    /**
     * Get consent version.
     */
    public function get_consent_version(): string {
        return (string) ( $this->data['consent_version'] ?? '' );
    }

    /**
     * Get application submission timestamp.
     */
    public function get_applied_at(): string {
        return (string) ( $this->data['applied_at'] ?? '' );
    }

    /**
     * Get review timestamp.
     */
    public function get_reviewed_at(): string {
        return (string) ( $this->data['reviewed_at'] ?? '' );
    }

    /**
     * Get reviewing administrator ID.
     */
    public function get_reviewed_by(): int {
        return (int) ( $this->data['reviewed_by'] ?? 0 );
    }

    /**
     * Get admin-only internal note.
     */
    public function get_internal_note(): string {
        return (string) ( $this->data['internal_note'] ?? '' );
    }

    /**
     * Determine whether an application has been submitted.
     */
    public function is_submitted(): bool {
        return '' !== $this->get_applied_at();
    }

    /**
     * Determine whether the application has been reviewed.
     */
    public function is_reviewed(): bool {
        return '' !== $this->get_reviewed_at()
            && $this->get_reviewed_by() > 0;
    }

    /**
     * Determine whether the application is pending.
     */
    public function is_pending(): bool {
        return Config::STATUS_PENDING === $this->get_status();
    }

    /**
     * Determine whether the application is approved.
     */
    public function is_approved(): bool {
        return Config::STATUS_APPROVED === $this->get_status();
    }

    /**
     * Determine whether the application is rejected.
     */
    public function is_rejected(): bool {
        return Config::STATUS_REJECTED === $this->get_status();
    }

    /**
     * Determine whether the application is suspended.
     */
    public function is_suspended(): bool {
        return Config::STATUS_SUSPENDED === $this->get_status();
    }

    /**
     * Return application data.
     *
     * A copy of the internal array is returned.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Safely retrieve an address.
     *
     * @param string $key Address key.
     *
     * @return array<string, string>
     */
    private function get_address( string $key ): array {
        $address = $this->data[ $key ] ?? array();

        if ( ! is_array( $address ) ) {
            return array();
        }

        $result = array();

        foreach ( $address as $field => $value ) {
            if ( ! is_string( $field ) ) {
                continue;
            }

            $result[ $field ] = is_scalar( $value )
                ? (string) $value
                : '';
        }

        return $result;
    }
}