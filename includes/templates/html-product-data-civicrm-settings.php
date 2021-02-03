<?php
/**
 * The CiviCRM settings tab HTML in the product tabs.
 *
 * @since 2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="woocommerece_civicrm" class="panel woocommerce_options_panel hidden">
	<div>
		<?php

		woocommerce_wp_select(
			[
				'id' => 'woocommerce_civicrm_financial_type_id',
				'name' => 'woocommerce_civicrm_financial_type_id',
				'label' => __( 'Financial type', 'woocommerce-civicrm' ),
				'desc_tip' => 'true',
				'description' => __( 'The CiviCRM financial type for this product.', 'woocommerce-civicrm' ),
				'options' => WCI()->helper->get_financial_types_options(),
			]
		);

		woocommerce_wp_select(
			[
				'id' => 'woocommerce_civicrm_membership_type_id',
				'name' => 'woocommerce_civicrm_membership_type_id',
				'label' => __( 'Membership Type', 'woocommerce-civicrm' ),
				'desc_tip' => 'true',
				'description' => __( 'Select a Membership Type if you would like this product to create a Membership in CiviCRM, the Memebership will be created (with duration, plan, etc.) based on the settings in CiviCRM.', 'woocommerce-civicrm' ),
				'options' => WCI()->helper->get_membership_types_options(),
			]
		);

		?>
	</div>
</div>
