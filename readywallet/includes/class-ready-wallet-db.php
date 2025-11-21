<?php
/**
 * ReadyWallet Database Manager
 * مدیریت دیتابیس اختصاصی و تراکنش‌های مالی
 * * ویژگی‌ها:
 * - ساخت جدول اختصاصی wp_ready_wallet_transactions برای سرعت بالا
 * - مدیریت قفل‌های تراکنش برای جلوگیری از Race Condition
 * - محاسبه تراز لحظه‌ای
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_DB {

    /**
     * نام جدول تراکنش‌ها
     */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ready_wallet_transactions';
    }

    /**
     * نصب و ساخت جدول در دیتابیس
     * این تابع باید در زمان فعال‌سازی افزونه اجرا شود
     */
    public function install_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(18,4) NOT NULL DEFAULT 0.0000,
            balance decimal(18,4) NOT NULL DEFAULT 0.0000,
            currency varchar(10) NOT NULL DEFAULT 'IRT',
            type varchar(20) NOT NULL COMMENT 'credit, debit, cashback, refund',
            reference_id varchar(100) DEFAULT NULL COMMENT 'Order ID or Bank Ref ID',
            description text DEFAULT NULL,
            admin_id bigint(20) DEFAULT NULL,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_id (reference_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * ثبت تراکنش جدید (قلب تپنده افزونه)
     * * @param array $args آرایه اطلاعات تراکنش
     * @return int|WP_Error شناسه تراکنش یا خطا
     */
    public function add_transaction( $args ) {
        global $wpdb;

        $defaults = [
            'user_id'      => get_current_user_id(),
            'amount'       => 0,
            'type'         => 'credit', // credit (افزایش) | debit (کاهش)
            'reference_id' => '',
            'description'  => '',
            'admin_id'     => 0
        ];

        $args = wp_parse_args( $args, $defaults );

        // اعتبارسنجی
        if ( empty( $args['user_id'] ) || ! is_numeric( $args['amount'] ) || $args['amount'] <= 0 ) {
            return new WP_Error( 'invalid_data', __( 'اطلاعات تراکنش نامعتبر است.', 'ready-wallet' ) );
        }

        // شروع تراکنش دیتابیس (برای امنیت مالی)
        $wpdb->query( 'START TRANSACTION' );

        try {
            // دریافت موجودی فعلی با قفل (FOR UPDATE) برای جلوگیری از همزمانی
            // این کار باعث می‌شود اگر دو خرید همزمان رخ داد، موجودی منفی نشود.
            $current_balance = $wpdb->get_var( $wpdb->prepare(
                "SELECT balance FROM $this->table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1 FOR UPDATE",
                $args['user_id']
            ) );

            if ( null === $current_balance ) {
                $current_balance = 0;
            }

            // محاسبه موجودی جدید
            if ( $args['type'] === 'credit' || $args['type'] === 'cashback' || $args['type'] === 'refund' ) {
                $new_balance = $current_balance + $args['amount'];
            } else {
                // حالت برداشت (debit)
                if ( $current_balance < $args['amount'] ) {
                    throw new Exception( __( 'موجودی کافی نیست.', 'ready-wallet' ) );
                }
                $new_balance = $current_balance - $args['amount'];
            }

            // درج در دیتابیس
            $inserted = $wpdb->insert(
                $this->table_name,
                [
                    'user_id'      => $args['user_id'],
                    'amount'       => $args['amount'],
                    'balance'      => $new_balance,
                    'currency'     => get_woocommerce_currency(),
                    'type'         => $args['type'],
                    'reference_id' => $args['reference_id'],
                    'description'  => $args['description'],
                    'admin_id'     => $args['admin_id'],
                    'date'         => current_time( 'mysql' ),
                ],
                [ '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' ]
            );

            if ( ! $inserted ) {
                throw new Exception( __( 'خطا در ذخیره تراکنش در پایگاه داده.', 'ready-wallet' ) );
            }

            $transaction_id = $wpdb->insert_id;

            // بروزرسانی متا دیتا برای دسترسی سریع (کش)
            update_user_meta( $args['user_id'], '_ready_wallet_balance', $new_balance );

            $wpdb->query( 'COMMIT' );

            // اجرای اکشن برای ارسال پیامک یا ایمیل
            do_action( 'ready_wallet_transaction_complete', $transaction_id, $args );

            return $transaction_id;

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'transaction_failed', $e->getMessage() );
        }
    }

    /**
     * دریافت موجودی کاربر
     */
    public function get_wallet_balance( $user_id ) {
        global $wpdb;
        // ابتدا سعی می‌کنیم از متای کاربر بخوانیم (سریعتر)
        $balance = get_user_meta( $user_id, '_ready_wallet_balance', true );
        
        // اگر موجود نبود، از دیتابیس محاسبه کن
        if ( '' === $balance ) {
            $balance = $wpdb->get_var( $wpdb->prepare(
                "SELECT balance FROM $this->table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ) );
            $balance = $balance ? $balance : 0;
            update_user_meta( $user_id, '_ready_wallet_balance', $balance );
        }

        return (float) $balance;
    }

    /**
     * گزارش‌گیری پیشرفته (مشابه افزونه‌های فروشگاهی)
     */
    public function get_transactions( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'user_id' => 0,
            'limit'   => 10,
            'offset'  => 0,
            'order'   => 'DESC',
            'type'    => ''
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = "WHERE 1=1";
        if ( ! empty( $args['user_id'] ) ) {
            $where .= $wpdb->prepare( " AND user_id = %d", $args['user_id'] );
        }
        if ( ! empty( $args['type'] ) ) {
            $where .= $wpdb->prepare( " AND type = %s", $args['type'] );
        }

        $sql = "SELECT * FROM $this->table_name $where ORDER BY id {$args['order']} LIMIT %d OFFSET %d";
        
        return $wpdb->get_results( $wpdb->prepare( $sql, $args['limit'], $args['offset'] ) );
    }
}