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

				add_action( 'bulk_edit_custom_box', array(&$this, 'add_contribution_to_quick_edit'), 10, 2 );


				// Bulk / quick edit.
				add_action( 'save_post', array( $this, 'bulk_and_quick_edit_hook' ), 10, 2 );
				add_action( 'contributions_product_bulk_and_quick_edit', array( $this, 'bulk_and_quick_edit_save_post' ), 10, 2 );


    }

    // Contribution fields for products
    function contribution_fields() {

        //global $woocommerce, $post;
        echo '<div class="options_group">';

        $default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );

        $contributions_types = WCI()->helper->financial_types;
        $options =
            array(
                '' => sprintf(
                    '-- '.__('Default (%s)', 'woocommerce-civicrm'),
                    isset($contributions_types[$default_contribution_type_id]) ? $contributions_types[$default_contribution_type_id] : __('unset', 'woocommerce-civicrm')
                )
            ) +
            $contributions_types +
            array(
                'exclude' => '-- '.__('Exclude', 'woocommerce-civicrm')
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


		// Contribution fields for products
    function contribution_fields_bulk() {

        //global $woocommerce, $post;
        echo '
								<div class="inline-edit-group">
								<label class="alignleft">
									<span class="title">'.__( 'Contribution type', 'woocommerce-civicrm' ).'</span>
									<span class="input-text-wrap">






				<select style="" id="_civicrm_contribution_type" name="civicrm_contribution_type" class="select short">';

				$contributions_types = WCI()->helper->financial_types;
        $options =
            array(

							''  => __( '— No change —', 'woocommerce-civicrm' ),

            ) +
            $contributions_types +
            array(
                'exclude' => '-- '.__('Exclude', 'woocommerce-civicrm')
            );

				foreach ( $options as $key => $value ) {
						echo '<option value="' . esc_attr( $key ) . '">' . $value . '</option>';
				}
        echo '	</select>
				</span>
			</label>
		</div>';
    }

    //Save
    function product_save( $post_id ){
        $civicrm_contribution_type = sanitize_text_field($_POST['civicrm_contribution_type']);
        update_post_meta( $post_id, '_civicrm_contribution_type', $civicrm_contribution_type );
    }

		function add_contribution_to_quick_edit( $column_name, $post_type  ){
				if ( 'product_cat' !== $column_name || 'product' !== $post_type ) {
					return;
				}
				echo '<div class="inline-edit-col-right" style="float:right;">';
				$this->contribution_fields_bulk();
				echo '</div>';
		}
		/**
		 * Offers a way to hook into save post without causing an infinite loop
		 * when quick/bulk saving product info.
		 *
		 * @since 3.0.0
		 * @param int    $post_id Post ID being saved.
		 * @param object $post Post object being saved.
		 */
		 function bulk_and_quick_edit_hook( $post_id, $post ) {
			remove_action( 'save_post', array( $this, 'bulk_and_quick_edit_hook' ) );
			do_action( 'contributions_product_bulk_and_quick_edit', $post_id, $post );
			add_action( 'save_post', array( $this, 'bulk_and_quick_edit_hook' ), 10, 2 );
		}
		/**
	 * Quick and bulk edit saving.
	 *
	 * @param int    $post_id Post ID being saved.
	 * @param object $post Post object being saved.
	 * @return int
	 */
	 function bulk_and_quick_edit_save_post( $post_id, $post ) {
		 if(isset($_GET['civicrm_contribution_type'])){
			 $civicrm_contribution_type = sanitize_text_field($_GET['civicrm_contribution_type']);
				update_post_meta( $post_id, '_civicrm_contribution_type', $civicrm_contribution_type );
		 }
	 }


}



?>
