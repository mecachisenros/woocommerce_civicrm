<?php
/**
 * Woocommerce CiviCRM Manger class.
 *
 * @since 2.0
 */
class Woocommerce_CiviCRM_Manager {

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks() {

		add_action( 'init', [ $this, 'check_utm' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'action_order' ], 10, 3 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'update_order_status' ], 99, 3 );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'order_data_after_order_details' ], 30 );
		add_action( 'woocommerce_new_order', [ $this, 'save_order' ], 10 );
	}

	/**
	 * Return the order number.
	 *
	 * @param int $post_id The post id.
	 * @since 2.2
	 * @return string $invoice_id The invoice id.
	 */
	public function get_invoice_id( $post_id ) {
		$invoice_no = get_post_meta( $post_id, '_order_number', true );
		$invoice_id = ! empty( $invoice_no ) ? $invoice_no : $post_id . '_woocommerce';
		return $invoice_id;
	}

	/**
	 * Action called when a post is saved.
	 *
	 * @param int $order_id The order id.
	 *
	 * @since 2.2
	 */
	public function save_order( $order_id ) {
		// Add the campaign ID to order.
		$current_campaign_id = get_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', true );
		$new_campaign_id = filter_input( INPUT_POST, 'order_civicrmcampaign', FILTER_VALIDATE_INT );
		if ( false !== $new_campaign_id && $new_campaign_id !== $current_campaign_id ) {
			$this->update_campaign( $order_id, $current_campaign_id, $new_campaign_id );
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $new_campaign_id ) );
		}

		// Add the source to order.
		$current_civicrmsource = get_post_meta( $order_id, '_order_source', true );
		$new_civicrmsource = filter_input( INPUT_POST, 'order_civicrmsource', FILTER_SANITIZE_STRING );
		if ( false !== $new_civicrmsource && $new_civicrmsource !== $current_civicrmsource ) {
			$this->update_source( $order_id, $new_civicrmsource );
			update_post_meta( $order_id, '_order_source', esc_attr( $new_civicrmsource ) );
		}
		if (
			wp_verify_nonce( filter_input( INPUT_POST, 'woocommerce_civicrm_order_new', FILTER_SANITIZE_STRING ), 'woocommerce_civicrm_order_new' )
			|| ( filter_input( INPUT_POST, 'post_ID', FILTER_VALIDATE_INT ) === null && get_post_meta( $order_id, '_pos', true ) )
		) {
			$this->action_order( $order_id, null, new WC_Order( $order_id ) );
		}

	}

	/**
	 * Action called when order is created in Woocommerce.
	 *
	 * @since 2.0
	 * @param int $order_id The order id.
	 * @param array $posted_data The posted data.
	 * @param object $order The order object.
	 * @return int|void $oder_id The order id.
	 */
	public function action_order( $order_id, $posted_data, $order ) {

		$cid = WCI()->helper->civicrm_get_cid( $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'woocommerce-civicrm' ) );
			return;
		}

		$cid = $this->add_update_contact( $cid, $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be found or created', 'woocommerce-civicrm' ) );
			return;
		}

		$source = $this->generate_source( $order );
		$this->update_source( $order_id, $source );
		update_post_meta( $order_id, '_order_source', $source );

		$this->utm_to_order( $order->get_id() );
		// Add the contribution record.
		$this->add_contribution( $cid, $order );
		do_action( 'woocommerce_civicrm_action_order', $order, $cid );

		return $order_id;

	}

	/**
	 * Update Order status.
	 *
	 * @since 2.0
	 * @param int $order_id The order id.
	 * @param string $old_status The old status.
	 * @param string $new_status The new status.
	 */
	public function update_order_status( $order_id, $old_status, $new_status ) {

		$order = new WC_Order( $order_id );

		$cid = WCI()->helper->civicrm_get_cid( $order );
		if ( false === $cid ) {
			$order->add_order_note( __( 'CiviCRM Contact could not be fetched', 'woocommerce-civicrm' ) );
			return;
		}

		$params = [
			'invoice_id' => $this->get_invoice_id( $order_id ),
			'return' => [ 'id', 'financial_type_id', 'receive_date', 'total_amount', 'contact_id' ],
		];

		try {

			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API.
			 */
			$contribution = civicrm_api3(
				'Contribution',
				'getsingle',
				apply_filters( 'woocommerce_civicrm_contribution_update_params', $params )
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Not able to find contribution' );
			return;
		}

		// Update contribution.
		try {

			$params = [
				'contribution_status_id' => $order->is_paid() ? 'Completed' : $this->map_contribution_status( $order->get_status() ),
				'id' => $contribution['id'],
				// 'financial_type_id' => $contribution['financial_type_id'],
				// 'receive_date' => $contribution['receive_date'],
				// 'total_amount' => $contribution['total_amount'],
				// 'contact_id' => $contribution['contact_id'],
			];
			$result = civicrm_api3( 'Contribution', 'create', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to update contribution', 'woocommerce-civicrm' ) );
			return;
		}

	}

	/**
	 * Update Campaign.
	 *
	 * @since 2.0
	 * @param int $order_id The order id.
	 * @param string $old_campaign_id The old campaign.
	 * @param string $new_campaign_id The new campaign.
	 */
	public function update_campaign( $order_id, $old_campaign_id, $new_campaign_id ) {

		$campaign_name = '';
		if ( false !== $new_campaign_id ) {
			$params = [
				'sequential' => 1,
				'return' => [ 'name' ],
				'id' => $new_campaign_id,
				'options' => [ 'limit' => 1 ],
			];
			try {
				$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );
				$campaign_name = isset( $campaigns_result['values'][0]['name'] ) ? $campaigns_result['values'][0]['name'] : '';
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				return false;
			}
		}

		$params = [
			'invoice_id' => $this->get_invoice_id( $order_id ),
			'return' => 'id',
		];

		try {

			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API
			 */
			$contribution = civicrm_api3(
				'Contribution',
				'getsingle',
				apply_filters( 'woocommerce_civicrm_contribution_update_params', $params )
			);

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Not able to find contribution' );
			return;
		}

		// Update contribution.
		try {
			$params = [
				'campaign_id' => $campaign_name,
				'id' => $contribution['id'],
			];
			$result = civicrm_api3( 'Contribution', 'create', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to update contribution', 'woocommerce-civicrm' ) );
			return;
		}

	}

	/**
	 * Update Source.
	 *
	 * @since 2.0
	 * @param int $order_id The order id.
	 * @param string $new_source The new source.
	 */
	public function update_source( $order_id, $new_source ) {

		$order = new WC_Order( $order_id );

		$params = [
			'invoice_id' => $this->get_invoice_id( $order_id ),
			'return' => 'id',
		];

		try {

			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API
			 */
			$contribution = civicrm_api3( 'Contribution', 'getsingle', apply_filters( 'woocommerce_civicrm_contribution_update_params', $params ) );

		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Not able to find contribution' );
			return;
		}

		// Update contribution.
		try {
			$params = [
				'source' => $new_source,
				'id' => $contribution['id'],
			];
			$result = civicrm_api3( 'Contribution', 'create', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to update contribution', 'woocommerce-civicrm' ) );
			return;
		}

	}
	/**
	 * Create or update contact.
	 *
	 * @since 2.0
	 * @param int $cid The contact_id.
	 * @param object $order The order object.
	 * @return int $cid The contact_id.
	 * @filter woocommerce_civicrm_bypass_add_update_contact
	 */
	public function add_update_contact( $cid, $order ) {
		// Allow to bypass contact update.
		if ( true === apply_filters( 'woocommerce_civicrm_bypass_add_update_contact', false, $cid, $order ) ) {
			return $cid;
		}
		$action = 'create';

		$contact = [];
		if ( 0 !== $cid ) {
			try {
				$params = [
					'contact_id' => $cid,
					'return' => [ 'id', 'contact_source', 'first_name', 'last_name', 'contact_type' ],
				];
				$contact = civicrm_api3( 'contact', 'getsingle', $params );
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Not able to find contact', 'woocommerce-civicrm' ) );
				return false;
			}
		} else {
			$contact['contact_type'] = 'Individual';
		}

		// Create contact
		// Prepare array to update contact via civi API.
		$cid = '';
		$email = $order->get_billing_email();
		$fname = $order->get_billing_first_name();
		$lname = $order->get_billing_last_name();

		// Try to get contact Id using dedupe.
		if ( '' !== $fname ) {
			$contact['first_name'] = $fname;
		} else {
			unset( $contact['first_name'] );
		}
		if ( '' !== $lname ) {
			$contact['last_name'] = $lname;
		} else {
			unset( $contact['last_name'] );
		}

		$contact['email'] = $email;
		$dedupe_params = CRM_Dedupe_Finder::formatParams( $contact, $contact['contact_type'] );
		$dedupe_params['check_permission'] = false;
		$ids = CRM_Dedupe_Finder::dupesByParams( $dedupe_params, $contact['contact_type'], 'Unsupervised' );

		if ( $ids ) {
			$cid = $ids['0'];
			$action = 'update';
		}

		// FIXME
		// Why are we setting display_name?
		if ( '' !== trim( "{$fname} {$lname}" ) ) {
			$contact['display_name'] = "{$fname} {$lname}";
		}

		if ( empty( $contact['contact_source'] ) ) {
			$contact['contact_source'] = __( 'Woocommerce purchase', 'woocommerce-civicrm' );
		}

		// Create contact or update existing contact.
		try {
			$result = civicrm_api3( 'Contact', 'create', $contact );
			$cid = $result['id'];

			$contact_url = '<a href="' . get_admin_url() . 'admin.php?page=CiviCRM&q=civicrm/contact/view&reset=1&cid=' . $cid . '">' . __( 'View', 'woocommerce-civicrm' ) . '</a>';

			// Add order note.
			if ( 'update' === $action ) {
				$note = __( 'CiviCRM Contact Updated - ', 'woocommerce-civicrm' ) . $contact_url;
			} else {
				$note = __( 'Created new CiviCRM Contact - ', 'woocommerce-civicrm' ) . $contact_url;
			}
			$order->add_order_note( $note );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to create/update contact', 'woocommerce-civicrm' ) );
			return false;
		}

		try {
			$existing_addresses = civicrm_api3( 'Address', 'get', [ 'contact_id' => $cid ] );
			$existing_addresses = $existing_addresses['values'];
			$existing_phones = civicrm_api3( 'Phone', 'get', [ 'contact_id' => $cid ] );
			$existing_phones = $existing_phones['values'];
			$existing_emails = civicrm_api3( 'Email', 'get', [ 'contact_id' => $cid ] );
			$existing_emails = $existing_emails['values'];
			$address_types = WCI()->helper->mapped_location_types;

			foreach ( $address_types as $address_type => $location_type_id ) {

				// Process Phone.
				$phone_exists = false;
				// 'shipping_phone' does not exist as a Woocommerce field.
				if ( 'shipping' !== $address_type && ! empty( $order->{'get_' . $address_type . '_phone'}() ) ) {
					$phone = [
						'phone_type_id' => 1,
						'location_type_id' => $location_type_id,
						'phone' => $order->{'get_' . $address_type . '_phone'}(),
						'contact_id' => $cid,
					];
					foreach ( $existing_phones as $existing_phone ) {
						if ( isset( $existing_phone['location_type_id'] ) && $existing_phone['location_type_id'] === $location_type_id ) {
							$phone['id'] = $existing_phone['id'];
						}
						if ( $existing_phone['phone'] === $phone['phone'] ) {
							$phone_exists = true;
						}
					}
					if ( ! $phone_exists ) {
						civicrm_api3( 'Phone', 'create', $phone );
						/* translators: %1$s: address type, %2$s: phone */
						$note = sprintf( __( 'Created new CiviCRM Phone of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $phone['phone'] );
						$order->add_order_note( $note );
					}
				}

				// Process Email.
				$email_exists = false;
				// 'shipping_email' does not exist as a Woocommerce field.
				if ( 'shipping' !== $address_type && ! empty( $order->{'get_' . $address_type . '_email'}() ) ) {
					$email = [
						'location_type_id' => $location_type_id,
						'email' => $order->{'get_' . $address_type . '_email'}(),
						'contact_id' => $cid,
					];
					foreach ( $existing_emails as $existing_email ) {
						if ( isset( $existing_email['location_type_id'] ) && $existing_email['location_type_id'] === $location_type_id ) {
							$email['id'] = $existing_email['id'];
						}
						if ( isset( $existing_email['email'] ) && $existing_email['email'] === $email['email'] ) {
							$email_exists = true;
						}
					}
					if ( ! $email_exists ) {
						civicrm_api3( 'Email', 'create', $email );
						/* translators: %1$s: address type, %2$s: email */
						$note = sprintf( __( 'Created new CiviCRM Email of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $email['email'] );
						$order->add_order_note( $note );
					}
				}

				// Process Address.
				$address_exists = false;
				if ( ! empty( $order->{'get_' . $address_type . '_address_1'}() ) && ! empty( $order->{'get_' . $address_type . '_postcode'}() ) ) {

					$country_id = WCI()->helper->get_civi_country_id( $order->{'get_' . $address_type . '_country'}() );
					$address = [
						'location_type_id'       => $location_type_id,
						'city'                   => $order->{'get_' . $address_type . '_city'}(),
						'postal_code'            => $order->{'get_' . $address_type . '_postcode'}(),
						'name'                   => $order->{'get_' . $address_type . '_company'}(),
						'street_address'         => $order->{'get_' . $address_type . '_address_1'}(),
						'supplemental_address_1' => $order->{'get_' . $address_type . '_address_2'}(),
						'country'                => $country_id,
						'state_province_id'      => WCI()->helper->get_civi_state_province_id( $order->{'get_' . $address_type . '_state'}(), $country_id ),
						'contact_id'             => $cid,
					];

					foreach ( $existing_addresses as $existing ) {
						if ( isset( $existing['location_type_id'] ) && $existing['location_type_id'] === $location_type_id ) {
							$address['id'] = $existing['id'];
						} elseif (
							// @TODO Don't create if exact match of another - should
							// we make 'exact match' configurable.
							isset( $existing['street_address'] )
							&& isset( $existing['city'] )
							&& isset( $existing['postal_code'] )
							&& isset( $address['street_address'] )
							&& $existing['street_address'] === $address['street_address']
							&& CRM_Utils_Array::value( 'supplemental_address_1', $existing ) === CRM_Utils_Array::value( 'supplemental_address_1', $address )
							&& $existing['city'] == $address['city']
							&& $existing['postal_code'] === $address['postal_code']
						) {
							$address_exists = true;
						}
					}
					if ( ! $address_exists ) {
						civicrm_api3( 'Address', 'create', $address );
						/* translators: %1$s: address type, %2$s: street address */
						$note = sprintf( __( 'Created new CiviCRM Address of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $address['street_address'] );
						$order->add_order_note( $note );
					}
				}
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to add/update address or phone', 'woocommerce-civicrm' ) );
		}

		return $cid;

	}

	/**
	 * Function to add a contribution record.
	 *
	 * @since 2.0
	 * @param int $cid The contact_id.
	 * @param WC_Order $order The order object.
	 */
	public function add_contribution( $cid, $order ) {

		// Bail if order is 'free' (0 amount) and 0 amount setting is enabled.
		if ( WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_ignore_0_amount_orders', false ) ) && $order->get_total() === 0 ) {
			return false;
		}

		$order_id = $order->get_id();
		$order_date = $order->get_date_paid();

		$order_paid_date = ! empty( $order_date ) ? $order_date->date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );

		$order_id = $order->get_id();
		$txn_id = __( 'Woocommerce Order - ', 'woocommerce-civicrm' ) . $order_id;
		$invoice_id = $this->get_invoice_id( $order_id );
		$this->create_custom_contribution_fields();

		// Ensure number format is Civi compliant.
		$decimal_separator = '.';
		$thousand_separator = '';
		try {
			$civi_decimal_separator = civicrm_api3(
				'Setting',
				'getvalue',
				[
					'sequential' => 1,
					'name' => 'monetaryDecimalPoint',
				]
			);
			$civi_thousand_separator = civicrm_api3(
				'Setting',
				'getvalue',
				[
					'sequential' => 1,
					'name' => 'monetaryThousandSeparator',
				]
			);
			if ( is_string( $civi_decimal_separator ) ) {
				$decimal_separator = $civi_decimal_separator;
			}
			if ( is_string( $civi_thousand_separator ) ) {
				$thousand_separator = $civi_thousand_separator;
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to fetch monetary settings', 'woocommerce-civicrm' ) );
		}

		$sales_tax_raw = $order->get_total_tax();
		$sales_tax = number_format( $sales_tax_raw, 2, $decimal_separator, $thousand_separator );

		$shipping_cost = $order->get_total_shipping();

		if ( ! $shipping_cost ) {
			$shipping_cost = 0;
		}
		$shipping_cost = number_format( $shipping_cost, 2, $decimal_separator, $thousand_separator );

		// @FIXME Landmine. CiviCRM doesn't seem to accept financial values
		// with precision greater than 2 digits after the decimal.
		$rounded_total = round( $order->get_total() * 100 ) / 100;

		// Couldn't figure where Woocommerce stores the subtotal (ie no TAX price)
		// So for now...
		$rounded_subtotal = $rounded_total - $sales_tax_raw;

		$rounded_subtotal = number_format( $rounded_subtotal, 2, $decimal_separator, $thousand_separator );

		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		$default_financial_type_vat_id = get_option( 'woocommerce_civicrm_financial_type_vat_id' ); // Get the VAT Financial type.
		$default_financial_type_shipping_id = get_option( 'woocommerce_civicrm_financial_type_shipping_id' ); // Get the VAT Financial type.

		$woocommerce_civicrm_campaign_id = get_option( 'woocommerce_civicrm_campaign_id' ); // Get the global CiviCRM campaign ID.
		$local_campaign_id = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );
		if ( ! empty( $local_campaign_id ) ) {
			$woocommerce_civicrm_campaign_id = $local_campaign_id; // Use the local CiviCRM campaign ID if possible.
		}

		$items = $order->get_items();

		$payment_instrument = $this->map_payment_instrument( $order->get_payment_method() );
		$source = $this->generate_source( $order );
		$params = [
			'contact_id' => $cid,
			'financial_type_id' => $default_financial_type_id,
			'payment_instrument_id' => $payment_instrument,
			'trxn_id' => $txn_id,
			'invoice_id' => $invoice_id,
			'source' => $source,
			'receive_date' => $order_paid_date,
			'contribution_status_id' => 'Pending',
			'note' => $this->create_detail_string( $items ),
			'campaign_id' => $woocommerce_civicrm_campaign_id,
			'line_items' => [],
		];

		// If the order has VAT (Tax) use VAT Fnancial type.
		if ( 0 !== $sales_tax ) {
			// Need to be set in admin page.
			$params['financial_type_id'] = $default_financial_type_vat_id;
		}

		$default_contribution_amount_data = WCI()->helper->get_default_contribution_price_field_data();

		/**
		 * Add line items to CiviCRM contribution.
		 *
		 * @since 2.2
		 */
		if ( count( $items ) ) {
			$financial_types = [];
			foreach ( $items as $item ) {

				$product = $item->get_product();

				$product_financial_type_id = empty( $product->get_meta( 'woocommerce_civicrm_financial_type_id' ) )
					? get_post_meta( $item['product_id'], '_civicrm_contribution_type', true )
					: $product->get_meta( 'woocommerce_civicrm_financial_type_id' );

				if ( 'exclude' === $product_financial_type_id ) {
					continue;
				}

				if ( empty( $product_financial_type_id ) ) {
					$product_financial_type_id = $default_financial_type_id;
				}
				if ( 0 === $item['qty'] ) {
					$item['qty'] = 1;
				}

				$line_item = [
					'price_field_id' => $default_contribution_amount_data['price_field']['id'],
					'qty' => $item['qty'],
					'line_total' => number_format( $item['line_total'], 2, $decimal_separator, $thousand_separator ),
					'unit_price' => number_format( $item['line_total'] / $item['qty'], 2, $decimal_separator, $thousand_separator ),
					'label' => $item['name'],
					'financial_type_id' => $product_financial_type_id,
				];

				// Get membership type id from product meta.
				$product_membership_type_id = $product->get_meta( 'woocommerce_civicrm_membership_type_id' );

				// FIXME
				// Decide whether we want to override
				// the financial type with the one from
				// the membership type instead of product/default.

				// Add line item membership params if applicable.
				if ( ! empty( $product_membership_type_id ) ) {

					$line_item = array_merge(
						$line_item,
						[
							'entity_table' => 'civicrm_membership',
							'membership_type_id' => $product_membership_type_id,
						]
					);

					$line_item_params = [
						'membership_type_id' => $product_membership_type_id,
						'contact_id' => $cid,
					];

				}

				$params['line_items'][ $item->get_id() ] = isset( $line_item_params )
					? [
						'line_item' => [ $line_item ],
						'params' => $line_item_params,
					]
					: [ 'line_item' => [ $line_item ] ];

				$financial_types[ $product_financial_type_id ] = $product_financial_type_id;

			}

			if ( 1 === count( $financial_types ) ) {
				$params['financial_type_id'] = $product_financial_type_id;
			}
		}

		// Line item for shipping,
		// shouldn't it be added to it's corresponding
		// product/line_item (i.e. order an order can have
		// both shippable and downloadable products)?
		if ( floatval( $shipping_cost ) > 0 ) {
			$params['line_items'][0] = [
				'line_item' => [
					[
						'price_field_id' => $default_contribution_amount_data['price_field']['id'],
						'qty' => 1,
						'line_total' => $shipping_cost,
						'unit_price' => $shipping_cost,
						'label' => 'Shipping',
						'financial_type_id' => $default_financial_type_shipping_id,
					]
				],
			];
		}

		// Flush UTM cookies.
		$this->delete_utm_cookies();
		try {
			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API
			 */
			$contribution = civicrm_api3( 'Order', 'create', apply_filters( 'woocommerce_civicrm_contribution_create_params', $params, $order ) );
			if ( isset( $contribution['id'] ) && $contribution['id'] ) {
				// Adds order note in reference to the created contribution.
				$order->add_order_note(
					sprintf(
						/* translators: %s: the contact summary page url */
						__( 'Contribution %s has been created in CiviCRM', 'woocommerce-civicrm' ),
						'<a href="'
						. add_query_arg(
							[
								'page' => 'CiviCRM',
								'q' => 'civicrm/contact/view/contribution',
								'reset' => '1',
								'id' => $contribution['id'],
								'cid' => $cid,
								'action' => 'view',
								'context' => 'dashboard',
								'selectedChild' => 'contribute',
							],
							admin_url( 'admin.php' )
						)
						. '">' . $contribution['id'] . '</a>'
					)
				);
				update_post_meta( $order_id, '_woocommerce_civicrm_contribution_id', $contribution['id'] );
				return $contribution;
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			// Log the error, but continue.
			CRM_Core_Error::debug_log_message( __( 'Not able to add contribution', 'woocommerce-civicrm' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		return false;
	}

	/**
	 * Maps Woocommerce payment method to CiviCRM payment instrument.
	 *
	 * @since 2.0
	 * @param string $payment_method Woocommerce payment method.
	 * @return int $id CiviCRM payment processor ID.
	 */
	public function map_payment_instrument( $payment_method ) {
		$map = [
			'paypal' => 1,
			'cod' => 3,
			'cheque' => 4,
			'bacs' => 5,
		];

		if ( array_key_exists( $payment_method, $map ) ) {
			$id = $map[ $payment_method ];
		} else {
			// Another Woocommerce payment method - good chance this is credit.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Create string to insert for purchase activity details.
	 *
	 * @since 2.0
	 * @param object $items The order object.
	 * @return string $str
	 */
	public function create_detail_string( $items ) {

		$str = '';
		$n = 1;
		foreach ( $items as $item ) {
			if ( $n > 1 ) {
				$str .= ', ';
			}
			$str .= $item['name'] . ' x ' . $item['quantity'];
			$n++;
		}

		return $str;

	}

	/**
	 * Maps WooCommerce order status to CiviCRM contribution status.
	 *
	 * @since 2.0
	 * @param string $order_status WooCommerce order status.
	 * @return int $id CiviCRM Contribution status.
	 */
	public function map_contribution_status( $order_status ) {

		$map = [
			'wc-completed'  => 1,
			'wc-pending'    => 2,
			'wc-cancelled'  => 3,
			'wc-failed'     => 4,
			'wc-processing' => 5,
			'wc-on-hold'    => 5,
			'wc-refunded'   => 7,
			'completed'  => 1,
			'pending'    => 2,
			'cancelled'  => 3,
			'failed'     => 4,
			'processing' => 5,
			'on-hold'    => 5,
			'refunded'   => 7,
		];

		if ( array_key_exists( $order_status, $map ) ) {
			$id = $map[ $order_status ];
		} else {
			// Oh no.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Generates a string to define contribution source.
	 *
	 * @since 2.2
	 * @param object $order The order object.
	 */
	public function generate_source( $order ) {
		// Default is the order Type
		// Until 2.2, contribution source was exactly the same as contribution note.
		$source = '';

		if ( get_post_meta( $order->get_id(), '_order_source', true ) === 'pos' ) {
			$source = 'pos';
		} else {

			$cookie = wp_unslash( $_COOKIE );
			// Checks if users comes from a campaign.
			if ( isset( $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) {
				$source = esc_attr( $cookie[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] );
			}
			// Append medium UTM if present.
			if ( isset( $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) {
				$source .= ' / ' . esc_attr( $cookie[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] );
			}
		}

		$order_source = get_post_meta( $order->get_id(), '_order_source', true );
		if ( false === $order_source ) {
			$order_source = '';
		}

		if ( '' === $source ) {
			$source = __( 'shop', 'woocommerce-civicrm' );
		}

		return $source;
	}

	/**
	 * Function to create sales tax and shipping cost custom fields for contribution.
	 *
	 * @since 2.0
	 */
	public function create_custom_contribution_fields() {
		$group_id = get_option( 'woocommerce_civicrm_contribution_group_id', false );
		if ( false !== $group_id ) {
			return;
		}

		// Let's check if the custom group already exists in CiviCRM.
		$params = [
			'name' => 'Woocommerce_purchases',
			'return' => [ 'id' ],
		];
		try {
			$custom_group = civicrm_api3( 'CustomGroup', 'getsingle', $params );
			if ( isset( $custom_group['id'] ) && $custom_group['id'] && is_numeric( $custom_group['id'] ) ) {
				$group_id = $custom_group['id'];
				update_option( 'woocommerce_civicrm_contribution_group_id', $group_id );
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to get custom group', 'woocommerce-civicrm' ) );
		}
		if ( ! $group_id ) {
			// First we need to check if the VAT and Shipping custom fields have
			// already been created.
			$params = [
				'title'            => 'Woocommerce Purchases',
				'name'             => 'Woocommerce_purchases',
				'extends'          => [ 'Contribution' ],
				'weight'           => 1,
				'collapse_display' => 0,
				'is_active'        => 1,
			];

			try {
				$custom_group = civicrm_api3( 'CustomGroup', 'create', $params );
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Not able to create custom group', 'woocommerce-civicrm' ) );
			}
			update_option( 'woocommerce_civicrm_contribution_group_id', $custom_group['id'] );
		}

		$params = [
			'label' => 'Sales tax',
			'return' => [ 'id' ],
		];
		try {
			$custom_field = civicrm_api3( 'CustomField', 'getsingle', $params );
			if ( isset( $custom_field['id'] ) && $custom_field['id'] && is_numeric( $custom_field['id'] ) ) {
				$tax_field = $custom_field['id'];
				update_option( 'woocommerce_civicrm_sales_tax_field_id', $tax_field );
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to get custom field', 'woocommerce-civicrm' ) );
		}
		if ( ! $tax_field ) {
			$params = [
				'custom_group_id' => $custom_group['id'],
				'label'           => 'Sales tax',
				'html_type'       => 'Text',
				'data_type'       => 'String',
				'weight'          => 1,
				'is_required'     => 0,
				'is_searchable'   => 0,
				'is_active'       => 1,
			];
			$tax_field = civicrm_api3( 'CustomField', 'create', $params );
			update_option( 'woocommerce_civicrm_sales_tax_field_id', $tax_field['id'] );
		}

		$params = [
			'label' => 'Shipping Cost',
			'return' => [ 'id' ],
		];
		try {
			$custom_field = civicrm_api3( 'CustomField', 'getSingle', $params );
			if ( isset( $custom_field['id'] ) && $custom_field['id'] && is_numeric( $custom_field['id'] ) ) {
				$shipping_field = $custom_field['id'];
				update_option( 'woocommerce_civicrm_shipping_cost_field_id', $shipping_field );
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to get custom field', 'woocommerce-civicrm' ) );
		}
		if ( ! $shipping_field ) {
			$params = [
				'custom_group_id' => $custom_group['id'],
				'label'           => 'Shipping Cost',
				'html_type'       => 'Text',
				'data_type'       => 'String',
				'weight'          => 2,
				'is_required'     => 0,
				'is_searchable'   => 0,
				'is_active'       => 1,
			];
			$shipping_field = civicrm_api3( 'CustomField', 'create', $params );
			update_option( 'woocommerce_civicrm_shipping_cost_field_id', $shipping_field['id'] );
		}
	}

	/**
	 * Adds a custom field to set a campaign.
	 *
	 * @since 2.2
	 * @param object $order Woocommerce order.
	 */
	public function order_data_after_order_details( $order ) {
		if ( $order->get_status() === 'auto-draft' ) {
			wp_nonce_field( 'woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new' );
		} else {
			wp_nonce_field( 'woocommerce_civicrm_order_edit', 'woocommerce_civicrm_order_edit' );
		}
		wp_enqueue_script( 'wccivi_admin_order', WOOCOMMERCE_CIVICRM_URL . 'js/admin_order.js', 'jquery', '1.0', true );
		$order_campaign = get_post_meta( $order->get_id(), '_woocommerce_civicrm_campaign_id', true );

		if ( '' === $order_campaign || false === $order_campaign ) {// if there is no campaign selected, select the default one (set up in WC -> settings -> CiviCRM).
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' ); // Get the global CiviCRM campaign ID.
		}
		$campaign_array = apply_filters( 'woocommerce_civicrm_campaign_list', 'campaigns' );
		if ( 'campaigns' === $campaign_array ) {
			$campaign_list = WCI()->helper->campaigns;
		} else {
			$campaign_list = WCI()->helper->all_campaigns;
		}
		?>
		<p class="form-field form-field-wide wc-civicrmcampaign">
			<label for="order_civicrmcampaign"><?php esc_html_e( 'CiviCRM Campaign', 'woocommerce-civicrm' ); ?></label>
			<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="<?php esc_attr( __( 'CiviCRM Campaign', 'woocommerce-civicrm' ) ); ?>">
				<option value=""></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
				<option value="<?php esc_attr( $campaign_id ); ?>" <?php selected( $campaign_id, $order_campaign, true ); ?>><?php echo esc_attr( $campaign_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php

		$order_source = get_post_meta( $order->get_id(), '_order_source', true );
		if ( false === $order_source ) {
			$order_source = '';
		}

		?>
		<p class="form-field form-field-wide wc-civicrmsource">
			<label for="order_civicrmsource"><?php esc_html_e( 'CiviCRM Source', 'woocommerce-civicrm' ); ?></label>
			<input type='text' list="sources" id="order_civicrmsource" name="order_civicrmsource" data-placeholder="<?php esc_attr_e( 'CiviCRM Source', 'woocommerce-civicrm' ); ?>" value="<?php echo esc_attr( $order_source ); ?>">
			<datalist id="sources">

			<?php
			global $wpdb;
			// FIXME
			// What is this, why use wpdb?
			// Interrogation de la base de données.
			$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_source'" );
			// Parcours des resultats obtenus.
			if ( count( $results ) > 0 ) {
				foreach ( $results as $meta ) {
					echo esc_html( '<option value="' . $meta->meta_value . '">' );
				}
			}
			?>
			</datalist>

		</p>
		<?php
		$cid = WCI()->helper->civicrm_get_cid( $order );
		if ( $cid ) {
			?>
			<div class="form-field form-field-wide wc-civicrmsource">
				<h3>
				<?php
				echo sprintf(
					/* translators: %s: contact summary screen link */
					__( 'View %s in CiviCRM', 'woocommerce-civicrm' ),
					'<a href="'
					. add_query_arg(
						[
							'page' => 'CiviCRM',
							'q' => 'civicrm/contact/view/',
							'cid' => $cid,
							'action' => 'view',
							'context' => 'dashboard',
						],
						admin_url( 'admin.php' )
					)
					. '" target="_blank"> '
					. _x( 'Contact', 'in: View Contact in CiviCRM', 'woocommerce-civicrm' )
					. '</a>'
				);
				?>
				</h3>
			</div>
			<?php
		}

	}

	/**
	 * Action to check if UTM parameters are passed in URL (front only).
	 *
	 * @since 2.2
	 */
	public function check_utm() {
		if ( is_admin() ) {
			return;
		}

		if ( isset( $_GET['utm_campaign'] ) || isset( $_GET['utm_source'] ) || isset( $_GET['utm_medium'] ) ) {
			$this->save_utm_cookies();
		}
	}

	/**
	 * Save UTM parameters to cookies.
	 *
	 * @since 2.2
	 */
	private function save_utm_cookies() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$expire = apply_filters( 'woocommerce_civicrm_utm_cookie_expire', 0 );
		$secure = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );
		$campaign = filter_input( INPUT_GET, 'utm_campaign' );
		if ( false !== $campaign ) {
			try {
				$params = [
					'sequential' => 1,
					'return' => ['id'],
					'name' => esc_attr( $campaign ),
				];
				$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );
				if ( $campaigns_result && isset( $campaigns_result['values'][0]['id'] ) ) {
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, $campaigns_result['values'][0]['id'], $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
				} else {
					// Remove cookie if campaign is invalid.
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS );
				}
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				return false;
			}
		}
		$source = filter_input( INPUT_GET, 'utm_source' );
		if ( false !== $source ) {
			setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, esc_attr( $source ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}
		$medium = filter_input( INPUT_GET, 'utm_medium' );
		if ( false !== $medium ) {
			setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, esc_attr( $medium ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}
	}

	/**
	 * Saves UTM cookie to post meta
	 *
	 * @param int $order_id The order ID.
	 * @since 2.2
	 */
	private function utm_to_order( $order_id ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$cookie = wp_unslash( $_COOKIE );
		if ( isset( $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) && $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) {
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $cookie[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) );
			setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		} else {
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' ); // Get the global CiviCRM campaign ID.
			update_post_meta( $order_id, '_woocommerce_civicrm_campaign_id', $order_campaign );
		}
	}

	/**
	 * Delete UTM cookies
	 *
	 * @since 2.2
	 */
	private function delete_utm_cookies() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		// Remove any existing cookies.
		$past = time() - YEAR_IN_SECONDS;
		setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
	}

}
