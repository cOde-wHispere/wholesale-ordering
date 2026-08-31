<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Applications\ApplicationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Provides authoritative data for the Phase 5 administration dashboard.
 *
 * WooCommerce remains the source of truth for order and revenue metrics.
 * Wholesale application counts come from ApplicationRepository.
 */
final class DashboardService {

	/**
	 * Application repository.
	 */
	private ApplicationRepository $application_repository;

	/**
	 * Constructor.
	 *
	 * @param ApplicationRepository|null $application_repository Application repository.
	 */
	public function __construct(
		?ApplicationRepository $application_repository = null
	) {
		$this->application_repository = $application_repository
			?? new ApplicationRepository();
	}

	/**
	 * Return the complete dashboard data set.
	 *
	 * @return array<string,mixed>
	 */
	public function get_dashboard(): array {
		return array(
			'orders'        => $this->get_order_metrics(),
			'applications'  => $this->get_application_metrics(),
			'revenue'       => $this->get_revenue_summary(),
			'low_stock'     => $this->get_low_stock_products(),
			'recent_customers' => $this->get_recent_customers(),
			'alerts'        => $this->get_operational_alerts(),
		);
	}

	/**
	 * Return new/pending order counts.
	 *
	 * @return array<string,int>
	 */
	public function get_order_metrics(): array {
		$new_orders = wc_get_orders(
			array(
				'status' => array( 'wc-processing', 'wc-on-hold' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$pending_payment = wc_get_orders(
			array(
				'status' => array( 'wc-pending' ),
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		return array(
			'new_orders'       => is_array( $new_orders ) ? count( $new_orders ) : 0,
			'pending_payment'  => is_array( $pending_payment ) ? count( $pending_payment ) : 0,
		);
	}

	/**
	 * Return pending wholesale application count.
	 *
	 * @return array<string,int>
	 */
	public function get_application_metrics(): array {
		return array(
			'pending' => $this->application_repository->count(
				array(
					'status' => 'pending',
				)
			),
		);
	}

	/**
	 * Return authoritative WooCommerce revenue information.
	 *
	 * This intentionally excludes cancelled/failed/refunded orders.
	 *
	 * @return array<string,mixed>
	 */
	public function get_revenue_summary(): array {
		$statuses = array(
			'wc-processing',
			'wc-completed',
			'wc-on-hold',
		);

		$orders = wc_get_orders(
			array(
				'status' => $statuses,
				'limit'  => -1,
				'return' => 'objects',
			)
		);

		$gross = 0.0;

		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}

				$gross += (float) $order->get_total();
			}
		}

		return array(
			'amount'   => wc_format_decimal(
				$gross,
				wc_get_price_decimals()
			),
			'currency' => get_woocommerce_currency(),
			'orders'   => is_array( $orders ) ? count( $orders ) : 0,
		);
	}

	/**
	 * Return low-stock products.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_low_stock_products(): array {
		if ( ! function_exists( 'wc_get_low_stock_products' ) ) {
			return array();
		}

		$products = wc_get_low_stock_products();

		if ( ! is_array( $products ) ) {
			return array();
		}

		$result = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$result[] = array(
				'id'       => $product->get_id(),
				'name'     => $product->get_name(),
				'sku'      => $product->get_sku(),
				'stock'    => $product->get_stock_quantity(),
				'status'   => $product->get_status(),
				'edit_url' => get_edit_post_link(
					$product->get_id(),
					''
				),
			);
		}

		return $result;
	}

	/**
	 * Return recently registered customers.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_customers(): array {
		$users = get_users(
			array(
				'role__in' => array(
					'customer',
					'approved_wholesale_customer',
				),
				'number'   => 10,
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);

		$result = array();

		foreach ( $users as $user ) {
			$result[] = array(
				'id'         => (int) $user->ID,
				'name'       => $user->display_name,
				'email'      => $user->user_email,
				'registered' => $user->user_registered,
			);
		}

		return $result;
	}

	/**
	 * Return operational alerts.
	 *
	 * Alerts are derived from current authoritative application/order data.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_operational_alerts(): array {
		$alerts = array();

		$pending = $this->application_repository->count(
			array(
				'status' => 'pending',
			)
		);

		if ( $pending > 0 ) {
			$alerts[] = array(
				'type'     => 'pending_wholesale_applications',
				'severity' => 'warning',
				'count'    => $pending,
				'message'  => sprintf(
					/* translators: %d pending applications. */
					_n(
						'%d wholesale application requires review.',
						'%d wholesale applications require review.',
						$pending,
						'wholesale-ordering'
					),
					$pending
				),
			);
		}

		return $alerts;
	}
}