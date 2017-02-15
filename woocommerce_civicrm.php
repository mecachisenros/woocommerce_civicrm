<?php
/*
    Plugin Name: Woocommerce CiviCRM
    Plugin URI: http://www.vedaconsulting.co.uk
    Description: Plugin for intergrating Woocommerce with CiviCRM
    Author: Veda NFP Consulting Ltd
    Version: 1.0
    Author URI: http://www.vedaconsulting.co.uk
    */

add_action('admin_init', 'woocommerce_civicrm_check_parent_plugin');
add_action('woocommerce_checkout_order_processed', 'woocommerce_civicrm_action_order', 10, 1);
add_action('admin_menu', 'woocommerce_civicrm_settings_page');

//action to fire on order status change hook
add_action( 'woocommerce_order_status_changed', 'woocommerce_civicrm_change_order', 99, 3 );

function woocommerce_civicrm_change_order( $order_id, $old_status, $new_status ) {
    woocommerce_civicrm_update_order($order_id); 
}

function woocommerce_civicrm_settings_page() {
    $page_title = 'WooCommerce CiviCRM Settings';
    $menu_title = 'WooCommerce CiviCRM Settings';
    $capability = 'edit_posts';
    $menu_slug = 'woocommerce_civicrm_settings';
    $function = 'woocommerce_civicrm_settings_page_display';
    $icon_url = '';
    $position = 56;

    add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
}


function woocommerce_civicrm_settings_page_display() {

  if (!civicrm_wp_initialize()) {
    return;
  }

  // For testing
  //$order = new WC_Order( 1 );
  //echo $order->get_subtotal();

  // Get Financial types
  $financialTypes = array();
  $financialTypesResult = civicrm_api3('FinancialType', 'get', array(
    'sequential' => 1,
  ));
  foreach($financialTypesResult['values'] as $key => $value) {
    $financialTypes[$value['id']] = $value['name'];
  }

  // Get address types
  $addressTypes = array();
  $addressTypesResult = civicrm_api3('address', 'getoptions', array('field' => 'location_type_id'));
  $addressTypes = $addressTypesResult['values'];

  $fields = array(
    'woocommerce_civicrm_financial_type_id',
    'woocommerce_civicrm_billing_location_type_id',
    'woocommerce_civicrm_shipping_location_type_id',
    );

  foreach ($fields as $field) {
    if (isset($_POST[$field])) {
      update_option($field, $_POST[$field]);
      $value = $_POST[$field];
    } 
    $$field = get_option($field, '');    
  }

  include 'woocommerce-civicrm-settings.php';
}

/**
 * Function to check if Woocommerce plugin is installed and active
 */
function woocommerce_civicrm_check_parent_plugin() {
	if ( is_admin() && current_user_can( 'activate_plugins' ) && !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
    add_action( 'admin_notices', 'woocommerce_civicrm_parent_plugin_required_notice' );

    deactivate_plugins( plugin_basename( __FILE__ ) ); 

    if ( isset( $_GET['activate'] ) ) {
        unset( $_GET['activate'] );
    }
	}
}

/**
 * Function to display warning, if Woocommerce plugin is not installed/active
 */
function woocommerce_civicrm_parent_plugin_required_notice(){
    ?><div class="error"><p>Woocommerce CiviCRM Plugin requires the Woocommerce plugin to be installed and active.</p></div><?php
}

/**
 * Action called when order is created in Woocommenrce
 *
 * @param int $order_id
 *   the order id
 */

function woocommerce_civicrm_action_order($order_id) {

	if (!civicrm_wp_initialize()) {
    return;
  }

  $order = new WC_Order( $order_id );

	$cid = _woocommerce_civicrm_get_cid($order);
  if ($cid === FALSE) {
    return;
  }

  $cid = _woocommerce_civicrm_add_update_contact($cid, $order);

  if ($cid === FALSE) {
    return;
  }

  // Add the contribution record.
  _woocommerce_civicrm_add_contribution($cid, $order);

	return $order_id;
}

/**
 * Action called when order is updated in Woocommenrce
 *
 * @param int $order_id
 *   the order id
 */


