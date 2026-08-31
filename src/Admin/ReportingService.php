<?php

namespace WholesaleOrdering\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Provides Phase 5 reporting/export functionality.
 *
 * Reports are generated from WooCommerce's authoritative order data.
 */
final class ReportingService {

	/**
	 * Generate an order report.
	 *
	 * @param array<string,mixed> $args Report filters.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_order_report(
		array $args = array()
	): array {
		$query = array(
			'limit'  => -1,
			'return' => 'objects',
			'orderby'=> 'date',
			'order'  => 'ASC',
		);

		if ( ! empty( $args['status'] ) ) {
			$query['status'] = array(
				sanitize_key( (string) $args['status'] ),
			);
		}

		if ( ! empty( $args['customer_id'] ) ) {
			$query['customer_id'] = absint(
				$args['customer_id']
			);
		}

		if ( ! empty( $args['date_after'] ) ) {
			$query['date_after'] = sanitize_text_field(
				(string) $args['date_after']
			);
		}

		if ( ! empty( $args['date_before'] ) ) {
			$query['date_before'] = sanitize_text_field(
				(string) $args['date_before']
			);
		}

		$orders = wc_get_orders( $query );

		$report = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$report[] = array(
				'order_id'       => $order->get_id(),
				'order_number'   => $order->get_order_number(),
				'date'           => $order->get_date_created()
					? $order->get_date_created()->date( 'Y-m-d H:i:s' )
					: '',
				'customer_id'    => $order->get_customer_id(),
				'status'         => $order->get_status(),
				'currency'       => $order->get_currency(),
				'subtotal'       => $order->get_subtotal(),
				'discount_total' => $order->get_discount_total(),
				'shipping_total' => $order->get_shipping_total(),
				'tax_total'      => $order->get_total_tax(),
				'total'          => $order->get_total(),
				'payment_method' => $order->get_payment_method(),
			);
		}

		return $report;
	}

	/**
	 * Build CSV content for an order report.
	 *
	 * @param array<string,mixed> $args Report filters.
	 *
	 * @return string
	 */
	public function export_orders_csv(
		array $args = array()
	): string {
		$rows = $this->get_order_report( $args );

		$handle = fopen( 'php://temp', 'w+' );

		if ( false === $handle ) {
			return '';
		}

		fputcsv(
			$handle,
			array(
				'Order ID',
				'Order Number',
				'Date',
				'Customer ID',
				'Status',
				'Currency',
				'Subtotal',
				'Discount',
				'Shipping',
				'Tax',
				'Total',
				'Payment Method',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$handle,
				array(
					$row['order_id'],
					$row['order_number'],
					$row['date'],
					$row['customer_id'],
					$row['status'],
					$row['currency'],
					$row['subtotal'],
					$row['discount_total'],
					$row['shipping_total'],
					$row['tax_total'],
					$row['total'],
					$row['payment_method'],
				)
			);
		}

		rewind( $handle );

		$csv = stream_get_contents( $handle );

		fclose( $handle );

		return is_string( $csv )
			? $csv
			: '';
	}

	/**
	 * Return basic sales summary.
	 *
	 * @param array<string,mixed> $args Report filters.
	 *
	 * @return array<string,mixed>
	 */
	public function get_sales_summary(
		array $args = array()
	): array {
		$rows = $this->get_order_report( $args );

		$total = 0.0;
		$tax   = 0.0;
		$shipping = 0.0;
		$discounts = 0.0;

		foreach ( $rows as $row ) {
			$total     += (float) $row['total'];
			$tax       += (float) $row['tax_total'];
			$shipping  += (float) $row['shipping_total'];
			$discounts += (float) $row['discount_total'];
		}

		return array(
			'orders'          => count( $rows ),
			'total'           => wc_format_decimal(
				$total,
				wc_get_price_decimals()
			),
			'tax'             => wc_format_decimal(
				$tax,
				wc_get_price_decimals()
			),
			'shipping'        => wc_format_decimal(
				$shipping,
				wc_get_price_decimals()
			),
			'discounts'       => wc_format_decimal(
				$discounts,
				wc_get_price_decimals()
			),
			'currency'        => get_woocommerce_currency(),
		);
	}
}