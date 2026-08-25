<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Central authoritative wholesale pricing service.
 *
 * No caller should independently decide whether a customer receives
 * wholesale pricing.
 */
final class PricingService {

    /**
     * Return the authoritative eligible price.
     *
     * Eligibility:
     *
     * authenticated
     *     +
     * approved wholesale status
     *     +
     * approved wholesale capability
     *     +
     * valid wholesale price
     *
     * Otherwise the regular WooCommerce price is returned.
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
        $regular_price = $product->get_regular_price( 'edit' );

        if ( ! $customer->can_use_wholesale_pricing() ) {
            return (string) $regular_price;
        }

        $wholesale_price = ProductFields::get_wholesale_price(
            $product
        );

        if ( ! $this->is_valid_wholesale_price( $wholesale_price ) ) {
            return (string) $regular_price;
        }

        return wc_format_decimal(
            $wholesale_price,
            wc_get_price_decimals()
        );
    }

    /**
     * Determine whether a wholesale price is valid.
     *
     * Empty is invalid. Zero is technically valid because the product field
     * specification permits non-negative values; accidental empty values
     * therefore never become zero.
     *
     * @param mixed $price Price.
     *
     * @return bool
     */
    public function isValidWholesalePrice(
        $price
    ): bool {
        return $this->is_valid_wholesale_price(
            $price
        );
    }

    /**
     * Determine whether a wholesale price is valid.
     *
     * @param mixed $price Price.
     *
     * @return bool
     */
    private function is_valid_wholesale_price(
        $price
    ): bool {
        if ( '' === $price || null === $price ) {
            return false;
        }

        if ( ! is_numeric( $price ) ) {
            return false;
        }

        return (float) $price >= 0;
    }
}