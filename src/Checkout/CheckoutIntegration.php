<?php

namespace WholesaleOrdering\Checkout;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;
use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates Phase 4 checkout rules with the WooCommerce Store API.
 *
 * Classic checkout validation is owned exclusively by CheckoutValidator.
 *
 * This class must therefore NOT register:
 *
 * - woocommerce_check_cart_items
 * - woocommerce_after_checkout_validation
 *
 * Those are the classic server-side checkout boundary owned by
 * CheckoutValidator.
 *
 * This class is responsible only for WooCommerce Store API / Checkout Block
 * validation.
 */
final class CheckoutIntegration {

	/**
	 * Pricing service.
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
	 * Register Store API checkout validation hooks.
	 *
	 * Classic checkout validation belongs exclusively to CheckoutValidator.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'woocommerce_store_api_cart_errors',
			array( $this, 'validate_store_api_cart' ),
			20,
			2
		);

		add_action(
			'woocommerce_store_api_validate_cart_item',
			array( $this, 'validate_store_api_cart_item' ),
			20,
			2
		);
	}

	/**
	 * Validate Store API cart.
	 *
	 * @param \WP_Error $errors Cart errors.
	 * @param \WC_Cart  $cart   Current cart.
	 *
	 * @return void
	 */
	public function validate_store_api_cart(
		\WP_Error $errors,
		\WC_Cart $cart
	): void {
		$cart_errors = $this->validate_cart( $cart );

		foreach ( $cart_errors->get_error_messages() as $message ) {
			$errors->add(
				'wholesale_cart_validation',
				$message
			);
		}
	}

	/**
	 * Validate an individual Store API cart item.
	 *
	 * @param \WC_Product         $product   Product.
	 * @param array<string,mixed> $cart_item Cart item.
	 *
	 * @return void
	 *
	 * @throws \Exception When validation fails.
	 */
	public function validate_store_api_cart_item(
		\WC_Product $product,
		array $cart_item
	): void {
		$quantity = isset( $cart_item['quantity'] )
			? (float) $cart_item['quantity']
			: 0.0;

		$context = new CustomerContext();

		$message = $this->validate_product_quantity(
			$product,
			$quantity,
			$context
		);

		if ( '' !== $message ) {
			throw new \Exception( $message );
		}
	}

	/**
	 * Validate the complete cart for Store API requests.
	 *
	 * @param \WC_Cart $cart Cart.
	 *
	 * @return \WP_Error
	 */
	public function validate_cart(
		\WC_Cart $cart
	): \WP_Error {
		$errors = new \WP_Error();
		$context = new CustomerContext();

		foreach ( $cart->get_cart() as $cart_item ) {
			if (
				! isset( $cart_item['data'] )
				|| ! $cart_item['data'] instanceof \WC_Product
			) {
				$errors->add(
					'wholesale_invalid_cart_item',
					__(
						'An item in your cart is no longer available.',
						'wholesale-ordering'
					)
				);

				continue;
			}

			$product = $cart_item['data'];

			$quantity = isset( $cart_item['quantity'] )
				? (float) $cart_item['quantity']
				: 0.0;

			$eligible_price = $this->pricing_service->getEligiblePrice(
				$product,
				$context
			);

			/*
			 * CartIntegration -> CartPricing owns application of the
			 * authoritative current price to the cart product object.
			 * This boundary only verifies that the cart carries that price.
			 */
			$current_cart_price = (string) $product->get_price( 'edit' );

			if (
				wc_format_decimal(
					$current_cart_price,
					wc_get_price_decimals()
				) !== wc_format_decimal(
					$eligible_price,
					wc_get_price_decimals()
				)
			) {
				$errors->add(
					'wholesale_price_changed',
					sprintf(
						__(
							'The price for %s has changed. Your cart has been refreshed. Please review it before checkout.',
							'wholesale-ordering'
						),
						$product->get_name()
					)
				);

				continue;
			}

			if ( ! $product->has_enough_stock( $quantity ) ) {
				$errors->add(
					'wholesale_stock_changed',
					sprintf(
						__(
							'There is not enough current stock for %s.',
							'wholesale-ordering'
						),
						$product->get_name()
					)
				);

				continue;
			}

			$message = $this->validate_product_quantity(
				$product,
				$quantity,
				$context
			);

			if ( '' !== $message ) {
				$errors->add(
					'wholesale_quantity_validation',
					$message
				);
			}
		}

		return $errors;
	}

	/**
	 * Validate quantity rules for a product.
	 *
	 * @param \WC_Product     $product  Product.
	 * @param float           $quantity Quantity.
	 * @param CustomerContext $context  Customer context.
	 *
	 * @return string Empty string when valid.
	 */
	private function validate_product_quantity(
		\WC_Product $product,
		float $quantity,
		CustomerContext $context
	): string {
		if ( $quantity <= 0 ) {
			return sprintf(
				__(
					'%s has an invalid quantity.',
					'wholesale-ordering'
				),
				$product->get_name()
			);
		}

		if ( ! $context->can_use_wholesale_pricing() ) {
			return '';
		}

		$minimum = (float) ProductFields::get_wholesale_min_qty(
			$product
		);

		$step = (float) ProductFields::get_wholesale_qty_step(
			$product
		);

		if ( $quantity + 0.0000001 < $minimum ) {
			return sprintf(
				__(
					'%s requires a minimum wholesale quantity of %s.',
					'wholesale-ordering'
				),
				$product->get_name(),
				wc_format_localized_decimal( $minimum )
			);
		}

		if (
			$step > 0
			&& ! $this->is_valid_step(
				$quantity,
				$step
			)
		) {
			return sprintf(
				__(
					'%s must be ordered in quantities of %s.',
					'wholesale-ordering'
				),
				$product->get_name(),
				wc_format_localized_decimal( $step )
			);
		}

		return '';
	}

	/**
	 * Validate quantity against a step.
	 *
	 * @param float $quantity Quantity.
	 * @param float $step     Step.
	 *
	 * @return bool
	 */
	private function is_valid_step(
		float $quantity,
		float $step
	): bool {
		if ( $step <= 0 ) {
			return true;
		}

		$ratio = $quantity / $step;

		return abs( $ratio - round( $ratio ) ) < 0.000001;
	}
}
