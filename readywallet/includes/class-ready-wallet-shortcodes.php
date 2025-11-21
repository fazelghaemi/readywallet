<?php
/**
 * ReadyWallet Shortcodes Manager
 * مدیریت نمایش کیف پول در فرانت‌اند و حساب کاربری
 * ویژگی‌ها:
 * - تمیزسازی کامل (حذف استایل‌های اینلاین)
 * - اتصال صحیح تب‌ها به تمپلیت‌ها
 * - مدیریت صفحه‌بندی (Pagination) تراکنش‌ها
 * * Shortcode: [ready_wallet]
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Shortcodes {

    public function __construct() {
        add_shortcode( 'ready_wallet', array( $this, 'render_wallet_shortcode' ) );
        
        // ادغام با حساب کاربری ووکامرس
        add_action( 'init', array( $this, 'add_my_account_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_wallet_menu_item' ) );
        add_action( 'woocommerce_account_ready-wallet_endpoint', array( $this, 'render_wallet_page' ) );
    }

    /**
     * خروجی شورتکد [ready_wallet]
     */
    public function render_wallet_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return sprintf( 
                '<div class="woocommerce-info">%s <a href="%s">%s</a></div>', 
                __( 'برای دسترسی به کیف پول، لطفاً', 'ready-wallet' ), 
                get_permalink( get_option('woocommerce_myaccount_page_id') ), 
                __( 'وارد شوید', 'ready-wallet' ) 
            );
        }

        ob_start();
        $this->render_wallet_ui();
        return ob_get_clean();
    }

    /**
     * ثبت Endpoint برای آدرس‌دهی زیبا (/my-account/ready-wallet)
     */
    public function add_my_account_endpoint() {
        add_rewrite_endpoint( 'ready-wallet', EP_ROOT | EP_PAGES );
    }

    /**
     * افزودن آیتم "کیف پول من" به منوی حساب کاربری
     */
    public function add_wallet_menu_item( $items ) {
        $new_items = array();
        foreach ( $items as $key => $value ) {
            if ( 'customer-logout' === $key ) {
                $new_items['ready-wallet'] = __( 'کیف پول من', 'ready-wallet' );
            }
            $new_items[ $key ] = $value;
        }
        return $new_items;
    }

    /**
     * رندر محتوا در صفحه حساب کاربری ووکامرس
     */
    public function render_wallet_page() {
        $this->render_wallet_ui();
    }

    /**
     * هسته اصلی نمایش رابط کاربری (UI)
     */
    private function render_wallet_ui() {
        // اطمینان از لود شدن استایل‌ها و اسکریپت‌ها
        wp_enqueue_style( 'ready-wallet-front' );
        wp_enqueue_script( 'ready-wallet-js' );

        $user_id = get_current_user_id();
        
        // --- منطق صفحه‌بندی تراکنش‌ها ---
        $per_page = 10;
        $current_page = isset( $_GET['rw_page'] ) ? max( 1, intval( $_GET['rw_page'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        $wallet_db = new Ready_Wallet_DB();
        
        // دریافت تراکنش‌ها
        $transactions = $wallet_db->get_transactions([
            'user_id' => $user_id,
            'limit'   => $per_page,
            'offset'  => $offset
        ]);

        // محاسبه تعداد کل (برای دکمه‌های بعدی/قبلی)
        // در اینجا یک کوئری سریع برای شمارش می‌زنیم
        global $wpdb;
        $table_name = $wpdb->prefix . 'ready_wallet_transactions';
        $total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE user_id = %d", $user_id ) );
        $total_pages = ceil( $total_count / $per_page );

        // شروع رندرینگ HTML
        ?>
        <div class="ready-wallet-container">
            
            <!-- 1. بخش موجودی (Balance Card) -->
            <?php 
                $balance_template = READY_WALLET_ABSPATH . 'templates/wallet/balance.php';
                if ( file_exists( $balance_template ) ) {
                    include $balance_template;
                }
            ?>

            <!-- 2. منوی تب‌ها -->
            <div class="rw-tabs-wrapper">
                <ul class="rw-tabs-nav">
                    <li class="active" data-tab="transactions">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e( 'تراکنش‌ها', 'ready-wallet' ); ?>
                    </li>
                    <li data-tab="deposit">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e( 'افزایش اعتبار', 'ready-wallet' ); ?>
                    </li>
                    <li data-tab="withdraw">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e( 'برداشت وجه', 'ready-wallet' ); ?>
                    </li>
                    <li data-tab="transfer">
                        <span class="dashicons dashicons-migrate"></span>
                        <?php _e( 'انتقال وجه', 'ready-wallet' ); ?>
                    </li>
                </ul>

                <!-- محتوای تب: تراکنش‌ها -->
                <div id="transactions" class="rw-tab-content active">
                    <?php 
                        // ارسال متغیرها به تمپلیت (مهم برای صفحه‌بندی)
                        set_query_var( 'rw_transactions', $transactions );
                        set_query_var( 'rw_current_page', $current_page );
                        set_query_var( 'rw_total_pages', $total_pages );
                        
                        include READY_WALLET_ABSPATH . 'templates/wallet/transaction-list.php'; 
                    ?>
                </div>

                <!-- محتوای تب: افزایش موجودی -->
                <div id="deposit" class="rw-tab-content">
                    <?php include READY_WALLET_ABSPATH . 'templates/wallet/deposit.php'; ?>
                </div>

                <!-- محتوای تب: برداشت وجه -->
                <div id="withdraw" class="rw-tab-content">
                    <?php include READY_WALLET_ABSPATH . 'templates/wallet/withdraw.php'; ?>
                </div>
                
                <!-- محتوای تب: انتقال وجه (اکنون فعال شده) -->
                <div id="transfer" class="rw-tab-content">
                    <?php include READY_WALLET_ABSPATH . 'templates/wallet/transfer.php'; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

return new Ready_Wallet_Shortcodes();