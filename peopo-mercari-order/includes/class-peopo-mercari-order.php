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

    const SHORTCODE             = 'mercari_import';
    const OPTION_GROUP          = 'peopo_mercari_deposit_settings';
    const OPTION_SOURCE_URL     = 'peopo_mercari_deposit_source_url';
    const OPTION_RATE_FIELD     = 'peopo_mercari_deposit_rate_field';
    const OPTION_SERVICE_FEE    = 'peopo_mercari_deposit_service_fee';
    const OPTION_WEIGHT_FEE     = 'peopo_mercari_deposit_weight_fee';
    const OPTION_DEBUG          = 'peopo_mercari_deposit_debug';
    const TRANSIENT_RATE        = 'peopo_mercari_deposit_rate';
    const PRODUCT_SKU           = 'MERCARI-DEPOSIT';
    const PRODUCT_NAME          = 'Mercari Deposit';
    const AJAX_FETCH_ACTION     = 'mdfw_fetch_mercari';
    const AJAX_TEST_RATE_ACTION = 'peopo_mercari_test_rate';

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
        $this->admin = new Peopo_Mercari_Order_Admin( $this );
    }

    /**
     * Register all plugin hooks.
     */
    public function run() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
        add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
        add_action( 'wp_ajax_' . self::AJAX_FETCH_ACTION, array( $this, 'handle_fetch_mercari' ) );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_FETCH_ACTION, array( $this, 'handle_fetch_mercari' ) );
        add_action( 'wp_ajax_' . self::AJAX_TEST_RATE_ACTION, array( $this, 'handle_test_rate' ) );
        add_action( 'template_redirect', array( $this, 'handle_deposit_submission' ) );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'sync_cart_item_price' ), 20 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_thankyou', array( $this, 'add_order_note' ), 10, 1 );
    }

    /**
     * Initialise runtime features.
     */
    public function init() {
        load_plugin_textdomain( 'peopo-mercari-order', false, dirname( plugin_basename( PEOPO_MERCARI_ORDER_PATH . 'peopo-mercari-order.php' ) ) . '/languages/' );
        add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_scripts() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;

        if ( ! $post instanceof WP_Post ) {
            return;
        }

        if ( ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
            return;
        }

        wp_enqueue_style(
            'peopo-mercari-order-frontend',
            PEOPO_MERCARI_ORDER_URL . 'assets/css/mercari-deposit.css',
            array(),
            PEOPO_MERCARI_ORDER_VERSION
        );

        wp_enqueue_script(
            'peopo-mercari-order-frontend',
            PEOPO_MERCARI_ORDER_URL . 'assets/js/mercari-deposit.js',
            array( 'jquery' ),
            PEOPO_MERCARI_ORDER_VERSION,
            true
        );

        wp_localize_script(
            'peopo-mercari-order-frontend',
            'PeopoMercariDeposit',
            array(
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( self::AJAX_FETCH_ACTION ),
                'i18n'       => array(
                    'loading'       => esc_html__( 'Đang lấy dữ liệu…', 'peopo-mercari-order' ),
                    'fetchError'    => esc_html__( 'Không thể lấy dữ liệu từ Mercari. Vui lòng nhập thủ công.', 'peopo-mercari-order' ),
                    'unknownError'  => esc_html__( 'Có lỗi xảy ra. Vui lòng thử lại.', 'peopo-mercari-order' ),
                    'manualTitle'   => esc_html__( 'Tiêu đề sản phẩm', 'peopo-mercari-order' ),
                    'manualPrice'   => esc_html__( 'Giá (JPY)', 'peopo-mercari-order' ),
                    'manualImage'   => esc_html__( 'Ảnh (URL)', 'peopo-mercari-order' ),
                    'submitDeposit' => esc_html__( 'Đặt cọc ngay', 'peopo-mercari-order' ),
                ),
            )
        );
    }

    /**
     * Render shortcode output.
     *
     * @return string
     */
    public function render_shortcode() {
        ob_start();
        ?>
        <div class="peopo-mercari-deposit" data-nonce="<?php echo esc_attr( wp_create_nonce( self::AJAX_FETCH_ACTION ) ); ?>">
            <div class="peopo-mercari-deposit__input">
                <label for="peopo-mercari-url"><?php esc_html_e( 'Dán liên kết Mercari', 'peopo-mercari-order' ); ?></label>
                <input type="url" id="peopo-mercari-url" name="peopo_mercari_url" placeholder="https://jp.mercari.com/..." required>
                <button type="button" class="button peopo-mercari-fetch" data-action="<?php echo esc_attr( self::AJAX_FETCH_ACTION ); ?>">
                    <?php esc_html_e( 'Lấy dữ liệu', 'peopo-mercari-order' ); ?>
                </button>
            </div>
            <div class="peopo-mercari-deposit__messages" aria-live="polite"></div>
            <div class="peopo-mercari-deposit__result" hidden>
                <div class="peopo-mercari-deposit__product">
                    <img src="" alt="" class="peopo-mercari-deposit__image" hidden>
                    <div class="peopo-mercari-deposit__details">
                        <h3 class="peopo-mercari-deposit__title"></h3>
                        <p class="peopo-mercari-deposit__price"></p>
                    </div>
                </div>
                <dl class="peopo-mercari-deposit__summary"></dl>
                <form method="post" class="peopo-mercari-deposit__form">
                    <?php wp_nonce_field( 'peopo_mercari_deposit_checkout', 'peopo_mercari_deposit_checkout_nonce' ); ?>
                    <input type="hidden" name="peopo_mercari_submit" value="1">
                    <input type="hidden" name="mercari_url" value="">
                    <input type="hidden" name="mercari_title" value="">
                    <input type="hidden" name="mercari_image" value="">
                    <input type="hidden" name="price_jpy" value="">
                    <input type="hidden" name="rate_value" value="">
                    <input type="hidden" name="rate_type" value="">
                    <input type="hidden" name="deposit_vnd" value="">
                    <input type="hidden" name="service_fee_percent" value="">
                    <input type="hidden" name="service_fee" value="">
                    <input type="hidden" name="subtotal_without_weight" value="">
                    <input type="hidden" name="weight_fee_perkg" value="">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Đặt cọc ngay', 'peopo-mercari-order' ); ?>
                    </button>
                </form>
                <div class="peopo-mercari-deposit__manual" hidden>
                    <h4><?php esc_html_e( 'Nhập thủ công nếu cần', 'peopo-mercari-order' ); ?></h4>
                    <label>
                        <?php esc_html_e( 'Tiêu đề sản phẩm', 'peopo-mercari-order' ); ?>
                        <input type="text" class="peopo-mercari-manual-title" placeholder="Mercari item">
                    </label>
                    <label>
                        <?php esc_html_e( 'Giá (JPY)', 'peopo-mercari-order' ); ?>
                        <input type="number" class="peopo-mercari-manual-price" min="0" step="1">
                    </label>
                    <label>
                        <?php esc_html_e( 'Ảnh (URL)', 'peopo-mercari-order' ); ?>
                        <input type="url" class="peopo-mercari-manual-image" placeholder="https://">
                    </label>
                    <button type="button" class="button peopo-mercari-apply-manual">
                        <?php esc_html_e( 'Áp dụng dữ liệu thủ công', 'peopo-mercari-order' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Mercari data fetch via AJAX.
     */
    public function handle_fetch_mercari() {
        check_ajax_referer( self::AJAX_FETCH_ACTION, 'nonce' );

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Liên kết không hợp lệ.', 'peopo-mercari-order' ) ) );
        }

        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( empty( $host ) || false === stripos( $host, 'mercari' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Vui lòng nhập liên kết Mercari hợp lệ.', 'peopo-mercari-order' ) ) );
        }

        $needs_manual = false;
        $data         = array(
            'title'     => '',
            'image'     => '',
            'price_jpy' => 0,
        );

        $mercari = $this->scrape_mercari( $url );

        if ( is_wp_error( $mercari ) ) {
            $needs_manual = true;
        } else {
            $data = array_merge( $data, $mercari );
            if ( empty( $data['title'] ) || empty( $data['price_jpy'] ) ) {
                $needs_manual = true;
            }
        }

        $settings = $this->get_settings();
        $rate     = $this->get_exchange_rate();

        if ( is_wp_error( $rate ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Không lấy được tỷ giá. Vui lòng thử lại sau.', 'peopo-mercari-order' ) ) );
        }

        $price_jpy = isset( $data['price_jpy'] ) ? (int) $data['price_jpy'] : 0;
        $deposit   = $this->calculate_totals( $price_jpy, $rate['rate'], $settings['service_fee'] );

        $response = array(
            'ok'           => true,
            'needs_manual' => $needs_manual,
            'data'         => array(
                'title'                    => $data['title'],
                'image'                    => $data['image'],
                'price_jpy'                => $price_jpy,
                'rate'                     => $rate['rate'],
                'rate_type'                => $rate['type'],
                'deposit_vnd'              => $deposit['deposit_vnd'],
                'service_fee_percent'      => $settings['service_fee'],
                'service_fee'              => $deposit['service_fee'],
                'subtotal_without_weight'  => $deposit['subtotal_without_weight'],
                'weight_fee_perkg'         => $settings['weight_fee'],
            ),
        );

        wp_send_json_success( $response );
    }

    /**
     * AJAX handler for rate test button.
     */
    public function handle_test_rate() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'peopo-mercari-order' ) ) );
        }

        check_ajax_referer( self::AJAX_TEST_RATE_ACTION, 'nonce' );

        $rate = $this->get_exchange_rate( true );

        if ( is_wp_error( $rate ) ) {
            wp_send_json_error( array( 'message' => $rate->get_error_message() ) );
        }

        wp_send_json_success( array( 'rate' => $rate['rate'], 'type' => $rate['type'] ) );
    }

    /**
     * Handle deposit submission, add product to cart and redirect to checkout.
     */
    public function handle_deposit_submission() {
        if ( ! isset( $_POST['peopo_mercari_submit'] ) ) {
            return;
        }

        if ( empty( $_POST['peopo_mercari_deposit_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['peopo_mercari_deposit_checkout_nonce'] ) ), 'peopo_mercari_deposit_checkout' ) ) {
            $this->add_notice( esc_html__( 'Yêu cầu không hợp lệ.', 'peopo-mercari-order' ) );
            return;
        }

        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
            $this->add_notice( esc_html__( 'WooCommerce chưa được kích hoạt.', 'peopo-mercari-order' ) );
            return;
        }

        $settings   = $this->get_settings();
        $rate       = $this->get_exchange_rate();
        $price_jpy  = isset( $_POST['price_jpy'] ) ? (int) wp_unslash( $_POST['price_jpy'] ) : 0;
        $url        = isset( $_POST['mercari_url'] ) ? esc_url_raw( wp_unslash( $_POST['mercari_url'] ) ) : '';
        $title      = isset( $_POST['mercari_title'] ) ? sanitize_text_field( wp_unslash( $_POST['mercari_title'] ) ) : '';
        $image      = isset( $_POST['mercari_image'] ) ? esc_url_raw( wp_unslash( $_POST['mercari_image'] ) ) : '';

        if ( empty( $price_jpy ) || empty( $url ) ) {
            $this->add_notice( esc_html__( 'Thiếu dữ liệu cần thiết.', 'peopo-mercari-order' ) );
            return;
        }

        if ( is_wp_error( $rate ) ) {
            $this->add_notice( esc_html__( 'Không thể lấy tỷ giá hiện tại.', 'peopo-mercari-order' ) );
            return;
        }

        $totals = $this->calculate_totals( $price_jpy, $rate['rate'], $settings['service_fee'] );

        $product_id = $this->get_or_create_product();

        if ( ! $product_id ) {
            $this->add_notice( esc_html__( 'Không thể tạo sản phẩm cọc.', 'peopo-mercari-order' ) );
            return;
        }

        $cart = WC()->cart;

        if ( ! $cart ) {
            $this->add_notice( esc_html__( 'Giỏ hàng không khả dụng.', 'peopo-mercari-order' ) );
            return;
        }

        $cart_data = array(
            'mdfw_deposit_price' => $totals['deposit_vnd'],
            'mercari_meta'       => array(
                'url'                     => $url,
                'title'                   => $title,
                'image'                   => $image,
                'price_jpy'               => $price_jpy,
                'rate_value'              => $rate['rate'],
                'rate_type'               => $rate['type'],
                'service_fee_percent'     => $settings['service_fee'],
                'service_fee'             => $totals['service_fee'],
                'deposit_vnd'             => $totals['deposit_vnd'],
                'subtotal_without_weight' => $totals['subtotal_without_weight'],
                'weight_fee_perkg'        => $settings['weight_fee'],
                'note_weight_pending'     => 1,
            ),
        );

        $added = $cart->add_to_cart( $product_id, 1, 0, array(), $cart_data );

        if ( ! $added ) {
            $this->add_notice( esc_html__( 'Không thể thêm sản phẩm vào giỏ.', 'peopo-mercari-order' ) );
            return;
        }

        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    /**
     * Sync cart item price from custom data.
     *
     * @param WC_Cart $cart WooCommerce cart instance.
     */
    public function sync_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['mdfw_deposit_price'] ) && isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
                $cart_item['data']->set_price( (float) $cart_item['mdfw_deposit_price'] );
            }
        }
    }

    /**
     * Display item data in cart and checkout.
     *
     * @param array $item_data Item data.
     * @param array $cart_item Cart item.
     *
     * @return array
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['mercari_meta'] ) || ! is_array( $cart_item['mercari_meta'] ) ) {
            return $item_data;
        }

        $meta = $cart_item['mercari_meta'];

        $link_value = '';
        if ( ! empty( $meta['url'] ) ) {
            $link_value = sprintf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( $meta['url'] ) );
            $link_value = wp_kses( $link_value, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) );
        }

        $item_data[] = array(
            'name'  => esc_html__( 'Link Mercari', 'peopo-mercari-order' ),
            'value' => $link_value,
        );

        $item_data[] = array(
            'name'  => esc_html__( 'Giá gốc (JPY)', 'peopo-mercari-order' ),
            'value' => esc_html( number_format_i18n( (int) $meta['price_jpy'] ) ),
        );

        $item_data[] = array(
            'name'  => esc_html__( 'Tỷ giá VCB', 'peopo-mercari-order' ),
            'value' => esc_html( sprintf( '%1$s (%2$s)', number_format_i18n( $meta['rate_value'], 2 ), $meta['rate_type'] ) ),
        );

        if ( isset( $meta['deposit_vnd'] ) ) {
            $item_data[] = array(
                'name'  => esc_html__( 'Tiền hàng quy đổi', 'peopo-mercari-order' ),
                'value' => wp_kses_post( wc_price( $meta['deposit_vnd'] ) ),
            );
        }

        $item_data[] = array(
            'name'  => esc_html__( 'Phí dịch vụ', 'peopo-mercari-order' ),
            'value' => wp_kses_post( sprintf( '%1$s%% (%2$s)', $meta['service_fee_percent'], wc_price( $meta['service_fee'] ) ) ),
        );

        $item_data[] = array(
            'name'  => esc_html__( 'Tạm tính (chưa cân nặng)', 'peopo-mercari-order' ),
            'value' => wp_kses_post( wc_price( $meta['subtotal_without_weight'] ) ),
        );

        if ( ! empty( $meta['note_weight_pending'] ) ) {
            $item_data[] = array(
                'name'  => esc_html__( 'Lưu ý', 'peopo-mercari-order' ),
                'value' => esc_html( sprintf( __( 'Phí cân nặng tạm tính %s VND/kg sẽ được thông báo sau.', 'peopo-mercari-order' ), number_format_i18n( $meta['weight_fee_perkg'] ) ) ),
            );
        }

        return $item_data;
    }

    /**
     * Persist metadata on order items.
     *
     * @param WC_Order_Item_Product $item   Order item.
     * @param string                $cart_item_key Cart item key.
     * @param array                 $values Cart item values.
     * @param WC_Order              $order Order instance.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['mercari_meta'] ) || ! is_array( $values['mercari_meta'] ) ) {
            return;
        }

        foreach ( $values['mercari_meta'] as $meta_key => $meta_value ) {
            $item->add_meta_data( '_' . $meta_key, $meta_value, true );
        }

        if ( ! empty( $values['mercari_meta']['url'] ) ) {
            $order->update_meta_data( '_mercari_url', esc_url_raw( $values['mercari_meta']['url'] ) );
        }
        if ( ! empty( $values['mercari_meta']['title'] ) ) {
            $order->update_meta_data( '_mercari_title', sanitize_text_field( $values['mercari_meta']['title'] ) );
        }
        if ( ! empty( $values['mercari_meta']['image'] ) ) {
            $order->update_meta_data( '_mercari_image', esc_url_raw( $values['mercari_meta']['image'] ) );
        }
        if ( ! empty( $values['mercari_meta']['price_jpy'] ) ) {
            $order->update_meta_data( '_mercari_price_jpy', (int) $values['mercari_meta']['price_jpy'] );
        }
        $order->update_meta_data( '_mercari_rate_value', isset( $values['mercari_meta']['rate_value'] ) ? (float) $values['mercari_meta']['rate_value'] : '' );
        $order->update_meta_data( '_mercari_rate_type', isset( $values['mercari_meta']['rate_type'] ) ? $values['mercari_meta']['rate_type'] : '' );
        $order->update_meta_data( '_mercari_service_fee_percent', isset( $values['mercari_meta']['service_fee_percent'] ) ? (float) $values['mercari_meta']['service_fee_percent'] : '' );
        $order->update_meta_data( '_mercari_service_fee', isset( $values['mercari_meta']['service_fee'] ) ? (float) $values['mercari_meta']['service_fee'] : '' );
        $order->update_meta_data( '_mercari_deposit_vnd', isset( $values['mercari_meta']['deposit_vnd'] ) ? (float) $values['mercari_meta']['deposit_vnd'] : '' );
        $order->update_meta_data( '_mercari_subtotal_without_weight', isset( $values['mercari_meta']['subtotal_without_weight'] ) ? (float) $values['mercari_meta']['subtotal_without_weight'] : '' );
        $order->update_meta_data( '_mercari_weight_fee_perkg', isset( $values['mercari_meta']['weight_fee_perkg'] ) ? (float) $values['mercari_meta']['weight_fee_perkg'] : '' );
    }

    /**
     * Append order note after successful payment.
     *
     * @param int $order_id Order ID.
     */
    public function add_order_note( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $url        = $order->get_meta( '_mercari_url', true );
        $price_jpy  = (int) $order->get_meta( '_mercari_price_jpy', true );
        $rate       = $order->get_meta( '_mercari_rate_value', true );
        $rate_type  = $order->get_meta( '_mercari_rate_type', true );
        $deposit    = (float) $order->get_meta( '_mercari_deposit_vnd', true );
        $service    = (float) $order->get_meta( '_mercari_service_fee', true );
        $subtotal   = (float) $order->get_meta( '_mercari_subtotal_without_weight', true );

        if ( empty( $url ) ) {
            return;
        }

        $lines   = array();
        $lines[] = sprintf( __( 'Link: %s', 'peopo-mercari-order' ), esc_url_raw( $url ) );
        $lines[] = sprintf( __( 'Giá gốc: %s JPY', 'peopo-mercari-order' ), number_format_i18n( $price_jpy ) );
        if ( $rate ) {
            $lines[] = sprintf( __( 'Tỷ giá: %1$s (%2$s)', 'peopo-mercari-order' ), number_format_i18n( (float) $rate, 2 ), $rate_type );
        }
        if ( $deposit ) {
            $lines[] = sprintf( __( 'Tiền hàng quy đổi: %s VND', 'peopo-mercari-order' ), number_format_i18n( $deposit ) );
        }
        if ( $service ) {
            $lines[] = sprintf( __( 'Phí dịch vụ: %s VND', 'peopo-mercari-order' ), number_format_i18n( $service ) );
        }
        if ( $subtotal ) {
            $lines[] = sprintf( __( 'Tạm tính (chưa cân nặng): %s VND', 'peopo-mercari-order' ), number_format_i18n( $subtotal ) );
        }

        $note = __( 'Đơn cọc Mercari:', 'peopo-mercari-order' ) . "\n" . implode( "\n", array_map( static function( $line ) {
            return '• ' . $line;
        }, $lines ) );

        $order->add_order_note( $note );
    }

    /**
     * Retrieve plugin settings with defaults.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'source_url'  => 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=10',
            'rate_field'  => 'Transfer',
            'service_fee' => 10,
            'weight_fee'  => 200000,
            'debug'       => false,
        );

        $settings = array(
            'source_url'  => esc_url_raw( get_option( self::OPTION_SOURCE_URL, $defaults['source_url'] ) ),
            'rate_field'  => sanitize_text_field( get_option( self::OPTION_RATE_FIELD, $defaults['rate_field'] ) ),
            'service_fee' => (float) get_option( self::OPTION_SERVICE_FEE, $defaults['service_fee'] ),
            'weight_fee'  => (float) get_option( self::OPTION_WEIGHT_FEE, $defaults['weight_fee'] ),
            'debug'       => (bool) get_option( self::OPTION_DEBUG, $defaults['debug'] ),
        );

        if ( ! in_array( $settings['rate_field'], array( 'Buy', 'Transfer', 'Sell' ), true ) ) {
            $settings['rate_field'] = $defaults['rate_field'];
        }

        if ( $settings['service_fee'] < 0 ) {
            $settings['service_fee'] = 0;
        }

        if ( $settings['weight_fee'] < 0 ) {
            $settings['weight_fee'] = 0;
        }

        return $settings;
    }

    /**
     * Get exchange rate from Vietcombank.
     *
     * @param bool $force_refresh Force refresh.
     *
     * @return array|WP_Error
     */
    public function get_exchange_rate( $force_refresh = false ) {
        $settings  = $this->get_settings();
        $use_cache = ! $force_refresh && empty( $settings['debug'] );

        if ( $use_cache ) {
            $cached = get_transient( self::TRANSIENT_RATE );

            if ( $cached ) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            $settings['source_url'],
            array(
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', __( 'Không nhận được dữ liệu tỷ giá.', 'peopo-mercari-order' ) );
        }

        $xml = simplexml_load_string( $body );

        if ( ! $xml ) {
            return new WP_Error( 'invalid_xml', __( 'Không phân tích được dữ liệu tỷ giá.', 'peopo-mercari-order' ) );
        }

        $rate_node = null;
        foreach ( $xml->Exrate as $item ) {
            if ( (string) $item['CurrencyCode'] === 'JPY' ) {
                $rate_node = $item;
                break;
            }
        }

        if ( ! $rate_node ) {
            return new WP_Error( 'missing_rate', __( 'Không tìm thấy tỷ giá JPY.', 'peopo-mercari-order' ) );
        }

        $field = $settings['rate_field'];
        $value = (string) $rate_node[ $field ];
        $value = str_replace( array( ',', ' ' ), array( '', '' ), $value );

        if ( empty( $value ) || ! is_numeric( $value ) ) {
            return new WP_Error( 'invalid_rate', __( 'Giá trị tỷ giá không hợp lệ.', 'peopo-mercari-order' ) );
        }

        $rate = array(
            'rate' => (float) $value,
            'type' => $field,
        );

        if ( $use_cache ) {
            set_transient( self::TRANSIENT_RATE, $rate, 5 * MINUTE_IN_SECONDS );
        }

        return $rate;
    }

    /**
     * Calculate totals based on inputs.
     *
     * @param int   $price_jpy Price in JPY.
     * @param float $rate      Exchange rate.
     * @param float $service_fee_percent Service fee percent.
     *
     * @return array
     */
    protected function calculate_totals( $price_jpy, $rate, $service_fee_percent ) {
        $deposit_vnd = round( (float) $price_jpy * (float) $rate );
        $service_fee = round( $deposit_vnd * ( (float) $service_fee_percent / 100 ) );

        return array(
            'deposit_vnd'             => $deposit_vnd,
            'service_fee'             => $service_fee,
            'subtotal_without_weight' => $deposit_vnd + $service_fee,
        );
    }

    /**
     * Scrape Mercari metadata.
     *
     * @param string $url Mercari URL.
     *
     * @return array|WP_Error
     */
    protected function scrape_mercari( $url ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'MercariDeposit/1.0 (+https://example.com)',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', __( 'Không lấy được nội dung sản phẩm.', 'peopo-mercari-order' ) );
        }

        $matches = array();
        $data    = array(
            'title'     => '',
            'image'     => '',
            'price_jpy' => 0,
        );

        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/', $body, $matches ) ) {
            $data['title'] = sanitize_text_field( html_entity_decode( $matches[1], ENT_QUOTES ) );
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $body, $matches ) ) {
            $data['image'] = esc_url_raw( $matches[1] );
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:price:amount["\'][^>]+content=["\']([^"\']+)["\']/', $body, $matches ) ) {
            $data['price_jpy'] = (int) preg_replace( '/\D+/', '', $matches[1] );
        }

        if ( empty( $data['price_jpy'] ) ) {
            if ( preg_match( '/[¥￥]\s?([0-9,.]+)/u', $body, $matches ) ) {
                $data['price_jpy'] = (int) preg_replace( '/\D+/', '', $matches[1] );
            }
        }

        return $data;
    }

    /**
     * Ensure hidden deposit product exists and return ID.
     *
     * @return int|false
     */
    protected function get_or_create_product() {
        $product_id = wc_get_product_id_by_sku( self::PRODUCT_SKU );

        if ( $product_id ) {
            return $product_id;
        }

        $product_data = array(
            'post_title'   => self::PRODUCT_NAME,
            'post_content' => __( 'Sản phẩm kỹ thuật dùng cho đặt cọc Mercari.', 'peopo-mercari-order' ),
            'post_status'  => 'private',
            'post_type'    => 'product',
        );

        $product_id = wp_insert_post( $product_data );

        if ( is_wp_error( $product_id ) || ! $product_id ) {
            return false;
        }

        update_post_meta( $product_id, '_sku', self::PRODUCT_SKU );
        update_post_meta( $product_id, '_price', 0 );
        update_post_meta( $product_id, '_regular_price', 0 );
        update_post_meta( $product_id, '_virtual', 'yes' );
        update_post_meta( $product_id, '_sold_individually', 'yes' );
        update_post_meta( $product_id, '_manage_stock', 'no' );

        return $product_id;
    }

    /**
     * Helper to add WooCommerce notice or die gracefully.
     *
     * @param string $message Notice message.
     * @param string $type    Notice type.
     */
    protected function add_notice( $message, $type = 'error' ) {
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( $message, $type );
            return;
        }

        if ( 'error' === $type ) {
            wp_die( esc_html( $message ) );
        }
    }
}
