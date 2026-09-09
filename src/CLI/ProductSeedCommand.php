<?php

namespace WholesaleOrdering\CLI;

use WholesaleOrdering\Products\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for deterministic Wholesale Ordering product fixtures.
 *
 * Commands:
 *
 * wp wholesale products seed <csv>
 * wp wholesale products count
 * wp wholesale products cleanup
 *
 * This tooling is intended for development/staging test data.
 */
final class ProductSeedCommand {

	/**
	 * Meta key marking a product as a Wholesale Ordering seed product.
	 */
	private const META_SEED = '_wholesale_ordering_seed';

	/**
	 * Meta key containing the deterministic seed identifier.
	 */
	private const META_SEED_ID = '_wholesale_ordering_seed_id';

	/**
	 * Product post type.
	 */
	private const PRODUCT_POST_TYPE = 'product';

	/**
	 * Register the WP-CLI command.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command(
			'wholesale products',
			self::class
		);
	}

	/**
	 * Import or update products from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file containing the product seed data.
	 *
	 * [--dry-run]
	 * : Validate and report what would happen without writing data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wholesale products seed data/products.csv
	 *     wp wholesale products seed data/products.csv --dry-run
	 *
	 * @param array<int,string>     $args       Positional arguments.
	 * @param array<string,mixed>   $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function seed(
		array $args,
		array $assoc_args
	): void {
		if ( ! class_exists( '\WooCommerce' ) ) {
			\WP_CLI::error(
				'WooCommerce must be active before product seeding can run.'
			);
		}

		if ( empty( $args[0] ) ) {
			\WP_CLI::error(
				'Please provide a CSV file path.'
			);
		}

		$file = self::resolve_csv_path(
			(string) $args[0]
		);

		if ( ! is_readable( $file ) ) {
			\WP_CLI::error(
				sprintf(
					'CSV file is not readable: %s',
					$file
				)
			);
		}

		$rows = self::read_csv( $file );

		if ( empty( $rows ) ) {
			\WP_CLI::error(
				'The CSV contains no product rows.'
			);
		}

		$validation_errors = array();

		foreach ( $rows as $index => $row ) {
			$errors = self::validate_row(
				$row,
				$index + 2
			);

			if ( ! empty( $errors ) ) {
				$validation_errors = array_merge(
					$validation_errors,
					$errors
				);
			}
		}

		if ( ! empty( $validation_errors ) ) {
			foreach ( $validation_errors as $error ) {
				\WP_CLI::warning( $error );
			}

			\WP_CLI::error(
				sprintf(
					'CSV validation failed with %d error(s). No products were written.',
					count( $validation_errors )
				)
			);
		}

		$dry_run = isset( $assoc_args['dry-run'] );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = 0;

		foreach ( $rows as $row ) {
			try {
				$result = self::upsert_product(
					$row,
					$dry_run
				);

				switch ( $result ) {
					case 'created':
						$created++;
						break;

					case 'updated':
						$updated++;
						break;

					case 'skipped':
						$skipped++;
						break;
				}
			} catch ( \Throwable $exception ) {
				$errors++;

				\WP_CLI::warning(
					sprintf(
						'%s: %s',
						$row['seed_id'] ?? 'unknown',
						$exception->getMessage()
					)
				);
			}
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Wholesale Ordering product seed summary' );
		\WP_CLI::log( '----------------------------------------' );
		\WP_CLI::log(
			sprintf(
				'CSV rows: %d',
				count( $rows )
			)
		);
		\WP_CLI::log(
			sprintf(
				'Created: %d',
				$created
			)
		);
		\WP_CLI::log(
			sprintf(
				'Updated: %d',
				$updated
			)
		);
		\WP_CLI::log(
			sprintf(
				'Skipped: %d',
				$skipped
			)
		);
		\WP_CLI::log(
			sprintf(
				'Errors: %d',
				$errors
			)
		);

		if ( $dry_run ) {
			\WP_CLI::success(
				'Dry run completed. No products were changed.'
			);

			return;
		}

		if ( $errors > 0 ) {
			\WP_CLI::warning(
				'Seed completed with errors. Review the warnings above.'
			);

			return;
		}

		\WP_CLI::success(
			'Product seed completed successfully.'
		);
	}

	/**
	 * Count products created by this seed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wholesale products count
	 *
	 * @return void
	 */
	public function count(
		array $args,
		array $assoc_args
	): void {
		unset( $args, $assoc_args );

		$query = new \WP_Query(
			array(
				'post_type'      => self::PRODUCT_POST_TYPE,
				'post_status'    => array(
					'publish',
					'draft',
					'pending',
					'private',
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SEED,
				'meta_value'     => '1',
			)
		);

		\WP_CLI::success(
			sprintf(
				'Wholesale Ordering seed products: %d',
				(int) $query->found_posts
			)
		);
	}

	/**
	 * Delete only products created by this seed.
	 *
	 * This is deliberately separate from WordPress/WooCommerce product
	 * deletion commands so that the seed boundary remains explicit.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wholesale products cleanup
	 *     wp wholesale products cleanup --yes
	 *
	 * @param array<int,string>     $args       Positional arguments.
	 * @param array<string,mixed>   $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cleanup(
		array $args,
		array $assoc_args
	): void {
		unset( $args );

		$query = new \WP_Query(
			array(
				'post_type'      => self::PRODUCT_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SEED,
				'meta_value'     => '1',
			)
		);

		$product_ids = array_map(
			'absint',
			$query->posts
		);

		if ( empty( $product_ids ) ) {
			\WP_CLI::success(
				'No Wholesale Ordering seed products found.'
			);

			return;
		}

		$count = count( $product_ids );

		if ( ! isset( $assoc_args['yes'] ) ) {
			\WP_CLI::confirm(
				sprintf(
					'Delete %d Wholesale Ordering seed product(s)?',
					$count
				)
			);
		}

		$deleted = 0;

		foreach ( $product_ids as $product_id ) {
			if ( wp_delete_post( $product_id, true ) ) {
				$deleted++;
			}
		}

		\WP_CLI::success(
			sprintf(
				'Deleted %d of %d seed product(s).',
				$deleted,
				$count
			)
		);
	}

	/**
	 * Resolve a CSV path.
	 *
	 * Relative paths are resolved against the current working directory.
	 * The command therefore works from the LocalWP Site Shell.
	 *
	 * @param string $file CSV path.
	 *
	 * @return string
	 */
	private static function resolve_csv_path(
		string $file
	): string {
		$file = trim( $file );

		if ( '' === $file ) {
			return '';
		}

		if (
			'/' === $file[0]
			|| preg_match( '/^[A-Za-z]:[\\\\\/]/', $file )
		) {
			return $file;
		}

		return getcwd() . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * Read and normalize CSV rows.
	 *
	 * @param string $file CSV path.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function read_csv(
		string $file
	): array {
		$handle = fopen( $file, 'rb' );

		if ( false === $handle ) {
			\WP_CLI::error(
				'Unable to open CSV file.'
			);
		}

		$headers = fgetcsv( $handle );

		if ( false === $headers || empty( $headers ) ) {
			fclose( $handle );

			\WP_CLI::error(
				'CSV header row is missing.'
			);
		}

		$headers = array_map(
			static function ( $header ): string {
				$header = (string) $header;

				/*
				 * Remove UTF-8 BOM from the first header if present.
				 */
				return trim(
					preg_replace(
						'/^\xEF\xBB\xBF/',
						'',
						$header
					)
				);
			},
			$headers
		);

		$required_headers = array(
			'seed_id',
			'product_name',
			'description',
			'regular_price',
			'wholesale_price',
			'wholesale_minimum_quantity',
			'quantity_step',
			'brand',
			'category',
			'image',
		);

		$missing_headers = array_diff(
			$required_headers,
			$headers
		);

		if ( ! empty( $missing_headers ) ) {
			fclose( $handle );

			\WP_CLI::error(
				sprintf(
					'CSV is missing required column(s): %s',
					implode( ', ', $missing_headers )
				)
			);
		}

		$rows = array();

		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			if (
				1 === count( $data )
				&& '' === trim( (string) $data[0] )
			) {
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
	 * Validate one CSV row.
	 *
	 * @param array<string,string> $row  CSV row.
	 * @param int                  $line CSV line number.
	 *
	 * @return array<int,string>
	 */
	private static function validate_row(
		array $row,
		int $line
	): array {
		$errors = array();

		$required = array(
			'seed_id',
			'product_name',
			'description',
			'regular_price',
			'wholesale_price',
			'wholesale_minimum_quantity',
			'quantity_step',
			'brand',
			'category',
		);

		foreach ( $required as $field ) {
			if (
				! isset( $row[ $field ] )
				|| '' === trim( $row[ $field ] )
			) {
				$errors[] = sprintf(
					'CSV line %d: missing required field "%s".',
					$line,
					$field
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return $errors;
		}

		if ( ! is_numeric( $row['regular_price'] ) ) {
			$errors[] = sprintf(
				'CSV line %d: regular_price must be numeric.',
				$line
			);
		}

		if ( ! is_numeric( $row['wholesale_price'] ) ) {
			$errors[] = sprintf(
				'CSV line %d: wholesale_price must be numeric.',
				$line
			);
		}

		if ( ! is_numeric( $row['wholesale_minimum_quantity'] ) ) {
			$errors[] = sprintf(
				'CSV line %d: wholesale_minimum_quantity must be numeric.',
				$line
			);
		}

		if ( ! is_numeric( $row['quantity_step'] ) ) {
			$errors[] = sprintf(
				'CSV line %d: quantity_step must be numeric.',
				$line
			);
		}

		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$regular_price  = (float) $row['regular_price'];
		$wholesale_price = (float) $row['wholesale_price'];
		$minimum        = (float) $row['wholesale_minimum_quantity'];
		$step            = (float) $row['quantity_step'];

		if ( $regular_price < 0 ) {
			$errors[] = sprintf(
				'CSV line %d: regular_price cannot be negative.',
				$line
			);
		}

		if ( $wholesale_price < 0 ) {
			$errors[] = sprintf(
				'CSV line %d: wholesale_price cannot be negative.',
				$line
			);
		}

		if ( $wholesale_price > $regular_price ) {
			$errors[] = sprintf(
				'CSV line %d: wholesale_price cannot exceed regular_price.',
				$line
			);
		}

		if ( $minimum < 1 ) {
			$errors[] = sprintf(
				'CSV line %d: wholesale_minimum_quantity must be >= 1.',
				$line
			);
		}

		if ( $step <= 0 ) {
			$errors[] = sprintf(
				'CSV line %d: quantity_step must be > 0.',
				$line
			);
		}

		return $errors;
	}

	/**
	 * Create or update a WooCommerce simple product.
	 *
	 * @param array<string,string> $row     CSV row.
	 * @param bool                 $dry_run Whether to avoid writes.
	 *
	 * @return string created|updated|skipped
	 */
	private static function upsert_product(
		array $row,
		bool $dry_run
	): string {
		$seed_id = sanitize_text_field(
			$row['seed_id']
		);

		$existing_id = self::find_product_by_seed_id(
			$seed_id
		);

		if ( $dry_run ) {
			\WP_CLI::log(
				sprintf(
					'[DRY RUN] %s → %s',
					$seed_id,
					$existing_id > 0
						? 'update'
						: 'create'
				)
			);

			return $existing_id > 0
				? 'updated'
				: 'created';
		}

		$product = $existing_id > 0
			? wc_get_product( $existing_id )
			: new \WC_Product_Simple();

		if ( ! $product instanceof \WC_Product_Simple ) {
			throw new \RuntimeException(
				sprintf(
					'Product ID %d could not be loaded as a simple product.',
					$existing_id
				)
			);
		}

		$product->set_name(
			sanitize_text_field(
				$row['product_name']
			)
		);

		$product->set_description(
			wp_kses_post(
				$row['description']
			)
		);

		$product->set_status( 'publish' );

		$product->set_catalog_visibility( 'visible' );

		$product->set_regular_price(
			self::format_price(
				$row['regular_price']
			)
		);

		/*
		 * Important:
		 *
		 * The WooCommerce product price itself remains anchored to the
		 * Regular Price. Wholesale Ordering's PricingService determines
		 * the customer-eligible price at runtime.
		 */
		$product->set_price(
			self::format_price(
				$row['regular_price']
			)
		);

		$product->set_sku(
			self::build_sku(
				$seed_id
			)
		);

		$product->update_meta_data(
			ProductFields::META_WHOLESALE_PRICE,
			self::format_price(
				$row['wholesale_price']
			)
		);

		$product->update_meta_data(
			ProductFields::META_WHOLESALE_MIN_QTY,
			self::format_quantity(
				$row['wholesale_minimum_quantity']
			)
		);

		$product->update_meta_data(
			ProductFields::META_WHOLESALE_QTY_STEP,
			self::format_quantity(
				$row['quantity_step']
			)
		);

		$product->update_meta_data(
			ProductFields::META_WHOLESALE_ONLY,
			'no'
		);

		$product->update_meta_data(
			self::META_SEED,
			'1'
		);

		$product->update_meta_data(
			self::META_SEED_ID,
			$seed_id
		);

		$product_id = $product->save();

		if ( ! $product_id ) {
			throw new \RuntimeException(
				'WooCommerce failed to save the product.'
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			throw new \RuntimeException(
				'WooCommerce product could not be reloaded after save.'
			);
		}

		self::assign_category(
			$product_id,
			$row['category']
		);

		self::assign_brand(
			$product_id,
			$row['brand']
		);

		self::maybe_set_image(
			$product,
			$row['image']
		);

		return $existing_id > 0
			? 'updated'
			: 'created';
	}

	/**
	 * Find an existing seeded product.
	 *
	 * @param string $seed_id Seed identifier.
	 *
	 * @return int
	 */
	private static function find_product_by_seed_id(
		string $seed_id
	): int {
		$posts = get_posts(
			array(
				'post_type'      => self::PRODUCT_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_SEED_ID,
				'meta_value'     => $seed_id,
			)
		);

		return empty( $posts )
			? 0
			: absint( $posts[0] );
	}

	/**
	 * Assign a WooCommerce product category.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $category   Category name.
	 *
	 * @return void
	 */
	private static function assign_category(
		int $product_id,
		string $category
	): void {
		$category = sanitize_text_field(
			$category
		);

		if ( '' === $category ) {
			return;
		}

		$term = term_exists(
			$category,
			'product_cat'
		);

		if ( ! $term ) {
			$term = wp_insert_term(
				$category,
				'product_cat'
			);
		}

		if ( is_wp_error( $term ) ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to create category "%s": %s',
					$category,
					$term->get_error_message()
				)
			);
		}

		$term_id = is_array( $term )
			? absint( $term['term_id'] )
			: absint( $term );

		if ( $term_id <= 0 ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to resolve category "%s".',
					$category
				)
			);
		}

		wp_set_object_terms(
			$product_id,
			array( $term_id ),
			'product_cat',
			false
		);
	}

	/**
	 * Assign an existing WooCommerce Brands taxonomy when available.
	 *
	 * We deliberately do not create a custom brand taxonomy here.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $brand      Brand name.
	 *
	 * @return void
	 */
	private static function assign_brand(
		int $product_id,
		string $brand
	): void {
		$brand = sanitize_text_field(
			$brand
		);

		if (
			'' === $brand
			|| ! taxonomy_exists( 'product_brand' )
		) {
			return;
		}

		$term = term_exists(
			$brand,
			'product_brand'
		);

		if ( ! $term ) {
			$term = wp_insert_term(
				$brand,
				'product_brand'
			);
		}

		if ( is_wp_error( $term ) ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to create brand "%s": %s',
					$brand,
					$term->get_error_message()
				)
			);
		}

		$term_id = is_array( $term )
			? absint( $term['term_id'] )
			: absint( $term );

		if ( $term_id <= 0 ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to resolve brand "%s".',
					$brand
				)
			);
		}

