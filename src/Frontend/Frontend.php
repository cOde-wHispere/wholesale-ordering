<?php

namespace WholesaleOrdering\Frontend;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Products\ProductFields;


defined( 'ABSPATH' ) || exit;

/**
 * Phase 6 customer-facing storefront integration.
 *
 * WooCommerce remains authoritative for the catalogue, product queries,
 * categories, search, product rendering, cart and checkout. This class adds
 * the missing storefront discovery/filter UI without replacing WooCommerce's
 * product loop or pricing engine.
 *
 * Phase 6A scope:
 * - category browsing/filtering;
 * - customer-facing product search;
 * - basic availability filtering;
 * - responsive storefront presentation;
 * - quantity controls and approved-wholesale quantity hints.
 *
 * The plugin never reads the stored wholesale price for presentation here.
 * Pricing remains owned by PricingService/WooCommercePricingIntegration.
 */
final class Frontend {

    private const STYLE_HANDLE = 'wholesale-ordering-frontend';
    private const SCRIPT_HANDLE = 'wholesale-ordering-quantity-controls';

    /**
     * Register frontend hooks.
     *
     * @return void
     */
    public static function register(): void {
        if ( is_admin() ) {
            return;
        }

        add_action(
            'wp_enqueue_scripts',
            array( self::class, 'enqueue_assets' ),
            20
        );

        add_action(
            'woocommerce_before_shop_loop',
            array( self::class, 'render_catalog_tools' ),
            5
        );

        add_filter(
            'woocommerce_quantity_input_args',
            array( self::class, 'filter_quantity_input_args' ),
            20,
            2
        );

        add_filter(
            'body_class',
            array( self::class, 'add_body_class' )
        );
    }

