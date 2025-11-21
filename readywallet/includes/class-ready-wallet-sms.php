<?php
/**
 * ReadyWallet Pro SMS Manager
 *
 * سیستم مدیریت اعلان‌های پیامکی (SMS Notifications).
 * متصل به درگاه MessageWay (راه پیام).
 *
 * ویژگی‌های خارق‌العاده:
 * 1. نرمال‌سازی خودکار شماره موبایل (Phone Sanitization).
 * 2. مدیریت رویدادهای چندگانه (واریز، برداشت، انتقال، درخواست).
 * 3. استفاده از HTTP API استاندارد وردپرس (wp_remote_post).
 * 4. لاگ‌گیری دقیق درخواست و پاسخ برای عیب‌یابی.
 *
 * @package     ReadyWallet/Classes
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_SMS_Manager {

    private $api_key;
    private $is_enabled;
    private $api_url = 'https://api.msgway.com/send'; // آدرس استاندارد MessageWay

    /**
     * سازنده کلاس
     */
    public function __construct() {
        $this->api_key    = get_option( 'ready_wallet_sms_api_key' );
        $this->is_enabled = get_option( 'ready_wallet_sms_enable', 'yes' );

        if ( 'yes' === $this->is_enabled && ! empty( $this->api_key ) ) {
            
            // 1. پیامک تراکنش‌های عمومی (شارژ، خرید، کش‌بک)
            // هوک تعریف شده در Ready_Wallet_DB
            add_action( 'ready_wallet_transaction_complete', array( $this, 'process_transaction_sms' ), 10, 2 );

            // 2. پیامک درخواست برداشت (مخصوص مدیر یا کاربر)
            // هوک تعریف شده در Form Handler
            add_action( 'ready_wallet_withdraw_request_submitted', array( $this, 'process_withdraw_sms' ), 10, 3 );

            // 3. پیامک انتقال وجه (P2P)
            // هوک تعریف شده در Form Handler
            add_action( 'ready_wallet_transfer_completed', array( $this, 'process_transfer_sms' ), 10, 4 );
        }
    }

    /**
     * پردازش پیامک تراکنش‌های عمومی
     */
    public function process_transaction_sms( $transaction_id, $args ) {
        $user_id = $args['user_id'];
        $type    = $args['type'];
        $amount  = $args['amount'];

        // جلوگیری از ارسال برای تراکنش‌های سیستمی که نیاز به پیامک ندارند
        if ( in_array( $type, ['transfer', 'withdraw_request'] ) ) {
            return; 
        }

        $phone = $this->get_user_mobile( $user_id );
        if ( ! $phone ) return;

        $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
        
        // آماده‌سازی پارامترها
        $params = [
            wc_price($amount),      // param1: مبلغ تراکنش
            wc_price($balance),     // param2: موجودی فعلی
            (string)$transaction_id // param3: شناسه تراکنش
        ];

        // تعیین پترن بر اساس نوع
        $template_id = 0;

        if ( in_array( $type, ['credit', 'cashback', 'refund'] ) ) {
            $template_id = get_option( 'ready_wallet_sms_tpl_charge' );
        } elseif ( $type == 'debit' ) {
            $template_id = get_option( 'ready_wallet_sms_tpl_debit' );
        }

        if ( $template_id ) {
            $this->send_pattern( $phone, $template_id, $params );
        }
    }

    /**
     * پردازش پیامک درخواست برداشت
     */
    public function process_withdraw_sms( $user_id, $amount, $transaction_id ) {
        // فعلاً از پترن Debit استفاده می‌کنیم یا می‌توان پترن اختصاصی در تنظیمات اضافه کرد
        // سناریو: اطلاع به کاربر که درخواست ثبت شد
        $phone = $this->get_user_mobile( $user_id );
        if ( ! $phone ) return;

        $template_id = get_option( 'ready_wallet_sms_tpl_debit' ); // یا پترن اختصاصی "ثبت درخواست"
        if ( $template_id ) {
            $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
            $params = [
                wc_price($amount),
                wc_price($balance),
                (string)$transaction_id
            ];
            $this->send_pattern( $phone, $template_id, $params );
        }
    }

    /**
     * پردازش پیامک انتقال وجه (برای گیرنده و فرستنده)
     */
    public function process_transfer_sms( $sender_id, $recipient_id, $amount, $transaction_id ) {
        
        // 1. ارسال به گیرنده (Credit)
        $recipient_phone = $this->get_user_mobile( $recipient_id );
        $charge_tpl = get_option( 'ready_wallet_sms_tpl_charge' );
        
        if ( $recipient_phone && $charge_tpl ) {
            $recipient_balance = Ready_Wallet()->db->get_wallet_balance( $recipient_id );
            $sender_user = get_userdata( $sender_id );
            $sender_name = $sender_user ? $sender_user->display_name : __('کاربر', 'ready-wallet');

            // پارامترها را می‌توان غنی‌تر کرد اگر پترن پشتیبانی کند
            $params = [
                wc_price($amount),
                wc_price($recipient_balance),
                (string)$transaction_id
            ];
            $this->send_pattern( $recipient_phone, $charge_tpl, $params );
        }

        // 2. ارسال به فرستنده (Debit) - اختیاری، چون فرم هندلر معمولا نوتیفیکیشن Debit را تریگر می‌کند
        // اما چون در فرم هندلر نوع تراکنش 'debit' ثبت شده، تابع process_transaction_sms خودکار آن را هندل می‌کند.
        // پس اینجا نیازی به کد اضافه نیست.
    }

    /**
     * ارسال درخواست به MessageWay
     *
     * @param string $mobile شماره موبایل
     * @param int $templateID شناسه پترن
     * @param array $params پارامترهای پترن
     * @return bool
     */
    private function send_pattern( $mobile, $templateID, $params ) {
        
        // ساخت بدنه درخواست طبق مستندات MessageWay
        $body = array(
            'method'     => 'sms', // متد ارسال
            'mobile'     => $mobile,
            'templateID' => (int) $templateID,
            'params'     => array_values( $params ), // تبدیل به آرایه ایندکس‌دار
        );

        // تنظیمات درخواست
        $args = array(
            'body'        => json_encode( $body ),
            'headers'     => array(
                'apiKey'       => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 15,
            'blocking'    => true,
            'data_format' => 'body',
        );

        // ارسال با استفاده از توابع استاندارد وردپرس
        $response = wp_remote_post( $this->api_url, $args );

        // لاگ‌گیری در صورت فعال بودن دیباگ
        if ( 'yes' === get_option( 'ready_wallet_debug' ) ) {
            $this->log( "SMS Request to {$mobile} (TPL: {$templateID}): " . json_encode($body) );
            
            if ( is_wp_error( $response ) ) {
                $this->log( "SMS Failed: " . $response->get_error_message(), 'error' );
            } else {
                $this->log( "SMS Response: " . wp_remote_retrieve_body( $response ) );
            }
        }

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        return ( $http_code >= 200 && $http_code < 300 );
    }

    /**
     * دریافت و نرمال‌سازی شماره موبایل کاربر
     */
    private function get_user_mobile( $user_id ) {
        // اولویت اول: فیلد استاندارد ووکامرس
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        
        // اولویت دوم: دیجیتس یا پلاگین‌های دیگر
        if ( empty( $phone ) ) {
            $phone = get_user_meta( $user_id, 'mobile', true );
        }
        if ( empty( $phone ) ) {
            $phone = get_user_meta( $user_id, 'digits_phone', true ); // پلاگین Digits
        }

        if ( empty( $phone ) ) return false;

        // نرمال‌سازی برای درگاه‌های ایرانی (تبدیل به فرمت 09xxxxxxxxx)
        // 1. حذف کد کشور (+98 یا 0098)
        $phone = preg_replace( '/^(\+98|0098|98)/', '0', $phone );
        
        // 2. اگر با 9 شروع می‌شود، یک 0 اضافه کن
        if ( substr( $phone, 0, 1 ) === '9' ) {
            $phone = '0' . $phone;
        }

        // 3. تبدیل اعداد فارسی به انگلیسی
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace( $persian, $english, $phone );

        return $phone;
    }

    /**
     * لاگر اختصاصی
     */
    private function log( $message, $level = 'info' ) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'ready-wallet-sms' );
        if ( $level === 'error' ) {
            $logger->error( $message, $context );
        } else {
            $logger->info( $message, $context );
        }
    }
}