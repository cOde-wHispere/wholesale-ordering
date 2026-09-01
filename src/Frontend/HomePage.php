<?php

namespace WholesaleOrdering\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing homepage presentation.
 *
 * The homepage is intentionally a presentation layer.
 *
 * WooCommerce remains authoritative for:
 *
 * - products;
 * - product queries;
 * - catalogue visibility;
 * - product rendering;
 * - prices;
 * - customer-specific eligible pricing;
 * - cart and checkout behavior.
 *
 * This class does not calculate or read wholesale prices and does not
 * create a second product query engine.
 */
final class HomePage {

	/**
	 * Register homepage integration.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( is_admin() ) {
			return;
		}

		add_filter(
			'template_include',
			array( self::class, 'template_include' ),
			99
		);

		add_filter(
			'body_class',
			array( self::class, 'add_body_class' )
		);
	}

	/**
	 * Use the plugin homepage template for the site's front page.
	 *
	 * The template still calls the active theme's get_header() and
	 * get_footer(), so the site remains compatible with the active theme.
	 *
	 * @param string $template Current template path.
	 *
	 * @return string
	 */
	public static function template_include( string $template ): string {
		if ( ! is_front_page() ) {
			return $template;
		}

		$plugin_template = dirname( __DIR__, 2 ) . '/templates/home.php';

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Add stable homepage body classes.
	 *
	 * @param array<int,string> $classes Body classes.
	 *
	 * @return array<int,string>
	 */
	public static function add_body_class( array $classes ): array {
		if ( is_front_page() ) {
			$classes[] = 'wholesale-ordering-home';
		}

		return $classes;
	}

	/**
	 * Render the WooCommerce product showcase.
	 *
	 * This deliberately delegates product selection and rendering to
	 * WooCommerce instead of implementing a second product loop.
	 *
	 * @return void
	 */
	public static function render_products(): void {
		if ( ! function_exists( 'do_shortcode' ) ) {
			return;
		}

		echo do_shortcode(
			'[products limit="4" columns="4" orderby="date" order="DESC" visibility="catalog"]'
		);
	}

	/**
	 * Render the WooCommerce category showcase.
	 *
	 * WooCommerce owns category discovery and rendering.
	 *
	 * @return void
	 */
	public static function render_categories(): void {
		if ( ! function_exists( 'do_shortcode' ) ) {
			return;
		}

		echo do_shortcode(
			'[product_categories number="6" columns="3" parent="0" hide_empty="1"]'
		);
	}

	/**
	 * Return the shop URL.
	 *
	 * @return string
	 */
	public static function shop_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}

		return home_url( '/' );
	}

	/**
	 * Return the account URL.
	 *
	 * @return string
	 */
	public static function account_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'myaccount' );
		}

		return wp_login_url();
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}