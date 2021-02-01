<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		add_filter( 'manage_shop_order_posts_columns', [ $this, 'columns_head' ], 11 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'columns_content' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ $this, 'restrict_manage_orders' ], 5 );
		add_filter( 'pre_get_posts', [ $this, 'pre_get_posts' ], 100 );

	}

	/**
	 * Alters columns.
	 *
	 * @param array $defaults The defaults.
	 * @return array $columns
	 */
	public function columns_head( $defaults ) {
		$nb_cols = count( $defaults );
		$new_cols = [
			'campaign' => __( 'Campaign', 'woocommerce-civicrm' ),
			'source' => __( 'Source', 'woocommerce-civicrm' ),
		];
		$columns = array_slice( $defaults, 0, $nb_cols - 2, true )
			+ $new_cols
			+ array_slice( $defaults, $nb_cols - 2, $nb_cols, true );
		return $columns;
	}

	/**
	 * Echoes content of a row in a given column.
	 *
	 * @param string $column_name The column name.
	 * @param int $post_id The post id.
	 */
	public function columns_content( $column_name, $post_id ) {
		if ( 'campaign' === $column_name ) {
			$campaign_id = get_post_meta( $post_id, '_woocommerce_civicrm_campaign_id', true );
			if ( $campaign_id ) {
				$params = [
					'sequential' => 1,
					'return' => [ 'name' ],
					'id' => $campaign_id,
					'options' => [ 'limit' => 1 ],
				];
				try {
					$campaigns_result = civicrm_api3( 'Campaign', 'get', $params );
					echo isset( $campaigns_result['values'][0]['name'] ) ? esc_attr( $campaigns_result['values'][0]['name'] ) : '';
				} catch ( CiviCRM_API3_Exception $e ) {
					CRM_Core_Error::debug_log_message( __( 'Not able to fetch campaign', 'woocommerce-civicrm' ) );
				}
			}
		}
		if ( 'source' === $column_name ) {
			echo esc_html( get_post_meta( $post_id, '_order_source', true ) );
		}
	}

	/**
	 * Fires before the Filter button on the Posts and Pages list tables.
	 *
	 * @param string $post_type The post type.
	 */
	public function restrict_manage_orders( $post_type = '' ) {
		global $typenow;
		if ( 'shop_order' !== $typenow ) {
			return;
		}
		$campaign_list = WCI()->helper->all_campaigns;
		if ( $campaign_list && ! empty( $campaign_list ) && is_array( $campaign_list ) ) {
			$selected = filter_input( INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT );
			?>
			<select name='shop_order_campaign_id' id='dropdown_shop_order_campaign_id'>
				<option value=""><?php esc_html_e( 'All campaigns', 'woocommerce-civicrm' ); ?></option>
				<?php foreach ( $campaign_list as $campaign_id => $campaign_name ) : ?>
					<option value="<?php echo esc_attr( $campaign_id ); ?>" <?php selected( $selected, $campaign_id ); ?>>
						<?php echo esc_attr( $campaign_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		}

		global $wpdb;
		$results = $wpdb->get_results( "SELECT DISTINCT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_source'" );
		if ( count( $results ) > 0 ) {
			$selected = filter_input( INPUT_GET, 'shop_order_source' );
			?>
			<select name='shop_order_source' id='dropdown_shop_order_source'>
				<option value=""><?php esc_html_e( 'All sources', 'woocommerce-civicrm' ); ?></option>
				<?php foreach ( $results as $meta ) : ?>
					<option value="<?php echo esc_attr( $meta->meta_value ); ?>" <?php selected( $selected, $meta->meta_value ); ?>>
						<?php echo esc_attr( $meta->meta_value ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		}

	}

	/**
	 * Fires after the query variable object is created, but before the actual query is run.
	 *
	 * @param WP_Query $query The WP_Query object.
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		global $typenow;
		$campaign_id = filter_input( INPUT_GET, 'shop_order_campaign_id', FILTER_VALIDATE_INT );
		$source = filter_input( INPUT_GET, 'shop_order_source' );
		if ( 'shop_order' === $typenow && ( $campaign_id || $source ) ) {
			$mq = $query->get( 'meta_query' );
			$meta_query = false !== $mq ?
				[
					'relation' => 'AND',
					$mq,
				]
				: [];

			if ( $campaign_id ) {
				$meta_query['campaign_clause'] = [
					'key' => '_woocommerce_civicrm_campaign_id',
					'value' => $campaign_id,
					'compare' => '==',
				];
			}
			if ( $source ) {
				$meta_query['source_clause'] = [
					'key' => '_order_source',
					'value' => $source,
					'compare' => '==',
				];
			}
			$query->set( 'meta_query', $meta_query );
		}
	}

}
