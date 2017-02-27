<h1>WooCommerce CiviCRM Settings</h1>

Below are the values used when creating contribution/address in CiviCRM. <br /><br />

<form method="POST">

	<table>
	<tr>

    <td><label for="awesome_text">Contribution type</label></td>
    <td>
    	<select name="woocommerce_civicrm_financial_type_id" id= "woocommerce_civicrm_financial_type_id" class ="required">
        <?php                        
        foreach( $financialTypes as $key => $value) { ?>                                                    
        <option value=<?php echo $key; if( $key == $woocommerce_civicrm_financial_type_id) { ?> selected="selected" <?php } ?>> <?php echo $value; ?></option>
        <?php } ?>
        </select>
    </td>

    </tr>
    <!-- Add extra Financial Type to handle purchases with VAT (TAX) -->
    <tr>
    <td><label for="awesome_text">Contribution type VAT (Tax)</label></td>	
    <td>
    	<select name="woocommerce_civicrm_financial_type_vat_id" id="woocommerce_civicrm_financial_type_vat_id" class ="required">
        <?php                        
        foreach( $financialTypesVat as $key => $value) { ?>                                                    
        <option value=<?php echo $key; if( $key == $woocommerce_civicrm_financial_type_vat_id) { ?> selected="selected" <?php } ?>> <?php echo $value; ?></option>
        <?php } ?>
        </select>
    </td>
    </tr>
    <tr>

	<td><label for="awesome_text">Billing Location Type</label></td>
    <td>
    	<select name="woocommerce_civicrm_billing_location_type_id" id= "woocommerce_civicrm_billing_location_type_id" class ="required">
        <?php                        
        foreach( $addressTypes as $key => $value) { ?>                                                    
        <option value=<?php echo $key; if( $key == $woocommerce_civicrm_billing_location_type_id) { ?> selected="selected" <?php } ?>> <?php echo $value; ?></option>
        <?php } ?>
        </select>
    </td>

    </tr>
    <tr>

    <td><label for="awesome_text">Shipping Location Type</label></td>
    <td>
    	<select name="woocommerce_civicrm_shipping_location_type_id" id= "woocommerce_civicrm_shipping_location_type_id" class ="required">
        <?php                        
        foreach( $addressTypes as $key => $value) { ?>                                                    
        <option value=<?php echo $key; if( $key == $woocommerce_civicrm_shipping_location_type_id) { ?> selected="selected" <?php } ?>> <?php echo $value; ?></option>
        <?php } ?>
        </select>
    </td>

	</tr>
    </table>

    <br />

    <input type="submit" value="Save" class="button button-primary button-large">
</form>
