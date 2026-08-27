<?php

namespace WholesaleOrdering\Cart;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;

defined( 'ABSPATH' ) || exit;

/**
 * Applies current authoritative pricing to cart products.
 */
final class CartPricing {

    private PricingService $pricing_service;

    public function __construct( ?PricingService $pricing_service = null ) {
        $this->pricing_service = $pricing_service ?? new PricingService();
    }

    /**
     * Recalculate every cart item using current server state.
     *
     * This intentionally creates a fresh CustomerContext on every
     * authoritative calculation so approval/suspension changes are picked up.
     */
    public function recalculate( ?\WC_Cart $cart = null ): void {
        $cart = $cart ?? WC()->cart;

        if ( ! $cart instanceof \WC_Cart ) {
            return;
        }

        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $customer = new CustomerContext();

        foreach ( $cart->get_cart() as $cart_item ) {
            if (
                ! isset( $cart_item['data'] )
                || ! $cart_item['data'] instanceof \WC_Product
            ) {
                continue;
            }

            $product = $cart_item['data'];

            /*
             * PricingService is the only authority deciding whether
             * wholesale pricing is currently permitted.
             */
            $price = $this->pricing_service->getEligiblePrice(
                $product,
                $customer
            );

            $product->set_price( $price );
        }
    }
}