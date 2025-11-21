<?php
/**
 * ReadyWallet Main Class
 *
 * نسخه تمیز و نهایی (بدون وابستگی به تنظیمات ووکامرس)
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet {

    public $version = '3.0.0'; // نسخه جدید
    protected static $_instance = null;
    public $db;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    public function includes() {
        $path = untrailingslashit( plugin_dir_path( READY_WALLET_PLUGIN_FILE ) );

        // 1. دیتابیس
        if ( file_exists( $path . '/includes/class-ready-wallet-db.php' ) ) {
            include_once $path . '/includes/class-ready-wallet-db.php';
            $this->db = new Ready_Wallet_DB(); 
        }

        // 2. ماژول‌های هسته
        if ( file_exists( $path . '/includes/class-ready-wallet-form-handler.php' ) ) {
            include_once $path . '/includes/class-ready-wallet-form-handler.php';
        }
        if ( file_exists( $path . '/includes/class-ready-wallet-shortcodes.php' ) ) {
            include_once $path . '/includes/class-ready-wallet-shortcodes.php';
        }

        // 3. ویژگی‌های تجاری
        if ( file_exists( $path . '/includes/class-ready-wallet-cashback.php' ) ) {
            include_once $path . '/includes/class-ready-wallet-cashback.php';
        }
        if ( file_exists( $path . '/includes/class-ready-wallet-sms.php' ) ) {
            include_once $path . '/includes/class-ready-wallet-sms.php';
        }

        // 4. REST API
        if ( file_exists( $path . '/includes/api/class-ready-wallet-rest-controller.php' ) ) {
            include_once $path . '/includes/api/class-ready-wallet-rest-controller.php';
        }

        // 5. ادمین (شامل تنظیمات جدید)
        if ( is_admin() && file_exists( $path . '/includes/admin/class-ready-wallet-admin.php' ) ) {
            include_once $path . '/includes/admin/class-ready-wallet-admin.php';
        }
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );
        
        register_activation_hook( READY_WALLET_PLUGIN_FILE, array( $this, 'install' ) );
    }

    public function on_plugins_loaded() {
        load_plugin_textdomain( 'ready-wallet', false, dirname( plugin_basename( READY_WALLET_PLUGIN_FILE ) ) . '/i18n/languages' );

        if ( class_exists( 'WooCommerce' ) ) {
            $path = untrailingslashit( plugin_dir_path( READY_WALLET_PLUGIN_FILE ) );
            
            // درگاه پرداخت
            if ( file_exists( $path . '/includes/gateways/class-ready-wallet-gateway.php' ) ) {
                include_once $path . '/includes/gateways/class-ready-wallet-gateway.php';
            }
        }

        if ( class_exists( 'Ready_Wallet_SMS_Manager' ) ) {
            new Ready_Wallet_SMS_Manager();
        }
    }

    public function init_rest_api() {
        if ( class_exists( 'Ready_Wallet_REST_Controller' ) ) {
            new Ready_Wallet_REST_Controller();
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'ready-wallet-front', plugins_url( 'assets/css/ready-wallet-frontend.css', READY_WALLET_PLUGIN_FILE ), array(), $this->version );
        wp_enqueue_script( 'ready-wallet-js', plugins_url( 'assets/js/ready-wallet-frontend.js', READY_WALLET_PLUGIN_FILE ), array( 'jquery' ), $this->version, true );
        wp_localize_script( 'ready-wallet-js', 'ready_wallet_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'error_empty' => __( 'لطفاً تمام فیلدها را پر کنید.', 'ready-wallet' ),
            'confirm_transfer' => __( 'آیا از انتقال وجه اطمینان دارید؟', 'ready-wallet' )
        ));
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style( 'ready-wallet-admin', plugins_url( 'assets/css/ready-wallet-admin.css', READY_WALLET_PLUGIN_FILE ), array(), $this->version );
    }

    public function install() {
        if ( is_object( $this->db ) ) {
            $this->db->install_schema();
        }
        if ( ! get_option( 'ready_wallet_deposit_product_id' ) && class_exists( 'WC_Product_Simple' ) ) {
            try {
                $product = new WC_Product_Simple();
                $product->set_name( 'شارژ کیف پول' );
                $product->set_slug( 'wallet-deposit' );
                $product->set_virtual( true );
                $product->set_catalog_visibility( 'hidden' );
                $product->set_price( 0 );
                $product->set_regular_price( 0 );
                $new_product_id = $product->save();
                if ( $new_product_id ) update_option( 'ready_wallet_deposit_product_id', $new_product_id );
            } catch ( Exception $e ) {}
        }
    }
}

if ( ! function_exists( 'Ready_Wallet' ) ) {
    function Ready_Wallet() {
        return Ready_Wallet::instance();
    }
}