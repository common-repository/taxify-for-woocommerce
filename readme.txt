=== Taxify for WooCommerce ===
Contributors: toddlahman
Tags: WooCommerce, tax, taxes, tax calculation, free tax calculation, sales tax, state tax, file tax, ecommerce, woothemes, taxjar, taxify, sales tax compliance, automation, accounting, sales tax filing
Tested up to: 4.9.4
Stable tag: 1.2.8.1
Requires at least: 4.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 3.0
WC tested up to: 3.3.4

Taxify™ automatically prepares, collects, and files your taxes and returns accurately.

== Description ==

[Taxify](http://taxify.co/) for WooCommerce automatically calculates sales tax in real-time in the cart, the checkout, and on orders in the WooCommerce dashboard, then Taxify automatically files the taxes in every state sales tax is owed. Taxify for WooCommerce is the easiest to use tax calculation plugin ever created for WooCommerce.

Government Certified. Taxify is a Streamlined Sales Tax Certified Service Provider, which future-proofs your business as laws change.

Tax research on-demand. With more than 40 tax attorneys on staff, Taxify gives you rates and rules so you can make smarter decisions about new products and new markets.

= A few plugin highlights =

* The plugin is completely automated, and requires no special knowledge to use it.
* Sales tax is automatically calculated on the frontend and backend accurately based on WooCommerce settings.
* Sales tax can be recalcuated anytime on any order.
* Sales tax is automatically filed with Taxify for automated filing in every state.
* Orders with sales tax from 13 months prior to the plugin's installation will be filed with Taxify automatically.
* Partial and fully refunded orders are automatically filed with Taxify.
* If desired, a tax exempt checkbox will appear on the checkout page to allow tax exempt customers to pay zero sales tax.
* Individual products can be marked as taxable, or not taxable.
* Custom Taxify Tax Classes can be setup for products at Taxify, and set on a per product basis.
* Sales tax appears on WooCommerce Taxes reports.
* Works with third party shipping plugins/extensions.

= Customers love Taxify for WooCommerce because ... =

* Effortless sales tax. Protect your business with accurate and automated filing in every state. Automatically Comply With Thousands of Laws.
* 50-State Coverage. Automated rules, rates, and filing in 12,000+ Tax Jurisdictions.
* Live experts are always available to make sure tax is never in your way.
* Part of Sovos, the world’s trusted leader in tax for the past 35 years.
* Real-Time tax rates. Get up-to-date and accurate tax rates for every state and every local jurisdiction.
* Transparent reporting and audit trail. Take back control with visibility into everything from your historical data to current filings. Never get caught unprepared. Taxify gives you live access to transactions, past reports and more.
* Self-Service web portal. Our people know tax. They love our customers, and they’re always one phone call away.
* Simple Pricing. No hidden fees. No pushy sales people or drawn out process. We keep our pricing as simple as our solution.
* Automated filing and remittance. Put preparation, submission and payment on autopilot for all your sales tax filings.
* Government Certified. Part of the Streamlined Sales Tax Project, Taxify future-proofs your business as laws change.
* Tax research on-demand. With more than 20 tax attorneys on staff, Taxify gives you rates and rules so you can make smarter decisions about new products and new markets.

== Installation ==

1. Go to Plugins > Add new
2. Type in Taxify for WooCommerce in the Search Plugins box
3. Click Install
4. Check the Enable Taxify checkbox
5. Enter the Taxify API Key
6. Click Save changes
7. Done!

= Troubleshooting =

[Taxify Support](https://taxify.co/contact-us/) is managed at Taxify. Please visit Taxify rather than the WordPress.org support forum.

== Frequently Asked Questions ==

[Taxify Documentation](https://www.toddlahman.com/taxify-for-woocommerce/).

== Screenshots ==

1. The extremely easy Taxify settings panel.
2. Automatic, and accurate, frontend tax calculation.
3. Automatic, and accurate, backend tax calculation.
4. Orders marked as tax exempt recalculate to zero sales tax automatically.
5. Order details show if an order is tax exempt, and if the order was filed with Taxify.
6. Order details show the exact date and time the order was filed with Taxify.

== Upgrade Notice ==

= 1.2.8.1 =

Upgrade immediately to keep your software up-to-date and secure.

== Changelog ==

= 1.2.8.1 - 26/03/2018 =
* Requirement - WooCommerce 3.0 or above now required. For best results, upgrade to WooCommerce 3.2 or greater.
* Fix - Tax calculations for guest customers on the frontend and backend.
* Fix - Shipping tax was not always updating as expected.
* Fix - WooCommerce CRUD update method was adding order metadata entries even if entries existed in WooCommerce data store, rather than updating existing entries, and only adding entries that did not already exist.
* Tweak - Display Taxify and WooCommerce version on Taxify Settings screen.
* Tweak - Display notice to encourage upgrading to WooCommerce 3.2 or greater if WooCommerce is not already.

= 1.2.8 - 27/12/2017 =
* Fix - Update cart shipping tax for WooCommerce 3.2 on the backend.
* Added - WordPress.org rating URL and message in admin footer on WC screens.
* Tweak - Required settings check.
* Tweak - Save via AJAX on variable product variations.
* Update - WooCommerce >= 3.x CRUD compatibility.

= 1.2.7 - 26/12/2017 =
* Fix - Update cart shipping tax for WooCommerce 3.2.

= 1.2.6 - 10/11/2017 =
* Fix - Update cart totals to include tax for WooCommerce 3.2.
* Tweak - Add WC tested up to plugin header args for WooCommerce 3.2.
* Tweak - Minor cart class housekeeping.

= 1.2.5 - 1/9/2016 =
* Fix - Merge Action-Scheduler timezone fixes.

= 1.2.4 - 16/7/2016 =
* Tweak - Send order date as transaction date to Taxify for consistency, rather than paid date or order completed date.

= 1.2.2 - 16/7/2016 =
* Tweak - Resend orders to Taxify after one hour, rather than 24 hours, after failure.
* Update - Update Action-Scheduler library for PHP 7.x compatibility and other fixes.

= 1.2.1 - 18/6/2016 =
* Requirement - WooCommerce 2.5 or above now required. Support for WooCommerce 2.4 or below dropped.
* Fix - Order was not recorded at Taxify with WooCommerce 2.6, if the country entry was missing in the database for the order.
* Added - Added security to debug logged messages.
* Added - Added button to settings screen to clear debug log file.
* Tweak - Minor code housekeeping.

= 1.1.4 - 7/1/2016 =
* Tweak - Provide the option to turn debug logging on or off. Default is off.
* Tweak - Verify an order exists before scheduling the order to be automatically filed.

= 1.1.2 - 6/12/2015 =
* Tweak - File Tax Exempt orders, and (free item) orders sold for zero ($0), with Taxify going forward, and bulk file those orders dating back 13 months, if they haven't been filed already.

= 1.1.1 - 3/11/2015 =
* Fix - Calculate shipping tax when shipping method has a colon in the name on the backend.

= 1.1 - 27/10/2015 =
* Fix - Calculate shipping tax when shipping method has a colon in the name on the frontend.
* Fix - Taxify Tax Class on Product edit screen reloaded with each page load, instead of when refresh button was clicked.

= 1.0.1 - 24/9/2015 =

* readme update.

= 1.0 - 24/9/2015 =

* Initial release.