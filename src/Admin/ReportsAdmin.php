<?php

namespace WholesaleOrdering\Admin;

defined( 'ABSPATH' ) || exit;

final class ReportsAdmin {

	private const PAGE = 'wholesale-ordering-reports';
	private const ACTION = 'wholesale_ordering_export_orders';
	private const NONCE = 'wholesale_ordering_export_orders';

	public static function register(): void {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'export' ) );
	}

	public static function menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		add_submenu_page( 'wholesale-ordering', __( 'Reports & Export', 'wholesale-ordering' ), __( 'Reports & Export', 'wholesale-ordering' ), 'manage_woocommerce', self::PAGE, array( self::class, 'render' ) );
	}

	public static function render(): void {
		self::cap();
		$service = new ReportingService();
		$args = self::filters();
		$summary = $service->get_sales_summary( $args );
		$rows = $service->get_order_report( $args );
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Reports & Export', 'wholesale-ordering' ); ?></h1>
		<form method="get" style="margin:15px 0"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" /><select name="status"><option value=""><?php echo esc_html__( 'All statuses', 'wholesale-ordering' ); ?></option><?php foreach ( wc_get_order_statuses() as $key => $label ) : $value = substr( $key, 3 ); ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $args['status'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><label><?php echo esc_html__( 'From', 'wholesale-ordering' ); ?> <input type="date" name="date_after" value="<?php echo esc_attr( $args['date_after'] ?? '' ); ?>" /></label><label><?php echo esc_html__( 'To', 'wholesale-ordering' ); ?> <input type="date" name="date_before" value="<?php echo esc_attr( $args['date_before'] ?? '' ); ?>" /></label><?php submit_button( __( 'Run report', 'wholesale-ordering' ), 'secondary', 'submit', false ); ?></form>
		<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => self::ACTION ), $args ), admin_url( 'admin-post.php' ) ), self::NONCE ) ); ?>"><?php echo esc_html__( 'Export orders CSV', 'wholesale-ordering' ); ?></a></p>
		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0"><div class="card" style="padding:18px;min-width:180px"><strong><?php echo esc_html( (string) $summary['orders'] ); ?></strong><br /><?php echo esc_html__( 'Orders', 'wholesale-ordering' ); ?></div><div class="card" style="padding:18px;min-width:180px"><strong><?php echo wp_kses_post( wc_price( (float) $summary['total'], array( 'currency' => $summary['currency'] ) ) ); ?></strong><br /><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></div><div class="card" style="padding:18px;min-width:180px"><strong><?php echo wp_kses_post( wc_price( (float) $summary['tax'], array( 'currency' => $summary['currency'] ) ) ); ?></strong><br /><?php echo esc_html__( 'Tax', 'wholesale-ordering' ); ?></div><div class="card" style="padding:18px;min-width:180px"><strong><?php echo wp_kses_post( wc_price( (float) $summary['shipping'], array( 'currency' => $summary['currency'] ) ) ); ?></strong><br /><?php echo esc_html__( 'Shipping', 'wholesale-ordering' ); ?></div></div>
		<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_html__( 'Order', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Date', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Customer', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Total', 'wholesale-ordering' ); ?></th></tr></thead><tbody><?php if ( empty( $rows ) ) : ?><tr><td colspan="5"><?php echo esc_html__( 'No orders match the report filters.', 'wholesale-ordering' ); ?></td></tr><?php else : foreach ( array_slice( $rows, 0, 100 ) as $row ) : ?><tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wholesale-ordering-orders&order_id=' . absint( $row['order_id'] ) ) ); ?>">#<?php echo esc_html( $row['order_number'] ); ?></a></td><td><?php echo esc_html( $row['date'] ); ?></td><td><?php echo esc_html( $row['customer_id'] ? ( self::customer_name( (int) $row['customer_id'] ) ) : __( 'Guest', 'wholesale-ordering' ) ); ?></td><td><?php echo esc_html( wc_get_order_status_name( $row['status'] ) ); ?></td><td><?php echo wp_kses_post( wc_price( (float) $row['total'], array( 'currency' => $row['currency'] ) ) ); ?></td></tr><?php endforeach; endif; ?></tbody></table>
		</div><?php
	}

	public static function export(): void {
		self::cap(); check_admin_referer( self::NONCE );
		$service = new ReportingService(); $csv = $service->export_orders_csv( self::filters() );
		if ( '' === $csv ) { wp_die( esc_html__( 'The report could not be generated.', 'wholesale-ordering' ) ); }
		nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="wholesale-orders-' . gmdate( 'Y-m-d-His' ) . '.csv"' ); echo $csv; exit;
	}

	private static function customer_name( int $user_id ): string { $user = $user_id > 0 ? get_user_by( 'id', $user_id ) : false; return $user ? $user->display_name : __( 'Customer', 'wholesale-ordering' ); }
	private static function filters(): array { $args = array(); foreach ( array( 'status', 'date_after', 'date_before' ) as $key ) { if ( isset( $_REQUEST[ $key ] ) && '' !== $_REQUEST[ $key ] ) { $args[ $key ] = 'status' === $key ? sanitize_key( wp_unslash( $_REQUEST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ); } } return $args; }
	private static function cap(): void { if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Access denied.', 'wholesale-ordering' ), '', array( 'response' => 403 ) ); } }
}
