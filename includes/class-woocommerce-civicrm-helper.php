<?php

/**
 * Woocommerce CiviCRM Helper class.
 *
 * @since 2.0
 */
class Woocommerce_CiviCRM_Helper {

	/**
	 * The active Financial Types.
	 *
	 * Array of key/value pairs holding the active financial types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The financial types
	 */
	public $financial_types;

	/**
	 * The active Membership Types.
	 *
	 * Array of key/value pairs holding the active Membership types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The Membership types
	 */
	public $membership_types;

	/**
	 * Optionvalue_membership_signup.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $financial_types The Membership types
	 */
	public $optionvalue_membership_signup;

	/**
	 * The Address Location Type.
	 *
	 * Array of key/value pairs holding the address location types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $location_types The location types
	 */
	public $location_types;

	/**
	 * Woocommerce/CiviCRM mapped address location types.
	 *
	 * Array of key/value pairs holding the woocommerce/civicrm address location types.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $mapped_location_types The mapped location types
	 */
	public $mapped_location_types;

	/**
	 * CiviCRM states.
	 *
	 * @since 2.0
	 * @access public
	 * @var array $states The CiviCRM states
	 */
	public $civicrm_states = [];

	/**
	 * CiviCRM campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM campaigns
	 */
	public $campaigns = [];

	/**
	 * CiviCRM campaigns.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM campaigns
	 */
	public $all_campaigns = [];

