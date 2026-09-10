<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

final class CustomersAdmin {

	private const PAGE = 'wholesale-ordering-customers';
	private const ACTION = 'wholesale_ordering_customer_action';
	private const NONCE = 'wholesale_ordering_customer_action';

	public static function register(): void {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	public static function menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		add_submenu_page( 'wholesale-ordering', __( 'Customers', 'wholesale-ordering' ), __( 'Customers', 'wholesale-ordering' ), 'manage_woocommerce', self::PAGE, array( self::class, 'render' ) );
	}

	public static function render(): void {
		self::cap();
		$service = new CustomerManagementService();
		$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
		if ( $user_id ) { self::detail( $service, $user_id ); return; }

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['wholesale_status'] ) ? sanitize_key( wp_unslash( $_GET['wholesale_status'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$data   = $service->list_customers( array( 'search' => $search, 'wholesale_status' => $status, 'page' => $page, 'per_page' => 20 ) );
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Customers', 'wholesale-ordering' ); ?></h1>
		<form method="get" style="margin:15px 0"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Name or email', 'wholesale-ordering' ); ?>" />
			<select name="wholesale_status"><option value=""><?php echo esc_html__( 'All wholesale statuses', 'wholesale-ordering' ); ?></option>
			<?php foreach ( Config::wholesale_statuses() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( self::status_label( $value ) ); ?></option><?php endforeach; ?></select>
			<?php submit_button( __( 'Filter', 'wholesale-ordering' ), 'secondary', 'submit', false ); ?>
		</form>
		<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_html__( 'Customer', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Email', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Company', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Wholesale status', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Registered', 'wholesale-ordering' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $data['items'] ) ) : ?><tr><td colspan="5"><?php echo esc_html__( 'No customers found.', 'wholesale-ordering' ); ?></td></tr><?php else : foreach ( $data['items'] as $customer ) : ?>
		<tr><td><strong><a href="<?php echo esc_url( self::url( (int) $customer['id'] ) ); ?>"><?php echo esc_html( $customer['name'] ); ?></a></strong></td><td><?php echo esc_html( $customer['email'] ); ?></td><td><?php echo esc_html( $customer['company'] ); ?></td><td><?php echo esc_html( self::status_label( $customer['wholesale_status'] ) ); ?></td><td><?php echo esc_html( self::date( $customer['registered'] ) ); ?></td></tr>
		<?php endforeach; endif; ?></tbody></table>
		<?php self::pagination( (int) $data['pages'], $page, $search, $status ); ?></div>
		<?php
	}

	private static function detail( CustomerManagementService $service, int $user_id ): void {
		$data = $service->get_customer( $user_id );
		if ( is_wp_error( $data ) ) { self::error( $data->get_error_message() ); return; }
		$status = $data['wholesale_status'];
		?>
		<div class="wrap"><h1><?php echo esc_html( $data['name'] ); ?></h1><p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php echo esc_html__( 'Back to customers', 'wholesale-ordering' ); ?></a></p>
		<table class="form-table"><tr><th><?php echo esc_html__( 'Email', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $data['email'] ); ?></td></tr><tr><th><?php echo esc_html__( 'Username', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $data['username'] ); ?></td></tr><tr><th><?php echo esc_html__( 'Wholesale status', 'wholesale-ordering' ); ?></th><td><strong><?php echo esc_html( self::status_label( $status ) ); ?></strong></td></tr><tr><th><?php echo esc_html__( 'Roles', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( implode( ', ', $data['roles'] ) ); ?></td></tr></table>
		<h2><?php echo esc_html__( 'Wholesale application', 'wholesale-ordering' ); ?></h2><?php if ( empty( $data['application'] ) ) : ?><p><?php echo esc_html__( 'No application found.', 'wholesale-ordering' ); ?></p><?php else : ?><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wholesale-ordering-applications&user_id=' . $user_id ) ); ?>"><?php echo esc_html__( 'View application', 'wholesale-ordering' ); ?></a></p><?php endif; ?>
		<?php if ( in_array( $status, array( Config::STATUS_APPROVED, Config::STATUS_SUSPENDED ), true ) ) : ?>
			<?php self::action_form( $user_id, Config::STATUS_APPROVED === $status ? 'suspend' : 'reactivate', Config::STATUS_APPROVED === $status ? __( 'Suspend wholesale access', 'wholesale-ordering' ) : __( 'Reactivate wholesale access', 'wholesale-ordering' ) ); ?>
		<?php elseif ( Config::STATUS_PENDING === $status ) : ?>
			<?php self::action_form( $user_id, 'approve', __( 'Approve wholesale access', 'wholesale-ordering' ), true ); ?>
			<?php self::action_form( $user_id, 'reject', __( 'Reject application', 'wholesale-ordering' ), true ); ?>
		<?php endif; ?>
		<h2><?php echo esc_html__( 'Order history', 'wholesale-ordering' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Order', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Date', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $data['orders'] ) ) : ?><tr><td colspan="4"><?php echo esc_html__( 'No orders found.', 'wholesale-ordering' ); ?></td></tr><?php else : foreach ( $data['orders'] as $order ) : ?><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wholesale-ordering-orders&order_id=' . absint( $order['id'] ) ) ); ?>">#<?php echo esc_html( $order['number'] ); ?></a></td><td><?php echo esc_html( self::date( $order['date'] ) ); ?></td><td><?php echo esc_html( $order['status'] ); ?></td><td><?php echo wp_kses_post( wc_price( (float) $order['total'], array( 'currency' => $order['currency'] ) ) ); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
		<?php
	}

	private static function action_form( int $user_id, string $operation, string $label, bool $note = false ): void {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0"><input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>" /><input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" /><?php wp_nonce_field( self::NONCE ); ?><?php if ( $note ) : ?><textarea name="internal_note" rows="3" class="large-text" placeholder="<?php echo esc_attr__( 'Internal review note', 'wholesale-ordering' ); ?>"></textarea><?php endif; ?><button class="button <?php echo in_array( $operation, array( 'approve', 'reactivate' ), true ) ? 'button-primary' : ''; ?>" type="submit"><?php echo esc_html( $label ); ?></button></form><?php
	}

	public static function handle(): void {
		self::cap(); check_admin_referer( self::NONCE );
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$op = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$note = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';
		$service = new CustomerManagementService(); $reviewer = get_current_user_id();
		$methods = array( 'approve', 'reject', 'suspend', 'reactivate' );
		if ( ! in_array( $op, $methods, true ) || $user_id <= 0 ) { self::redirect( self::url( $user_id ), 'error', __( 'Invalid customer action.', 'wholesale-ordering' ) ); }
		$result = $service->{$op}( $user_id, $reviewer, $note );
		self::redirect( self::url( $user_id ), is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Customer wholesale status updated.', 'wholesale-ordering' ) );
	}

	private static function pagination( int $pages, int $page, string $search, string $status ): void { if ( $pages <= 1 ) { return; } echo '<div class="tablenav"><div class="tablenav-pages">'; echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => self::PAGE, 'paged' => '%#%', 's' => $search, 'wholesale_status' => $status ), admin_url( 'admin.php' ) ), 'current' => $page, 'total' => $pages, 'type' => 'plain' ) ) ); echo '</div></div>'; }
	private static function url( int $id = 0 ): string { return add_query_arg( $id ? array( 'page' => self::PAGE, 'user_id' => $id ) : array( 'page' => self::PAGE ), admin_url( 'admin.php' ) ); }
	private static function date( string $value ): string { $time = strtotime( $value ); return false === $time ? $value : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ); }
	private static function status_label( string $status ): string { $labels = array( Config::STATUS_PENDING => __( 'Pending', 'wholesale-ordering' ), Config::STATUS_APPROVED => __( 'Approved', 'wholesale-ordering' ), Config::STATUS_REJECTED => __( 'Rejected', 'wholesale-ordering' ), Config::STATUS_SUSPENDED => __( 'Suspended', 'wholesale-ordering' ) ); return $labels[ $status ] ?? __( 'None', 'wholesale-ordering' ); }
	private static function cap(): void { if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Access denied.', 'wholesale-ordering' ), '', array( 'response' => 403 ) ); } }
	private static function error( string $message ): void { echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>'; }
	private static function redirect( string $url, string $type, string $message ): never { wp_safe_redirect( add_query_arg( array( 'wo_notice' => $type, 'wo_message' => $message ), $url ) ); exit; }
	public static function notice(): void { if ( empty( $_GET['wo_message'] ) ) { return; } $type = isset( $_GET['wo_notice'] ) && 'error' === sanitize_key( wp_unslash( $_GET['wo_notice'] ) ) ? 'error' : 'success'; echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wo_message'] ) ) ) . '</p></div>'; }
}
