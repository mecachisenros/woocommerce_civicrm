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
    add_action('restrict_manage_posts', array($this, 'restrict_manage_orders'), 5);
    add_filter( 'pre_get_posts', array($this , 'pre_get_posts_campaign' ), 100);

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

    function restrict_manage_orders($value = ''){
        global $woocommerce, $typenow;
        if ('shop_order' != $typenow) {
            return;
        }
        $campaign_list = WCI()->helper->campaigns;
        if ($campaign_list && !empty($campaign_list) && is_array($campaign_list)) {
            $selected = filter_input(INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT);
            ?>
            <select name='shop_order_campaign_id' id='dropdown_shop_order_campaign_id'>
                <option value=""><?php _e('All campaigns', 'woocommerce-civicrm'); ?></option>
                <?php foreach ($campaign_list as $campaign_id => $campaign_name): ?>
                <option value="<?php echo $campaign_id; ?>" <?php selected($selected, $campaign_id); ?>>
                  <?php echo esc_attr($campaign_name); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php
        }

    }

    public static function pre_get_posts_campaign($query)
    {
      global $typenow;
      if ( $typenow == 'shop_order' && (false != $campaign_id = filter_input(INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT)) ) {
        $meta_query = (false != $mq = $query->get('meta_query')) ? array(
          'relation' => 'AND',
          $mq,
        ) : array();
        $meta_query['campaign_clause'] = array(
            'key' => '_woocommerce_civicrm_campaign_id',
            'value' => $campaign_id,
            'compare' => '==',
        );
        $query->set( 'meta_query', $meta_query );
      }
      return $query;
    }

}
