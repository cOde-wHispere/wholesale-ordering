<?php

namespace WholesaleOrdering\Auth;

use WholesaleOrdering\Applications\ApplicationService;
use WholesaleOrdering\Security\DocumentSecurity;

defined( 'ABSPATH' ) || exit;

/**
 * Customer registration integration.
 *
 * Phase 2 / Phase 7 responsibilities:
 *
 * - extend the WooCommerce registration form with wholesale application
 *   fields;
 * - collect the required customer identity fields;
 * - collect and validate the wholesale business information;
 * - provide a WooCommerce country selector using ISO country codes;
 * - validate the registration/application input;
 * - allow WooCommerce/WordPress to remain responsible for account creation
 *   and password handling;
 * - submit the completed wholesale application through ApplicationService;
 * - validate and securely store optional supporting documents through
 *   DocumentSecurity;
 * - record consent timestamp and plugin-owned consent version;
 * - never grant wholesale privileges during registration.
 *
 * Application lifecycle/state changes remain owned by ApplicationService.
 */
final class Registration {

	/**
	 * Current consent version.
	 *
	 * This is plugin-owned metadata and must not be supplied by the browser.
	 *
	 * @var string
	 */
	private const CONSENT_VERSION = '1.0';

	/**
	 * Maximum supporting-document size in bytes.
	 *
	 * @var int
	 */
	private const MAX_DOCUMENT_SIZE = 5242880;

	/**
	 * Register registration hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return;
		}

		add_action(
			'woocommerce_register_form_tag',
			array( self::class, 'render_form_tag' )
		);

		add_action(
			'woocommerce_register_form',
			array( self::class, 'render_fields' )
		);

		add_filter(
			'woocommerce_registration_errors',
			array( self::class, 'validate_registration' ),
			10,
			3
		);

		add_action(
			'woocommerce_created_customer',
			array( self::class, 'handle_created_customer' ),
			10,
			3
		);
	}

	/**
	 * Add multipart encoding so optional supporting documents reach PHP.
	 *
	 * @return void
	 */
	public static function render_form_tag(): void {
		echo 'enctype="multipart/form-data"';
	}

