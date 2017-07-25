<?php

/**
 * Woocommerce CiviCRM Sync Email class.
 *
 * @since 2.0
 */

class Woocommerce_CiviCRM_Sync_Email {

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
		// Sync Woocommerce and Civicrm email for contact/user
		add_action( 'civicrm_post', array( $this, 'sync_civi_contact_email' ), 10, 4 );
		// Sync Woocommerce and Civicrm email for user/contact
		add_action( 'woocommerce_customer_save_address', array( $this, 'sync_wp_user_woocommerce_email' ), 10, 2 );
	}

	/**
	 * Sync Civicrm email for contact->user.
	 *
	 * Fires when a Civi contact's email is edited.
	 * @since 2.0
	 * @param string $op The operation being performed
	 * @param string $objectName The entity name
	 * @param int $objectId The entity id
	 * @param object $objectRef The entity object
	 */
	public function sync_civi_contact_email( $op, $objectName, $objectId, $objectRef ){

		// abbort if sync is not enabled
		if( ! Woocommerce_CiviCRM_Helper::$instance->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) return;

		if ( $op != 'edit' ) return;

		if ( $objectName != 'Email' ) return;

		// Abort if the email being edited is not one of the mapped ones
		if( ! in_array( $objectRef->location_type_id, Woocommerce_CiviCRM_Helper::$instance->mapped_location_types ) ) return;

		// abort if we don't have a contact_id
		if ( ! isset( $objectRef->contact_id ) ) return;

		$cms_user = Woocommerce_CiviCRM_Helper::$instance->get_civicrm_ufmatch( $objectRef->contact_id, 'contact_id' );

		// abort if we don't have a WordPress user
		if ( ! $cms_user ) return;

		// Proceed
		$email_type = array_search( $objectRef->location_type_id, Woocommerce_CiviCRM_Helper::$instance->mapped_location_types );

		// only billing_email, there's no shipping_email field
		if( $email_type == 'billing' )
			update_user_meta( $cms_user['uf_id'], $email_type . '_email', $objectRef->email );

		/**
		 * Broadcast that a Woocommerce email has been updated for a user.
		 *
		 * @since 2.0
		 * @param int $user_id The WordPress user_id
		 * @param string $email_type The Woocommerce email type 'billing' || 'shipping'
		 */
		do_action( 'woocommerce_civicrm_wc_email_updated', $cms_user['uf_id'], $email_type );

	}

	/**
	 * Sync Woocommerce email for user->contact.
	 *
	 * Fires when Woocomerce email is edited.
	 * @since 2.0
	 * @param int $user_id The WP user_id
	 * @param string $load_address The address type 'shipping' | 'billing'
	 */
	public function sync_wp_user_woocommerce_email( $user_id, $load_address ){

		// abbort if sync is not enabled
		if( ! Woocommerce_CiviCRM_Helper::$instance->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_email' ) ) ) return;

		// abort if email is not of type 'billing'
		if( $load_address != 'billing' ) return;

		$civi_contact = Woocommerce_CiviCRM_Helper::$instance->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// abort if we don't have a CiviCRM contact
		if ( ! $civi_contact ) return;

		$mapped_location_types = Woocommerce_CiviCRM_Helper::$instance->mapped_location_types;
		$civi_email_location_type = $mapped_location_types[$load_address];

		$customer = new WC_Customer( $user_id );

		$edited_email = array(
			'email' => $customer->{'get_' . $load_address . '_email'}(),
		);

		$params = array(
			'contact_id' => $civi_contact['contact_id'],
			'location_type_id' => $civi_email_location_type,
		);

		try {
			$civi_email = civicrm_api3( 'Email', 'getsingle', $params );
		} catch ( CiviCRM_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		try {
			if ( isset( $civi_email ) && ! $civi_email['is_error'] ) {
				$new_params = array_merge( $civi_email, $edited_email );
			} else {
				$new_params = array_merge( $params, $edited_email );
			}
			$create_email = civicrm_api3( 'Email', 'create', $new_params );
		} catch ( CiviCRM_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		/**
		 * Broadcast that a CiviCRM email has been updated.
		 *
		 * @since 2.0
		 * @param int $contact_id The CiviCRM contact_id
		 * @param array $email The CiviCRM email edited
		 */
		do_action( 'woocommerce_civicrm_civi_email_updated', $civi_contact['contact_id'], $create_email );
	}
}
