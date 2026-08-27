<?php

namespace WholesaleOrdering\Checkout;

use WholesaleOrdering\Cart\CartValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Final server-side classic checkout validation boundary.
 *
 * This is the single Wholesale Ordering plugin owner of:
 *
 *     woocommerce_after_checkout_validation
 *
 * CartIntegration owns cart-level validation.
 * CheckoutIntegration owns Store API-specific validation.
 *
 * This class owns the final classic checkout request boundary and additionally
 * verifies the selected shipping and payment methods against current server
 * state.
 */
final class CheckoutValidator {

    /**
     * Cart validator.
     *
     * @var CartValidator
     */
    private CartValidator $cart_validator;

    /**
     * Constructor.
     *
     * @param CartValidator|null $cart_validator Cart validator.
     */
    public function __construct(
        ?CartValidator $cart_validator = null
    ) {
        $this->cart_validator = $cart_validator ?? new CartValidator();
    }

    /**
     * Register the single classic checkout validation boundary.
     *
     * @return void
     */
    public function register(): void {
        add_action(
            'woocommerce_after_checkout_validation',
            array( $this, 'validate_checkout' ),
            999,
            2
        );
    }

    /**
     * Revalidate the entire transaction against current server state.
     *
     * @param array<string,mixed> $data   Checkout data.
     * @param \WP_Error           $errors Checkout errors.
     *
     * @return void
     */
    public function validate_checkout(
        array $data,
        \WP_Error $errors
    ): void {
        /*
         * CartPricing has already applied the current authoritative price
         * during WooCommerce totals calculation.
         *
         * CartValidator now performs the final server-side revalidation of
         * product/account/price/stock/quantity rules.
         */
        foreach ( $this->cart_validator->validate_cart() as $error ) {
            $errors->add(
                $error->get_error_code(),
                $error->get_error_message()
            );
        }

        /*
         * Verify that the selected shipping and payment methods are still
         * available at the moment checkout is submitted.
         */
        $this->validate_shipping_method(
            $data,
            $errors
        );

        $this->validate_payment_method(
            $data,
            $errors
        );
    }

    /**
     * Validate selected shipping methods against current packages.
     *
     * @param array<string,mixed> $data   Checkout data.
     * @param \WP_Error           $errors Checkout errors.
     *
     * @return void
     */
    private function validate_shipping_method(
        array $data,
        \WP_Error $errors
    ): void {
        $chosen = isset( $data['shipping_method'] )
            ? $data['shipping_method']
            : array();

        if ( ! is_array( $chosen ) ) {
            $chosen = array( $chosen );
        }

        $chosen = array_filter(
            array_map(
                static function ( $value ) {
                    return sanitize_text_field(
                        wp_unslash( (string) $value )
                    );
                },
                $chosen
            )
        );

        if ( empty( $chosen ) ) {
            return;
        }

        $available_ids = array();

        if ( WC()->shipping() ) {
            foreach ( WC()->shipping()->get_packages() as $package ) {
                if ( empty( $package['rates'] ) ) {
                    continue;
                }

                foreach ( $package['rates'] as $rate_id => $rate ) {
                    $available_ids[] = (string) $rate_id;
                }
            }
        }

        foreach ( $chosen as $method_id ) {
            if ( ! in_array( (string) $method_id, $available_ids, true ) ) {
                $errors->add(
                    'wholesale_ordering_invalid_shipping_method',
                    __(
                        'The selected shipping method is no longer available.',
                        'wholesale-ordering'
                    )
                );

                break;
            }
        }
    }

    /**
     * Validate selected payment method against current gateways.
     *
     * @param array<string,mixed> $data   Checkout data.
     * @param \WP_Error           $errors Checkout errors.
     *
     * @return void
     */
    private function validate_payment_method(
        array $data,
        \WP_Error $errors
    ): void {
        $payment_method = isset( $data['payment_method'] )
            ? sanitize_text_field(
                wp_unslash( (string) $data['payment_method'] )
            )
            : '';

        if ( '' === $payment_method ) {
            return;
        }

        $gateways = WC()->payment_gateways()
            ? WC()->payment_gateways()->get_available_payment_gateways()
            : array();

        if ( ! isset( $gateways[ $payment_method ] ) ) {
            $errors->add(
                'wholesale_ordering_invalid_payment_method',
                __(
                    'The selected payment method is no longer available.',
                    'wholesale-ordering'
                )
            );
        }
    }
}