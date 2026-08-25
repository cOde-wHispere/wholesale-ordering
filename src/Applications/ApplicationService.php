<?php

namespace WholesaleOrdering\Applications;

use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\RoleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates wholesale application business workflows.
 *
 * Application controllers, REST endpoints, admin screens and future
 * customer-facing UI should use this service rather than directly changing
 * application metadata, wholesale status or wholesale roles.
 *
 * Important identity invariant:
 *
 * The application's email represents the WordPress account's canonical
 * account/login/contact identity. It is not an independently editable
 * application field.
 *
 * The service therefore:
 *
 * - reads the canonical email from WP_User;
 * - rejects a submitted email that differs from the account email;
 * - never changes wp_users.user_email as part of an application workflow.
 */
final class ApplicationService {

    /**
     * Application repository.
     */
    private ApplicationRepository $repository;

    /**
     * Application validator.
     */
    private ApplicationValidator $validator;

    /**
     * Constructor.
     *
     * @param ApplicationRepository|null $repository Application repository.
     * @param ApplicationValidator|null  $validator  Application validator.
     */
    public function __construct(
        ?ApplicationRepository $repository = null,
        ?ApplicationValidator $validator = null
    ) {
        $this->repository = $repository
            ?? new ApplicationRepository();

        $this->validator = $validator
            ?? new ApplicationValidator();
    }

    /**
     * Retrieve an application by WordPress user ID.
     *
     * @param int $user_id User ID.
     *
     * @return WholesaleApplication|null
     */
    public function get(
        int $user_id
    ): ?WholesaleApplication {
        return $this->repository->find_by_user_id(
            $user_id
        );
    }

    /**
     * Submit a wholesale application.
     *
     * The account email is treated as canonical identity. If application
     * input contains an email, it must match the WordPress account email.
     * The repository remains responsible for persisting application data
     * and intentionally does not modify user_email.
     *
     * @param int                  $user_id Applicant.
     * @param array<string, mixed> $data    Application data.
     *
     * @return true|\WP_Error
     */
    public function submit(
        int $user_id,
        array $data
    ) {
        $user = $this->get_user(
            $user_id
        );

        if ( is_wp_error( $user ) ) {
            return $user;
        }

        /*
         * Email is account identity, not independently editable
         * application data.
         *
         * Validate the supplied value when present, but require it to
         * correspond exactly to the canonical WordPress account email.
         */
        $email_error = $this->validate_account_email(
            $user,
            $data
        );

        if ( is_wp_error( $email_error ) ) {
            return $email_error;
        }

        /*
         * Replace the application payload's email with the canonical
         * account email before persistence. This guarantees that every
         * layer receives the same identity value.
         */
        $data['email'] = $user->user_email;

        $errors = $this->validator->validate_submission(
            $data
        );

        if ( ! empty( $errors ) ) {
            return new \WP_Error(
                'invalid_application',
                'The wholesale application contains validation errors.',
                $errors
            );
        }

        $existing = $this->repository->find_by_user_id(
            $user_id
        );

        if ( $existing && $existing->is_approved() ) {
            return new \WP_Error(
                'already_approved',
                'This customer is already an approved wholesale customer.'
            );
        }

        if ( $existing && $existing->is_suspended() ) {
            return new \WP_Error(
                'account_suspended',
                'A suspended wholesale account cannot submit a new application.'
            );
        }

        /*
         * A pending application is not duplicated. Submitting again updates
         * the existing application and refreshes its submission timestamp.
         */
        if (
            ! $this->repository->save(
                $user_id,
                $data
            )
        ) {
            return new \WP_Error(
                'application_save_failed',
                'The wholesale application could not be saved.'
            );
        }

        $timestamp = current_time(
            'mysql',
            true
        );

        /*
         * Capture the previous state so rejected applications can clear
         * their previous review information when resubmitted.
         */
        $was_rejected = $existing
            && $existing->is_rejected();

        if (
            ! $this->repository->mark_submitted(
                $user_id,
                $timestamp
            )
        ) {
            return new \WP_Error(
                'application_submission_failed',
                'The application could not be marked as submitted.'
            );
        }

        if ( $was_rejected ) {
            $this->repository->clear_review(
                $user_id
            );
        }

        /*
         * A submitted application must never retain plugin-owned wholesale
         * access.
         */
        if (
            ! $this->remove_wholesale_access(
                $user_id
            )
        ) {
            return new \WP_Error(
                'wholesale_access_cleanup_failed',
                'Wholesale access could not be removed from the application.'
            );
        }

        do_action(
            'wholesale_ordering_application_submitted',
            $user_id,
            $timestamp
        );

        return true;
    }

