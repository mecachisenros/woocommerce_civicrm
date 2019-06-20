<?php

/**
 * Woocommerce CiviCRM Sync class.
 *
 * @since 2.1
 */

class Woocommerce_CiviCRM_Sync {

	/**
	 * The Address sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var array $address The address sync object
	 */
	public $address;

	/**
	 * The Email sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var array $email The email sync object
	 */
	public $email;

	/**
	 * The Phone sync object.
	 *
	 * @since 2.1
	 * @access public
	 * @var array $phone The email sync object
	 */
	public $phone;

	/**
	 * Initialises this object.
	 *
	 * @since 2.1
	 */
	public function __construct() {
		$this->include_files();
		$this->setup_objects();
	}

	/**
	 * Include sync files.
	 *
	 * @since 0.1
	 */
	public function include_files() {
		// Include Address Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/sync/class-woocommerce-civicrm-sync-address.php';
		// Include Phone Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/sync/class-woocommerce-civicrm-sync-phone.php';
		// Include Email Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/sync/class-woocommerce-civicrm-sync-email.php';
	}

	/**
	 * Setup sync objects.
	 *
	 * @since 2.1
	 */
	public function setup_objects() {
		// init address sync
		$this->address = new Woocommerce_CiviCRM_Sync_Address;
		// init phone sync
		$this->phone = new Woocommerce_CiviCRM_Sync_Phone;
		// init email sync
		$this->email = new Woocommerce_CiviCRM_Sync_Email;
	}
}