function woocommerce_civicrm_update_order($order_id) {

  if (!civicrm_wp_initialize()) {
    return;
  }

  $order = new WC_Order( $order_id );

  $params = array(
    'invoice_id' => $order_id . '_woocommerce',
    'return' => 'id'
  );

  try{
    $contribution = civicrm_api3('contribution', 'getsingle', $params);
    //$cid = $contribution['contact_id'];
  }
  catch (Exception $e) {
    CRM_Core_Error::debug_log_message('Not able to find contribution');
    return;
  }

  //CRM_Core_Error::debug_log_message('Update order');

  // Update contribution
  try {
    $params = array(
      //'contact_id' => $cid,
      'contribution_status_id' => _woocommerce_civicrm_map_contribution_status($order->post_status),
      'id' => $contribution['id'],
    );
    $result = civicrm_api3('contribution', 'create', $params);
  }
  catch (Exception $e) {
    CRM_Core_Error::debug_log_message('Not able to update contribution');
    return;
  }
}

/*
 * Function to create/update CiviCRM contact during order 
 */
function _woocommerce_civicrm_add_update_contact($cid, $order) {

  if (!civicrm_wp_initialize()) {
    return;
  }

  $action = 'create';

  //try {
    $contact = array();
    if ($cid != 0) {
      try {
        $params = array(
          'contact_id' => $cid,
          'return' => array('id', 'source', 'first_name', 'last_name'),
        );
        $contact = civicrm_api3('contact', 'getsingle', $params);
      }
      catch (CiviCRM_Exception $e) {
        CRM_Core_Error::debug_log_message('Not able to find contact');
        return FALSE;
      }
    }

    // Create contact
    // Prepare array to update contact via civi API.
    $email = '';
    $fname = '';
    $lname = '';
    $cid = '';
    if (!empty($order->shipping_email)) {
      $email = $order->shipping_email;
      $fname = $order->shipping_first_name;
      $lname = $order->shipping_last_name;
    } else {
      $email = $order->billing_email;
      $fname = $order->billing_first_name;
      $lname = $order->billing_last_name;
    }

    // Try to get contact Id using dedupe
    $contact['first_name'] = $fname;
    $contact['last_name'] = $lname;
    $contact['email'] = $email;
    $dedupeParams = CRM_Dedupe_Finder::formatParams($contact, 'Individual');
    $dedupeParams['check_permission'] = FALSE;
    $ids = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual', 'Unsupervised');
    if ($ids) {
      $cid = $ids['0'];
      $action = 'update';
    }

    $contact['sort_name'] = "{$lname}, {$fname}";
    $contact['display_name'] = "{$fname} {$lname}";
    if (!$cid) {
      $contact['contact_type'] = 'Individual';
    }
    
    if (isset($contact['contact_subtype'])) {
      unset($contact['contact_subtype']);
    }
    if (empty($contact['source'])) {
      $contact['source'] = 'Woocommerce purchase';
    }

    //CRM_Core_Error::debug_var('Contact', $contact);

    // Create contact or update existing contact.
    //if ($contact_status == 'new' && !$contact_info['values'][$cid]['first_name'] && !$contact_info['values'][$cid]['last_name']) {
      try {
        $result = civicrm_api3('contact', 'create', $contact);
        $cid = $result['id'];
        $name = trim($contact['display_name']);
        $name = !empty($name) ? $contact['display_name'] : $cid;
        $admin_url = get_admin_url();
        $contact_url = "<a href='".$admin_url."admin.php?page=CiviCRM&q=civicrm/contact/view&reset=1&cid=".$cid."'>View</a>";

        // Add order note
        if ($action == 'update') {
          $note = 'CiviCRM Contact Updated - '.$contact_url;
        } else {
          $note = 'Created new CiviCRM Contact - '.$contact_url;
        }
        $order->add_order_note( $note );
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message('Not able to create/update contact');
        return FALSE;
      }
    //}

    //$loc_types = $GLOBALS["WooCommerceCiviCRMLocationTypes"];

    $woocommerce_billing = $loc_types['woocommerce-billing'];
    $woocommerce_shipping = $loc_types['woocommerce-shipping'];
    $civicrm_billing = get_option('woocommerce_civicrm_billing_location_type_id', 5);
    $civicrm_shipping = get_option('woocommerce_civicrm_shipping_location_type_id', 1);
    
    try {
      $existing_addresses = civicrm_api3('address', 'get', array('contact_id' => $cid));
      $existing_addresses = $existing_addresses['values'];
      $existing_phones = civicrm_api3('phone', 'get', array('contact_id' => $cid));
      $existing_phones = $existing_phones['values'];
      $shipping_address = $billing_address = array('contact_id' => $cid);
      $address_types = array('billing', 'shipping');

      foreach ($address_types as $address_type) {

        // Process Phone
        $phone_exists = FALSE;
        if (!empty($order->{$address_type . '_phone'})) {
          $phone = array(
            'phone_type_id' => 1,
            'location_type_id' => ${'civicrm_' . $address_type},
            'phone' => $order->{$address_type . '_phone'},
            'contact_id' => $cid,
          );
          foreach ($existing_phones as $existing_phone) {
            if ($existing_phone['location_type_id'] == ${'civicrm_' . $address_type}) {
              $phone['id'] = $existing_phone['id'];
            }
            if ($existing_phone['phone'] == $phone['phone']) {
              $phone_exists = TRUE;
            }
          }
          if (!$phone_exists) {
            civicrm_api3('phone', 'create', $phone);

            $note = "Created new CiviCRM Phone of type {$address_type}: {$phone['phone']}";
            $order->add_order_note( $note );
          }
        }

        // Process Address
        $address_exists = FALSE;
        if (!empty($order->{$address_type . '_address_1'}) && !empty($order->{$address_type . '_postcode'})) {
          
          // Get country id
          $country_id = _woocommerce_civicrm_get_country_id($order->{$address_type . '_country'});

          $address = array(
            'location_type_id'       => ${'civicrm_' . $address_type},
            'city'                   => $order->{$address_type . '_city'},
            'postal_code'            => $order->{$address_type . '_postcode'},
            'name'                   => $order->{$address_type . '_company'},
            'street_address'         => $order->{$address_type . '_address_1'},
            'supplemental_address_1' => $order->{$address_type . '_address_2'},
            'country'                => $country_id,
            'contact_id'             => $cid,
          );
          
          foreach ($existing_addresses as $existing) {
            if ($existing['location_type_id'] == ${'civicrm_' . $address_type}) {
              $address['id'] = $existing['id'];
            }
            // @TODO Don't create if exact match of another - should
            // we make 'exact match' configurable.
            elseif (
              $existing['street_address'] == $address['street_address']
              && CRM_Utils_Array::value('supplemental_address_1', $existing) == CRM_Utils_Array::value('supplemental_address_1', $address)
              && $existing['city'] == $address['city']
              && $existing['postal_code'] == $address['postal_code']
            ) {
              $address_exists = TRUE;
            }
          }
          if (!$address_exists) {
            civicrm_api3('address', 'create', $address);

            $note = "Created new CiviCRM Address of type {$address_type}: {$address['street_address']}";
            $order->add_order_note( $note );
          }
        }
      }

    } catch (CiviCRM_Exception $e) {
      CRM_Core_Error::debug_log_message('Not able to add/update address or phone');
    }
    return $cid;
  //} catch (Exception $e) {
  //  CRM_Core_Error::debug_log_message('Not able to process contact');
  //  return FALSE;
  //}
}

