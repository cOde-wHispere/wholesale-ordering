<?php

defined( 'ABSPATH' ) || exit;

$shop_url = function_exists( 'wc_get_page_permalink' )
	? wc_get_page_permalink( 'shop' )
	: home_url( '/shop/' );

$account_url = function_exists( 'wc_get_page_permalink' )
	? wc_get_page_permalink( 'myaccount' )
	: wp_login_url();

$site_name = get_bloginfo( 'name' );

if ( '' === $site_name ) {
	$site_name = __( 'Wholesale Ordering', 'wholesale-ordering' );
}

/*
 * Allow the active theme to provide its normal document/header setup.
 *
 * SiteChrome is attached to wp_body_open. Some themes/templates do not
 * execute wp_body_open(), so get_header() alone cannot be treated as a
 * guarantee that the application shell has rendered.
 */
get_header();

/*
 * Guarantee that the WordPress body-open lifecycle fires.
 *
 * SiteChrome owns the Wholesale Ordering application header/navigation.
 *
 * The did_action() guard prevents duplicate execution when the active
 * theme has already called wp_body_open().
 */
if (
	function_exists( 'wp_body_open' )
	&& 0 === did_action( 'wp_body_open' )
) {
	wp_body_open();
}
?>

<main
	id="primary"
	class="wholesale-ordering-homepage"
