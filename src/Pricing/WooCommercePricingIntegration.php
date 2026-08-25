<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates the authoritative pricing service with WooCommerce.
 */
final class WooCommercePricingIntegration {

    /**
     * Pricing service.
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
        $this->pricing_service = $pricing_service
            ?? new PricingService();
    }

    /**
     * Register WooCommerce pricing hooks.
     *
     * @return void
     */
    public function register(): void {
        /*
         * Product and variation price getters.
         */
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

        /*
         * Price HTML is explicitly rebuilt for approved customers so that
         * WooCommerce cannot accidentally expose the underlying regular/sale
         * price pair while rendering a wholesale price.
         */
        add_filter(
            'woocommerce_get_price_html',
            array( $this, 'filter_price_html' ),
            999,
            2
        );

        /*
         * Cart totals are re-authorized server-side on every totals
         * calculation.
         */
        add_action(
            'woocommerce_before_calculate_totals',
            array( $this, 'recalculate_cart_prices' ),
            999
        );

        /*
         * WooCommerce variation AJAX payload.
         */
        add_filter(
            'woocommerce_available_variation',
            array( $this, 'filter_available_variation' ),
            999,
            3
        );
    }

    /**
     * Filter product/variation price.
     *
     * @param string|float $price   Current price.
     * @param \WC_Product  $product Product.
     *
     * @return string|float
     */
    public function filter_product_price(
        $price,
        \WC_Product $product
    ) {
        $context = new CustomerContext();

        return $this->pricing_service->getEligiblePrice(
            $product,
            $context
        );
    }

    /**
     * Filter rendered price HTML.
     *
     * @param string       $html    Price HTML.
     * @param \WC_Product  $product Product.
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

        $price = $this->pricing_service->getEligiblePrice(
            $product,
            $context
        );

        return wc_price(
            wc_get_price_to_display(
                $product,
                array(
                    'price' => $price,
                )
            )
        ) . $product->get_price_suffix();
    }

    /**
     * Recalculate all cart item prices against the current customer context.
     *
     * @param \WC_Cart $cart Cart.
     *
     * @return void
     */
    public function recalculate_cart_prices(
        \WC_Cart $cart
    ): void {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        $context = new CustomerContext();

        foreach ( $cart->get_cart() as $cart_item ) {
            if (
                ! isset( $cart_item['data'] )
                || ! $cart_item['data'] instanceof \WC_Product
            ) {
                continue;
            }

            $product = $cart_item['data'];

            $eligible_price = $this->pricing_service->getEligiblePrice(
                $product,
                $context
            );

            $product->set_price(
                $eligible_price
            );
        }
    }

    /**
     * Protect variation AJAX payloads.
     *
     * @param array<string,mixed> $variation_data Variation payload.
     * @param \WC_Product          $product        Parent product.
     * @param \WC_Product_Variation $variation      Variation.
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

        /*
         * WooCommerce variation data is public application data. Never add
         * the raw wholesale metadata to this payload.
         */
        unset(
            $variation_data['wholesale_price'],
            $variation_data['wholesale_min_qty'],
            $variation_data['wholesale_qty_step'],
            $variation_data['wholesale_only']
        );

        $variation_data['display_price'] = wc_get_price_to_display(
            $variation,
            array(
                'price' => $eligible_price,
            )
        );

        $variation_data['price_html'] = wc_price(
            $variation_data['display_price']
        ) . $variation->get_price_suffix();

        if ( $context->can_use_wholesale_pricing() ) {
            $variation_data['display_regular_price'] = $variation_data['display_price'];
        } else {
            $variation_data['display_regular_price'] = wc_get_price_to_display(
                $variation,
                array(
                    'price' => $variation->get_regular_price( 'edit' ),
                )
            );
        }

        return $variation_data;
    }
}