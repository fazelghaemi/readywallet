<?php
/**
 * Plugin Name: ReadyWallet
 * Plugin URI: https://readystudio.ir/readywallet
 * Description: کیف پول هوشمند و اختصاصی ردی استودیو با قابلیت درگاه پیامکی MessageWay و رابط کاربری مدرن.
 * Version: 2.0.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: ready-wallet
 * Domain Path: /i18n/languages/
 *
 * @package ReadyWallet
 */

defined( 'ABSPATH' ) || exit;

// تعریف ثابت‌های اصلی با نام جدید
if ( ! defined( 'READY_WALLET_PLUGIN_FILE' ) ) {
    define( 'READY_WALLET_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'READY_WALLET_ABSPATH' ) ) {
    define( 'READY_WALLET_ABSPATH', dirname( READY_WALLET_PLUGIN_FILE ) . '/' );
}
if ( ! defined( 'READY_WALLET_PLUGIN_VERSION' ) ) {
    define( 'READY_WALLET_PLUGIN_VERSION', '2.0.0' );
}

/**
 * کلاس اصلی برای بارگذاری پلاگین
 */
if ( ! class_exists( 'Ready_Wallet' ) ) {
    include_once dirname( __FILE__ ) . '/includes/class-ready-wallet.php';
}

/**
 * تابع اصلی برای اجرای پلاگین
 */
function ready_wallet() {
    return Ready_Wallet::instance();
}

// اجرای پلاگین
ready_wallet();