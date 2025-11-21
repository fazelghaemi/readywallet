<?php
/**
 * Plugin Name: ReadyWallet Pro
 * Plugin URI: https://readystudio.ir/readywallet
 * Description: سیستم جامع کیف پول هوشمند، پاداش خرید (Cashback) و درگاه پیامکی اختصاصی ردی استودیو.
 * Version: 2.9.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: ready-wallet
 * Domain Path: /i18n/languages/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package ReadyWallet
 */

defined( 'ABSPATH' ) || exit;

/**
 * 1. تعریف ثابت‌های حیاتی (Constants)
 * این ثابت‌ها در سراسر افزونه برای آدرس‌دهی فایل‌ها استفاده می‌شوند.
 */
if ( ! defined( 'READY_WALLET_PLUGIN_FILE' ) ) {
    define( 'READY_WALLET_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'READY_WALLET_ABSPATH' ) ) {
    define( 'READY_WALLET_ABSPATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'READY_WALLET_PLUGIN_URL' ) ) {
    define( 'READY_WALLET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// نسخه افزونه (بسیار مهم برای کش‌باسی CSS/JS)
if ( ! defined( 'READY_WALLET_PLUGIN_VERSION' ) ) {
    define( 'READY_WALLET_PLUGIN_VERSION', '2.9.0' );
}

/**
 * 2. بررسی پیش‌نیازها (Dependency Check)
 * اطمینان حاصل می‌کنیم که ووکامرس نصب است تا از خطای Fatal جلوگیری شود.
 */
function ready_wallet_check_dependencies() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'ready_wallet_woocommerce_missing_notice' );
    }
}
add_action( 'plugins_loaded', 'ready_wallet_check_dependencies' );

function ready_wallet_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e( 'افزونه <strong>ReadyWallet</strong> برای اجرا نیاز به نصب و فعال‌سازی <strong>ووکامرس</strong> دارد.', 'ready-wallet' ); ?></p>
    </div>
    <?php
}

/**
 * 3. بارگذاری کلاس اصلی (Main Loader)
 * اگر کلاس اصلی وجود نداشت، آن را فراخوانی می‌کنیم.
 */
if ( ! class_exists( 'Ready_Wallet' ) ) {
    include_once READY_WALLET_ABSPATH . 'includes/class-ready-wallet.php';
}

/**
 * 4. تابع دسترسی سریع (Helper Function)
 * با فراخوانی ready_wallet() به نمونه اصلی کلاس دسترسی خواهید داشت.
 *
 * @return Ready_Wallet
 */
function ready_wallet() {
    return Ready_Wallet::instance();
}

/**
 * 5. اجرای افزونه
 */
ready_wallet();

/**
 * 6. هوک‌های فعال‌سازی و غیرفعال‌سازی (Activation/Deactivation)
 * نکته: منطق دیتابیس در کلاس اصلی هندل می‌شود، اما اینجا می‌توانیم کارهای تمیزکاری انجام دهیم.
 */
register_activation_hook( __FILE__, 'ready_wallet_activation' );
register_deactivation_hook( __FILE__, 'ready_wallet_deactivation' );

function ready_wallet_activation() {
    // اگر نیاز به عملیات خاصی در لحظه فعال‌سازی بود (مثلاً چک کردن نسخه PHP)
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( basename( __FILE__ ) );
        wp_die( __( 'این افزونه نیاز به PHP نسخه 7.4 یا بالاتر دارد.', 'ready-wallet' ) );
    }
    
    // فراخوانی متد نصب در کلاس اصلی برای ساخت جداول
    if ( function_exists( 'ready_wallet' ) ) {
        ready_wallet()->install();
    }
}

function ready_wallet_deactivation() {
    // پاک کردن کش‌ها یا کرون جاب‌ها هنگام غیرفعال‌سازی
    wp_cache_flush();
}