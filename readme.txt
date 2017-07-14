=== Woocommerce CiviCRM ===
Contributors: veda-consulting, mecachisenros, rajeshrhino, JoeMurray, kcristiano, cdhassell
Tags: civicrm, woocommerce, integration
Requires at least: 4.5
Tested up to: 4.8
Stable tag: 2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrates CiviCRM with Woocommerce.


== Description ==

1. Woocommerce orders are created as contributions in CiviCRM. Line items are not created in the contribution, but the product name x quantity are included in the 'source' field of the contribution
2. Salex tax (VAT) & Shipping cost are saved as custom data against contribution
3. Logged in users are recognised and the contribution is created against the related contact record
4. If not logged in, the plugin tries to find the contact record in CiviCRM using Dedupe rules and the contribution is created against the found contact record.
5. If the contact does not exist, a new contact record is created in CiviCRM and the contribution is created against the newly created contact record.
6. Related contact record link is added to the Woocommerce order as notes.
7. Option to sync CiviCRM and Woocommerce address, if a user edits his/hers address (only address, not billing phone/email) through the Woocommerce Account >> Edit Address page, CiviCRM profile, or through CiviCRM backoffice, the addresses will be updated in both CiviCRM and Woocommerce.

### Requirements

This plugin requires a minimum of *CiviCRM 4.6* and *Woocommerce 3.0+*.

### Configuration

Configure the integration settings in Woocommerce Menu >> Settings >> CiviCRM (Tab)
Direct URL: https://example.com/wp-admin/admin.php?page=wc-settings&tab=woocommerce_civicrm



== Installation ==

Step 1: Install Wordpress plugin

Install this Wordpress plugin as usual. More information about installing plugins in Wordpress - https://codex.wordpress.org/Managing_Plugins#Installing_Plugins

Step 2: Install CiviCRM extension

Extension is compatible with CiviCRM v4.6+.

Install https://github.com/veda-consulting/uk.co.vedaconsulting.module.woocommercecivicrm as CiviCRM extension to view WooCommerce orders in a CiviCRM tab. More information about manually installing extensions in CiviCRM - http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions#Extensions-Installinganewextension NOTE: The tab displays orders only for contacts having a related Wordpress user record.



== Changelog ==

= 2.0 =
* Plugin refactored
* Moved Settings page to Woocommerce -> Settings -> CiviCRM (Tab)
* Added translation support
* Added 'woocommerce_civicrm_contribution_create_params' filter
* Added 'woocommerce_civicrm_contribution_update_params' filter
* Added 'woocommerce_civicrm_financial_types_params' filter
* Added 'woocommerce_civicrm_admin_settings_fields' filter
* Added 'woocommerce_civicrm_address_map' filter
* Added 'woocommerce_civicrm_mapped_location_types' filter
* Added 'woocommerce_civicrm_wc_address_updated' action
* Added 'woocommerce_civicrm_wc_address_updated' action


= 1.0 =

* Initial release
