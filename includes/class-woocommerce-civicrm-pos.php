<?php

/**
 * Woocommerce CiviCRM POS class.
 *
 * @since 2.0
 */
class Woocommerce_CiviCRM_POS {



	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct(){

		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	public function register_hooks(){



		// add the filter

		add_action( 'wp_ajax_set_campaign', array( $this,'set_campaign') );
    add_action( 'wp_ajax_nopriv_set_campaign', array( $this,'set_campaign') );

		add_action( 'wp_ajax_get_campaign', array( $this,'get_campaign') );
    add_action( 'wp_ajax_nopriv_get_campaign', array( $this,'get_campaign') );

		add_filter( 'woocommerce_pos_enqueue_footer_js', array( $this,'wc_pos_campaign_js'), 10, 1 );

		add_filter( 'woocommerce_pos_enqueue_head_css', array( $this,'wc_pos_campaign_css'), 10, 1 );

		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'wc_pos_campaign_prepare_shop_order_object' ), 11, 3 );
	}
	public function wc_pos_campaign_prepare_shop_order_object( $response, $order, $request ) {
		WCI()->manager->update_campaign( $response->data['id'], '', get_user_meta(get_current_user_id(),"pos_campaign_id", true) );
		die();
		return $response;
	}
	function wc_pos_campaign_js($js) {
		$js['pos-campaign-js']= WOOCOMMERCE_CIVICRM_URL . 'js/pos_campaign.js';
		return $js;
	}

	function wc_pos_campaign_css($var) {
		$var['pos-campaign-css']= WOOCOMMERCE_CIVICRM_URL . 'css/pos_campaign.css';
		return $var;
	}

	function set_campaign() {
		update_user_meta(get_current_user_id(),"pos_campaign_id",  $_POST['campaign_id']);
	}
	function get_campaign() {
		$order_campaign = get_user_meta(get_current_user_id(),"pos_campaign_id", true);
		if(!$order_campaign){
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id', false);
			if(!$order_campaign){
				$order_campaign = 0;
			}
			add_user_meta(get_current_user_id(),"pos_campaign_id", $order_campaign);
		}
		$render = '
			<div class="wc-civicrmcampaign">
				<div class="list-row">
					<div>
						<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="'. __('CiviCRM Campaign', 'woocommerce-civicrm') .'">';
						foreach (WCI()->helper->campaigns as $campaign_id => $campaign_name){
							$render .= '<option value="'. $campaign_id .'" '.selected($campaign_id, $order_campaign, false).'>'. $campaign_name.'</option>';
						}
						$render .= '</select>
					</div>
					<div>
						<label for="order_civicrmcampaign">'. __('CiviCRM Campaign', 'woocommerce-civicrm').'</label>
					</div>
				</div>
			</div>
			';

			echo $render;
		wp_die(); // this is required to terminate immediately and return a proper response
	}


}
