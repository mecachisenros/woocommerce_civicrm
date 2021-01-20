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
		add_action( 'wp_ajax_woocommerce_civicrm_set_datas_from_pos', [ $this, 'pos_set_datas' ] );
		add_action( 'wp_ajax_nopriv_set_campaign', [ $this, 'set_campaign' ] );

		// get the campaign list from civicrm
		add_action( 'wp_ajax_woocommerce_civicrm_get_datas_for_pos', [ $this, 'pos_get_datas' ] );
		add_action( 'wp_ajax_nopriv_get_campaign', [ $this, 'get_campaign' ] );

		// Enqueue JS and CSS for the campaign list
		add_filter( 'woocommerce_pos_enqueue_footer_js', [ $this, 'wc_pos_campaign_js' ], 10, 1 );
		add_filter( 'woocommerce_pos_enqueue_head_css', [ $this, 'wc_pos_campaign_css' ], 10, 1 );

		// when the order object is prepared save the campaign
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'wc_pos_campaign_prepare_shop_order_object' ], 11, 3 );

	}

	/**
	 *
	 * Saves Campaign to WC order and in the contribution in CiviCRM
	 *
	 */
	public function wc_pos_campaign_prepare_shop_order_object( $response, $order, $request ) {
		WCI()->manager->update_campaign( $response->data['id'], '', get_user_meta( get_current_user_id(), 'pos_campaign_id', true ) ); // save camapaign to civicrm
		update_post_meta( $response->data['id'], '_woocommerce_civicrm_campaign_id', get_user_meta( get_current_user_id(), 'pos_campaign_id', true ) ); // save camapaign to post data for this woocommerce order
		return $response;
	}

	/**
	 *
	 * Add JS and CSS to the POS page
	 *
	 */
	public function wc_pos_campaign_js( $js ) {
		wp_nonce_field( 'woocommerce_civicrm_order_new', 'woocommerce_civicrm_order_new' );
		$js['pos-campaign-js'] = WOOCOMMERCE_CIVICRM_URL . 'js/pos_campaign.js';
		return $js;
	}

	public function wc_pos_campaign_css( $var ) {
		$var['pos-campaign-css'] = WOOCOMMERCE_CIVICRM_URL . 'css/pos_campaign.css';
		return $var;
	}


	/**
	 *
	 * Triggered onload and when the user changes the campaign, it save the campaign in the meta, for this user
	 *
	 */
	public function pos_set_datas() {
		$campaign_id = esc_attr( $_POST['campaign_id'] );
		$source = esc_attr( $_POST['source'] );
		update_user_meta( get_current_user_id(), 'pos_campaign_id', $campaign_id );
		update_user_meta( get_current_user_id(), 'pos_source',  $source );
		wp_send_json(
			[
				'campaign_id' => $campaign_id,
				'source' => $source,
			]
		);
		exit;
	}

	/**
	 *
	 * Triggered onload, serves the list of campaigns
	 *
	 */
	public function pos_get_datas() {
		$order_campaign = get_user_meta( get_current_user_id(), 'pos_campaign_id', true );
		if ( ! $order_campaign ) { // if there is no campaign for this user, try the default from WP
			$order_campaign = get_option( 'woocommerce_civicrm_campaign_id', false );
			if ( ! $order_campaign ) { // if there is no default campaign from WP, set it to none
				$order_campaign = 0;
			}
			add_user_meta( get_current_user_id(), 'pos_campaign_id', $order_campaign ); // there was no meta for the user at this point so create one because we will need it
		}
		$order_source = get_user_meta( get_current_user_id(), 'pos_source', true );
		if ( ! $order_source ) {
			$order_source = '';
			add_user_meta( get_current_user_id(), 'pos_source', $order_source );
		}
		$render = '
			<div class="wc-civicrmcampaign">
				<div class="list-row">
					<div>
						<select id="order_civicrmcampaign" name="order_civicrmcampaign" placeholder="' . __( 'CiviCRM Campaign', 'woocommerce-civicrm' ) . '">';
						foreach ( WCI()->helper->campaigns as $campaign_id => $campaign_name ) {
							$render .= '<option value="' . $campaign_id . '" ' . selected( $campaign_id, $order_campaign, false ) . '>' . $campaign_name.'</option>'; // select by default the $order_camapaign
						}
						$render .= '</select>
					</div>
					<div>
						<label for="order_civicrmcampaign">' . __( 'CiviCRM Campaign', 'woocommerce-civicrm' ).'</label>
					</div>
				</div>
				<div class="list-row">
					<div>
						<input type="text" list="sources" id="order_civicrmsource" name="order_civicrmsource" placeholder="' . __( 'CiviCRM Source', 'woocommerce-civicrm' ) . '" value="' . $order_source . '">
						<datalist id="sources">';
						global $wpdb;
						$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_source'" );
						if ( count( $results ) > 0 ) {
							foreach ( $results as $meta ) {
								$render .= '<option value="' . $meta->meta_value . '">';
							}
						}
						$render .= '</datalist>
					</div>
					<div>
						<label for="order_civicrmsource">' . __( 'CiviCRM Source', 'woocommerce-civicrm' ) . '</label>
					</div>
				</div>
			</div>
			';

		echo $render;
		exit(); // this is required to terminate immediately and return a proper response
	}

}