    /**
     * Approve a pending wholesale application.
     *
     * @param int    $user_id       Applicant.
     * @param int    $reviewer_id   Administrative reviewer.
     * @param string $internal_note Optional admin-only note.
     *
     * @return true|\WP_Error
     */
    public function approve(
        int $user_id,
        int $reviewer_id,
        string $internal_note = ''
    ) {
        $authorization = $this->authorize_reviewer(
            $reviewer_id
        );

        if ( is_wp_error( $authorization ) ) {
            return $authorization;
        }

        $application = $this->repository->find_by_user_id(
            $user_id
        );

        if ( ! $application ) {
            return $this->application_not_found();
        }

        if (
            ! $this->validator->is_valid_transition(
                $application->get_status(),
                Config::STATUS_APPROVED
            )
        ) {
            return new \WP_Error(
                'invalid_transition',
                'Only a pending wholesale application can be approved.'
            );
        }

        $validation = $this->validator->validate_approval(
            $reviewer_id
        );

        if ( ! empty( $validation ) ) {
            return new \WP_Error(
                'invalid_approval',
                'The approval request is invalid.',
                $validation
            );
        }

        $note_errors = $this->validator->validate_internal_note(
            $internal_note
        );

        if ( ! empty( $note_errors ) ) {
            return new \WP_Error(
                'invalid_approval_note',
                'The approval request is invalid.',
                $note_errors
            );
        }

        $timestamp = current_time(
            'mysql',
            true
        );

        /*
         * Ensure the plugin-owned role exists before assigning it.
         */
        RoleManager::install();

        if (
            ! $this->restore_wholesale_role(
                $user_id
            )
        ) {
            return new \WP_Error(
                'wholesale_capability_failed',
                'Wholesale capability could not be granted.'
            );
        }

        if (
            ! WholesaleStatus::set(
                $user_id,
                Config::STATUS_APPROVED
            )
        ) {
            $this->remove_wholesale_access(
                $user_id
            );

            return new \WP_Error(
                'status_update_failed',
                'Wholesale approval status could not be saved.'
            );
        }

        if (
            ! $this->repository->record_review(
                $user_id,
                Config::STATUS_APPROVED,
                $reviewer_id,
                $timestamp,
                $internal_note
            )
        ) {
            /*
             * Roll back the authoritative wholesale state if the review
             * record cannot be persisted.
             */
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_PENDING
            );

            $this->remove_wholesale_access(
                $user_id
            );

            return new \WP_Error(
                'review_record_failed',
                'The approval record could not be saved.'
            );
        }

        do_action(
            'wholesale_ordering_application_approved',
            $user_id,
            $reviewer_id,
            $timestamp,
            $internal_note
        );

