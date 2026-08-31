<?php

namespace WholesaleOrdering\Pricing;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Central authoritative V1 pricing service.
 *
 * V1 uses the required two-price model:
 * Regular Price for guests/non-approved customers and Wholesale Price for
 * authenticated, approved wholesale customers when configured.
 *
 * No registered-customer percentage discount, quantity-break pricing, or
 * customer-specific negotiated pricing is calculated here.
 */
final class PricingService {
    public function getEligiblePrice( \WC_Product $product, CustomerContext $customer ): string {
        $regular_price = $product->get_regular_price( 'edit' );
        if ( ! $customer->can_use_wholesale_pricing() ) {
            return (string) $regular_price;
        }
        $wholesale_price = ProductFields::get_wholesale_price( $product );
        if ( ! $this->is_valid_wholesale_price( $wholesale_price ) ) {
            return (string) $regular_price;
        }
        return wc_format_decimal( $wholesale_price, wc_get_price_decimals() );
    }

    public function isValidWholesalePrice( $price ): bool {
        return $this->is_valid_wholesale_price( $price );
    }

    private function is_valid_wholesale_price( $price ): bool {
        if ( '' === $price || null === $price || ! is_numeric( $price ) ) {
            return false;
        }
        return (float) $price >= 0;
    }
}
