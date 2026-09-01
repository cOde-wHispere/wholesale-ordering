<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;

defined( 'ABSPATH' ) || exit;

/**
 * Homepage QA contract tests.
 *
 * These tests verify the homepage implementation remains a presentation
 * layer around the existing WooCommerce catalogue/pricing architecture.
 *
 * They do NOT calculate, replace, or compare customer prices themselves.
 *
 * Manual browser QA remains required for:
 *
 * - Guest
 * - Registered/Pending
 * - Approved Wholesale
 * - Mobile
 * - Tablet
 * - Desktop
 *
 * The critical pricing acceptance rule is that homepage product cards must
 * obtain the same customer-authorized price as the normal WooCommerce Shop
 * product loop.
 */
final class HomePageQATest extends TestCase {

	/**
	 * Locate the homepage implementation.
	 *
	 * @return string
	 */
	private function get_homepage_class_file(): string {
		$file = dirname( __DIR__ ) . '/src/Frontend/HomePage.php';

		$this->assertFileExists(
			$file,
			'Homepage implementation file is missing.'
		);

		return $file;
	}

	/**
	 * Locate the homepage template.
	 *
	 * @return string
	 */
	private function get_homepage_template(): string {
		$file = dirname( __DIR__ ) . '/templates/home.php';

		$this->assertFileExists(
			$file,
			'Homepage template is missing.'
		);

		return $file;
	}

	/**
	 * Read the homepage implementation.
	 *
	 * @return string
	 */
	private function get_homepage_class_source(): string {
		$source = file_get_contents(
			$this->get_homepage_class_file()
		);

		$this->assertIsString( $source );

		return $source;
	}

	/**
	 * Read the homepage template.
	 *
	 * @return string
	 */
	private function get_homepage_template_source(): string {
		$source = file_get_contents(
			$this->get_homepage_template()
		);

		$this->assertIsString( $source );

		return $source;
	}

	/**
	 * Homepage implementation must exist and remain separate from the
	 * existing storefront discovery implementation.
	 */
	public function test_homepage_implementation_and_template_exist(): void {
		$this->get_homepage_class_file();
		$this->get_homepage_template();
	}

	/**
	 * Homepage must not become a second pricing engine.
	 *
	 * The homepage must never directly read the stored wholesale price or
	 * independently decide customer eligibility.
	 */
	public function test_homepage_does_not_implement_pricing_logic(): void {
		$source = $this->get_homepage_class_source()
			. "\n"
			. $this->get_homepage_template_source();

		$forbidden_patterns = array(
			'get_wholesale_price',
			'wholesale_price',
			'getEligiblePrice',
			'can_use_wholesale_pricing',
			'CustomerContext',
			'PricingService',
			'get_regular_price',
			'set_price',
			'woocommerce_product_get_price',
			'woocommerce_get_price',
		);

		foreach ( $forbidden_patterns as $pattern ) {
			$this->assertStringNotContainsString(
				$pattern,
				$source,
				sprintf(
					'Homepage must not contain independent pricing logic: %s',
					$pattern
				)
			);
		}
	}

	/**
	 * Homepage must use the existing WooCommerce product presentation path.
	 *
	 * This prevents a custom homepage card renderer from becoming a second
	 * product/pricing presentation engine.
	 */
	public function test_homepage_uses_woocommerce_product_rendering(): void {
		$source = $this->get_homepage_class_source()
			. "\n"
			. $this->get_homepage_template_source();

		$woocommerce_rendering_contracts = array(
			'woocommerce',
			'products',
		);

		foreach ( $woocommerce_rendering_contracts as $pattern ) {
			$this->assertStringContainsString(
				$pattern,
				strtolower( $source ),
				sprintf(
					'Homepage should remain connected to WooCommerce rendering: %s',
					$pattern
				)
			);
		}
	}

	/**
	 * Homepage must not expose a stored wholesale price in its markup.
	 */
	public function test_homepage_does_not_print_hidden_wholesale_price(): void {
		$source = $this->get_homepage_class_source()
			. "\n"
			. $this->get_homepage_template_source();

		$this->assertDoesNotMatchRegularExpression(
			'/wholesale[\s_-]*price/i',
			$source,
			'Homepage must not directly print or expose the stored wholesale price.'
		);
	}

	/**
	 * Homepage must provide a catalogue route to the existing Shop page.
	 */
	public function test_homepage_contains_shop_navigation(): void {
		$source = $this->get_homepage_class_source()
			. "\n"
			. $this->get_homepage_template_source();

		$this->assertMatchesRegularExpression(
			'/shop|wc_get_page_permalink|woocommerce/i',
			$source,
			'Homepage should provide navigation into the existing WooCommerce catalogue.'
		);
	}

	/**
	 * Homepage must contain the wholesale-access call to action.
	 */
	public function test_homepage_contains_wholesale_access_call_to_action(): void {
		$source = $this->get_homepage_class_source()
			. "\n"
			. $this->get_homepage_template_source();

		$this->assertMatchesRegularExpression(
			'/wholesale/i',
			$source,
			'Homepage should retain the wholesale customer access/application message.'
		);
	}

	/**
	 * Homepage template must not contain hard-coded product prices.
	 */
	public function test_homepage_template_contains_no_hardcoded_product_price(): void {
		$template = $this->get_homepage_template_source();

		$this->assertDoesNotMatchRegularExpression(
			'/[$€£]\s*[0-9]+(?:[.,][0-9]{1,2})?/i',
			$template,
			'Product prices must come from WooCommerce, not hard-coded homepage markup.'
		);
	}

	/**
	 * Homepage CSS must remain presentation-only.
	 */
	public function test_homepage_css_does_not_define_price_behavior(): void {
		$stylesheet = dirname( __DIR__ ) . '/assets/css/frontend.css';

		$this->assertFileExists( $stylesheet );

		$css = file_get_contents( $stylesheet );

		$this->assertIsString( $css );

		$this->assertDoesNotMatchRegularExpression(
			'/wholesale[\s_-]*price/i',
			$css,
			'Homepage CSS must not implement or expose pricing behavior.'
		);
	}

	/**
	 * Footer styling must be scoped to the plugin homepage/footer classes.
	 */
	public function test_footer_css_is_scoped(): void {
		$stylesheet = dirname( __DIR__ ) . '/assets/css/frontend.css';

		$css = file_get_contents( $stylesheet );

		$this->assertIsString( $css );

		$this->assertStringContainsString(
			'.wholesale-ordering-home-footer',
			$css
		);

		$this->assertStringContainsString(
			'.wholesale-ordering-home-footer a',
			$css
		);
	}
}