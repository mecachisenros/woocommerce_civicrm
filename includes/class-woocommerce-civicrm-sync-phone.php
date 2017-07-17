<?php

/**
 * Woocommerce CiviCRM Sync Phone class.
 *
 * @since 2.0
 */

 class Woocommerce_CiviCRM_Sync_Phone {

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
   * @since 0.2
   */
  public function register_hooks() {
    // Sync Woocommerce and Civicrm phone for contact/user
    add_action( 'civicrm_post', array( $this, 'sync_civi_contact_phone' ), 10, 4 );
    // Sync Woocommerce and Civicrm phone for user/contact
    add_action( 'woocommerce_customer_save_address', array( $this, 'sync_wp_user_woocommerce_phone' ), 10, 2 );
  }

  /**
   * Sync Civicrm phone for contact/user.
   *
   * Fires when a Civi contact's phone is edited.
   * @since 2.0
   * @param string $op The operation being performed
   * @param string $objectName The entity name
   * @param int $objectId The entity id
   * @param object $objectRef The entity object
   */
  public function sync_civi_contact_phone( $op, $objectName, $objectId, $objectRef ){

    // abbort if sync is not enabled
    if( ! get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) return;

    if ( $op != 'edit' ) return;

    if ( $objectName != 'Phone' ) return;
    
    // Abort if the phone being edited is not one of the mapped ones
    if( ! in_array( $objectRef->location_type_id, Woocommerce_CiviCRM_Helper::$instance->mapped_location_types ) ) return;

    // abort if we don't have a contact_id
    if ( ! isset( $objectRef->contact_id ) ) return;

    $cms_user = Woocommerce_CiviCRM_Helper::$instance->get_civicrm_ufmatch( $objectRef->contact_id, 'contact_id' );

    // abort if we don't have a WordPress user
    if ( ! $cms_user ) return;

    // Proceed
    $phone_type = array_search( $objectRef->location_type_id, Woocommerce_CiviCRM_Helper::$instance->mapped_location_types );

    // only billing_phone, there's no shipping_phone field
    if( $phone_type == 'billing' )
      update_user_meta( $cms_user['uf_id'], $phone_type . '_phone', $objectRef->phone );

    /**
     * Broadcast that a Woocommerce phone has been updated for a user.
     *
     * @since 2.0
     * @param int $user_id The WordPress user id
     * @param string $phone_type The Woocommerce phone type 'billing' || 'shipping'
     */
    do_action( 'woocommerce_civicrm_wc_phone_updated', $cms_user['uf_id'], $phone_type );

  }

  /**
   * Sync Woocommerce phone for user->contact.
   *
   * Fires when Woocomerce address is edited.
   * @since 2.0
   * @param int $user_id The WP user id
   * @param string $load_address The address type 'shipping' | 'billing'
   */
  public function sync_wp_user_woocommerce_phone( $user_id, $load_address ){

    // abbort if sync is not enabled
    if( ! get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) return;

    // abort if phone is not of type 'billing'
    if( $load_address != 'billing' ) return;

    $civi_contact = Woocommerce_CiviCRM_Helper::$instance->get_civicrm_ufmatch( $user_id, 'uf_id' );

    // abort if we don't have a CiviCRM contact
    if ( ! $civi_contact ) return;

    $mapped_location_types = Woocommerce_CiviCRM_Helper::$instance->mapped_location_types;
    $civi_phone_location_type = $mapped_location_types[$load_address];

    $customer = new WC_Customer( $user_id );

    $edited_phone = array(
      'phone' => $customer->{'get_' . $load_address . '_phone'}(),
    );

    $params = array(
      'contact_id' => $civi_contact['contact_id'],
      'location_type_id' => $civi_phone_location_type,
    );

    try {
      $civi_phone = civicrm_api3( 'Phone', 'getsingle', $params );
    } catch ( CiviCRM_Exception $e ) {
      CRM_Core_Error::debug_log_message( $e->getMessage() );
    }

    if ( isset( $civi_phone ) && ! $civi_phone['is_error'] ) {
      $new_params = array_merge( $civi_phone, $edited_phone );
      try {
        $create_phone = civicrm_api3( 'Phone', 'create', $new_params );
      } catch ( CiviCRM_Exception $e ) {
        CRM_Core_Error::debug_log_message( $e->getMessage() );
      }
    }

    /**
     * Broadcast that a CiviCRM phone has been updated.
     *
     * @since 2.0
     * @param int $contact_id The CiviCRM contact_id
     * @param array $phone The CiviCRM phone edited
     */
    do_action( 'woocommerce_civicrm_civi_phone_updated', $civi_contact['contact_id'], $create_phone );
  }
}
