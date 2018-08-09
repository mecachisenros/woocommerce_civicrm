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



		// add the filters | Chosen filters are not ideal , but there are the only few you can access when on a POS page

		// persist the campaign for this user in the data base
		add_action( 'wp_ajax_set_campaign', array( $this,'set_campaign') );
    add_action( 'wp_ajax_nopriv_set_campaign', array( $this,'set_campaign') );

		// get the campaign list from civicrm
		add_action( 'wp_ajax_get_campaign', array( $this,'get_campaign') );
    add_action( 'wp_ajax_nopriv_get_campaign', array( $this,'get_campaign') );

		// Enqueue JS and CSS for the campaign list
		add_filter( 'woocommerce_pos_enqueue_footer_js', array( $this,'wc_pos_campaign_js'), 10, 1 );
		add_filter( 'woocommerce_pos_enqueue_head_css', array( $this,'wc_pos_campaign_css'), 10, 1 );

		// when the order object is prepared save the campaign
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'wc_pos_campaign_prepare_shop_order_object' ), 11, 3 );


	}

	/**
	 *
	 * Saves Campaign to WC order and in the contribution in CiviCRM
	 *
	 */
	public function wc_pos_campaign_prepare_shop_order_object( $response, $order, $request ) {
		WCI()->manager->update_campaign( $response->data['id'], '', get_user_meta(get_current_user_id(),"pos_campaign_id", true) ); // save camapaign to civicrm
		update_post_meta( $response->data['id'], '_woocommerce_civicrm_campaign_id', get_user_meta(get_current_user_id(),"pos_campaign_id", true)); // save camapaign to post data for this woocommerce order
		return $response;
	}

	/**
	 *
	 * Add JS and CSS to the POS page
	 * 
	 */
	function wc_pos_campaign_js($js) {
		wp_nonce_field('woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new');
		$js['pos-campaign-js']= WOOCOMMERCE_CIVICRM_URL . 'js/pos_campaign.js';
		return $js;
	}

	function wc_pos_campaign_css($var) {
		$var['pos-campaign-css']= WOOCOMMERCE_CIVICRM_URL . 'css/pos_campaign.css';
		return $var;
	}


	/**
	 *
	 * Triggered onload and when the user changes the campaign, it save the campaign in the meta, for this user
	 *
	 */
	function set_campaign() {
		update_user_meta(get_current_user_id(),"pos_campaign_id",  $_POST['campaign_id']);
	}

	/**
	 *
	 * Triggered onload, serves the list of campaigns
	 *
	 */
	function get_campaign() {
		$order_campaign = get_user_meta(get_current_user_id(),"pos_campaign_id", true);
		if(!$order_campaign){ // if there is no campaign for this user, try the default from WP
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id', false);
			if(!$order_campaign){ // if there is no default campaign from WP, set it to none
				$order_campaign = 0;
			}
			add_user_meta(get_current_user_id(),"pos_campaign_id", $order_campaign); // there was no meta for the user at this point so create one because we will need it
		}
		$render = '
			<div class="wc-civicrmcampaign">
				<div class="list-row">
					<div>
						<select id="order_civicrmcampaign" name="order_civicrmcampaign" data-placeholder="'. __('CiviCRM Campaign', 'woocommerce-civicrm') .'">';
						foreach (WCI()->helper->campaigns as $campaign_id => $campaign_name){
							$render .= '<option value="'. $campaign_id .'" '.selected($campaign_id, $order_campaign, false).'>'. $campaign_name.'</option>'; // select by default the $order_camapaign
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
