<?php

class CRM_Contact_Page_Purchases extends CRM_Core_Page {

	function run() {
		// Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
		CRM_Utils_System::setTitle( ts( 'Purchases' ) );

		// Example: Assign a variable for use in a template
		$uid = CRM_Utils_Request::retrieve( 'uid', 'Positive', $this );

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
			$order = new WC_Order();
			$order->populate( $customer_order );
			$status = get_term_by( 'slug', $order->status, 'shop_order_status' );
			$item_count = $order->get_item_count();
			$total = $order->get_total();
			$orders[$customer_order->ID]['order_number'] = $order->get_order_number();
			$orders[$customer_order->ID]['order_date'] = date( 'Y-m-d', strtotime( $order->order_date ));
			$orders[$customer_order->ID]['order_billing_name'] = $order->get_formatted_billing_full_name();
			$orders[$customer_order->ID]['order_shipping_name'] = $order->get_formatted_shipping_full_name();
			$orders[$customer_order->ID]['item_count'] = $item_count;
			$orders[$customer_order->ID]['order_total'] = $total;
			$orders[$customer_order->ID]['order_link'] = $site_url."/wp-admin/post.php?action=edit&post=".$order->get_order_number();
		}

		$this->assign( 'orders', $orders );

		parent::run();
	}
}
