<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

final class OrdersAdmin {

	private const PAGE = 'wholesale-ordering-orders';
	private const ACTION = 'wholesale_ordering_order_action';
	private const NONCE = 'wholesale_ordering_order_action';

	public static function register(): void {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	public static function menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		add_submenu_page( 'wholesale-ordering', __( 'Orders', 'wholesale-ordering' ), __( 'Orders', 'wholesale-ordering' ), 'manage_woocommerce', self::PAGE, array( self::class, 'render' ) );
	}

	public static function render(): void {
		self::cap();
		$service = new OrderManagementService();
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		if ( $order_id ) { self::detail( $service, $order_id ); return; }
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$data = $service->list_orders( array( 'page' => $page, 'per_page' => 20, 'status' => $status, 'search' => $search ) );
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Orders', 'wholesale-ordering' ); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=shop_order' ) ); ?>"><?php echo esc_html__( 'Create manual order', 'wholesale-ordering' ); ?></a></p>
		<form method="get" style="margin:15px 0"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" /><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Order number, customer or email', 'wholesale-ordering' ); ?>" />
		<select name="status"><option value=""><?php echo esc_html__( 'All statuses', 'wholesale-ordering' ); ?></option><?php foreach ( wc_get_order_statuses() as $key => $label ) : $value = substr( $key, 3 ); ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Filter', 'wholesale-ordering' ), 'secondary', 'submit', false ); ?></form>
		<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_html__( 'Order', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Customer', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Date', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Payment', 'wholesale-ordering' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $data['items'] ) ) : ?><tr><td colspan="6"><?php echo esc_html__( 'No orders found.', 'wholesale-ordering' ); ?></td></tr><?php else : foreach ( $data['items'] as $order ) : $customer = $order['customer_id'] ? get_user_by( 'id', $order['customer_id'] ) : false; ?><tr><td><strong><a href="<?php echo esc_url( self::url( (int) $order['id'] ) ); ?>">#<?php echo esc_html( $order['number'] ); ?></a></strong></td><td><?php echo esc_html( $customer ? $customer->display_name : __( 'Guest', 'wholesale-ordering' ) ); ?></td><td><?php echo esc_html( self::date( $order['date'] ) ); ?></td><td><?php echo esc_html( wc_get_order_status_name( $order['status'] ) ); ?></td><td><?php echo wp_kses_post( wc_price( (float) $order['total'], array( 'currency' => $order['currency'] ) ) ); ?></td><td><?php echo esc_html( $order['payment'] ?: '—' ); ?></td></tr><?php endforeach; endif; ?></tbody></table>
		<?php self::pagination( (int) $data['pages'], $page, $search, $status ); ?></div>
		<?php
	}

	private static function detail( OrderManagementService $service, int $order_id ): void {
		$order = $service->get_order( $order_id );
		if ( is_wp_error( $order ) ) { self::error( $order->get_error_message() ); return; }
		?>
		<div class="wrap"><h1><?php echo esc_html( sprintf( __( 'Order #%s', 'wholesale-ordering' ), $order['number'] ) ); ?></h1><p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php echo esc_html__( 'Back to orders', 'wholesale-ordering' ); ?></a> &nbsp; <a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>"><?php echo esc_html__( 'Open WooCommerce order', 'wholesale-ordering' ); ?></a></p>
		<table class="form-table"><tr><th><?php echo esc_html__( 'Customer', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $order['customer_id'] ? ( self::customer_name( (int) $order['customer_id'] ) ) : __( 'Guest', 'wholesale-ordering' ) ); ?></td></tr><tr><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( wc_get_order_status_name( $order['status'] ) ); ?></td></tr><tr><th><?php echo esc_html__( 'Payment', 'wholesale-ordering' ); ?></th><td><?php echo esc_html( $order['payment_title'] ?: $order['payment_method'] ?: '—' ); ?></td></tr><tr><th><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $order['total'], array( 'currency' => $order['currency'] ) ) ); ?></td></tr></table>
		<h2><?php echo esc_html__( 'Order items and pricing context', 'wholesale-ordering' ); ?></h2><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Item', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Qty', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Subtotal', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Price context', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Charged unit price', 'wholesale-ordering' ); ?></th></tr></thead><tbody><?php foreach ( $order['items'] as $item ) : ?><tr><td><?php echo esc_html( $item['name'] ); ?></td><td><?php echo esc_html( (string) $item['quantity'] ); ?></td><td><?php echo wp_kses_post( wc_price( (float) $item['subtotal'], array( 'currency' => $order['currency'] ) ) ); ?></td><td><?php echo wp_kses_post( wc_price( (float) $item['total'], array( 'currency' => $order['currency'] ) ) ); ?></td><td><?php echo esc_html( $item['price_context'] ?: '—' ); ?></td><td><?php echo esc_html( $item['charged_unit_price'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table>
		<h2><?php echo esc_html__( 'Order actions', 'wholesale-ordering' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="operation" value="status" /><input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" /><?php wp_nonce_field( self::NONCE ); ?><select name="status"><?php foreach ( wc_get_order_statuses() as $key => $label ) : $value = substr( $key, 3 ); ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $order['status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><input class="regular-text" name="note" placeholder="<?php echo esc_attr__( 'Optional order note', 'wholesale-ordering' ); ?>" /><button class="button button-primary" type="submit"><?php echo esc_html__( 'Update status', 'wholesale-ordering' ); ?></button></form>
		<?php $remaining = max( 0, (float) $order['total'] - (float) wc_get_order( $order_id )->get_total_refunded() ); if ( $remaining > 0 ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px"><input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="operation" value="refund" /><input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" /><?php wp_nonce_field( self::NONCE ); ?><input type="number" step="0.01" min="0.01" max="<?php echo esc_attr( $remaining ); ?>" name="amount" placeholder="<?php echo esc_attr__( 'Refund amount', 'wholesale-ordering' ); ?>" required /><input class="regular-text" name="reason" placeholder="<?php echo esc_attr__( 'Refund reason', 'wholesale-ordering' ); ?>" /><label><input type="checkbox" name="restock" value="1" checked /> <?php echo esc_html__( 'Restock items', 'wholesale-ordering' ); ?></label><button class="button" type="submit"><?php echo esc_html__( 'Process refund', 'wholesale-ordering' ); ?></button></form><?php endif; ?>
		</div><?php
	}

	public static function handle(): void {
		self::cap(); check_admin_referer( self::NONCE );
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$op = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$service = new OrderManagementService(); $result = false;
		if ( 'status' === $op ) { $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''; $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : ''; $result = $service->update_status( $order_id, $status, $note ); }
		elseif ( 'refund' === $op ) { $amount = isset( $_POST['amount'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['amount'] ) ) : 0; $reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : ''; $result = $service->refund( $order_id, $amount, $reason, ! empty( $_POST['restock'] ) ); }
		else { $result = new \WP_Error( 'invalid_action', __( 'Invalid order action.', 'wholesale-ordering' ) ); }
		self::redirect( self::url( $order_id ), is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Order updated successfully.', 'wholesale-ordering' ) );
	}

	private static function pagination( int $pages, int $page, string $search, string $status ): void { if ( $pages <= 1 ) { return; } echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => self::PAGE, 'paged' => '%#%', 's' => $search, 'status' => $status ), admin_url( 'admin.php' ) ), 'current' => $page, 'total' => $pages, 'type' => 'plain' ) ) ) . '</div></div>'; }
	private static function url( int $id = 0 ): string { return add_query_arg( $id ? array( 'page' => self::PAGE, 'order_id' => $id ) : array( 'page' => self::PAGE ), admin_url( 'admin.php' ) ); }
	private static function customer_name( int $user_id ): string { $user = $user_id > 0 ? get_user_by( 'id', $user_id ) : false; return $user ? $user->display_name : __( 'Customer', 'wholesale-ordering' ); }
	private static function date( string $value ): string { $time = strtotime( $value ); return false === $time ? $value : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ); }
	private static function cap(): void { if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Access denied.', 'wholesale-ordering' ), '', array( 'response' => 403 ) ); } }
	private static function error( string $message ): void { echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div></div>'; }
	private static function redirect( string $url, string $type, string $message ): never { wp_safe_redirect( add_query_arg( array( 'wo_notice' => $type, 'wo_message' => $message ), $url ) ); exit; }
	public static function notice(): void { if ( empty( $_GET['wo_message'] ) ) { return; } $type = isset( $_GET['wo_notice'] ) && 'error' === sanitize_key( wp_unslash( $_GET['wo_notice'] ) ) ? 'error' : 'success'; echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wo_message'] ) ) ) . '</p></div>'; }
}