    /**
     * Enqueue responsive presentation and quantity-control assets.
     *
     * @return void
     */
    public static function enqueue_assets(): void {
        $plugin_root = dirname( __DIR__, 2 );
        $plugin_file = $plugin_root . '/wholesale-ordering.php';
        $style_file  = $plugin_root . '/assets/css/frontend.css';
        $script_file = $plugin_root . '/assets/js/quantity-controls.js';

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
            file_exists( $script_file )
                ? (string) filemtime( $script_file )
                : '1.0.0',
            true
        );
    }

    /**
     * Determine whether the current request is a WooCommerce catalogue view.
     *
     * Search is included only when the request is a product search. The form
     * generated below explicitly submits post_type=product, so normal site
     * searches are not modified by this storefront toolbar.
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
            || ( is_search() && 'product' === get_query_var( 'post_type' ) );
    }

    /**
     * Render customer-facing catalogue discovery and filtering controls.
     *
     * The form intentionally uses WooCommerce/WordPress catalogue query
     * variables rather than introducing a custom product query or endpoint.
     * This keeps category/search results inside WooCommerce's normal loop and
     * preserves the existing authoritative pricing integration.
     *
     * @return void
     */
    public static function render_catalog_tools(): void {
        if ( ! self::is_catalog_context() ) {
            return;
        }

        $search       = isset( $_GET['s'] )
            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )
            : '';
        $category     = isset( $_GET['product_cat'] )
            ? sanitize_title( wp_unslash( $_GET['product_cat'] ) )
            : '';
        $stock_status = isset( $_GET['stock_status'] )
            ? sanitize_key( wp_unslash( $_GET['stock_status'] ) )
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
                <form method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
                    <label for="wholesale-ordering-product-search">
                        <?php echo esc_html__( 'Search products', 'wholesale-ordering' ); ?>
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
                        <input type="hidden" name="post_type" value="product" />
                        <button type="submit" class="button">
                            <?php echo esc_html__( 'Search', 'wholesale-ordering' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <?php if ( ! empty( $categories ) ) : ?>
                <nav
                    class="wholesale-ordering-category-nav"
                    aria-label="<?php echo esc_attr__( 'Product categories', 'wholesale-ordering' ); ?>"
                >
                    <strong><?php echo esc_html__( 'Categories', 'wholesale-ordering' ); ?></strong>
                    <div class="wholesale-ordering-category-links">
                        <a
                            href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
                            class="<?php echo '' === $category ? 'is-active' : ''; ?>"
                        >
                            <?php echo esc_html__( 'All products', 'wholesale-ordering' ); ?>
                        </a>
                        <?php foreach ( $categories as $term ) : ?>
                            <a
                                href="<?php echo esc_url( get_term_link( $term ) ); ?>"
                                class="<?php echo $category === $term->slug ? 'is-active' : ''; ?>"
                            >
                                <?php echo esc_html( $term->name ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>
            <?php endif; ?>

            <div class="wholesale-ordering-catalog-filter">
                <form method="get" action="<?php echo esc_url( self::get_filter_action_url() ); ?>">
                    <div class="wholesale-ordering-filter-grid">
                        <div>
                            <label for="wholesale-ordering-category-filter">
                                <?php echo esc_html__( 'Filter by category', 'wholesale-ordering' ); ?>
                            </label>
                            <select
                                id="wholesale-ordering-category-filter"
                                name="product_cat"
                            >
                                <option value="">
                                    <?php echo esc_html__( 'All categories', 'wholesale-ordering' ); ?>
                                </option>
                                <?php foreach ( $categories as $term ) : ?>
                                    <option
                                        value="<?php echo esc_attr( $term->slug ); ?>"
                                        <?php selected( $category, $term->slug ); ?>
                                    >
                                        <?php echo esc_html( $term->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="wholesale-ordering-stock-filter">
                                <?php echo esc_html__( 'Filter by availability', 'wholesale-ordering' ); ?>
                            </label>
                            <select
                                id="wholesale-ordering-stock-filter"
                                name="stock_status"
                            >
                                <option value="">
                                    <?php echo esc_html__( 'All products', 'wholesale-ordering' ); ?>
                                </option>
                                <option value="instock" <?php selected( $stock_status, 'instock' ); ?>>
                                    <?php echo esc_html__( 'In stock', 'wholesale-ordering' ); ?>
                                </option>
                                <option value="outofstock" <?php selected( $stock_status, 'outofstock' ); ?>>
                                    <?php echo esc_html__( 'Out of stock', 'wholesale-ordering' ); ?>
                                </option>
                                <option value="onbackorder" <?php selected( $stock_status, 'onbackorder' ); ?>>
                                    <?php echo esc_html__( 'On backorder', 'wholesale-ordering' ); ?>
                                </option>
                            </select>
                        </div>

                        <div class="wholesale-ordering-filter-actions">
                            <button type="submit" class="button">
                                <?php echo esc_html__( 'Filter', 'wholesale-ordering' ); ?>
                            </button>
                            <a class="button wholesale-ordering-reset" href="<?php echo esc_url( $reset_url ); ?>">
                                <?php echo esc_html__( 'Reset', 'wholesale-ordering' ); ?>
                            </a>
                        </div>
                    </div>

                    <?php if ( '' !== $search ) : ?>
                        <input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
                        <input type="hidden" name="post_type" value="product" />
                    <?php endif; ?>
                </form>
            </div>
        </section>
        <?php
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
     * Apply authoritative wholesale quantity constraints to approved users.
     *
     * Quantity rules are presentation hints only. Cart/checkout services remain
     * the server-authoritative validation boundary.
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

        $minimum = (float) ProductFields::get_wholesale_min_qty( $product );
        $step    = (float) ProductFields::get_wholesale_qty_step( $product );

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

        if ( is_user_logged_in() ) {
            $classes[] = 'wholesale-ordering-authenticated';
        } else {
            $classes[] = 'wholesale-ordering-guest';
        }

        if ( self::is_catalog_context() ) {
            $classes[] = 'wholesale-ordering-catalog';
        }

        return $classes;
    }

    private function __construct() {}
}