<?php

/**
 * Woocommerce CiviCRM Settings Tab class.
 *
 * @since 2.0
 */

class Woocommerce_CiviCRM_Settings_Tab {

	/**
	 * Initialises this object.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->register_hooks();
		if(WCI()->is_network_installed){
			$this->register_settings();
		}
	}

	/**
	 * Register hooks
	 *
	 * @since 0.2
	 */
	public function register_hooks() {
		// Add Civicrm settings tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		// Add Woocommerce Civicrm settings
		add_action( 'woocommerce_settings_woocommerce_civicrm', array( $this, 'add_settings_fields' ), 10 );
		// Update Woocommerce Civicrm settings
		add_action( 'woocommerce_update_options_woocommerce_civicrm', array( $this, 'update_settings_fields' ) );
		// Update network settings
		add_action('network_admin_edit_woocommerce_civicrm_network_settings', array($this, 'trigger_network_settings') );
	}

	public function register_settings(){
		register_setting( 'woocommerce_civicrm_network_settings', 'woocommerce_civicrm_network_settings' );

		// Makes sure functions exists
        if ( ! function_exists( 'add_settings_field' ) ) {
            require_once( ABSPATH . '/wp-admin/includes/template.php' );
        }

		add_settings_section(
            'woocommerce-civicrm-settings-network-general',
                __('General settings', 'woocommerce-civicrm'),
                array($this, 'settings_section_callback'),
                'woocommerce-civicrm-settings-network'
            );

        add_settings_field(
                'woocommerce_civicrm_shop_blog_id',
                __('Main Woocommerce blog ID', 'woocommerce-civicrm'),
                array( $this, 'settings_field_select'),
                'woocommerce-civicrm-settings-network',
                'woocommerce-civicrm-settings-network-general',
                array( 'name' => 'wc_blog_id', 'network'=>true, 'description'=>__('', 'woocommerce-civicrm') , 'options'=>WCI()->helper->get_sites())
        );
	}

	public function settings_section_callback(){

	}

	public function settings_field_text($args){
        $option = 'woocommerce_civicrm_network_settings';
		$options = get_site_option($option);
        ?>
        <input
			name="<?php echo $option; ?>[<?php echo $args['name']; ?>]"
			id="<?php echo $args['name']; ?>"
			value="<?php echo (isset($options[$args['name']]) ? $options[$args['name']] : ''); ?>"
			class="regular-text"/>
        <?php if(isset($args['description']) && $args['description']): ?>
        <div class="description"><?php echo $args['description']; ?></div>
        <?php endif; ?>
        <?php
    }

	public function settings_field_select($args){
        $option = 'woocommerce_civicrm_network_settings';
		$options = get_site_option($option);
        ?>
        <select
			name="<?php echo $option; ?>[<?php echo $args['name']; ?>]"
			id="<?php echo $args['name']; ?>"
			class="regular-select">
			<?php foreach((array) $args['options'] as $key=>$option): ?>
				<option value="<?php esc_attr_e($key); ?>" <?php selected($key, isset($options[$args['name']]) ? $options[$args['name']] : '', true); ?>>
					<?php esc_attr_e($option); ?>
				</option>
			<?php endforeach; ?>
		</select>
        <?php if(isset($args['description']) && $args['description']): ?>
        <div class="description"><?php echo $args['description']; ?></div>
        <?php endif; ?>
        <?php
    }

	function trigger_network_settings(){
        if (!wp_verify_nonce(\filter_input(INPUT_POST, 'woocommerce-civicrm-settings', FILTER_SANITIZE_STRING), 'woocommerce-civicrm-settings')) {
            wp_die(__('Cheating uh?', 'woocommerce-civicrm'));
	    }
        $settings = array(
            'wc_blog_id'=>esc_attr($_POST['woocommerce_civicrm_network_settings']['wc_blog_id'])
        );
        $update = update_site_option( 'woocommerce_civicrm_network_settings', $settings);
        wp_redirect(
            add_query_arg(
                array(
					'page' => 'woocommerce-civicrm-settings',
					'confirm' => 'success'
				),
                (network_admin_url( 'settings.php' ))
            )
        );
        exit;
    }

	/**
	 * Add CiviCRM tab to the settings page.
	 *
	 * @since 2.0
	 * @uses 'woocommerce_settings_tabs_array' filter
	 * @param array $setting_tabs The setting tabs array
	 * @return array $setting_tabs The setting tabs array
	 */
	public function add_settings_tab( $setting_tabs ) {
		$setting_tabs['woocommerce_civicrm'] = __( 'CiviCRM', 'woocommerce-civicrm' );
		return $setting_tabs;
	}

