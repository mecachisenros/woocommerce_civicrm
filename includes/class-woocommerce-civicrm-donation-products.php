<?php
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Woocommerce CiviCRM Product class.
 *
 * @since 2.2
 */

class Woocommerce_CiviCRM_Donation_Products {

    /**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

    public function register_hooks(){

        add_action( 'woocommerce_product_options_general_product_data', array(&$this, 'donation_fields'), 10 );
        add_action( 'woocommerce_process_product_meta', array(&$this, 'product_save') );

				add_action( 'admin_enqueue_scripts', array(&$this, 'donation_admin_script') );

				add_shortcode( 'woocommerce-civicrm-donation', array(&$this, 'woocommerce_civicrm_donation') );


    }

		function donation_admin_script() {
			wp_enqueue_script( 'donation_admin_script', WOOCOMMERCE_CIVICRM_URL . 'js/donation-admin.js', array(), '1.0' );
		}
    // Contribution fields for products
    function donation_fields() {

        //global $woocommerce, $post;
        echo '<div class="options_group donation_fields">';

					$arg_suggestion = array(
					'label' => 'One-time Price list choices', // Text in Label
					'placeholder' => '25,45,55*,100',
					'id' => '_civicrm_donation_onetime_price_list', // required
					'name' => 'civicrm_donation_onetime_price_list', //name will set from id if empty

					'description' => 'List here the choices you want to see on the Donation page for the One-Time Donation. Separate by "," and give * to the default one.'
					);
					woocommerce_wp_text_input( $arg_suggestion );

					$arg_suggestion = array(
					'label' => 'Recurring Price list choices', // Text in Label
					'placeholder' => '5,10,15,30',
					'id' => '_civicrm_donation_recurring_price_list', // required
					'name' => 'civicrm_donation_recurring_price_list', //name will set from id if empty

					'description' => 'List here the choices you want to see on the Donation page for the Recurring Donation. Separate by ",".'
					);
					woocommerce_wp_text_input( $arg_suggestion );


					$arg_tax_return = array(
					'label' => 'Percentage of Tax Return (%)', // Text in Label
					'placeholder' => '66',
					'id' => '_civicrm_donation_tax_return', // required
					'name' => 'civicrm_donation_tax_return', //name will set from id if empty

					'description' => 'Enter here the percentage of tax return the benefactor will get back after donation.'
					);
					woocommerce_wp_text_input( $arg_tax_return );
        echo '</div>';

    }



    //Save
    function product_save( $post_id ){
        $civicrm_donation_tax_return = sanitize_text_field($_POST['civicrm_donation_tax_return']);
        update_post_meta( $post_id, '_civicrm_donation_tax_return', $civicrm_donation_tax_return );


		    $civicrm_donation_onetime_price_list = sanitize_text_field($_POST['civicrm_donation_onetime_price_list']);
		    update_post_meta( $post_id, '_civicrm_donation_onetime_price_list', $civicrm_donation_onetime_price_list );

				$civicrm_donation_recurring_price_list = sanitize_text_field($_POST['civicrm_donation_recurring_price_list']);
		    update_post_meta( $post_id, '_civicrm_donation_recurring_price_list', $civicrm_donation_recurring_price_list );
    }

		function woocommerce_civicrm_donation( $atts ){
			$result = array(
				"success" => "true",
				"output" => "",
				"error" => ""
			);

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if(is_plugin_active('woocommerce/woocommerce.php') && is_plugin_active('woocommerce-name-your-price/woocommerce-name-your-price.php')){
				wp_enqueue_script( 'donation-front-script', WOOCOMMERCE_CIVICRM_URL . 'js/donation-front.js', array(), '1.0' );
				wp_enqueue_style('donation-style', WOOCOMMERCE_CIVICRM_URL . 'css/donation.css' );
				$result["output"] .= '
				<div class="woocommerce_civicrm_donation">
						<div class="col_wrapper">
						<div class="group_choices">';

				if(isset($atts['product-id'])&&$atts['product-id']!=""&&$atts['product-id']!=="0"){

					$product = wc_get_product( $atts['product-id'] );
					if($product===FALSE || $product===NULL){
						$result["error"] .= __('The product ID provided in the attribute product-id is not a valid product ID.','woocommerce-civicrm').'<br/>';
						$result["success"]= false;
					}else{
						$_nyp = get_post_meta($product->get_id() , "_nyp" , true );
						if($_nyp == "yes"){
							$result["output"] .= '
							<div class="col_wrapper">';
							if(metadata_exists('post' ,$product->get_id() , "_civicrm_donation_onetime_price_list" )){
								$_civicrm_donation_onetime_price_array = explode( ",",get_post_meta($product->get_id() , "_civicrm_donation_onetime_price_list" , true ));
								if(is_array($_civicrm_donation_onetime_price_array) && count($_civicrm_donation_onetime_price_array)>0){
									$result["output"] .= '<div class="onetime_choices"><h2>'.__('I donate once','woocommerce-civicrm').'</h2>';
									foreach ($_civicrm_donation_onetime_price_array as $_civicrm_donation_price) {
										$checked = "";
										if(strpos($_civicrm_donation_price ,"*")){
											$checked = ' checked="checked" ';
											$_civicrm_donation_price = str_replace("*" , "", $_civicrm_donation_price);
										}
										$result["output"] .= '<label><input type="radio" value="'.$_civicrm_donation_price.'" name="price" '.$checked.'/><span> '.$_civicrm_donation_price.' '.__('€','woocommerce-civicrm').'</span></label>';
									}
									$result["output"] .= '<label>	<input type="radio" value="other_price_onetime" name="price"/>
																								<span>'.__('Another amount','woocommerce-civicrm').' <input type="text" name="other_price_onetime" id="other_price_onetime" class="other_price" disabled> '.__('€','woocommerce-civicrm').'</span></label>';
									$result["output"] .= '</div>';
								}
							}

							if(metadata_exists('post' ,$product->get_id() , "_civicrm_donation_recurring_price_list" )){
								$_civicrm_donation_recurring_price_array = explode( ",",get_post_meta($product->get_id() , "_civicrm_donation_recurring_price_list" , true ));
								if(is_array($_civicrm_donation_recurring_price_array) && count($_civicrm_donation_recurring_price_array)>0){
									$result["output"] .= '<div class="recurring_choices wcd_col"><h2>'.__('I donate monthly','woocommerce-civicrm').'</h2>';
									foreach ($_civicrm_donation_recurring_price_array as $_civicrm_donation_price) {
										$result["output"] .= '<label><input type="radio" value="'.$_civicrm_donation_price.'" name="price" class="recurring_choices"/><span> '.$_civicrm_donation_price.' '.__('€','woocommerce-civicrm').'</span></label>';
									}
									$result["output"] .= '<label>	<input type="radio" value="other_price_recurring" class="recurring_choices" name="price"/>
																								<span>'.__('Another amount','woocommerce-civicrm').' <input type="text" name="other_price_recurring" id="other_price_recurring" class="other_price" disabled> '.__('€','woocommerce-civicrm').'</span></label>';
									$result["output"] .= '</div>';
								}
							}

							$result["output"] .= '	</div>';
							$result["output"] .= '<input type="text" value="false" id="is_recurring" class="is_recurring">';
							$result["output"] .= '<input type="text" value="" id="actual_price" class="actual_price">';
							if(metadata_exists('post' ,$product->get_id() , "_civicrm_donation_tax_return" )){
								$_civicrm_donation_tax_return = get_post_meta($product->get_id() , "_civicrm_donation_tax_return", true);

								$result["output"] .= '<input type="text" value="'.$_civicrm_donation_tax_return.'" id="tax_return" class="tax_return">';
								$result["output"] .= '<div class="tax_return">

								<p>'. sprintf(__('Thanks to the tax return of %s','woocommerce-civicrm'), $_civicrm_donation_tax_return).' %, <br/>
								my <span id="actual_price_calculation"></span> '.__('€','woocommerce-civicrm').' donation will only cost me <span id="after_tax_price_calculation"></span> '.__('€','woocommerce-civicrm').'.
								</p></div>';
							}
						}else{
							$result["error"] .= __('The Donation Product you have given is not a Name Your Price Product. Please, edit the product and check the Name Your Price checkbox.','woocommerce-civicrm').'<br/>';
							$result["success"]= false;
						}
					}
				}else{
					$result["error"] .= __('You need to fill at least the Product ID for the Donation Product you want to show. The attribute is product-id.','woocommerce-civicrm').'<br/>';
					$result["success"]= false;
				}
				$result["output"] .= '	<div class="gateways">gateways</div>';
				$result["output"] .= '	</div>';
				$result["output"] .= '	<div class="form_basket">form</div>';

				$result["output"] .= '</div>';
			}else{
				$result["error"] .= __('This Shortcode needs WooCommerce and WooCommerce Name Your Price to be activated to work.','woocommerce-civicrm') .'<br/>';
				$result["success"]= false;
			}
			if($result["success"]){
				return $result["output"];
			}else{
				return $result["error"];
			}
		}
}



?>
