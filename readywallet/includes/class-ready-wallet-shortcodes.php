<?php
/**
 * ReadyWallet Shortcodes & Frontend Manager
 *
 * مدیریت نمایش کیف پول در فرانت‌اند (شورتکد) و حساب کاربری ووکامرس.
 *
 * ویژگی‌های پیشرفته:
 * - سیستم تب‌بندی توسعه‌پذیر (Hookable Tabs)
 * - بارگذاری مشروط استایل‌ها و اسکریپت‌ها (Performance Optimization)
 * - مدیریت اعلان‌های ووکامرس (Flash Messages)
 * - صفحه‌بندی پیشرفته تراکنش‌ها
 *
 * @package     ReadyWallet/Classes
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Shortcodes {

    /**
     * سازنده کلاس
     */
    public function __construct() {
        // ثبت شورتکد [ready_wallet]
        add_shortcode( 'ready_wallet', array( $this, 'render_wallet_shortcode' ) );
        
        // یکپارچگی با حساب کاربری ووکامرس
        add_action( 'init', array( $this, 'add_my_account_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_wallet_menu_item' ) );
        
        // اتصال محتوا به اندپوینت حساب کاربری
        // نام اکشن باید با نام اندپوینت یکی باشد: woocommerce_account_{ENDPOINT}_endpoint
        $endpoint = get_option( 'ready_wallet_endpoint', 'ready-wallet' );
        add_action( 'woocommerce_account_' . $endpoint . '_endpoint', array( $this, 'render_wallet_page' ) );

        // بارگذاری مشروط اسکریپت‌ها
        add_action( 'wp_enqueue_scripts', array( $this, 'conditional_enqueue_assets' ) );
    }

    /**
     * 1. رندر کردن خروجی شورتکد
     * * @param array $atts ویژگی‌های شورتکد
     * @return string خروجی HTML
     */
    public function render_wallet_shortcode( $atts ) {
        // جلوگیری از دسترسی مهمان
        if ( ! is_user_logged_in() ) {
            $login_url = get_permalink( get_option('woocommerce_myaccount_page_id') );
            $message = sprintf( 
                '<div class="woocommerce-info">%s <a href="%s" class="rw-login-link">%s</a></div>', 
                __( 'برای دسترسی به کیف پول، لطفاً', 'ready-wallet' ), 
                esc_url( $login_url ), 
                __( 'وارد شوید', 'ready-wallet' ) 
            );
            return $message;
        }

        // شروع بافر برای جلوگیری از چاپ مستقیم
        ob_start();
        $this->render_wallet_ui();
        return ob_get_clean();
    }

    /**
     * 2. رندر کردن در صفحه حساب کاربری
     */
    public function render_wallet_page() {
        $this->render_wallet_ui();
    }

    /**
     * هسته اصلی تولید رابط کاربری (UI)
     * این متد ساختار تب‌ها و محتوا را مدیریت می‌کند.
     */
    private function render_wallet_ui() {
        // اطمینان از لود شدن استایل‌ها (در صورتی که شرطی لود نشده باشند)
        wp_enqueue_style( 'ready-wallet-front' );
        wp_enqueue_script( 'ready-wallet-js' );

        $user_id = get_current_user_id();
        
        // نمایش پیام‌های خطا/موفقیت ووکامرس (Flash Messages)
        // مثل: "موجودی کافی نیست" یا "شارژ با موفقیت انجام شد"
        echo '<div class="rw-notices-wrapper">';
        wc_print_notices();
        echo '</div>';

        ?>
        <div class="ready-wallet-container">
            
            <!-- الف: کارت موجودی (Header) -->
            <?php 
                $balance_template = READY_WALLET_ABSPATH . 'templates/wallet/balance.php';
                if ( file_exists( $balance_template ) ) {
                    include $balance_template;
                }
            ?>

            <!-- ب: سیستم تب‌بندی داینامیک -->
            <?php 
                // تعریف تب‌های پیش‌فرض
                $tabs = array(
                    'transactions' => array(
                        'title' => __( 'تراکنش‌ها', 'ready-wallet' ),
                        'icon'  => 'dashicons-list-view',
                        'priority' => 10
                    ),
                    'deposit' => array(
                        'title' => __( 'افزایش اعتبار', 'ready-wallet' ),
                        'icon'  => 'dashicons-plus-alt2',
                        'priority' => 20
                    ),
                    'withdraw' => array(
                        'title' => __( 'برداشت وجه', 'ready-wallet' ),
                        'icon'  => 'dashicons-download',
                        'priority' => 30
                    ),
                    'transfer' => array(
                        'title' => __( 'انتقال وجه', 'ready-wallet' ),
                        'icon'  => 'dashicons-migrate',
                        'priority' => 40
                    ),
                );

                // اجازه به سایر پلاگین‌ها برای افزودن تب جدید (Hookable)
                $tabs = apply_filters( 'ready_wallet_tabs', $tabs );

                // مرتب‌سازی تب‌ها بر اساس اولویت
                uasort( $tabs, function($a, $b) {
                    return $a['priority'] - $b['priority'];
                });
            ?>

            <div class="rw-tabs-wrapper">
                <!-- منوی تب‌ها -->
                <ul class="rw-tabs-nav">
                    <?php 
                    $is_first = true;
                    foreach ( $tabs as $key => $tab ) : 
                        $active_class = $is_first ? 'active' : '';
                    ?>
                        <li class="<?php echo esc_attr( $active_class ); ?>" data-tab="<?php echo esc_attr( $key ); ?>">
                            <?php if ( ! empty( $tab['icon'] ) ) : ?>
                                <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html( $tab['title'] ); ?>
                        </li>
                    <?php 
                        $is_first = false;
                    endforeach; 
                    ?>
                </ul>

                <!-- محتوای تب‌ها -->
                <?php 
                $is_first = true;
                foreach ( $tabs as $key => $tab ) : 
                    $active_class = $is_first ? 'active' : '';
                    $is_first = false;
                ?>
                    <div id="<?php echo esc_attr( $key ); ?>" class="rw-tab-content <?php echo esc_attr( $active_class ); ?>">
                        <?php 
                            // فراخوانی متد مربوط به هر تب یا فایل تمپلیت
                            // مثال: اگر کلید تب 'deposit' باشد، فایل templates/wallet/deposit.php لود می‌شود
                            // یا اگر تابع render_tab_deposit وجود داشته باشد اجرا می‌شود.
                            
                            $this->render_tab_content( $key );
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * مدیریت محتوای هر تب
     * این تابع تصمیم می‌گیرد چه چیزی در هر تب نمایش داده شود.
     */
    private function render_tab_content( $tab_key ) {
        // 1. اگر اکشن خاصی برای این تب تعریف شده باشد (برای توسعه‌دهندگان)
        if ( has_action( "ready_wallet_tab_content_{$tab_key}" ) ) {
            do_action( "ready_wallet_tab_content_{$tab_key}" );
            return;
        }

        // 2. اگر فایل تمپلیت استاندارد وجود داشته باشد
        $template_file = READY_WALLET_ABSPATH . "templates/wallet/{$tab_key}.php";
        
        // استثنا برای تب تراکنش‌ها (نیاز به منطق دیتابیس دارد)
        if ( 'transactions' === $tab_key ) {
            $template_file = READY_WALLET_ABSPATH . 'templates/wallet/transaction-list.php';
            $this->setup_transactions_data(); // آماده‌سازی داده‌ها قبل از لود فایل
        }

        if ( file_exists( $template_file ) ) {
            include $template_file;
        } else {
            // محتوای پیش‌فرض برای تب‌های بدون فایل
            echo '<p>' . __( 'محتوایی برای نمایش وجود ندارد.', 'ready-wallet' ) . '</p>';
        }
    }

    /**
     * آماده‌سازی داده‌های تراکنش (Pagination Logic)
     * این متد متغیرهای لازم را به scope سراسری یا query_var می‌فرستد.
     */
    private function setup_transactions_data() {
        $user_id = get_current_user_id();
        $per_page = 10;
        $current_page = isset( $_GET['rw_page'] ) ? max( 1, intval( $_GET['rw_page'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        $wallet_db = new Ready_Wallet_DB();
        
        $transactions = $wallet_db->get_transactions([
            'user_id' => $user_id,
            'limit'   => $per_page,
            'offset'  => $offset
        ]);

        // محاسبه تعداد کل (برای صفحه‌بندی)
        global $wpdb;
        $table_name = $wpdb->prefix . 'ready_wallet_transactions';
        // استفاده از کش برای کوئری کانت (Performance)
        $total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE user_id = %d", $user_id ) );
        $total_pages = ceil( $total_count / $per_page );

        set_query_var( 'rw_transactions', $transactions );
        set_query_var( 'rw_current_page', $current_page );
        set_query_var( 'rw_total_pages', $total_pages );
    }

    /**
     * افزودن Endpoint به لیست متغیرهای کوئری وردپرس
     */
    public function add_query_vars( $vars ) {
        $endpoint = get_option( 'ready_wallet_endpoint', 'ready-wallet' );
        $vars[] = $endpoint;
        return $vars;
    }

    /**
     * ثبت Endpoint جدید در ساختار URL ووکامرس
     */
    public function add_my_account_endpoint() {
        $endpoint = get_option( 'ready_wallet_endpoint', 'ready-wallet' );
        add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
    }

    /**
     * افزودن لینک "کیف پول" به منوی حساب کاربری
     */
    public function add_wallet_menu_item( $items ) {
        $new_items = array();
        $endpoint = get_option( 'ready_wallet_endpoint', 'ready-wallet' );
        $title = get_option( 'ready_wallet_menu_title', __( 'کیف پول من', 'ready-wallet' ) );

        // درج آیتم قبل از دکمه خروج (Customer Logout)
        foreach ( $items as $key => $value ) {
            if ( 'customer-logout' === $key ) {
                $new_items[ $endpoint ] = $title;
            }
            $new_items[ $key ] = $value;
        }
        return $new_items;
    }

    /**
     * بارگذاری بهینه استایل‌ها و اسکریپت‌ها
     * فقط در صفحات مرتبط لود شوند.
     */
    public function conditional_enqueue_assets() {
        global $post;

        $load_assets = false;
        $endpoint = get_option( 'ready_wallet_endpoint', 'ready-wallet' );

        // 1. اگر در صفحه حساب کاربری و تب کیف پول هستیم
        if ( is_account_page() && isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], $endpoint ) !== false ) {
            $load_assets = true;
        }

        // 2. اگر شورتکد در محتوای صفحه وجود دارد
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ready_wallet' ) ) {
            $load_assets = true;
        }

        if ( $load_assets ) {
            // فراخوانی متد عمومی لودر (تعریف شده در کلاس اصلی)
            Ready_Wallet()->enqueue_assets();
        }
    }
}

return new Ready_Wallet_Shortcodes();