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
	public function __construct(){

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks(){

		add_action('init', array( $this, 'check_utm'));
		add_action('woocommerce_checkout_order_processed', array( $this, 'action_order' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'update_order_status' ), 99, 3 );
		add_action('woocommerce_admin_order_data_after_order_details', array( $this, 'order_data_after_order_details'), 30);
		add_action('save_post', array( $this, 'save_post'), 10);

	}

	/**
	 * Action called when a post is saved
	 *
	 * @param int $post_id
	 * @since 2.2
	 */
	public function save_post( $post_id ){
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return;

		if (get_post_type( $post_id ) !== 'shop_order' )
			return;

		// Add the campaign ID to order

		$current_campaign_id = get_post_meta( $post_id, '_woocommerce_civicrm_campaign_id', true);
		if((false !== $new_campaign_id = filter_input(INPUT_POST, 'order_civicrmcampaign', FILTER_VALIDATE_INT)) && $new_campaign_id != $current_campaign_id ){

			var_dump($new_campaign_id);
			die();
			$this->update_campaign($post_id,$current_campaign_id,$new_campaign_id);
			update_post_meta($post_id, '_woocommerce_civicrm_campaign_id', esc_attr( $new_campaign_id ));
		}
		// In dashbord context, woocommerce_checkout_order_processed is not called after a creation
		if (wp_verify_nonce(\filter_input(INPUT_POST, 'woocommerce_civicrm_order_new', FILTER_SANITIZE_STRING), 'woocommerce_civicrm_order_new')) {
			$this->action_order( $post_id );
		}

	}

	/**
	 * Action called when order is created in Woocommerce.
	 *
	 * @since 2.0
	 * @param int $order_id The order id
	 */
	 public function action_order( $order_id, $posted_data, $order){


		$cid = WCI()->helper->civicrm_get_cid( $order );
		if ( $cid === FALSE ) {
				$order->add_order_note(  __( 'CiviCRM Contact could not be fetched', 'woocommerce-civicrm' ) );
				return;
		}
		$cid = $this->add_update_contact( $cid, $order );

		if ( $cid === FALSE ) {
				$order->add_order_note(  __( 'CiviCRM Contact could not be found or created', 'woocommerce-civicrm' ) );
				return;
		}

		// Add the contribution record.
		$this->add_contribution( $cid, $order );

		return $order_id;

	}

	/**
	 * Update Order status.
	 *
	 * @since 2.0
	 * @param int $order_id The order id
	 * @param string $old_status The old status
	 * @param string $new_status The new status
	 */
	public function update_order_status( $order_id, $old_status, $new_status ){

		$order = new WC_Order( $order_id );

		$params = array(
			'invoice_id' => $order_id . '_woocommerce',
			'return' => 'id'
		);

		try {

			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API
			 */
			$contribution = civicrm_api3( 'Contribution', 'getsingle', apply_filters( 'woocommerce_civicrm_contribution_update_params', $params ) );
		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Not able to find contribution' );
			return;
		}

		// Update contribution
		try {
			$params = array(
				'contribution_status_id' => $this->map_contribution_status( $order->get_status() ),
				'id' => $contribution['id'],
			);
			$result = civicrm_api3( 'Contribution', 'create', $params );
		} catch ( Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to update contribution', 'woocommerce-civicrm' ) );
			return;
		}

	}

	/**
	 * Update Campaign.
	 *
	 * @since 2.0
	 * @param int $order_id The order id
	 * @param string $old_campaign_id The old campaign
	 * @param string $new_campaign_id The new campaign
	 */
	public function update_campaign( $order_id, $old_campaign_id, $new_campaign_id ){

		$order = new WC_Order( $order_id );

		$campaign_name = '';
		if($new_campaign_id!==false){
			$params = array(
				'sequential' => 1,
				'return' => array("name"),
				'id' => $new_campaign_id,
				'options' => array('limit' => 1),
			);
			try{
				$campaignsResult = civicrm_api3( 'Campaign', 'get', $params );
				$campaign_name = isset($campaignsResult['values'][0]['name']) ? $campaignsResult['values'][0]['name'] : '';
			} catch ( CiviCRM_API3_Exception $e ){
				CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				return FALSE;
			}
		}

		$params = array(
			'invoice_id' => $order_id . '_woocommerce',
			'return' => 'id'
		);

		try {

			/**
			 * Filter Contribution params before calling the Civi's API.
			 *
			 * @since 2.0
			 * @param array $params The params to be passsed to the API
			 */
			$contribution = civicrm_api3( 'Contribution', 'getsingle', apply_filters( 'woocommerce_civicrm_contribution_update_params', $params ) );
		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( 'Not able to find contribution' );
			return;
		}

		// Update contribution
		try {
			$params = array(
				'campaign_id' => $campaign_name,
				'id' => $contribution['id'],
			);
			$result = civicrm_api3( 'Contribution', 'create', $params );
			var_dump($result);
			die();
		} catch ( Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to update contribution', 'woocommerce-civicrm' ) );
			return;
		}

	}

	/**
	 * Create or update contact.
	 *
	 * @since 2.0
	 * @param int $cid The contact_id
	 * @param object $order The order object
	 * @return int $cid The contact_id
	 */
	public function add_update_contact( $cid, $order ){

		$action = 'create';

		$contact = array();
		if( $cid != 0 ){
			try {
				$params = array(
					'contact_id' => $cid,
					'return' => array( 'id', 'source', 'first_name', 'last_name' ),
				);
				$contact = civicrm_api3( 'contact', 'getsingle', $params );
			} catch ( CiviCRM_API3_Exception $e ){
				CRM_Core_Error::debug_log_message( __( 'Not able to find contact', 'woocommerce-civicrm' ) );
				return FALSE;
			}
		}

		// Create contact
		// Prepare array to update contact via civi API.
		$cid = '';
		$email = $order->get_billing_email();
		$fname = $order->get_billing_first_name();
		$lname = $order->get_billing_last_name();

		// Try to get contact Id using dedupe
		$contact['first_name'] = $fname;
		$contact['last_name'] = $lname;
		$contact['email'] = $email;
		$dedupeParams = CRM_Dedupe_Finder::formatParams( $contact, 'Individual' );
		$dedupeParams['check_permission'] = FALSE;
		$ids = CRM_Dedupe_Finder::dupesByParams( $dedupeParams, 'Individual', 'Unsupervised' );

		if( $ids ){
			$cid = $ids['0'];
			$action = 'update';
		}

		$contact['display_name'] = "{$fname} {$lname}";
		if( ! $cid ){
			$contact['contact_type'] = 'Individual';
		}

		if( isset( $contact['contact_subtype'] ) ){
			unset( $contact['contact_subtype'] );
		}
		if( empty( $contact['source'] ) ){
			$contact['source'] = __( 'Woocommerce purchase', 'woocommerce-civicrm' );
		}

		// Create contact or update existing contact.
		try {
			$result = civicrm_api3( 'Contact', 'create', $contact );
			$cid = $result['id'];
			$name = trim( $contact['display_name'] );
			$name = ! empty( $name ) ? $contact['display_name'] : $cid;
			$contact_url = "<a href='" . get_admin_url() . "admin.php?page=CiviCRM&q=civicrm/contact/view&reset=1&cid=" . $cid . "'>" . __( 'View', 'woocommerce-civicrm' ) . "</a>";

			// Add order note
			if( $action == 'update' ){
				$note = __( 'CiviCRM Contact Updated - ', 'woocommerce-civicrm' ) . $contact_url;
			} else {
				$note = __( 'Created new CiviCRM Contact - ', 'woocommerce-civicrm' ) . $contact_url;
			}
			$order->add_order_note( $note );
		} catch ( CiviCRM_API3_Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to create/update contact', 'woocommerce-civicrm' ) );
			return FALSE;
		}

		try {
			$existing_addresses = civicrm_api3( 'Address', 'get', array( 'contact_id' => $cid ) );
			$existing_addresses = $existing_addresses['values'];
			$existing_phones = civicrm_api3( 'Phone', 'get', array( 'contact_id' => $cid ) );
			$existing_phones = $existing_phones['values'];
			$existing_emails = civicrm_api3( 'Email', 'get', array( 'contact_id' => $cid ) );
			$existing_emails = $existing_emails['values'];
			$address_types = WCI()->helper->mapped_location_types;

			foreach( $address_types as $address_type => $location_type_id ){

				// Process Phone
				$phone_exists = FALSE;
				// 'shipping_phone' does not exist as a Woocommerce field
				if( $address_type != 'shipping' && ! empty( $order->{'get_' . $address_type . '_phone'}() ) ){
					$phone = array(
						'phone_type_id' => 1,
						'location_type_id' => $location_type_id,
						'phone' => $order->{'get_' . $address_type . '_phone'}(),
						'contact_id' => $cid,
					);
					foreach( $existing_phones as $existing_phone ){
						if( isset($existing_phone['location_type_id']) && $existing_phone['location_type_id'] == $location_type_id ){
							$phone['id'] = $existing_phone['id'];
						}
						if( $existing_phone['phone'] == $phone['phone'] ){
							$phone_exists = TRUE;
						}
					}
					if( ! $phone_exists ){
						civicrm_api3( 'Phone', 'create', $phone );

						$note = sprintf(__( 'Created new CiviCRM Phone of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $phone['phone']);
						$order->add_order_note( $note );
					}
				}

				// Process Email
				$email_exists = FALSE;
				// 'shipping_email' does not exist as a Woocommerce field
				if( $address_type != 'shipping' && ! empty( $order->{'get_' . $address_type . '_email'}() ) ){
					$email = array(
						'location_type_id' => $location_type_id,
						'email' => $order->{'get_' . $address_type . '_email'}(),
						'contact_id' => $cid,
					);
					foreach( $existing_emails as $existing_email ){
						if( $existing_email['location_type_id'] == $location_type_id ){
							$email['id'] = $existing_email['id'];
						}
						if( $existing_email['email'] == $email['email'] ){
							$email_exists = TRUE;
						}
					}
					if( ! $email_exists ){
					civicrm_api3( 'Email', 'create', $email );
						$note = sprintf(__( 'Created new CiviCRM Email of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $email['email']);
						$order->add_order_note( $note );
					}
				}

				// Process Address
				$address_exists = FALSE;
				if( ! empty( $order->{'get_' . $address_type . '_address_1'}() ) && ! empty( $order->{'get_' . $address_type . '_postcode'}() ) ){

					$country_id = WCI()->helper->get_civi_country_id( $order->{'get_' . $address_type . '_country'}() );
					$address = array(
						'location_type_id'       => $location_type_id,
						'city'                   => $order->{'get_' . $address_type . '_city'}(),
						'postal_code'            => $order->{'get_' . $address_type . '_postcode'}(),
						'name'                   => $order->{'get_' . $address_type . '_company'}(),
						'street_address'         => $order->{'get_' . $address_type . '_address_1'}(),
						'supplemental_address_1' => $order->{'get_' . $address_type . '_address_2'}(),
						'country'                => $country_id,
						'state_province_id'      => WCI()->helper->get_civi_state_province_id( $order->{'get_' . $address_type . '_state'}(), $country_id ),
						'contact_id'             => $cid,
					);

					foreach( $existing_addresses as $existing ){
						if( $existing['location_type_id'] == $location_type_id ){
							$address['id'] = $existing['id'];
						}
						// @TODO Don't create if exact match of another - should
						// we make 'exact match' configurable.
						elseif (
							$existing['street_address'] == $address['street_address']
							&& CRM_Utils_Array::value( 'supplemental_address_1', $existing ) == CRM_Utils_Array::value( 'supplemental_address_1', $address )
							&& $existing['city'] == $address['city']
							&& $existing['postal_code'] == $address['postal_code']
						){
							$address_exists = TRUE;
						}
					}
					if( ! $address_exists ){
						civicrm_api3( 'Address', 'create', $address );

						$note = sprintf(__( 'Created new CiviCRM Address of type %1$s: %2$s', 'woocommerce-civicrm' ), $address_type, $address['street_address']);
						$order->add_order_note( $note );
					}
				}
			}
		} catch ( CiviCRM_API3_Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to add/update address or phone', 'woocommerce-civicrm' ) );
		}

		return $cid;

	}

	/**
	 * Function to add a contribution record.
	 *
	 * @since 2.0
	 * @param int $cid The contact_id
	 * @param object $order The order object
	 */
	public function add_contribution( $cid, &$order ) {

		$debug['cid']=$cid;

		$order_id = $order->get_id();
		$txn_id = __( 'Woocommerce Order - ', 'woocommerce-civicrm' ) . $order_id;
		$invoice_id = (false != $invoice_no = get_post_meta($order_id, '_order_number')) ? $invoice_no : $order_id . '_woocommerce';
		$this->create_custom_contribution_fields();
		$this->utm_to_order( $order->get_id() );

		$sales_tax_field_id = 'custom_' . get_option( 'woocommerce_civicrm_sales_tax_field_id' );
		$shipping_cost_field_id = 'custom_' . get_option( 'woocommerce_civicrm_shipping_cost_field_id' );

		// Ensure number format is Civi compliant
		$decimal_separator = '.';
		$thousand_separator = '';
		try{
			$civi_decimal_separator = civicrm_api3('Setting', 'getvalue', array(
			  'sequential' => 1,
			  'name' => "monetaryDecimalPoint",
			));
			$civi_thousand_separator = civicrm_api3('Setting', 'getvalue', array(
			  'sequential' => 1,
			  'name' => "monetaryThousandSeparator",
			));
			if(is_string($civi_decimal_separator)){
				$decimal_separator = $civi_decimal_separator;
			}
			if(is_string($civi_thousand_separator)){
				$thousand_separator = $civi_thousand_separator;
			}
		} catch ( CiviCRM_API3_Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to fetch monetary settings', 'woocommerce-civicrm' ) );
		}

		$sales_tax = $order->get_total_tax();
		$sales_tax = number_format( $sales_tax, 2, $decimal_separator, $thousand_separator );

		$shipping_cost = $order->get_total_shipping();

		if (!$shipping_cost) {
			$shipping_cost = 0;
		}
		$shipping_cost = number_format($shipping_cost, 2, $decimal_separator, $thousand_separator );

		// @FIXME Landmine. CiviCRM doesn't seem to accept financial values
		// with precision greater than 2 digits after the decimal.
		$rounded_total = round( $order->get_total() * 100 ) / 100;

		// Couldn't figure where Woocommerce stores the subtotal (ie no TAX price)
		// So for now...
		$rounded_subtotal = $rounded_total - $sales_tax;

		$rounded_subtotal = number_format($rounded_subtotal, 2, $decimal_separator, $thousand_separator);

		$default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
		$contribution_type_vat_id = get_option( 'woocommerce_civicrm_financial_type_vat_id' ); // Get the VAT Financial type
		$campaign_name = '';
		$woocommerce_civicrm_campaign_id = get_option( 'woocommerce_civicrm_campaign_id' ); // Get the global CiviCRM campaign ID
		if(false !== $local_campaign_id = get_post_meta($order->get_id(), '_woocommerce_civicrm_campaign_id', true)){
			$woocommerce_civicrm_campaign_id = $local_campaign_id; // Use the local CiviCRM campaign ID if possible
		}
		if($woocommerce_civicrm_campaign_id){
			$params = array(
				'sequential' => 1,
				'return' => array("name"),
				'id' => $woocommerce_civicrm_campaign_id,
				'options' => array('limit' => 1),
			);
			try{
				$campaignsResult = civicrm_api3( 'Campaign', 'get', $params );
				$campaign_name = isset($campaignsResult['values'][0]['name']) ? $campaignsResult['values'][0]['name'] : '';
			} catch ( CiviCRM_API3_Exception $e ){
				CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				return FALSE;
			}
		}

		$contribution_status_id = $this->map_contribution_status($order->get_status());

		// Get order paid date
		// In case of post treatment
		$order_paid_date = 'now';
		$order_date = $order->get_date_paid();
		if(!$order_date){
			$order_date = $order->get_date_completed();
		}
		if(!$order_date){
			$order_date = $order->get_date_created();
		}
		if($order_date){
			$order_paid_date = $order_date->date('Y-m-d H:i:s');
		}

		$items = $order->get_items();

		$payment_instrument = $this->map_payment_instrument( $order->get_payment_method() );
		$source = $this->generate_source( $order );
		$params = array(
			'contact_id' => $cid,
			'total_amount' => $rounded_subtotal,
			// Need to be set in admin page
			'contribution_type_id' => $default_contribution_type_id,
			'payment_instrument_id' => $payment_instrument,
			'non_deductible_amount' => number_format( 0, 2, $decimal_separator, $thousand_separator ),
			'fee_amount' => number_format( 0, 2, $decimal_separator, $thousand_separator ),
			'trxn_id' => $txn_id,
			'invoice_id' => $invoice_id,
			'source' => $source,
			'receive_date' => $order_paid_date,
			'contribution_status_id' => $contribution_status_id,
			'note' => $this->create_detail_string( $items ),
			"$sales_tax_field_id" => $sales_tax,
			"$shipping_cost_field_id" => $shipping_cost,
			'campaign_id' => $campaign_name,
			'api.line_item.create' => array(),
		);
		// If the order has VAT (Tax) use VAT Fnancial type
		if( $sales_tax != 0 ){
			// Need to be set in admin page
			$params['contribution_type_id'] = $contribution_type_vat_id;
		}

		/**
		 * Add line items to CiviCRM contribution
		 * @since 2.2
		 */
		foreach( $items as $item ){
			$custom_contribution_type = get_post_meta($item['product_id'], '_civicrm_contribution_type', true);
			if($custom_contribution_type === 'exclude')
				continue;

			if(!$custom_contribution_type){
				$custom_contribution_type = $default_contribution_type_id;
			}
			$params['api.line_item.create'][] = array(
				'price_field_id' => array(
				  '0' => 3,
				),
				'qty' => $item['qty'],
				'line_total' => number_format( $item['line_total'], 2, $decimal_separator, $thousand_separator ),
				'unit_price' => number_format( $item['line_total'] / $item['qty'], 2, $decimal_separator, $thousand_separator ),
				'label' => $item['name'],
				'financial_type_id' => $custom_contribution_type,
			);
		}

		// Flush UTM cookies
		$this->delete_utm_cookies();

		try {
			/**
		 * Filter Contribution params before calling the Civi's API.
		 *
		 * @since 2.0
		 * @param array $params The params to be passsed to the API
		 */
			$contribution = civicrm_api3( 'Contribution', 'create', apply_filters( 'woocommerce_civicrm_contribution_create_params', $params ) );
			if(isset($contribution['id']) && $contribution['id']){
				// Adds order note in reference to the created contribution
				$order->add_order_note(sprintf(__('Contribution %s has been created in CiviCRM', 'woocommerce-civicrm'),
					'<a href="' .add_query_arg(
						array(
							'page' => 'CiviCRM',
							'q' => 'civicrm/contact/view/contribution',
							'reset' => '1',
							'id' => $contribution['id'],
							'cid' => $cid,
							'action' => 'view',
							'context' => 'dashboard',
							'selectedChild' => 'contribute'
						),
						admin_url('admin.php')
					). '">' . $contribution['id'] . '</a>')
				);
				return $contribution;
			}
		} catch ( CiviCRM_API3_Exception $e ) {
			// Log the error, but continue.
			CRM_Core_Error::debug_log_message( __( 'Not able to add contribution', 'woocommerce-civicrm' ) );
		}
		$order->add_order_note(  __( 'CiviCRM Contribution could not be created', 'woocommerce-civicrm' ) );
		return false;
	}

	/**
	 * Maps Woocommerce payment method to CiviCRM payment instrument.
	 *
	 * @since 2.0
	 * @param string $payment_method Woocommerce payment method
	 * @return int $id CiviCRM payment processor ID
	 */
	public function map_payment_instrument( $payment_method ) {
		$map = array(
			"paypal" 	=> 1,
			"cod"  		=> 3,
			"cheque"  => 4,
			"bacs" 		=> 5,
		);

		if( array_key_exists( $payment_method, $map ) ){
			$id = $map[$payment_method];
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
	 * @param object $order The order object
	 * @return string $str
	 */
	public function create_detail_string( $items ) {

		$str = '';
		$n = 1;
		foreach( $items as $item ){
			if ( $n > 1 ) {
				$str .= ', ';
			}
			$str .= $item['name'].' x '.$item['quantity'];
			$n++;
		}

		return $str;

	}

	/**
	 * Maps WooCommerce order status to CiviCRM contribution status.
	 *
	 * @since 2.0
	 * @param string $order_status WooCommerce order status
	 * @return int $id CiviCRM Contribution status
	 */
	public function map_contribution_status( $order_status ) {

		$map = array(
			'wc-completed'  => 1,
			'wc-pending'    => 2,
			'wc-cancelled'  => 3,
			'wc-failed'     => 4,
			'wc-processing' => 5,
			'wc-on-hold'    => 5,
			'wc-refunded'   => 7,
		);

		if ( array_key_exists( $order_status, $map ) ) {
			$id = $map[$order_status];
		} else {
			// Oh no.
			$id = 1;
		}

		return $id;

	}

	/**
	 * Generates a string to define contribution source.
	 *
	 * @param  object $order The order object
	 * @since 2.2
	 */
	public function generate_source( $order ){
		// Default is the order Type
		// Until 2.2, contribution source was exactly the same as contribution note.
		$source = $order->get_type();
		// Checks if users comes from a campaign
		if ( isset( $_COOKIE[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) && $_COOKIE[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ] ) {
			$source =  esc_attr($_COOKIE[ 'woocommerce_civicrm_utm_source_' . COOKIEHASH ]);
		}
		// Append medium UTM if present
		if ( isset( $_COOKIE[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) && $_COOKIE[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ] ) {
			$source .=  ' / '.esc_attr($_COOKIE[ 'woocommerce_civicrm_utm_medium_' . COOKIEHASH ]);
		}
		return $source;
	}

	/**
	 * Function to create sales tax and shipping cost custom fields for contribution.
	 *
	 * @since 2.0
	 */
	public function create_custom_contribution_fields(){
		$group_id = get_option( 'woocommerce_civicrm_contribution_group_id', FALSE );
		if( $group_id != FALSE ){
			return;
		}

		// First we need to check if the VAT and Shipping custom fields have
		// already been created.
		$params = array(
			'title'            => 'Woocommerce Purchases',
			'name'             => 'Woocommerce_purchases',
			'extends'          => array( 'Contribution' ),
			'weight'           => 1,
			'collapse_display' => 0,
			'is_active'        => 1,
		);

		try {
			$custom_group = civicrm_api3( 'CustomGroup', 'create', $params );
		} catch ( CiviCRM_API3_Exception $e ){
			CRM_Core_Error::debug_log_message( __( 'Not able to create custom group', 'woocommerce-civicrm' ) );
		}
		add_option( 'woocommerce_civicrm_contribution_group_id', $custom_group['id'] );

		$params = array(
			'custom_group_id' => $custom_group['id'],
			'label'           => 'Sales tax',
			'html_type'       => 'Text',
			'data_type'       => 'String',
			'weight'          => 1,
			'is_required'     => 0,
			'is_searchable'   => 0,
			'is_active'       => 1,
		);
		$tax_field = civicrm_api3( 'Custom_field', 'create', $params );
		add_option( 'woocommerce_civicrm_sales_tax_field_id', $tax_field['id'] );

		$params = array(
			'custom_group_id' => $custom_group['id'],
			'label'           => 'Shipping Cost',
			'html_type'       => 'Text',
			'data_type'       => 'String',
			'weight'          => 2,
			'is_required'     => 0,
			'is_searchable'   => 0,
			'is_active'       => 1,
		);
		$shipping_field = civicrm_api3( 'Custom_field', 'create', $params );
		add_option( 'woocommerce_civicrm_shipping_cost_field_id', $shipping_field['id'] );
	}

	/**
	 * Adds a custom field to set a campaign
	 *
	 * @param  object $order Woocommerce order
	 * @since 2.2
	 */
	public function order_data_after_order_details($order){
		if($order->get_status() === 'auto-draft'){
			wp_nonce_field('woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new');
		}
		else{
			wp_nonce_field('woocommerce_civicrm_order_edit', 'woocommerce_civicrm_order_edit');
		}
		$order_campaign = get_post_meta($order->get_id(), '_woocommerce_civicrm_campaign_id', true);

		if($order_campaign==""||$order_campaign===false){// if there is no campaign selected, select the default one (set up in WC -> settings -> CiviCRM)
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id' ); // Get the global CiviCRM campaign ID
		}

		?>
		<p class="form-field form-field-wide wc-civicrmcampaign">
			<label for="order_civicrmcampaign"><?php _e('CiviCRM Campaign', 'woocommerce-civicrm'); ?></label>

			<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="<?php esc_attr(__('CiviCRM Campaign', 'woocommerce-civicrm')); ?>">
				<option value=""></option>
				<?php foreach (WCI()->helper->campaigns as $campaign_id => $campaign_name): ?>
				<option value="<?php esc_attr_e($campaign_id); ?>" <?php selected($campaign_id, $order_campaign, true); ?>><?php echo $campaign_name; ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Action to check if UTM parameters are passed in URL (front only)
	 *
	 * @since 2.2
	 */
	public function check_utm(){
		if (is_admin())
			return;

		if(isset($_GET['utm_campaign']) || isset($_GET['utm_source']) || isset($_GET['utm_medium']) ){
			$this->save_utm_cookies();
		}
	}

	/**
	 * Save UTM parameters to cookies
	 *
	 * @since 2.2
	 */
	private function save_utm_cookies(){
		if(defined( 'WP_CLI') && WP_CLI){
			return;
		}
		$expire 	= apply_filters( 'woocommerce_civicrm_utm_cookie_expire', 0 );
		$secure 	= ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) );

		if(false !== $campaign = filter_input(INPUT_GET, 'utm_campaign')){
			try {
				$params = array(
					'sequential' => 1,
					'return' => array("id"),
					'name' => esc_attr($campaign),
				);
				$campaignsResult = civicrm_api3( 'Campaign', 'get', $params );
				if($campaignsResult && isset($campaignsResult['values'][0]['id'])){
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, $campaignsResult['values'][0]['id'], $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
				}
				else{
					// Remove cookie if campaign is invalid
					setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS );
				}
			} catch ( CiviCRM_API3_Exception $e ){
				CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				return FALSE;
			}
		}
		if(false !== $source = filter_input(INPUT_GET, 'utm_source')){
			setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, esc_attr($source), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}
		if(false !== $medium = filter_input(INPUT_GET, 'utm_medium')){
			setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, esc_attr($medium), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		}
	}

	/**
	 * Saves UTM cookie to post meta
	 *
	 * @param int $order_id The order ID
	 * @since 2.2
	 */
	private function utm_to_order( $order_id ){
		if(defined( 'WP_CLI') && WP_CLI){
			return;
		}
		if ( isset( $_COOKIE[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) && $_COOKIE[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ) {
			update_post_meta($order_id, '_woocommerce_civicrm_campaign_id', esc_attr( $_COOKIE[ 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH ] ));
			setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	/**
	 * Delete UTM cookies
	 *
	 * @since 2.2
	 */
	private function delete_utm_cookies(){
		if(defined( 'WP_CLI') && WP_CLI){
			return;
		}
		// Remove any existing cookies.
		$past = time() - YEAR_IN_SECONDS;
		setcookie( 'woocommerce_civicrm_utm_campaign_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_source_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( 'woocommerce_civicrm_utm_medium_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
	}
}
