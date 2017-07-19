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
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->replace = Woocommerce_CiviCRM_Helper::$instance->check_yes_no_value( get_option( 'woocommerce_civicrm_replace_woocommerce_states' ) );
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
		foreach ( Woocommerce_CiviCRM_Helper::$instance->civicrm_states as $state_id => $state ) {
			$new_states[ Woocommerce_CiviCRM_Helper::$instance->get_civi_country_iso_code( $state['country_id'] ) ][ $state['abbreviation'] ] = $state['name'];
		}
    
		return $new_states;
	}
}