	/**
	 * Render wholesale registration/application fields.
	 *
	 * WooCommerce remains responsible for the native account fields
	 * username/email/password and account creation. First/last name are
	 * explicitly rendered here because they are required application
	 * identity fields.
	 *
	 * @return void
	 */
	public static function render_fields(): void {
		$country_options = self::get_country_options();
		?>

	<fieldset class="wholesale-ordering-registration">
		<legend>
			<?php
			echo esc_html__(
				'Wholesale Application',
				'wholesale-ordering'
			);
			?>
		</legend>

		<p class="form-row form-row-first">
			<label for="wholesale-first-name">
				<?php
				echo esc_html__(
					'First name',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="text"
				class="input-text"
				name="first_name"
				id="wholesale-first-name"
				value="<?php echo esc_attr( self::posted_value( 'first_name' ) ); ?>"
				required
				autocomplete="given-name"
			/>
		</p>

		<p class="form-row form-row-last">
			<label for="wholesale-last-name">
				<?php
				echo esc_html__(
					'Last name',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="text"
				class="input-text"
				name="last_name"
				id="wholesale-last-name"
				value="<?php echo esc_attr( self::posted_value( 'last_name' ) ); ?>"
				required
				autocomplete="family-name"
			/>
		</p>

		<div class="clear"></div>

		<p class="form-row form-row-first">
			<label for="wholesale-company-name">
				<?php
				echo esc_html__(
					'Company / Trading name',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_company_name"
				id="wholesale-company-name"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_company_name' ) ); ?>"
				required
				autocomplete="organization"
			/>
		</p>

		<p class="form-row form-row-last">
			<label for="wholesale-phone">
				<?php
				echo esc_html__(
					'Business phone',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="tel"
				class="input-text"
				name="wholesale_phone"
				id="wholesale-phone"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_phone' ) ); ?>"
				required
				autocomplete="tel"
			/>
		</p>

		<div class="clear"></div>

		<p class="form-row form-row-wide">
			<label for="wholesale-address-1">
				<?php
				echo esc_html__(
					'Billing address',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_billing_address_1"
				id="wholesale-address-1"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_billing_address_1' ) ); ?>"
				required
				autocomplete="address-line1"
			/>
		</p>

		<p class="form-row form-row-wide">
			<label for="wholesale-address-2">
				<?php
				echo esc_html__(
					'Billing address line 2',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_billing_address_2"
				id="wholesale-address-2"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_billing_address_2' ) ); ?>"
				autocomplete="address-line2"
			/>
		</p>

		<p class="form-row form-row-first">
			<label for="wholesale-city">
				<?php
				echo esc_html__(
					'City',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_billing_city"
				id="wholesale-city"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_billing_city' ) ); ?>"
				required
				autocomplete="address-level2"
			/>
		</p>

		<p class="form-row form-row-last">
			<label for="wholesale-state">
				<?php
				echo esc_html__(
					'State / Region',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_billing_state"
				id="wholesale-state"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_billing_state' ) ); ?>"
				autocomplete="address-level1"
			/>
		</p>

		<div class="clear"></div>

		<p class="form-row form-row-first">
			<label for="wholesale-postcode">
				<?php
				echo esc_html__(
					'Postcode',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_billing_postcode"
				id="wholesale-postcode"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_billing_postcode' ) ); ?>"
				autocomplete="postal-code"
			/>
		</p>

		<p class="form-row form-row-last">
			<label for="wholesale-country">
				<?php
				echo esc_html__(
					'Country',
					'wholesale-ordering'
				);
				?>
				&nbsp;<span class="required">*</span>
			</label>

			<select
				class="select"
				name="wholesale_billing_country"
				id="wholesale-country"
				required
				autocomplete="country"
			>
				<option value="">
					<?php
					echo esc_html__(
						'Select a country',
						'wholesale-ordering'
					);
					?>
				</option>

				<?php foreach ( $country_options as $country_code => $country_name ) : ?>
					<option
						value="<?php echo esc_attr( $country_code ); ?>"
						<?php selected( self::posted_value( 'wholesale_billing_country' ), $country_code ); ?>
					>
						<?php echo esc_html( $country_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<div class="clear"></div>

		<p class="form-row form-row-wide">
			<label for="wholesale-tax-number">
				<?php
				echo esc_html__(
					'VAT / Tax number',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_tax_number"
				id="wholesale-tax-number"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_tax_number' ) ); ?>"
			/>
		</p>

		<p class="form-row form-row-wide">
			<label for="wholesale-business-registration">
				<?php
				echo esc_html__(
					'Business registration number',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_business_registration_number"
				id="wholesale-business-registration"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_business_registration_number' ) ); ?>"
			/>
		</p>

		<p class="form-row form-row-wide">
			<label for="wholesale-business-type">
				<?php
				echo esc_html__(
					'Business type',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="text"
				class="input-text"
				name="wholesale_business_type"
				id="wholesale-business-type"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_business_type' ) ); ?>"
			/>
		</p>

		<p class="form-row form-row-wide">
			<label for="wholesale-website">
				<?php
				echo esc_html__(
					'Website / social profile',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="url"
				class="input-text"
				name="wholesale_website"
				id="wholesale-website"
				value="<?php echo esc_attr( self::posted_value( 'wholesale_website' ) ); ?>"
				autocomplete="url"
			/>
		</p>

		<p class="form-row form-row-wide">
			<label for="wholesale-supporting-document">
				<?php
				echo esc_html__(
					'Supporting document (optional)',
					'wholesale-ordering'
				);
				?>
			</label>

			<input
				type="file"
				class="input-text"
				name="wholesale_supporting_document"
				id="wholesale-supporting-document"
				accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
			/>

			<small>
				<?php
				echo esc_html__(
					'PDF, JPG or PNG; maximum 5 MB.',
					'wholesale-ordering'
				);
				?>
			</small>
		</p>

		<p class="form-row form-row-wide">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
				<input
					type="checkbox"
					class="woocommerce-form__input woocommerce-form__input-checkbox"
					name="wholesale_consent"
					value="1"
					<?php checked( self::posted_value( 'wholesale_consent' ), '1' ); ?>
					required
				/>

				<span>
					<?php
					echo esc_html__(
						'I consent to the processing of my information for this wholesale application.',
						'wholesale-ordering'
					);
					?>
					&nbsp;<span class="required">*</span>
				</span>
			</label>
		</p>
	</fieldset>

	<?php
	}

	/**
	 * Validate wholesale registration/application input.
	 *
	 * Native WooCommerce validation remains responsible for the account
	 * email/password/username validation. This layer validates the additional
	 * wholesale application data.
	 *
	 * Consent version is generated by the application and never trusted from
	 * browser input.
	 *
	 * @param \WP_Error $errors   Registration errors.
	 * @param string    $username Submitted username.
	 * @param string    $email    Submitted email.
	 *
	 * @return \WP_Error
	 */
	public static function validate_registration(
		$errors,
		string $username,
		string $email
	) {
		if ( ! $errors instanceof \WP_Error ) {
			$errors = new \WP_Error();
		}

		if (
			isset( $_FILES['wholesale_supporting_document'] )
			&& is_array( $_FILES['wholesale_supporting_document'] )
		) {
			$upload_validation = DocumentSecurity::validate_upload(
				$_FILES['wholesale_supporting_document']
			);

			if ( is_wp_error( $upload_validation ) ) {
				$errors->add(
					'wholesale_supporting_document',
					$upload_validation->get_error_message()
				);
			}
		}

		$data = self::build_application_data(
			$email
		);

		/*
		 * ApplicationService/ApplicationValidator remain the authoritative
		 * validation boundary for application fields.
		 */
		$validator = new \WholesaleOrdering\Applications\ApplicationValidator();

		$validation_errors = $validator->validate_submission(
			$data
		);

		foreach ( $validation_errors as $field => $message ) {
			$errors->add(
				'wholesale_' . sanitize_key( $field ),
				$message
			);
		}

		return $errors;
	}

	/**
	 * Submit the wholesale application after WooCommerce creates the account.
	 *
	 * Account creation has already succeeded at this point. The service is
	 * responsible for creating/updating the application and setting the
	 * wholesale status to pending.
	 *
	 * @param int   $customer_id         Customer user ID.
	 * @param array $new_customer_data   WooCommerce customer data.
	 * @param bool  $password_generated  Whether password was generated.
	 *
	 * @return void
	 */
	public static function handle_created_customer(
		$customer_id,
		$new_customer_data = array(),
		$password_generated = false
	): void {
		$customer_id = absint( $customer_id );

		if ( $customer_id <= 0 ) {
			return;
		}

		$user = get_user_by(
			'id',
			$customer_id
		);

		if ( ! $user ) {
			return;
		}

		$data = self::build_application_data(
			$user->user_email
		);

		/*
		 * Store supporting documents only after validation and through the
		 * dedicated security boundary.
		 */
		if (
			isset( $_FILES['wholesale_supporting_document'] )
			&& is_array( $_FILES['wholesale_supporting_document'] )
		) {
			$document_id = DocumentSecurity::store_upload(
				$customer_id,
				$_FILES['wholesale_supporting_document']
			);

			if ( is_wp_error( $document_id ) ) {
				do_action(
					'wholesale_ordering_registration_document_failed',
					$customer_id,
					$document_id
				);
			} elseif ( $document_id > 0 ) {
				$data['supporting_document_id'] = (int) $document_id;
			}
		}

		/*
		 * Keep the WordPress account's canonical identity.
		 *
		 * ApplicationService deliberately owns the canonical-email invariant.
		 */
		$result = ( new ApplicationService() )->submit(
			$customer_id,
			$data
		);

		/*
		 * Do not silently convert a failed application submission into
		 * wholesale access. Registration has created the account, but only
		 * ApplicationService may establish application state.
		 */
		if ( is_wp_error( $result ) ) {
			do_action(
				'wholesale_ordering_registration_application_failed',
				$customer_id,
				$result
			);

			return;
		}

		do_action(
			'wholesale_ordering_registration_application_submitted',
			$customer_id
		);
	}

	/**
	 * Build application data from registration POST data.
	 *
	 * Consent metadata is application-generated:
	 *
	 * - consent       comes from the user's submitted checkbox;
	 * - consent_at    is generated server-side;
	 * - consent_version is generated from the plugin's current version.
	 *
	 * The browser cannot choose the consent version or timestamp.
	 *
	 * @param string $email Account email.
	 *
	 * @return array<string,mixed>
	 */
	private static function build_application_data(
		string $email
	): array {
		$first_name = self::posted_value( 'first_name' );
		$last_name  = self::posted_value( 'last_name' );

		$country = strtoupper(
			self::posted_value( 'wholesale_billing_country' )
		);

		$consent = self::posted_value(
			'wholesale_consent'
		);

		$consent_at = '';

		if ( '1' === $consent ) {
			$consent_at = current_time(
				'mysql',
				true
			);
		}

		$billing_address = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'company'    => self::posted_value(
				'wholesale_company_name'
			),
			'address_1'  => self::posted_value(
				'wholesale_billing_address_1'
			),
			'address_2'  => self::posted_value(
				'wholesale_billing_address_2'
			),
			'city'       => self::posted_value(
				'wholesale_billing_city'
			),
			'state'      => self::posted_value(
				'wholesale_billing_state'
			),
			'postcode'   => self::posted_value(
				'wholesale_billing_postcode'
			),
			'country'    => $country,
		);

		return array(
			'first_name' => $first_name,

			'last_name' => $last_name,

			'email' => sanitize_email(
				$email
			),

			'company_name' => self::posted_value(
				'wholesale_company_name'
			),

			'phone' => self::posted_value(
				'wholesale_phone'
			),

			'billing_address' => $billing_address,

			'delivery_address' => array(),

			'tax_number' => self::posted_value(
				'wholesale_tax_number'
			),

			'business_registration_number' => self::posted_value(
				'wholesale_business_registration_number'
			),

			'business_type' => self::posted_value(
				'wholesale_business_type'
			),

			'website' => self::posted_value(
				'wholesale_website'
			),

			'consent' => $consent,

			'consent_at' => $consent_at,

			'consent_version' => self::CONSENT_VERSION,
		);
	}

	/**
	 * Return WooCommerce country options.
	 *
	 * The application stores the ISO country code, while the form displays
	 * the human-readable WooCommerce country name.
	 *
	 * @return array<string,string>
	 */
	private static function get_country_options(): array {
		if (
			! function_exists( 'WC' )
			|| ! WC()
			|| ! isset( WC()->countries )
		) {
			return array();
		}

		$countries = WC()->countries->get_countries();

		if ( ! is_array( $countries ) ) {
			return array();
		}

		$options = array();

		foreach ( $countries as $country_code => $country_name ) {
			$country_code = strtoupper(
				sanitize_key( $country_code )
			);

			if ( '' === $country_code ) {
				continue;
			}

			$options[ $country_code ] = (string) $country_name;
		}

		return $options;
	}

	/**
	 * Safely retrieve a submitted registration value.
	 *
	 * @param string $key POST field.
	 *
	 * @return string
	 */
	private static function posted_value(
		string $key
	): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		if ( is_array( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field(
			wp_unslash(
				$_POST[ $key ]
			)
		);
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}
}