	/**
	 * Add Woocommerce Civicrm settings to the Settings tab.
	 *
	 * @since 2.0
	 */
	public function add_settings_fields(){
		woocommerce_admin_fields( $this->civicrm_settings_fields() );
	}

	/**
	 * Update Woocommerce Civicrm settings.
	 *
	 * @since 2.0
	 */
	public function update_settings_fields(){
		woocommerce_update_options( $this->civicrm_settings_fields() );
	}

	/**
	 * Settings options.
	 *
	 * @since 2.0
	 * @return array $options The fields configuraion
	 */
	public function civicrm_settings_fields(){

		$options = array(
			'section_title' => array(
				'name' => __( 'CiviCRM Settings', 'woocommerce-civicrm' ),
				'type' => 'title',
				'desc' => __( 'Below are the values used when creating contribution/address in CiviCRM.', 'woocommerce-civicrm' ),
				'id' => 'woocommerce_civicrm_section_title'
			),
			'woocommerce_civicrm_financial_type_id' => array(
				'name' => __( 'Contribution Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => WCI()->helper->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_id'
				),
			'woocommerce_civicrm_campaign_id' => array(
				'name' => __( 'Default campaign', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => WCI()->helper->campaigns,
				'id'   => 'woocommerce_civicrm_campaign_id'
				),
			'woocommerce_civicrm_financial_type_vat_id' => array(
				'name' => __( 'Contribution Type VAT (Tax)', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => WCI()->helper->financial_types,
				'id'   => 'woocommerce_civicrm_financial_type_vat_id'
			),
			'woocommerce_civicrm_billing_location_type_id' => array(
				'name' => __( 'Billing Location Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => WCI()->helper->location_types,
				'id'   => 'woocommerce_civicrm_billing_location_type_id'
			),
			'woocommerce_civicrm_shipping_location_type_id' => array(
				'name' => __( 'Shipping Location Type', 'woocommerce-civicrm' ),
				'type' => 'select',
				'options' => WCI()->helper->location_types,
				'id'   => 'woocommerce_civicrm_shipping_location_type_id'
			),
			'woocommerce_civicrm_sync_contact_address' => array(
				'name' => __( 'Sync Contact address', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user address with CiviCRM\'s contact address and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_address'
			),
			'woocommerce_civicrm_sync_contact_phone' => array(
				'name' => __( 'Sync Contact billing phone', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user\'s billing phone with CiviCRM\'s contact billing phone and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_phone'
			),
			'woocommerce_civicrm_sync_contact_email' => array(
				'name' => __( 'Sync Contact billing email', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'If enabled, this option will synchronize Woocommerce user\'s billing email with CiviCRM\'s contact billing email and viceversa.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_sync_contact_email'
			),
			'woocommerce_civicrm_replace_woocommerce_states' => array(
				'name' => __( 'Replace Woocommerce States', 'woocommerce-civicrm' ),
				'type' => 'checkbox',
				'desc' => __( 'WARNING, DATA LOSS!! If enabled, this option will replace Woocommerce\'s States/Counties with CiviCRM\'s States/Provinces, you WILL lose any existing State/County data for existing Customers. Any Woocommerce Settings that relay on State/County will have te be reconfigured.', 'woocommerce-civicrm' ),
				'id'   => 'woocommerce_civicrm_replace_woocommerce_states'
      ),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => 'woocommerce_civicrm_section_end'
			)
		);

		/**
		 * Filter Woocommerce CiviCRM setting fields
		 *
		 * @since 2.0
		 * @param array $options The fields configuration
		 */
		return apply_filters( 'woocommerce_civicrm_admin_settings_fields', $options );

	}


	public function network_settings(){
        ?>
    <div class="wrap">
        <h2><?php _e('Woocommerce CiviCRM settings', 'woocommerce-civicrm' ); ?></h2>
        <?php settings_errors(); ?>
        <form action="edit.php?action=woocommerce_civicrm_network_settings" method="post">
        <?php wp_nonce_field('woocommerce-civicrm-settings', 'woocommerce-civicrm-settings'); ?>
        <?php settings_fields( 'woocommerce-civicrm-settings-network' ); ?>
        <?php do_settings_sections('woocommerce-civicrm-settings-network'); ?>
        <?php submit_button(); ?>
        </form>
    </div>
    <?php

    }
}
