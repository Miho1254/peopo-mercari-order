<?php
/**
 * Plugin Name:       Peopo Mercari Order
 * Plugin URI:        https://example.com/plugins/peopo-mercari-order
 * Description:       Template plugin to integrate WooCommerce with Mercari order management.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       peopo-mercari-order
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined( 'PEOPO_MERCARI_ORDER_VERSION' ) ) {
    define( 'PEOPO_MERCARI_ORDER_VERSION', '0.1.0' );
}

if ( ! defined( 'PEOPO_MERCARI_ORDER_PATH' ) ) {
    define( 'PEOPO_MERCARI_ORDER_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PEOPO_MERCARI_ORDER_URL' ) ) {
    define( 'PEOPO_MERCARI_ORDER_URL', plugin_dir_url( __FILE__ ) );
}

require_once PEOPO_MERCARI_ORDER_PATH . 'includes/class-peopo-mercari-order.php';

/**
 * Begins execution of the plugin.
 */
function peopo_mercari_order_run() {
    $plugin = new Peopo_Mercari_Order();
    $plugin->run();
}
add_action( 'plugins_loaded', 'peopo_mercari_order_run' );
