<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 5 product-management service.
 *
 * WooCommerce remains authoritative for product data and regular price.
 * Wholesale-specific fields remain owned by ProductFields.
 */
final class ProductManagementService {

	/**
	 * Create a product.
	 *
	 * @param array<string,mixed> $data Product data.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	public function create( array $data ) {
		$product = new \WC_Product_Simple();

		return $this->save_product(
			$product,
			$data
		);
	}

	/**
	 * Get a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	public function get( int $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error(
				'product_not_found',
				__( 'Product not found.', 'wholesale-ordering' )
			);
		}

		return $product;
	}

	/**
	 * Update a product.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string,mixed> $data       Product data.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	public function update(
		int $product_id,
		array $data
	) {
		$product = $this->get( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		return $this->save_product(
			$product,
			$data
		);
	}

	/**
	 * Duplicate a product using WooCommerce's duplication mechanism.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	public function duplicate( int $product_id ) {
		$product = $this->get( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$duplicate = clone $product;

		$duplicate->set_id( 0 );
		$duplicate->set_name(
			$product->get_name() . ' ' . __( '(Copy)', 'wholesale-ordering' )
		);
		$duplicate->set_status( 'draft' );

		$duplicate->save();

		$wholesale_price = ProductFields::get_wholesale_price(
			$product
		);

		if ( '' !== (string) $wholesale_price ) {
			update_post_meta(
				$duplicate->get_id(),
				ProductFields::META_WHOLESALE_PRICE,
				wc_format_decimal(
					$wholesale_price,
					wc_get_price_decimals()
				)
			);
		}

		return wc_get_product( $duplicate->get_id() );
	}

	/**
	 * Publish a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return true|\WP_Error
	 */
	public function publish( int $product_id ) {
		return $this->set_status(
			$product_id,
			'publish'
		);
	}

	/**
	 * Unpublish a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return true|\WP_Error
	 */
	public function unpublish( int $product_id ) {
		return $this->set_status(
			$product_id,
			'draft'
		);
	}

