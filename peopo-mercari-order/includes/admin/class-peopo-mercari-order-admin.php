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
     * Main plugin instance.
     *
     * @var Peopo_Mercari_Order
     */
    protected $plugin;

    /**
     * Hook suffix for settings page.
     *
     * @var string
     */
    protected $page_hook = '';

    /**
     * Constructor.
     *
     * @param Peopo_Mercari_Order $plugin Plugin instance.
     */
    public function __construct( Peopo_Mercari_Order $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Register admin menu entry.
     */
    public function register_menu() {
        $this->page_hook = add_submenu_page(
            'woocommerce',
            __( 'Mercari Deposit', 'peopo-mercari-order' ),
            __( 'Mercari Deposit', 'peopo-mercari-order' ),
            'manage_woocommerce',
            'peopo-mercari-deposit',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            Peopo_Mercari_Order::OPTION_GROUP,
            Peopo_Mercari_Order::OPTION_SOURCE_URL,
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=10',
            )
        );

        register_setting(
            Peopo_Mercari_Order::OPTION_GROUP,
            Peopo_Mercari_Order::OPTION_RATE_FIELD,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_rate_field' ),
                'default'           => 'Transfer',
            )
        );

        register_setting(
            Peopo_Mercari_Order::OPTION_GROUP,
            Peopo_Mercari_Order::OPTION_SERVICE_FEE,
            array(
                'type'              => 'number',
                'sanitize_callback' => array( $this, 'sanitize_positive_float' ),
                'default'           => 10,
            )
        );

        register_setting(
            Peopo_Mercari_Order::OPTION_GROUP,
            Peopo_Mercari_Order::OPTION_WEIGHT_FEE,
            array(
                'type'              => 'number',
                'sanitize_callback' => array( $this, 'sanitize_positive_float' ),
                'default'           => 200000,
            )
        );

        register_setting(
            Peopo_Mercari_Order::OPTION_GROUP,
            Peopo_Mercari_Order::OPTION_DEBUG,
            array(
                'type'              => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => false,
            )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( empty( $this->page_hook ) || $hook_suffix !== $this->page_hook ) {
            return;
        }

        wp_enqueue_script(
            'peopo-mercari-order-admin',
            PEOPO_MERCARI_ORDER_URL . 'assets/js/mercari-deposit-admin.js',
            array( 'jquery' ),
            PEOPO_MERCARI_ORDER_VERSION,
            true
        );

        wp_localize_script(
            'peopo-mercari-order-admin',
            'PeopoMercariAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( Peopo_Mercari_Order::AJAX_TEST_RATE_ACTION ),
                'action'  => Peopo_Mercari_Order::AJAX_TEST_RATE_ACTION,
                'i18n'    => array(
                    'testing' => esc_html__( 'Đang kiểm tra…', 'peopo-mercari-order' ),
                    'success' => esc_html__( 'Tỷ giá hiện tại: %s (%s)', 'peopo-mercari-order' ),
                    'error'   => esc_html__( 'Không thể lấy tỷ giá: %s', 'peopo-mercari-order' ),
                ),
            )
        );
    }

    /**
     * Render the settings page for the plugin.
     */
    public function render_settings_page() {
        $settings = $this->plugin->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Mercari Deposit', 'peopo-mercari-order' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( Peopo_Mercari_Order::OPTION_GROUP );
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="peopo-mercari-source-url"><?php esc_html_e( 'Nguồn tỷ giá (XML)', 'peopo-mercari-order' ); ?></label>
                            </th>
                            <td>
                                <input type="url" class="regular-text" id="peopo-mercari-source-url" name="<?php echo esc_attr( Peopo_Mercari_Order::OPTION_SOURCE_URL ); ?>" value="<?php echo esc_attr( $settings['source_url'] ); ?>">
                                <p class="description"><?php esc_html_e( 'Mặc định sử dụng nguồn XML của Vietcombank.', 'peopo-mercari-order' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="peopo-mercari-rate-field"><?php esc_html_e( 'Loại tỷ giá', 'peopo-mercari-order' ); ?></label>
                            </th>
                            <td>
                                <select id="peopo-mercari-rate-field" name="<?php echo esc_attr( Peopo_Mercari_Order::OPTION_RATE_FIELD ); ?>">
                                    <?php foreach ( array( 'Buy', 'Transfer', 'Sell' ) as $field ) : ?>
                                        <option value="<?php echo esc_attr( $field ); ?>" <?php selected( $settings['rate_field'], $field ); ?>><?php echo esc_html( $field ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Giá trị mặc định: Transfer.', 'peopo-mercari-order' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="peopo-mercari-service-fee"><?php esc_html_e( 'Phí dịch vụ (%)', 'peopo-mercari-order' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="peopo-mercari-service-fee" name="<?php echo esc_attr( Peopo_Mercari_Order::OPTION_SERVICE_FEE ); ?>" value="<?php echo esc_attr( $settings['service_fee'] ); ?>" min="0" step="0.1">
                                <p class="description"><?php esc_html_e( 'Mặc định 10%.', 'peopo-mercari-order' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="peopo-mercari-weight-fee"><?php esc_html_e( 'Phí cân nặng (VND/kg)', 'peopo-mercari-order' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="peopo-mercari-weight-fee" name="<?php echo esc_attr( Peopo_Mercari_Order::OPTION_WEIGHT_FEE ); ?>" value="<?php echo esc_attr( $settings['weight_fee'] ); ?>" min="0" step="1000">
                                <p class="description"><?php esc_html_e( 'Mặc định 200.000 VND/kg (chỉ hiển thị).', 'peopo-mercari-order' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ghi log debug', 'peopo-mercari-order' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( Peopo_Mercari_Order::OPTION_DEBUG ); ?>" value="1" <?php checked( $settings['debug'], true ); ?>>
                                    <?php esc_html_e( 'Bật chế độ debug (ghi log trong trang này).', 'peopo-mercari-order' ); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
                <button type="button" class="button" id="peopo-mercari-test-rate">
                    <?php esc_html_e( 'Test Exchange Rate', 'peopo-mercari-order' ); ?>
                </button>
                <p id="peopo-mercari-test-rate-result"></p>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitize rate field.
     *
     * @param string $value Raw value.
     *
     * @return string
     */
    public function sanitize_rate_field( $value ) {
        $allowed = array( 'Buy', 'Transfer', 'Sell' );

        if ( in_array( $value, $allowed, true ) ) {
            return $value;
        }

        return 'Transfer';
    }

    /**
     * Sanitize numeric option.
     *
     * @param mixed $value Raw value.
     *
     * @return float
     */
    public function sanitize_positive_float( $value ) {
        $value = is_numeric( $value ) ? (float) $value : 0;

        if ( $value < 0 ) {
            $value = 0;
        }

        return $value;
    }

    /**
     * Sanitize checkbox value.
     *
     * @param mixed $value Raw value.
     *
     * @return bool
     */
    public function sanitize_checkbox( $value ) {
        return ! empty( $value );
    }
}
