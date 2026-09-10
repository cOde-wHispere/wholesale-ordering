<?php

namespace WholesaleOrdering;

use WholesaleOrdering\Admin\Admin;
use WholesaleOrdering\Auth\Registration;
use WholesaleOrdering\Account\Account;
use WholesaleOrdering\Cart\CartIntegration;
use WholesaleOrdering\Checkout\CheckoutIntegration;
use WholesaleOrdering\CLI\ProductSeedCommand;
use WholesaleOrdering\Frontend\Frontend;
use WholesaleOrdering\Infrastructure\Config;
use WholesaleOrdering\Infrastructure\Logger;
use WholesaleOrdering\Infrastructure\MigrationRunner;
use WholesaleOrdering\Infrastructure\Requirements;
use WholesaleOrdering\Orders\OrderIntegration;
use WholesaleOrdering\Pricing\WooCommercePricingIntegration;
use WholesaleOrdering\Products\ProductFields;
use WholesaleOrdering\Security\DocumentSecurity;
use WholesaleOrdering\Security\PricingLeakageProtection;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin bootstrap class.
 */
final class Plugin {

	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		if ( ! Requirements::php_compatible() ) {
			Logger::error(
				'Plugin initialization aborted: PHP version requirement not satisfied.',
				array(
					'required' => '8.3',
					'current'  => PHP_VERSION,
				)
			);

			add_action( 'admin_notices', array( self::class, 'php_missing_notice' ) );
			return;
		}

		add_action( 'plugins_loaded', array( self::class, 'plugins_loaded' ), 20 );
	}

	public static function plugins_loaded(): void {
		if ( ! Requirements::woocommerce_available() ) {
			Logger::warning( 'Wholesale Ordering initialization skipped: WooCommerce is not available.' );
			add_action( 'admin_notices', array( self::class, 'woocommerce_missing_notice' ) );
			return;
		}

		self::register_runtime();

		update_option( Config::OPTION_VERSION, Config::VERSION, false );

		Logger::info(
			'Wholesale Ordering plugin initialized.',
			array( 'version' => Config::VERSION )
		);
	}

	/**
	 * Register runtime services and hooks.
	 *
	 * WooCommerce remains authoritative for catalogue, orders, totals and
	 * store configuration. The plugin adds wholesale-specific behavior and
	 * its Shop Manager operational interface around those systems.
	 */
	private static function register_runtime(): void {
		MigrationRunner::run();

		// Phase 5 Shop Manager administration.
		Admin::register();

		// WooCommerce product fields plus the V1 single Wholesale Price field.
		ProductFields::register();

		$pricing_integration = new WooCommercePricingIntegration();
		$pricing_integration->register();

		( new CartIntegration() )->register();
		( new CheckoutIntegration() )->register();
		( new OrderIntegration() )->register();

		// Protect REST/structured-data/authenticated-cache exposure of pricing.
		PricingLeakageProtection::register();

		// Development/staging fixture command; no direct DB writes from Python.
		ProductSeedCommand::register();

		// Protected supporting-document boundary.
		DocumentSecurity::register();

		// Phase 6 customer-facing experience.
		Frontend::register();
		Account::register();
		Registration::register();
	}

	public static function php_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><strong><?php echo esc_html__( 'Wholesale Ordering', 'wholesale-ordering' ); ?></strong> <?php echo esc_html__( 'requires PHP 8.3 or higher.', 'wholesale-ordering' ); ?></p></div>
		<?php
	}

	public static function woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error"><p><strong><?php echo esc_html__( 'Wholesale Ordering', 'wholesale-ordering' ); ?></strong> <?php echo esc_html__( 'requires WooCommerce to be installed and active.', 'wholesale-ordering' ); ?></p></div>
		<?php
	}

	public static function activate(): void {
		if ( ! Requirements::php_compatible() ) {
			wp_die(
				esc_html__( 'Wholesale Ordering requires PHP 8.3 or higher.', 'wholesale-ordering' ),
				esc_html__( 'Plugin activation failed', 'wholesale-ordering' ),
				array( 'back_link' => true )
			);
		}

		update_option( Config::OPTION_VERSION, Config::VERSION, false );
		MigrationRunner::run();

		Logger::info(
			'Wholesale Ordering plugin activated.',
			array( 'version' => Config::VERSION )
		);
	}

	public static function deactivate(): void {
		Logger::info(
			'Wholesale Ordering plugin deactivated.',
			array( 'version' => Config::VERSION )
		);
	}

	private function __construct() {}
}
