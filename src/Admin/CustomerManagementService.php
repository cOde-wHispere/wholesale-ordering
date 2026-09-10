<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Applications\ApplicationRepository;
use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Customers\WholesaleStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Customer management service for Phase 5 administration.
 *
 * WordPress remains authoritative for customer identity and WooCommerce
 * remains authoritative for customer order history. Wholesale lifecycle
 * transitions are delegated to ApplicationService.
 */
final class CustomerManagementService {

	private ApplicationRepository $application_repository;
	private ApplicationService $application_service;

	public function __construct( ?ApplicationRepository $application_repository = null, ?ApplicationService $application_service = null ) {
		$this->application_repository = $application_repository ?? new ApplicationRepository();
		$this->application_service    = $application_service ?? new ApplicationService( $this->application_repository );
	}

	public function list_customers( array $args = array() ): array {
		$search           = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$wholesale_status = isset( $args['wholesale_status'] ) ? sanitize_key( (string) $args['wholesale_status'] ) : '';
		$page             = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page         = isset( $args['per_page'] ) ? max( 1, min( 100, absint( $args['per_page'] ) ) ) : 20;

		$query_args = array(
			'number'  => -1,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( '' !== $search ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new \WP_User_Query( $query_args );
		$users = $query->get_results();
		$filtered = array();

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$status = WholesaleStatus::get( (int) $user->ID );
			if ( '' !== $wholesale_status && $status !== $wholesale_status ) {
				continue;
			}

			$filtered[] = $this->build_customer_summary( $user );
		}

		$total = count( $filtered );
		$items = array_slice( $filtered, ( $page - 1 ) * $per_page, $per_page );

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		);
	}

	public function get_customer( int $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'customer_not_found', __( 'Customer not found.', 'wholesale-ordering' ) );
		}

		$application = $this->application_repository->find_by_user_id( $user_id );

		return array(
			'id'               => (int) $user->ID,
			'username'         => $user->user_login,
			'email'            => $user->user_email,
			'first_name'       => $user->first_name,
			'last_name'        => $user->last_name,
			'display_name'     => $user->display_name,
			'registered'       => $user->user_registered,
			'roles'            => array_values( $user->roles ),
			'wholesale_status'  => WholesaleStatus::get( $user_id ),
			'application'      => $application ? $application->to_array() : null,
			'orders'           => $this->get_order_history( $user_id ),
		);
	}

	public function get_order_history( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);

		$result = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$result[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
				'status'   => $order->get_status(),
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
			);
		}
		return $result;
	}

	public function approve( int $user_id, int $reviewer_id, string $internal_note = '' ) {
		return $this->application_service->approve( $user_id, $reviewer_id, $internal_note );
	}

	public function reject( int $user_id, int $reviewer_id, string $internal_note = '' ) {
		return $this->application_service->reject( $user_id, $reviewer_id, $internal_note );
	}

	public function suspend( int $user_id, int $reviewer_id, string $internal_note = '' ) {
		return $this->application_service->suspend( $user_id, $reviewer_id, $internal_note );
	}

	public function reactivate( int $user_id, int $reviewer_id, string $internal_note = '' ) {
		return $this->application_service->reactivate( $user_id, $reviewer_id, $internal_note );
	}

	private function build_customer_summary( \WP_User $user ): array {
		$user_id = (int) $user->ID;
		return array(
			'id'               => $user_id,
			'username'         => $user->user_login,
			'email'            => $user->user_email,
			'name'             => $user->display_name,
			'company'          => (string) get_user_meta( $user_id, '_wholesale_ordering_company_name', true ),
			'registered'       => $user->user_registered,
			'wholesale_status' => WholesaleStatus::get( $user_id ),
			'roles'            => array_values( $user->roles ),
		);
	}
}
