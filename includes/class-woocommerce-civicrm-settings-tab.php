<?php

/**
 * Woocommerce CiviCRM Settings Tab class.
 *
 * @since 2.0
 */

class Woocommerce_CiviCRM_Settings_Tab {

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// Add Civicrm settings tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		// Add Woocommerce Civicrm settings
		add_action( 'woocommerce_settings_woocommerce_civicrm', array( $this, 'add_settings_fields' ), 10 );
		// Update Woocommerce Civicrm settings
		add_action( 'woocommerce_update_options_woocommerce_civicrm', array( $this, 'update_settings_fields' ) );
	}

	/**
	 * Add CiviCRM tab to the settings page.
	 *
	 * @since 2.0
	 * @uses 'woocommerce_settings_tabs_array' filter
	 * @param array $setting_tabs The setting tabs array
	 * @return array $setting_tabs The setting tabs array
	 */
	public function add_settings_tab( $setting_tabs ) {
		$setting_tabs['woocommerce_civicrm'] = __( 'CiviCRM', 'woocommerce-civicrm' );
		return $setting_tabs;
	}

	/**
	 * Add Woocommerce Civicrm settings to the Settings tab.
	 *
	 * @since 2.0
	 */
	public function add_settings_fields(){
		woocommerce_admin_fields( $this->civicrm_settings_fields() );
	}

	/**
	 * Update Woocommerce Civicrm settings.
	 *
	 * @since 2.0
	 */
	public function update_settings_fields(){
		woocommerce_update_options( $this->civicrm_settings_fields() );
	}

	/**
	 * Settings options.
	 *
	 * @since 2.0
	 * @return array $options The fields configuraion
	 */
	public function civicrm_settings_fields(){

		$options = array(
			'section_title' => array(
				'name' => __( 'CiviCRM Settings', 'woocommerce-civicrm' ),
				'type' => 'title',
				'desc' => __( 'Below are the values used when creating contribution/address in CiviCRM.', 'woocommerce-civicrm' ),
				'id' => 'woocommerce_civicrm_section_title'
			),
			'woocommerce_civicrm_financial_type_id' => array(
				'name' => __( 'Contribution Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => Woocommerce_CiviCRM_Helper::$instance->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_id'
				),
			'woocommerce_civicrm_financial_type_vat_id' => array(
				'name' => __( 'Contribution Type VAT (Tax)', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => Woocommerce_CiviCRM_Helper::$instance->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_vat_id'
			),
			'woocommerce_civicrm_billing_location_type_id' => array(
				'name' => __( 'Billing Location Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => Woocommerce_CiviCRM_Helper::$instance->location_types,
				'id'   => 'woocommerce_civicrm_billing_location_type_id'
			),
			'woocommerce_civicrm_shipping_location_type_id' => array(
				'name' => __( 'Shipping Location Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => Woocommerce_CiviCRM_Helper::$instance->location_types,
				'id'   => 'woocommerce_civicrm_shipping_location_type_id'
			),
			'woocommerce_civicrm_sync_contact_address' => array(
				'name' => __( 'Sync Contact address', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user address with CiviCRM\'s contact address and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_address'
			),
			'woocommerce_civicrm_sync_contact_phone' => array(
				'name' => __( 'Sync Contact billing phone', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user\'s billing phone with CiviCRM\'s contact billing phone and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_phone'
			),
			'woocommerce_civicrm_sync_contact_email' => array(
				'name' => __( 'Sync Contact billing email', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user\'s billing email with CiviCRM\'s contact billing email and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_email'
			),
			'woocommerce_civicrm_replace_woocommerce_states' => array(
				'name' => __( 'Replace Woocommerce States', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'WARNING, possible data loss!! If enabled, this option will replace Woocommerce\'s States/Counties with CiviCRM\'s States/Provinces, you might lose any existing State/County data for existing Customers.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_replace_woocommerce_states'
      ),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => 'woocommerce_civicrm_section_end'
			)
		);

		/**
		 * Filter Woocommerce CiviCRM setting fields
		 *
		 * @since 2.0
		 * @param array $options The fields configuration
		 */
		return apply_filters( 'woocommerce_civicrm_admin_settings_fields', $options );

	}
}
