<?php

/**
 * Woocommerce CiviCRM Orders Contact Tab class.
 *
 * @since 2.0
 */
class Woocommerce_CiviCRM_Orders_Contact_Tab {

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Moves to main woocommerce site if multisite installation
	 *
	 * @since 2.2
	 */
	private function fix_site(){
		if(!is_multisite())
			return;

		$wc_site_id = get_option( 'woocommerce_civicrm_blog_id' );
		if($wc_site_id == get_current_blog_id())
			return;

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
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// register custom php directory
		add_action( 'civicrm_config', array( $this, 'register_custom_php_directory' ), 10, 1 );
		// register custom template directory
		add_action( 'civicrm_config', array( $this, 'register_custom_template_directory' ), 10, 1 );
    	// register menu callback
		add_filter( 'civicrm_xmlMenu', array( $this, 'register_callback' ), 10, 1 );
		// Add Civicrm settings tab
		add_filter( 'civicrm_tabset', array( $this, 'add_orders_contact_tab' ), 10, 3 );
	}

	/**
	 * Register php directory.
	 *
	 * @since 2.0
	 * @param object $config The CiviCRM config object
	 */
	public function register_custom_php_directory( &$config ){
		$this->fix_site();
		$custom_path = WOOCOMMERCE_CIVICRM_PATH . 'custom_php';
		$include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $include_path );
		$this->unfix_site();
	}

	/**
	 * Register template directory.
	 *
	 * @since 2.0
	 * @param object $config The CiviCRM config object
	 */
	public function register_custom_template_directory( &$config ){
		$this->fix_site();
		$custom_path = WOOCOMMERCE_CIVICRM_PATH . 'custom_tpl';
		$template = CRM_Core_Smarty::singleton()->addTemplateDir( $custom_path );
		$include_template_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $include_template_path );
		$this->unfix_site();
	}

  	/**
	 * Register XML file.
	 *
	 * @since 2.0
	 * @param array $files The array for files used to build the menu
	 */
  	public function register_callback( &$files ){
		$this->fix_site();
		$files[] = WOOCOMMERCE_CIVICRM_PATH . 'xml/menu.xml';
		$this->unfix_site();
	}

	/**
	 * Add CiviCRM tab to the settings page.
	 *
	 * @since 2.0
	 * @uses 'woocommerce_settings_tabs_array' filter
	 * @param array $setting_tabs The setting tabs array
	 * @return array $setting_tabs The setting tabs array
	 */
	public function add_orders_contact_tab( $tabsetName, &$tabs, $context ) {
		$uid = CRM_Core_BAO_UFMatch::getUFId( $context['contact_id'] );
		if (empty($uid)) {
			return;
		}

		$this->fix_site();
		$orders = $this->get_orders( $uid );
		$this->unfix_site();

		$url = CRM_Utils_System::url( 'civicrm/contact/view/purchases', "reset=1&uid=$uid");
		$tabs[] = array( 'id'    => 'woocommerce-orders',
			'url'   => $url,
			'title' => 'Woocommerce Orders',
			'count' => count($orders), //$order_count,
			'weight' => 99
		);
	}

	/**
	 * Get Customer orders.
	 *
	 * @since 2.1
	 * @param int $uid The User id for a contact (UFMatch)
	 * @return array $orders The orders
	 */
	public function get_orders( $uid ) {
		$this->fix_site();
		$customer_orders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', array(
			'numberposts' => -1,
			'meta_key'    => '_customer_user',
			'meta_value'  => $uid,
			'post_type'   => 'shop_order',
			'post_status' => array_keys( wc_get_order_statuses() )
		) ) );

		$site_url = get_site_url();
		$orders = array();
		foreach ( $customer_orders as $customer_order ) {
			$order = new WC_Order($customer_order);
			//$order->populate( $customer_order );
			$status = get_term_by( 'slug', $order->get_status(), 'shop_order_status' );
			$item_count = $order->get_item_count();
			$total = $order->get_total();
			$orders[$customer_order->ID]['order_number'] = $order->get_order_number();
			$orders[$customer_order->ID]['order_date'] = date( 'Y-m-d', strtotime( $order->get_date_created() ));
			$orders[$customer_order->ID]['order_billing_name'] = $order->get_formatted_billing_full_name();
			$orders[$customer_order->ID]['order_shipping_name'] = $order->get_formatted_shipping_full_name();
			$orders[$customer_order->ID]['item_count'] = $item_count;
			$orders[$customer_order->ID]['order_total'] = $total;
			$orders[$customer_order->ID]['order_link'] = $site_url."/wp-admin/post.php?action=edit&post=".$order->get_order_number();
		}
		$this->unfix_site();
		if( ! empty( $orders ) ) return $orders;

		return false;
	}
}
