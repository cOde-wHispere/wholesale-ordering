<?php

namespace WholesaleOrdering\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and persists wholesale product metadata.
 *
 * Wholesale metadata is intentionally stored as protected WordPress meta.
 * It is not registered for public REST exposure and is never treated as a
 * replacement for WooCommerce's authoritative regular-price fields.
 */
final class ProductFields {

    /**
     * Wholesale price meta key.
     */
    public const META_WHOLESALE_PRICE = '_wholesale_price';

    /**
     * Wholesale minimum quantity meta key.
     */
    public const META_WHOLESALE_MIN_QTY = '_wholesale_min_qty';

    /**
     * Wholesale quantity step meta key.
     */
    public const META_WHOLESALE_QTY_STEP = '_wholesale_qty_step';

    /**
     * Wholesale-only meta key.
     */
    public const META_WHOLESALE_ONLY = '_wholesale_only';

    /**
     * Register product field hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action(
            'woocommerce_product_options_pricing',
            array( self::class, 'render_product_fields' )
        );

        add_action(
            'woocommerce_variation_options_pricing',
            array( self::class, 'render_variation_fields' ),
            10,
            3
        );

        add_action(
            'woocommerce_admin_process_product_object',
            array( self::class, 'save_product_fields' )
        );

        add_action(
            'woocommerce_admin_process_variation_object',
            array( self::class, 'save_variation_fields' )
        );
    }

    /**
     * Render simple-product wholesale fields.
     *
     * @return void
     */
    public static function render_product_fields(): void {
        global $post;

        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            return;
        }

