<?php
/**
 * ReadyWallet Database Manager
 *
 * مدیریت پیشرفته دیتابیس اختصاصی، تراکنش‌های مالی و تضمین یکپارچگی داده‌ها.
 *
 * ویژگی‌های کلیدی:
 * - جدول اختصاصی برای پرفورمنس بالا (High Performance)
 * - قفل‌گذاری بدبینانه (Pessimistic Locking) برای جلوگیری از Race Condition
 * - سیستم کشینگ لایه اول (WP Object Cache)
 * - سیستم لاگ‌گیری استاندارد ووکامرس (Audit Logs)
 *
 * @package     ReadyWallet/Classes
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_DB {

    /**
     * نام جدول تراکنش‌ها در دیتابیس
     * @var string
     */
    private $table_name;

    /**
     * نام گروه کش برای مدیریت کشینگ
     * @var string
     */
    private $cache_group = 'ready_wallet';

    /**
     * نمونه لاگر ووکامرس
     * @var WC_Logger
     */
    private $logger;

    /**
     * سازنده کلاس
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ready_wallet_transactions';
        
        // راه‌اندازی سیستم لاگ اگر ووکامرس فعال باشد
        if ( function_exists( 'wc_get_logger' ) ) {
            $this->logger = wc_get_logger();
        }
    }

    /**
     * نصب یا بروزرسانی ساختار جدول دیتابیس
     * استفاده از dbDelta برای مدیریت هوشمند تغییرات Schema
     */
    public function install_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(26,8) NOT NULL DEFAULT 0.00000000,
            balance decimal(26,8) NOT NULL DEFAULT 0.00000000,
            currency varchar(10) NOT NULL DEFAULT 'IRT',
            type varchar(50) NOT NULL COMMENT 'credit, debit, cashback, refund, transfer',
            reference_id varchar(100) DEFAULT NULL COMMENT 'Order ID, Transaction ID',
            description text DEFAULT NULL,
            admin_id bigint(20) UNSIGNED DEFAULT 0,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_id (reference_id),
            KEY date (date)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * ثبت تراکنش جدید با امنیت و دقت بالا (Atomic Operation)
     *
     * @param array $args آرایه اطلاعات تراکنش
     * @return int|WP_Error شناسه تراکنش در صورت موفقیت یا شیء خطا
     */
    public function add_transaction( $args ) {
        global $wpdb;

        // 1. نرمال‌سازی و اعتبارسنجی داده‌ها
        $defaults = [
            'user_id'      => get_current_user_id(),
            'amount'       => 0,
            'type'         => 'credit', // credit (افزایش) | debit (کاهش)
            'reference_id' => '',
            'description'  => '',
            'admin_id'     => 0,
            'currency'     => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'IRT'
        ];

        $args = wp_parse_args( $args, $defaults );

        // تبدیل اجباری به عدد اعشاری دقیق
        $amount = filter_var( $args['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
        $user_id = absint( $args['user_id'] );

        if ( empty( $user_id ) ) {
            return new WP_Error( 'invalid_user', __( 'کاربر نامعتبر است.', 'ready-wallet' ) );
        }

        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', __( 'مبلغ تراکنش باید بزرگتر از صفر باشد.', 'ready-wallet' ) );
        }

        // 2. شروع تراکنش دیتابیس (ACID Compliance)
        $wpdb->query( 'START TRANSACTION' );

        try {
            // 3. قفل کردن رکورد کاربر (Pessimistic Locking)
            // این کوئری آخرین موجودی کاربر را می‌گیرد و تا پایان تراکنش، اجازه تغییر آن را به دیگران نمی‌دهد.
            $last_transaction = $wpdb->get_row( $wpdb->prepare(
                "SELECT balance FROM $this->table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1 FOR UPDATE",
                $user_id
            ) );

            $current_balance = $last_transaction ? (float) $last_transaction->balance : 0.0;

            // 4. محاسبه موجودی جدید
            $new_balance = 0.0;
            $is_credit = in_array( $args['type'], ['credit', 'cashback', 'refund'] );

            if ( $is_credit ) {
                $new_balance = $current_balance + $amount;
            } else {
                // عملیات برداشت (Debit)
                if ( $current_balance < $amount ) {
                    throw new Exception( __( 'موجودی کیف پول کافی نیست.', 'ready-wallet' ) );
                }
                $new_balance = $current_balance - $amount;
            }

            // 5. درج در دیتابیس
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'user_id'      => $user_id,
                    'amount'       => $amount,
                    'balance'      => $new_balance,
                    'currency'     => $args['currency'],
                    'type'         => $args['type'],
                    'reference_id' => $args['reference_id'],
                    'description'  => $args['description'],
                    'admin_id'     => $args['admin_id'],
                    'date'         => current_time( 'mysql' ),
                ],
                [ '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' ]
            );

            if ( false === $result ) {
                throw new Exception( __( 'خطای سیستمی در ذخیره تراکنش.', 'ready-wallet' ) . ' DB Error: ' . $wpdb->last_error );
            }

            $transaction_id = $wpdb->insert_id;

            // 6. پایان موفقیت‌آمیز تراکنش
            $wpdb->query( 'COMMIT' );

            // 7. بروزرسانی سیستم کش و متای کاربر
            $this->update_wallet_cache( $user_id, $new_balance );

            // 8. ثبت لاگ موفقیت
            $this->log( "Transaction #{$transaction_id} success. Type: {$args['type']}, Amount: {$amount}, User: {$user_id}, New Balance: {$new_balance}" );

            // 9. اجرای هوک‌ها برای ماژول‌های دیگر (پیامک، ایمیل و...)
            do_action( 'ready_wallet_transaction_complete', $transaction_id, $args );

            return $transaction_id;

        } catch ( Exception $e ) {
            // بازگرداندن تغییرات در صورت خطا
            $wpdb->query( 'ROLLBACK' );
            
            // ثبت لاگ خطا
            $this->log( "Transaction failed for User {$user_id}. Error: " . $e->getMessage(), 'error' );

            return new WP_Error( 'transaction_failed', $e->getMessage() );
        }
    }

    /**
     * دریافت موجودی کاربر با سیستم کش هوشمند
     *
     * @param int $user_id
     * @return float
     */
    public function get_wallet_balance( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );

        // 1. بررسی کش مموری (Object Cache)
        $cached_balance = wp_cache_get( "balance_{$user_id}", $this->cache_group );
        if ( false !== $cached_balance ) {
            return (float) $cached_balance;
        }

        // 2. بررسی متای کاربر (لایه دوم کش)
        $meta_balance = get_user_meta( $user_id, '_ready_wallet_balance', true );
        if ( '' !== $meta_balance ) {
            wp_cache_set( "balance_{$user_id}", $meta_balance, $this->cache_group, 3600 );
            return (float) $meta_balance;
        }

        // 3. خواندن از دیتابیس (منبع اصلی)
        $balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT balance FROM $this->table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ) );

        $balance = $balance ? (float) $balance : 0.0;

        // بروزرسانی کش‌ها
        $this->update_wallet_cache( $user_id, $balance );

        return $balance;
    }

    /**
     * دریافت لیست تراکنش‌ها با فیلترهای پیشرفته
     */
    public function get_transactions( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'user_id'    => 0,
            'limit'      => 20,
            'offset'     => 0,
            'order'      => 'DESC',
            'orderby'    => 'id',
            'type'       => '',
            'start_date' => '',
            'end_date'   => '',
            'search'     => ''
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = "WHERE 1=1";
        $query_args = [];

        // فیلتر کاربر
        if ( ! empty( $args['user_id'] ) ) {
            $where .= " AND user_id = %d";
            $query_args[] = $args['user_id'];
        }

        // فیلتر نوع تراکنش
        if ( ! empty( $args['type'] ) ) {
            $where .= " AND type = %s";
            $query_args[] = $args['type'];
        }

        // فیلتر تاریخ
        if ( ! empty( $args['start_date'] ) ) {
            $where .= " AND date >= %s";
            $query_args[] = $args['start_date'];
        }
        if ( ! empty( $args['end_date'] ) ) {
            $where .= " AND date <= %s";
            $query_args[] = $args['end_date'];
        }

        // جستجو در توضیحات یا رفنس
        if ( ! empty( $args['search'] ) ) {
            $where .= " AND (description LIKE %s OR reference_id LIKE %s)";
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $query_args[] = $like;
            $query_args[] = $like;
        }

        // ایمن‌سازی ORDER BY
        $allowed_orderby = ['id', 'date', 'amount', 'balance'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'id';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $query_args[] = $args['limit'];
        $query_args[] = $args['offset'];

        $sql = "SELECT * FROM $this->table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        
        return $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
    }

    /**
     * دریافت یک تراکنش خاص
     */
    public function get_transaction( $transaction_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $transaction_id ) );
    }

    /**
     * بازسازی موجودی کاربر (Recalculate)
     * مفید برای زمانی که دیتابیس دستی دستکاری شده یا مغایرتی وجود دارد.
     */
    public function recalculate_balance( $user_id ) {
        global $wpdb;
        
        // محاسبه مجموع اعتبارات (Credit)
        $credits = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM $this->table_name WHERE user_id = %d AND type IN ('credit', 'cashback', 'refund')",
            $user_id
        ) );

        // محاسبه مجموع برداشت‌ها (Debit)
        $debits = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM $this->table_name WHERE user_id = %d AND type IN ('debit', 'withdraw')",
            $user_id
        ) );

        $calculated_balance = (float) $credits - (float) $debits;

        // دریافت آخرین رکورد برای آپدیت موجودی نهایی آن
        $last_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $this->table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id ) );

        if ( $last_id ) {
            $wpdb->update( 
                $this->table_name, 
                ['balance' => $calculated_balance], 
                ['id' => $last_id], 
                ['%f'], 
                ['%d'] 
            );
        }

        // بروزرسانی کش
        $this->update_wallet_cache( $user_id, $calculated_balance );

        return $calculated_balance;
    }

    /**
     * بروزرسانی کش‌های مربوط به کیف پول
     */
    private function update_wallet_cache( $user_id, $balance ) {
        // بروزرسانی کش متا (Persistent)
        update_user_meta( $user_id, '_ready_wallet_balance', $balance );
        
        // بروزرسانی کش حافظه (In-Memory)
        wp_cache_set( "balance_{$user_id}", $balance, $this->cache_group, 3600 );
    }

    /**
     * ثبت لاگ در سیستم گزارشات ووکامرس
     */
    private function log( $message, $level = 'info' ) {
        if ( $this->logger ) {
            $context = array( 'source' => 'ready-wallet' );
            if ( $level === 'error' ) {
                $this->logger->error( $message, $context );
            } else {
                $this->logger->info( $message, $context );
            }
        }
    }
}