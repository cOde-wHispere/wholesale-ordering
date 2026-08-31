<?php

namespace WholesaleOrdering\Account;

use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Customers\WholesaleStatus;
use WholesaleOrdering\Infrastructure\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 6 customer account integration.
 *
 * WooCommerce owns the standard:
 *
 * - Dashboard
 * - Orders
 * - Addresses
 * - Account Details
 * - Logout
 *
 * This class adds:
 *
 * - Wholesale Status
 * - customer-safe order document presentation
 *
 * The wholesale-status endpoint is a native WordPress rewrite endpoint
 * attached to the WooCommerce My Account page.
 */
final class Account {

	/**
	 * My Account endpoint slug.
	 */
	private const ENDPOINT = 'wholesale-status';

	/**
	 * Rewrite-flush state option.
	 *
	 * A versioned value is used instead of a permanent boolean so that
	 * future endpoint/rewrite changes can trigger exactly one refresh.
	 */
	private const FLUSH_OPTION =
		'wholesale_ordering_phase6_account_rewrite_flushed';

	/**
	 * Current rewrite configuration version.
	 *
	 * Increment this whenever the account endpoint/rewrite structure
	 * changes and a new rewrite-rule refresh is required.
	 */
	private const REWRITE_VERSION = '2';

	/**
	 * Register customer account hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		/*
		 * Account functionality is customer-facing only.
		 *
		 * Do not register the frontend account hooks inside wp-admin.
		 */
		if ( is_admin() ) {
			return;
		}

		/*
		 * The rewrite endpoint must be registered before WordPress
		 * processes the My Account request.
		 */
		add_action(
			'init',
			array( self::class, 'register_endpoint' ),
			20
		);

		/*
		 * Refresh rewrite rules when this version of the endpoint
		 * configuration has not yet been installed.
		 */
		add_action(
			'init',
			array( self::class, 'maybe_flush_rewrite_rules' ),
			99
		);

		/*
		 * Add Wholesale Status to the WooCommerce My Account navigation.
		 */
		add_filter(
			'woocommerce_account_menu_items',
			array( self::class, 'add_menu_item' ),
			40
		);

		/*
		 * WooCommerce dispatches this dynamic action when the endpoint
		 * is successfully resolved.
		 */
		add_action(
			'woocommerce_account_' . self::ENDPOINT . '_endpoint',
			array( self::class, 'render_wholesale_status' )
		);

		/*
		 * Show a concise status summary on the normal account dashboard.
		 */
		add_action(
			'woocommerce_account_dashboard',
			array( self::class, 'render_dashboard_status' ),
			30
		);

