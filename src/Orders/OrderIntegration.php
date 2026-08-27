<?php

namespace WholesaleOrdering\Orders;

use WholesaleOrdering\Pricing\CustomerContext;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves transaction-time pricing information on order items.
 */
final class OrderIntegration {

    public function register(): void {
        add_action(
            'woocommerce_checkout_create_order_line_item',
            array( $this, 'snapshot_line_item_price' ),
            999,
            4
        );
    }

    /**
     * Store transaction-time price context.
     *
     * WooCommerce itself persists subtotal/total/quantity on the order item.
     * These metadata fields make the pricing context explicit for auditing.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array<string,mixed>    $values Cart item values.
     * @param \WC_Order              $order Order.
     */
    public function snapshot_line_item_price(
        \WC_Order_Item_Product $item,
        $cart_item_key,
        array $values,
        \WC_Order $order
    ): void {
        if (
            ! isset( $values['data'] )
            || ! $values['data'] instanceof \WC_Product
        ) {
            return;
        }

        $quantity = (float) $item->get_quantity();

        if ( $quantity <= 0 ) {
            return;
        }

        /*
         * Subtotal is the transaction unit price before order-level
         * discounts. Total is the amount actually charged for the line
         * after applicable line/order discounts, before tax.
         */
        $subtotal_unit_price = (float) $item->get_subtotal() / $quantity;
        $charged_unit_price  = (float) $item->get_total() / $quantity;

        $decimals = wc_get_price_decimals();

        $customer = new CustomerContext(
            $order->get_customer_id() > 0
                ? (int) $order->get_customer_id()
                : null
        );

        $item->add_meta_data(
            '_wholesale_ordering_unit_price',
            wc_format_decimal(
                $subtotal_unit_price,
                $decimals
            ),
            true
        );

        $item->add_meta_data(
            '_wholesale_ordering_charged_unit_price',
            wc_format_decimal(
                $charged_unit_price,
                $decimals
            ),
            true
        );

        $item->add_meta_data(
            '_wholesale_ordering_price_context',
            $customer->can_use_wholesale_pricing()
                ? 'wholesale'
                : 'regular',
            true
        );
    }
}