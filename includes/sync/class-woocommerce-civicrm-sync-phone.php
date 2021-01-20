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
		add_action( 'civicrm_post', [ $this, 'sync_civi_contact_phone' ], 10, 4 );
		// Sync Woocommerce and Civicrm phone for user/contact
		add_action( 'woocommerce_customer_save_address', [ $this, 'sync_wp_user_woocommerce_phone' ], 10, 2 );
	}

	/**
	 * Sync Civicrm phone for contact/user.
	 *
	 * Fires when a Civi contact's phone is edited.
	 * @since 2.0
	 * @param string $op The operation being performed
	 * @param string $object_name The entity name
	 * @param int $object_id The entity id
	 * @param object $object_ref The entity object
	 */
	public function sync_civi_contact_phone( $op, $object_name, $object_id, $object_ref ) {

		// abbort if sync is not enabled
		if ( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return;
		}

		if ( 'edit' !== $op ) {
			return;
		}

		if ( 'Phone' !== $object_name ) {
			return;
		}

		// Abort if the phone being edited is not one of the mapped ones
		if ( ! in_array( $object_ref->location_type_id, WCI()->helper->mapped_location_types ) ) {
			return;
		}

		// abort if we don't have a contact_id
		if ( ! isset( $object_ref->contact_id ) ) {
			return;
		}

		$cms_user = WCI()->helper->get_civicrm_ufmatch( $object_ref->contact_id, 'contact_id' );

		// abort if we don't have a WordPress user
		if ( ! $cms_user ) {
			return;
		}

		// Proceed
		$phone_type = array_search( $object_ref->location_type_id, WCI()->helper->mapped_location_types );

		// only billing_phone, there's no shipping_phone field
		if ( 'billing' === $phone_type ) {
			update_user_meta( $cms_user['uf_id'], $phone_type . '_phone', $object_ref->phone );
		}

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
	public function sync_wp_user_woocommerce_phone( $user_id, $load_address ) {

		// abbort if sync is not enabled
		if ( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_phone' ) ) ) {
			return;
		}

		// abort if phone is not of type 'billing'
		if ( 'billing' !== $load_address ) {
			return;
		}

		$civi_contact = WCI()->helper->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// abort if we don't have a CiviCRM contact
		if ( ! $civi_contact ) {
			return;
		}

		$mapped_location_types = WCI()->helper->mapped_location_types;
		$civi_phone_location_type = $mapped_location_types[ $load_address ];

		$customer = new WC_Customer( $user_id );

		$edited_phone = [
			'phone' => $customer->{'get_' . $load_address . '_phone'}(),
		];

		$params = [
			'contact_id' => $civi_contact['contact_id'],
			'location_type_id' => $civi_phone_location_type,
		];

		try {
			$civi_phone = civicrm_api3( 'Phone', 'getsingle', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		try {
			if ( isset( $civi_phone ) && ! $civi_phone['is_error'] ) {
				$new_params = array_merge( $civi_phone, $edited_phone );
			} else {
				$new_params = array_merge( $params, $edited_phone );
			}
			$create_phone = civicrm_api3( 'Phone', 'create', $new_params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
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
