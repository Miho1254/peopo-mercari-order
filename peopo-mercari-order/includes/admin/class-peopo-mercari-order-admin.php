<?php
/**
 * Admin functionality for Peopo Mercari Order plugin.
 *
 * @package Peopo_Mercari_Order
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin pages and settings.
 */
class Peopo_Mercari_Order_Admin {

    /**
     * Register admin menu entry.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Mercari Orders', 'peopo-mercari-order' ),
            __( 'Mercari Orders', 'peopo-mercari-order' ),
            'manage_options',
            'peopo-mercari-order',
            array( $this, 'render_settings_page' ),
            'dashicons-cart'
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'peopo_mercari_order_settings', 'peopo_mercari_order_api_key' );
        register_setting( 'peopo_mercari_order_settings', 'peopo_mercari_order_api_secret' );
    }

    /**
     * Render the settings page for the plugin.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Mercari API Settings', 'peopo-mercari-order' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'peopo_mercari_order_settings' );
                do_settings_sections( 'peopo_mercari_order_settings' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="peopo-mercari-order-api-key"><?php esc_html_e( 'API Key', 'peopo-mercari-order' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="peopo-mercari-order-api-key" name="peopo_mercari_order_api_key" value="<?php echo esc_attr( get_option( 'peopo_mercari_order_api_key', '' ) ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="peopo-mercari-order-api-secret"><?php esc_html_e( 'API Secret', 'peopo-mercari-order' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="peopo-mercari-order-api-secret" name="peopo_mercari_order_api_secret" value="<?php echo esc_attr( get_option( 'peopo_mercari_order_api_secret', '' ) ); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
