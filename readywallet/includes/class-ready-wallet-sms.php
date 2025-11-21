<?php
/**
 * ReadyWallet SMS Manager
 * مدیریت ارسال پیامک‌های تراکنش با درگاه MessageWay (راه پیام)
 * نسخه: داینامیک (متصل به تنظیمات)
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_SMS_Manager {

    private $api_key;
    private $is_enabled;

    public function __construct() {
        // خواندن تنظیمات از دیتابیس
        $this->api_key    = get_option( 'ready_wallet_sms_api_key' );
        $this->is_enabled = get_option( 'ready_wallet_sms_enable', 'yes' );

        // اگر افزونه فعال بود، هوک را اجرا کن
        if ( 'yes' === $this->is_enabled && ! empty( $this->api_key ) ) {
            add_action('woo_wallet_transaction_process_complete', array($this, 'process_sms_trigger'), 10, 2);
        }
    }

    /**
     * پردازش منطق ارسال پیامک
     */
    public function process_sms_trigger($transaction_id, $user_id) {
        
        $transaction = get_wallet_transaction($transaction_id);
        if (!$transaction) return;

        $amount  = $transaction->amount;
        $type    = $transaction->type; // credit | debit
        $balance = woo_wallet()->wallet->get_wallet_balance($user_id);
        
        // دریافت شماره موبایل
        $phone = get_user_meta($user_id, 'billing_phone', true);
        if (empty($phone)) $phone = get_user_meta($user_id, 'mobile', true);

        if (empty($phone)) return;

        // پارامترهای ارسالی به پترن
        $params = [
            number_format($amount), // %param1%
            number_format($balance), // %param2%
            (string)$transaction_id  // %param3%
        ];

        // دریافت شناسه قالب از تنظیمات
        $tpl_charge = get_option('ready_wallet_sms_tpl_charge');
        $tpl_debit  = get_option('ready_wallet_sms_tpl_debit');

        if ($type == 'credit' && !empty($tpl_charge)) {
            $this->send_messageway_sms($phone, $tpl_charge, $params);
        } elseif ($type == 'debit' && !empty($tpl_debit)) {
            $this->send_messageway_sms($phone, $tpl_debit, $params);
        }
    }

    /**
     * ارسال درخواست به API MessageWay
     */
    private function send_messageway_sms($mobile, $templateID, $params) {
        
        $url = 'https://api.msgway.com/send'; 

        $data = [
            'method'     => 'sms',
            'mobile'     => $mobile,
            'templateID' => (int)$templateID,
            'params'     => $params,
        ];

        $headers = [
            'apiKey: ' . $this->api_key,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('ReadyWallet SMS Error: ' . curl_error($ch));
        }
        curl_close($ch);
    }
}