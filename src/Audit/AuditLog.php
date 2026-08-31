<?php

namespace WholesaleOrdering\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 7 audit logging.
 *
 * Records high-value security and business events without storing secrets
 * or sensitive payloads.
 *
 * The audit trail intentionally uses a bounded WordPress option so Phase 7
 * does not introduce a second custom persistence architecture. Events are
 * retained in newest-first order and capped to a safe maximum.
 */
final class AuditLog {

	/**
	 * WordPress option containing the audit events.
	 */
	private const OPTION_KEY = 'wholesale_ordering_audit_log';

	/**
	 * Maximum number of retained events.
	 */
	private const MAX_EVENTS = 500;

	/**
	 * Register audit hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'wholesale_ordering_application_approved',
			array( self::class, 'record_application_approved' ),
			10,
			4
		);

		add_action(
			'wholesale_ordering_application_rejected',
			array( self::class, 'record_application_rejected' ),
			10,
			4
		);

		add_action(
			'wholesale_ordering_application_suspended',
			array( self::class, 'record_application_suspended' ),
			10,
			4
		);

		add_action(
			'wholesale_ordering_application_reactivated',
			array( self::class, 'record_application_reactivated' ),
			10,
			4
		);

		add_action(
			'woocommerce_order_refunded',
			array( self::class, 'record_refund' ),
			10,
			2
		);

		add_action(
			'set_user_role',
			array( self::class, 'record_role_change' ),
			10,
			3
		);

		add_action(
			'add_user_role',
			array( self::class, 'record_role_added' ),
			10,
			2
		);

		add_action(
			'remove_user_role',
			array( self::class, 'record_role_removed' ),
			10,
			2
		);

		add_action(
			'added_post_meta',
			array( self::class, 'record_price_meta_change' ),
			10,
			4
		);

		add_action(
			'updated_post_meta',
			array( self::class, 'record_price_meta_change' ),
			10,
			4
		);

		add_action(
			'deleted_post_meta',
			array( self::class, 'record_price_meta_change' ),
			10,
			4
		);
	}

	/**
	 * Record wholesale approval.
	 *
	 * @param int    $user_id       Affected customer.
	 * @param int    $reviewer_id   Reviewer.
	 * @param string $timestamp     Event timestamp.
	 * @param string $internal_note Internal note. Never persisted.
	 *
	 * @return void
	 */
	public static function record_application_approved(
		int $user_id,
		int $reviewer_id,
		string $timestamp,
		string $internal_note = ''
	): void {
		self::record(
			'application_approved',
			$user_id,
			$reviewer_id,
			array(
				'status'    => 'approved',
				'timestamp' => self::sanitize_timestamp( $timestamp ),
			)
		);
	}

	/**
	 * Record wholesale rejection.
	 *
	 * @param int    $user_id       Affected customer.
	 * @param int    $reviewer_id   Reviewer.
	 * @param string $timestamp     Event timestamp.
	 * @param string $internal_note Internal note. Never persisted.
	 *
	 * @return void
	 */
	public static function record_application_rejected(
		int $user_id,
		int $reviewer_id,
		string $timestamp,
		string $internal_note = ''
	): void {
		self::record(
			'application_rejected',
			$user_id,
			$reviewer_id,
			array(
				'status'    => 'rejected',
				'timestamp' => self::sanitize_timestamp( $timestamp ),
			)
		);
	}

	/**
	 * Record wholesale suspension.
	 *
	 * @param int    $user_id       Affected customer.
	 * @param int    $reviewer_id   Reviewer.
	 * @param string $timestamp     Event timestamp.
	 * @param string $internal_note Internal note. Never persisted.
	 *
	 * @return void
	 */
	public static function record_application_suspended(
		int $user_id,
		int $reviewer_id,
		string $timestamp,
		string $internal_note = ''
	): void {
		self::record(
			'application_suspended',
			$user_id,
			$reviewer_id,
			array(
				'status'    => 'suspended',
				'timestamp' => self::sanitize_timestamp( $timestamp ),
			)
		);
	}

	/**
	 * Record wholesale reactivation.
	 *
	 * @param int    $user_id       Affected customer.
	 * @param int    $reviewer_id   Reviewer.
	 * @param string $timestamp     Event timestamp.
	 * @param string $internal_note Internal note. Never persisted.
	 *
	 * @return void
	 */
	public static function record_application_reactivated(
		int $user_id,
		int $reviewer_id,
		string $timestamp,
		string $internal_note = ''
	): void {
		self::record(
			'application_reactivated',
			$user_id,
			$reviewer_id,
			array(
				'status'    => 'approved',
				'timestamp' => self::sanitize_timestamp( $timestamp ),
			)
		);
	}

	/**
	 * Record a WooCommerce refund.
	 *
	 * @param int $order_id Order ID.
	 * @param int $refund_id Refund ID.
	 *
	 * @return void
	 */
	public static function record_refund(
		int $order_id,
		int $refund_id = 0
	): void {
		$actor_id = get_current_user_id();

		self::record(
			'refund',
			$order_id,
			$actor_id,
			array(
				'order_id' => absint( $order_id ),
				'refund_id' => absint( $refund_id ),
			)
		);
	}

