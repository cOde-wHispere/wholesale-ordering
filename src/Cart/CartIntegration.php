<?php

namespace WholesaleOrdering\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Connects Phase 4 cart rules to WooCommerce.
 *
 * Responsibilities:
 *
 * - Validate product additions.
 * - Validate cart quantity changes.
 * - Apply the authoritative current customer price to cart products.
 * - Validate the complete cart before checkout.
 *
 * CartIntegration is the single WooCommerce integration point that mutates
 * cart product prices.
 */
final class CartIntegration {

    /**
     * Cart validation service.
     *
     * @var CartValidator
     */
    private CartValidator $validator;

    /**
     * Cart pricing service.
     *
     * @var CartPricing
     */
    private CartPricing $pricing;

    /**
     * Constructor.
     *
     * @param CartValidator|null $validator Cart validator.
     * @param CartPricing|null   $pricing   Cart pricing service.
     */
    public function __construct(
        ?CartValidator $validator = null,
        ?CartPricing $pricing = null
    ) {
        $this->validator = $validator ?? new CartValidator();
        $this->pricing   = $pricing ?? new CartPricing();
    }

    /**
     * Register authoritative cart hooks.
     *
     * @return void
     */
    public function register(): void {
        /*
         * Classic WooCommerce and Store API both use this validation filter
         * when an item is added to the cart.
         */
        add_filter(
            'woocommerce_add_to_cart_validation',
            array( $this, 'validate_add_to_cart' ),
            999,
            5
        );

        /*
         * Classic cart quantity update validation.
         */
        add_filter(
            'woocommerce_update_cart_validation',
            array( $this, 'validate_cart_quantity_update' ),
            999,
            4
        );

        /*
         * CartPricing is the ONLY Phase 4 component allowed to mutate
         * cart product prices.
         *
         * It must run late so the authoritative customer price is present
         * before WooCommerce calculates totals.
         */
        add_action(
            'woocommerce_before_calculate_totals',
            array( $this, 'recalculate_prices' ),
            1000
        );

        /*
         * Final cart-level validation.
         *
         * WooCommerce's Store API also executes this legacy cart validation
         * path and converts notices into Store API errors.
         */
        add_action(
            'woocommerce_check_cart_items',
            array( $this, 'validate_cart_items' ),
            1000
        );
    }

    /**
     * Validate a product before it is added to the cart.
     *
     * @param bool              $passed      Existing validation result.
     * @param int               $product_id Product ID.
     * @param float|int         $quantity    Requested quantity.
     * @param int               $variation_id Variation ID.
     * @param array<string,mixed> $variations Variation attributes.
     *
     * @return bool
     */
    public function validate_add_to_cart(
        $passed,
        $product_id,
        $quantity,
        $variation_id = 0,
        $variations = array()
    ) {
        if ( ! $passed ) {
            return false;
        }

        $product = $variation_id
            ? wc_get_product( $variation_id )
            : wc_get_product( $product_id );

        if ( ! $product instanceof \WC_Product ) {
            wc_add_notice(
                __(
                    'The selected product is no longer available.',
                    'wholesale-ordering'
                ),
                'error'
            );

            return false;
        }

        $errors = $this->validator->validate_add_to_cart(
            $product,
            (float) $quantity
        );

        foreach ( $errors as $error ) {
            wc_add_notice(
                $error->get_error_message(),
                'error'
            );
        }

        return empty( $errors );
    }

    /**
     * Validate a classic cart quantity update.
     *
     * @param bool               $passed        Existing validation result.
     * @param string             $cart_item_key Cart item key.
     * @param array<string,mixed> $values        Cart item values.
     * @param float|int           $quantity      Requested quantity.
     *
     * @return bool
     */
    public function validate_cart_quantity_update(
        $passed,
        $cart_item_key,
        $values,
        $quantity
    ) {
        if ( ! $passed || ! is_array( $values ) ) {
            return $passed;
        }

        $cart_item             = $values;
        $cart_item['quantity'] = (float) $quantity;

        /*
         * Do not mutate pricing here.
         *
         * CartPricing remains the single price mutation owner.
         */
        $errors = $this->validator->validate_cart_item(
            $cart_item,
            (string) $cart_item_key,
            false
        );

        foreach ( $errors as $error ) {
            wc_add_notice(
                $error->get_error_message(),
                'error'
            );
        }

        return empty( $errors ) ? $passed : false;
    }

    /**
     * Apply authoritative customer pricing to the cart.
     *
     * @param \WC_Cart $cart WooCommerce cart.
     *
     * @return void
     */
    public function recalculate_prices( \WC_Cart $cart ): void {
        $this->pricing->recalculate( $cart );
    }

    /**
     * Validate the complete cart.
     *
     * @return void
     */
    public function validate_cart_items(): void {
        foreach ( $this->validator->validate_cart() as $error ) {
            wc_add_notice(
                $error->get_error_message(),
                'error'
            );
        }
    }
}