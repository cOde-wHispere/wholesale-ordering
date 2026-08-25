<?php

namespace WholesaleOrdering\Security;

use WholesaleOrdering\Products\ProductFields;
use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;

defined( 'ABSPATH' ) || exit;

/**
 * Protects wholesale pricing from secondary/public exposure paths.
 */
final class PricingLeakageProtection {

    /**
     * Register security hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action(
            'init',
            array( self::class, 'protect_runtime_cache' ),
            1
        );

        add_action(
            'send_headers',
            array( self::class, 'send_private_cache_headers' ),
            1
        );

        add_filter(
            'wp_headers',
            array( self::class, 'filter_cache_headers' ),
            999
        );

        /*
         * WooCommerce REST product and variation responses.
         */
        add_filter(
            'woocommerce_rest_prepare_product_object',
            array( self::class, 'protect_product_rest_response' ),
            999,
            3
        );

        add_filter(
            'woocommerce_rest_prepare_product_variation_object',
            array( self::class, 'protect_product_rest_response' ),
            999,
            3
        );

        /*
         * WooCommerce structured product data.
         */
        add_filter(
            'woocommerce_structured_data_product',
            array( self::class, 'protect_structured_product_data' ),
            999,
            2
        );
    }

    /**
     * Mark authenticated requests as non-cacheable.
     *
     * Public pages are safe to cache because public users receive only
     * regular pricing. Authenticated pages are role-sensitive because
     * pending/rejected/approved/suspended states differ.
     *
     * @return void
     */
    public static function protect_runtime_cache(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        self::define_cache_constant(
            'DONOTCACHEPAGE',
            true
        );

        self::define_cache_constant(
            'DONOTCACHEOBJECT',
            true
        );

        self::define_cache_constant(
            'DONOTCACHEDB',
            true
        );
    }

    /**
     * Send private/no-store headers for authenticated and API requests.
     *
     * @return void
     */
    public static function send_private_cache_headers(): void {
        if (
            is_user_logged_in()
            || wp_doing_ajax()
            || self::is_rest_request()
            || self::is_graphql_request()
        ) {
            header(
                'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
                true
            );

            header(
                'Pragma: no-cache',
                true
            );

            header(
                'Vary: Cookie',
                false
            );
        }
    }

    /**
     * Add cache variation headers.
     *
     * @param array<string,string> $headers Headers.
     *
     * @return array<string,string>
     */
    public static function filter_cache_headers(
        array $headers
    ): array {
        if (
            is_user_logged_in()
            || self::is_rest_request()
            || self::is_graphql_request()
        ) {
            $headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
            $headers['Pragma']        = 'no-cache';

            if ( isset( $headers['Vary'] ) ) {
                if ( false === stripos( $headers['Vary'], 'Cookie' ) ) {
                    $headers['Vary'] .= ', Cookie';
                }
            } else {
                $headers['Vary'] = 'Cookie';
            }
        }

        return $headers;
    }

    /**
     * Protect WooCommerce REST product responses.
     *
     * Raw wholesale metadata is never part of the public REST contract.
     *
     * @param \WP_REST_Response $response Response.
     * @param \WC_Product       $product  Product.
     * @param \WP_REST_Request  $request  Request.
     *
     * @return \WP_REST_Response
     */
    public static function protect_product_rest_response(
        \WP_REST_Response $response,
        \WC_Product $product,
        \WP_REST_Request $request
    ): \WP_REST_Response {
        $data = $response->get_data();

        if ( ! is_array( $data ) ) {
            return $response;
        }

        /*
         * Never expose the internal wholesale fields to customers through
         * WooCommerce REST responses.
         */
        if ( isset( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
            $protected_keys = array(
                ProductFields::META_WHOLESALE_PRICE,
                ProductFields::META_WHOLESALE_MIN_QTY,
                ProductFields::META_WHOLESALE_QTY_STEP,
                ProductFields::META_WHOLESALE_ONLY,
            );

            $data['meta_data'] = array_values(
                array_filter(
                    $data['meta_data'],
                    static function ( $meta ) use ( $protected_keys ): bool {
                        if ( ! is_array( $meta ) || ! isset( $meta['key'] ) ) {
                            return true;
                        }

                        return ! in_array(
                            (string) $meta['key'],
                            $protected_keys,
                            true
                        );
                    }
                )
            );
        }

        /*
         * Do not allow arbitrary wholesale fields added by another component
         * to pass through the public response.
         */
        unset(
            $data['wholesale_price'],
            $data['wholesale_min_qty'],
            $data['wholesale_qty_step'],
            $data['wholesale_only']
        );

        /*
         * The normal WooCommerce price field is already passed through the
         * authoritative pricing integration. Recalculate defensively here
         * so REST remains correct even when another extension changes the
         * response late in the lifecycle.
         */
        $pricing_service = new PricingService();
        $context         = new CustomerContext();

        $eligible_price = $pricing_service->getEligiblePrice(
            $product,
            $context
        );

        if ( array_key_exists( 'price', $data ) ) {
            $data['price'] = wc_format_decimal(
                $eligible_price,
                wc_get_price_decimals()
            );
        }

        $response->set_data(
            $data
        );

        return $response;
    }

    /**
     * Protect structured product data.
     *
     * @param array<string,mixed> $markup  Structured data.
     * @param \WC_Product         $product Product.
     *
     * @return array<string,mixed>
     */
    public static function protect_structured_product_data(
        array $markup,
        \WC_Product $product
    ): array {
        $context = new CustomerContext();

        if ( ! $context->can_use_wholesale_pricing() ) {
            /*
             * Unauthorized users must never receive a wholesale property.
             */
            unset(
                $markup['wholesale_price'],
                $markup['wholesale_min_qty'],
                $markup['wholesale_qty_step'],
                $markup['wholesale_only']
            );

            return $markup;
        }

        $pricing_service = new PricingService();

        $eligible_price = $pricing_service->getEligiblePrice(
            $product,
            $context
        );

        /*
         * Structured data is allowed to expose the price the current
         * authorized customer is actually eligible to see.
         */
        if ( isset( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
            $markup['offers']['price'] = wc_format_decimal(
                $eligible_price,
                wc_get_price_decimals()
            );

            unset(
                $markup['offers']['wholesale_price'],
                $markup['offers']['wholesale_min_qty'],
                $markup['offers']['wholesale_qty_step'],
                $markup['offers']['wholesale_only']
            );
        }

        unset(
            $markup['wholesale_price'],
            $markup['wholesale_min_qty'],
            $markup['wholesale_qty_step'],
            $markup['wholesale_only']
        );

        return $markup;
    }

    /**
     * Determine whether current request is REST.
     *
     * @return bool
     */
    private static function is_rest_request(): bool {
        return defined( 'REST_REQUEST' )
            && REST_REQUEST;
    }

    /**
     * Determine whether current request is GraphQL.
     *
     * @return bool
     */
    private static function is_graphql_request(): bool {
        return defined( 'GRAPHQL_REQUEST' )
            && GRAPHQL_REQUEST;
    }

    /**
     * Define a cache constant when possible.
     *
     * @param string $name  Constant name.
     * @param bool   $value Constant value.
     *
     * @return void
     */
    private static function define_cache_constant(
        string $name,
        bool $value
    ): void {
        if ( ! defined( $name ) ) {
            define(
                $name,
                $value
            );
        }
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}