	/**
	 * Record a complete role replacement.
	 *
	 * @param int    $user_id     Affected user.
	 * @param string $role        New role.
	 * @param array  $old_roles   Previous roles.
	 *
	 * @return void
	 */
	public static function record_role_change(
		int $user_id,
		string $role,
		array $old_roles = array()
	): void {
		self::record(
			'permission_role_changed',
			$user_id,
			get_current_user_id(),
			array(
				'new_role'  => sanitize_key( $role ),
				'old_roles' => self::sanitize_role_list( $old_roles ),
			)
		);
	}

	/**
	 * Record a role addition.
	 *
	 * @param int    $user_id Affected user.
	 * @param string $role    Added role.
	 *
	 * @return void
	 */
	public static function record_role_added(
		int $user_id,
		string $role
	): void {
		self::record(
			'permission_role_added',
			$user_id,
			get_current_user_id(),
			array(
				'role' => sanitize_key( $role ),
			)
		);
	}

	/**
	 * Record a role removal.
	 *
	 * @param int    $user_id Affected user.
	 * @param string $role    Removed role.
	 *
	 * @return void
	 */
	public static function record_role_removed(
		int $user_id,
		string $role
	): void {
		self::record(
			'permission_role_removed',
			$user_id,
			get_current_user_id(),
			array(
				'role' => sanitize_key( $role ),
			)
		);
	}

	/**
	 * Record changes to supported product price metadata.
	 *
	 * @param int    $meta_id    Metadata ID.
	 * @param int    $object_id  Product/post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New/current value.
	 *
	 * @return void
	 */
	public static function record_price_meta_change(
		int $meta_id,
		int $object_id,
		string $meta_key,
		$meta_value
	): void {
		if ( ! in_array(
			$meta_key,
			array(
				'_regular_price',
				'_wholesale_price',
			),
			true
		) ) {
			return;
		}

		$post = get_post( $object_id );

		if ( ! $post || ! in_array(
			$post->post_type,
			array( 'product', 'product_variation' ),
			true
		) ) {
			return;
		}

		/*
		 * Never put the actual price into the audit record.
		 *
		 * The event proves that a price field changed without creating
		 * another copy of commercially sensitive pricing data.
		 */
		self::record(
			'price_changed',
			$object_id,
			get_current_user_id(),
			array(
				'product_id' => absint( $object_id ),
				'field'      => sanitize_key( $meta_key ),
			)
		);
	}

	/**
	 * Return the current audit entries.
	 *
	 * This method is intended for controlled administrative tooling.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_entries(): array {
		$events = get_option(
			self::OPTION_KEY,
			array()
		);

		if ( ! is_array( $events ) ) {
			return array();
		}

		return $events;
	}

	/**
	 * Record one sanitized audit event.
	 *
	 * @param string               $event      Event name.
	 * @param int                  $subject_id Affected object/user.
	 * @param int                  $actor_id   Acting user.
	 * @param array<string,mixed>  $details    Safe event details.
	 *
	 * @return void
	 */
	private static function record(
		string $event,
		int $subject_id,
		int $actor_id,
		array $details = array()
	): void {
		$events = self::get_entries();

		$events[] = array(
			'id'         => wp_generate_uuid4(),
			'event'      => sanitize_key( $event ),
			'actor_id'   => absint( $actor_id ),
			'subject_id' => absint( $subject_id ),
			'timestamp'  => current_time( 'mysql', true ),
			'details'    => self::sanitize_details( $details ),
		);

		if ( count( $events ) > self::MAX_EVENTS ) {
			$events = array_slice(
				$events,
				-count( $events ),
				self::MAX_EVENTS
			);
		}

		update_option(
			self::OPTION_KEY,
			$events,
			false
		);
	}

	/**
	 * Sanitize safe event details.
	 *
	 * @param array<string,mixed> $details Details.
	 *
	 * @return array<string,mixed>
	 */
	private static function sanitize_details( array $details ): array {
		$result = array();

		foreach ( $details as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$result[ $key ] = $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$result[ $key ] = sanitize_text_field( $value );
				continue;
			}

			if ( is_array( $value ) ) {
				$result[ $key ] = array_map(
					static function ( $item ) {
						return is_scalar( $item )
							? sanitize_text_field( (string) $item )
							: '';
					},
					$value
				);
			}
		}

		return $result;
	}

	/**
	 * Sanitize a role list.
	 *
	 * @param array<int,mixed> $roles Roles.
	 *
	 * @return array<int,string>
	 */
	private static function sanitize_role_list( array $roles ): array {
		$result = array();

		foreach ( $roles as $role ) {
			if ( is_string( $role ) && '' !== $role ) {
				$result[] = sanitize_key( $role );
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Sanitize an audit timestamp.
	 *
	 * @param string $timestamp Timestamp.
	 *
	 * @return string
	 */
	private static function sanitize_timestamp(
		string $timestamp
	): string {
		return sanitize_text_field( $timestamp );
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}