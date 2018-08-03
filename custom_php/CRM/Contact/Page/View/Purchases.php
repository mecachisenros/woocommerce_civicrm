<?php

class CRM_Contact_Page_View_Purchases extends CRM_Core_Page {

	function run() {
		CRM_Utils_System::setTitle( ts( 'Purchases' ) );

		$uid = CRM_Utils_Request::retrieve( 'uid', 'Positive', $this );

		$orders = WCI()->orders_tab->get_orders( $uid );

		$this->assign( 'i18n', array(
			'orderNumber' 	=> __('Order Number', 'woocommerce-civicrm'),
		    'date' 			=> __('Date', 'woocommerce-civicrm'),
		    'billingName' 	=> __('Billing Name', 'woocommerce-civicrm'),
		    'shippingName' 	=> __('Shipping Name', 'woocommerce-civicrm'),
		    'itemCount' 	=> __('Item count', 'woocommerce-civicrm'),
		    'amount'		=> __('Amount', 'woocommerce-civicrm'),
		    'actions' 		=> __('Actions', 'woocommerce-civicrm'),
		    'emptyUid' 		=> __('This contact is not linked to any WordPress user or WooCommerce Customer', 'woocommerce-civicrm'),
		) );
		$this->assign( 'orders', $orders );
		$this->assign( 'uid', $uid );

		parent::run();
	}
}
