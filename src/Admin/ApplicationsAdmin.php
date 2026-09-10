<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Applications\WholesaleApplication;
use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 5 wholesale application administration.
 *
 * Lifecycle changes remain delegated to ApplicationService. This class owns
 * only the Shop Manager UI, request validation, capability checks and
 * redirects.
 */
final class ApplicationsAdmin {

	private const PAGE_SLUG    = 'wholesale-ordering-applications';
	private const ACTION       = 'wholesale_ordering_application_action';
	private const NONCE        = 'wholesale_ordering_application_nonce';
	private const PER_PAGE     = 20;

	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( self::class, 'register_submenu' ), 10 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_action' ) );
		add_action( 'admin_notices', array( self::class, 'render_notice' ) );
	}

	public static function register_submenu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_submenu_page(
			'wholesale-ordering',
			__( 'Applicants', 'wholesale-ordering' ),
			__( 'Applicants', 'wholesale-ordering' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		self::assert_capability();

		$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;

		if ( $user_id > 0 ) {
			self::render_detail( $user_id );
			return;
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( '' !== $status && ! Config::is_wholesale_status( $status ) ) {
			$status = '';
		}

		$service = new ApplicationService();
		$users   = get_users(
			array(
				'role__in' => array( 'customer', 'approved_wholesale_customer' ),
				'number'   => -1,
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);

		$applications = array();
		foreach ( $users as $user ) {
			$application = $service->get( (int) $user->ID );
			if ( ! $application instanceof WholesaleApplication || ! $application->is_submitted() ) {
				continue;
			}
			if ( '' !== $status && $application->get_status() !== $status ) {
				continue;
			}
			$applications[] = $application;
		}

		$total = count( $applications );
		$page  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$items = array_slice( $applications, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Wholesale Applicants', 'wholesale-ordering' ); ?></h1>
			<p><?php echo esc_html__( 'Review wholesale applications and manage wholesale access.', 'wholesale-ordering' ); ?></p>

			<form method="get" style="margin:16px 0;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<select name="status">
					<option value=""><?php echo esc_html__( 'All statuses', 'wholesale-ordering' ); ?></option>
					<?php foreach ( Config::wholesale_statuses() as $value ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( self::status_label( $value ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'wholesale-ordering' ), 'secondary', 'submit', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Applicant', 'wholesale-ordering' ); ?></th>
					<th><?php echo esc_html__( 'Email', 'wholesale-ordering' ); ?></th>
					<th><?php echo esc_html__( 'Company', 'wholesale-ordering' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th>
					<th><?php echo esc_html__( 'Application date', 'wholesale-ordering' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'wholesale-ordering' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="6"><?php echo esc_html__( 'No wholesale applications found.', 'wholesale-ordering' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $application ) : ?>
						<?php $user = get_user_by( 'id', $application->get_user_id() ); ?>
						<tr>
							<td><?php echo esc_html( trim( $application->get_first_name() . ' ' . $application->get_last_name() ) ); ?></td>
							<td><?php echo esc_html( $application->get_email() ); ?></td>
							<td><?php echo esc_html( $application->get_company_name() ); ?></td>
							<td><?php echo esc_html( self::status_label( $application->get_status() ) ); ?></td>
							<td><?php echo esc_html( self::format_date( $application->get_applied_at() ) ); ?></td>
							<td><a href="<?php echo esc_url( self::url( array( 'user_id' => $application->get_user_id() ) ) ); ?>"><?php echo esc_html__( 'View', 'wholesale-ordering' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total > self::PER_PAGE ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( array( 'page' => self::PAGE_SLUG, 'status' => $status, 'paged' => '%#%' ), admin_url( 'admin.php' ) ),
								'current'   => $page,
								'total'     => (int) ceil( $total / self::PER_PAGE ),
								'type'      => 'plain',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_detail( int $user_id ): void {
		$application = ( new ApplicationService() )->get( $user_id );
		if ( ! $application instanceof WholesaleApplication || ! $application->is_submitted() ) {
			self::error( __( 'The wholesale application could not be found.', 'wholesale-ordering' ) );
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			self::error( __( 'The applicant account could not be found.', 'wholesale-ordering' ) );
			return;
		}

		$status = $application->get_status();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Wholesale Application', 'wholesale-ordering' ); ?></h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php echo esc_html__( 'Back to Applicants', 'wholesale-ordering' ); ?></a></p>

			<table class="form-table">
				<tr><th><?php echo esc_html__( 'Applicant', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( trim( $application->get_first_name() . ' ' . $application->get_last_name() ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Email', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_email() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Phone', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_phone() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Company', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_company_name() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Business type', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_business_type() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Tax number', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_tax_number() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Registration number', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $application->get_registration_number() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( self::status_label( $status ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Applied', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( self::format_date( $application->get_applied_at() ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'Internal note', 'wholesale-ordering' ); ?></th><td><?php echo nl2br( esc_html( $application->get_internal_note() ) ); ?></td></tr>
			</table>

			<h2><?php echo esc_html__( 'Wholesale access actions', 'wholesale-ordering' ); ?></h2>
			<?php self::render_action_form( $user_id, $status ); ?>

			<p><a class="button" href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>"><?php echo esc_html__( 'Open WordPress customer profile', 'wholesale-ordering' ); ?></a></p>
		</div>
		<?php
	}

	private static function render_action_form( int $user_id, string $status ): void {
		$operations = array();
		if ( Config::STATUS_PENDING === $status ) {
			$operations = array(
				'approve' => __( 'Approve wholesale access', 'wholesale-ordering' ),
				'reject'  => __( 'Reject application', 'wholesale-ordering' ),
			);
		} elseif ( Config::STATUS_APPROVED === $status ) {
			$operations = array( 'suspend' => __( 'Suspend wholesale access', 'wholesale-ordering' ) );
		} elseif ( Config::STATUS_SUSPENDED === $status ) {
			$operations = array( 'reactivate' => __( 'Reactivate wholesale access', 'wholesale-ordering' ) );
		} elseif ( Config::STATUS_REJECTED === $status ) {
			$operations = array( 'reactivate' => __( 'Reactivate wholesale access', 'wholesale-ordering' ) );
		}

		foreach ( $operations as $operation => $label ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 16px;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>" />
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>
				<?php if ( in_array( $operation, array( 'approve', 'reject', 'suspend', 'reactivate' ), true ) ) : ?>
					<textarea name="internal_note" class="large-text" rows="3" placeholder="<?php echo esc_attr__( 'Optional internal review note', 'wholesale-ordering' ); ?>"></textarea>
				<?php endif; ?>
				<p><button type="submit" class="button <?php echo 'approve' === $operation ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button></p>
			</form>
		<?php endforeach;
	}

	public static function handle_action(): void {
		self::assert_capability();
		check_admin_referer( self::ACTION, self::NONCE );

		$user_id   = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$note      = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';

		$service = new ApplicationService();
		$reviewer_id = get_current_user_id();

		switch ( $operation ) {
			case 'approve':
				$result = $service->approve( $user_id, $reviewer_id, $note );
				break;
			case 'reject':
				$result = $service->reject( $user_id, $reviewer_id, $note );
				break;
			case 'suspend':
				$result = $service->suspend( $user_id, $reviewer_id, $note );
				break;
			case 'reactivate':
				$result = $service->reactivate( $user_id, $reviewer_id, $note );
				break;
			default:
				$result = new \WP_Error( 'invalid_application_action', __( 'Invalid application action.', 'wholesale-ordering' ) );
		}

		$url = self::url( array( 'user_id' => $user_id ) );
		self::redirect( $url, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Application updated successfully.', 'wholesale-ordering' ) );
	}

	public static function render_notice(): void {
		if ( empty( $_GET['wo_notice'] ) || empty( $_GET['wo_message'] ) ) {
			return;
		}
		$type = 'error' === sanitize_key( wp_unslash( $_GET['wo_notice'] ) ) ? 'error' : 'success';
		$message = sanitize_text_field( wp_unslash( $_GET['wo_message'] ) );
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function status_label( string $status ): string {
		$labels = array(
			Config::STATUS_PENDING   => __( 'Pending', 'wholesale-ordering' ),
			Config::STATUS_APPROVED  => __( 'Approved', 'wholesale-ordering' ),
			Config::STATUS_REJECTED  => __( 'Rejected', 'wholesale-ordering' ),
			Config::STATUS_SUSPENDED => __( 'Suspended', 'wholesale-ordering' ),
		);
		return $labels[ $status ] ?? __( 'Unknown', 'wholesale-ordering' );
	}

	private static function format_date( string $date ): string {
		if ( '' === $date ) {
			return '—';
		}
		$timestamp = strtotime( $date );
		return false === $timestamp ? $date : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function assert_capability(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage wholesale applications.', 'wholesale-ordering' ), esc_html__( 'Access denied', 'wholesale-ordering' ), array( 'response' => 403 ) );
		}
	}

	private static function error( string $message ): void {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>';
	}

	private static function redirect( string $url, string $type, string $message ): never {
		wp_safe_redirect( add_query_arg( array( 'wo_notice' => $type, 'wo_message' => $message ), $url ) );
		exit;
	}

	private function __construct() {}
}
