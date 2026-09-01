<?php

namespace WholesaleOrdering\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide presentation chrome.
 *
 * This class owns only the shared visual shell supplied by the
 * Wholesale Ordering plugin:
 *
 * - removes the default Twenty Twenty-Five footer template part;
 * - renders the application's own footer;
 * - provides consistent site identity/navigation across frontend pages.
 *
 * It does NOT own:
 *
 * - WooCommerce product queries;
 * - product loops;
 * - product pricing;
 * - cart calculations;
 * - checkout calculations;
 * - customer authorization;
 * - wholesale status;
 * - application processing.
 *
 * WooCommerce remains authoritative for commerce behavior.
 */
final class SiteChrome {

	/**
	 * Prevent duplicate footer rendering.
	 *
	 * @var bool
	 */
	private static $footer_rendered = false;

	/**
	 * Register site chrome hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( is_admin() ) {
			return;
		}

		/*
		 * Twenty Twenty-Five is a block theme. Its footer is normally
		 * rendered through a core/template-part block rather than a
		 * traditional PHP footer.php file.
		 *
		 * Suppress only the theme footer template part.
		 */
		add_filter(
			'render_block',
			array( self::class, 'filter_theme_footer' ),
			20,
			2
		);

		/*
		 * Render our controlled footer after the page content has finished.
		 */
		add_action(
			'wp_footer',
			array( self::class, 'render_footer' ),
			5
		);

		add_filter(
			'body_class',
			array( self::class, 'add_body_class' ),
			20
		);
	}

	/**
	 * Remove the default Twenty Twenty-Five footer template part.
	 *
	 * This targets only a footer template-part block. Other template parts
	 * and WooCommerce blocks remain untouched.
	 *
	 * @param string   $block_content Rendered block HTML.
	 * @param array<string,mixed> $block Block data.
	 *
	 * @return string
	 */
	public static function filter_theme_footer(
		string $block_content,
		array $block
	): string {
		if ( empty( $block['blockName'] ) ) {
			return $block_content;
		}

		if ( 'core/template-part' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( empty( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			return $block_content;
		}

		$slug = isset( $block['attrs']['slug'] )
			? sanitize_key( (string) $block['attrs']['slug'] )
			: '';

		if ( 'footer' !== $slug ) {
			return $block_content;
		}

		/*
		 * Do not suppress arbitrary template parts.
		 *
		 * Only the theme footer template part is replaced.
		 */
		return '';
	}

	/**
	 * Add a body class identifying the application's site chrome.
	 *
	 * @param array<int,string> $classes Existing body classes.
	 *
	 * @return array<int,string>
	 */
	public static function add_body_class( array $classes ): array {
		$classes[] = 'wholesale-ordering-site';

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Render the application footer.
	 *
	 * The footer is intentionally lightweight. It does not duplicate
	 * WooCommerce templates or commerce logic.
	 *
	 * @return void
	 */
	public static function render_footer(): void {
		if ( self::$footer_rendered ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		self::$footer_rendered = true;

		$site_name = get_bloginfo( 'name' );

		if ( '' === $site_name ) {
			$site_name = __( 'Wholesale Ordering', 'wholesale-ordering' );
		}

		$shop_url = '';

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );
		}

		if ( '' === $shop_url ) {
			$shop_url = home_url( '/shop/' );
		}

		$account_url = '';

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$account_url = wc_get_page_permalink( 'myaccount' );
		}

		if ( '' === $account_url ) {
			$account_url = wp_login_url();
		}

		$cart_url = '';

		if ( function_exists( 'wc_get_cart_url' ) ) {
			$cart_url = wc_get_cart_url();
		}

		$home_url = home_url( '/' );
		?>
		<footer
			class="wholesale-ordering-site-footer"
			role="contentinfo"
		>
			<div class="wholesale-ordering-site-footer__inner">

				<div class="wholesale-ordering-site-footer__brand">
					<a
						class="wholesale-ordering-site-footer__site-name"
						href="<?php echo esc_url( $home_url ); ?>"
					>
						<?php echo esc_html( $site_name ); ?>
					</a>

					<p class="wholesale-ordering-site-footer__description">
						<?php
						echo esc_html__(
							'Simple, reliable ordering for business customers.',
							'wholesale-ordering'
						);
						?>
					</p>
				</div>

				<nav
					class="wholesale-ordering-site-footer__navigation"
					aria-label="<?php echo esc_attr__( 'Footer navigation', 'wholesale-ordering' ); ?>"
				>
					<ul>
						<li>
							<a href="<?php echo esc_url( $shop_url ); ?>">
								<?php
								echo esc_html__(
									'Shop',
									'wholesale-ordering'
								);
								?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( $account_url ); ?>">
								<?php
								echo esc_html__(
									'My Account',
									'wholesale-ordering'
								);
								?>
							</a>
						</li>

						<?php if ( '' !== $cart_url ) : ?>
							<li>
								<a href="<?php echo esc_url( $cart_url ); ?>">
									<?php
									echo esc_html__(
										'Cart',
										'wholesale-ordering'
									);
									?>
								</a>
							</li>
						<?php endif; ?>
					</ul>
				</nav>

			</div>

			<div class="wholesale-ordering-site-footer__bottom">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: site name */
							__(
								'© %s. All rights reserved.',
								'wholesale-ordering'
							),
							$site_name
						)
					);
					?>
				</p>

				<p>
					<?php
					echo esc_html__(
						'Powered by WooCommerce.',
						'wholesale-ordering'
					);
					?>
				</p>
			</div>
		</footer>
		<?php
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}