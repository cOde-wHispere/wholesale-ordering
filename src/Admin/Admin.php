<?php

namespace WholesaleOrdering\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Wholesale Ordering administration interface.
 *
 * Phase 5 foundation only.
 *
 * Business/domain operations remain delegated to their existing
 * application services and WooCommerce.
 */
final class Admin {

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
			array( self::class, 'register_menu' )
		);
	}

	/**
	 * Register the Wholesale Ordering admin menu.
	 *
	 * @return void
	 */
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
	}

	/**
	 * Render the initial Phase 5 dashboard boundary.
	 *
	 * @return void
	 */
	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to access Wholesale Ordering administration.',
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

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Wholesale Ordering', 'wholesale-ordering' ); ?></h1>

			<p>
				<?php
				echo esc_html__(
					'Wholesale Ordering administration is ready for Phase 5 operations.',
					'wholesale-ordering'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}