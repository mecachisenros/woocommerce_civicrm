# WooCommerce CiviCRM Integration

## Installation

Step 1: Install Wordpress plugin

Install the Wordpress plugin as usual. More information about installing plugins in Wordpress - https://codex.wordpress.org/Managing_Plugins#Installing_Plugins

Step 2: Install CiviCRM extension

Install https://github.com/veda-consulting/uk.co.vedaconsulting.module.woocommercecivicrm as CiviCRM extension to view WooCommerce orders in a CiviCRM tab. More information about manually installing extensions in CiviCRM - http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions#Extensions-Installinganewextension
NOTE: The tab displays orders only for contacts having a related Wordpress user record.

## Configuration

Configure the integration settings in WP Menu >> WooCommerce CiviCRM Settings

## Functionality

1. Woocommerce orders are created as contributions in CiviCRM. Line items are not created in the contribution, but the product name x quantity are included in the 'source' field of the contribution
2. Salex tax (VAT) & Shipping cost are saved as custom data against contribution
3. Logged in users are recognised and the contribution is created against the related contact record
4. If not logged in, the plugin tries to find the contact record in CiviCRM using Dedupe rules and the contribution is created against the found contact record.
5. If the contact does not exist, a new contact record is created in CiviCRM and the contribution is created against the newly created contact record.
6. Related contact record link is added to the Woocommerce order as notes.
