<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Admin\ApplicationsAdmin;
use WholesaleOrdering\Admin\CustomersAdmin;
use WholesaleOrdering\Admin\OrdersAdmin;
use WholesaleOrdering\Admin\ProductsAdmin;
use WholesaleOrdering\Admin\ReportsAdmin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Wholesale Ordering administration interface.
 *
 * Phase 5 provides the Shop Manager operational surface while leaving
 * business/domain ownership with the existing services and WooCommerce.
 */
final class Admin {

	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( self::class, 'register_menu' ), 10 );
		add_action( 'admin_menu', array( self::class, 'remove_default_dashboard_submenu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );

		ApplicationsAdmin::register();
		CustomersAdmin::register();
		OrdersAdmin::register();
		ProductsAdmin::register();
		ReportsAdmin::register();
	}

	public static function register_menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_menu_page(
			__( 'Wholesale Ordering', 'wholesale-ordering' ),
			__( 'Wholesale Ordering', 'wholesale-ordering' ),
			'manage_woocommerce',
			'wholesale-ordering',
			array( self::class, 'render_dashboard' ),
			'dashicons-store',
			56
		);

		add_submenu_page(
			'wholesale-ordering',
			__( 'Dashboard', 'wholesale-ordering' ),
			__( 'Dashboard', 'wholesale-ordering' ),
			'manage_woocommerce',
			'wholesale-ordering-dashboard',
			array( self::class, 'render_dashboard' )
		);

		add_submenu_page(
			'wholesale-ordering',
			__( 'Store Operations', 'wholesale-ordering' ),
			__( 'Store Operations', 'wholesale-ordering' ),
			'manage_woocommerce',
			'wholesale-ordering-store-operations',
			array( self::class, 'render_store_operations' )
		);
	}

	public static function remove_default_dashboard_submenu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// add_menu_page() creates an automatic submenu entry using the parent
		// slug. Remove that duplicate so the visible button is explicitly
		// labelled Dashboard. The top-level menu still opens the dashboard.
		remove_submenu_page( 'wholesale-ordering', 'wholesale-ordering' );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'wholesale-ordering' ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
	}

	public static function render_dashboard(): void {
		self::assert_capability();

		$service = new DashboardService();
		$data    = $service->get_dashboard();
		$orders  = $data['orders'] ?? array();
		$apps    = $data['applications'] ?? array();
		$revenue = $data['revenue'] ?? array();
		$stock   = $data['low_stock'] ?? array();
		$users   = $data['recent_customers'] ?? array();
		$alerts  = $data['alerts'] ?? array();

		$orders_url = admin_url( 'admin.php?page=wholesale-ordering-orders' );
		$apps_url   = admin_url( 'admin.php?page=wholesale-ordering-applications' );
		$customers  = admin_url( 'admin.php?page=wholesale-ordering-customers' );
		$products   = admin_url( 'admin.php?page=wholesale-ordering-products' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Wholesale Ordering Dashboard', 'wholesale-ordering' ); ?></h1>
			<p><?php echo esc_html__( 'Shop Manager operational overview. Order and revenue figures are read from WooCommerce.', 'wholesale-ordering' ); ?></p>

			<?php self::render_styles(); ?>

			<?php if ( empty( $alerts ) ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'No operational alerts require attention.', 'wholesale-ordering' ); ?></p></div>
			<?php else : ?>
				<div class="wo-admin-alerts">
					<?php foreach ( $alerts as $alert ) : ?>
						<div class="notice notice-<?php echo esc_attr( 'error' === ( $alert['severity'] ?? '' ) ? 'error' : 'warning' ); ?> inline">
							<p><?php echo esc_html( $alert['message'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

			<div class="wo-admin-grid wo-admin-grid--metrics">
				<?php self::metric_card( __( 'New / processing orders', 'wholesale-ordering' ), (int) ( $orders['new_orders'] ?? 0 ), $orders_url, 'dashicons-cart' ); ?>
				<?php self::metric_card( __( 'Pending payments', 'wholesale-ordering' ), (int) ( $orders['pending_payment'] ?? 0 ), $orders_url, 'dashicons-clock' ); ?>
				<?php self::metric_card( __( 'Pending wholesale applications', 'wholesale-ordering' ), (int) ( $apps['pending'] ?? 0 ), $apps_url, 'dashicons-businessman' ); ?>
				<?php self::metric_card( __( 'Low-stock products', 'wholesale-ordering' ), count( $stock ), $products, 'dashicons-warning' ); ?>
			</div>

			<div class="wo-admin-grid wo-admin-grid--two">
				<section class="wo-admin-card">
					<h2><?php echo esc_html__( 'Revenue summary', 'wholesale-ordering' ); ?></h2>
					<div class="wo-admin-revenue">
						<strong><?php echo wp_kses_post( wc_price( (float) ( $revenue['amount'] ?? 0 ), array( 'currency' => $revenue['currency'] ?? get_woocommerce_currency() ) ) ); ?></strong>
						<span><?php echo esc_html( sprintf( _n( '%d qualifying order', '%d qualifying orders', (int) ( $revenue['orders'] ?? 0 ), 'wholesale-ordering' ), (int) ( $revenue['orders'] ?? 0 ) ) ); ?></span>
					</div>
					<p class="description"><?php echo esc_html__( 'Includes processing, completed and on-hold orders; excludes cancelled and failed orders.', 'wholesale-ordering' ); ?></p>
				</section>

				<section class="wo-admin-card">
					<h2><?php echo esc_html__( 'Quick operations', 'wholesale-ordering' ); ?></h2>
					<p class="wo-admin-actions">
						<a class="button button-primary" href="<?php echo esc_url( $orders_url ); ?>"><?php echo esc_html__( 'Manage orders', 'wholesale-ordering' ); ?></a>
						<a class="button" href="<?php echo esc_url( $apps_url ); ?>"><?php echo esc_html__( 'Review applications', 'wholesale-ordering' ); ?></a>
						<a class="button" href="<?php echo esc_url( $customers ); ?>"><?php echo esc_html__( 'Customers', 'wholesale-ordering' ); ?></a>
						<a class="button" href="<?php echo esc_url( $products ); ?>"><?php echo esc_html__( 'Products', 'wholesale-ordering' ); ?></a>
					</p>
				</section>
			</div>

			<div class="wo-admin-grid wo-admin-grid--two">
				<section class="wo-admin-card">
					<h2><?php echo esc_html__( 'Low-stock products', 'wholesale-ordering' ); ?></h2>
					<?php if ( empty( $stock ) ) : ?>
						<p><?php echo esc_html__( 'No products are currently below their WooCommerce low-stock threshold.', 'wholesale-ordering' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php echo esc_html__( 'Product', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'SKU', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Stock', 'wholesale-ordering' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $stock, 0, 8 ) as $product ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $product['edit_url'] ?? '#' ); ?>"><?php echo esc_html( $product['name'] ?? '' ); ?></a></td>
									<td><?php echo esc_html( $product['sku'] ?? '—' ); ?></td>
									<td><?php echo esc_html( null === ( $product['stock'] ?? null ) ? __( 'N/A', 'wholesale-ordering' ) : (string) $product['stock'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>

				<section class="wo-admin-card">
					<h2><?php echo esc_html__( 'Recent customers', 'wholesale-ordering' ); ?></h2>
					<?php if ( empty( $users ) ) : ?>
						<p><?php echo esc_html__( 'No customers found.', 'wholesale-ordering' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead><tr><th><?php echo esc_html__( 'Customer', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Email', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Registered', 'wholesale-ordering' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $users, 0, 8 ) as $customer ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wholesale-ordering-customers&user_id=' . absint( $customer['id'] ?? 0 ) ) ); ?>"><?php echo esc_html( $customer['name'] ?? '' ); ?></a></td>
									<td><?php echo esc_html( $customer['email'] ?? '' ); ?></td>
									<td><?php echo esc_html( self::format_date( $customer['registered'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	public static function render_store_operations(): void {
		self::assert_capability();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Store Operations', 'wholesale-ordering' ); ?></h1>
			<p><?php echo esc_html__( 'WooCommerce remains authoritative for catalogue, orders, taxes, shipping, payments, reports and store settings.', 'wholesale-ordering' ); ?></p>
			<?php self::render_styles(); ?>
			<div class="wo-admin-grid wo-admin-grid--two">
				<?php
				self::operation_link( __( 'WooCommerce Products', 'wholesale-ordering' ), admin_url( 'edit.php?post_type=product' ), __( 'Manage catalogue, categories, images, stock, prices and product data.', 'wholesale-ordering' ) );
				self::operation_link( __( 'WooCommerce Orders', 'wholesale-ordering' ), admin_url( 'edit.php?post_type=shop_order' ), __( 'Use WooCommerce for its full order workflow and supported payment/refund operations.', 'wholesale-ordering' ) );
				self::operation_link( __( 'Coupons', 'wholesale-ordering' ), admin_url( 'edit.php?post_type=shop_coupon' ), __( 'Create and manage WooCommerce coupons.', 'wholesale-ordering' ) );
				self::operation_link( __( 'WooCommerce Settings', 'wholesale-ordering' ), admin_url( 'admin.php?page=wc-settings' ), __( 'Taxes, shipping, payment methods, emails and store configuration.', 'wholesale-ordering' ) );
				self::operation_link( __( 'WooCommerce Reports / Analytics', 'wholesale-ordering' ), admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ), __( 'Open WooCommerce reporting and analytics.', 'wholesale-ordering' ) );
				?>
			</div>
		</div>
		<?php
	}

	private static function metric_card( string $label, int $value, string $url, string $icon ): void {
		?>
		<div class="wo-admin-card wo-admin-metric">
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( (string) $value ); ?></strong></div>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'View', 'wholesale-ordering' ); ?></a>
		</div>
		<?php
	}

	private static function operation_link( string $title, string $url, string $description ): void {
		?>
		<section class="wo-admin-card">
			<h2><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Open', 'wholesale-ordering' ); ?></a>
		</section>
		<?php
	}

	private static function format_date( string $value ): string {
		$time = strtotime( $value );
		return false === $time ? $value : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
	}

	private static function assert_capability(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access Wholesale Ordering administration.', 'wholesale-ordering' ),
				esc_html__( 'Access denied', 'wholesale-ordering' ),
				array( 'response' => 403 )
			);
		}
	}

	private static function render_styles(): void {
		?>
		<style>
		.wo-admin-grid{display:grid;gap:16px;margin:20px 0}.wo-admin-grid--metrics{grid-template-columns:repeat(4,minmax(0,1fr))}.wo-admin-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}.wo-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;box-sizing:border-box}.wo-admin-card h2{margin-top:0}.wo-admin-metric{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center}.wo-admin-metric .dashicons{font-size:28px;width:28px;height:28px}.wo-admin-metric div{display:flex;flex-direction:column}.wo-admin-metric div span{color:#646970}.wo-admin-metric div strong{font-size:26px;line-height:1.2}.wo-admin-revenue{display:flex;align-items:baseline;gap:14px}.wo-admin-revenue strong{font-size:30px}.wo-admin-revenue span{color:#646970}.wo-admin-actions{display:flex;gap:8px;flex-wrap:wrap}.wo-admin-alerts .notice{margin-left:0}.wo-admin-card table{margin-top:10px}@media(max-width:1000px){.wo-admin-grid--metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.wo-admin-grid--two,.wo-admin-grid--metrics{grid-template-columns:1fr}}
		</style>
		<?php
	}

	private function __construct() {}
}