        return true;
    }

    /**
     * Reject a pending wholesale application.
     *
     * @param int    $user_id       Applicant.
     * @param int    $reviewer_id   Administrative reviewer.
     * @param string $internal_note Optional admin-only note/reason.
     *
     * @return true|\WP_Error
     */
    public function reject(
        int $user_id,
        int $reviewer_id,
        string $internal_note = ''
    ) {
        $authorization = $this->authorize_reviewer(
            $reviewer_id
        );

        if ( is_wp_error( $authorization ) ) {
            return $authorization;
        }

        $application = $this->repository->find_by_user_id(
            $user_id
        );

        if ( ! $application ) {
            return $this->application_not_found();
        }

        if (
            ! $this->validator->is_valid_transition(
                $application->get_status(),
                Config::STATUS_REJECTED
            )
        ) {
            return new \WP_Error(
                'invalid_transition',
                'Only a pending wholesale application can be rejected.'
            );
        }

        $validation = $this->validator->validate_rejection(
            $reviewer_id,
            $internal_note
        );

        if ( ! empty( $validation ) ) {
            return new \WP_Error(
                'invalid_rejection',
                'The rejection request is invalid.',
                $validation
            );
        }

        $timestamp = current_time(
            'mysql',
            true
        );

        if (
            ! WholesaleStatus::set(
                $user_id,
                Config::STATUS_REJECTED
            )
        ) {
            return new \WP_Error(
                'status_update_failed',
                'Wholesale rejection status could not be saved.'
            );
        }

        if (
            ! $this->remove_wholesale_access(
                $user_id
            )
        ) {
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_PENDING
            );

            return new \WP_Error(
                'role_removal_failed',
                'Wholesale access could not be removed.'
            );
        }

        if (
            ! $this->repository->record_review(
                $user_id,
                Config::STATUS_REJECTED,
                $reviewer_id,
                $timestamp,
                $internal_note
            )
        ) {
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_PENDING
            );

            return new \WP_Error(
                'review_record_failed',
                'The rejection record could not be saved.'
            );
        }

        do_action(
            'wholesale_ordering_application_rejected',
            $user_id,
            $reviewer_id,
            $timestamp,
            $internal_note
        );

        return true;
    }

    /**
     * Resubmit a rejected application.
     *
     * The updated application data is passed through submit(), which also
     * re-enforces the account-email identity invariant.
     *
     * @param int                  $user_id Applicant.
     * @param array<string, mixed> $data    Updated application data.
     *
     * @return true|\WP_Error
     */
    public function resubmit(
        int $user_id,
        array $data
    ) {
        $application = $this->repository->find_by_user_id(
            $user_id
        );

        if ( ! $application ) {
            return $this->application_not_found();
        }

        if ( ! $application->is_rejected() ) {
            return new \WP_Error(
                'invalid_resubmission',
                'Only a rejected application can be resubmitted.'
            );
        }

        return $this->submit(
            $user_id,
            $data
        );
    }

    /**
     * Suspend an approved wholesale customer.
     *
     * @param int    $user_id       Customer.
     * @param int    $reviewer_id   Administrative reviewer.
     * @param string $internal_note Optional admin-only note.
     *
     * @return true|\WP_Error
     */
    public function suspend(
        int $user_id,
        int $reviewer_id,
        string $internal_note = ''
    ) {
        $authorization = $this->authorize_reviewer(
            $reviewer_id
        );

        if ( is_wp_error( $authorization ) ) {
            return $authorization;
        }

        $application = $this->repository->find_by_user_id(
            $user_id
        );

        if ( ! $application ) {
            return $this->application_not_found();
        }

        if (
            ! $this->validator->is_valid_transition(
                $application->get_status(),
                Config::STATUS_SUSPENDED
            )
        ) {
            return new \WP_Error(
                'invalid_transition',
                'Only an approved wholesale customer can be suspended.'
            );
        }

        $note_errors = $this->validator->validate_internal_note(
            $internal_note
        );

        if ( ! empty( $note_errors ) ) {
            return new \WP_Error(
                'invalid_suspension_note',
                'The suspension request is invalid.',
                $note_errors
            );
        }

        $timestamp = current_time(
            'mysql',
            true
        );

        if (
            ! WholesaleStatus::set(
                $user_id,
                Config::STATUS_SUSPENDED
            )
        ) {
            return new \WP_Error(
                'status_update_failed',
                'Wholesale suspension could not be saved.'
            );
        }

        if (
            ! $this->remove_wholesale_access(
                $user_id
            )
        ) {
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_APPROVED
            );

            return new \WP_Error(
                'role_removal_failed',
                'Wholesale access could not be removed.'
            );
        }

        if (
            ! $this->repository->record_review(
                $user_id,
                Config::STATUS_SUSPENDED,
                $reviewer_id,
                $timestamp,
                $internal_note
            )
        ) {
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_APPROVED
            );

            $this->restore_wholesale_role(
                $user_id
            );

            return new \WP_Error(
                'review_record_failed',
                'The suspension record could not be saved.'
            );
        }

        do_action(
            'wholesale_ordering_application_suspended',
            $user_id,
            $reviewer_id,
            $timestamp,
            $internal_note
        );

        return true;
    }

    /**
     * Reactivate a suspended wholesale customer.
     *
     * @param int    $user_id       Customer.
     * @param int    $reviewer_id   Administrative reviewer.
     * @param string $internal_note Optional admin-only note.
     *
     * @return true|\WP_Error
     */
    public function reactivate(
        int $user_id,
        int $reviewer_id,
        string $internal_note = ''
    ) {
        $authorization = $this->authorize_reviewer(
            $reviewer_id
        );

        if ( is_wp_error( $authorization ) ) {
            return $authorization;
        }

        $application = $this->repository->find_by_user_id(
            $user_id
        );

        if ( ! $application ) {
            return $this->application_not_found();
        }

        if (
            ! $this->validator->is_valid_transition(
                $application->get_status(),
                Config::STATUS_APPROVED
            )
        ) {
            return new \WP_Error(
                'invalid_transition',
                'Only a suspended wholesale customer can be reactivated.'
            );
        }

        $note_errors = $this->validator->validate_internal_note(
            $internal_note
        );

        if ( ! empty( $note_errors ) ) {
            return new \WP_Error(
                'invalid_reactivation_note',
                'The reactivation request is invalid.',
                $note_errors
            );
        }

        $timestamp = current_time(
            'mysql',
            true
        );

        RoleManager::install();

        if (
            ! $this->restore_wholesale_role(
                $user_id
            )
        ) {
            return new \WP_Error(
                'role_restore_failed',
                'Wholesale capability could not be restored.'
            );
        }

        if (
            ! WholesaleStatus::set(
                $user_id,
                Config::STATUS_APPROVED
            )
        ) {
            $this->remove_wholesale_access(
                $user_id
            );

            return new \WP_Error(
                'status_update_failed',
                'Wholesale reactivation status could not be saved.'
            );
        }

        if (
            ! $this->repository->record_review(
                $user_id,
                Config::STATUS_APPROVED,
                $reviewer_id,
                $timestamp,
                $internal_note
            )
        ) {
            WholesaleStatus::set(
                $user_id,
                Config::STATUS_SUSPENDED
            );

            $this->remove_wholesale_access(
                $user_id
            );

            return new \WP_Error(
                'review_record_failed',
                'The reactivation record could not be saved.'
            );
        }

        do_action(
            'wholesale_ordering_application_reactivated',
            $user_id,
            $reviewer_id,
            $timestamp,
            $internal_note
        );

        return true;
    }

    /**
     * Validate that submitted email represents the account identity.
     *
     * The service deliberately does not allow the application workflow to
     * change wp_users.user_email.
     *
     * @param \WP_User             $user WordPress account.
     * @param array<string, mixed>  $data Application submission data.
     *
     * @return true|\WP_Error
     */
    private function validate_account_email(
        \WP_User $user,
        array $data
    ) {
        $account_email = sanitize_email(
            (string) $user->user_email
        );

        if ( '' === $account_email || ! is_email( $account_email ) ) {
            return new \WP_Error(
                'invalid_account_email',
                'The WordPress account does not have a valid email address.'
            );
        }

        /*
         * The field may be omitted by trusted internal callers because the
         * service can obtain the canonical value directly from WP_User.
         */
        if ( ! array_key_exists( 'email', $data ) ) {
            return true;
        }

        $submitted_email = sanitize_email(
            (string) $data['email']
        );

        if ( '' === $submitted_email || ! is_email( $submitted_email ) ) {
            return new \WP_Error(
                'invalid_application_email',
                'A valid account email address is required.'
            );
        }

        /*
         * Email comparison is case-insensitive.
         *
         * The address is still stored/reported using WordPress's canonical
         * user_email value rather than the submitted representation.
         */
        if (
            strtolower( $submitted_email )
            !== strtolower( $account_email )
        ) {
            return new \WP_Error(
                'application_email_mismatch',
                'The application email must match the email address of the WordPress account.'
            );
        }

        return true;
    }

    /**
     * Verify reviewer authority.
     *
     * @param int $reviewer_id Reviewer user ID.
     *
     * @return true|\WP_Error
     */
    private function authorize_reviewer(
        int $reviewer_id
    ) {
        if ( $reviewer_id <= 0 ) {
            return new \WP_Error(
                'invalid_reviewer',
                'A valid reviewer is required.'
            );
        }

        $reviewer = get_user_by(
            'id',
            $reviewer_id
        );

        if ( ! $reviewer ) {
            return new \WP_Error(
                'reviewer_not_found',
                'The reviewer account could not be found.'
            );
        }

        if (
            ! user_can(
                $reviewer_id,
                'manage_woocommerce'
            )
        ) {
            return new \WP_Error(
                'forbidden',
                'You are not authorized to manage wholesale applications.',
                array(
                    'status' => 403,
                )
            );
        }

        return true;
    }

    /**
     * Get a WordPress user.
     *
     * @param int $user_id User ID.
     *
     * @return \WP_User|\WP_Error
     */
    private function get_user(
        int $user_id
    ) {
        if ( $user_id <= 0 ) {
            return new \WP_Error(
                'invalid_user',
                'A valid user account is required.'
            );
        }

        $user = get_user_by(
            'id',
            $user_id
        );

        if ( ! $user ) {
            return new \WP_Error(
                'user_not_found',
                'The user account could not be found.'
            );
        }

        return $user;
    }

    /**
     * Restore the plugin-owned wholesale role.
     *
     * The role itself is the source of plugin-owned wholesale access.
     * Capability checks are deliberately not used as the existence test,
     * because another WordPress role could independently provide the same
     * capability.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    private function restore_wholesale_role(
        int $user_id
    ): bool {
        $user = get_user_by(
            'id',
            $user_id
        );

        if ( ! $user ) {
            return false;
        }

        $user->add_role(
            RoleManager::ROLE
        );

        return $this->has_wholesale_role(
            $user
        );
    }

    /**
     * Remove the plugin-owned wholesale role.
     *
     * This does not remove capabilities supplied by unrelated WordPress
     * roles. It only removes the role owned by this plugin.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    private function remove_wholesale_access(
        int $user_id
    ): bool {
        $user = get_user_by(
            'id',
            $user_id
        );

        if ( ! $user ) {
            return false;
        }

        $user->remove_role(
            RoleManager::ROLE
        );

        return ! $this->has_wholesale_role(
            $user
        );
    }

    /**
     * Determine whether the plugin-owned wholesale role is assigned.
     *
     * @param \WP_User $user WordPress user.
     *
     * @return bool
     */
    private function has_wholesale_role(
        \WP_User $user
    ): bool {
        return in_array(
            RoleManager::ROLE,
            (array) $user->roles,
            true
        );
    }

    /**
     * Return application-not-found error.
     *
     * @return \WP_Error
     */
    private function application_not_found(): \WP_Error {
        return new \WP_Error(
            'application_not_found',
            'The wholesale application could not be found.'
        );
    }
}