	/**
	 * CiviCRM campaigns status.
	 *
	 * @since 2.2
	 * @access public
	 * @var array $campaigns The CiviCRM campaigns
	 */
	public $campaigns_status = [];

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->inited();
	}

	/**
	 * CiviCRM inited.
	 *
	 * @since 2.0
	 */
	public function inited() {

		if ( ! WCI()->boot_civi() ) {
			return;
		}
		$this->financial_types = $this->get_financial_types();
		$this->membership_types = $this->get_civicrm_membership_types();
		$this->location_types = $this->get_address_location_types();
		$this->civicrm_states = $this->get_civicrm_states();
		$this->campaigns_status = $this->get_campaigns_status();
		$this->campaigns = $this->get_campaigns();
		$this->all_campaigns = $this->get_all_campaigns();
		$this->mapped_location_types = $this->set_mapped_location_types();
		$this->optionvalue_membership_signup = $this->get_civicrm_optionvalue_membership_signup();
	}

	/**
	 * Get CiviCRM contact_id.
	 *
	 * @since 2.0
	 * @param object $order The order object.
	 * @return int $cid The contact_id.
	 */
	public function civicrm_get_cid( $order ) {
		$email = '';
		if ( is_user_logged_in() && ! is_admin() ) { // If user is logged in but not in the admin (not a manual order).
			$current_user = wp_get_current_user();
			$email = $current_user->user_email;
		} else {
			if ( filter_input( INPUT_POST, 'customer_user', FILTER_VALIDATE_INT ) ) { // if there was a fiel customer user in form (manual order).
				$cu_id = filter_input( INPUT_POST, 'customer_user', FILTER_VALIDATE_INT );

				$user_info = get_userdata( $cu_id );
				$email = $user_info->user_email;

			} else {
				$email = $order->get_billing_email();
			}
		}

		$wp_user_id = $order->get_user_id();
		// Backend order should not use the logged in user's contact.
		if ( ! is_admin() && 0 !== $wp_user_id ) {
			try {
				$uf_match = civicrm_api3(
					'UFMatch',
					'get',
					[
						'sequential' => 1,
						'uf_id' => $wp_user_id,
					]
				);
				if ( 1 === $uf_match['count'] ) {
					return $uf_match['values'][0]['contact_id'];
				}
			} catch ( CiviCRM_API3_Exception $e ) {
				CRM_Core_Error::debug_log_message( __( 'Failed to get contact from UF table', 'woocommerce-civicrm' ) );
				CRM_Core_Error::debug_log_message( $e->getMessage() );
			}
		} elseif ( $email != '' ) {
			// The customer is anonymous.  Look in the CiviCRM contacts table for a
			// contact that matches the billing email.
			$params = [
				'email' => $email,
				'return.contact_id' => true,
				'sequential' => 1,
			];
		}
		if ( ! isset( $params ) ) {
			CRM_Core_Error::debug_log_message( __( 'Cannot guess contact without an email', 'woocommerce-civicrm' ) );
			return false;
		}

		try {
			$contact = civicrm_api3( 'Contact', 'get', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Failed to get contact by email', 'woocommerce-civicrm' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return false;
		}

		// No matches found, so we will need to create a contact.
		if ( count( $contact ) == 0 ) {
			return 0;
		}
		$cid = isset( $contact['values'][0]['id'] ) ? $contact['values'][0]['id'] : 0;

		return $cid;

	}

	/**
	 * Get CiviCRM UFMatch.
	 *
	 * Get UFMatch for contact_id or WP user_id.
	 *
	 * @since 2.0
	 * @param int $id The CiviCRM contact_id or WP user_id.
	 * @param string $property 'contact_id' | 'uf_id'.
	 * @return array $uf_match The UFMatch.
	 */
	public function get_civicrm_ufmatch( $id, $property ) {

		if ( ! in_array( $property, [ 'contact_id', 'uf_id' ], true ) ) {
			return;
		}

		try {
			$uf_match = civicrm_api3(
				'UFMatch',
				'getsingle',
				[
					'sequential' => 1,
					$property => $id,
				]
			);
		} catch ( Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		if ( ! empty( $uf_match['is_error'] ) ) {
			return $uf_match;
		}
	}

	/**
	 * Function to get CiviCRM country ID for Woocommerce country ISO Code.
	 *
	 * @since 2.0
	 * @param string $woocommerce_country WooCommerce country ISO code.
	 * @return int $id CiviCRM country_id
	 */
	public function get_civi_country_id( $woocommerce_country ) {

		if ( empty( $woocommerce_country ) ) {
			return;
		}

		$result = civicrm_api3(
			'Country',
			'getsingle',
			[
				'sequential' => 1,
				'iso_code' => $woocommerce_country,
			]
		);

		if ( ! $result['id'] ) {
			return;
		}

		return $result['id'];

	}

	/**
	 * Function to get CiviCRM country ISO Code for country_id.
	 *
	 * @since 2.0
	 * @param string $country_id CiviCRM country_id.
	 * @return int $iso_code CiviCRM country ISO Code.
	 */
	public function get_civi_country_iso_code( $country_id ) {

		if ( empty( $country_id ) ) {
			return;
		}

		$result = civicrm_api3(
			'Country',
			'getsingle',
			[
				'sequential' => 1,
				'id' => $country_id,
			]
		);

		if ( ! $result['iso_code'] ) {
			return;
		}

		return $result['iso_code'];

	}

	/**
	 * Function to get CiviCRM state_province_id.
	 *
	 * @since 2.0
	 * @param string $woocommerce_state Woocommerce state.
	 * @param int $country_id CiviCRM country_id.
	 * @return int $id CiviCRM state_province_id.
	 */
	public function get_civi_state_province_id( $woocommerce_state, $country_id ) {

		if ( empty( $woocommerce_state ) ) {
			return;
		}

		if ( empty( $this->civicrm_states ) ) {
			$this->civicrm_states = $this->get_civicrm_states();
		}

		foreach ( $this->civicrm_states as $state_id => $state ) {
			if ( $state['country_id'] === $country_id && $state['abbreviation'] === $woocommerce_state ) {
				return $state['id'];
			}

			if ( $state['country_id'] === $country_id && $state['name'] === $woocommerce_state ) {
				return $state['id'];
			}
		}
	}

	/**
	 * Function to get CiviCRM State/Province name or abbreviation.
	 *
	 * @since 2.0
	 * @param int $state_province_id CiviCRM state id.
	 * @return string $name CiviCRM State/Province name or abbreviation.
	 */
	public function get_civi_state_province_name( $state_province_id ) {

		if ( empty( $state_province_id ) ) {
			return;
		}

		if ( empty( $this->civicrm_states ) ) {
			$this->civicrm_states = $this->get_civicrm_states();
		}

		$civi_state = $this->civicrm_states[ $state_province_id ];

		$woocommerce_countries = new WC_Countries();

		foreach ( $woocommerce_countries->get_states() as $country => $states ) {
			$found = array_search( $civi_state['name'], $states, true );
			if ( ! empty( $states ) && $found ) {
				return $found;
			}
		}

		return $civi_state['name'];
	}

	/**
	 * Function to get Woocommerece CiviCRM address map.
	 *
	 * @since 2.0
	 * @param  string $address_type Woocommerce address type 'billing' || 'shipping'.
	 * @return array $mapped_address The address maps.
	 */
	public function get_mapped_address( $address_type ) {

		/**
		 * Filter address map.
		 *
		 * @since 2.0
		 * @param array $mapped_address
		 */
		return apply_filters(
			'woocommerce_civicrm_address_map',
			[
				$address_type . '_address_1' => 'street_address',
				$address_type . '_address_2' => 'supplemental_address_1',
				$address_type . '_city' => 'city',
				$address_type . '_postcode' => 'postal_code',
				$address_type . '_country' => 'country_id',
				$address_type . '_state' => 'state_province_id',
				$address_type . '_company' => 'name',
			]
		);
	}

	/**
	 * Get CiviCRM states.
	 *
	 * Build multidimentional array of CiviCRM states | array( 'state_id' => array( 'name', 'id', 'abbreviation', 'country_id' ) ).
	 *
	 * @since 2.0
	 */
	private function get_civicrm_states() {

		if ( ! empty( $this->civicrm_states ) ) {
			return $this->civicrm_states;
		}

		$query = 'SELECT name,id,country_id,abbreviation FROM civicrm_state_province';

		$dao = CRM_Core_DAO::executeQuery( $query );
		$civicrm_states = [];
		while ( $dao->fetch() ) {
			$civicrm_states[ $dao->id ] = [
				'id' => $dao->id,
				'name' => $dao->name,
				'abbreviation' => $dao->abbreviation,
				'country_id' => $dao->country_id,
			];
		}

		return $civicrm_states;
	}

	/**
	 * Get CiviCRM campaigns.
	 *
	 * Build multidimentional array of CiviCRM campaigns | array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 */
	private function get_campaigns() {

		if ( ! empty( $this->civicrm_campaigns ) ) {
			return $this->civicrm_campaigns;
		}

		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name' ],
			'is_active' => 1,
			'status_id' => [ 'NOT IN' => [ 'Completed', 'Cancelled' ] ],
			'options' => [
				'sort' => 'name',
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaigns params before calling the Civi's API.
		 *
		 * @since 2.2
		 * @param array $params The params to be passsed to the API.
		 */
		$campaigns_result = civicrm_api3( 'Campaign', 'get', apply_filters( 'woocommerce_civicrm_campaigns_params', $params ) );

		$civicrm_campaigns = [
			__( 'None', 'woocommerce-civicrm' ),
		];
		foreach ( $campaigns_result['values'] as $key => $value ) {
			$civicrm_campaigns[ $value['id'] ] = $value['name'];
		}
		return $civicrm_campaigns;
	}

	/**
	 * Get CiviCRM all campaigns with status.
	 *
	 * Build multidimentional array of CiviCRM campaigns | array( 'campaign_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 */
	private function get_all_campaigns() {

		if ( ! empty( $this->all_campaigns ) ) {
			return $this->all_campaigns;
		}

		if ( ! empty( $this->campaigns_status ) ) {
			$this->campaigns_status = $this->get_campaigns_status();
		}
		$params = [
			'sequential' => 1,
			'return' => [ 'id', 'name', 'status_id' ],
			'options' => [
				'sort' => 'status_id ASC , created_date DESC , name ASC',
				'limit' => 0,
			],
		];

		/**
		 * Filter Campaigns params before calling the Civi's API.
		 *
		 * @since 2.2
		 * @param array $params The params to be passsed to the API
		 */
		$all_campaigns_result = civicrm_api3( 'Campaign', 'get', apply_filters( 'woocommerce_civicrm_campaigns_params', $params ) );

		$all_campaigns = [
			__( 'None', 'woocommerce-civicrm' ),
		];

		foreach ( $all_campaigns_result['values'] as $key => $value ) {

			$status = '';
			if ( isset( $value['status_id'] ) && isset( $this->campaigns_status[ $value['status_id'] ] ) ) {
				$status = ' - ' . $this->campaigns_status[ $value['status_id'] ];
			}

			$all_campaigns[ $value['id'] ] = $value['name'] . $status;
		}

		return $all_campaigns;

	}

	/**
	 * Get CiviCRM all campaigns with status.
	 *
	 * Build multidimentional array of CiviCRM campaigns | array( 'status_id' => array( 'name', 'id', 'parent_id' ) ).
	 *
	 * @since 2.2
	 */
	private function get_campaigns_status() {

		if ( ! empty( $this->campaigns_status ) ) {
			return $this->campaigns_status;
		}

		$params = [
			'sequential' => 1,
			'option_group_id' => 'campaign_status',
		];

		/**
		 * Filter Campaigns params before calling the Civi's API.
		 *
		 * @since 2.2
		 * @param array $params The params to be passsed to the API
		 */

		$civicrm_campaigns_status = [];
		$status_result = civicrm_api3( 'OptionValue', 'get', apply_filters( 'woocommerce_civicrm_status_params', $params ) );

		if ( 0 === $status_result['is_error'] && $status_result['count'] > 0 ) {

			foreach ( $status_result['values'] as $key => $value ) {
				$civicrm_campaigns_status[ $value['value'] ] = $value['label'];
			}

			return $civicrm_campaigns_status;
		} else {
			return false;
		}
	}

	/**
	 * Set Woocommerce CiviCRM mapped location types.
	 *
	 * @since 2.0
	 * @return array $mapped_location_types The mapped location types
	 */
	private function set_mapped_location_types() {

		/**
		 * Filter Woocommerce CiviCRM location types
		 *
		 * @since 2.0
		 * @param array $mapped_location_types
		 */
		return apply_filters(
			'woocommerce_civicrm_mapped_location_types',
			[
				'billing' => get_option( 'woocommerce_civicrm_billing_location_type_id' ),
				'shipping' => get_option( 'woocommerce_civicrm_shipping_location_type_id' ),
			]
		);
	}

	/**
	 * Get CiviCRM Financial Types.
	 *
	 * @since 2.0
	 * @return array $financialTypes The financial types
	 */
	private function get_financial_types() {

		if ( isset( $this->financial_types ) ) {
			return $this->financial_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
		];

		/**
		 * Filter Financial type params before calling the Civi's API.
		 *
		 * @since 2.0
		 * @param array $params The params to be passsed to the API
		 */
		$financial_types_result = civicrm_api3( 'FinancialType', 'get', apply_filters( 'woocommerce_civicrm_financial_types_params', $params ) );

		$financial_types = [];
		foreach ( $financial_types_result['values'] as $key => $value ) {
			$financial_types[ $value['id'] ] = $value['name'];
		}

		return $financial_types;

	}

	/**
	 * Get CiviCRM Address Location Types.
	 *
	 * @since 2.0
	 * @return array $addressTypes The address location types
	 */
	private function get_address_location_types() {

		if ( isset( $this->location_types ) ) {
			return $this->location_types;
		}

		$address_types_result = civicrm_api3( 'Address', 'getoptions', [ 'field' => 'location_type_id' ] );
		return $address_types_result['values'];

	}

	/**
	 * Get CiviCRM Membership Types.
	 *
	 * @since 2.0
	 */
	public function get_civicrm_membership_types() {

		if ( isset( $this->membership_types ) ) {
			return $this->membership_types;
		}

		$params = [
			'sequential' => 1,
			'is_active' => 1,
		];

		/**
		 * Filter Financial type params before calling the Civi's API.
		 *
		 * @since 2.0
		 * @param array $params The params to be passsed to the API
		 */
		$membership_types_result = civicrm_api3( 'MembershipType', 'get', apply_filters( 'woocommerce_civicrm_membership_types_params', $params ) );

		$membership_types = [];
		foreach ( $membership_types_result['values'] as $key => $value ) {
			$membership_types['by_membership_type_id'][ $value['id'] ] = $value;
			$membership_types['by_financial_type_id'][ $value['financial_type_id'] ] = $value;
		}

		return apply_filters( 'woocommerce_civicrm_membership_types', $membership_types, $membership_types_result );

	}

	/**
	 * Get CiviCRM OptionValue Membership Signup.
	 *
	 * @since 2.0
	 */
	public function get_civicrm_optionvalue_membership_signup() {

		$result = civicrm_api3(
			'OptionValue',
			'get',
			[
				'sequential' => 1,
				'return' => [ 'value' ],
				'name' => 'Membership Signup',
			]
		);
		return $result['values'][0]['value'];
	}

	/**
	 * Function to check whether a value is (string) 'yes'.
	 *
	 * @param string $value The value to check.
	 * @return bool true | false
	 */
	public function check_yes_no_value( $value ) {
		return 'yes' === $value ? true : false;
	}

	/**
	 * Get WordPress sites on a multisite Installation
	 *
	 * @return array $sites [$site_id: $site_name].
	 */
	public function get_sites() {
		$sites = [];
		if ( is_multisite() ) {
			$wp_sites = get_sites(
				[
					'orderby' => 'domain',
				]
			);
			foreach ( $wp_sites as $site ) {
				$sites[ $site->blog_id ] = $site->domain;
			}
		}
		return $sites;
	}

	/**
	 * Retrieves the default contribution amount data,
	 * price set, price_field, and price field value.
	 *
	 * @since 2.4
	 *
	 * @return array $default_contribution_amount_data The default contribution amount data.
	 */
	public function get_default_contribution_price_field_data() {

		try {
			$price_set = civicrm_api3(
				'PriceSet',
				'getsingle',
				[
					'name' => 'default_contribution_amount',
					'is_reserved' => true,
					'api.PriceField.getsingle' => [
						'price_set_id' => "\$value.id",
						'options' => [
							'limit' => 1,
							'sort' => 'id ASC',
						],
					],
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to retrieve default price set', 'woocommerce-civicrm' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return null;
		}

		$price_field = $price_set['api.PriceField.getsingle'];
		unset( $price_set['api.PriceField.getsingle'] );

		$default_contribution_amount_data = [
			'price_set' => $price_set,
			'price_field' => $price_field,
		];

		return $default_contribution_amount_data;

	}

	/**
	 * Retrieves the formatted financial types options.
	 *
	 * @since 2.4
	 *
	 * @return array $financial_types The financial types.
	 */
	public function get_financial_types_options() {

		$default_financial_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );

		$options = [
			sprintf(
				/* translators: %s: financial type */
				'-- ' . __( 'Default (%s)', 'woocommerce-civicrm' ),
				$this->financial_types[ $default_financial_type_id ] ?? __( 'unset', 'woocommerce-civicrm' )
			),
		]
		+ $this->financial_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'woocommerce-civicrm' ),
		];

		return $options;

	}

	/**
	 * Retrieves the membership types options array.
	 *
	 * @since 2.4
	 *
	 * @return array $membership_types_options The membership types options.
	 */
	public function get_membership_types_options() {

		try {
			$membership_types = civicrm_api3(
				'MembershipType',
				'get',
				[
					'is_active' => true,
					'options.limit' => 0,
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( __( 'Not able to retrieve memebrship types.', 'woocommerce-civicrm' ) );
			CRM_Core_Error::debug_log_message( $e->getMessage() );
			return [];
		}

		if ( empty( $membership_types['count'] ) ) {
			return [];
		}

		$membership_types_options = [
			0 => '',
		];

		$membership_types_options = array_reduce(
			$membership_types['values'],
			function( $list, $membership_type ) {

				$list[ $membership_type['id'] ] = $membership_type['name'];

				return $list;

			},
			$membership_types_options
		);

		return $membership_types_options;

	}

	/**
	 * Retrieves a membership type by its id.
	 *
	 * @since 2.4
	 *
	 * @param int $id The membership type id.
	 * @return array|null $membership_type The membership type or null.
	 */
	public function get_membership_type( int $id ) {
		try {
			return civicrm_api3(
				'MembershipType',
				'gesingle',
				[
					'id' => $id,
				]
			);
		} catch ( CiviCRM_API3_Exception $e ) {
			return null;
		}
	}

}
