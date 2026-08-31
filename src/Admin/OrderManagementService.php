<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Pricing\CustomerContext;
use WholesaleOrdering\Pricing\PricingService;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 5 order-management service.
 *
 * WooCommerce remains authoritative for order persistence, totals,
 * status transitions and refunds.
 */
final class OrderManagementService {

	/**
	 * Pricing service.
	 */
	private PricingService $pricing_service;

	/**
	 * Constructor.
	 *
	 * @param PricingService|null $pricing_service Pricing service.
	 */
	public function __construct(
		?PricingService $pricing_service = null
	) {
		$this->pricing_service = $pricing_service
			?? new PricingService();
	}

	/**
	 * Search/filter orders.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function list_orders( array $args = array() ): array {
		$page = isset( $args['page'] )
			? max( 1, absint( $args['page'] ) )
			: 1;

		$per_page = isset( $args['per_page'] )
			? max( 1, min( 100, absint( $args['per_page'] ) ) )
			: 20;

		$query = array(
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
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

		if ( ! empty( $args['payment_method'] ) ) {
			$query['payment_method'] = sanitize_key(
				(string) $args['payment_method']
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

		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field(
				(string) $args['search']
			);
		}

		$orders = wc_get_orders( $query );

		$result = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$result[] = $this->build_order_summary(
				$order
			);
		}

		$total_query = $query;
		$total_query['limit'] = -1;
		$total_query['offset'] = 0;
		$total_query['return'] = 'ids';

		$total_ids = wc_get_orders( $total_query );

		$total = is_array( $total_ids )
			? count( $total_ids )
			: 0;

		return array(
			'items'    => $result,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil(
				$total / $per_page
			),
		);
	}

	/**
	 * Get complete order details.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_order( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error(
				'order_not_found',
				__( 'Order not found.', 'wholesale-ordering' )
			);
		}

		$items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$items[] = array(
				'id'              => $item_id,
				'product_id'      => $item->get_product_id(),
				'variation_id'    => $item->get_variation_id(),
				'name'            => $item->get_name(),
				'quantity'        => $item->get_quantity(),
				'subtotal'        => $item->get_subtotal(),
				'total'           => $item->get_total(),
				'subtotal_tax'    => $item->get_subtotal_tax(),
				'total_tax'       => $item->get_total_tax(),
				'price_context'   => $item->get_meta(
					'_wholesale_ordering_price_context',
					true
				),
				'unit_price'      => $item->get_meta(
					'_wholesale_ordering_unit_price',
					true
				),
				'charged_unit_price' => $item->get_meta(
					'_wholesale_ordering_charged_unit_price',
					true
				),
			);
		}

		return array(
			'id'              => $order->get_id(),
			'number'          => $order->get_order_number(),
			'status'          => $order->get_status(),
			'date_created'    => $order->get_date_created()
				? $order->get_date_created()->date( 'c' )
				: '',
			'customer_id'     => $order->get_customer_id(),
			'currency'        => $order->get_currency(),
			'subtotal'        => $order->get_subtotal(),
			'discount_total'  => $order->get_discount_total(),
			'shipping_total'  => $order->get_shipping_total(),
			'tax_total'       => $order->get_total_tax(),
			'total'           => $order->get_total(),
			'payment_method'  => $order->get_payment_method(),
			'payment_title'   => $order->get_payment_method_title(),
			'billing'         => $order->get_address( 'billing' ),
			'shipping'        => $order->get_address( 'shipping' ),
			'customer_note'   => $order->get_customer_note(),
			'items'           => $items,
		);
	}

	/**
	 * Update an order status through WooCommerce.
	 *
	 * @param int         $order_id Order ID.
	 * @param string      $status   WooCommerce status.
	 * @param string|null $note     Optional note.
	 *
	 * @return true|\WP_Error
	 */
	public function update_status(
		int $order_id,
		string $status,
		?string $note = null
	) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error(
				'order_not_found',
				__( 'Order not found.', 'wholesale-ordering' )
			);
		}

		$status = sanitize_key( $status );

		if ( 0 !== strpos( $status, 'wc-' ) ) {
			$status = 'wc-' . $status;
		}

		$status = substr( $status, 3 );

		if ( ! in_array(
			$status,
			array_keys( wc_get_order_statuses() ),
			true
		) ) {
			return new \WP_Error(
				'invalid_order_status',
				__( 'Invalid WooCommerce order status.', 'wholesale-ordering' )
			);
		}

		$order->update_status(
			$status,
			$note
				? sanitize_textarea_field( $note )
				: ''
		);

		return true;
	}

	/**
	 * Process a refund through WooCommerce.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @param bool   $restock  Whether to restore stock.
	 *
	 * @return \WC_Order_Refund|\WP_Error
	 */
	public function refund(
		int $order_id,
		float $amount,
		string $reason = '',
		bool $restock = true
	) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error(
				'order_not_found',
				__( 'Order not found.', 'wholesale-ordering' )
			);
		}

		if ( $amount <= 0 ) {
			return new \WP_Error(
				'invalid_refund_amount',
				__( 'Refund amount must be greater than zero.', 'wholesale-ordering' )
			);
		}

		$remaining = (float) $order->get_total()
			- (float) $order->get_total_refunded();

		if ( $amount > $remaining + 0.0000001 ) {
			return new \WP_Error(
				'refund_exceeds_remaining',
				__( 'Refund amount exceeds the remaining refundable amount.', 'wholesale-ordering' )
			);
		}

		try {
			$refund = wc_create_refund(
				array(
					'amount'         => wc_format_decimal( $amount ),
					'reason'         => sanitize_text_field( $reason ),
					'order_id'       => $order_id,
					'refund_payment' => true,
					'restock_items'  => $restock,
				)
			);
		} catch ( \Throwable $exception ) {
			return new \WP_Error(
				'refund_failed',
				__( 'The refund could not be processed.', 'wholesale-ordering' )
			);
		}

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		return $refund;
	}

	/**
	 * Create a manual/admin order.
	 *
	 * Current pricing is calculated from the current customer context.
	 * Historical prices are never copied.
	 *
	 * @param int                       $customer_id Customer ID; 0 for guest.
	 * @param array<int,array<string,mixed>> $items Order items.
	 * @param array<string,mixed>       $data  Optional order data.
	 *
	 * @return \WC_Order|\WP_Error
	 */
	public function create_manual_order(
		int $customer_id,
		array $items,
		array $data = array()
	) {
		$order = wc_create_order(
			array(
				'customer_id' => max( 0, $customer_id ),
				'created_via' => 'wholesale-ordering-admin',
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$context = new CustomerContext(
			$customer_id > 0
				? $customer_id
				: null
		);

		foreach ( $items as $item ) {
			$product_id = absint(
				$item['product_id'] ?? 0
			);

			$quantity = (float) (
				$item['quantity'] ?? 0
			);

			if ( $product_id <= 0 || $quantity <= 0 ) {
				$order->delete( true );

				return new \WP_Error(
					'invalid_manual_order_item',
					__( 'A manual order contains an invalid item.', 'wholesale-ordering' )
				);
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				$order->delete( true );

				return new \WP_Error(
					'product_not_found',
					__( 'A manual order references a product that does not exist.', 'wholesale-ordering' )
				);
			}

			$price = $this->pricing_service->getEligiblePrice(
				$product,
				$context
			);

			$order_item_id = $order->add_product(
				$product,
				$quantity,
				array(
					'subtotal' => (float) $price * $quantity,
					'total'    => (float) $price * $quantity,
				)
			);

			if ( ! $order_item_id ) {
				$order->delete( true );

				return new \WP_Error(
					'manual_order_item_failed',
					__( 'A manual order item could not be added.', 'wholesale-ordering' )
				);
			}
		}

		if ( ! empty( $data['billing'] ) && is_array( $data['billing'] ) ) {
			$order->set_address(
				$data['billing'],
				'billing'
			);
		}

		if ( ! empty( $data['shipping'] ) && is_array( $data['shipping'] ) ) {
			$order->set_address(
				$data['shipping'],
				'shipping'
			);
		}

		if ( isset( $data['customer_note'] ) ) {
			$order->set_customer_note(
				sanitize_textarea_field(
					(string) $data['customer_note']
				)
			);
		}

		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Build compact order summary.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return array<string,mixed>
	 */
	private function build_order_summary(
		\WC_Order $order
	): array {
		return array(
			'id'          => $order->get_id(),
			'number'      => $order->get_order_number(),
			'customer_id' => $order->get_customer_id(),
			'status'      => $order->get_status(),
			'date'        => $order->get_date_created()
				? $order->get_date_created()->date( 'c' )
				: '',
			'total'       => $order->get_total(),
			'currency'    => $order->get_currency(),
			'payment'     => $order->get_payment_method(),
		);
	}
}