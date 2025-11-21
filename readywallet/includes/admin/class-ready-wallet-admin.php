<?php
/**
 * ReadyWallet Admin Interface (Standalone Version)
 * * مدیریت کامل پیشخوان: 
 * 1. داشبورد (آمار و لیست)
 * 2. تغییر موجودی دستی
 * 3. تنظیمات (کاملاً مستقل از ووکامرس)
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_manual_transaction' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) ); // ثبت تنظیمات جدید
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * بارگذاری اسکریپت‌ها
     */
    public function enqueue_scripts() {
        if ( isset($_GET['page']) && strpos($_GET['page'], 'ready-wallet') !== false ) {
            wp_enqueue_style( 'ready-wallet-admin-css', READY_WALLET_PLUGIN_URL . 'assets/css/ready-wallet-admin.css', array(), READY_WALLET_PLUGIN_VERSION );
            wp_enqueue_script( 'woocommerce_admin' );
            wp_enqueue_script( 'wc-enhanced-select' ); 
            wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
        }
    }

    /**
     * ثبت منوها
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'کیف پول ردی', 'ready-wallet' ),
            __( 'کیف پول ردی', 'ready-wallet' ),
            'manage_woocommerce',
            'ready-wallet',
            array( $this, 'render_dashboard_page' ),
            'dashicons-wallet',
            56
        );

        add_submenu_page( 'ready-wallet', __( 'داشبورد', 'ready-wallet' ), __( 'داشبورد', 'ready-wallet' ), 'manage_woocommerce', 'ready-wallet', array( $this, 'render_dashboard_page' ) );
        add_submenu_page( 'ready-wallet', __( 'تغییر موجودی', 'ready-wallet' ), __( 'تغییر موجودی', 'ready-wallet' ), 'manage_woocommerce', 'ready-wallet-manual', array( $this, 'render_manual_transaction_page' ) );
        
        // صفحه تنظیمات اختصاصی
        add_submenu_page( 'ready-wallet', __( 'تنظیمات', 'ready-wallet' ), __( 'تنظیمات', 'ready-wallet' ), 'manage_woocommerce', 'ready-wallet-settings', array( $this, 'render_settings_page' ) );
    }

    /**
     * ثبت فیلدهای تنظیمات در وردپرس (Settings API)
     */
    public function register_plugin_settings() {
        // گروه تنظیمات عمومی
        register_setting( 'ready_wallet_general_group', 'ready_wallet_menu_title' );
        register_setting( 'ready_wallet_general_group', 'ready_wallet_endpoint' );
        register_setting( 'ready_wallet_general_group', 'ready_wallet_min_deposit' );
        register_setting( 'ready_wallet_general_group', 'ready_wallet_max_deposit' );

        // گروه تنظیمات پیامک
        register_setting( 'ready_wallet_sms_group', 'ready_wallet_sms_enable' );
        register_setting( 'ready_wallet_sms_group', 'ready_wallet_sms_api_key' );
        register_setting( 'ready_wallet_sms_group', 'ready_wallet_sms_tpl_charge' );
        register_setting( 'ready_wallet_sms_group', 'ready_wallet_sms_tpl_debit' );
    }

    /**
     * رندر صفحه تنظیمات (تب‌بندی شده)
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap ready-wallet-wrap">
            <h1 class="wp-heading-inline"><?php _e( 'تنظیمات کیف پول ردی', 'ready-wallet' ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=ready-wallet-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">عمومی</a>
                <a href="?page=ready-wallet-settings&tab=sms" class="nav-tab <?php echo $active_tab == 'sms' ? 'nav-tab-active' : ''; ?>">درگاه پیامک</a>
            </h2>

            <div class="rw-admin-form-card" style="max-width: 800px; margin-top: 20px;">
                <form method="post" action="options.php">
                    <?php
                    if ( $active_tab == 'general' ) {
                        settings_fields( 'ready_wallet_general_group' );
                        do_settings_sections( 'ready_wallet_general_group' );
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">عنوان در منوی کاربری</th>
                                <td><input type="text" name="ready_wallet_menu_title" value="<?php echo esc_attr( get_option('ready_wallet_menu_title', 'کیف پول من') ); ?>" class="regular-text" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">آدرس صفحه (Endpoint)</th>
                                <td>
                                    <input type="text" name="ready_wallet_endpoint" value="<?php echo esc_attr( get_option('ready_wallet_endpoint', 'ready-wallet') ); ?>" class="regular-text" />
                                    <p class="description">پس از تغییر، پیوندهای یکتا را ذخیره کنید.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">حداقل مبلغ شارژ (تومان)</th>
                                <td><input type="number" name="ready_wallet_min_deposit" value="<?php echo esc_attr( get_option('ready_wallet_min_deposit', 1000) ); ?>" class="regular-text" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">حداکثر مبلغ شارژ (تومان)</th>
                                <td><input type="number" name="ready_wallet_max_deposit" value="<?php echo esc_attr( get_option('ready_wallet_max_deposit', 0) ); ?>" class="regular-text" /></td>
                            </tr>
                        </table>
                        <?php
                    } else {
                        settings_fields( 'ready_wallet_sms_group' );
                        do_settings_sections( 'ready_wallet_sms_group' );
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">فعالسازی پیامک</th>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" name="ready_wallet_sms_enable" value="yes" <?php checked( get_option('ready_wallet_sms_enable'), 'yes' ); ?> />
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">کلید API (MessageWay)</th>
                                <td><input type="password" name="ready_wallet_sms_api_key" value="<?php echo esc_attr( get_option('ready_wallet_sms_api_key') ); ?>" class="regular-text" style="width: 100%;" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">کد پترن شارژ (Credit)</th>
                                <td><input type="text" name="ready_wallet_sms_tpl_charge" value="<?php echo esc_attr( get_option('ready_wallet_sms_tpl_charge') ); ?>" class="regular-text" placeholder="مثال: 1001" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">کد پترن کسر/برداشت (Debit)</th>
                                <td><input type="text" name="ready_wallet_sms_tpl_debit" value="<?php echo esc_attr( get_option('ready_wallet_sms_tpl_debit') ); ?>" class="regular-text" placeholder="مثال: 1002" /></td>
                            </tr>
                        </table>
                        <?php
                    }
                    
                    submit_button( 'ذخیره تنظیمات', 'primary button-hero' );
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    // --- بخش داشبورد و تراکنش‌ها (بدون تغییر) ---
    public function render_dashboard_page() {
        $wallet_db = new Ready_Wallet_DB();
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 15;
        $offset = ( $page - 1 ) * $per_page;

        $args = [ 'limit' => $per_page, 'offset' => $offset, 'order' => 'DESC' ];
        $transactions = $wallet_db->get_transactions( $args );
        
        global $wpdb;
        $table = $wpdb->prefix . 'ready_wallet_transactions';
        $total_credit = $wpdb->get_var("SELECT SUM(amount) FROM $table WHERE type IN ('credit', 'cashback')");
        $total_debit = $wpdb->get_var("SELECT SUM(amount) FROM $table WHERE type='debit'");
        $count = $wpdb->get_var("SELECT COUNT(id) FROM $table");
        ?>
        <div class="wrap ready-wallet-wrap">
            <div class="rw-header">
                <h1 class="wp-heading-inline"><?php _e( 'داشبورد کیف پول', 'ready-wallet' ); ?></h1>
                <a href="<?php echo admin_url( 'admin.php?page=ready-wallet-manual' ); ?>" class="page-title-action primary-btn"><?php _e( 'ثبت تراکنش جدید', 'ready-wallet' ); ?></a>
            </div>

            <div class="rw-admin-stats">
                <div class="rw-stat-card credit">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <div class="stat-content">
                        <h3><?php echo wc_price($total_credit); ?></h3>
                        <span><?php _e('کل واریزی‌ها', 'ready-wallet'); ?></span>
                    </div>
                </div>
                <div class="rw-stat-card debit">
                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                    <div class="stat-content">
                        <h3><?php echo wc_price($total_debit); ?></h3>
                        <span><?php _e('کل برداشت‌ها', 'ready-wallet'); ?></span>
                    </div>
                </div>
                <div class="rw-stat-card total">
                    <span class="dashicons dashicons-chart-area"></span>
                    <div class="stat-content">
                        <h3><?php echo number_format_i18n($count); ?></h3>
                        <span><?php _e('تعداد تراکنش‌ها', 'ready-wallet'); ?></span>
                    </div>
                </div>
            </div>

            <div class="rw-admin-table-wrapper">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'شناسه', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'کاربر', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'نوع', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'توضیحات', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'تاریخ', 'ready-wallet' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $transactions ) ) : ?>
                            <?php foreach ( $transactions as $transaction ) : 
                                $user = get_userdata( $transaction->user_id );
                                $type_class = in_array($transaction->type, ['credit', 'cashback']) ? 'status-credit' : 'status-debit';
                                $type_label = ($transaction->type == 'credit') ? 'واریز' : (($transaction->type == 'debit') ? 'برداشت' : $transaction->type);
                            ?>
                                <tr>
                                    <td>#<?php echo $transaction->id; ?></td>
                                    <td>
                                        <?php if($user): ?>
                                            <strong><a href="<?php echo get_edit_user_link($user->ID); ?>"><?php echo $user->display_name; ?></a></strong>
                                            <br><span class="description"><?php echo $user->user_email; ?></span>
                                        <?php else: echo 'کاربر حذف شده'; endif; ?>
                                    </td>
                                    <td><span class="rw-badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                                    <td class="amount-col"><?php echo wc_price( $transaction->amount ); ?></td>
                                    <td><?php echo $transaction->description; ?></td>
                                    <td><?php echo date_i18n( 'Y/m/d H:i', strtotime( $transaction->date ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6" style="text-align:center; padding: 20px;"><?php _e( 'هنوز تراکنشی ثبت نشده است.', 'ready-wallet' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // --- بخش تغییر موجودی (بدون تغییر عمده) ---
    public function render_manual_transaction_page() {
        ?>
        <div class="wrap ready-wallet-wrap">
            <h1 class="wp-heading-inline"><?php _e( 'تراکنش دستی', 'ready-wallet' ); ?></h1>
            <div class="rw-admin-form-card">
                <form method="post" action="">
                    <?php wp_nonce_field( 'rw_manual_transaction', 'rw_nonce' ); ?>
                    <div class="rw-form-group">
                        <label><?php _e( 'انتخاب کاربر', 'ready-wallet' ); ?></label>
                        <select class="wc-customer-search" name="user_id" data-placeholder="<?php _e( 'جستجو بر اساس نام یا موبایل...', 'ready-wallet' ); ?>" style="width: 100%;" required></select>
                    </div>
                    <div class="rw-form-row">
                        <div class="rw-form-group half">
                            <label><?php _e( 'نوع عملیات', 'ready-wallet' ); ?></label>
                            <select name="type" class="regular-text" style="width:100%">
                                <option value="credit"><?php _e( 'افزایش اعتبار (شارژ)', 'ready-wallet' ); ?></option>
                                <option value="debit"><?php _e( 'کسر اعتبار (جریمه)', 'ready-wallet' ); ?></option>
                            </select>
                        </div>
                        <div class="rw-form-group half">
                            <label><?php _e( 'مبلغ (تومان)', 'ready-wallet' ); ?></label>
                            <input type="number" name="amount" required step="any" placeholder="0" style="width:100%">
                        </div>
                    </div>
                    <div class="rw-form-group">
                        <label><?php _e( 'توضیحات', 'ready-wallet' ); ?></label>
                        <textarea name="description" rows="3" class="large-text" placeholder="علت تغییر موجودی..." style="width:100%"></textarea>
                    </div>
                    <div class="rw-form-actions">
                        <input type="submit" name="submit_manual_transaction" class="button button-primary button-hero" value="<?php _e( 'ثبت تغییرات', 'ready-wallet' ); ?>">
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_manual_transaction() {
        if ( isset( $_POST['submit_manual_transaction'] ) && check_admin_referer( 'rw_manual_transaction', 'rw_nonce' ) ) {
            if ( ! current_user_can( 'manage_woocommerce' ) ) return;
            $user_id = intval( $_POST['user_id'] );
            $amount = floatval( $_POST['amount'] );
            $type = sanitize_text_field( $_POST['type'] );
            $desc = sanitize_textarea_field( $_POST['description'] );

            if ( $user_id > 0 && $amount > 0 ) {
                $wallet_db = new Ready_Wallet_DB();
                $result = $wallet_db->add_transaction([
                    'user_id' => $user_id, 'amount' => $amount, 'type' => $type,
                    'description' => $desc . ' (توسط مدیر)', 'admin_id' => get_current_user_id()
                ]);
                if ( ! is_wp_error( $result ) ) {
                    add_action( 'admin_notices', function() { echo '<div class="notice notice-success is-dismissible"><p>' . __('تراکنش با موفقیت ثبت شد.', 'ready-wallet') . '</p></div>'; });
                } else {
                    $err = $result->get_error_message();
                    add_action( 'admin_notices', function() use($err) { echo '<div class="notice notice-error"><p>' . $err . '</p></div>'; });
                }
            }
        }
    }
}
return new Ready_Wallet_Admin();