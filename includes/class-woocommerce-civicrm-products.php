<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Woocommerce CiviCRM Product class.
 *
 * @since 2.2
 */
class Woocommerce_CiviCRM_Products {

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
	 * @since 2.2
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Add civicrm product settings tab.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_civicrm_product_tab' ] );
		// Add civicrm product panel template.
		add_action( 'woocommerce_product_data_panels', [ $this, 'add_civicrm_product_panel' ] );
		// Save civicrm product settings.
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_civicrm_product_settings' ] );

		add_action( 'bulk_edit_custom_box', [ $this, 'add_contribution_to_quick_edit' ], 10, 2 );

		add_action( 'manage_product_posts_custom_column', [ $this, 'columns_content' ], 90, 2 );

		// Bulk / quick edit.
		add_action( 'save_post', [ $this, 'bulk_and_quick_edit_hook' ], 10, 2 );
		add_action( 'contributions_product_bulk_and_quick_edit', [ $this, 'bulk_and_quick_edit_save_post' ], 10, 2 );

	}

	/**
	 * Adds a CiviCRM settings tab to the new/edit Product screen.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_product_data_tabs' filter.
	 *
	 * @param array $tabs The product tabs.
	 * @return array $tabs The modified product tabs.
	 */
	public function add_civicrm_product_tab( $tabs ) {

		$tabs['woocommerece_civicrm'] = [
			'label' => __( 'CiviCRM Settings', 'woocommerce-civicrm' ),
			'target'   => 'woocommerece_civicrm',
		];

		return $tabs;

	}

	/**
	 * Includes the CiviCRM settings panel to the new/edit Product screen.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_product_data_panels' action.
	 */
	public function add_civicrm_product_panel() {

		include dirname( __FILE__ ) . '/templates/html-product-data-civicrm-settings.php';

	}

	/**
	 * Adds the the CiviCRM product settings
	 * as meta before product is saved.
	 *
	 * @since 2.4
	 *
	 * @uses 'woocommerce_admin_process_product_object' action.
	 *
	 * @param WC_Product $product The product object.
	 * @return void
	 */
	public function save_civicrm_product_settings( $product ) {

		if ( isset( $_POST['woocommerce_civicrm_financial_type_id'] ) ) {
			$financial_type_id = sanitize_key( $_POST['woocommerce_civicrm_financial_type_id'] );
			$product->add_meta_data( 'woocommerce_civicrm_financial_type_id', $financial_type_id, true );
		}

		if ( isset( $_POST['woocommerce_civicrm_membership_type_id'] ) ) {
			$membership_type_id = sanitize_key( $_POST['woocommerce_civicrm_membership_type_id'] );
			$product->add_meta_data( 'woocommerce_civicrm_membership_type_id', $membership_type_id, true );
		}

	}

	/**
	 * Undocumented function
	 *
	 * @param string $column_name The column name.
	 * @param int $post_id The post id.
	 * @return void
	 */
	public function columns_content( $column_name, $post_id ) {
		if ( 'product_cat' === $column_name ) {
			$contribution_type = get_post_meta( $post_id, 'woocommerce_civicrm_financial_type_id', TRUE );
			$default_contribution_type_id = get_option( 'woocommerce_civicrm_financial_type_id' );
			$contributions_types = WCI()->helper->financial_types;
			echo '<br>' . (
				( null !== $contribution_type && isset( $contributions_types[ $contribution_type ] ) )
					? esc_html( $contributions_types[ $contribution_type ] )
					: sprintf(
						/* translators: %s: default financial type */
						__( '%s (Default)', 'woocommerce-civicrm' ),
						isset( $contributions_types[ $default_contribution_type_id ] )
							? $contributions_types[ $default_contribution_type_id ]
							: __( 'unset', 'woocommerce-civicrm' )
					)
			);
		}
	}


	/**
	 * Contribution fields for products.
	 *
	 * @return void
	 */
	public function contribution_fields_bulk() {

		echo '
			<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title">' . __( 'Contribution type', 'woocommerce-civicrm' ) . '</span>
				<span class="input-text-wrap">
				<select style="" id="_civicrm_contribution_type" name="civicrm_contribution_type" class="select short">';
		$contributions_types = WCI()->helper->financial_types;
		$options = [
			__( '— No change —', 'woocommerce-civicrm' ),
		]
		+ $contributions_types +
		[
			'exclude' => '-- ' . __( 'Exclude', 'woocommerce-civicrm' ),
		];

		foreach ( $options as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '">' . $value . '</option>';
		}
		echo '</select>
				</span>
			</label>
		</div>';
	}

	/**
	 * Add contribution to quick edit.
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_contribution_to_quick_edit( $column_name, $post_type ) {
		if ( 'product_cat' !== $column_name || 'product' !== $post_type ) {
			return;
		}
		echo '<div class="inline-edit-col-right" style="float:right;">';
		$this->contribution_fields_bulk();
		echo '</div>';
	}

	/**
	 * Offers a way to hook into save post without causing an infinite loop
	 * when quick/bulk saving product info.
	 *
	 * @since 3.0.0
	 * @param int    $post_id Post ID being saved.
	 * @param object $post Post object being saved.
	 */
	public function bulk_and_quick_edit_hook( $post_id, $post ) {
		remove_action( 'save_post', [ $this, 'bulk_and_quick_edit_hook' ] );
		do_action( 'contributions_product_bulk_and_quick_edit', $post_id, $post );
		add_action( 'save_post', [ $this, 'bulk_and_quick_edit_hook' ], 10, 2 );
	}

	/**
	 * Quick and bulk edit saving.
	 *
	 * @param int    $post_id Post ID being saved.
	 * @param object $post Post object being saved.
	 * @return void
	 */
	public function bulk_and_quick_edit_save_post( $post_id, $post ) {
		if ( isset( $_GET['civicrm_contribution_type'] ) ) {
			$civicrm_contribution_type = sanitize_text_field( $_GET['civicrm_contribution_type'] );
			update_post_meta( $post_id, '_civicrm_contribution_type', $civicrm_contribution_type );
		}
	}

}
