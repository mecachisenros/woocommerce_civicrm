<?php

class CRM_Contact_Page_View_Purchases extends CRM_Core_Page {

	function run() {
		CRM_Utils_System::setTitle( ts( 'Purchases' ) );

		$uid = CRM_Utils_Request::retrieve( 'uid', 'Positive', $this );

		$orders = WCI()->orders_tab->get_orders( $uid );

		$this->assign( 'orders', $orders );

		parent::run();
	}
}
