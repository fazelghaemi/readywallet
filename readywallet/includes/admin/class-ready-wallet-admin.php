<?php
/**
 * ReadyWallet Admin Interface
 * مدیریت پیشخوان افزونه: نمایش لیست تراکنش‌ها و تغییر دستی موجودی
 * * ویژگی‌ها:
 * - نمایش جدول تراکنش‌ها از دیتابیس اختصاصی
 * - فرم واریز/برداشت دستی توسط ادمین
 * - فیلتر کردن تراکنش‌ها بر اساس کاربر
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_manual_transaction' ) );
    }

    /**
     * افزودن منو به پیشخوان وردپرس
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
     * نمایش صفحه اصلی (لیست تراکنش‌ها)
     */
    public function render_dashboard_page() {
        // دریافت تراکنش‌ها از کلاس دیتابیس
        $wallet_db = new Ready_Wallet_DB();
        
        $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;

        $args = [
            'limit'  => $per_page,
            'offset' => $offset,
            'order'  => 'DESC'
        ];

        // فیلتر بر اساس کاربر
        if ( isset( $_GET['user_id'] ) && ! empty( $_GET['user_id'] ) ) {
            $args['user_id'] = intval( $_GET['user_id'] );
        }

        $transactions = $wallet_db->get_transactions( $args );
        
        // محاسبه تعداد کل برای صفحه‌بندی (نیاز به کوئری count دارد که فعلاً ساده‌سازی شده)
        // برای نسخه حرفه‌ای باید متد count_transactions به کلاس DB اضافه شود.
        
        ?>
        <div class="wrap ready-wallet-wrap">
            <h1 class="wp-heading-inline"><?php _e( 'لیست تراکنش‌های کیف پول', 'ready-wallet' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=ready-wallet-manual' ); ?>" class="page-title-action"><?php _e( 'تراکنش دستی جدید', 'ready-wallet' ); ?></a>
            <hr class="wp-header-end">

            <div class="card" style="max-width: 100%; margin-top: 20px; padding: 0;">
                <table class="wp-list-table widefat fixed striped table-view-list posts">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-primary"><?php _e( 'شناسه', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'کاربر', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'نوع', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'موجودی پس از تراکنش', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'توضیحات', 'ready-wallet' ); ?></th>
                            <th scope="col" class="manage-column"><?php _e( 'تاریخ', 'ready-wallet' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $transactions ) ) : ?>
                            <?php foreach ( $transactions as $transaction ) : 
                                $user = get_userdata( $transaction->user_id );
                                $type_label = '';
                                $type_class = '';
                                
                                switch ( $transaction->type ) {
                                    case 'credit': $type_label = 'افزایش اعتبار'; $type_class = 'updated'; break;
                                    case 'debit': $type_label = 'برداشت/خرید'; $type_class = 'error'; break;
                                    case 'cashback': $type_label = 'کش‌بک خرید'; $type_class = 'updated'; break;
                                    default: $type_label = $transaction->type;
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $transaction->id; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url( 'user-edit.php?user_id=' . $transaction->user_id ); ?>">
                                            <?php echo $user ? $user->display_name : __( 'کاربر حذف شده', 'ready-wallet' ); ?>
                                        </a>
                                        <br>
                                        <small><?php echo $user ? $user->user_email : ''; ?></small>
                                    </td>
                                    <td><span class="badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                                    <td><strong><?php echo wc_price( $transaction->amount ); ?></strong></td>
                                    <td><?php echo wc_price( $transaction->balance ); ?></td>
                                    <td><?php echo $transaction->description; ?></td>
                                    <td><?php echo date_i18n( 'Y/m/d H:i', strtotime( $transaction->date ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7"><?php _e( 'هیچ تراکنشی یافت نشد.', 'ready-wallet' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * نمایش فرم تغییر دستی موجودی
     */
    public function render_manual_transaction_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'تغییر موجودی دستی کاربران', 'ready-wallet' ); ?></h1>
            
            <div class="card" style="max-width: 600px; padding: 20px;">
                <form method="post" action="">
                    <?php wp_nonce_field( 'rw_manual_transaction', 'rw_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="user_id"><?php _e( 'شناسه کاربر (User ID)', 'ready-wallet' ); ?></label></th>
                            <td>
                                <input type="number" name="user_id" id="user_id" class="regular-text" required placeholder="مثال: 1">
                                <p class="description"><?php _e( 'شناسه کاربری که می‌خواهید موجودی او را تغییر دهید.', 'ready-wallet' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="type"><?php _e( 'نوع عملیات', 'ready-wallet' ); ?></label></th>
                            <td>
                                <select name="type" id="type">
                                    <option value="credit"><?php _e( 'افزایش اعتبار (Credit)', 'ready-wallet' ); ?></option>
                                    <option value="debit"><?php _e( 'کسر اعتبار (Debit)', 'ready-wallet' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="amount"><?php _e( 'مبلغ', 'ready-wallet' ); ?></label></th>
                            <td>
                                <input type="number" name="amount" id="amount" class="regular-text" required step="0.01">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description"><?php _e( 'توضیحات', 'ready-wallet' ); ?></label></th>
                            <td>
                                <textarea name="description" id="description" class="regular-text" rows="3"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit_manual_transaction" id="submit" class="button button-primary" value="<?php _e( 'ثبت تراکنش', 'ready-wallet' ); ?>">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * پردازش فرم ارسال دستی
     */
    public function handle_manual_transaction() {
        if ( isset( $_POST['submit_manual_transaction'] ) && isset( $_POST['rw_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['rw_nonce'], 'rw_manual_transaction' ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            $user_id = intval( $_POST['user_id'] );
            $amount  = floatval( $_POST['amount'] );
            $type    = sanitize_text_field( $_POST['type'] );
            $desc    = sanitize_textarea_field( $_POST['description'] );

            $wallet_db = new Ready_Wallet_DB();
            
            $result = $wallet_db->add_transaction([
                'user_id'      => $user_id,
                'amount'       => $amount,
                'type'         => $type,
                'description'  => $desc . ' (توسط مدیر)',
                'admin_id'     => get_current_user_id()
            ]);

            if ( is_wp_error( $result ) ) {
                add_action( 'admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                });
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' . __( 'تراکنش با موفقیت ثبت شد.', 'ready-wallet' ) . '</p></div>';
                });
            }
        }
    }
}

return new Ready_Wallet_Admin();