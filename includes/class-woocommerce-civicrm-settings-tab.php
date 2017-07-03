<?php

/**
 * Woocommerce CiviCRM Settings Tab class.
 *
 * @since 2.0
 */

 class Woocommerce_CiviCRM_Settings_Tab {

  /**
   * The active Financial Types.
   *
   * Array of key/value pairs holding the active financial types.
   * @since 2.0
   * @access private
   * @var array $financial_types The financial types
   */
  private $financial_types;

  /**
   * The Address Location Type.
   *
   * Array of key/value pairs holding the address location types.
   * @since 2.0
   * @access private
   * @var array $location_types The location types
   */
  private $location_types;

  /**
	 * Initialises this object.
   *
	 * @since 2.0
	 */
  public function __construct() {
    $this->financial_types = $this->get_financial_types();
    $this->location_types = $this->get_address_location_types();
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
        'options' => $this->financial_types,
        'id'   => 'woocommerce_civicrm_financial_type_id'
      ),
      'woocommerce_civicrm_financial_type_vat_id' => array(
        'name' => __( 'Contribution Type VAT (Tax)', 'woocommerce-civicrm' ),
        'type' => 'select',
        'options' => $this->financial_types,
        'id'   => 'woocommerce_civicrm_financial_type_vat_id'
      ),
      'woocommerce_civicrm_billing_location_type_id' => array(
        'name' => __( 'Billing Location Type', 'woocommerce-civicrm' ),
        'type' => 'select',
        'options' => $this->location_types,
        'id'   => 'woocommerce_civicrm_billing_location_type_id'
      ),
      'woocommerce_civicrm_shipping_location_type_id' => array(
        'name' => __( 'Shipping Location Type', 'woocommerce-civicrm' ),
        'type' => 'select',
        'options' => $this->location_types,
        'id'   => 'woocommerce_civicrm_shipping_location_type_id'
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

  private function get_financial_types(){

    $params = array(
      'sequential' => 1,
      'is_active' => 1,
    );

    /**
     * Filter Financial type params before calling the Civi's API.
     *
     * @since 2.0
     * @param array $params The params to be passsed to the API
     */
    $financialTypesResult = civicrm_api3( 'FinancialType', 'get', apply_filters( 'woocommerce_civicrm_financial_types_params', $params ) );

    $financialTypes = array();
    foreach( $financialTypesResult['values'] as $key => $value ) {
      $financialTypes[$value['id']] = $value['name'];
    }

    return $financialTypes;

  }

  private function get_address_location_types(){

    $addressTypesResult = civicrm_api3( 'Address', 'getoptions', array( 'field' => 'location_type_id' ) );
    return $addressTypesResult['values'];

  }
}
