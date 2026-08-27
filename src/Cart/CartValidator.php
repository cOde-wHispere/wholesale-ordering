<?php

namespace WholesaleOrdering\Cart;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;
use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Authoritative server-side validation for WooCommerce cart state.
 */
final class CartValidator {

    private PricingService $pricing_service;

    public function __construct( ?PricingService $pricing_service = null ) {
        $this->pricing_service = $pricing_service ?? new PricingService();
    }

    /**
     * Validate every current cart item.
     *
     * @param \WC_Cart|null $cart Cart instance.
     * @param bool           $check_price Whether to compare the current cart price.
     *
     * @return array<int,\WP_Error>
     */
    public function validate_cart(
        ?\WC_Cart $cart = null,
        bool $check_price = true
    ): array {
        $cart = $cart ?? WC()->cart;

        if ( ! $cart instanceof \WC_Cart ) {
            return array();
        }

        $errors = array();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $errors = array_merge(
                $errors,
                $this->validate_cart_item(
                    $cart_item,
                    (string) $cart_item_key,
                    $check_price
                )
            );
        }

        return $errors;
    }

    /**
     * Validate one cart item against authoritative server state.
     *
     * @param array<string,mixed> $cart_item Cart item.
     * @param string              $cart_item_key Cart item key.
     * @param bool                $check_price Check authoritative price.
     *
     * @return array<int,\WP_Error>
     */
    public function validate_cart_item(
        array $cart_item,
        string $cart_item_key = '',
        bool $check_price = true
    ): array {
        $errors = array();

        $product = isset( $cart_item['data'] )
            && $cart_item['data'] instanceof \WC_Product
            ? $cart_item['data']
            : null;

        if ( ! $product instanceof \WC_Product ) {
            return array(
                new \WP_Error(
                    'wholesale_ordering_invalid_product',
                    __( 'One of the products in your cart is no longer available.', 'wholesale-ordering' )
                ),
            );
        }

        $product_id = $product->get_id();
        $quantity   = isset( $cart_item['quantity'] )
            ? (float) $cart_item['quantity']
            : 0.0;

        /*
         * Product state is authoritative.
         */
        if (
            'publish' !== $product->get_status()
            || ! $product->is_purchasable()
        ) {
            $errors[] = new \WP_Error(
                'wholesale_ordering_product_unavailable',
                sprintf(
                    __( 'Product #%d is no longer available for purchase.', 'wholesale-ordering' ),
                    $product_id
                )
            );

            return $errors;
        }

        /*
         * Quantity is authoritative.
         */
        if ( $quantity <= 0 ) {
            $errors[] = new \WP_Error(
                'wholesale_ordering_invalid_quantity',
                __( 'A cart quantity must be greater than zero.', 'wholesale-ordering' )
            );

            return $errors;
        }

        /*
         * Stock is always revalidated server-side.
         */
        if (
            ! $product->is_in_stock()
            && ! $product->backorders_allowed()
        ) {
            $errors[] = new \WP_Error(
                'wholesale_ordering_out_of_stock',
                sprintf(
                    __( 'Product #%d is out of stock.', 'wholesale-ordering' ),
                    $product_id
                )
            );
        } elseif ( ! $product->has_enough_stock( $quantity ) ) {
            $errors[] = new \WP_Error(
                'wholesale_ordering_insufficient_stock',
                sprintf(
                    __( 'There is not enough stock available for product #%d.', 'wholesale-ordering' ),
                    $product_id
                )
            );
        }

        /*
         * Customer authorization is resolved from current server state.
         * This is what makes BR-01 and BR-02 work.
         */
        $customer = new CustomerContext();

        if ( $customer->can_use_wholesale_pricing() ) {
            $minimum = ProductFields::get_wholesale_min_qty( $product );
            $step    = ProductFields::get_wholesale_qty_step( $product );

            if ( $quantity + 0.0000001 < $minimum ) {
                $errors[] = new \WP_Error(
                    'wholesale_ordering_minimum_quantity',
                    sprintf(
                        __( 'The minimum quantity for this product is %s.', 'wholesale-ordering' ),
                        wc_format_localized_decimal( $minimum )
                    )
                );
            }

            if (
                $step > 0
                && ! $this->is_valid_step( $quantity, $step )
            ) {
                $errors[] = new \WP_Error(
                    'wholesale_ordering_quantity_step',
                    sprintf(
                        __( 'The quantity for this product must be in increments of %s.', 'wholesale-ordering' ),
                        wc_format_localized_decimal( $step )
                    )
                );
            }
        }

        /*
         * Price validation occurs after CartPricing has recalculated the
         * product price from the current customer/product state.
         */
        if ( $check_price ) {
            $expected_price = $this->pricing_service->getEligiblePrice(
                $product,
                $customer
            );

            $current_price = $product->get_price( 'edit' );

            if ( ! $this->prices_match( $current_price, $expected_price ) ) {
                $errors[] = new \WP_Error(
                    'wholesale_ordering_price_changed',
                    __( 'A product price changed. Your cart has been recalculated; please review it before continuing.', 'wholesale-ordering' )
                );
            }
        }

        return $errors;
    }

    /**
     * Validate a product before it enters the cart.
     *
     * Price is deliberately not compared here because the authoritative
     * cart price is applied during cart totals calculation.
     *
     * @return array<int,\WP_Error>
     */
    public function validate_add_to_cart(
        \WC_Product $product,
        float $quantity
    ): array {
        return $this->validate_cart_item(
            array(
                'data'     => $product,
                'quantity' => $quantity,
            ),
            '',
            false
        );
    }

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

    private function prices_match(
        $actual,
        $expected
    ): bool {
        $decimals = wc_get_price_decimals();

        return abs(
            (float) wc_format_decimal( $actual, $decimals )
            - (float) wc_format_decimal( $expected, $decimals )
        ) < 0.000001;
    }
}