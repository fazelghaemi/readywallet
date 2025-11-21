<?php
/**
 * ReadyWallet Main Class
 *
 * @class       Ready_Wallet
 * @version     2.1.0
 * @package     ReadyWallet/Classes
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet {

    public $version = '2.1.0';
    protected static $_instance = null;

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
        // فایل‌های اصلی (فرض بر وجود فایل‌های هسته Woo Wallet)
        include_once READY_WALLET_ABSPATH . 'includes/class-woo-wallet-transactions.php'; 
        include_once READY_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php'; 
        
        // *** فایل‌های اختصاصی ReadyWallet ***
        include_once READY_WALLET_ABSPATH . 'includes/class-ready-wallet-settings.php'; // تنظیمات ادمین
        include_once READY_WALLET_ABSPATH . 'includes/class-ready-wallet-sms.php';      // هسته پیامک
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
    }

    public function on_plugins_loaded() {
        // راه‌اندازی کلاس پیامک
        new Ready_Wallet_SMS_Manager();
    }

    /**
     * استایل‌های سمت کاربر (Frontend)
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style( 
            'ready-wallet-front', 
            plugins_url( '/assets/css/ready-wallet-frontend.css', READY_WALLET_PLUGIN_FILE ), 
            array(), 
            $this->version 
        );
    }

    /**
     * استایل‌های سمت مدیریت (Admin)
     */
    public function enqueue_admin_styles() {
        wp_enqueue_style( 
            'ready-wallet-admin', 
            plugins_url( '/assets/css/ready-wallet-admin.css', READY_WALLET_PLUGIN_FILE ), 
            array(), 
            $this->version 
        );
    }
}