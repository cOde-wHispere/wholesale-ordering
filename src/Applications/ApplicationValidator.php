<?php

namespace WholesaleOrdering\Applications;

use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Validates wholesale application commands and state transitions.
 */
final class ApplicationValidator {

    /**
     * Maximum internal-note length.
     */
    private const MAX_INTERNAL_NOTE_LENGTH = 5000;

    /**
     * Validate a wholesale application submission.
     *
     * V1 required fields:
     * - first name
     * - last name
     * - email
     * - company/trading name
     * - phone
     * - billing address
     * - consent
     *
     * @param array<string, mixed> $data Application data.
     *
     * @return array<string, string>
     */
    public function validate_submission( array $data ): array {
        $errors = array();

        $this->required_text(
            $data,
            'first_name',
            'First name',
            $errors
        );

        $this->required_text(
            $data,
            'last_name',
            'Last name',
            $errors
        );

        $this->required_text(
            $data,
            'company_name',
            'Company / Trading name',
            $errors
        );

        $this->required_text(
            $data,
            'phone',
            'Phone',
            $errors
        );

        $this->validate_email(
            $data,
            $errors
        );

        $this->required_address(
            $data,
            'billing_address',
            'Billing address',
            $errors
        );

        $this->validate_optional_address(
            $data,
            'delivery_address',
            'Delivery address',
            $errors
        );

        $this->validate_website(
            $data,
            $errors
        );

        $this->validate_supporting_document(
            $data,
            $errors
        );

        $this->validate_consent(
            $data,
            $errors
        );

        return $errors;
    }

    /**
     * Validate an approval command.
     *
     * @param int $reviewer_id Reviewer user ID.
     *
     * @return array<string, string>
     */
    public function validate_approval( int $reviewer_id ): array {
        return $this->validate_reviewer(
            $reviewer_id
        );
    }

    /**
     * Validate a rejection command.
     *
     * @param int         $reviewer_id   Reviewer user ID.
     * @param string|null $internal_note Optional internal note.
     *
     * @return array<string, string>
     */
    public function validate_rejection(
        int $reviewer_id,
        ?string $internal_note = null
    ): array {
        $errors = $this->validate_reviewer(
            $reviewer_id
        );

        $note_errors = $this->validate_internal_note(
            $internal_note
        );

        $errors = array_merge(
            $errors,
            $note_errors
        );

        return $errors;
    }

    /**
     * Validate a suspension command.
     *
     * @param int         $reviewer_id   Reviewer user ID.
     * @param string|null $internal_note Optional internal note.
     *
     * @return array<string, string>
     */
    public function validate_suspension(
        int $reviewer_id,
        ?string $internal_note = null
    ): array {
        return $this->validate_rejection(
            $reviewer_id,
            $internal_note
        );
    }

    /**
     * Validate a reactivation command.
     *
     * @param int         $reviewer_id   Reviewer user ID.
     * @param string|null $internal_note Optional internal note.
     *
     * @return array<string, string>
     */
    public function validate_reactivation(
        int $reviewer_id,
        ?string $internal_note = null
    ): array {
        return $this->validate_rejection(
            $reviewer_id,
            $internal_note
        );
    }

    /**
     * Validate an internal review note.
     *
     * This method is public because ApplicationService uses it directly
     * when validating approval notes.
     *
     * @param string|null $internal_note Optional internal note.
     *
     * @return array<string, string>
     */
    public function validate_internal_note(
        ?string $internal_note = null
    ): array {
        $errors = array();

        if (
            null !== $internal_note
            && mb_strlen( $internal_note ) > self::MAX_INTERNAL_NOTE_LENGTH
        ) {
            $errors['internal_note'] =
                'The internal note is too long.';
        }

        return $errors;
    }

    /**
     * Determine whether a wholesale status transition is allowed.
     *
     * Allowed lifecycle:
     *
     * pending    -> approved
     * pending    -> rejected
     * rejected   -> pending
     * approved   -> suspended
     * suspended  -> approved
     *
     * @param string $from Current status.
     * @param string $to   Requested status.
     *
     * @return bool
     */
    public function is_valid_transition(
        string $from,
        string $to
    ): bool {
        if (
            ! Config::is_wholesale_status( $from )
            || ! Config::is_wholesale_status( $to )
        ) {
            return false;
        }

        $transitions = array(
            Config::STATUS_PENDING => array(
                Config::STATUS_APPROVED,
                Config::STATUS_REJECTED,
            ),
            Config::STATUS_REJECTED => array(
                Config::STATUS_PENDING,
            ),
            Config::STATUS_APPROVED => array(
                Config::STATUS_SUSPENDED,
            ),
            Config::STATUS_SUSPENDED => array(
                Config::STATUS_APPROVED,
            ),
        );

        return isset( $transitions[ $from ] )
            && in_array(
                $to,
                $transitions[ $from ],
                true
            );
    }