>

	<section class="wholesale-home-hero">
		<div class="wholesale-home-container wholesale-home-hero__grid">

			<div class="wholesale-home-hero__content">

				<span class="wholesale-home-eyebrow">
					<?php
					echo esc_html__(
						'Wholesale Ordering',
						'wholesale-ordering'
					);
					?>
				</span>

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

				<div class="wholesale-home-actions">

					<a
						class="button wholesale-home-primary-button"
						href="<?php echo esc_url( $shop_url ); ?>"
					>
						<?php
						echo esc_html__(
							'Browse Products',
							'wholesale-ordering'
						);
						?>
					</a>

					<?php if ( ! is_user_logged_in() ) : ?>

						<a
							class="button wholesale-home-secondary-button"
							href="<?php echo esc_url( $account_url ); ?>"
						>
							<?php
							echo esc_html__(
								'Create an Account',
								'wholesale-ordering'
							);
							?>
						</a>

					<?php else : ?>

						<a
							class="button wholesale-home-secondary-button"
							href="<?php echo esc_url( $account_url ); ?>"
						>
							<?php
							echo esc_html__(
								'My Account',
								'wholesale-ordering'
							);
							?>
						</a>

					<?php endif; ?>

				</div>

			</div>

			<div
				class="wholesale-home-hero-panel"
				aria-label="<?php echo esc_attr__( 'Ordering overview', 'wholesale-ordering' ); ?>"
			>

				<div class="wholesale-home-hero-panel__top">

					<span
						class="wholesale-home-hero-panel__dot"
						aria-hidden="true"
					></span>

					<span>
						<?php
						echo esc_html__(
							'Ordering workspace',
							'wholesale-ordering'
						);
						?>
					</span>

				</div>

				<div class="wholesale-home-hero-panel__main">

					<span class="wholesale-home-hero-panel__label">
						<?php
						echo esc_html__(
							'Find what you need',
							'wholesale-ordering'
						);
						?>
					</span>

					<strong>
						<?php
						echo esc_html__(
							'Browse the catalogue',
							'wholesale-ordering'
						);
						?>
					</strong>

					<div class="wholesale-home-hero-panel__search">

						<span aria-hidden="true">⌕</span>

						<span>
							<?php
							echo esc_html__(
								'Search products…',
								'wholesale-ordering'
							);
							?>
						</span>

					</div>

					<div class="wholesale-home-hero-panel__stats">

						<div>
							<strong>01</strong>

							<span>
								<?php
								echo esc_html__(
									'Browse',
									'wholesale-ordering'
								);
								?>
							</span>
						</div>

						<div>
							<strong>02</strong>

							<span>
								<?php
								echo esc_html__(
									'Order',
									'wholesale-ordering'
								);
								?>
							</span>
						</div>

						<div>
							<strong>03</strong>

							<span>
								<?php
								echo esc_html__(
									'Track',
									'wholesale-ordering'
								);
								?>
							</span>
						</div>

					</div>

				</div>

			</div>

		</div>
	</section>

	<section
		class="wholesale-home-features"
		aria-label="<?php echo esc_attr__( 'Ordering features', 'wholesale-ordering' ); ?>"
	>

		<div class="wholesale-home-container">

			<div class="wholesale-home-feature-grid">

				<article class="wholesale-home-feature-card">

					<span
						class="wholesale-home-feature-card__number"
						aria-hidden="true"
					>
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

				<article class="wholesale-home-feature-card">

					<span
						class="wholesale-home-feature-card__number"
						aria-hidden="true"
					>
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

				<article class="wholesale-home-feature-card">

					<span
						class="wholesale-home-feature-card__number"
						aria-hidden="true"
					>
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

	<section
		class="wholesale-home-categories wholesale-home-section"
		aria-labelledby="wholesale-home-categories-title"
	>

		<div class="wholesale-home-container">

			<div class="wholesale-home-section-heading">

				<div>

					<span class="wholesale-home-section-eyebrow">
						<?php
						echo esc_html__(
							'Explore',
							'wholesale-ordering'
						);
						?>
					</span>

					<h2 id="wholesale-home-categories-title">
						<?php
						echo esc_html__(
							'Shop by category',
							'wholesale-ordering'
						);
						?>
					</h2>

				</div>

				<a
					class="wholesale-home-section-link"
					href="<?php echo esc_url( $shop_url ); ?>"
				>
					<?php
					echo esc_html__(
						'View all products',
						'wholesale-ordering'
					);
					?>

					<span aria-hidden="true">→</span>
				</a>

			</div>

			<?php
			/*
			 * HomePage delegates category rendering to WooCommerce.
			 *
			 * Do not introduce a second category/product query engine here.
			 */
			\WholesaleOrdering\Frontend\HomePage::render_categories();
			?>

		</div>

	</section>

	<section
		class="wholesale-home-products wholesale-home-section"
		aria-labelledby="wholesale-home-products-title"
	>

		<div class="wholesale-home-container">

			<div class="wholesale-home-section-heading">

				<div>

					<span class="wholesale-home-section-eyebrow">
						<?php
						echo esc_html__(
							'Catalogue',
							'wholesale-ordering'
						);
						?>
					</span>

					<h2 id="wholesale-home-products-title">
						<?php
						echo esc_html__(
							'Latest products',
							'wholesale-ordering'
						);
						?>
					</h2>

				</div>

				<a
					class="wholesale-home-section-link"
					href="<?php echo esc_url( $shop_url ); ?>"
				>
					<?php
					echo esc_html__(
						'Open shop',
						'wholesale-ordering'
					);
					?>

					<span aria-hidden="true">→</span>
				</a>

			</div>

			<?php
			/*
			 * WooCommerce remains authoritative for:
			 *
			 * - product retrieval;
			 * - catalogue visibility;
			 * - product rendering;
			 * - price resolution.
			 */
			\WholesaleOrdering\Frontend\HomePage::render_products();
			?>

		</div>

	</section>

	<section class="wholesale-home-business">

		<div class="wholesale-home-container">

			<div class="wholesale-home-business-card">

				<div class="wholesale-home-business-content">

					<span class="wholesale-home-section-eyebrow">
						<?php
						echo esc_html__(
							'Business access',
							'wholesale-ordering'
						);
						?>
					</span>

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

				</div>

				<div class="wholesale-home-business-action">

					<a
						class="button"
						href="<?php echo esc_url( $account_url ); ?>"
					>
						<?php
						echo is_user_logged_in()
							? esc_html__(
								'Open My Account',
								'wholesale-ordering'
							)
							: esc_html__(
								'Get Started',
								'wholesale-ordering'
							);
						?>
					</a>

				</div>

			</div>

		</div>

	</section>

</main>

<?php

/*
 * Allow the active theme to provide its normal footer.
 */
get_footer();

/*
 * Guarantee that the WordPress footer lifecycle fires.
 *
 * SiteChrome owns the Wholesale Ordering application footer through
 * wp_footer. The did_action() guard prevents duplicate execution when
 * the active theme has already called wp_footer().
 */
if ( function_exists( 'wp_footer' ) && 0 === did_action( 'wp_footer' ) ) {
	wp_footer();
}