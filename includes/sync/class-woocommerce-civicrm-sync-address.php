<?php

/**
 * Woocommerce CiviCRM Sync Address class.
 *
 * @since 2.0
 */

class Woocommerce_CiviCRM_Sync_Address {

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
		// Sync Woocommerce and Civicrm address for contact/user
		add_action( 'civicrm_post', array( $this, 'sync_civi_contact_address' ), 10, 4 );
		// Sync Woocommerce and Civicrm address for user/contact
		add_action( 'woocommerce_customer_save_address', array( $this, 'sync_wp_user_woocommerce_address' ), 10, 2 );
	}


	/**
	 * Checks if Woocommerce is activated on another blog
	 *
	 * @since 2.2
	 */
	private function is_remote_wc(){
		if( false == WCI()->is_network_installed )
			return false;

		$option = 'woocommerce_civicrm_network_settings';
		$options = get_site_option($option);
		if(!$options)
			return false;

		$wc_site_id = $options['wc_blog_id'];
		if($wc_site_id == get_current_blog_id())
			return false;

		return $wc_site_id;
	}

	/**
	 * Moves to main woocommerce site if multisite installation
	 *
	 * @since 2.2
	 */
	private function fix_site(){
		if( false == $wc_site_id = $this->is_remote_wc() ){
			return;
		}

		switch_to_blog($wc_site_id);
	}

	/**
	 * Moves to current site if multisite installation
	 *
	 * @since 2.2
	 */
	private function unfix_site(){
		if(!is_multisite())
			return;

		restore_current_blog();
	}
	/**
	 * Sync Civicrm address for contact->user.
	 *
	 * Fires when a Civi contact's address is edited.
	 * @since 2.0
	 * @param string $op The operation being performed
	 * @param string $objectName The entity name
	 * @param int $objectId The entity id
	 * @param object $objectRef The entity object
	 */
	public function sync_civi_contact_address( $op, $objectName, $objectId, $objectRef ){

		// abbort if sync is not enabled
		$this->fix_site();
		if( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) return;
		$this->unfix_site();

		if ( $op != 'edit' ) return;

		if ( $objectName != 'Address' ) return;

		// Abort if the address being edited is not one of the mapped ones
		if( ! in_array( $objectRef->location_type_id, WCI()->helper->mapped_location_types ) ) return;

		// abort if we don't have a contact_id
		if ( ! isset( $objectRef->contact_id ) ) return;

		$cms_user = WCI()->helper->get_civicrm_ufmatch( $objectRef->contact_id, 'contact_id' );

		// abort if we don't have a WordPress user_id
		if ( ! $cms_user ) return;

		// Proceed

		foreach(WCI()->helper->mapped_location_types as $mapped_location_type_key => $mapped_location_type_value){
			if($objectRef->location_type_id == $mapped_location_type_value){
				$address_type = $mapped_location_type_key;
				foreach ( WCI()->helper->get_mapped_address( $address_type ) as $wc_field => $civi_field ) {
					if ( ! empty( $objectRef->{$civi_field} ) && ! is_null( $objectRef->{$civi_field} )) {
						if($objectRef->{$civi_field} == 'null'){
							$new_value = "";
						}else{
							$new_value = $objectRef->{$civi_field};
						}
						switch ( $civi_field ) {
							case 'country_id':
								update_user_meta( $cms_user['uf_id'], $wc_field, WCI()->helper->get_civi_country_iso_code( $objectRef->{$civi_field} ) );
								continue 2;
							case 'state_province_id':
								update_user_meta( $cms_user['uf_id'], $wc_field, WCI()->helper->get_civi_state_province_name( $objectRef->{$civi_field} ) );
								continue 2;
							default:
								update_user_meta( $cms_user['uf_id'], $wc_field, $new_value );
								continue 2;
						}
					}
				}
			}
		}
		/**
		 * Broadcast that a Woocommerce address has been updated for a user.
		 *
		 * @since 2.0
		 * @param int $user_id The WordPress user id
		 * @param string $address_type The Woocommerce adress type 'billing' || 'shipping'
		 */
		do_action( 'woocommerce_civicrm_wc_address_updated', $cms_user['uf_id'], $address_type );

	}

	/**
	 * Sync Woocommerce address for user->contact.
	 *
	 * Fires when Woocomerce address is edited.
	 * @since 2.0
	 * @param int $user_id The WP user_id
	 * @param string $load_address The address type 'shipping' | 'billing'
	 */
	public function sync_wp_user_woocommerce_address( $user_id, $load_address ){

		// abbort if sync is not enabled
		$this->fix_site();
		if( ! WCI()->helper->check_yes_no_value( get_option( 'woocommerce_civicrm_sync_contact_address' ) ) ) return;
		$this->unfix_site();

		$customer = new WC_Customer( $user_id );

		$civi_contact = WCI()->helper->get_civicrm_ufmatch( $user_id, 'uf_id' );

		// abort if we don't have a CiviCRM contact
		if ( ! $civi_contact ) return;

		$mapped_location_types = WCI()->helper->mapped_location_types;
		$civi_address_location_type = $mapped_location_types[$load_address];

		$edited_address = array();
		foreach ( WCI()->helper->get_mapped_address( $load_address ) as $wc_field => $civi_field ) {
			switch ( $civi_field ) {
				case 'country_id':
					$edited_address[$civi_field] = WCI()->helper->get_civi_country_id( $customer->{'get_' . $wc_field}() );
					continue 2;
				case 'state_province_id':
					$edited_address[$civi_field] = WCI()->helper->get_civi_state_province_id( $customer->{'get_' . $wc_field}(), $edited_address['country_id'] );
					continue 2;
				default:
					$edited_address[$civi_field] = $customer->{'get_' . $wc_field}();
					continue 2;
			}
		}

		$params = array(
			'contact_id' => $civi_contact['contact_id'],
			'location_type_id' => $civi_address_location_type,
		);

		try {
			$civi_address = civicrm_api3( 'Address', 'getsingle', $params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		try {
			if ( isset( $civi_address ) && ! $civi_address['is_error'] ) {
				$new_params = array_merge( $civi_address, $edited_address );
			} else {
				$new_params = array_merge( $params, $edited_address );
			}
			$create_address = civicrm_api3( 'Address', 'create', $new_params );
		} catch ( CiviCRM_API3_Exception $e ) {
			CRM_Core_Error::debug_log_message( $e->getMessage() );
		}

		/**
		 * Broadcast that a CiviCRM address has been updated.
		 *
		 * @since 2.0
		 * @param int $contact_id The CiviCRM contact_id
		 * @param array $address The CiviCRM edited address
		 */
		do_action( 'woocommerce_civicrm_civi_address_updated', $civi_contact['contact_id'], $create_address );
	}
}
