<?php

namespace WholesaleOrdering\Frontend;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Products\ProductFields;
use WholesaleOrdering\Frontend\SiteChrome;

defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing storefront integration.
 *
 * WooCommerce remains authoritative for:
 *
 * - catalogue queries;
 * - product loops;
 * - product rendering;
 * - product prices;
 * - cart;
 * - checkout;
 * - order processing.
 *
 * This class coordinates the plugin's customer-facing frontend features
 * without replacing WooCommerce's core storefront behavior.
 *
 * Responsibilities:
 *
 * - catalogue search;
 * - category navigation/filtering;
 * - availability filtering;
 * - responsive frontend assets;
 * - quantity input presentation hints;
 * - homepage registration;
 * - site-wide frontend chrome registration.
 *
 * Pricing remains owned by PricingService and
 * WooCommercePricingIntegration.
 */
final class Frontend {

	/**
	 * Frontend stylesheet handle.
	 */
	private const STYLE_HANDLE = 'wholesale-ordering-frontend';

	/**
	 * Quantity-control script handle.
	 */
	private const SCRIPT_HANDLE = 'wholesale-ordering-quantity-controls';

	/**
    * Site-wide chrome script handle.
    */
    private const CHROME_SCRIPT_HANDLE = 'wholesale-ordering-site-chrome';

	/**
	 * Register frontend hooks.
	 *
	 * The frontend coordinator deliberately delegates homepage presentation
	 * and site-wide chrome to their dedicated classes. This prevents the
	 * catalogue layer from becoming responsible for unrelated presentation.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( is_admin() ) {
			return;
		}

		/*
		 * Site-wide presentation.
		 *
		 * SiteChrome owns the plugin-controlled header/footer presentation
		 * and protection against the default theme chrome being displayed
		 * alongside it.
		 */
		SiteChrome::register();

		/*
		 * Homepage presentation.
		 *
		 * HomePage owns the homepage layout. It must continue to delegate
		 * product/category rendering to WooCommerce rather than creating
		 * another product or pricing implementation.
		 */
		HomePage::register();

		/*
		 * Frontend assets.
		 */
		add_action(
			'wp_enqueue_scripts',
			array( self::class, 'enqueue_assets' ),
			20
		);

		/*
		 * Catalogue discovery controls.
		 */
		add_action(
			'woocommerce_before_shop_loop',
			array( self::class, 'render_catalog_tools' ),
			5
		);

		/*
		 * Translate the public availability filter into WooCommerce's
		 * authoritative product meta query. The select uses `stock_status`
		 * for a stable public URL parameter; WooCommerce itself stores the
		 * authoritative value in `_stock_status`.
		 */
		add_filter(
			'woocommerce_product_query_meta_query',
			array( self::class, 'filter_catalog_meta_query' ),
			20,
			2
		);

		/*
		 * Quantity values are presentation hints only.
		 *
		 * Cart and checkout remain the server-authoritative validation
		 * boundary.
		 */
		add_filter(
			'woocommerce_quantity_input_args',
			array( self::class, 'filter_quantity_input_args' ),
			20,
			2
		);

		/*
		 * Stable plugin body classes for responsive styling.
		 */
		add_filter(
			'body_class',
			array( self::class, 'add_body_class' )
		);
	}
				/**
 * Enqueue responsive presentation and frontend interaction assets.
 *
 * @return void
 */
