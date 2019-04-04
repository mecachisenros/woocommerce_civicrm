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
    add_filter( 'pre_get_posts', array($this , 'pre_get_posts' ), 100);

  }

  /**
   * alters columns
   * @param array $defaults
   * @return array
   */
    public function columns_head($defaults) {
      $nb_cols = count($defaults);
      $new_cols = array(
        'campaign' => __('Campaign', 'woocommerce-civicrm'),
        'source' => __('Source', 'woocommerce-civicrm'),
      );
      $columns = array_slice($defaults, 0, $nb_cols-2, true) +
      $new_cols +
      array_slice($defaults, $nb_cols-2, $nb_cols, true) ;
      return $columns;
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
        $campaign_list = WCI()->helper->all_campaigns;
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

        global $wpdb;
        $results = $wpdb->get_results("SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_source'");
        if(count($results)>0){
          $selected = filter_input(INPUT_GET, 'shop_order_source');
          ?>
          <select name='shop_order_source' id='dropdown_shop_order_source'>
            <option value=""><?php _e('All sources', 'woocommerce-civicrm'); ?></option>
            <?php foreach ($results as $meta): ?>
              <option value="<?php echo esc_attr($meta->meta_value); ?>" <?php selected($selected, $meta->meta_value); ?>>
                <?php echo esc_attr($meta->meta_value); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php
        }

    }

    public static function pre_get_posts($query)
    {
      global $typenow;
      $campaign_id = filter_input(INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT);
      $source = filter_input(INPUT_GET, 'shop_order_source');
      if ( $typenow == 'shop_order' && ($campaign_id || $source) ) {
        $meta_query = (false != $mq = $query->get('meta_query')) ? array(
          'relation' => 'AND',
          $mq,
        ) : array();
        if($campaign_id){
          $meta_query['campaign_clause'] = array(
              'key' => '_woocommerce_civicrm_campaign_id',
              'value' => $campaign_id,
              'compare' => '==',
          );
        }
        if($source){
          $meta_query['source_clause'] = array(
              'key' => '_order_source',
              'value' => $source,
              'compare' => '==',
          );
        }
        $query->set( 'meta_query', $meta_query );
      }
      return $query;
    }

}