		/*
		 * Present customer-authorized order documents.
		 */
		add_action(
			'woocommerce_view_order',
			array( self::class, 'render_order_documents' ),
			30
		);
	}

	/**
	 * Register the Wholesale Status rewrite endpoint.
	 *
	 * The endpoint is attached to both root and page rewrite structures.
	 *
	 * For WooCommerce My Account this allows URLs such as:
	 *
	 * /my-account/wholesale-status/
	 *
	 * to resolve through WooCommerce's endpoint dispatcher.
	 *
	 * @return void
	 */
	public static function register_endpoint(): void {
		add_rewrite_endpoint(
			self::ENDPOINT,
			EP_ROOT | EP_PAGES
		);
	}

	/**
	 * Flush rewrite rules when the installed rewrite configuration
	 * version differs from the current endpoint configuration version.
	 *
	 * This prevents flushing on every request while also fixing the
	 * permanent one-time-flush problem from the previous implementation.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules(): void {
		$installed_version = (string) get_option(
			self::FLUSH_OPTION,
			''
		);

		if ( self::REWRITE_VERSION === $installed_version ) {
			return;
		}

		/*
		 * register_endpoint() has already run at priority 20, therefore
		 * WordPress knows about the endpoint before the rules are flushed.
		 */
		flush_rewrite_rules( false );

		update_option(
			self::FLUSH_OPTION,
			self::REWRITE_VERSION,
			false
		);
	}

	/**
	 * Add Wholesale Status to My Account.
	 *
	 * @param array<string,string> $items Account menu items.
	 *
	 * @return array<string,string>
	 */
	public static function add_menu_item( array $items ): array {
		if ( ! is_user_logged_in() ) {
			return $items;
		}

		$logout = array();

		if ( isset( $items['customer-logout'] ) ) {
			$logout['customer-logout'] = $items['customer-logout'];
			unset( $items['customer-logout'] );
		}

		$result = array();

		foreach ( $items as $key => $label ) {
			$result[ $key ] = $label;

			if ( 'dashboard' === $key ) {
				$result[ self::ENDPOINT ] = __(
					'Wholesale Status',
					'wholesale-ordering'
				);
			}
		}

		foreach ( $logout as $key => $label ) {
			$result[ $key ] = $label;
		}

		return $result;
	}

	/**
	 * Render the Wholesale Status page.
	 *
	 * @return void
	 */
	public static function render_wholesale_status(): void {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			self::render_message(
				__(
					'You must be logged in to view wholesale status.',
					'wholesale-ordering'
				),
				'error'
			);

			return;
		}

		$status = WholesaleStatus::get( $user_id );

		$application = ( new ApplicationService() )->get(
			$user_id
		);

		$applied_at = '';

		if ( $application ) {
			$applied_at = $application->get_applied_at();
		}
		?>
		<section class="wholesale-account-panel wholesale-status-panel">

			<h2>
				<?php
				echo esc_html__(
					'Wholesale Status',
					'wholesale-ordering'
				);
				?>
			</h2>

			<p>
				<strong>
					<?php
					echo esc_html__(
						'Current status:',
						'wholesale-ordering'
					);
					?>
				</strong>

				<?php echo esc_html( self::status_label( $status ) ); ?>
			</p>

			<?php if ( '' !== $applied_at ) : ?>
				<p>
					<strong>
						<?php
						echo esc_html__(
							'Application submitted:',
							'wholesale-ordering'
						);
						?>
					</strong>

					<?php
					echo esc_html(
						self::format_timestamp( $applied_at )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="woocommerce-info">
				<?php
				echo esc_html(
					self::customer_message(
						$status,
						$application
					)
				);
				?>
			</div>

		</section>
		<?php
	}

	/**
	 * Render concise dashboard status.
	 *
	 * @return void
	 */
	public static function render_dashboard_status(): void {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		$status = WholesaleStatus::get( $user_id );
		?>
		<div class="wholesale-account-panel wholesale-status-summary">

			<h3>
				<?php
				echo esc_html__(
					'Wholesale Status',
					'wholesale-ordering'
				);
				?>
			</h3>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: wholesale status */
						__(
							'Your current wholesale status is: %s.',
							'wholesale-ordering'
						),
						self::status_label( $status )
					)
				);
				?>
			</p>

			<p>
				<a
					class="button"
					href="<?php echo esc_url( self::endpoint_url() ); ?>"
				>
					<?php
					echo esc_html__(
						'View Wholesale Status',
						'wholesale-ordering'
					);
					?>
				</a>
			</p>

		</div>
		<?php
	}

	/**
	 * Render customer-safe order documents.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public static function render_order_documents( int $order_id ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$current_user_id = get_current_user_id();

		if (
			$current_user_id <= 0
			|| (int) $order->get_customer_id() !== $current_user_id
		) {
			return;
		}

		$documents = apply_filters(
			'wholesale_ordering_customer_documents',
			array(),
			$order,
			$current_user_id
		);

		if ( ! is_array( $documents ) ) {
			$documents = array();
		}

		$documents = self::sanitize_documents( $documents );
		?>
		<section class="wholesale-account-panel wholesale-order-documents">

			<h2>
				<?php
				echo esc_html__(
					'Documents / Invoices',
					'wholesale-ordering'
				);
				?>
			</h2>

			<?php if ( empty( $documents ) ) : ?>

				<p>
					<?php
					echo esc_html__(
						'No additional documents or invoices are available for this order.',
						'wholesale-ordering'
					);
					?>
				</p>

			<?php else : ?>

				<ul>
					<?php foreach ( $documents as $document ) : ?>
						<li>
							<a
								href="<?php echo esc_url( $document['url'] ); ?>"
								rel="noopener noreferrer"
							>
								<?php
								echo esc_html(
									$document['label']
								);
								?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>

			<?php endif; ?>

		</section>
		<?php
	}

	/**
	 * Sanitize document descriptors.
	 *
	 * @param array<int,mixed> $documents Documents.
	 *
	 * @return array<int,array{label:string,url:string}>
	 */
	private static function sanitize_documents(
		array $documents
	): array {
		$result = array();

		foreach ( $documents as $document ) {
			if ( ! is_array( $document ) ) {
				continue;
			}

			$url = isset( $document['url'] )
				? esc_url_raw( (string) $document['url'] )
				: '';

			$label = isset( $document['label'] )
				? sanitize_text_field( (string) $document['label'] )
				: '';

			if ( '' === $url || '' === $label ) {
				continue;
			}

			$result[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $result;
	}

	/**
	 * Return customer-safe status copy.
	 *
	 * @param string $status      Status.
	 * @param mixed  $application Application.
	 *
	 * @return string
	 */
	private static function customer_message(
		string $status,
		$application
	): string {
		switch ( $status ) {
			case Config::STATUS_APPROVED:
				return __(
					'Your wholesale access is approved. Eligible products will use the configured Wholesale Price.',
					'wholesale-ordering'
				);

			case Config::STATUS_REJECTED:
				return __(
					'Your wholesale application was not approved. You may resubmit through the available application process.',
					'wholesale-ordering'
				);

			case Config::STATUS_SUSPENDED:
				return __(
					'Your wholesale access is currently suspended. Products will use the Regular Price.',
					'wholesale-ordering'
				);

			case Config::STATUS_PENDING:
			default:
				if ( $application ) {
					return __(
						'Your wholesale application is awaiting review. Products will use the Regular Price while it is pending.',
						'wholesale-ordering'
					);
				}

				return __(
					'No wholesale application has been submitted. Products use the Regular Price.',
					'wholesale-ordering'
				);
		}
	}

	/**
	 * Customer-facing status label.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private static function status_label( string $status ): string {
		$labels = array(
			Config::STATUS_PENDING   => __( 'Pending', 'wholesale-ordering' ),
			Config::STATUS_APPROVED  => __( 'Approved', 'wholesale-ordering' ),
			Config::STATUS_REJECTED  => __( 'Rejected', 'wholesale-ordering' ),
			Config::STATUS_SUSPENDED => __( 'Suspended', 'wholesale-ordering' ),
		);

		return $labels[ $status ]
			?? __( 'Pending', 'wholesale-ordering' );
	}

	/**
	 * Format application timestamp.
	 *
	 * @param string $timestamp Timestamp.
	 *
	 * @return string
	 */
	private static function format_timestamp(
		string $timestamp
	): string {
		if ( '' === $timestamp ) {
			return '—';
		}

		$time = strtotime( $timestamp );

		if ( false === $time ) {
			return $timestamp;
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$time
		);
	}

	/**
	 * Return the canonical WooCommerce My Account endpoint URL.
	 *
	 * WooCommerce's endpoint URL generator is preferred because it
	 * respects the site's configured My Account page and endpoint
	 * structure.
	 *
	 * @return string
	 */
	private static function endpoint_url(): string {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$url = wc_get_account_endpoint_url(
				self::ENDPOINT
			);

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		/*
		 * Fallback for installations where WooCommerce's helper is not
		 * available yet.
		 */
		$account_page_id = function_exists( 'wc_get_page_id' )
			? wc_get_page_id( 'myaccount' )
			: 0;

		if ( $account_page_id > 0 ) {
			$account_url = get_permalink( $account_page_id );

			if ( is_string( $account_url ) && '' !== $account_url ) {
				return trailingslashit( $account_url )
					. self::ENDPOINT
					. '/';
			}
		}

		return home_url(
			'/my-account/' . self::ENDPOINT . '/'
		);
	}

	/**
	 * Render safe account message.
	 *
	 * @param string $message Message.
	 * @param string $type    Notice type.
	 *
	 * @return void
	 */
	private static function render_message(
		string $message,
		string $type
	): void {
		$class = 'woocommerce-' .
			( 'error' === $type ? 'error' : 'info' );

		echo '<div class="' . esc_attr( $class ) . '"><p>';
		echo esc_html( $message );
		echo '</p></div>';
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}