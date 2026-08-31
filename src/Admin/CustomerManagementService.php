<?php

namespace WholesaleOrdering\Admin;

use WholesaleOrdering\Applications\ApplicationRepository;
use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Customer management service for Phase 5 administration.
 *
 * Customer identity remains owned by WordPress.
 * Wholesale lifecycle remains owned by ApplicationService.
 */
final class CustomerManagementService {

	/**
	 * Application repository.
	 */
	private ApplicationRepository $application_repository;

	/**
	 * Application service.
	 */
	private ApplicationService $application_service;

	/**
	 * Constructor.
	 *
	 * @param ApplicationRepository|null $application_repository Application repository.
	 * @param ApplicationService|null     $application_service    Application service.
	 */
	public function __construct(
		?ApplicationRepository $application_repository = null,
		?ApplicationService $application_service = null
	) {
		$this->application_repository = $application_repository
			?? new ApplicationRepository();

		$this->application_service = $application_service
			?? new ApplicationService(
				$this->application_repository
			);
	}

	/**
	 * Search and filter customers.
	 *
	 * Supported filters:
	 * - search
	 * - role
	 * - wholesale_status
	 * - registration_from
	 * - registration_to
	 * - page
	 * - per_page
	 *
	 * @param array<string,mixed> $args Query arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function list_customers( array $args = array() ): array {
		$search = isset( $args['search'] )
			? sanitize_text_field( (string) $args['search'] )
			: '';

		$wholesale_status = isset( $args['wholesale_status'] )
			? sanitize_key( (string) $args['wholesale_status'] )
			: '';

		$page = isset( $args['page'] )
			? max( 1, absint( $args['page'] ) )
			: 1;

		$per_page = isset( $args['per_page'] )
			? max( 1, min( 100, absint( $args['per_page'] ) ) )
			: 20;

		$query_args = array(
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( '' !== $search ) {
			$query_args['search'] = '*' . $search . '*';
			$query_args['search_columns'] = array(
				'user_login',
				'user_email',
				'display_name',
			);
		}

		$query = new \WP_User_Query( $query_args );

		$users = $query->get_results();

		$result = array();

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$status = WholesaleStatus::get( (int) $user->ID );

			if (
				'' !== $wholesale_status
				&& $status !== $wholesale_status
			) {
				continue;
			}

			$result[] = $this->build_customer_summary( $user );
		}

		return array(
			'items'    => $result,
			'total'    => (int) $query->get_total(),
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil(
				$query->get_total() / $per_page
			),
		);
	}

	/**
	 * Get complete customer profile.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_customer( int $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new \WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'wholesale-ordering' )
			);
		}

		$application = $this->application_repository->find_by_user_id(
			$user_id
		);

		return array(
			'id'              => (int) $user->ID,
			'username'        => $user->user_login,
			'email'           => $user->user_email,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
			'display_name'    => $user->display_name,
			'registered'      => $user->user_registered,
			'roles'           => array_values( $user->roles ),
			'wholesale_status'=> WholesaleStatus::get( $user_id ),
			'application'     => $application
				? $application->to_array()
				: null,
			'orders'          => $this->get_order_history( $user_id ),
		);
	}

	/**
	 * Get customer order history.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
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
				'date'     => $order->get_date_created()
					? $order->get_date_created()->date( 'c' )
					: '',
				'status'   => $order->get_status(),
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
			);
		}

		return $result;
	}

	/**
	 * Approve a wholesale customer.
	 *
	 * Lifecycle authority remains ApplicationService.
	 *
	 * @param int    $user_id       Customer ID.
	 * @param int    $reviewer_id   Administrator ID.
	 * @param string $internal_note Optional note.
	 *
	 * @return true|\WP_Error
	 */
	public function approve(
		int $user_id,
		int $reviewer_id,
		string $internal_note = ''
	) {
		return $this->application_service->approve(
			$user_id,
			$reviewer_id,
			$internal_note
		);
	}

	/**
	 * Reject a wholesale customer.
	 *
	 * @param int    $user_id       Customer ID.
	 * @param int    $reviewer_id   Administrator ID.
	 * @param string $internal_note Optional note.
	 *
	 * @return true|\WP_Error
	 */
	public function reject(
		int $user_id,
		int $reviewer_id,
		string $internal_note = ''
	) {
		return $this->application_service->reject(
			$user_id,
			$reviewer_id,
			$internal_note
		);
	}

	/**
	 * Suspend a wholesale customer.
	 *
	 * @param int    $user_id       Customer ID.
	 * @param int    $reviewer_id   Administrator ID.
	 * @param string $internal_note Optional note.
	 *
	 * @return true|\WP_Error
	 */
	public function suspend(
		int $user_id,
		int $reviewer_id,
		string $internal_note = ''
	) {
		return $this->application_service->suspend(
			$user_id,
			$reviewer_id,
			$internal_note
		);
	}

	/**
	 * Reactivate a wholesale customer.
	 *
	 * @param int    $user_id       Customer ID.
	 * @param int    $reviewer_id   Administrator ID.
	 * @param string $internal_note Optional note.
	 *
	 * @return true|\WP_Error
	 */
	public function reactivate(
		int $user_id,
		int $reviewer_id,
		string $internal_note = ''
	) {
		return $this->application_service->reactivate(
			$user_id,
			$reviewer_id,
			$internal_note
		);
	}

	/**
	 * Build customer list data.
	 *
	 * @param \WP_User $user User.
	 *
	 * @return array<string,mixed>
	 */
	private function build_customer_summary( \WP_User $user ): array {
		$user_id = (int) $user->ID;

		return array(
			'id'               => $user_id,
			'username'         => $user->user_login,
			'email'            => $user->user_email,
			'name'             => $user->display_name,
			'company'          => (string) get_user_meta(
				$user_id,
				'_wholesale_ordering_company_name',
				true
			),
			'registered'       => $user->user_registered,
			'wholesale_status' => WholesaleStatus::get( $user_id ),
			'roles'            => array_values( $user->roles ),
		);
	}
}