        echo '<div class="options_group wholesale-ordering-product-fields">';

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_PRICE,
                'label'             => __( 'Wholesale price', 'wholesale-ordering' ),
                'description'       => __( 'Price available to approved wholesale customers.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'text',
                'data_type'         => 'price',
                'value'             => self::get_meta_value(
                    (int) $post->ID,
                    self::META_WHOLESALE_PRICE
                ),
                'custom_attributes' => array(
                    'min'  => '0',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_MIN_QTY,
                'label'             => __( 'Wholesale minimum quantity', 'wholesale-ordering' ),
                'description'       => __( 'Minimum quantity for wholesale ordering. Defaults to 1.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'value'             => self::get_meta_value(
                    (int) $post->ID,
                    self::META_WHOLESALE_MIN_QTY,
                    '1'
                ),
                'custom_attributes' => array(
                    'min'  => '1',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_QTY_STEP,
                'label'             => __( 'Wholesale quantity step', 'wholesale-ordering' ),
                'description'       => __( 'Optional case/pack quantity increment.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'value'             => self::get_meta_value(
                    (int) $post->ID,
                    self::META_WHOLESALE_QTY_STEP
                ),
                'custom_attributes' => array(
                    'min'  => '0',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id'          => self::META_WHOLESALE_ONLY,
                'label'       => __( 'Wholesale only', 'wholesale-ordering' ),
                'description' => __( 'Stored for wholesale-only catalogue behaviour. Enforcement is a separate policy decision.', 'wholesale-ordering' ),
                'desc_tip'    => true,
                'value'       => self::get_meta_value(
                    (int) $post->ID,
                    self::META_WHOLESALE_ONLY,
                    'no'
                ),
            )
        );

        echo '</div>';
    }

    /**
     * Render variation wholesale fields.
     *
     * @param int              $loop           Variation loop index.
     * @param array<string,mixed> $variation_data Variation data.
     * @param \WC_Product_Variation $variation Variation object.
     *
     * @return void
     */
    public static function render_variation_fields(
        int $loop,
        array $variation_data,
        \WC_Product_Variation $variation
    ): void {
        $variation_id = $variation->get_id();

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_PRICE . '[' . $loop . ']',
                'name'              => self::META_WHOLESALE_PRICE . '[' . $loop . ']',
                'label'             => __( 'Wholesale price', 'wholesale-ordering' ),
                'description'       => __( 'Wholesale price for this variation.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'text',
                'data_type'         => 'price',
                'value'             => self::get_meta_value(
                    $variation_id,
                    self::META_WHOLESALE_PRICE
                ),
                'wrapper_class'     => 'form-row form-row-first',
                'custom_attributes' => array(
                    'min'  => '0',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_MIN_QTY . '[' . $loop . ']',
                'name'              => self::META_WHOLESALE_MIN_QTY . '[' . $loop . ']',
                'label'             => __( 'Wholesale minimum quantity', 'wholesale-ordering' ),
                'description'       => __( 'Minimum wholesale quantity for this variation.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'value'             => self::get_meta_value(
                    $variation_id,
                    self::META_WHOLESALE_MIN_QTY,
                    '1'
                ),
                'wrapper_class'     => 'form-row form-row-first',
                'custom_attributes' => array(
                    'min'  => '1',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => self::META_WHOLESALE_QTY_STEP . '[' . $loop . ']',
                'name'              => self::META_WHOLESALE_QTY_STEP . '[' . $loop . ']',
                'label'             => __( 'Wholesale quantity step', 'wholesale-ordering' ),
                'description'       => __( 'Optional case/pack increment for this variation.', 'wholesale-ordering' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'value'             => self::get_meta_value(
                    $variation_id,
                    self::META_WHOLESALE_QTY_STEP
                ),
                'wrapper_class'     => 'form-row form-row-last',
                'custom_attributes' => array(
                    'min'  => '0',
                    'step' => 'any',
                ),
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id'            => self::META_WHOLESALE_ONLY . '[' . $loop . ']',
                'name'          => self::META_WHOLESALE_ONLY . '[' . $loop . ']',
                'label'         => __( 'Wholesale only', 'wholesale-ordering' ),
                'description'   => __( 'Stored for future wholesale-only behaviour.', 'wholesale-ordering' ),
                'desc_tip'      => true,
                'value'         => self::get_meta_value(
                    $variation_id,
                    self::META_WHOLESALE_ONLY,
                    'no'
                ),
                'wrapper_class' => 'form-row form-row-full',
            )
        );
    }

    /**
     * Save simple-product wholesale fields.
     *
     * @param \WC_Product $product Product object.
     *
     * @return void
     */
    public static function save_product_fields(
        \WC_Product $product
    ): void {
        if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
            return;
        }

        self::save_meta_from_post(
            $product,
            self::META_WHOLESALE_PRICE,
            'price'
        );

        self::save_meta_from_post(
            $product,
            self::META_WHOLESALE_MIN_QTY,
            'minimum_quantity'
        );

        self::save_meta_from_post(
            $product,
            self::META_WHOLESALE_QTY_STEP,
            'quantity_step'
        );

        self::save_boolean_meta_from_post(
            $product,
            self::META_WHOLESALE_ONLY
        );
    }

    /**
     * Save variation wholesale fields.
     *
     * @param \WC_Product_Variation $variation Variation object.
     *
     * @return void
     */
    public static function save_variation_fields(
        \WC_Product_Variation $variation
    ): void {
        if ( ! current_user_can( 'edit_post', $variation->get_id() ) ) {
            return;
        }

        self::save_variation_meta_from_post(
            $variation,
            self::META_WHOLESALE_PRICE,
            'price'
        );

        self::save_variation_meta_from_post(
            $variation,
            self::META_WHOLESALE_MIN_QTY,
            'minimum_quantity'
        );

        self::save_variation_meta_from_post(
            $variation,
            self::META_WHOLESALE_QTY_STEP,
            'quantity_step'
        );

        self::save_variation_boolean_meta_from_post(
            $variation,
            self::META_WHOLESALE_ONLY
        );
    }

    /**
     * Get wholesale price.
     *
     * @param \WC_Product $product Product.
     *
     * @return string Empty string when not configured.
     */
    public static function get_wholesale_price(
        \WC_Product $product
    ): string {
        return self::get_meta_value(
            $product->get_id(),
            self::META_WHOLESALE_PRICE
        );
    }

    /**
     * Get wholesale minimum quantity.
     *
     * @param \WC_Product $product Product.
     *
     * @return float
     */
    public static function get_wholesale_min_qty(
        \WC_Product $product
    ): float {
        $value = self::get_meta_value(
            $product->get_id(),
            self::META_WHOLESALE_MIN_QTY,
            '1'
        );

        return max(
            1.0,
            (float) $value
        );
    }

    /**
     * Get wholesale quantity step.
     *
     * @param \WC_Product $product Product.
     *
     * @return float
     */
    public static function get_wholesale_qty_step(
        \WC_Product $product
    ): float {
        $value = self::get_meta_value(
            $product->get_id(),
            self::META_WHOLESALE_QTY_STEP,
            ''
        );

        if ( '' === $value ) {
            return 0.0;
        }

        return max(
            0.0,
            (float) $value
        );
    }

    /**
     * Determine whether wholesale-only flag is enabled.
     *
     * This is storage/read support only in V1. The specification identifies
     * wholesale-only catalogue restrictions as optional/future behaviour.
     *
     * @param \WC_Product $product Product.
     *
     * @return bool
     */
    public static function is_wholesale_only(
        \WC_Product $product
    ): bool {
        return 'yes' === self::get_meta_value(
            $product->get_id(),
            self::META_WHOLESALE_ONLY,
            'no'
        );
    }

    /**
     * Get raw protected metadata.
     *
     * @param int    $product_id Product ID.
     * @param string $meta_key   Meta key.
     * @param string $default    Default.
     *
     * @return string
     */
    private static function get_meta_value(
        int $product_id,
        string $meta_key,
        string $default = ''
    ): string {
        $value = get_post_meta(
            $product_id,
            $meta_key,
            true
        );

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return (string) $value;
    }

    /**
     * Save scalar product metadata from POST.
     *
     * @param \WC_Product $product Product.
     * @param string      $meta_key Meta key.
     * @param string      $type Data type.
     *
     * @return void
     */
    private static function save_meta_from_post(
        \WC_Product $product,
        string $meta_key,
        string $type
    ): void {
        if ( ! isset( $_POST[ $meta_key ] ) ) {
            return;
        }

        $raw = wp_unslash(
            $_POST[ $meta_key ]
        );

        if ( is_array( $raw ) ) {
            return;
        }

        $value = self::sanitize_value(
            (string) $raw,
            $type
        );

        if ( '' === $value ) {
            delete_post_meta(
                $product->get_id(),
                $meta_key
            );

            return;
        }

        update_post_meta(
            $product->get_id(),
            $meta_key,
            $value
        );
    }

    /**
     * Save boolean product metadata.
     *
     * @param \WC_Product $product Product.
     * @param string      $meta_key Meta key.
     *
     * @return void
     */
    private static function save_boolean_meta_from_post(
        \WC_Product $product,
        string $meta_key
    ): void {
        if ( ! array_key_exists( $meta_key, $_POST ) ) {
            return;
        }

        $value = isset( $_POST[ $meta_key ] )
            ? 'yes'
            : 'no';

        update_post_meta(
            $product->get_id(),
            $meta_key,
            $value
        );
    }

    /**
     * Save variation scalar metadata.
     *
     * @param \WC_Product_Variation $variation Variation.
     * @param string                $meta_key Meta key.
     * @param string                $type Data type.
     *
     * @return void
     */
    private static function save_variation_meta_from_post(
        \WC_Product_Variation $variation,
        string $meta_key,
        string $type
    ): void {
        if ( ! isset( $_POST[ $meta_key ] ) || ! is_array( $_POST[ $meta_key ] ) ) {
            return;
        }

        $variation_id = $variation->get_id();

        /*
         * Find the variation's position in the submitted variation list.
         * WooCommerce's variation object save hook does not provide the loop
         * index, so locate the matching hidden variation ID.
         */
        if (
            ! isset( $_POST['variable_post_id'] )
            || ! is_array( $_POST['variable_post_id'] )
        ) {
            return;
        }

        $ids = array_map(
            'absint',
            wp_unslash( $_POST['variable_post_id'] )
        );

        $index = array_search(
            $variation_id,
            $ids,
            true
        );

        if ( false === $index || ! array_key_exists( $index, $_POST[ $meta_key ] ) ) {
            return;
        }

        $raw = wp_unslash(
            $_POST[ $meta_key ][ $index ]
        );

        if ( is_array( $raw ) ) {
            return;
        }

        $value = self::sanitize_value(
            (string) $raw,
            $type
        );

        if ( '' === $value ) {
            delete_post_meta(
                $variation_id,
                $meta_key
            );

            return;
        }

        update_post_meta(
            $variation_id,
            $meta_key,
            $value
        );
    }

    /**
     * Save variation boolean metadata.
     *
     * @param \WC_Product_Variation $variation Variation.
     * @param string                $meta_key Meta key.
     *
     * @return void
     */
    private static function save_variation_boolean_meta_from_post(
        \WC_Product_Variation $variation,
        string $meta_key
    ): void {
        if ( ! isset( $_POST[ $meta_key ] ) || ! is_array( $_POST[ $meta_key ] ) ) {
            return;
        }

        if (
            ! isset( $_POST['variable_post_id'] )
            || ! is_array( $_POST['variable_post_id'] )
        ) {
            return;
        }

        $ids = array_map(
            'absint',
            wp_unslash( $_POST['variable_post_id'] )
        );

        $index = array_search(
            $variation->get_id(),
            $ids,
            true
        );

        if ( false === $index ) {
            return;
        }

        $value = isset(
            $_POST[ $meta_key ][ $index ]
        )
            ? 'yes'
            : 'no';

        update_post_meta(
            $variation->get_id(),
            $meta_key,
            $value
        );
    }

    /**
     * Sanitize product metadata.
     *
     * @param string $value Value.
     * @param string $type Type.
     *
     * @return string
     */
    private static function sanitize_value(
        string $value,
        string $type
    ): string {
        $value = trim( $value );

        if ( '' === $value ) {
            return '';
        }

        if ( 'price' === $type ) {
            $value = wc_format_decimal(
                $value,
                wc_get_price_decimals()
            );

            return (float) $value < 0
                ? ''
                : $value;
        }

        if ( 'minimum_quantity' === $type ) {
            $value = wc_format_decimal(
                $value,
                6
            );

            return (float) $value < 1
                ? '1'
                : $value;
        }

        if ( 'quantity_step' === $type ) {
            $value = wc_format_decimal(
                $value,
                6
            );

            return (float) $value <= 0
                ? ''
                : $value;
        }

        return sanitize_text_field( $value );
    }

    /**
     * Private constructor.
     */
    private function __construct() {}
}