/**
 * Fuction to add a contribution record.
 */
function _woocommerce_civicrm_add_contribution($cid, &$order) {

	$txn_id = 'Woocommerce Order - '.$order->id;
  $invoice_id = $order->id . '_woocommerce';

  _woocommerce_civicrm_create_custom_contribution_fields();

  $sales_tax_field_id = 'custom_' . get_option('woocommerce_civicrm_sales_tax_field_id', '');
  $shipping_cost_field_id = 'custom_' . get_option('woocommerce_civicrm_shipping_cost_field_id', '');

  $sales_tax = $order->get_total_tax();
  $sales_tax = number_format($sales_tax, 2);

  $shipping_cost = $order->order_shipping;
  $shipping_cost = number_format($shipping_cost, 2);

	// @FIXME Landmine. CiviCRM doesn't seem to accept financial values
  // with precision greater than 2 digits after the decimal.
  $rounded_total = round($order->order_total * 100) / 100;

  $contribution_type_id = get_option('woocommerce_civicrm_financial_type_id', 1);

	$params = array(
    'contact_id' => $cid,
    'total_amount' => $rounded_total,
    // Need to be set in admin page
    'contribution_type_id' => $contribution_type_id,
    'payment_instrument_id' => _woocommerce_civicrm_map_payment_instrument($order->payment_method),
    'non_deductible_amount' => 00.00,
    'fee_amount' => 00.00,
    'total_amount' => $rounded_total,
    //'net_amount' => $order->get_subtotal(),
    'trxn_id' => $txn_id,
    'invoice_id' => $invoice_id,
    'source' => _woocommerce_civicrm_create_detail_string($order),
    'receive_date' => 'now',
    'contribution_status_id' => _woocommerce_civicrm_map_contribution_status($order->post_status),
    'note' => _woocommerce_civicrm_create_detail_string($order),
    "$sales_tax_field_id" => $sales_tax,
    "$shipping_cost_field_id" => $shipping_cost,
  );
  try {
    $contribution = civicrm_api3('Contribution', 'create', $params);

    // Process line items
    //if (!empty($contribution['id'])) {
    //  _woocommerce_civicrm_process_line_items($order, $contribution['id']);
    //}
  }
  catch (Exception $e) {
    // Log the error, but continue.
    return FALSE;
  }

  return TRUE;
}


