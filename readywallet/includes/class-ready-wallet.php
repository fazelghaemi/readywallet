<?php
/**
 * ReadyWallet Main Class
 * نسخه اصلاح شده برای رفع خطای Redeclare
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet {

    public $version = '2.8.0';
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
        // استفاده از untrailingslashit برای جلوگیری از دابل اسلش در ویندوز
        $path = untrailingslashit( plugin_dir_path( READY_WALLET_PLUGIN_FILE ) );

        // 1. دیتابیس
        include_once $path . '/includes/class-ready-wallet-db.php';
        $this->db = new Ready_Wallet_DB(); 

        // 2. تنظیمات (تب اختصاصی ووکامرس)
        include_once $path . '/includes/class-ready-wallet-settings.php';
        
        // 3. ماژول‌های هسته
        include_once $path . '/includes/class-ready-wallet-form-handler.php';
        include_once $path . '/includes/class-ready-wallet-shortcodes.php';
        include_once $path . '/includes/class-ready-wallet-cashback.php';
        include_once $path . '/includes/class-ready-wallet-sms.php';

        // 4. REST API - بررسی وجود فایل قبل از لود
        if ( file_exists( $path . '/includes/api/class-ready-wallet-rest-controller.php' ) ) {
            include_once $path . '/includes/api/class-ready-wallet-rest-controller.php';
        }

        // 5. ادمین
        if ( is_admin() ) {
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
        // بارگذاری درگاه پرداخت استاندارد ووکامرس
        if ( class_exists( 'WooCommerce' ) ) {
            $path = untrailingslashit( plugin_dir_path( READY_WALLET_PLUGIN_FILE ) );
            include_once $path . '/includes/gateways/class-ready-wallet-gateway.php';
        }

        new Ready_Wallet_SMS_Manager();
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
        ));
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style( 'ready-wallet-admin', plugins_url( 'assets/css/ready-wallet-admin.css', READY_WALLET_PLUGIN_FILE ), array(), $this->version );
    }

    public function install() {
        $this->db->install_schema();
        // ایجاد محصول شارژ
        if ( ! get_option( 'ready_wallet_deposit_product_id' ) ) {
            if ( class_exists( 'WC_Product_Simple' ) ) {
                $product = new WC_Product_Simple();
                $product->set_name( 'شارژ کیف پول' );
                $product->set_virtual( true );
                $product->set_catalog_visibility( 'hidden' );
                $product->set_price( 0 );
                $product->set_regular_price( 0 );
                $product->save();
                update_option( 'ready_wallet_deposit_product_id', $product->get_id() );
            }
        }
    }
}

// تابع کمکی حذف شد چون در فایل اصلی پلاگین تعریف شده است.