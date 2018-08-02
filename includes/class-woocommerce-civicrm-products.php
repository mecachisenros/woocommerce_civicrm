<?php
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Woocommerce CiviCRM Product class.
 *
 * @since 2.2
 */

class Woocommerce_CiviCRM_Products {

    /**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

    public function register_hooks(){
        add_action( 'woocommerce_product_options_general_product_data', array(&$this, 'contribution_fields') );
        add_action( 'woocommerce_process_product_meta', array(&$this, 'product_save') );
    }

    // Contribution fields for products
    function contribution_fields() {

        //global $woocommerce, $post;
        echo '<div class="options_group">';

        $default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
        $contributions_types = WCI()->helper->financial_types;
        $options = array_merge(
            array(
                '' => sprintf(
                    '-- '.__('Default (%s)', 'woocommerce-civicrm'),
                    isset($contributions_types[$default_contribution_type_id]) ? $contributions_types[$default_contribution_type_id] : __('unset', 'woocommerce-civicrm')
                )
            ),
            $contributions_types,
            array(
                'exclude' => '-- '.__('Exclude', 'woocommerce-civicrm')
            )
        );

        // Contribution field :
        woocommerce_wp_select(
            array(
                'id' => '_civicrm_contribution_type',
                'name' => 'civicrm_contribution_type',
                'label' => __( 'Contribution type', 'woocommerce-civicrm' ),
                'desc_tip' => 'true',
                'description' => __( 'Custom contribution type for this product', 'woocommerce-civicrm' ),
                'options' => $options,
            )
        );
        echo '</div>';
    }

    //Save
    function product_save( $post_id ){
        $civicrm_contribution_type = sanitize_text_field($_POST['civicrm_contribution_type']);
        update_post_meta( $post_id, '_civicrm_contribution_type', $civicrm_contribution_type );
    }

}
?>