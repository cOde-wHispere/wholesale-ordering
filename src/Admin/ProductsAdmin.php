<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

final class ProductsAdmin {

	private const PAGE = 'wholesale-ordering-products';
	private const ACTION = 'wholesale_ordering_product_action';
	private const NONCE = 'wholesale_ordering_product_action';

	public static function register(): void {
		if ( ! is_admin() ) { return; }
		add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	public static function menu(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		add_submenu_page( 'wholesale-ordering', __( 'Products', 'wholesale-ordering' ), __( 'Products', 'wholesale-ordering' ), 'manage_woocommerce', self::PAGE, array( self::class, 'render' ) );
	}

	public static function render(): void {
		self::cap();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$query = array( 'limit' => 20, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC', 'status' => $status ? $status : array( 'publish', 'draft', 'pending', 'private' ) );
		if ( $search ) { $query['search'] = $search; }
		$results = wc_get_products( $query );
		$products = is_object( $results ) && isset( $results->products ) ? $results->products : array();
		$total_pages = is_object( $results ) && isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'Products', 'wholesale-ordering' ); ?></h1>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><?php echo esc_html__( 'Add product', 'wholesale-ordering' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ); ?>"><?php echo esc_html__( 'Categories', 'wholesale-ordering' ); ?></a></p>
		<p class="description"><?php echo esc_html__( 'WooCommerce owns the catalogue. Use the native editor for full product fields; the Wholesale Price field remains the single V1 wholesale price.', 'wholesale-ordering' ); ?></p>
		<form method="get" style="margin:15px 0"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" /><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Product name or SKU', 'wholesale-ordering' ); ?>" /><select name="status"><option value=""><?php echo esc_html__( 'All statuses', 'wholesale-ordering' ); ?></option><?php foreach ( array( 'publish' => __( 'Published', 'wholesale-ordering' ), 'draft' => __( 'Draft', 'wholesale-ordering' ), 'pending' => __( 'Pending', 'wholesale-ordering' ), 'private' => __( 'Private / archived', 'wholesale-ordering' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Filter', 'wholesale-ordering' ), 'secondary', 'submit', false ); ?></form>
		<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_html__( 'Product', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'SKU', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Regular price', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Wholesale price', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Stock', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Status', 'wholesale-ordering' ); ?></th><th><?php echo esc_html__( 'Actions', 'wholesale-ordering' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $products ) ) : ?><tr><td colspan="7"><?php echo esc_html__( 'No products found.', 'wholesale-ordering' ); ?></td></tr><?php else : foreach ( $products as $product ) : $id = $product->get_id(); $archived = 'yes' === get_post_meta( $id, '_wholesale_ordering_archived', true ); $edit = get_edit_post_link( $id, '' ); ?><tr><td><strong><a href="<?php echo esc_url( $edit ?: '#' ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></strong></td><td><?php echo esc_html( $product->get_sku() ?: '—' ); ?></td><td><?php echo wp_kses_post( $product->get_regular_price() !== '' ? wc_price( (float) $product->get_regular_price(), array( 'currency' => get_woocommerce_currency() ) ) : '—' ); ?></td><td><?php $wp = ProductFields::get_wholesale_price( $product ); echo wp_kses_post( '' !== (string) $wp ? wc_price( (float) $wp, array( 'currency' => get_woocommerce_currency() ) ) : '—' ); ?></td><td><?php echo esc_html( $product->managing_stock() ? (string) $product->get_stock_quantity() : wc_get_stock_status_name( $product->get_stock_status() ) ); ?></td><td><?php echo esc_html( $archived ? __( 'Archived', 'wholesale-ordering' ) : wc_get_product_status_name( $product->get_status() ) ); ?></td><td><?php self::action_links( $id, $product->get_status(), $archived ); ?></td></tr><?php endforeach; endif; ?></tbody></table>
		<?php if ( $total_pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => self::PAGE, 'paged' => '%#%', 's' => $search, 'status' => $status ), admin_url( 'admin.php' ) ), 'current' => $page, 'total' => $total_pages, 'type' => 'plain' ) ) ); ?></div></div><?php endif; ?></div>
		<?php
	}

	private static function action_links( int $id, string $status, bool $archived ): void {
		$links = array();
		$links[] = self::link( $id, 'duplicate', __( 'Duplicate', 'wholesale-ordering' ) );
		if ( $archived ) { $links[] = self::link( $id, 'restore', __( 'Restore', 'wholesale-ordering' ) ); }
		else { if ( 'publish' === $status ) { $links[] = self::link( $id, 'unpublish', __( 'Unpublish', 'wholesale-ordering' ) ); } else { $links[] = self::link( $id, 'publish', __( 'Publish', 'wholesale-ordering' ) ); } $links[] = self::link( $id, 'archive', __( 'Archive', 'wholesale-ordering' ) ); }
		echo implode( ' | ', $links );
	}

	private static function link( int $id, string $op, string $label ): string { $url = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'product_id' => $id, 'operation' => $op ), admin_url( 'admin-post.php' ) ), self::NONCE ); return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'; }

	public static function handle(): void {
		self::cap(); check_admin_referer( self::NONCE );
		$id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0; $op = isset( $_REQUEST['operation'] ) ? sanitize_key( wp_unslash( $_REQUEST['operation'] ) ) : '';
		$service = new ProductManagementService(); $map = array( 'publish' => 'publish', 'unpublish' => 'unpublish', 'archive' => 'archive', 'restore' => 'restore', 'duplicate' => 'duplicate' );
		if ( ! $id || ! isset( $map[ $op ] ) ) { self::redirect( self::url(), 'error', __( 'Invalid product action.', 'wholesale-ordering' ) ); }
		$result = $service->{$map[ $op ]}( $id ); $url = is_wp_error( $result ) || 'duplicate' !== $op ? self::url() : self::url( (int) $result->get_id() );
		self::redirect( $url, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Product action completed.', 'wholesale-ordering' ) );
	}

	private static function url( int $id = 0 ): string { return admin_url( $id ? 'post.php?post=' . $id . '&action=edit' : 'admin.php?page=' . self::PAGE ); }
	private static function cap(): void { if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Access denied.', 'wholesale-ordering' ), '', array( 'response' => 403 ) ); } }
	private static function redirect( string $url, string $type, string $message ): never { wp_safe_redirect( add_query_arg( array( 'wo_notice' => $type, 'wo_message' => $message ), $url ) ); exit; }
	public static function notice(): void { if ( empty( $_GET['wo_message'] ) ) { return; } $type = isset( $_GET['wo_notice'] ) && 'error' === sanitize_key( wp_unslash( $_GET['wo_notice'] ) ) ? 'error' : 'success'; echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wo_message'] ) ) ) . '</p></div>'; }
}
