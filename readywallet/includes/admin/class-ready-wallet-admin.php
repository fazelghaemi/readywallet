<?php
/**
 * ReadyWallet Admin Interface
 * مدیریت پیشخوان افزونه: نمایش لیست تراکنش‌ها و تغییر دستی موجودی
 * * آپدیت: افزودن جستجوی AJAX کاربر و کارت‌های آماری
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_manual_transaction' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * بارگذاری اسکریپت‌های جستجوی کاربر ووکامرس
     */
    public function enqueue_scripts() {
        if ( isset($_GET['page']) && strpos($_GET['page'], 'ready-wallet') !== false ) {
            wp_enqueue_script( 'woocommerce_admin' );
            wp_enqueue_script( 'select2' );
            wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css' );
        }
    }

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

        add_submenu_page(
            'ready-wallet',
            __( 'تراکنش‌ها', 'ready-wallet' ),
            __( 'تراکنش‌ها', 'ready-wallet' ),
            'manage_woocommerce',
            'ready-wallet',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'ready-wallet',
            __( 'تغییر موجودی دستی', 'ready-wallet' ),
            __( 'تغییر موجودی', 'ready-wallet' ),
            'manage_woocommerce',
            'ready-wallet-manual',
            array( $this, 'render_manual_transaction_page' )
        );
    }

    /**
     * نمایش صفحه اصلی (لیست تراکنش‌ها + آمار)
     */
    public function render_dashboard_page() {
        $wallet_db = new Ready_Wallet_DB();
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;

        $args = [ 'limit' => $per_page, 'offset' => $offset, 'order' => 'DESC' ];

        if ( isset( $_GET['user_id'] ) && ! empty( $_GET['user_id'] ) ) {
            $args['user_id'] = intval( $_GET['user_id'] );
        }

        $transactions = $wallet_db->get_transactions( $args );
        
        // محاسبه آمار سریع (می‌تواند کش شود)
        global $wpdb;
        $table = $wpdb->prefix . 'ready_wallet_transactions';
        $total_credit = $wpdb->get_var("SELECT SUM(amount) FROM $table WHERE type='credit'");
        $total_debit = $wpdb->get_var("SELECT SUM(amount) FROM $table WHERE type='debit'");
        ?>
        
        <div class="wrap ready-wallet-wrap">
            <h1 class="wp-heading-inline"><?php _e( 'کیف پول هوشمند ردی', 'ready-wallet' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=ready-wallet-manual' ); ?>" class="page-title-action primary-btn"><?php _e( 'ثبت تراکنش جدید', 'ready-wallet' ); ?></a>
            <hr class="wp-header-end">

            <!-- کارت‌های آماری -->
            <div class="rw-admin-stats">
                <div class="rw-stat-card credit">
                    <h3><?php echo wc_price($total_credit); ?></h3>
                    <span><?php _e('کل شارژ انجام شده', 'ready-wallet'); ?></span>
                </div>
                <div class="rw-stat-card debit">
                    <h3><?php echo wc_price($total_debit); ?></h3>
                    <span><?php _e('کل مصرف کاربران', 'ready-wallet'); ?></span>
                </div>
                <div class="rw-stat-card count">
                    <h3><?php echo count($transactions); ?>+</h3>
                    <span><?php _e('تراکنش‌های اخیر', 'ready-wallet'); ?></span>
                </div>
            </div>

            <!-- جدول تراکنش‌ها -->
            <div class="rw-admin-table-wrapper">
                <table class="wp-list-table widefat fixed striped table-view-list posts">
                    <thead>
                        <tr>
                            <th><?php _e( 'شناسه', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'کاربر', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'نوع عملیات', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'موجودی نهایی', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'توضیحات', 'ready-wallet' ); ?></th>
                            <th><?php _e( 'تاریخ', 'ready-wallet' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $transactions ) ) : ?>
                            <?php foreach ( $transactions as $transaction ) : 
                                $user = get_userdata( $transaction->user_id );
                                $status_class = ($transaction->type == 'credit' || $transaction->type == 'cashback') ? 'status-credit' : 'status-debit';
                                $status_label = ($transaction->type == 'credit') ? 'واریز' : (($transaction->type == 'debit') ? 'برداشت' : $transaction->type);
                            ?>
                                <tr>
                                    <td>#<?php echo $transaction->id; ?></td>
                                    <td class="user-col">
                                        <?php if($user): ?>
                                            <div class="user-info">
                                                <strong><?php echo $user->display_name; ?></strong>
                                                <small><?php echo $user->user_email; ?></small>
                                            </div>
                                        <?php else: echo 'کاربر حذف شده'; endif; ?>
                                    </td>
                                    <td><span class="rw-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                                    <td class="amount-col"><?php echo wc_price( $transaction->amount ); ?></td>
                                    <td><?php echo wc_price( $transaction->balance ); ?></td>
                                    <td><?php echo $transaction->description; ?></td>
                                    <td><?php echo date_i18n( 'Y/m/d H:i', strtotime( $transaction->date ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="7" style="text-align:center; padding: 20px;"><?php _e( 'هنوز تراکنشی ثبت نشده است.', 'ready-wallet' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * نمایش فرم تغییر دستی موجودی (با جستجوی پیشرفته)
     */
    public function render_manual_transaction_page() {
        ?>
        <div class="wrap ready-wallet-wrap">
            <h1 class="wp-heading-inline"><?php _e( 'تراکنش دستی', 'ready-wallet' ); ?></h1>
            
            <div class="rw-admin-form-card">
                <form method="post" action="">
                    <?php wp_nonce_field( 'rw_manual_transaction', 'rw_nonce' ); ?>
                    
                    <div class="rw-form-group">
                        <label for="user_id"><?php _e( 'انتخاب کاربر', 'ready-wallet' ); ?></label>
                        <!-- کلاس wc-customer-search به طور خودکار این فیلد را به جستجوی AJAX تبدیل می‌کند -->
                        <select class="wc-customer-search" name="user_id" id="user_id" data-placeholder="<?php _e( 'جستجو بر اساس نام یا شماره موبایل...', 'ready-wallet' ); ?>" style="width: 100%;" required></select>
                        <p class="description"><?php _e( 'نام، ایمیل یا شماره موبایل کاربر را تایپ کنید.', 'ready-wallet' ); ?></p>
                    </div>

                    <div class="rw-form-row">
                        <div class="rw-form-group half">
                            <label for="type"><?php _e( 'نوع عملیات', 'ready-wallet' ); ?></label>
                            <select name="type" id="type" class="regular-text">
                                <option value="credit"><?php _e( 'افزایش اعتبار (شارژ)', 'ready-wallet' ); ?></option>
                                <option value="debit"><?php _e( 'کسر اعتبار (جریمه/اصلاح)', 'ready-wallet' ); ?></option>
                            </select>
                        </div>
                        <div class="rw-form-group half">
                            <label for="amount"><?php _e( 'مبلغ (تومان)', 'ready-wallet' ); ?></label>
                            <input type="number" name="amount" id="amount" class="regular-text" required step="any" placeholder="0">
                        </div>
                    </div>

                    <div class="rw-form-group">
                        <label for="description"><?php _e( 'توضیحات (بابت...)', 'ready-wallet' ); ?></label>
                        <textarea name="description" id="description" rows="3" class="large-text" placeholder="مثال: هدیه جشنواره..."></textarea>
                    </div>
                    
                    <div class="rw-form-actions">
                        <input type="submit" name="submit_manual_transaction" id="submit" class="button button-primary button-hero" value="<?php _e( 'ثبت و اعمال تغییرات', 'ready-wallet' ); ?>">
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    // هندلر فرم بدون تغییر باقی مانده است...
    public function handle_manual_transaction() {
        if ( isset( $_POST['submit_manual_transaction'] ) && isset( $_POST['rw_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['rw_nonce'], 'rw_manual_transaction' ) ) return;
            if ( ! current_user_can( 'manage_woocommerce' ) ) return;

            $user_id = intval( $_POST['user_id'] );
            $amount  = floatval( $_POST['amount'] );
            $type    = sanitize_text_field( $_POST['type'] );
            $desc    = sanitize_textarea_field( $_POST['description'] );

            $wallet_db = new Ready_Wallet_DB();
            $result = $wallet_db->add_transaction([
                'user_id' => $user_id, 'amount' => $amount, 'type' => $type,
                'description' => $desc . ' (توسط مدیر)', 'admin_id' => get_current_user_id()
            ]);

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ($result) { echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>'; });
            } else {
                add_action( 'admin_notices', function() { echo '<div class="notice notice-success"><p>' . __( 'تراکنش با موفقیت ثبت شد.', 'ready-wallet' ) . '</p></div>'; });
            }
        }
    }
}
return new Ready_Wallet_Admin();