/**
 * Get contact id for the order's customer.
 *
 * @param object $order
 *   Wordpress Order Object
 *
 * @return false|integer
 *   > 0: existing contact
 *   = 0: create new contact
 *   FALSE: error
 */
function _woocommerce_civicrm_get_cid($order) {

	$user_id = get_current_user_id();
	if ($user_id > 0) {
		// Logged in user
		global $current_user;
    get_currentuserinfo();
		$match = CRM_Core_BAO_UFMatch::synchronizeUFMatch($current_user, $current_user->ID, $current_user->user_email, 'WordPress', FALSE, 'Individual');
    if (!is_object($match)) {
      return FALSE;
    }
    return $match->contact_id;
	} 

  // The customer is anonymous.  Look in the CiviCRM contacts table for a
  // contact that matches the billing email.
  $params = array(
    'email' => $order->billing_email,
    'return.contact_id' => TRUE,
    'sequential' => 1,
  );
  try{
    $contact = civicrm_api3('contact', 'get', $params);
  }
  catch (Exception $e) {
    return FALSE;
  }

  // No matches found, so we will need to create a contact.
  if (count($contact) == 0) {
    return 0;
  }
  $cid = $contact['values'][0]['id'];
  return $cid;
}


/**
 * Maps Woocommerce payment method to CiviCRM payment instrument.
 *
 * @param string $payment_method
 *   Woocommerce payment method
 *
 * @return int
 *   CiviCRM payment processor ID
 */
function _woocommerce_civicrm_map_payment_instrument($payment_method) {
  $map = array(
  	"paypal" 	=> 1,
  	"cod"  		=> 3,
  	"cheque"  => 4,
    "bacs" 		=> 5,
  );

  if (array_key_exists($payment_method, $map)) {
    $id = $map[$payment_method];
  }
  else {
    // Another Woocommerce payment method - good chance this is credit.
    $id = 1;
  }

  return $id;
}

/**
 * Create string to insert for purchase activity details.
 */
function _woocommerce_civicrm_create_detail_string($order) {
	$items = $order->get_items();

  $str = '';
  $n = 1;
  foreach ($items as $item) {
    if ($n > 1) {
      $str .= ', ';
    }
    $str .= $item['name'].' x '.$item['item_meta']['_qty'][0];
    $n++;
  }

  return $str;
}

/*
 * Function to add line items for contribution
 */
