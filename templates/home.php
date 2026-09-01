<?php

use WholesaleOrdering\Frontend\HomePage;

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="wholesale-ordering-homepage">

	<section class="wholesale-home-hero">
		<div class="wholesale-home-container">

			<div class="wholesale-home-hero-content">

				<p class="wholesale-home-eyebrow">
					<?php
					echo esc_html__(
						'Wholesale Ordering',
						'wholesale-ordering'
					);
					?>
				</p>

				<h1>
					<?php
					echo esc_html__(
						'Business ordering made simple.',
						'wholesale-ordering'
					);
					?>
				</h1>

				<p class="wholesale-home-hero-text">
					<?php
					echo esc_html__(
						'Browse our products, find what your business needs, and place your order through a straightforward WooCommerce shopping experience.',
						'wholesale-ordering'
					);
					?>
				</p>

				<div class="wholesale-home-actions">

					<a
						class="button wholesale-home-primary-button"
						href="<?php echo esc_url( HomePage::shop_url() ); ?>"
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
							href="<?php echo esc_url( HomePage::account_url() ); ?>"
						>
							<?php
							echo esc_html__(
								'Create an Account',
								'wholesale-ordering'
							);
							?>
						</a>

					<?php endif; ?>

				</div>

			</div>

		</div>
	</section>

	<section class="wholesale-home-categories">
		<div class="wholesale-home-container">

			<div class="wholesale-home-section-heading">

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

				<p>
					<?php
					echo esc_html__(
						'Start with a category and quickly find the products you need.',
						'wholesale-ordering'
					);
					?>
				</p>

			</div>

			<div class="wholesale-home-woocommerce-output">
				<?php HomePage::render_categories(); ?>
			</div>

		</div>
	</section>

	<section class="wholesale-home-products">
		<div class="wholesale-home-container">

			<div class="wholesale-home-section-heading">

				<p class="wholesale-home-eyebrow">
					<?php
					echo esc_html__(
						'Products',
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

				<p>
					<?php
					echo esc_html__(
						'Browse a selection from the current catalogue.',
						'wholesale-ordering'
					);
					?>
				</p>

			</div>

			<div class="wholesale-home-woocommerce-output">
				<?php HomePage::render_products(); ?>
			</div>

			<div class="wholesale-home-section-action">

				<a
					class="button"
					href="<?php echo esc_url( HomePage::shop_url() ); ?>"
				>
					<?php
					echo esc_html__(
						'View All Products',
						'wholesale-ordering'
					);
					?>
				</a>

			</div>

		</div>
	</section>

	<section class="wholesale-home-business">
		<div class="wholesale-home-container">

			<div class="wholesale-home-business-card">

				<div>
					<p class="wholesale-home-eyebrow">
						<?php
						echo esc_html__(
							'For business customers',
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
							'Create an account and submit your wholesale application. Approval and wholesale access are handled through the application process.',
							'wholesale-ordering'
						);
						?>
					</p>
				</div>

				<div class="wholesale-home-business-action">

					<a
						class="button"
						href="<?php echo esc_url( HomePage::account_url() ); ?>"
					>
						<?php
						echo esc_html__(
							'My Account',
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
get_footer();