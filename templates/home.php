<?php
/**
 * Wholesale Ordering application homepage.
 *
 * This template intentionally owns the complete document boundary.
 * The active theme header/footer are not loaded because SiteChrome
 * owns the application's shared frontend shell.
 *
 * @package WholesaleOrdering
 */

defined( 'ABSPATH' ) || exit;

use WholesaleOrdering\Frontend\HomePage;

$shop_url = HomePage::shop_url();

$account_url = HomePage::account_url();

$site_name = get_bloginfo( 'name' );

if ( '' === $site_name ) {
    $site_name = __( 'Wholesale Ordering', 'wholesale-ordering' );
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<?php
/*
 * SiteChrome owns the application header.
 *
 * Do not call get_header() here. Doing so would load the active
 * WordPress theme's header and create a duplicate site header.
 */
if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
}
?>

<main
    id="primary"
    class="wholesale-ordering-home"
>
    <section class="wholesale-home-hero">
        <div class="wholesale-home-hero__content">

            <div class="wholesale-home-hero__text">
                <p class="wholesale-home-eyebrow">
                    <?php echo esc_html( $site_name ); ?>
                </p>

                <h1>
                    <?php
                    echo esc_html__(
                        'Business ordering made simple.',
                        'wholesale-ordering'
                    );
                    ?>
                </h1>

                <p class="wholesale-home-hero__lead">
                    <?php
                    echo esc_html__(
                        'Browse products, manage your account and place orders through one straightforward shopping experience.',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <div class="wholesale-home-hero__actions">
                    <a
                        class="wholesale-button wholesale-button--primary"
                        href="<?php echo esc_url( $shop_url ); ?>"
                    >
                        <?php
                        echo esc_html__(
                            'Browse Products',
                            'wholesale-ordering'
                        );
                        ?>
                    </a>

                    <a
                        class="wholesale-button wholesale-button--secondary"
                        href="<?php echo esc_url( $account_url ); ?>"
                    >
                        <?php
                        echo esc_html__(
                            'Create an Account',
                            'wholesale-ordering'
                        );
                        ?>
                    </a>
                </div>
            </div>

        </div>
    </section>

    <section class="wholesale-home-workspace">
        <div class="wholesale-home-section__inner">

            <header class="wholesale-home-section__header">
                <p class="wholesale-home-eyebrow">
                    <?php
                    echo esc_html__(
                        'Ordering workspace',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <h2>
                    <?php
                    echo esc_html__(
                        'Find what you need',
                        'wholesale-ordering'
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Browse the catalogue, find products quickly and move directly into ordering.',
                        'wholesale-ordering'
                    );
                    ?>
                </p>
            </header>

            <div class="wholesale-home-search">
                <a
                    class="wholesale-home-search__link"
                    href="<?php echo esc_url( $shop_url ); ?>"
                >
                    <span
                        class="wholesale-home-search__icon"
                        aria-hidden="true"
                    >
                        ⌕
                    </span>

                    <span>
                        <?php
                        echo esc_html__(
                            'Search products…',
                            'wholesale-ordering'
                        );
                        ?>
                    </span>
                </a>
            </div>

            <div class="wholesale-home-steps">
                <div class="wholesale-home-step">
                    <span>01</span>

                    <strong>
                        <?php
                        echo esc_html__(
                            'Browse',
                            'wholesale-ordering'
                        );
                        ?>
                    </strong>
                </div>

                <div class="wholesale-home-step">
                    <span>02</span>

                    <strong>
                        <?php
                        echo esc_html__(
                            'Order',
                            'wholesale-ordering'
                        );
                        ?>
                    </strong>
                </div>

                <div class="wholesale-home-step">
                    <span>03</span>

                    <strong>
                        <?php
                        echo esc_html__(
                            'Track',
                            'wholesale-ordering'
                        );
                        ?>
                    </strong>
                </div>
            </div>

        </div>
    </section>

    <section class="wholesale-home-features">
        <div class="wholesale-home-section__inner">

            <div class="wholesale-home-feature-grid">

                <article class="wholesale-home-feature">
                    <span class="wholesale-home-feature__number">
                        01
                    </span>

                    <h2>
                        <?php
                        echo esc_html__(
                            'Browse with confidence',
                            'wholesale-ordering'
                        );
                        ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html__(
                            'Search products, explore categories and use the catalogue filters to find what you need.',
                            'wholesale-ordering'
                        );
                        ?>
                    </p>
                </article>

                <article class="wholesale-home-feature">
                    <span class="wholesale-home-feature__number">
                        02
                    </span>

                    <h2>
                        <?php
                        echo esc_html__(
                            'Account-aware ordering',
                            'wholesale-ordering'
                        );
                        ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html__(
                            'Your account and wholesale status determine the pricing and ordering experience you are authorized to receive.',
                            'wholesale-ordering'
                        );
                        ?>
                    </p>
                </article>

                <article class="wholesale-home-feature">
                    <span class="wholesale-home-feature__number">
                        03
                    </span>

                    <h2>
                        <?php
                        echo esc_html__(
                            'One consistent workspace',
                            'wholesale-ordering'
                        );
                        ?>
                    </h2>

                    <p>
                        <?php
                        echo esc_html__(
                            'Move between products, your account and cart without leaving the same application experience.',
                            'wholesale-ordering'
                        );
                        ?>
                    </p>
                </article>

            </div>

        </div>
    </section>

    <section class="wholesale-home-catalogue">
        <div class="wholesale-home-section__inner">

            <header class="wholesale-home-section__header">
                <p class="wholesale-home-eyebrow">
                    <?php
                    echo esc_html__(
                        'Explore',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <h2>
                    <?php
                    echo esc_html__(
                        'Shop by category',
                        'wholesale-ordering'
                    );
                    ?>
                </h2>
            </header>

            <div class="wholesale-home-categories">
                <?php HomePage::render_categories(); ?>
            </div>

            <div class="wholesale-home-catalogue-link">
                <a href="<?php echo esc_url( $shop_url ); ?>">
                    <?php
                    echo esc_html__(
                        'View all products →',
                        'wholesale-ordering'
                    );
                    ?>
                </a>
            </div>

        </div>
    </section>

    <section class="wholesale-home-products">
        <div class="wholesale-home-section__inner">

            <header class="wholesale-home-section__header">
                <p class="wholesale-home-eyebrow">
                    <?php
                    echo esc_html__(
                        'Catalogue',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <h2>
                    <?php
                    echo esc_html__(
                        'Latest products',
                        'wholesale-ordering'
                    );
                    ?>
                </h2>
            </header>

            <div class="wholesale-home-product-grid">
                <?php HomePage::render_products(); ?>
            </div>

            <div class="wholesale-home-catalogue-link">
                <a href="<?php echo esc_url( $shop_url ); ?>">
                    <?php
                    echo esc_html__(
                        'Open shop →',
                        'wholesale-ordering'
                    );
                    ?>
                </a>
            </div>

        </div>
    </section>

    <section class="wholesale-home-business">
        <div class="wholesale-home-section__inner">

            <div class="wholesale-home-business__content">

                <p class="wholesale-home-eyebrow">
                    <?php
                    echo esc_html__(
                        'Business access',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <h2>
                    <?php
                    echo esc_html__(
                        'Need wholesale access?',
                        'wholesale-ordering'
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Create an account and use your customer workspace to manage your ordering journey.',
                        'wholesale-ordering'
                    );
                    ?>
                </p>

                <a
                    class="wholesale-button wholesale-button--primary"
                    href="<?php echo esc_url( $account_url ); ?>"
                >
                    <?php
                    echo esc_html__(
                        'Get Started',
                        'wholesale-ordering'
                    );
                    ?>
                </a>

            </div>

        </div>
    </section>
</main>

<?php
/*
 * SiteChrome owns the application footer.
 *
 * wp_footer() fires the normal WordPress footer lifecycle and therefore
 * allows SiteChrome and WooCommerce/other frontend extensions to attach
 * their normal footer behavior.
 */
if ( function_exists( 'wp_footer' ) ) {
    wp_footer();
}
?>

</body>
</html>