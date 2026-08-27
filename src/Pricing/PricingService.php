<?php

namespace WholesaleOrdering\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Central authoritative V1 pricing service.
 *
 * V1 pricing rules:
 *
 * - Guest: standard/base WooCommerce price.
 * - Registered customer: 2% discount from standard/base WooCommerce price.
 * - Approved wholesale customer: 4% discount from standard/base WooCommerce price.
 * - Pending/rejected/suspended customer: standard/base WooCommerce price.
 *
 * The 2% and 4% discounts are mutually exclusive and are never compounded.
 *
 * The WooCommerce Regular Price is always the pricing anchor.
 *
 * No caller should independently calculate customer pricing.
 */
final class PricingService {

    /**
     * Registered customer discount.
     *
     * @var string
     */
    private const REGISTERED_DISCOUNT = '0.02';

    /**
     * Approved wholesale customer discount.
     *
     * @var string
     */
    private const WHOLESALE_DISCOUNT = '0.04';

    /**
     * Return the authoritative eligible price.
     *
     * The calculation is always anchored to the product's standard/base
     * WooCommerce Regular Price.
     *
     * V1:
     *
     * Guest:
     *     regular price
     *
     * Registered:
     *     regular price - 2%
     *
     * Approved wholesale:
     *     regular price - 4%
     *
     * Pending/rejected/suspended:
     *     regular price
     *
     * The discount is never applied to an already-discounted value.
     *
     * @param \WC_Product     $product  Product or variation.
     * @param CustomerContext $customer Customer pricing context.
     *
     * @return string
     */
    public function getEligiblePrice(
        \WC_Product $product,
        CustomerContext $customer
    ): string {
        /*
         * Always retrieve the underlying WooCommerce Regular Price directly.
         *
         * The edit context prevents this service from recursively invoking
         * WooCommerce product price filters.
         */
        $regular_price = $product->get_regular_price( 'edit' );

        if ( '' === $regular_price || null === $regular_price ) {
            return '';
        }

        $discount_rate = $customer->get_discount_rate();

        if ( '0.00' === $discount_rate ) {
            return $this->format_price( $regular_price );
        }

        return $this->calculate_discounted_price(
            $regular_price,
            $discount_rate
        );
    }

    /**
     * Get the authoritative V1 discount rate for a customer.
     *
     * @param CustomerContext $customer Customer context.
     *
     * @return string Decimal fraction.
     */
    public function getDiscountRate(
        CustomerContext $customer
    ): string {
        return $customer->get_discount_rate();
    }

    /**
     * Determine whether a discount rate is a supported V1 rate.
     *
     * @param mixed $rate Discount rate.
     *
     * @return bool
     */
    public function isValidDiscountRate(
        $rate
    ): bool {
        if ( '' === $rate || null === $rate ) {
            return false;
        }

        if ( ! is_numeric( $rate ) ) {
            return false;
        }

        return in_array(
            (string) $rate,
            array(
                '0',
                '0.00',
                self::REGISTERED_DISCOUNT,
                self::WHOLESALE_DISCOUNT,
            ),
            true
        );
    }

    /**
     * Calculate a discounted price from the base Regular Price.
     *
     * This method deliberately accepts the base price and discount rate
     * separately so the calculation cannot accidentally compound discounts.
     *
     * @param string|float $regular_price Base WooCommerce Regular Price.
     * @param string|float $discount_rate Decimal discount rate.
     *
     * @return string
     */
    private function calculate_discounted_price(
        $regular_price,
        $discount_rate
    ): string {
        $regular = (float) $regular_price;
        $rate    = (float) $discount_rate;

        /*
         * Defensive protection against invalid internal values.
         */
        if ( $regular < 0 || $rate < 0 || $rate > 1 ) {
            return $this->format_price( $regular_price );
        }

        /*
         * The calculation is always:
         *
         * base price × (1 - applicable discount)
         *
         * There is no second discount stage.
         */
        $discounted = $regular * ( 1 - $rate );

        return $this->format_price( $discounted );
    }

    /**
     * Format a WooCommerce price using the store's configured precision.
     *
     * @param string|float $price Price.
     *
     * @return string
     */
    private function format_price(
        $price
    ): string {
        return wc_format_decimal(
            $price,
            wc_get_price_decimals()
        );
    }
}