function _woocommerce_civicrm_process_line_items($order, $contributionId) {

  if (empty($contributionId)) {
    return;
  }

  $items = $order->get_items();
  //CRM_Core_Error::debug_var('LineItems', $items);

  foreach ($items as $item) {

    //$subtotal = $item['item_meta']['_line_total'];
    //$qty = $item['item_meta']['_qty'];
    //$unitprice = $subtotal / $qty;
    //$unitprice = round($unitprice * 100) / 100;

    $params  = array(
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contributionId,
      'contribution_id' => $contributionId,
      'price_field_id' => WOOCOMMERCE_CIVICRM_PRICE_FIELD_ID,
      'label' => $item['name'],
      'qty' => $item['item_meta']['_qty'],
      'unit_price' => $item['item_meta']['_line_total'],
      'line_total' => $item['item_meta']['_line_total'],
      'participant_count' => "0",
      'price_field_value_id' => WOOCOMMERCE_CIVICRM_PRICE_FIELD_VALUE_ID,
      'financial_type_id' => WOOCOMMERCE_CIVICRM_CONTRIBUTION_TYPE_ID,
      'deductible_amount' => "0.00"
    );

    try {
      $result = civicrm_api3('LineItem', 'create', $params);
      //CRM_Core_Error::debug_var('LineItem', $result);
    } catch (Exception $e) {
      return;
    }
  }
}

/**
 * Maps WooCommerce order status to CiviCRM contribution status.
 *
 * @param string $order_status
 *   WooCommerce order status
 *
 * @return int
 *   CiviCRM Contribution status
 */
function _woocommerce_civicrm_map_contribution_status($order_status) {
  $map = array(
  	'wc-completed'  => 1,
		'wc-pending'    => 2,
		'wc-cancelled'  => 3,
		'wc-failed'     => 4,
		'wc-processing' => 5,
		'wc-on-hold'    => 5,
		'wc-refunded'   => 7,
	);

  if (array_key_exists($order_status, $map)) {
    $id = $map[$order_status];
  }
  else {
    // Oh no.
    $id = 1;
  }

  return $id;
}

/*
 * Function to get CiviCRM country ID for Woocommerce country ISO Code
 */
function _woocommerce_civicrm_get_country_id($woocommerce_country) {
  // Get Woocommerce countries list
  //$country = new WC_Countries();
  //$countries = $country->get_countries();

  //$iso_code = $countries[''];

  if (empty($woocommerce_country)) {
    return;
  }

  $result = civicrm_api3('Country', 'getsingle', array(
    'sequential' => 1,
    'iso_code' => $woocommerce_country,
  ));

  if ($result['id']) {
    return $result['id'];
  } else {
    // Default to GB, if empty
    return 1226;
  }
}

/*
 * Function to create sales tax and shipping cost custom fields for contribution
 */
function _woocommerce_civicrm_create_custom_contribution_fields() {
  $group_id = get_option('woocommerce_civicrm_contribution_group_id', FALSE);
  if ($group_id != FALSE) {
    return;
  }

  // First we need to check if the VAT and Shipping custom fields have
  // already been created.
  $params = array(
    'title'            => 'Woocommerce Purchases',
    'name'             => 'Woocommerce_purchases',
    'extends'          => array('Contribution'),
    'weight'           => 1,
    'collapse_display' => 0,
    'is_active'        => 1,
  );
  try {
    $custom_group = civicrm_api3('custom_group', 'create', $params);
  }
  catch (CiviCRM_API3_Exception $e) {
    CRM_Core_Error::debug_log_message('Not able to create custom group');
  }
  add_option('woocommerce_civicrm_contribution_group_id', $custom_group['id']);

  $params = array(
    'custom_group_id' => $custom_group['id'],
    'label'           => 'Sales tax',
    'html_type'       => 'Text',
    'data_type'       => 'String',
    'weight'          => 1,
    'is_required'     => 0,
    'is_searchable'   => 0,
    'is_active'       => 1,
  );
  $tax_field = civicrm_api3('custom_field', 'create', $params);
  add_option('woocommerce_civicrm_sales_tax_field_id', $tax_field['id']);

  $params = array(
    'custom_group_id' => $custom_group['id'],
    'label'           => 'Shipping Cost',
    'html_type'       => 'Text',
    'data_type'       => 'String',
    'weight'          => 2,
    'is_required'     => 0,
    'is_searchable'   => 0,
    'is_active'       => 1,
  );
  $shipping_field = civicrm_api3('custom_field', 'create', $params);
  add_option('woocommerce_civicrm_shipping_cost_field_id', $shipping_field['id']);
}

?>
