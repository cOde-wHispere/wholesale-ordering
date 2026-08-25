<?php

namespace WholesaleOrdering\Applications;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Persists and retrieves wholesale applications.
 *
 * V1 uses native WordPress user metadata and WooCommerce customer
 * metadata. A custom application table can be introduced later if
 * application querying requirements justify it.
 */
final class ApplicationRepository {

    private const META_COMPANY_NAME          = '_wholesale_ordering_company_name';
    private const META_TAX_NUMBER            = '_wholesale_ordering_tax_number';
    private const META_BUSINESS_REGISTRATION  = '_wholesale_ordering_business_registration_number';
    private const META_BUSINESS_TYPE         = '_wholesale_ordering_business_type';
    private const META_WEBSITE               = '_wholesale_ordering_website';
    private const META_SUPPORTING_DOCUMENT   = '_wholesale_ordering_supporting_document_id';
    private const META_CONSENT_AT            = '_wholesale_ordering_consent_at';
    private const META_CONSENT_VERSION       = '_wholesale_ordering_consent_version';
    private const META_APPLIED_AT            = '_wholesale_ordering_applied_at';
    private const META_REVIEWED_AT           = '_wholesale_ordering_reviewed_at';
    private const META_REVIEWED_BY           = '_wholesale_ordering_reviewed_by';
    private const META_INTERNAL_NOTE         = '_wholesale_ordering_internal_note';

    /**
     * Find an application by user ID.
     */
    public function find_by_user_id(
        int $user_id
    ): ?WholesaleApplication {
        if ( $user_id <= 0 ) {
            return null;
        }

        $user = get_user_by(
            'id',
            $user_id
        );

        if ( ! $user ) {
            return null;
        }

        return new WholesaleApplication(
            $this->build_data( $user )
        );
    }