public static function enqueue_assets(): void {
    $plugin_root = dirname( __DIR__, 2 );
    $plugin_file = $plugin_root . '/wholesale-ordering.php';

    $style_file      = $plugin_root . '/assets/css/frontend.css';
    $quantity_script = $plugin_root . '/assets/js/quantity-controls.js';
    $chrome_script   = $plugin_root . '/assets/js/site-chrome.js';

    wp_enqueue_style(
        self::STYLE_HANDLE,
        plugins_url(
            'assets/css/frontend.css',
            $plugin_file
        ),
        array(),
        file_exists( $style_file )
            ? (string) filemtime( $style_file )
            : '1.0.0'
    );

    wp_enqueue_script(
        self::SCRIPT_HANDLE,
        plugins_url(
            'assets/js/quantity-controls.js',
            $plugin_file
        ),
        array(),
        file_exists( $quantity_script )
            ? (string) filemtime( $quantity_script )
            : '1.0.0',
        true
    );

    /*
     * Shared application-shell interaction.
     *
     * This handles only navigation presentation. It does not implement
     * catalogue, pricing, cart or checkout behavior.
     */
    if ( file_exists( $chrome_script ) ) {
        wp_enqueue_script(
            self::CHROME_SCRIPT_HANDLE,
            plugins_url(
                'assets/js/site-chrome.js',
                $plugin_file
            ),
            array(),
            (string) filemtime( $chrome_script ),
            true
        );
    }
}

	/**
	 * Determine whether the current request is a WooCommerce catalogue view.
	 *
	 * Search is included only when the request is a product search.
	 *
	 * @return bool
	 */
	private static function is_catalog_context(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop()
			|| is_product_category()
			|| is_product_tag()
			|| (
				is_search()
				&& 'product' === get_query_var( 'post_type' )
			);
	}

	/**
	 * Render customer-facing catalogue discovery and filtering controls.
	 *
	 * The form uses WooCommerce/WordPress catalogue query variables.
	 * It does not introduce a custom product query or pricing calculation.
	 *
	 * @return void
	 */
	public static function render_catalog_tools(): void {
		if ( ! self::is_catalog_context() ) {
			return;
		}

		$search = isset( $_GET['s'] )
			? sanitize_text_field(
				wp_unslash( $_GET['s'] )
			)
			: '';

		$category = isset( $_GET['product_cat'] )
			? sanitize_title(
				wp_unslash( $_GET['product_cat'] )
			)
			: '';

		$stock_status = isset( $_GET['stock_status'] )
			? sanitize_key(
				wp_unslash( $_GET['stock_status'] )
			)
			: '';

		if ( '' === $category && is_product_category() ) {
			$queried_object = get_queried_object();

			if ( $queried_object instanceof \WP_Term ) {
				$category = $queried_object->slug;
			}
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$reset_url = self::get_catalog_reset_url();
		?>
		<section
			class="wholesale-ordering-catalog-tools"
			aria-label="<?php echo esc_attr__( 'Product catalogue tools', 'wholesale-ordering' ); ?>"
		>

			<div class="wholesale-ordering-catalog-search">

				<form
					method="get"
					action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
				>

					<label for="wholesale-ordering-product-search">
						<?php
						echo esc_html__(
							'Search products',
							'wholesale-ordering'
						);
						?>
					</label>

					<div class="wholesale-ordering-search-row">

						<input
							id="wholesale-ordering-product-search"
							type="search"
							name="s"
							value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php echo esc_attr__( 'Search products…', 'wholesale-ordering' ); ?>"
							autocomplete="off"
						/>

						<input
							type="hidden"
							name="post_type"
							value="product"
						/>

						<button
							type="submit"
							class="button"
						>
							<?php
							echo esc_html__(
								'Search',
								'wholesale-ordering'
							);
							?>
						</button>

					</div>

				</form>

			</div>

			<?php if ( ! empty( $categories ) ) : ?>

				<nav
					class="wholesale-ordering-category-nav"
					aria-label="<?php echo esc_attr__( 'Product categories', 'wholesale-ordering' ); ?>"
				>

					<strong>
						<?php
						echo esc_html__(
							'Categories',
							'wholesale-ordering'
						);
						?>
					</strong>

					<div class="wholesale-ordering-category-links">

						<a
							href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
							class="<?php echo '' === $category ? 'is-active' : ''; ?>"
						>
							<?php
							echo esc_html__(
								'All products',
								'wholesale-ordering'
							);
							?>
						</a>

						<?php foreach ( $categories as $term ) : ?>

							<a
								href="<?php echo esc_url( get_term_link( $term ) ); ?>"
								class="<?php echo $category === $term->slug ? 'is-active' : ''; ?>"
							>
								<?php
								echo esc_html( $term->name );
								?>
							</a>

						<?php endforeach; ?>

					</div>

				</nav>

			<?php endif; ?>

			<div class="wholesale-ordering-catalog-filter">

				<form
					method="get"
					action="<?php echo esc_url( self::get_filter_action_url() ); ?>"
				>

					<div class="wholesale-ordering-filter-grid">

						<div>

							<label for="wholesale-ordering-category-filter">
								<?php
								echo esc_html__(
									'Filter by category',
									'wholesale-ordering'
								);
								?>
							</label>

							<select
								id="wholesale-ordering-category-filter"
								name="product_cat"
							>

								<option value="">
									<?php
									echo esc_html__(
										'All categories',
										'wholesale-ordering'
									);
									?>
								</option>

								<?php foreach ( $categories as $term ) : ?>

									<option
										value="<?php echo esc_attr( $term->slug ); ?>"
										<?php selected( $category, $term->slug ); ?>
									>
										<?php
										echo esc_html( $term->name );
										?>
									</option>

								<?php endforeach; ?>

							</select>

						</div>

						<div>

							<label for="wholesale-ordering-stock-filter">
								<?php
								echo esc_html__(
									'Filter by availability',
									'wholesale-ordering'
								);
								?>
							</label>

							<select
								id="wholesale-ordering-stock-filter"
								name="stock_status"
							>

								<option value="">
									<?php
									echo esc_html__(
										'All products',
										'wholesale-ordering'
									);
									?>
								</option>

								<option
									value="instock"
									<?php selected( $stock_status, 'instock' ); ?>
								>
									<?php
									echo esc_html__(
										'In stock',
										'wholesale-ordering'
									);
									?>
								</option>

								<option
									value="outofstock"
									<?php selected( $stock_status, 'outofstock' ); ?>
								>
									<?php
									echo esc_html__(
										'Out of stock',
										'wholesale-ordering'
									);
									?>
								</option>

								<option
									value="onbackorder"
									<?php selected( $stock_status, 'onbackorder' ); ?>
								>
									<?php
									echo esc_html__(
										'On backorder',
										'wholesale-ordering'
									);
									?>
								</option>

							</select>

						</div>

						<div class="wholesale-ordering-filter-actions">

							<button
								type="submit"
								class="button"
							>
								<?php
								echo esc_html__(
									'Filter',
									'wholesale-ordering'
								);
								?>
							</button>

							<a
								class="button wholesale-ordering-reset"
								href="<?php echo esc_url( $reset_url ); ?>"
							>
								<?php
								echo esc_html__(
									'Reset',
									'wholesale-ordering'
								);
								?>
							</a>

						</div>

					</div>

					<?php if ( '' !== $search ) : ?>

						<input
							type="hidden"
							name="s"
							value="<?php echo esc_attr( $search ); ?>"
						/>

						<input
							type="hidden"
							name="post_type"
							value="product"
						/>

					<?php endif; ?>

				</form>

			</div>

		</section>
		<?php
	}

	/**
	 * Apply the selected availability filter to WooCommerce's catalogue query.
	 *
	 * The storefront form intentionally uses `stock_status` as its public
	 * request parameter. WordPress/WooCommerce does not automatically treat
	 * that parameter as a catalogue query constraint, so translate it at the
	 * WooCommerce product-query boundary into the native `_stock_status` meta
	 * field. This preserves the native WooCommerce product loop and does not
	 * introduce a second product-query engine.
	 *
	 * @param array<string,mixed> $meta_query Existing WooCommerce meta query.
	 * @param \WP_Query            $query      Main catalogue query.
	 *
	 * @return array<string,mixed>
	 */
	public static function filter_catalog_meta_query(
		array $meta_query,
		$query = null
	): array {
		if ( ! self::is_catalog_context() ) {
			return $meta_query;
		}

		$stock_status = isset( $_GET['stock_status'] )
			? sanitize_key( wp_unslash( $_GET['stock_status'] ) )
			: '';

		$allowed_statuses = array(
			'instock',
			'outofstock',
			'onbackorder',
		);

		if (
			'' === $stock_status
			|| ! in_array( $stock_status, $allowed_statuses, true )
		) {
			return $meta_query;
		}

		$meta_query[] = array(
			'key'     => '_stock_status',
			'value'   => $stock_status,
			'compare' => '=',
		);

		return $meta_query;
	}

	/**
	 * Return the appropriate form action for the current catalogue context.
	 *
	 * @return string
	 */
	private static function get_filter_action_url(): string {
		if ( is_product_category() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$term_link = get_term_link( $term );

				if ( ! is_wp_error( $term_link ) ) {
					return (string) $term_link;
				}
			}
		}

		return (string) wc_get_page_permalink( 'shop' );
	}

	/**
	 * Return a clean catalogue URL with active search/filter parameters removed.
	 *
	 * @return string
	 */
	private static function get_catalog_reset_url(): string {
		if ( is_product_category() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$term_link = get_term_link( $term );

				if ( ! is_wp_error( $term_link ) ) {
					return (string) $term_link;
				}
			}
		}

		return (string) wc_get_page_permalink( 'shop' );
	}

	/**
	 * Apply approved-wholesale quantity presentation hints.
	 *
	 * This method does not authorize an order and does not enforce cart rules.
	 * Server-side cart/checkout validation remains authoritative.
	 *
	 * @param array<string,mixed> $args    Quantity input arguments.
	 * @param \WC_Product|null    $product Product.
	 *
	 * @return array<string,mixed>
	 */
	public static function filter_quantity_input_args(
		array $args,
		$product = null
	): array {
		if ( ! $product instanceof \WC_Product ) {
			return $args;
		}

		$context = new CustomerContext();

		if ( ! $context->can_use_wholesale_pricing() ) {
			return $args;
		}

		$minimum = (float) ProductFields::get_wholesale_min_qty(
			$product
		);

		$step = (float) ProductFields::get_wholesale_qty_step(
			$product
		);

		if ( $minimum > 0 ) {
			$args['min_value'] = $minimum;
		}

		if ( $step > 0 ) {
			$args['step'] = $step;
		}

		return $args;
	}

	/**
	 * Add stable plugin body classes for responsive styling.
	 *
	 * @param array<int,string> $classes Body classes.
	 *
	 * @return array<int,string>
	 */
	public static function add_body_class( array $classes ): array {
		$classes[] = 'wholesale-ordering-frontend';
		$classes[] = 'wholesale-ordering-app';

		if ( is_user_logged_in() ) {
			$classes[] = 'wholesale-ordering-authenticated';
		} else {
			$classes[] = 'wholesale-ordering-guest';
		}

		if ( self::is_catalog_context() ) {
			$classes[] = 'wholesale-ordering-catalog';
		}

		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			$classes[] = 'wholesale-ordering-homepage';
		}

		return $classes;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}