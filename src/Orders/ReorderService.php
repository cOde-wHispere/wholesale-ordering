<?php

namespace WholesaleOrdering\Orders;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates the wholesale ordering system with native WooCommerce reorder.
 *
 * Phase 4 deliberately does not rebuild orders manually.
 *
 * Native flow:
 *
 * Completed order
 *     -> WooCommerce order_again URL
 *     -> WooCommerce cart session
 *     -> CartIntegration
 *     -> current PricingService price
 *     -> checkout validation
 *     -> new order
 *
 * Historical order prices must never become current cart prices.
 */
final class ReorderService {

    /**
     * Register native WooCommerce reorder integration.
     *
     * @return void
     */
    public function register(): void {
        /*
         * V1 policy: only completed orders may use Order Again.
         */
        add_filter(
            'woocommerce_valid_order_statuses_for_order_again',
            array( $this, 'valid_order_statuses' ),
            10,
            1
        );

        /*
         * Observe native reorder completion without modifying historical
         * pricing information.
         */
        add_action(
            'woocommerce_ordered_again',
            array( $this, 'after_native_reorder' ),
            20,
            3
        );
    }

    /**
     * Return the Phase 4/V1 permitted reorder statuses.
     *
     * @param array<int|string,string> $statuses WooCommerce statuses.
     *
     * @return array<int,string>
     */
    public function valid_order_statuses(
        array $statuses
    ): array {
        return array( 'completed' );
    }

    /**
     * Determine whether a customer may use the reorder helper.
     *
     * WooCommerce remains responsible for the actual order_again request
     * authorization and nonce validation.
     *
     * @param int $order_id Order ID.
     *
     * @return bool
     */
    public function can_reorder(
        int $order_id
    ): bool {
        if ( $order_id <= 0 || ! is_user_logged_in() ) {
            return false;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            return false;
        }

        /*
         * Phase 4 only exposes completed orders to native reorder.
         */
        if (
            ! $order->has_status(
                $this->valid_order_statuses( array() )
            )
        ) {
            return false;
        }

        /*
         * Only the order owner may reorder their own customer order.
         *
         * Administrators/store managers are intentionally not granted
         * customer-facing reorder permission by this helper.
         */
        $customer_id = (int) $order->get_customer_id();

        if ( $customer_id <= 0 ) {
            return false;
        }

        return $customer_id === get_current_user_id();
    }

    /**
     * Build the native WooCommerce reorder URL.
     *
     * @param int $order_id Order ID.
     *
     * @return string Empty string when unavailable.
     */
    public function get_reorder_url(
        int $order_id
    ): string {
        if ( ! $this->can_reorder( $order_id ) ) {
            return '';
        }

        return wp_nonce_url(
            add_query_arg(
                'order_again',
                $order_id,
                wc_get_cart_url()
            ),
            'woocommerce-order_again'
        );
    }

    /**
     * Observe completion of native WooCommerce reorder cart population.
     *
     * No historical monetary values are copied into the current cart.
     *
     * @param int                         $order_id   Historical order ID.
     * @param array<int,\WC_Order_Item>   $order_items Historical items.
     * @param array<string,mixed>         $cart       Native cart array.
     *
     * @return void
     */
    public function after_native_reorder(
        int $order_id,
        array $order_items,
        array &$cart
    ): void {
        /*
         * Intentionally empty.
         *
         * Do NOT copy:
         *
         * - historical product price;
         * - historical wholesale price;
         * - historical discount;
         * - historical subtotal;
         * - historical tax;
         * - historical coupon amount.
         *
         * Native WooCommerce rebuilds the cart from the products.
         *
         * CartIntegration -> CartPricing -> PricingService then determines
         * the current authorized price.
         */
    }
}