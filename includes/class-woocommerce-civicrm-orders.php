<?php
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Woocommerce CiviCRM Orders class.
 *
 * @since 2.2
 */

class Woocommerce_CiviCRM_Orders {

  /**
   * Initialises this object.
   *
   * @since 2.0
   */
  public function __construct() {
    $this->register_hooks();
  }

  public function register_hooks(){

    add_filter('manage_shop_order_posts_columns', array(&$this, 'columns_head'), 11);
    add_action('manage_shop_order_posts_custom_column', array(&$this, 'columns_content'), 10, 2);

  }

  /**
   * alters columns
   * @param array $defaults
   * @return array
   */
    public function columns_head($defaults) {
      $defaults['campaign'] = __('Campaign', 'woocommerce-civicrm');
      $defaults['source'] = __('Source', 'woocommerce-civicrm');
      return $defaults;
    }

    /**
     * echoes content of a row in a given column
     * @param string $column_name
     * @param int $post_id
     */
    public function columns_content($column_name, $post_id) {
      if ($column_name == 'campaign') {
        $campaign_id =  get_post_meta($post_id, '_woocommerce_civicrm_campaign_id', true);
        if($campaign_id){
          $params = array(
            'sequential' => 1,
            'return' => array("name"),
            'id' => $campaign_id,
            'options' => array('limit' => 1),
          );
          try{
            $campaignsResult = civicrm_api3( 'Campaign', 'get', $params );
            echo isset($campaignsResult['values'][0]['name']) ? $campaignsResult['values'][0]['name'] : '';
          } catch ( CiviCRM_API3_Exception $e ){
            CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
          }
        }
      }
      if ($column_name == 'source') {
        echo get_post_meta($post_id, '_order_source', true);
      }
    }

}



?>