	/**
	 * Archive a product without deleting historical order references.
	 *
	 * The previous status is stored so restore can return the product to
	 * its previous WooCommerce status.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return true|\WP_Error
	 */
	public function archive( int $product_id ) {
		$product = $this->get( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		update_post_meta(
			$product_id,
			'_wholesale_ordering_previous_product_status',
			$product->get_status()
		);

		update_post_meta(
			$product_id,
			'_wholesale_ordering_archived',
			'yes'
		);

		$product->set_status( 'private' );
		$product->save();

		return true;
	}

	/**
	 * Restore an archived product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return true|\WP_Error
	 */
	public function restore( int $product_id ) {
		$product = $this->get( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$status = get_post_meta(
			$product_id,
			'_wholesale_ordering_previous_product_status',
			true
		);

		$status = in_array(
			$status,
			array(
				'publish',
				'draft',
				'pending',
				'private',
			),
			true
		)
			? $status
			: 'draft';

		$product->set_status( $status );
		$product->save();

		delete_post_meta(
			$product_id,
			'_wholesale_ordering_archived'
		);

		delete_post_meta(
			$product_id,
			'_wholesale_ordering_previous_product_status'
		);

		return true;
	}

	/**
	 * Save supported WooCommerce product fields.
	 *
	 * @param \WC_Product      $product Product.
	 * @param array<string,mixed> $data Product data.
	 *
	 * @return \WC_Product|\WP_Error
	 */
	private function save_product(
		\WC_Product $product,
		array $data
	) {
		$this->apply_product_data(
			$product,
			$data
		);

		try {
			$product->save();
		} catch ( \Throwable $exception ) {
			return new \WP_Error(
				'product_save_failed',
				__( 'The product could not be saved.', 'wholesale-ordering' )
			);
		}

		$this->save_wholesale_fields(
			$product,
			$data
		);

		return $product;
	}

	/**
	 * Apply WooCommerce-owned product fields.
	 *
	 * @param \WC_Product       $product Product.
	 * @param array<string,mixed> $data Product data.
	 *
	 * @return void
	 */
	private function apply_product_data(
		\WC_Product $product,
		array $data
	): void {
		if ( array_key_exists( 'name', $data ) ) {
			$product->set_name(
				sanitize_text_field( (string) $data['name'] )
			);
		}

		if ( array_key_exists( 'description', $data ) ) {
			$product->set_description(
				wp_kses_post( (string) $data['description'] )
			);
		}

		if ( array_key_exists( 'short_description', $data ) ) {
			$product->set_short_description(
				wp_kses_post( (string) $data['short_description'] )
			);
		}

		if ( array_key_exists( 'sku', $data ) ) {
			$product->set_sku(
				sanitize_text_field( (string) $data['sku'] )
			);
		}

		if ( array_key_exists( 'regular_price', $data ) ) {
			$product->set_regular_price(
				wc_format_decimal(
					$data['regular_price'],
					wc_get_price_decimals()
				)
			);
		}

		if ( array_key_exists( 'tax_class', $data ) ) {
			$product->set_tax_class(
				sanitize_text_field( (string) $data['tax_class'] )
			);
		}

		if ( array_key_exists( 'stock_quantity', $data ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity(
				wc_stock_amount( $data['stock_quantity'] )
			);
		}

		if ( array_key_exists( 'stock_status', $data ) ) {
			$product->set_stock_status(
				sanitize_key( (string) $data['stock_status'] )
			);
		}

		if ( array_key_exists( 'weight', $data ) ) {
			$product->set_weight(
				wc_format_decimal( $data['weight'] )
			);
		}

		if ( array_key_exists( 'length', $data ) ) {
			$product->set_length(
				wc_format_decimal( $data['length'] )
			);
		}

		if ( array_key_exists( 'width', $data ) ) {
			$product->set_width(
				wc_format_decimal( $data['width'] )
			);
		}

		if ( array_key_exists( 'height', $data ) ) {
			$product->set_height(
				wc_format_decimal( $data['height'] )
			);
		}

		if ( array_key_exists( 'status', $data ) ) {
			$status = sanitize_key( (string) $data['status'] );

			if (
				in_array(
					$status,
					array( 'publish', 'draft', 'pending', 'private' ),
					true
				)
			) {
				$product->set_status( $status );
			}
		}
	}

	/**
	 * Save wholesale-specific fields.
	 *
	 * Regular price remains WooCommerce-owned.
	 *
	 * @param \WC_Product       $product Product.
	 * @param array<string,mixed> $data Product data.
	 *
	 * @return void
	 */
	private function save_wholesale_fields(
		\WC_Product $product,
		array $data
	): void {
		if ( array_key_exists( 'wholesale_price', $data ) ) {
			$value = trim(
				(string) $data['wholesale_price']
			);

			if ( '' === $value ) {
				delete_post_meta(
					$product->get_id(),
					ProductFields::META_WHOLESALE_PRICE
				);
			} elseif ( is_numeric( $value ) && (float) $value >= 0 ) {
				update_post_meta(
					$product->get_id(),
					ProductFields::META_WHOLESALE_PRICE,
					wc_format_decimal(
						$value,
						wc_get_price_decimals()
					)
				);
			}
		}

		if ( array_key_exists( 'wholesale_min_qty', $data ) ) {
			update_post_meta(
				$product->get_id(),
				ProductFields::META_WHOLESALE_MIN_QTY,
				wc_format_decimal(
					max( 1, (float) $data['wholesale_min_qty'] ),
					6
				)
			);
		}

		if ( array_key_exists( 'wholesale_qty_step', $data ) ) {
			$value = (float) $data['wholesale_qty_step'];

			if ( $value > 0 ) {
				update_post_meta(
					$product->get_id(),
					ProductFields::META_WHOLESALE_QTY_STEP,
					wc_format_decimal(
						$value,
						6
					)
				);
			}
		}
	}

	/**
	 * Change WooCommerce product status.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Product status.
	 *
	 * @return true|\WP_Error
	 */
	private function set_status(
		int $product_id,
		string $status
	) {
		$product = $this->get( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$product->set_status( $status );
		$product->save();

		return true;
	}
}