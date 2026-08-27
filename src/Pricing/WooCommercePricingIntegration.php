<?php

namespace WholesaleOrdering\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates the authoritative PricingService with WooCommerce.
 *
 * Phase 4 responsibility:
 *
 * - Authorize WooCommerce product price reads through PricingService.
 * - Render the exact price authorized for the current customer.
 * - Protect variation AJAX price payloads.
 *
 * This class deliberately does NOT mutate cart item prices.
 *
 * Cart price mutation belongs exclusively to:
 *
 * CartIntegration
 *     -> CartPricing
 *         -> PricingService
 *
 * The V1 pricing model is the specification's two-price model:
 *
 * - Regular Price for guests and non-approved customers.
 * - Wholesale Price for authenticated, approved wholesale customers when
 *   the wholesale price is valid.
 *
 * No separate registered-customer discount is introduced here.
 */
final class WooCommercePricingIntegration {

	/**
	 * Authoritative pricing service.
	 *
	 * @var PricingService
	 */
	private PricingService $pricing_service;

	/**
	 * Constructor.
	 *
	 * @param PricingService|null $pricing_service Pricing service.
	 */
	public function __construct(
		?PricingService $pricing_service = null
	) {
		$this->pricing_service = $pricing_service ?? new PricingService();
	}

	/**
	 * Register WooCommerce pricing integration hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'woocommerce_product_get_price',
			array( $this, 'filter_product_price' ),
			999,
			2
		);

		add_filter(
			'woocommerce_product_variation_get_price',
			array( $this, 'filter_product_price' ),
			999,
			2
		);

		add_filter(
			'woocommerce_get_price_html',
			array( $this, 'filter_price_html' ),
			999,
			2
		);

		add_filter(
			'woocommerce_available_variation',
			array( $this, 'filter_available_variation' ),
			999,
			3
		);
	}

	/**
	 * Filter a WooCommerce product or variation price.
	 *
	 * PricingService is the sole authority for the applicable price.
	 *
	 * @param string|float $price   Existing WooCommerce price.
	 * @param \WC_Product  $product Product or variation.
	 *
	 * @return string
	 */
	public function filter_product_price(
		$price,
		\WC_Product $product
	): string {
		$context = new CustomerContext();

		return $this->pricing_service->getEligiblePrice(
			$product,
			$context
		);
	}

	/**
	 * Filter rendered WooCommerce price HTML.
	 *
	 * Approved wholesale customers receive the authoritative Wholesale Price.
	 * Guests and non-approved customers retain WooCommerce's normal rendering
	 * of the Regular Price.
	 *
	 * @param string      $html    Existing price HTML.
	 * @param \WC_Product $product Product.
	 *
	 * @return string
	 */
	public function filter_price_html(
		string $html,
		\WC_Product $product
	): string {
		$context = new CustomerContext();

		if ( ! $context->can_use_wholesale_pricing() ) {
			return $html;
		}

		$eligible_price = $this->pricing_service->getEligiblePrice(
			$product,
			$context
		);

		if ( '' === $eligible_price ) {
			return $html;
		}

		$display_price = wc_get_price_to_display(
			$product,
			array(
				'price' => $eligible_price,
			)
		);

		return wc_price( $display_price ) . $product->get_price_suffix();
	}

	/**
	 * Protect WooCommerce variation AJAX payloads.
	 *
	 * Only the price applicable to the current customer is exposed.
	 *
	 * @param array<string,mixed>    $variation_data Existing variation data.
	 * @param \WC_Product             $product        Parent product.
	 * @param \WC_Product_Variation   $variation      Variation.
	 *
	 * @return array<string,mixed>
	 */
	public function filter_available_variation(
		array $variation_data,
		\WC_Product $product,
		\WC_Product_Variation $variation
	): array {
		$context = new CustomerContext();

		$eligible_price = $this->pricing_service->getEligiblePrice(
			$variation,
			$context
		);

		unset(
			$variation_data['wholesale_price'],
			$variation_data['wholesale_min_qty'],
			$variation_data['wholesale_qty_step'],
			$variation_data['wholesale_only']
		);

		if ( '' === $eligible_price ) {
			return $variation_data;
		}

		$display_price = wc_get_price_to_display(
			$variation,
			array(
				'price' => $eligible_price,
			)
		);

		$variation_data['display_price'] = $display_price;

		$variation_data['price_html'] = wc_price(
			$display_price
		) . $variation->get_price_suffix();

		if ( $context->can_use_wholesale_pricing() ) {
			$variation_data['display_regular_price'] = $display_price;
		} else {
			$variation_data['display_regular_price'] =
				wc_get_price_to_display(
					$variation,
					array(
						'price' => $variation->get_regular_price( 'edit' ),
					)
				);
		}

		return $variation_data;
	}
}
