<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Secure admin CSV importer for WooCommerce simple products.
 *
 * This replaces the development-only WP-CLI seed command with a browser-based
 * import that can be used on LocalWP, staging, and InstaWP without shell access.
 */
final class ProductCsvImporter {

    private const PAGE_SLUG = 'wholesale-ordering-product-import';
    private const ACTION = 'wholesale_ordering_import_products';
    private const NONCE = 'wholesale_ordering_product_import';
    private const MAX_FILE_SIZE = 5242880; // 5 MB.

    /**
     * Register admin page and upload handler.
     *
     * @return void
     */
    public static function register(): void {
        if ( ! is_admin() ) {
            return;
        }

        add_action(
            'admin_menu',
            array( self::class, 'register_menu' ),
            10
        );

        add_action(
            'admin_post_' . self::ACTION,
            array( self::class, 'handle_upload' )
        );
    }

    /**
     * Register the CSV importer under Wholesale Ordering.
     *
     * @return void
     */
    public static function register_menu(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        add_submenu_page(
            'wholesale-ordering',
            __( 'Import Products CSV', 'wholesale-ordering' ),
            __( 'Import Products', 'wholesale-ordering' ),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array( self::class, 'render_page' )
        );
    }

    /**
     * Render the upload page.
     *
     * @return void
     */
    public static function render_page(): void {
        self::assert_capability();

        $notice = isset( $_GET['wo_import_notice'] )
            ? sanitize_key( wp_unslash( $_GET['wo_import_notice'] ) )
            : '';
        $message = isset( $_GET['wo_import_message'] )
            ? sanitize_text_field( wp_unslash( $_GET['wo_import_message'] ) )
            : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Import Products from CSV', 'wholesale-ordering' ); ?></h1>

            <?php if ( '' !== $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( in_array( $notice, array( 'success', 'warning' ), true ) ? $notice : 'error' ); ?> is-dismissible">
                    <p><?php echo esc_html( $message ); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <?php
                echo esc_html__(
                    'Upload a validated Wholesale Ordering product CSV. The importer creates or updates WooCommerce simple products and stores the V1 Regular Price + Wholesale Price fields.',
                    'wholesale-ordering'
                );
                ?>
            </p>

            <p>
                <strong><?php echo esc_html__( 'Important:', 'wholesale-ordering' ); ?></strong>
                <?php
                echo esc_html__(
                    'The CSV is imported on this WordPress installation. It does not require WP-CLI or direct database access.',
                    'wholesale-ordering'
                );
                ?>
            </p>

            <form
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                enctype="multipart/form-data"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                <?php wp_nonce_field( self::NONCE ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wholesale-ordering-products-csv">
                                <?php echo esc_html__( 'Product CSV', 'wholesale-ordering' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="file"
                                id="wholesale-ordering-products-csv"
                                name="products_csv"
                                accept=".csv,text/csv"
                                required
                            >
                            <p class="description">
                                <?php
                                echo esc_html__(
                                    'Maximum size: 5 MB. All rows are validated before any product is written.',
                                    'wholesale-ordering'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Validate and Import Products', 'wholesale-ordering' ) ); ?>
            </form>

            <h2><?php echo esc_html__( 'Required CSV columns', 'wholesale-ordering' ); ?></h2>
            <p><code>seed_id, sku, name, description, regular_price, wholesale_price, wholesale_minimum_quantity, quantity_step, brand, category, stock_quantity, manage_stock, low_stock_threshold, weight, length, width, height, tax_status, tax_class, status, catalog_visibility, image_url</code></p>
        </div>
        <?php
    }

    /**
     * Process uploaded CSV.
     *
     * @return void
     */
    public static function handle_upload(): void {
        self::assert_capability();

        check_admin_referer( self::NONCE );

        if ( ! class_exists( '\WooCommerce' ) ) {
            self::redirect_notice( 'error', 'WooCommerce must be active before products can be imported.' );
        }

        if ( empty( $_FILES['products_csv'] ) || ! is_array( $_FILES['products_csv'] ) ) {
            self::redirect_notice( 'error', 'Please select a CSV file.' );
        }

        $file = $_FILES['products_csv'];
        $error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        $size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
        $tmp   = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        $name  = isset( $file['name'] ) ? (string) $file['name'] : '';

        if ( UPLOAD_ERR_OK !== $error ) {
            self::redirect_notice( 'error', self::upload_error_message( $error ) );
        }

        if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
            self::redirect_notice( 'error', 'The uploaded file could not be verified.' );
        }

        if ( $size <= 0 || $size > self::MAX_FILE_SIZE ) {
            self::redirect_notice( 'error', 'The CSV file must be larger than 0 bytes and no larger than 5 MB.' );
        }

        $extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( 'csv' !== $extension ) {
            self::redirect_notice( 'error', 'Only .csv files are accepted.' );
        }

        $rows = self::read_csv( $tmp );
        if ( is_wp_error( $rows ) ) {
            self::redirect_notice( 'error', $rows->get_error_message() );
        }

        if ( empty( $rows ) ) {
            self::redirect_notice( 'error', 'The CSV contains no product rows.' );
        }

        $errors = array();
        foreach ( $rows as $index => $row ) {
            $row_errors = self::validate_row( $row, $index + 2 );
            if ( ! empty( $row_errors ) ) {
                $errors = array_merge( $errors, $row_errors );
            }
        }

        if ( ! empty( $errors ) ) {
            self::redirect_notice(
                'error',
                sprintf(
                    'CSV validation failed: %s',
                    implode( ' | ', array_slice( $errors, 0, 5 ) )
                    . ( count( $errors ) > 5 ? ' | Additional errors were found.' : '' )
                )
            );
        }

        $created = 0;
        $updated = 0;
        $failed  = 0;
        $failure_messages = array();

        foreach ( $rows as $index => $row ) {
            try {
                $result = self::upsert_product( $row );
                if ( 'created' === $result ) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch ( \Throwable $exception ) {
                $failed++;
                $failure_messages[] = sprintf(
                    'CSV line %d (%s): %s',
                    $index + 2,
                    $row['sku'],
                    $exception->getMessage()
                );
            }
        }

        if ( $failed > 0 ) {
            self::redirect_notice(
                'warning',
                sprintf(
                    'Import finished with %d created, %d updated, and %d failed. %s',
                    $created,
                    $updated,
                    $failed,
                    implode( ' | ', array_slice( $failure_messages, 0, 3 ) )
                )
            );
        }

        self::redirect_notice(
            'success',
            sprintf(
                'Import completed successfully: %d created, %d updated, 0 failed.',
                $created,
                $updated
            )
        );
    }

    /**
     * Read CSV into normalized rows.
     *
     * @param string $file Uploaded temporary file.
     * @return array<int,array<string,string>>|\WP_Error
     */
    private static function read_csv( string $file ) {
        $handle = fopen( $file, 'rb' );
        if ( false === $handle ) {
            return new \WP_Error( 'csv_open_failed', 'Unable to open the uploaded CSV.' );
        }

        $headers = fgetcsv( $handle );
        if ( false === $headers || empty( $headers ) ) {
            fclose( $handle );
            return new \WP_Error( 'csv_header_missing', 'The CSV header row is missing.' );
        }

        $headers = array_map(
            static function ( $header ): string {
                return trim(
                    preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        (string) $header
                    )
                );
            },
            $headers
        );

        $required = self::required_headers();
        $missing  = array_diff( $required, $headers );
        if ( ! empty( $missing ) ) {
            fclose( $handle );
            return new \WP_Error(
                'csv_headers_missing',
                'CSV is missing required column(s): ' . implode( ', ', $missing )
            );
        }

        $rows = array();
        while ( false !== ( $data = fgetcsv( $handle ) ) ) {
            if ( 1 === count( $data ) && '' === trim( (string) $data[0] ) ) {
                continue;
            }

            $row = array();
            foreach ( $headers as $index => $header ) {
                $row[ $header ] = isset( $data[ $index ] )
                    ? trim( (string) $data[ $index ] )
                    : '';
            }

            $rows[] = $row;
        }

        fclose( $handle );
        return $rows;
    }

    /**
     * Required columns for the importer.
     *
     * @return array<int,string>
     */
    private static function required_headers(): array {
        return array(
            'seed_id',
            'sku',
            'name',
            'description',
            'regular_price',
            'wholesale_price',
            'wholesale_minimum_quantity',
            'quantity_step',
            'brand',
            'category',
            'stock_quantity',
            'manage_stock',
            'low_stock_threshold',
            'weight',
            'length',
            'width',
            'height',
            'tax_status',
            'tax_class',
            'status',
            'catalog_visibility',
            'image_url',
        );
    }

    /**
     * Validate one row before import.
     *
     * @param array<string,string> $row CSV row.
     * @param int $line CSV line number.
     * @return array<int,string>
     */
    private static function validate_row( array $row, int $line ): array {
        $errors = array();

        foreach ( self::required_headers() as $field ) {
            if ( ! array_key_exists( $field, $row ) ) {
                $errors[] = sprintf( 'Line %d: missing column "%s".', $line, $field );
            }
        }

        if ( ! empty( $errors ) ) {
            return $errors;
        }

        foreach ( array( 'seed_id', 'sku', 'name', 'brand', 'category' ) as $field ) {
            if ( '' === trim( $row[ $field ] ) ) {
                $errors[] = sprintf( 'Line %d: %s cannot be empty.', $line, $field );
            }
        }

        foreach ( array( 'regular_price', 'wholesale_price', 'wholesale_minimum_quantity', 'quantity_step' ) as $field ) {
            if ( ! is_numeric( $row[ $field ] ) ) {
                $errors[] = sprintf( 'Line %d: %s must be numeric.', $line, $field );
            }
        }

        if ( is_numeric( $row['regular_price'] ) && (float) $row['regular_price'] < 0 ) {
            $errors[] = sprintf( 'Line %d: regular_price cannot be negative.', $line );
        }

        if ( is_numeric( $row['wholesale_price'] ) && (float) $row['wholesale_price'] < 0 ) {
            $errors[] = sprintf( 'Line %d: wholesale_price cannot be negative.', $line );
        }

        if (
            is_numeric( $row['regular_price'] )
            && is_numeric( $row['wholesale_price'] )
            && (float) $row['wholesale_price'] > (float) $row['regular_price']
        ) {
            $errors[] = sprintf( 'Line %d: wholesale_price cannot exceed regular_price.', $line );
        }

        if ( is_numeric( $row['wholesale_minimum_quantity'] ) && (float) $row['wholesale_minimum_quantity'] < 1 ) {
            $errors[] = sprintf( 'Line %d: wholesale_minimum_quantity must be at least 1.', $line );
        }

        if ( is_numeric( $row['quantity_step'] ) && (float) $row['quantity_step'] <= 0 ) {
            $errors[] = sprintf( 'Line %d: quantity_step must be greater than 0.', $line );
        }

        foreach ( array( 'stock_quantity', 'low_stock_threshold', 'weight', 'length', 'width', 'height' ) as $field ) {
            if ( '' !== $row[ $field ] && ! is_numeric( $row[ $field ] ) ) {
                $errors[] = sprintf( 'Line %d: %s must be numeric when supplied.', $line, $field );
            }
        }

        if ( ! in_array( strtolower( $row['manage_stock'] ), array( '0', '1', 'yes', 'no', 'true', 'false' ), true ) ) {
            $errors[] = sprintf( 'Line %d: manage_stock must be 0/1, yes/no, or true/false.', $line );
        }

        if ( ! in_array( $row['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
            $errors[] = sprintf( 'Line %d: unsupported product status "%s".', $line, $row['status'] );
        }

        if ( ! in_array( $row['catalog_visibility'], array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
            $errors[] = sprintf( 'Line %d: unsupported catalog_visibility "%s".', $line, $row['catalog_visibility'] );
        }

        if ( '' !== $row['image_url'] && ! filter_var( $row['image_url'], FILTER_VALIDATE_URL ) ) {
            $errors[] = sprintf( 'Line %d: image_url must be a valid URL when supplied.', $line );
        }

        return $errors;
    }

    /**
     * Create or update one WooCommerce simple product.
     *
     * @param array<string,string> $row CSV row.
     * @return string created|updated
     */
    private static function upsert_product( array $row ): string {
        $sku     = sanitize_text_field( $row['sku'] );
        $seed_id = sanitize_text_field( $row['seed_id'] );

        $existing_id = wc_get_product_id_by_sku( $sku );

        if ( ! $existing_id ) {
            $existing_id = self::find_by_seed_id( $seed_id );
        }

        $product = $existing_id
            ? wc_get_product( $existing_id )
            : new \WC_Product_Simple();

        if ( ! $product instanceof \WC_Product_Simple ) {
            throw new \RuntimeException( 'Existing product is not a simple WooCommerce product.' );
        }

        $product->set_name( sanitize_text_field( $row['name'] ) );
        $product->set_description( wp_kses_post( $row['description'] ) );
        $product->set_status( $row['status'] );
        $product->set_catalog_visibility( $row['catalog_visibility'] );
        $product->set_sku( $sku );
        $product->set_regular_price( self::format_price( $row['regular_price'] ) );
        $product->set_price( self::format_price( $row['regular_price'] ) );
        $product->set_tax_status( in_array( $row['tax_status'], array( 'taxable', 'shipping', 'none' ), true ) ? $row['tax_status'] : 'taxable' );
        $product->set_tax_class( sanitize_title( $row['tax_class'] ) );

        $manage_stock = in_array( strtolower( $row['manage_stock'] ), array( '1', 'yes', 'true' ), true );
        $product->set_manage_stock( $manage_stock );
        if ( '' !== $row['stock_quantity'] ) {
            $product->set_stock_quantity( (float) $row['stock_quantity'] );
        }
        if ( '' !== $row['low_stock_threshold'] ) {
            $product->set_low_stock_amount( (float) $row['low_stock_threshold'] );
        }

        if ( '' !== $row['weight'] ) {
            $product->set_weight( (string) $row['weight'] );
        }
        if ( '' !== $row['length'] ) {
            $product->set_length( (string) $row['length'] );
        }
        if ( '' !== $row['width'] ) {
            $product->set_width( (string) $row['width'] );
        }
        if ( '' !== $row['height'] ) {
            $product->set_height( (string) $row['height'] );
        }

        $product->update_meta_data(
            ProductFields::META_WHOLESALE_PRICE,
            self::format_price( $row['wholesale_price'] )
        );
        $product->update_meta_data(
            ProductFields::META_WHOLESALE_MIN_QTY,
            self::format_quantity( $row['wholesale_minimum_quantity'] )
        );
        $product->update_meta_data(
            ProductFields::META_WHOLESALE_QTY_STEP,
            self::format_quantity( $row['quantity_step'] )
        );
        $product->update_meta_data( ProductFields::META_WHOLESALE_ONLY, 'no' );
        $product->update_meta_data( '_wholesale_ordering_seed', '1' );
        $product->update_meta_data( '_wholesale_ordering_seed_id', $seed_id );

        $product_id = $product->save();
        if ( ! $product_id ) {
            throw new \RuntimeException( 'WooCommerce failed to save the product.' );
        }

        self::assign_term( $product_id, $row['category'], 'product_cat' );

        if ( taxonomy_exists( 'product_brand' ) && '' !== trim( $row['brand'] ) ) {
            self::assign_term( $product_id, $row['brand'], 'product_brand' );
        }

        if ( '' !== $row['image_url'] ) {
            self::maybe_import_image( $product_id, $row['image_url'] );
        }

        return $existing_id ? 'updated' : 'created';
    }

    /**
     * Find an existing imported product by deterministic seed ID.
     *
     * @param string $seed_id Seed ID.
     * @return int
     */
    private static function find_by_seed_id( string $seed_id ): int {
        $posts = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => '_wholesale_ordering_seed_id',
                'meta_value'     => $seed_id,
            )
        );

        return empty( $posts ) ? 0 : absint( $posts[0] );
    }

    /**
     * Assign or create a taxonomy term.
     *
     * @param int    $product_id Product ID.
     * @param string $name       Term name.
     * @param string $taxonomy   Taxonomy.
     * @return void
     */
    private static function assign_term( int $product_id, string $name, string $taxonomy ): void {
        $name = sanitize_text_field( $name );
        if ( '' === $name ) {
            return;
        }

        $term = term_exists( $name, $taxonomy );
        if ( ! $term ) {
            $term = wp_insert_term( $name, $taxonomy );
        }

        if ( is_wp_error( $term ) ) {
            throw new \RuntimeException( $term->get_error_message() );
        }

        $term_id = is_array( $term ) ? absint( $term['term_id'] ) : absint( $term );
        if ( $term_id > 0 ) {
            wp_set_object_terms( $product_id, array( $term_id ), $taxonomy, false );
        }
    }

    /**
     * Import a remote image only when the product does not already have one.
     *
     * @param int    $product_id Product ID.
     * @param string $url         Remote image URL.
     * @return void
     */
    private static function maybe_import_image( int $product_id, string $url ): void {
        $product = wc_get_product( $product_id );
        if ( ! $product || $product->get_image_id() ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( esc_url_raw( $url ), $product_id, null, 'id' );
        if ( is_wp_error( $attachment_id ) ) {
            throw new \RuntimeException( 'Image import failed: ' . $attachment_id->get_error_message() );
        }

        $product->set_image_id( absint( $attachment_id ) );
        $product->save();
    }

    private static function format_price( $price ): string {
        return wc_format_decimal( $price, wc_get_price_decimals() );
    }

    private static function format_quantity( $quantity ): string {
        return wc_format_decimal( $quantity, 6 );
    }

    private static function assert_capability(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to import products.', 'wholesale-ordering' ),
                esc_html__( 'Access denied', 'wholesale-ordering' ),
                array( 'response' => 403 )
            );
        }
    }

    private static function redirect_notice( string $type, string $message ): never {
        $url = add_query_arg(
            array(
                'page'               => self::PAGE_SLUG,
                'wo_import_notice'   => $type,
                'wo_import_message'  => wp_strip_all_tags( $message ),
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    private static function upload_error_message( int $error ): string {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'The CSV upload was incomplete.',
            UPLOAD_ERR_NO_FILE    => 'No CSV file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded CSV.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the CSV upload.',
        );

        return $messages[ $error ] ?? 'The CSV upload failed.';
    }
}
