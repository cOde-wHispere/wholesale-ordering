<?php
namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Applications\WholesaleApplication;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Security\DocumentSecurity;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 5 wholesale application administration.
 *
 * The admin layer is responsible for:
 *
 * - capability checks;
 * - request validation;
 * - nonce verification;
 * - rendering application information;
 * - collecting review notes;
 * - calling ApplicationService for lifecycle operations;
 * - redirecting after actions;
 * - displaying success/error notices.
 *
 * It does not directly modify wholesale status, roles or application
 * lifecycle state.
 */
final class ApplicationsAdmin {

        /**
         * Admin page slug.
         */
        private const PAGE_SLUG = 'wholesale-ordering-applications';

        /**
         * Query parameter used for a selected application.
         */
        private const USER_ID_PARAM = 'user_id';

        /**
         * Status filter parameter.
         */
        private const STATUS_PARAM = 'status';

        /**
         * Admin action name.
         */
        private const ACTION_PARAM = 'wholesale_ordering_application_action';

        /**
         * Nonce action.
         */
        private const NONCE_ACTION = 'wholesale_ordering_application_action';

        /**
         * Nonce field.
         */
        private const NONCE_FIELD = '_wholesale_ordering_application_nonce';

        /**
         * Number of applications displayed per page.
         */
        private const PER_PAGE = 20;

        /**
         * Register admin hooks.
         *
         * @return void
         */
        public static function register(): void {
                if ( ! is_admin() ) {
                        return;
                }

                add_action(
                        'admin_menu',
                        array( self::class, 'register_submenu' )
                );

                add_action(
                        'admin_post_' . self::ACTION_PARAM,
                        array( self::class, 'handle_action' )
                );

                add_action(
                        'admin_notices',
                        array( self::class, 'render_notices' )
                );
        }

        /**
         * Register Applications submenu.
         *
         * @return void
         */
        public static function register_submenu(): void {
                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        return;
                }