    /**
     * Determine whether application data exists for a user.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    public function exists( int $user_id ): bool {
        $application = $this->find_by_user_id( $user_id );

        return null !== $application
            && $application->is_submitted();
    }

    /**
     * Save application profile/business data.
     *
     * Status and lifecycle transitions are intentionally handled by the
     * application service.
     *
     * @param int                  $user_id User ID.
     * @param array<string, mixed> $data    Validated data.
     *
     * @return bool
     */
    public function save(
        int $user_id,
        array $data
    ): bool {
        if (
            $user_id <= 0
            || ! get_user_by( 'id', $user_id )
        ) {
            return false;
        }

        $user_update = array(
            'ID'         => $user_id,
            'first_name' => sanitize_text_field(
                (string) ( $data['first_name'] ?? '' )
            ),
            'last_name' => sanitize_text_field(
                (string) ( $data['last_name'] ?? '' )
            ),
        );

        if ( false === wp_update_user( $user_update ) ) {
            return false;
        }

        $metadata = array(
            self::META_COMPANY_NAME => sanitize_text_field(
                (string) ( $data['company_name'] ?? '' )
            ),
            self::META_TAX_NUMBER => sanitize_text_field(
                (string) ( $data['tax_number'] ?? '' )
            ),
            self::META_BUSINESS_REGISTRATION => sanitize_text_field(
                (string) (
                    $data['business_registration_number'] ?? ''
                )
            ),
            self::META_BUSINESS_TYPE => sanitize_text_field(
                (string) ( $data['business_type'] ?? '' )
            ),
            self::META_WEBSITE => esc_url_raw(
                (string) ( $data['website'] ?? '' )
            ),
            self::META_SUPPORTING_DOCUMENT => absint(
                $data['supporting_document_id'] ?? 0
            ),
            self::META_CONSENT_AT => sanitize_text_field(
                (string) ( $data['consent_at'] ?? '' )
            ),
            self::META_CONSENT_VERSION => sanitize_text_field(
                (string) ( $data['consent_version'] ?? '' )
            ),
        );

        foreach ( $metadata as $key => $value ) {
            if (
                false === update_user_meta(
                    $user_id,
                    $key,
                    $value
                )
            ) {
                return false;
            }
        }

        if (
            ! $this->save_address(
                $user_id,
                'billing',
                $data['billing_address'] ?? array()
            )
        ) {
            return false;
        }

        if (
            isset( $data['delivery_address'] )
            && is_array( $data['delivery_address'] )
            && ! $this->save_address(
                $user_id,
                'shipping',
                $data['delivery_address']
            )
        ) {
            return false;
        }

        if ( isset( $data['phone'] ) ) {
            if (
                false === update_user_meta(
                    $user_id,
                    'billing_phone',
                    sanitize_text_field(
                        (string) $data['phone']
                    )
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark an application as submitted.
     *
     * This operation also establishes pending wholesale status.
     *
     * @param int    $user_id   Applicant.
     * @param string $timestamp Submission timestamp.
     *
     * @return bool
     */
    public function mark_submitted(
        int $user_id,
        string $timestamp
    ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        if (
            false === update_user_meta(
                $user_id,
                self::META_APPLIED_AT,
                sanitize_text_field( $timestamp )
            )
        ) {
            return false;
        }

        return WholesaleStatus::set(
            $user_id,
            Config::STATUS_PENDING
        );
    }

    /**
     * Record administrative review metadata.
     *
     * Status itself is managed by the service so that role/status
     * coordination remains in one place.
     *
     * @param int         $user_id
     * @param int         $reviewer_id
     * @param string      $timestamp
     * @param string|null $internal_note
     *
     * @return bool
     */
    public function record_review(
        int $user_id,
        int $reviewer_id,
        string $timestamp,
        ?string $internal_note = null
    ): bool {
        if (
            $user_id <= 0
            || $reviewer_id <= 0
        ) {
            return false;
        }

        $operations = array(
            self::META_REVIEWED_AT => sanitize_text_field(
                $timestamp
            ),
            self::META_REVIEWED_BY => $reviewer_id,
        );

        if ( null !== $internal_note ) {
            $operations[ self::META_INTERNAL_NOTE ] =
                sanitize_textarea_field(
                    $internal_note
                );
        }

        foreach ( $operations as $key => $value ) {
            if (
                false === update_user_meta(
                    $user_id,
                    $key,
                    $value
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear review metadata.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    public function clear_review( int $user_id ): bool {
        if ( $user_id <= 0 ) {
            return false;
        }

        delete_user_meta(
            $user_id,
            self::META_REVIEWED_AT
        );

        delete_user_meta(
            $user_id,
            self::META_REVIEWED_BY
        );

        delete_user_meta(
            $user_id,
            self::META_INTERNAL_NOTE
        );

        return true;
    }

    /**
     * Build domain data from a WordPress user.
     *
     * @param \WP_User $user User object.
     *
     * @return array<string, mixed>
     */
    private function build_data(
        \WP_User $user
    ): array {
        $user_id = (int) $user->ID;

        return array(
            'user_id' => $user_id,
            'status'  => WholesaleStatus::get( $user_id ),

            'first_name' => (string) get_user_meta(
                $user_id,
                'first_name',
                true
            ),
            'last_name' => (string) get_user_meta(
                $user_id,
                'last_name',
                true
            ),
            'email' => (string) $user->user_email,

            'company_name' => (string) get_user_meta(
                $user_id,
                self::META_COMPANY_NAME,
                true
            ),
            'phone' => (string) get_user_meta(
                $user_id,
                'billing_phone',
                true
            ),

            'billing_address' => $this->get_address(
                $user_id,
                'billing'
            ),
            'delivery_address' => $this->get_address(
                $user_id,
                'shipping'
            ),

            'tax_number' => (string) get_user_meta(
                $user_id,
                self::META_TAX_NUMBER,
                true
            ),
            'business_registration_number' => (string) get_user_meta(
                $user_id,
                self::META_BUSINESS_REGISTRATION,
                true
            ),
            'business_type' => (string) get_user_meta(
                $user_id,
                self::META_BUSINESS_TYPE,
                true
            ),
            'website' => (string) get_user_meta(
                $user_id,
                self::META_WEBSITE,
                true
            ),
            'supporting_document_id' => (int) get_user_meta(
                $user_id,
                self::META_SUPPORTING_DOCUMENT,
                true
            ),
            'consent_at' => (string) get_user_meta(
                $user_id,
                self::META_CONSENT_AT,
                true
            ),
            'consent_version' => (string) get_user_meta(
                $user_id,
                self::META_CONSENT_VERSION,
                true
            ),
            'applied_at' => (string) get_user_meta(
                $user_id,
                self::META_APPLIED_AT,
                true
            ),
            'reviewed_at' => (string) get_user_meta(
                $user_id,
                self::META_REVIEWED_AT,
                true
            ),
            'reviewed_by' => (int) get_user_meta(
                $user_id,
                self::META_REVIEWED_BY,
                true
            ),
            'internal_note' => (string) get_user_meta(
                $user_id,
                self::META_INTERNAL_NOTE,
                true
            ),
        );
    }

    /**
     * Save a WooCommerce address.
     *
     * @param int                  $user_id User ID.
     * @param string               $type    Address type.
     * @param array<string, mixed> $address Address data.
     *
     * @return bool
     */
    private function save_address(
        int $user_id,
        string $type,
        array $address
    ): bool {
        $allowed = array(
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
        );

        foreach ( $allowed as $field ) {
            if ( ! array_key_exists( $field, $address ) ) {
                continue;
            }

            if (
                false === update_user_meta(
                    $user_id,
                    $type . '_' . $field,
                    sanitize_text_field(
                        (string) $address[ $field ]
                    )
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieve a WooCommerce address.
     *
     * @return array<string, string>
     */
    private function get_address(
        int $user_id,
        string $type
    ): array {
        $fields = array(
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
        );

        $address = array();

        foreach ( $fields as $field ) {
            $address[ $field ] = (string) get_user_meta(
                $user_id,
                $type . '_' . $field,
                true
            );
        }

        return $address;
    }
}