    /**
     * Validate reviewer ID.
     *
     * @param int $reviewer_id Reviewer user ID.
     *
     * @return array<string, string>
     */
    private function validate_reviewer( int $reviewer_id ): array {
        if ( $reviewer_id <= 0 ) {
            return array(
                'reviewer_id' => 'A valid reviewer is required.',
            );
        }

        return array();
    }

    /**
     * Validate email.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function validate_email(
        array $data,
        array &$errors
    ): void {
        if (
            ! isset( $data['email'] )
            || '' === trim( (string) $data['email'] )
        ) {
            $errors['email'] = 'Email address is required.';
            return;
        }

        if ( ! is_email( (string) $data['email'] ) ) {
            $errors['email'] = 'A valid email address is required.';
        }
    }

    /**
     * Validate consent.
     *
     * Consent is required and should carry a version.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function validate_consent(
        array $data,
        array &$errors
    ): void {
        if (
            empty( $data['consent'] )
            && empty( $data['consent_at'] )
        ) {
            $errors['consent'] =
                'Terms and privacy consent is required.';
        }

        if (
            ! empty( $data['consent'] )
            && (
                ! isset( $data['consent_version'] )
                || '' === trim( (string) $data['consent_version'] )
            )
        ) {
            $errors['consent_version'] =
                'Consent version is required.';
        }
    }

    /**
     * Validate website.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function validate_website(
        array $data,
        array &$errors
    ): void {
        if ( empty( $data['website'] ) ) {
            return;
        }

        if (
            ! filter_var(
                $data['website'],
                FILTER_VALIDATE_URL
            )
        ) {
            $errors['website'] =
                'Website must be a valid URL.';
        }
    }

    /**
     * Validate supporting document reference.
     *
     * Actual MIME type, file size and protected-access validation belongs
     * to the document/security layer.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function validate_supporting_document(
        array $data,
        array &$errors
    ): void {
        if ( ! isset( $data['supporting_document_id'] ) ) {
            return;
        }

        if ( (int) $data['supporting_document_id'] < 0 ) {
            $errors['supporting_document_id'] =
                'Supporting document reference is invalid.';
        }
    }

    /**
     * Validate an optional delivery address.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function validate_optional_address(
        array $data,
        string $key,
        string $label,
        array &$errors
    ): void {
        if (
            ! isset( $data[ $key ] )
            || '' === trim(
                (string) wp_json_encode( $data[ $key ] )
            )
        ) {
            return;
        }

        if ( ! is_array( $data[ $key ] ) ) {
            $errors[ $key ] = $label . ' must be a valid address.';
            return;
        }

        $this->validate_address_fields(
            $data[ $key ],
            $key,
            $label,
            $errors,
            false
        );
    }

    /**
     * Validate required billing address.
     *
     * @param array<string, mixed>  $data
     * @param string                $key
     * @param string                $label
     * @param array<string, string> $errors
     */
    private function required_address(
        array $data,
        string $key,
        string $label,
        array &$errors
    ): void {
        if (
            ! isset( $data[ $key ] )
            || ! is_array( $data[ $key ] )
        ) {
            $errors[ $key ] = $label . ' is required.';
            return;
        }

        $this->validate_address_fields(
            $data[ $key ],
            $key,
            $label,
            $errors,
            true
        );
    }

    /**
     * Validate address fields.
     *
     * @param array<string, mixed>  $address
     * @param string                $key
     * @param string                $label
     * @param array<string, string> $errors
     * @param bool                  $required
     */
    private function validate_address_fields(
        array $address,
        string $key,
        string $label,
        array &$errors,
        bool $required
    ): void {
        $required_fields = array(
            'address_1',
            'city',
            'postcode',
            'country',
        );

        foreach ( $required_fields as $field ) {
            if (
                $required
                && (
                    ! isset( $address[ $field ] )
                    || '' === trim( (string) $address[ $field ] )
                )
            ) {
                $errors[ $key . '.' . $field ] =
                    $label . ' ' . $field . ' is required.';
            }
        }

        if (
            isset( $address['country'] )
            && '' !== trim( (string) $address['country'] )
            && 2 !== strlen( (string) $address['country'] )
        ) {
            $errors[ $key . '.country' ] =
                $label . ' country must use a valid country code.';
        }
    }

    /**
     * Validate a required text field.
     *
     * @param array<string, mixed>  $data
     * @param string                $key
     * @param string                $label
     * @param array<string, string> $errors
     */
    private function required_text(
        array $data,
        string $key,
        string $label,
        array &$errors
    ): void {
        if (
            ! isset( $data[ $key ] )
            || '' === trim( (string) $data[ $key ] )
        ) {
            $errors[ $key ] = $label . ' is required.';
        }
    }
}