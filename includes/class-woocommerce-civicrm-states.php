<?php

/**
 * Woocommerce CiviCRM States class.
 *
 * @since 2.0
 */

class Woocommerce_CiviCRM_States {

	/**
	 * Replace Woocommerce States/Counties.
	 *
	 * @since 2.0
	 * @access public
	 * @var bool $replace
	 */
	public $replace = false;

	/**
	 * CiviCRM Countries.
	 *
	 * Array holding CiviCRM country list in the form of array( 'country_id' => 'is_code' )
	 * @since 2.0
	 * @access public
	 * @var array $countries The CiviCRM countries
	 */
	public $civicrm_countries = array();


	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->replace = Woocommerce_CiviCRM_Helper::$instance->check_yes_no_value( get_option( 'woocommerce_civicrm_replace_woocommerce_states' ) );
		//$this->civicrm_countries = $this->get_civicrm_countries();
		$this->register_hooks();
	}

	/**
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// Add Civicrm settings tab
		add_filter( 'woocommerce_states', array( $this, 'replace_woocommerce_states' ), 10, 1 );
	}

	/**
	 * Function to replace Woocommerce state/counties list with CiviCRM's list.
	 *
	 * @since 2.0
	 * @uses 'woocommerce_states' filter
	 * @param array $states The Woocommerce state/counties
	 * @return array $states The modifies states/counties
	 */
	public function replace_woocommerce_states( $states ) {
		// abort if replace is not enabled
		if( ! $this->replace ) return $states;

		$new_states = array();
		foreach ( Woocommerce_CiviCRM_Helper::instance()->get_civicrm_states() as $state_id => $state ) {
			$new_states[ $this->civicrm_countries[ $state['country_id'] ] ][ $state['abbreviation'] ] = $state['name'];
		}

		return $new_states;
	}
	
	/**
	 * Function to get CiviCRM countries.
	 *
	 * @since 2.0
	 * @return array $civicrm_countries The CiviCRM country list
	 */
	public function get_civicrm_countries(){
		if( ! empty( $this->civicrm_countries ) ) return $this->civicrm_countries;

		$civicrm_countries = array();
		// Get countries from cache, if set
		if (Woocommerce_CiviCRM_Helper::$instance->check_yes_no_value(get_option('woocommerce_civicrm_cache_data'))) {
			Woocommerce_CiviCRM_Helper::$instance->get_cached_civicrm_country($civicrm_countries);
		} else {
			// If cache is not set, get countries from civicrm
			$countries = civicrm_api3( 'Country', 'get', array(
				'sequential' => 1,
				'options' => array( 'limit' => 0 ),
			));
			foreach( $countries['values'] as $key => $country ){
				$civicrm_countries[$country['id']] = $country['iso_code']; 
			}
		}

		return $civicrm_countries;
	}
}