                add_submenu_page(
                        'wholesale-ordering',
                        __( 'Applications', 'wholesale-ordering' ),
                        __( 'Applications', 'wholesale-ordering' ),
                        'manage_woocommerce',
                        self::PAGE_SLUG,
                        array( self::class, 'render_page' )
                );
        }

        /**
         * Render applications administration page.
         *
         * @return void
         */
        public static function render_page(): void {
                self::assert_capability();

                $user_id = isset( $_GET[ self::USER_ID_PARAM ] )
                        ? absint( wp_unslash( $_GET[ self::USER_ID_PARAM ] ) )
                        : 0;

                if ( $user_id > 0 ) {
                        self::render_detail( $user_id );
                        return;
                }

                self::render_list();
        }

        /**
         * Render application list.
         *
         * @return void
         */
        private static function render_list(): void {
                $status = isset( $_GET[ self::STATUS_PARAM ] )
                        ? sanitize_key(
                                wp_unslash( $_GET[ self::STATUS_PARAM ] )
                        )
                        : '';

                if (
                        '' !== $status
                        && ! Config::is_wholesale_status( $status )
                ) {
                        $status = '';
                }

                $page = isset( $_GET['paged'] )
                        ? max(
                                1,
                                absint(
                                        wp_unslash( $_GET['paged'] )
                                )
                        )
                        : 1;

                $users = self::get_applicant_users();

                $applications = array();

                foreach ( $users as $user ) {
                        $application = ( new ApplicationService() )->get(
                                (int) $user->ID
                        );

                        if ( ! $application instanceof WholesaleApplication ) {
                                continue;
                        }

                        if ( ! $application->is_submitted() ) {
                                continue;
                        }

                        if (
                                '' !== $status
                                && $application->get_status() !== $status
                        ) {
                                continue;
                        }

                        $applications[] = $application;
                }

                $total = count( $applications );

                $offset = ( $page - 1 ) * self::PER_PAGE;

                $paged_applications = array_slice(
                        $applications,
                        $offset,
                        self::PER_PAGE
                );

                ?>
                <div class="wrap">
                        <h1>
                                <?php
                                echo esc_html__(
                                        'Wholesale Applications',
                                        'wholesale-ordering'
                                );
                                ?>
                        </h1>

                        <?php self::render_status_filter( $status ); ?>

                        <table class="wp-list-table widefat fixed striped">
                                <thead>
                                        <tr>
                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Applicant',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>

                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Business',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>

                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Email',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>

                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Status',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>

                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Submitted',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>

                                                <th scope="col">
                                                        <?php
                                                        echo esc_html__(
                                                                'Actions',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </th>
                                        </tr>
                                </thead>

                                <tbody>
                                        <?php if ( empty( $paged_applications ) ) : ?>
                                                <tr>
                                                        <td colspan="6">
                                                                <?php
                                                                echo esc_html__(
                                                                        'No wholesale applications found.',
                                                                        'wholesale-ordering'
                                                                );
                                                                ?>
                                                        </td>
                                                </tr>
                                        <?php else : ?>
                                                <?php foreach ( $paged_applications as $application ) : ?>
                                                        <?php
                                                        $detail_url = add_query_arg(
                                                                array(
                                                                        'page'    => self::PAGE_SLUG,
                                                                        'user_id' => $application->get_user_id(),
                                                                ),
                                                                admin_url( 'admin.php' )
                                                        );
                                                        ?>
                                                        <tr>
                                                                <td>
                                                                        <strong>
                                                                                <a href="<?php echo esc_url( $detail_url ); ?>">
                                                                                        <?php
                                                                                        echo esc_html(
                                                                                                trim(
                                                                                                        $application->get_first_name()
                                                                                                        . ' '
                                                                                                        . $application->get_last_name()
                                                                                                )
                                                                                        );
                                                                                        ?>
                                                                                </a>
                                                                        </strong>
                                                                </td>

                                                                <td>
                                                                        <?php
                                                                        echo esc_html(
                                                                                $application->get_company_name()
                                                                        );
                                                                        ?>
                                                                </td>

                                                                <td>
                                                                        <?php
                                                                        echo esc_html(
                                                                                $application->get_email()
                                                                        );
                                                                        ?>
                                                                </td>

                                                                <td>
                                                                        <?php
                                                                        echo esc_html(
                                                                                self::status_label(
                                                                                        $application->get_status()
                                                                                )
                                                                        );
                                                                        ?>
                                                                </td>

                                                                <td>
                                                                        <?php
                                                                        echo esc_html(
                                                                                self::format_timestamp(
                                                                                        $application->get_applied_at()
                                                                                )
                                                                        );
                                                                        ?>
                                                                </td>

                                                                <td>
                                                                        <a
                                                                                class="button button-small"
                                                                                href="<?php echo esc_url( $detail_url ); ?>"
                                                                        >
                                                                                <?php
                                                                                echo esc_html__(
                                                                                        'View',
                                                                                        'wholesale-ordering'
                                                                                );
                                                                                ?>
                                                                        </a>
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </tbody>
                        </table>

                        <?php
                        self::render_pagination(
                                $total,
                                $page,
                                $status
                        );
                        ?>
                </div>
                <?php
        }

        /**
         * Render status filter.
         *
         * @param string $selected Selected status.
         *
         * @return void
         */
        private static function render_status_filter(
                string $selected
        ): void {
                ?>
                <form method="get" style="margin: 15px 0;">
                        <input
                                type="hidden"
                                name="page"
                                value="<?php echo esc_attr( self::PAGE_SLUG ); ?>"
                        />

                        <label for="wholesale-ordering-status">
                                <strong>
                                        <?php
                                        echo esc_html__(
                                                'Filter by status:',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </strong>
                        </label>

                        <select
                                id="wholesale-ordering-status"
                                name="<?php echo esc_attr( self::STATUS_PARAM ); ?>"
                        >
                                <option value="">
                                        <?php
                                        echo esc_html__(
                                                'All statuses',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </option>

                                <?php foreach ( Config::wholesale_statuses() as $status ) : ?>
                                        <option
                                                value="<?php echo esc_attr( $status ); ?>"
                                                <?php selected( $selected, $status ); ?>
                                        >
                                                <?php
                                                echo esc_html(
                                                        self::status_label( $status )
                                                );
                                                ?>
                                        </option>
                                <?php endforeach; ?>
                        </select>

                        <?php
                        submit_button(
                                __( 'Filter', 'wholesale-ordering' ),
                                'secondary',
                                'submit',
                                false
                        );
                        ?>
                </form>
                <?php
        }

        /**
         * Render application detail view.
         *
         * @param int $user_id Applicant user ID.
         *
         * @return void
         */
        private static function render_detail( int $user_id ): void {
                $application = ( new ApplicationService() )->get( $user_id );

                if (
                        ! $application instanceof WholesaleApplication
                        || ! $application->is_submitted()
                ) {
                        self::render_error(
                                __(
                                        'The wholesale application could not be found.',
                                        'wholesale-ordering'
                                )
                        );

                        return;
                }

                $user = get_user_by( 'id', $user_id );

                if ( ! $user ) {
                        self::render_error(
                                __(
                                        'The applicant account could not be found.',
                                        'wholesale-ordering'
                                )
                        );

                        return;
                }

                $back_url = add_query_arg(
                        array(
                                'page' => self::PAGE_SLUG,
                        ),
                        admin_url( 'admin.php' )
                );

                ?>
                <div class="wrap">
                        <h1>
                                <?php
                                echo esc_html__(
                                        'Wholesale Application',
                                        'wholesale-ordering'
                                );
                                ?>
                        </h1>

                        <p>
                                <a href="<?php echo esc_url( $back_url ); ?>">
                                        &larr;
                                        <?php
                                        echo esc_html__(
                                                'Back to Applications',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </a>
                        </p>

                        <?php self::render_application_summary( $application ); ?>

                        <?php self::render_review_information( $application ); ?>

                        <?php self::render_actions( $application ); ?>
                </div>
                <?php
        }

        /**
         * Render applicant and business information.
         *
         * @param WholesaleApplication $application Application.
         *
         * @return void
         */
        private static function render_application_summary(
                WholesaleApplication $application
        ): void {
                $billing  = $application->get_billing_address();
                $delivery = $application->get_delivery_address();

                ?>
                <h2>
                        <?php
                        echo esc_html__(
                                'Applicant Information',
                                'wholesale-ordering'
                        );
                        ?>
                </h2>

                <table class="form-table">
                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Applicant',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                trim(
                                                        $application->get_first_name()
                                                        . ' '
                                                        . $application->get_last_name()
                                                )
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Email',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php echo esc_html( $application->get_email() ); ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Phone',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php echo esc_html( $application->get_phone() ); ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Company / Trading name',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_company_name()
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Business type',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_business_type()
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Tax / VAT number',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_tax_number()
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Business registration number',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_business_registration_number()
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Website / social profile',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php if ( '' !== $application->get_website() ) : ?>
                                                <a
                                                        href="<?php echo esc_url( $application->get_website() ); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                >
                                                        <?php
                                                        echo esc_html(
                                                                $application->get_website()
                                                        );
                                                        ?>
                                                </a>
                                        <?php else : ?>
                                                &mdash;
                                        <?php endif; ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Status',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <strong>
                                                <?php
                                                echo esc_html(
                                                        self::status_label(
                                                                $application->get_status()
                                                        )
                                                );
                                                ?>
                                        </strong>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Submitted at',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                self::format_timestamp(
                                                        $application->get_applied_at()
                                                )
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Consent recorded',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_consent_at()
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Consent version',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->get_consent_version()
                                        );
                                        ?>
                                </td>
                        </tr>
                </table>

                <h2>
                        <?php
                        echo esc_html__(
                                'Billing Address',
                                'wholesale-ordering'
                        );
                        ?>
                </h2>

                <p>
                        <?php echo wp_kses_post( self::format_address( $billing ) ); ?>
                </p>

                <h2>
                        <?php
                        echo esc_html__(
                                'Delivery Address',
                                'wholesale-ordering'
                        );
                        ?>
                </h2>

                <p>
                        <?php
                        echo wp_kses_post(
                                self::format_address( $delivery )
                        );
                        ?>
                </p>

                <?php self::render_supporting_document( $application ); ?>
                <?php
        }

        /**
         * Render supporting document reference.
         *
         * @param WholesaleApplication $application Application.
         *
         * @return void
         */
        private static function render_supporting_document(
                WholesaleApplication $application
        ): void {
                $document_id = $application->get_supporting_document_id();

                ?>
                <h2><?php echo esc_html__( 'Supporting Document', 'wholesale-ordering' ); ?></h2>
                <?php

                if ( $document_id <= 0 ) :
                        ?>
                        <p><?php echo esc_html__( 'No supporting document was supplied.', 'wholesale-ordering' ); ?></p>
                        <?php
                        return;
                endif;

                $attachment = get_post( $document_id );
                if ( ! $attachment || 'attachment' !== $attachment->post_type ) :
                        ?>
                        <p><?php echo esc_html__( 'The supporting document record could not be found.', 'wholesale-ordering' ); ?></p>
                        <?php
                        return;
                endif;

                $name = DocumentSecurity::document_name( $document_id );
                if ( '' === $name ) {
                        $name = sprintf( __( 'Document #%d', 'wholesale-ordering' ), $document_id );
                }
                ?>
                <p>
                        <strong><?php echo esc_html__( 'Document:', 'wholesale-ordering' ); ?></strong>
                        <?php echo esc_html( $name ); ?>
                </p>
                <?php if ( DocumentSecurity::can_download( $document_id ) ) : ?>
                        <p>
                                <a class="button" href="<?php echo esc_url( DocumentSecurity::download_url( $document_id ) ); ?>">
                                        <?php echo esc_html__( 'Download securely', 'wholesale-ordering' ); ?>
                                </a>
                        </p>
                <?php endif; ?>
                <?php
        }

        /**
         * Render review information.
         *
         * @param WholesaleApplication $application Application.
         *
         * @return void
         */
        private static function render_review_information(
                WholesaleApplication $application
        ): void {
                ?>
                <h2>
                        <?php
                        echo esc_html__(
                                'Review Information',
                                'wholesale-ordering'
                        );
                        ?>
                </h2>

                <table class="form-table">
                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Reviewed at',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        echo esc_html(
                                                $application->is_reviewed()
                                                        ? self::format_timestamp(
                                                                $application->get_reviewed_at()
                                                        )
                                                        : __(
                                                                'Not reviewed',
                                                                'wholesale-ordering'
                                                        )
                                        );
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Reviewer',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        $reviewer_id = $application->get_reviewed_by();

                                        if ( $reviewer_id > 0 ) {
                                                $reviewer = get_user_by(
                                                        'id',
                                                        $reviewer_id
                                                );

                                                if ( $reviewer ) {
                                                        echo esc_html(
                                                                $reviewer->display_name
                                                        );
                                                        echo ' (#';
                                                        echo esc_html(
                                                                (string) $reviewer_id
                                                        );
                                                        echo ')';
                                                } else {
                                                        echo esc_html(
                                                                sprintf(
                                                                        /* translators: %d: reviewer user ID */
                                                                        __(
                                                                                'User #%d',
                                                                                'wholesale-ordering'
                                                                        ),
                                                                        $reviewer_id
                                                                )
                                                        );
                                                }
                                        } else {
                                                echo esc_html__(
                                                        'Not reviewed',
                                                        'wholesale-ordering'
                                                );
                                        }
                                        ?>
                                </td>
                        </tr>

                        <tr>
                                <th>
                                        <?php
                                        echo esc_html__(
                                                'Internal review note',
                                                'wholesale-ordering'
                                        );
                                        ?>
                                </th>
                                <td>
                                        <?php
                                        $note = $application->get_internal_note();

                                        if ( '' !== $note ) {
                                                echo nl2br(
                                                        esc_html( $note )
                                                );
                                        } else {
                                                echo esc_html__(
                                                        'No internal note.',
                                                        'wholesale-ordering'
                                                );
                                        }
                                        ?>
                                </td>
                        </tr>
                </table>
                <?php
        }

        /**
         * Render available lifecycle actions.
         *
         * @param WholesaleApplication $application Application.
         *
         * @return void
         */
        private static function render_actions(
                WholesaleApplication $application
        ): void {
                $status  = $application->get_status();
                $user_id = $application->get_user_id();

                ?>
                <h2>
                        <?php
                        echo esc_html__(
                                'Review / Customer Actions',
                                'wholesale-ordering'
                        );
                        ?>
                </h2>

                <?php if ( Config::STATUS_PENDING === $status ) : ?>

                        <form
                                method="post"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                style="margin-bottom:20px;"
                        >
                                <input
                                        type="hidden"
                                        name="action"
                                        value="<?php echo esc_attr( self::ACTION_PARAM ); ?>"
                                />

                                <input
                                        type="hidden"
                                        name="operation"
                                        value="approve"
                                />

                                <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo esc_attr( $user_id ); ?>"
                                />

                                <?php
                                wp_nonce_field(
                                        self::NONCE_ACTION,
                                        self::NONCE_FIELD
                                );
                                ?>

                                <p>
                                        <label for="approve-internal-note">
                                                <strong>
                                                        <?php
                                                        echo esc_html__(
                                                                'Internal review note',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </strong>
                                        </label>
                                </p>

                                <textarea
                                        id="approve-internal-note"
                                        name="internal_note"
                                        rows="5"
                                        class="large-text"
                                ></textarea>

                                <p>
                                        <button
                                                type="submit"
                                                class="button button-primary"
                                        >
                                                <?php
                                                echo esc_html__(
                                                        'Approve Application',
                                                        'wholesale-ordering'
                                                );
                                                ?>
                                        </button>
                                </p>
                        </form>

                        <form
                                method="post"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        >
                                <input
                                        type="hidden"
                                        name="action"
                                        value="<?php echo esc_attr( self::ACTION_PARAM ); ?>"
                                />

                                <input
                                        type="hidden"
                                        name="operation"
                                        value="reject"
                                />

                                <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo esc_attr( $user_id ); ?>"
                                />

                                <?php
                                wp_nonce_field(
                                        self::NONCE_ACTION,
                                        self::NONCE_FIELD
                                );
                                ?>

                                <p>
                                        <label for="reject-internal-note">
                                                <strong>
                                                        <?php
                                                        echo esc_html__(
                                                                'Rejection reason / internal note',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </strong>
                                        </label>
                                </p>

                                <textarea
                                        id="reject-internal-note"
                                        name="internal_note"
                                        rows="5"
                                        class="large-text"
                                        required
                                ></textarea>

                                <p>
                                        <button
                                                type="submit"
                                                class="button"
                                        >
                                                <?php
                                                echo esc_html__(
                                                        'Reject Application',
                                                        'wholesale-ordering'
                                                );
                                                ?>
                                        </button>
                                </p>
                        </form>

                <?php elseif ( Config::STATUS_APPROVED === $status ) : ?>

                        <form
                                method="post"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        >
                                <input
                                        type="hidden"
                                        name="action"
                                        value="<?php echo esc_attr( self::ACTION_PARAM ); ?>"
                                />

                                <input
                                        type="hidden"
                                        name="operation"
                                        value="suspend"
                                />

                                <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo esc_attr( $user_id ); ?>"
                                />

                                <?php
                                wp_nonce_field(
                                        self::NONCE_ACTION,
                                        self::NONCE_FIELD
                                );
                                ?>

                                <p>
                                        <label for="suspend-internal-note">
                                                <strong>
                                                        <?php
                                                        echo esc_html__(
                                                                'Suspension note',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </strong>
                                        </label>
                                </p>

                                <textarea
                                        id="suspend-internal-note"
                                        name="internal_note"
                                        rows="5"
                                        class="large-text"
                                ></textarea>

                                <p>
                                        <button
                                                type="submit"
                                                class="button"
                                        >
                                                <?php
                                                echo esc_html__(
                                                        'Suspend Wholesale Access',
                                                        'wholesale-ordering'
                                                );
                                                ?>
                                        </button>
                                </p>
                        </form>

                <?php elseif ( Config::STATUS_SUSPENDED === $status ) : ?>

                        <form
                                method="post"
                                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        >
                                <input
                                        type="hidden"
                                        name="action"
                                        value="<?php echo esc_attr( self::ACTION_PARAM ); ?>"
                                />

                                <input
                                        type="hidden"
                                        name="operation"
                                        value="reactivate"
                                />

                                <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo esc_attr( $user_id ); ?>"
                                />

                                <?php
                                wp_nonce_field(
                                        self::NONCE_ACTION,
                                        self::NONCE_FIELD
                                );
                                ?>

                                <p>
                                        <label for="reactivate-internal-note">
                                                <strong>
                                                        <?php
                                                        echo esc_html__(
                                                                'Reactivation note',
                                                                'wholesale-ordering'
                                                        );
                                                        ?>
                                                </strong>
                                        </label>
                                </p>

                                <textarea
                                        id="reactivate-internal-note"
                                        name="internal_note"
                                        rows="5"
                                        class="large-text"
                                ></textarea>

                                <p>
                                        <button
                                                type="submit"
                                                class="button button-primary"
                                        >
                                                <?php
                                                echo esc_html__(
                                                        'Reactivate Wholesale Access',
                                                        'wholesale-ordering'
                                                );
                                                ?>
                                        </button>
                                </p>
                        </form>

                <?php elseif ( Config::STATUS_REJECTED === $status ) : ?>

                        <p>
                                <?php
                                echo esc_html__(
                                        'This application was rejected. The customer may resubmit through the customer-facing application workflow.',
                                        'wholesale-ordering'
                                );
                                ?>
                        </p>

                <?php endif; ?>
                <?php
        }

        /**
         * Handle an admin lifecycle action.
         *
         * @return void
         */
        public static function handle_action(): void {
                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        wp_die(
                                esc_html__(
                                        'You are not authorized to manage wholesale applications.',
                                        'wholesale-ordering'
                                ),
                                esc_html__(
                                        'Access denied',
                                        'wholesale-ordering'
                                ),
                                array(
                                        'response' => 403,
                                )
                        );
                }

                check_admin_referer(
                        self::NONCE_ACTION,
                        self::NONCE_FIELD
                );

                $user_id = isset( $_POST['user_id'] )
                        ? absint(
                                wp_unslash( $_POST['user_id'] )
                        )
                        : 0;

                $operation = isset( $_POST['operation'] )
                        ? sanitize_key(
                                wp_unslash( $_POST['operation'] )
                        )
                        : '';

                $note = isset( $_POST['internal_note'] )
                        ? sanitize_textarea_field(
                                wp_unslash( $_POST['internal_note'] )
                        )
                        : '';

                $redirect = self::detail_url( $user_id );

                if ( $user_id <= 0 ) {
                        self::redirect_with_notice(
                                $redirect,
                                'error',
                                __(
                                        'Invalid application user.',
                                        'wholesale-ordering'
                                )
                        );
                }

                $user = get_user_by(
                        'id',
                        $user_id
                );

                if ( ! $user ) {
                        self::redirect_with_notice(
                                $redirect,
                                'error',
                                __(
                                        'The applicant account could not be found.',
                                        'wholesale-ordering'
                                )
                        );
                }

                $service     = new ApplicationService();
                $reviewer_id = get_current_user_id();

                switch ( $operation ) {
                        case 'approve':
                                $result = $service->approve(
                                        $user_id,
                                        $reviewer_id,
                                        $note
                                );

                                $success_message = __(
                                        'Wholesale application approved successfully.',
                                        'wholesale-ordering'
                                );
                                break;

                        case 'reject':
                                $result = $service->reject(
                                        $user_id,
                                        $reviewer_id,
                                        $note
                                );

                                $success_message = __(
                                        'Wholesale application rejected successfully.',
                                        'wholesale-ordering'
                                );
                                break;

                        case 'suspend':
                                $result = $service->suspend(
                                        $user_id,
                                        $reviewer_id,
                                        $note
                                );

                                $success_message = __(
                                        'Wholesale customer suspended successfully.',
                                        'wholesale-ordering'
                                );
                                break;

                        case 'reactivate':
                                $result = $service->reactivate(
                                        $user_id,
                                        $reviewer_id,
                                        $note
                                );

                                $success_message = __(
                                        'Wholesale customer reactivated successfully.',
                                        'wholesale-ordering'
                                );
                                break;

                        default:
                                self::redirect_with_notice(
                                        $redirect,
                                        'error',
                                        __(
                                                'Invalid wholesale application action.',
                                                'wholesale-ordering'
                                        )
                                );
                }

                if ( is_wp_error( $result ) ) {
                        self::redirect_with_notice(
                                $redirect,
                                'error',
                                $result->get_error_message()
                        );
                }

                self::redirect_with_notice(
                        $redirect,
                        'success',
                        $success_message
                );
        }

        /**
         * Render admin notices generated by this module.
         *
         * @return void
         */
        public static function render_notices(): void {
                if ( ! is_admin() ) {
                        return;
                }

                if (
                        ! isset( $_GET['wholesale_ordering_notice'] )
                        || ! isset( $_GET['wholesale_ordering_message'] )
                ) {
                        return;
                }

                $type = sanitize_key(
                        wp_unslash(
                                $_GET['wholesale_ordering_notice']
                        )
                );

                $message = sanitize_text_field(
                        wp_unslash(
                                $_GET['wholesale_ordering_message']
                        )
                );

                if ( ! in_array( $type, array( 'success', 'error' ), true ) ) {
                        return;
                }

                $class = 'notice notice-' . $type . ' is-dismissible';

                ?>
                <div class="<?php echo esc_attr( $class ); ?>">
                        <p>
                                <?php echo esc_html( $message ); ?>
                        </p>
                </div>
                <?php
        }

        /**
         * Return applicant WordPress users.
         *
         * Applications use applied_at metadata as the persisted submission
         * marker. Status remains owned by WholesaleStatus and is not queried
         * directly here.
         *
         * @return array<int,\WP_User>
         */
        private static function get_applicant_users(): array {
                $query = new \WP_User_Query(
                        array(
                                'meta_key'     => '_wholesale_ordering_applied_at',
                                'meta_compare' => 'EXISTS',
                                'orderby'      => 'meta_value',
                                'order'        => 'DESC',
                                'number'       => -1,
                                'fields'       => 'all',
                        )
                );

                $users = $query->get_results();

                return is_array( $users )
                        ? $users
                        : array();
        }

        /**
         * Render pagination.
         *
         * @param int    $total  Total applications.
         * @param int    $page   Current page.
         * @param string $status Status filter.
         *
         * @return void
         */
        private static function render_pagination(
                int $total,
                int $page,
                string $status
        ): void {
                $total_pages = (int) ceil(
                        $total / self::PER_PAGE
                );

                if ( $total_pages <= 1 ) {
                        return;
                }

                $base = add_query_arg(
                        array(
                                'page'   => self::PAGE_SLUG,
                                'paged'  => '%#%',
                                'status' => $status,
                        ),
                        admin_url( 'admin.php' )
                );

                echo '<div class="tablenav"><div class="tablenav-pages">';

                echo wp_kses_post(
                        paginate_links(
                                array(
                                        'base'      => $base,
                                        'format'    => '',
                                        'current'  => $page,
                                        'total'     => $total_pages,
                                        'type'      => 'plain',
                                        'prev_text' => __( '&laquo; Previous', 'wholesale-ordering' ),
                                        'next_text' => __( 'Next &raquo;', 'wholesale-ordering' ),
                                )
                        )
                );

                echo '</div></div>';
        }

        /**
         * Format an application timestamp for the administrator.
         *
         * @param string $timestamp UTC timestamp.
         *
         * @return string
         */
        private static function format_timestamp(
                string $timestamp
        ): string {
                if ( '' === $timestamp ) {
                        return 'ΓÇö';
                }

                $time = strtotime( $timestamp );

                if ( false === $time ) {
                        return $timestamp;
                }

                return wp_date(
                        get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                        $time
                );
        }

        /**
         * Format an address for display.
         *
         * @param array<string,string> $address Address.
         *
         * @return string
         */
        private static function format_address(
                array $address
        ): string {
                $parts = array();

                foreach (
                        array(
                                'first_name',
                                'last_name',
                                'company',
                                'address_1',
                                'address_2',
                                'city',
                                'state',
                                'postcode',
                                'country',
                        ) as $field
                ) {
                        if (
                                isset( $address[ $field ] )
                                && '' !== trim( $address[ $field ] )
                        ) {
                                $parts[] = esc_html(
                                        $address[ $field ]
                                );
                        }
                }

                if ( empty( $parts ) ) {
                        return esc_html__(
                                'No address supplied.',
                                'wholesale-ordering'
                        );
                }

                return implode( '<br>', $parts );
        }

        /**
         * Convert status into administrator-facing text.
         *
         * @param string $status Status.
         *
         * @return string
         */
        private static function status_label(
                string $status
        ): string {
                $labels = array(
                        Config::STATUS_PENDING   => __( 'Pending', 'wholesale-ordering' ),
                        Config::STATUS_APPROVED  => __( 'Approved', 'wholesale-ordering' ),
                        Config::STATUS_REJECTED  => __( 'Rejected', 'wholesale-ordering' ),
                        Config::STATUS_SUSPENDED => __( 'Suspended', 'wholesale-ordering' ),
                );

                return $labels[ $status ] ?? __( 'Unknown', 'wholesale-ordering' );
        }

        /**
         * Build application detail URL.
         *
         * @param int $user_id User ID.
         *
         * @return string
         */
        private static function detail_url( int $user_id ): string {
                return add_query_arg(
                        array(
                                'page'    => self::PAGE_SLUG,
                                'user_id' => $user_id,
                        ),
                        admin_url( 'admin.php' )
                );
        }

        /**
         * Redirect and attach an admin notice.
         *
         * @param string $url     Destination URL.
         * @param string $type    Notice type.
         * @param string $message Notice message.
         *
         * @return never
         */
        private static function redirect_with_notice(
                string $url,
                string $type,
                string $message
        ): never {
                $url = add_query_arg(
                        array(
                                'wholesale_ordering_notice'  => $type,
                                'wholesale_ordering_message' => $message,
                        ),
                        $url
                );

                wp_safe_redirect( $url );
                exit;
        }

        /**
         * Assert administration capability.
         *
         * @return void
         */
        private static function assert_capability(): void {
                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                        wp_die(
                                esc_html__(
                                        'You do not have permission to access Wholesale Ordering applications.',
                                        'wholesale-ordering'
                                ),
                                esc_html__(
                                        'Access denied',
                                        'wholesale-ordering'
                                ),
                                array(
                                        'response' => 403,
                                )
                        );
                }
        }

        /**
         * Render a simple error message.
         *
         * @param string $message Error message.
         *
         * @return void
         */
        private static function render_error(
                string $message
        ): void {
                ?>
                <div class="notice notice-error">
                        <p><?php echo esc_html( $message ); ?></p>
                </div>
                <?php
        }

        /**
         * Private constructor.
         */
        private function __construct() {}
}
