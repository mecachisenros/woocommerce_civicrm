<?php

class CRM_Contact_Page_View_Purchases extends CRM_Core_Page {

	function run() {
		CRM_Utils_System::setTitle( ts( 'Purchases' ) );

		$uid = CRM_Utils_Request::retrieve( 'uid', 'Positive', $this );

		$orders = $uid ? WCI()->orders_tab->get_orders( $uid ) : array();

		$this->assign( 'i18n', array(
			'orderNumber' 	=> __('Order Number', 'woocommerce-civicrm'),
		    'date' 			=> __('Date', 'woocommerce-civicrm'),
		    'billingName' 	=> __('Billing Name', 'woocommerce-civicrm'),
		    'shippingName' 	=> __('Shipping Name', 'woocommerce-civicrm'),
		    'itemCount' 	=> __('Item count', 'woocommerce-civicrm'),
		    'amount'		=> __('Amount', 'woocommerce-civicrm'),
		    'actions' 		=> __('Actions', 'woocommerce-civicrm'),
		    'emptyUid' 		=> __('This contact is not linked to any WordPress user or WooCommerce Customer', 'woocommerce-civicrm'),
				'orders' 		=> __('Orders', 'woocommerce-civicrm'),
				'addOrder' 		=> __('Add Order', 'woocommerce-civicrm'),
		) );
		$this->assign( 'orders', $orders );
		$this->assign( 'uid', $uid );
		$this->assign( 'newOrderUrl', apply_filters('helios_woocommerce_add_order_pos_url', add_query_arg(array(	'post_type' => 'shop_order',
																																																							'user_id' => $uid,
																																																						),admin_url('post-new.php')) ,$uid ));
		parent::run();
	}
}
