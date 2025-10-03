<?php
/**
 * Main plugin class
 *
 * @package Peopo_Mercari_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once PEOPO_MERCARI_ORDER_PATH . 'includes/admin/class-peopo-mercari-order-admin.php';

/**
 * The core plugin class.
 */
class Peopo_Mercari_Order {

    /**
     * Admin functionality controller.
     *
     * @var Peopo_Mercari_Order_Admin
     */
    protected $admin;

    /**
     * Initialize the plugin by setting up hooks.
     */
    public function __construct() {
        $this->admin = new Peopo_Mercari_Order_Admin();
    }

    /**
     * Register all of the hooks for admin functionality.
     */
    public function run() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
        add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'handle_completed_order' ), 10, 1 );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'peopo-mercari-order', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Handle WooCommerce order completion.
     *
     * @param int $order_id Order ID.
     */
    public function handle_completed_order( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        /**
         * Example: Send order data to Mercari API.
         * Replace this with real API integration.
         */
        do_action( 'peopo_mercari_order_sync', $order );
    }
}
