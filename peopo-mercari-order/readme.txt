=== Peopo Mercari Order ===
Contributors: your-name
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This is a starter template for integrating WooCommerce with Mercari order management. It registers an admin settings page to store API credentials and exposes a hook to sync completed orders with external services.

== Installation ==

1. Upload the `peopo-mercari-order` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Mercari Orders** in the WordPress admin menu to enter your API credentials.

== Frequently Asked Questions ==

= Does this plugin sync orders automatically? =

The template triggers the `peopo_mercari_order_sync` action whenever a WooCommerce order is marked as completed. Hook into this action to connect with external services.

= Can I extend this plugin? =

Yes. Use the provided hooks or extend the classes located in the `includes` directory to add functionality.

== Changelog ==

= 0.1.0 =
* Initial release.
