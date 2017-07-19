<?php

/**
 * Plugin Name: Woocommerce CiviCRM
 * Plugin URI: http://www.vedaconsulting.co.uk
 * Description: Plugin for intergrating Woocommerce with CiviCRM
 * Author: Veda NFP Consulting Ltd
 * Version: 2.0
 * Author URI: http://www.vedaconsulting.co.uk
 * Text Domain: woocommerce-civicrm
 * Domain path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) die;

/**
 * Woocommerce CiviCRM class
 * A class that encapsulates this plugin's functionality
 * @since 2.0
 */
class Woocommerce_CiviCRM {

	/**
	 * The class instance.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $instance The class instance
	 */
	private static $instance;

	/**
	 * The Settings Tab management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $settings_tab The Settings Tab management object
	 */
	private static $settings_tab;

	/**
	 * The Manager management object.
	 *
	 * Encapsulates the Woocommerce CiviCRM functionality
	 * @since 2.0
	 * @access private
	 * @var object $manager The plugin functionality management object
	 */
	private static $manager;

    /**
	 * The Sync management object.
	 *
	 * Encapsulates the Woocommerce and CiviCRM synchrinzation objects.
	 * @since 2.0
	 * @access private
	 * @var object $sync The Sync management object
	 */
	private static $sync;

	/**
	 * The Helper management object.
	 *
	 * Encapsulates the Helper management object.
	 * @since 2.0
	 * @access private
	 * @var object $helper The Helper management object
	 */
	public static $helper;

	/**
	 * CiviCRM States/Provinces management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $states_replacement The States replacement management object
	 */
	private static $states_replacement;


	/**
	 * Returns a single instance of this object when called.
	 *
	 * @since 2.0
	 * @return object $instance Woocommerce_CiviCRM instance
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			// instantiate
			self::$instance = new Woocommerce_CiviCRM;
			// initialise if the environment allows
			if ( self::$instance->check_dependencies() ) {
				self::$instance->define_constants();
				self::$instance->include_files();
				self::$instance->setup_objects();
				self::$instance->register_hooks();
			}

			/**
			 * Broadcast to other plugins that this plugin is loaded.
			 * @since 2.0
			 */
			do_action( 'woocommerce_civicrm_loaded' );
		}
		// always return instance
		return self::$instance;
	}

	/**
	 * Define constants
	 *
	 * @since 2.0
	 */
	private function define_constants(){
		define( 'WOOCOMMERCE_CIVICRM_VER', '2.0' );
		define( 'WOOCOMMERCE_CIVICRM_URL', plugin_dir_url( __FILE__ ) );
		define( 'WOOCOMMERCE_CIVICRM_PATH', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Check plugin dependencies.
	 * @since 2.0
	 * @return bool True if dependencies exist, false otherwise
	 */
	private function check_dependencies() {
		// Bail if Woocommerce is not available
		if ( ! defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3', '>=' ) ){
			add_action( 'admin_notices', array( $this, 'display_woocommerce_required_notice' ) );
			return false;
		}
		// Bail if CiviCRM is not available
		if ( ! function_exists( 'civi_wp' ) ){
			add_action( 'admin_notices', array( $this, 'display_civicrm_required_notice' ) );
			return false;
		}
		// Bail if unable to init CiviCRM
		if ( ! civi_wp()->initialize() ){
			add_action( 'admin_notices', array( $this, 'display_civicrm_initialised_notice' ) );
			return false;
		}

		return true;

	}

	/**
	 * Include plugin files.
	 *
	 * @since 2.0
	 */
	private function include_files() {
		// Include Woocommerce CiviCRM Helper class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-helper.php';
		// Include Woocommerce settings tab class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-settings-tab.php';
		// Include Woocommerce functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-manager.php';
		// Include Address Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-sync-address.php';
		// Include Phone Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-sync-phone.php';
		// Include Email Sync functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-sync-email.php';
		// Include States replacement functionality class
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woocommerce-civicrm-states.php';
	}

	/**
	 * Set up plugin objects.
	 *
	 * @since 2.0
	 */
	private function setup_objects() {
		// init helper instance
		self::$helper = Woocommerce_CiviCRM_Helper::instance();
		// init settings page
		self::$settings_tab = new Woocommerce_CiviCRM_Settings_Tab;
		// init manager
		self::$manager = new Woocommerce_CiviCRM_Manager;
		// init states replacement
		self::$states_replacement = new Woocommerce_CiviCRM_States;
		// init address sync
		self::$sync['address'] = new Woocommerce_CiviCRM_Sync_Address;
		// init phone sync
		self::$sync['phone'] = new Woocommerce_CiviCRM_Sync_Phone;
		// init email sync
		self::$sync['email'] = new Woocommerce_CiviCRM_Sync_Email;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	private function register_hooks() {
		// use translation files
		add_action( 'plugins_loaded', array( $this, 'enable_translation' ) );
    // add settings link to plugin lisitng page
		add_filter( 'plugin_action_links', array( $this, 'add_action_links' ), 10, 2 );
	}

	/**
	 * Load translation files.
	 *
	 * Reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 2.0
	 */
	public function enable_translation() {
		// load translations if present
		load_plugin_textdomain(
			'woocommerce-civicrm', // unique name
			false, // deprecated argument
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // relative path to translation files
		);
	}

	/**
	 * Add Settings link to plugin listing page.
	 *
	 * @since 2.0
	 * @param array $links The list of plugin links
	 * @param $file The plugin file
	 * @return $links
	 */
	public function add_action_links( $links, $file ) {
		if( $file == plugin_basename( __FILE__ ) ){
			$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=woocommerce_civicrm' ) . '">' . __( 'Settings', 'woocommerce-civicrm') . '</a>';
		}
		return $links;
	}

	/**
	 * Display Woocommerce required notice.
	 *
	 * @since 2.0
	 */
	public function display_woocommerce_required_notice(){
		$class = 'notice notice-error';
		$message = __( 'Woocommerce v3.+ must be installed and activated.', 'woocommerce-civicrm' );
		sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display CiviCRM required notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_required_notice(){
		$class = 'notice notice-error';
		$message = __( 'CiviCRM must be installed and activated.', 'woocommerce-civicrm' );
		sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display CiviCRM initilised notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_initialised_notice(){
		$class = 'notice notice-error';
		$message = __( 'Error - CiviCRM could not be initilised.', 'woocommerce-civicrm' );
		sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

}

/**
 * Instantiate plugin.
 *
 * @since 2.0
 * @return object $instance The plugin instance
 */
function woocommerce_civicrm() {
	return Woocommerce_CiviCRM::instance();
}
// init Woocommerce CiviCRM
add_action( 'init', 'woocommerce_civicrm' );
