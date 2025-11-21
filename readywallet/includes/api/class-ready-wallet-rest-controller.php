<?php
/**
 * ReadyWallet Pro REST API Controller
 *
 * کنترلر مدیریت درخواست‌های API برای اتصال اپلیکیشن موبایل و Headless.
 * Namespace: readywallet/v2
 *
 * ویژگی‌های خارق‌العاده:
 * 1. استانداردهای کامل WP REST API (Schema & Sanitization).
 * 2. اندپوینت انتقال وجه (P2P Transfer) برای اپلیکیشن.
 * 3. دریافت موجودی و تراکنش‌ها با فیلترهای پیشرفته.
 * 4. مدیریت خطاهای استاندارد (Error Handling).
 *
 * @package     ReadyWallet/API
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Ready_Wallet_REST_Controller' ) ) :

class Ready_Wallet_REST_Controller extends WP_REST_Controller {

    /**
     * فضای نام API
     */
    protected $namespace = 'readywallet/v2';

    /**
     * مسیر پایه
     */
    protected $rest_base = 'wallet';

    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * ثبت مسیرهای API
     */
    public function register_routes() {
        
        // 1. دریافت موجودی (GET /balance)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/balance', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_balance' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(),
            ),
            'schema' => array( $this, 'get_balance_schema' ),
        ) );

        // 2. دریافت لیست تراکنش‌ها (GET /transactions)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/transactions', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_transactions' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            'schema' => array( $this, 'get_transaction_schema' ),
        ) );

        // 3. انتقال وجه (POST /transfer)
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/transfer', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'process_transfer' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'amount' => array(
                        'required'          => true,
                        'type'              => 'number',
                        'description'       => __( 'مبلغ انتقال', 'ready-wallet' ),
                        'validate_callback' => function($param) { return is_numeric($param) && $param > 0; }
                    ),
                    'recipient' => array(
                        'required'    => true,
                        'type'        => 'string',
                        'description' => __( 'ایمیل، نام کاربری یا شماره موبایل گیرنده', 'ready-wallet' ),
                    ),
                    'note' => array(
                        'required'    => false,
                        'type'        => 'string',
                        'description' => __( 'یادداشت تراکنش', 'ready-wallet' ),
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ),
                ),
            ),
        ) );
    }

    /**
     * بررسی سطح دسترسی کاربر
     */
    public function permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 
                'rest_forbidden', 
                __( 'برای دسترسی به کیف پول باید وارد حساب کاربری شوید.', 'ready-wallet' ), 
                array( 'status' => 401 ) 
            );
        }
        return true;
    }

    /**
     * 1. اندپوینت دریافت موجودی
     */
    public function get_balance( $request ) {
        $user_id = get_current_user_id();
        $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
        
        $response = array(
            'user_id'          => $user_id,
            'balance'          => $balance,
            'balance_formatted'=> wc_price( $balance ),
            'currency'         => get_woocommerce_currency(),
            'currency_symbol'  => get_woocommerce_currency_symbol(),
        );

        return rest_ensure_response( $response );
    }

    /**
     * 2. اندپوینت دریافت تراکنش‌ها
     */
    public function get_transactions( $request ) {
        $user_id = get_current_user_id();
        
        // پارامترهای ورودی
        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $type     = $request->get_param( 'type' ); // credit, debit
        $order    = $request->get_param( 'order' );

        $args = array(
            'user_id' => $user_id,
            'limit'   => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'order'   => $order,
            'type'    => $type
        );

        $transactions = Ready_Wallet()->db->get_transactions( $args );
        $data = array();

        foreach ( $transactions as $transaction ) {
            $data[] = $this->prepare_transaction_for_response( $transaction );
        }

        // محاسبه تعداد کل برای هدر
        // نکته: برای پرفورمنس در اپلیکیشن، تعداد کل را می‌توان در بادی هم فرستاد یا در هدر X-WP-Total
        
        return rest_ensure_response( $data );
    }

    /**
     * 3. اندپوینت انتقال وجه (API Transfer)
     */
    public function process_transfer( $request ) {
        $sender_id = get_current_user_id();
        $amount    = $request->get_param( 'amount' );
        $recipient_input = $request->get_param( 'recipient' );
        $note      = $request->get_param( 'note' );

        // الف: یافتن گیرنده
        $recipient = get_user_by( 'email', $recipient_input );
        if ( ! $recipient ) {
            $recipient = get_user_by( 'login', $recipient_input );
        }
        // پشتیبانی از جستجو با شماره موبایل (اگر دیجیتس یا متای موبایل دارید)
        if ( ! $recipient ) {
            $users = get_users([
                'meta_key' => 'billing_phone', 
                'meta_value' => $recipient_input,
                'number' => 1
            ]);
            if ( ! empty($users) ) $recipient = $users[0];
        }

        if ( ! $recipient ) {
            return new WP_Error( 'invalid_recipient', __( 'کاربر گیرنده یافت نشد.', 'ready-wallet' ), array( 'status' => 404 ) );
        }

        if ( $sender_id == $recipient->ID ) {
            return new WP_Error( 'self_transfer', __( 'انتقال وجه به خود امکان‌پذیر نیست.', 'ready-wallet' ), array( 'status' => 400 ) );
        }

        // ب: بررسی موجودی
        $balance = Ready_Wallet()->db->get_wallet_balance( $sender_id );
        if ( $balance < $amount ) {
            return new WP_Error( 'insufficient_funds', __( 'موجودی کافی نیست.', 'ready-wallet' ), array( 'status' => 400 ) );
        }

        // ج: انجام تراکنش
        try {
            // کسر از فرستنده
            $debit_id = Ready_Wallet()->db->add_transaction([
                'user_id'      => $sender_id,
                'amount'       => $amount,
                'type'         => 'debit',
                'description'  => sprintf( __( 'انتقال API به %s. یادداشت: %s', 'ready-wallet' ), $recipient->display_name, $note )
            ]);

            if ( is_wp_error( $debit_id ) ) throw new Exception( $debit_id->get_error_message() );

            // واریز به گیرنده
            $credit_id = Ready_Wallet()->db->add_transaction([
                'user_id'      => $recipient->ID,
                'amount'       => $amount,
                'type'         => 'credit',
                'description'  => sprintf( __( 'دریافت API از %s. یادداشت: %s', 'ready-wallet' ), wp_get_current_user()->display_name, $note )
            ]);

            if ( is_wp_error( $credit_id ) ) throw new Exception( $credit_id->get_error_message() );

            // تریگر کردن هوک پیامک
            do_action( 'ready_wallet_transfer_completed', $sender_id, $recipient->ID, $amount, $debit_id );

            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'انتقال با موفقیت انجام شد.', 'ready-wallet' ),
                'transaction_id' => $debit_id,
                'new_balance' => Ready_Wallet()->db->get_wallet_balance( $sender_id )
            ) );

        } catch ( Exception $e ) {
            return new WP_Error( 'transfer_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * آماده‌سازی داده‌های تراکنش برای خروجی JSON
     */
    protected function prepare_transaction_for_response( $transaction ) {
        return array(
            'id'           => (int) $transaction->id,
            'type'         => $transaction->type,
            'amount'       => (float) $transaction->amount,
            'amount_fmt'   => wc_price( $transaction->amount ),
            'balance'      => (float) $transaction->balance,
            'description'  => $transaction->description,
            'date'         => $transaction->date,
            'date_gmt'     => get_gmt_from_date( $transaction->date ),
        );
    }

    /**
     * پارامترهای استاندارد کالکشن
     */
    public function get_collection_params() {
        return array(
            'page' => array(
                'description'       => 'شماره صفحه',
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description'       => 'تعداد در هر صفحه',
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
            'type' => array(
                'description'       => 'فیلتر نوع تراکنش',
                'type'              => 'string',
                'enum'              => array( 'credit', 'debit', 'cashback' ),
            ),
        );
    }

    /**
     * اسکیمای داده‌ها (برای Swagger)
     */
    public function get_balance_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'wallet_balance',
            'type'       => 'object',
            'properties' => array(
                'user_id' => array( 'type' => 'integer' ),
                'balance' => array( 'type' => 'number' ),
                'formatted' => array( 'type' => 'string' ),
            ),
        );
    }

    public function get_transaction_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'wallet_transaction',
            'type'       => 'object',
            'properties' => array(
                'id' => array( 'type' => 'integer' ),
                'type' => array( 'type' => 'string' ),
                'amount' => array( 'type' => 'number' ),
                'description' => array( 'type' => 'string' ),
                'date' => array( 'type' => 'string', 'format' => 'date-time' ),
            ),
        );
    }
}

endif;