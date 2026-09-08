<?php

namespace WholesaleOrdering\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide presentation chrome.
 *
 * Owns only the shared visual application shell:
 *
 * - replaces the active block theme header/footer template parts;
 * - renders the application header;
 * - renders primary navigation;
 * - renders the global product search;
 * - renders the cart/account actions;
 * - renders the application footer;
 * - provides consistent site identity across frontend pages.
 *
 * It does NOT own:
 *
 * - product queries;
 * - product loops;
 * - pricing;
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
     * Prevent duplicate rendering.
     *
     * @var bool
     */
    private static $header_rendered = false;

    /**
     * Prevent duplicate rendering.
     *
     * @var bool
     */
    private static $footer_rendered = false;

    /**
     * Register the shared application shell.
     *
     * @return void
     */
    public static function register(): void {
        if ( is_admin() ) {
            return;
        }

        /*
         * Remove the active block theme's own header/footer template parts.
         * This prevents two separate site shells from appearing together.
         */
        add_filter(
            'render_block',
            array( self::class, 'filter_theme_template_parts' ),
            20,
            2
        );

        /*
         * The application header belongs immediately after <body>.
         */
        add_action(
            'wp_body_open',
            array( self::class, 'render_header' ),
            5
        );

        /*
         * The application footer belongs at the normal WordPress footer
         * boundary so WooCommerce and other frontend plugins retain their
         * normal footer lifecycle.
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
     * Remove the theme header/footer template parts.
     *
     * @param string              $block_content Rendered block HTML.
     * @param array<string,mixed> $block         Block data.
     *
     * @return string
     */
    public static function filter_theme_template_parts(
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

        if ( in_array( $slug, array( 'header', 'footer' ), true ) ) {
            return '';
        }

        return $block_content;
    }

    /**
     * Add stable application-shell body classes.
     *
     * @param array<int,string> $classes Existing body classes.
     *
     * @return array<int,string>
     */
    public static function add_body_class( array $classes ): array {
        $classes[] = 'wholesale-ordering-site';

        return array_values(
            array_unique( $classes )
        );
    }

    /**
     * Render the shared application header.
     *
     * This header is rendered on the homepage, Shop, product/category/search
     * pages, My Account, Cart and Checkout.
     *
     * @return void
     */
    public static function render_header(): void {
        if ( self::$header_rendered || is_admin() ) {
            return;
        }

        self::$header_rendered = true;

        $site_name = get_bloginfo( 'name' );

        if ( '' === $site_name ) {
            $site_name = __( 'Wholesale Ordering', 'wholesale-ordering' );
        }

        $home_url = home_url( '/' );

        $shop_url = function_exists( 'wc_get_page_permalink' )
            ? wc_get_page_permalink( 'shop' )
            : home_url( '/shop/' );

        $account_url = function_exists( 'wc_get_page_permalink' )
            ? wc_get_page_permalink( 'myaccount' )
            : wp_login_url();

        $cart_url = function_exists( 'wc_get_cart_url' )
            ? wc_get_cart_url()
            : '';

        $cart_count = 0;

        if ( function_exists( 'WC' ) && WC()->cart ) {
            $cart_count = (int) WC()->cart->get_cart_contents_count();
        }

        $search_value = isset( $_GET['s'] )
            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )
            : '';

        $is_home    = is_front_page();
        $is_shop    = function_exists( 'is_shop' ) && is_shop();
        $is_account = function_exists( 'is_account_page' ) && is_account_page();
        ?>
        <header class="wholesale-ordering-site-header">
            <div class="wholesale-ordering-site-header__inner">

                <a
                    class="wholesale-ordering-site-brand"
                    href="<?php echo esc_url( $home_url ); ?>"
                    aria-label="<?php echo esc_attr( $site_name ); ?>"
                >
                    <span
                        class="wholesale-ordering-site-brand__mark"
                        aria-hidden="true"
                    >
                        WO
                    </span>

                    <span class="wholesale-ordering-site-brand__text">
                        <strong>
                            <?php echo esc_html( $site_name ); ?>
                        </strong>

                        <small>
                            <?php
                            echo esc_html__(
                                'Business ordering',
                                'wholesale-ordering'
                            );
                            ?>
                        </small>
                    </span>
                </a>

                <button
                    type="button"
                    class="wholesale-ordering-mobile-toggle"
                    aria-expanded="false"
                    aria-controls="wholesale-ordering-primary-navigation"
                >
                    <span class="screen-reader-text">
                        <?php
                        echo esc_html__(
                            'Open navigation',
                            'wholesale-ordering'
                        );
                        ?>
                    </span>

                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>

                <nav
                    id="wholesale-ordering-primary-navigation"
                    class="wholesale-ordering-primary-navigation"
                    aria-label="<?php echo esc_attr__( 'Primary navigation', 'wholesale-ordering' ); ?>"
                >
                    <a
                        href="<?php echo esc_url( $home_url ); ?>"
                        class="<?php echo $is_home ? 'is-active' : ''; ?>"
                        <?php echo $is_home ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html__( 'Home', 'wholesale-ordering' ); ?>
                    </a>

                    <a
                        href="<?php echo esc_url( $shop_url ); ?>"
                        class="<?php echo $is_shop ? 'is-active' : ''; ?>"
                        <?php echo $is_shop ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html__( 'Shop', 'wholesale-ordering' ); ?>
                    </a>

                    <a
                        href="<?php echo esc_url( $account_url ); ?>"
                        class="<?php echo $is_account ? 'is-active' : ''; ?>"
                        <?php echo $is_account ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html__( 'My Account', 'wholesale-ordering' ); ?>
                    </a>
                </nav>

                <div class="wholesale-ordering-header-actions">

                    <form
                        class="wholesale-ordering-header-search"
                        method="get"
                        action="<?php echo esc_url( $shop_url ); ?>"
                        role="search"
                    >
                        <label
                            class="screen-reader-text"
                            for="wholesale-ordering-header-search"
                        >
                            <?php
                            echo esc_html__(
                                'Search products',
                                'wholesale-ordering'
                            );
                            ?>
                        </label>

                        <span
                            class="wholesale-ordering-header-search__icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="18"
                                height="18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-4-4"></path>
                            </svg>
                        </span>

                        <input
                            id="wholesale-ordering-header-search"
                            type="search"
                            name="s"
                            value="<?php echo esc_attr( $search_value ); ?>"
                            placeholder="<?php echo esc_attr__( 'Search products…', 'wholesale-ordering' ); ?>"
                            autocomplete="off"
                        />

                        <input
                            type="hidden"
                            name="post_type"
                            value="product"
                        />
                    </form>

                    <a
                        class="wholesale-ordering-header-account"
                        href="<?php echo esc_url( $account_url ); ?>"
                    >
                        <span
                            class="wholesale-ordering-header-account__icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                width="20"
                                height="20"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="8" r="3.5"></circle>
                                <path d="M5 20c.8-3.2 3.1-5 7-5s6.2 1.8 7 5"></path>
                            </svg>
                        </span>

                        <span class="wholesale-ordering-header-account__label">
                            <?php
                            echo is_user_logged_in()
                                ? esc_html__( 'Account', 'wholesale-ordering' )
                                : esc_html__( 'Sign in', 'wholesale-ordering' );
                            ?>
                        </span>
                    </a>

                    <?php if ( '' !== $cart_url ) : ?>
                        <a
                            class="wholesale-ordering-header-cart"
                            href="<?php echo esc_url( $cart_url ); ?>"
                            aria-label="<?php echo esc_attr__( 'Shopping cart', 'wholesale-ordering' ); ?>"
                        >
                            <span
                                class="wholesale-ordering-header-cart__icon"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    width="20"
                                    height="20"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="9" cy="20" r="1"></circle>
                                    <circle cx="18" cy="20" r="1"></circle>
                                    <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 8H6"></path>
                                </svg>
                            </span>

                            <span>
                                <?php echo esc_html__( 'Cart', 'wholesale-ordering' ); ?>
                            </span>

                            <span class="wholesale-ordering-cart-count">
                                <?php echo esc_html( (string) $cart_count ); ?>
                            </span>
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </header>
        <?php
    }

    /**
     * Render the shared application footer.
     *
     * @return void
     */
    public static function render_footer(): void {
        if ( self::$footer_rendered || is_admin() ) {
            return;
        }

        self::$footer_rendered = true;

        $site_name = get_bloginfo( 'name' );

        if ( '' === $site_name ) {
            $site_name = __( 'Wholesale Ordering', 'wholesale-ordering' );
        }

        $home_url = home_url( '/' );

        $shop_url = function_exists( 'wc_get_page_permalink' )
            ? wc_get_page_permalink( 'shop' )
            : home_url( '/shop/' );

        $account_url = function_exists( 'wc_get_page_permalink' )
            ? wc_get_page_permalink( 'myaccount' )
            : wp_login_url();

        $cart_url = function_exists( 'wc_get_cart_url' )
            ? wc_get_cart_url()
            : '';
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

                    <p>
                        <?php
                        echo esc_html__(
                            'A modern ordering experience for business customers.',
                            'wholesale-ordering'
                        );
                        ?>
                    </p>
                </div>

                <div class="wholesale-ordering-site-footer__links">
                    <h2>
                        <?php
                        echo esc_html__(
                            'Quick links',
                            'wholesale-ordering'
                        );
                        ?>
                    </h2>

                    <nav
                        aria-label="<?php echo esc_attr__( 'Footer navigation', 'wholesale-ordering' ); ?>"
                    >
                        <ul>
                            <li>
                                <a href="<?php echo esc_url( $home_url ); ?>">
                                    <?php echo esc_html__( 'Home', 'wholesale-ordering' ); ?>
                                </a>
                            </li>

                            <li>
                                <a href="<?php echo esc_url( $shop_url ); ?>">
                                    <?php echo esc_html__( 'Shop', 'wholesale-ordering' ); ?>
                                </a>
                            </li>

                            <li>
                                <a href="<?php echo esc_url( $account_url ); ?>">
                                    <?php echo esc_html__( 'My Account', 'wholesale-ordering' ); ?>
                                </a>
                            </li>

                            <?php if ( '' !== $cart_url ) : ?>
                                <li>
                                    <a href="<?php echo esc_url( $cart_url ); ?>">
                                        <?php echo esc_html__( 'Cart', 'wholesale-ordering' ); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>

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