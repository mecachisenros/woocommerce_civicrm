=== Woocommerce CiviCRM ===
Contributors: veda-consulting, mecachisenros, rajeshrhino, JoeMurray, kcristiano, cdhassell, bastho
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
7. Option to sync CiviCRM and Woocommerce address, billing phone, and billing email. If a user edits his/hers address, billing phone, or billing email through the Woocommerce Account >> Edit Address page, CiviCRM profile, or through CiviCRM's backoffice, the data will be updated in both CiviCRM and Woocommerce.
8. Option to replace Woocommerce's States/Counties list with CiviCRM's State/Province list. (WARNING!!! Enabling this option in an exiting Woocommerce instance will cause State/Couny data loss for exiting Customers and Woocommerce settings that relay on those.)

### Requirements

This plugin requires a minimum of *CiviCRM 4.6* and *Woocommerce 3.0+*.

### Configuration

Configure the integration settings in Woocommerce Menu >> Settings >> CiviCRM (Tab)
Direct URL: https://example.com/wp-admin/admin.php?page=wc-settings&tab=woocommerce_civicrm



== Installation ==

Step 1: Install Wordpress plugin

Install this Wordpress plugin as usual. More information about installing plugins in Wordpress - https://codex.wordpress.org/Managing_Plugins#Installing_Plugins


== Changelog ==

= 2.2 =
* Added Campaign support for contributions
    * UTM support (utm_campaign, utm_source and utm_medium)
* Added Multisite support
* Updated contribution source: default to order type. Contribution source was the same as contrinution note
* Fixed number format for contribution amount must match CiviCRM Settings
* Fixed i18n
* Added French L10n

= 2.1 =
* More refactoring
* Minor fixes
* The Order tab is rendered from this plugin, there's no need for the CiviCRM extension

= 2.0 =
* Plugin refactored
* Moved Settings page to Woocommerce -> Settings -> CiviCRM (Tab)
* Added translation support
* Added option to sync Customer/Contact address
* Added option to sync Customer/Contact billing phone
* Added option to sync Customer/Contact billing email
* Added option to replace Woocommerce State/County list with CiviCRM State/Province list
* Added 'woocommerce_civicrm_contribution_create_params' filter
* Added 'woocommerce_civicrm_contribution_update_params' filter
* Added 'woocommerce_civicrm_financial_types_params' filter
* Added 'woocommerce_civicrm_admin_settings_fields' filter
* Added 'woocommerce_civicrm_address_map' filter
* Added 'woocommerce_civicrm_mapped_location_types' filter
* Added 'woocommerce_civicrm_wc_address_updated' action
* Added 'woocommerce_civicrm_civi_address_updated' action
* Added ‘woocommerce_civicrm_wc_phone_updated’ action
* Added ‘woocommerce_civicrm_civi_phone_updated’ action
* Added ‘woocommerce_civicrm_wc_email_updated’ action
* Added ‘woocommerce_civicrm_civi_email_updated’ action


= 1.0 =

* Initial release