		wp_set_object_terms(
			$product_id,
			array( $term_id ),
			'product_brand',
			false
		);
	}

	/**
	 * Set a product image when the referenced image exists.
	 *
	 * Missing seed images are non-fatal.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $image   Image path/name.
	 *
	 * @return void
	 */
	private static function maybe_set_image(
		\WC_Product $product,
		string $image
	): void {
		$image = trim( $image );

		if ( '' === $image ) {
			return;
		}

		$possible_paths = array(
			getcwd() . DIRECTORY_SEPARATOR . $image,
			getcwd() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $image,
			dirname( getcwd() ) . DIRECTORY_SEPARATOR . $image,
		);

		$image_path = '';

		foreach ( $possible_paths as $possible_path ) {
			if ( is_readable( $possible_path ) ) {
				$image_path = $possible_path;
				break;
			}
		}

		if ( '' === $image_path ) {
			\WP_CLI::warning(
				sprintf(
					'Image not found for %s: %s. Product imported without image.',
					$product->get_sku(),
					$image
				)
			);

			return;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => basename( $image_path ),
				'tmp_name' => $image_path,
			),
			$product->get_id()
		);

		if ( is_wp_error( $attachment_id ) ) {
			\WP_CLI::warning(
				sprintf(
					'Image import failed for %s: %s',
					$product->get_sku(),
					$attachment_id->get_error_message()
				)
			);

			return;
		}

		$product->set_image_id(
			$attachment_id
		);

		$product->save();
	}

	/**
	 * Build deterministic SKU.
	 *
	 * @param string $seed_id Seed identifier.
	 *
	 * @return string
	 */
	private static function build_sku(
		string $seed_id
	): string {
		return strtoupper(
			'WO-' . sanitize_title( $seed_id )
		);
	}

	/**
	 * Format price using WooCommerce precision.
	 *
	 * @param string|float $price Price.
	 *
	 * @return string
	 */
	private static function format_price(
		$price
	): string {
		return wc_format_decimal(
			$price,
			wc_get_price_decimals()
		);
	}

	/**
	 * Format quantity metadata.
	 *
	 * @param string|float $quantity Quantity.
	 *
	 * @return string
	 */
	private static function format_quantity(
		$quantity
	): string {
		return wc_format_decimal(
			$quantity,
			6
		);
	}
}