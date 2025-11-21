<?php
/**
 * ReadyWallet Form Handler
 *
 * مدیریت پردازش فرم‌های سمت کاربر (Frontend Forms) شامل:
 * 1. افزایش موجودی (Deposit): افزودن محصول به سبد خرید
 * 2. برداشت وجه (Withdraw): ثبت درخواست کسر وجه
 * 3. انتقال وجه (Transfer): جابجایی اعتبار بین کاربران
 *
 * ویژگی‌های پیشرفته:
 * - اعتبارسنجی امنیتی دقیق (Nonce & Capabilities)
 * - بررسی محدودیت‌های مالی (Min/Max) از تنظیمات
 * - پشتیبانی از اعشار در مبالغ
 * - تریگر کردن هوک‌های پیامک و نوتیفیکیشن
 *
 * @package     ReadyWallet/Classes
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Form_Handler {

    /**
     * سازنده کلاس
     * گوش دادن به درخواست‌های POST قبل از بارگذاری کامل صفحه
     */
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'process_deposit_form' ) );
        add_action( 'template_redirect', array( $this, 'process_withdraw_form' ) );
        add_action( 'template_redirect', array( $this, 'process_transfer_form' ) );
    }

    /**
     * 1. پردازش فرم افزایش موجودی (Deposit)
     * این متد محصول "شارژ کیف پول" را با قیمت دلخواه کاربر به سبد خرید اضافه می‌کند.
     */
    public function process_deposit_form() {
        if ( isset( $_POST['woo_wallet_deposit'] ) && isset( $_POST['woo_wallet_deposit_nonce'] ) ) {
            
            // الف: بررسی امنیتی
            if ( ! wp_verify_nonce( $_POST['woo_wallet_deposit_nonce'], 'woo_wallet_deposit' ) ) {
                wc_add_notice( __( 'نشست شما منقضی شده است. لطفاً صفحه را رفرش کنید.', 'ready-wallet' ), 'error' );
                return;
            }

            // ب: دریافت و تمیزسازی مبلغ
            $amount_str = isset( $_POST['woo_wallet_deposit_amount'] ) ? wc_clean( $_POST['woo_wallet_deposit_amount'] ) : 0;
            // حذف کاما یا فرمت‌های پولی برای تبدیل به عدد خالص
            $amount = floatval( str_replace( ',', '', $amount_str ) );

            if ( $amount <= 0 ) {
                wc_add_notice( __( 'مبلغ وارد شده نامعتبر است.', 'ready-wallet' ), 'error' );
                return;
            }

            // ج: بررسی محدودیت‌های تعریف شده در تنظیمات
            $min_deposit = (float) get_option( 'ready_wallet_min_deposit', 1000 );
            $max_deposit = (float) get_option( 'ready_wallet_max_deposit', 0 );

            if ( $amount < $min_deposit ) {
                wc_add_notice( sprintf( __( 'حداقل مبلغ شارژ %s است.', 'ready-wallet' ), wc_price( $min_deposit ) ), 'error' );
                return;
            }

            if ( $max_deposit > 0 && $amount > $max_deposit ) {
                wc_add_notice( sprintf( __( 'حداکثر مبلغ مجاز برای شارژ %s است.', 'ready-wallet' ), wc_price( $max_deposit ) ), 'error' );
                return;
            }

            // د: عملیات افزودن به سبد خرید
            // طبق استاندارد کیف پول، سبد خرید باید فقط شامل محصول شارژ باشد
            WC()->cart->empty_cart(); 

            $deposit_product_id = get_option( 'ready_wallet_deposit_product_id' );
            
            // بررسی وجود محصول شارژ
            if ( ! $deposit_product_id || ! wc_get_product( $deposit_product_id ) ) {
                wc_add_notice( __( 'خطای پیکربندی: محصول شارژ کیف پول یافت نشد. لطفاً با پشتیبانی تماس بگیرید.', 'ready-wallet' ), 'error' );
                return;
            }

            // افزودن محصول به سبد خرید و ذخیره مبلغ در متای آیتم
            // (قیمت واقعی در هوک woocommerce_before_calculate_totals ست می‌شود که باید در فایل اصلی باشد)
            WC()->cart->add_to_cart( $deposit_product_id, 1, 0, array(), array( 'ready_wallet_deposit_amount' => $amount ) );

            // هدایت مستقیم به صفحه تسویه حساب برای پرداخت سریع
            wp_redirect( wc_get_checkout_url() );
            exit;
        }
    }

    /**
     * 2. پردازش فرم درخواست برداشت (Withdraw)
     * ثبت درخواست برداشت وجه از کیف پول توسط کاربر
     */
    public function process_withdraw_form() {
        if ( isset( $_POST['woo_wallet_withdraw'] ) && isset( $_POST['woo_wallet_withdraw_nonce'] ) ) {

            if ( ! wp_verify_nonce( $_POST['woo_wallet_withdraw_nonce'], 'woo_wallet_withdraw' ) ) {
                wc_add_notice( __( 'خطای امنیتی. لطفاً دوباره تلاش کنید.', 'ready-wallet' ), 'error' );
                return;
            }

            if ( ! is_user_logged_in() ) return;

            $user_id = get_current_user_id();
            $amount_str = isset( $_POST['woo_wallet_withdraw_amount'] ) ? wc_clean( $_POST['woo_wallet_withdraw_amount'] ) : 0;
            $amount  = floatval( str_replace( ',', '', $amount_str ) );

            // حداقل برداشت (فعلاً ثابت، می‌تواند به تنظیمات اضافه شود)
            $min_withdraw = 50000; 
            if ( $amount < $min_withdraw ) {
                wc_add_notice( sprintf( __( 'حداقل مبلغ قابل برداشت %s است.', 'ready-wallet' ), wc_price( $min_withdraw ) ), 'error' );
                return;
            }

            // بررسی موجودی کاربر
            $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );

            if ( $balance < $amount ) {
                wc_add_notice( __( 'موجودی کیف پول شما برای این برداشت کافی نیست.', 'ready-wallet' ), 'error' );
                return;
            }

            // ثبت تراکنش برداشت (Debit)
            // در این مرحله موجودی کسر می‌شود تا کاربر نتواند دوباره خرج کند.
            $result = Ready_Wallet()->db->add_transaction([
                'user_id'      => $user_id,
                'amount'       => $amount,
                'type'         => 'debit', // نوع تراکنش
                'description'  => __( 'درخواست برداشت وجه (در انتظار واریز)', 'ready-wallet' )
            ]);

            if ( ! is_wp_error( $result ) ) {
                wc_add_notice( __( 'درخواست برداشت شما با موفقیت ثبت شد. مبلغ از حساب کسر گردید و پس از بررسی واریز خواهد شد.', 'ready-wallet' ), 'success' );
                
                // اجرای هوک برای اطلاع‌رسانی به مدیر (ایمیل/پیامک)
                do_action( 'ready_wallet_withdraw_request_submitted', $user_id, $amount, $result );
            } else {
                wc_add_notice( $result->get_error_message(), 'error' );
            }
        }
    }

    /**
     * 3. پردازش فرم انتقال وجه (Transfer)
     * عملیات حساس انتقال اعتبار بین دو کاربر
     */
    public function process_transfer_form() {
        if ( isset( $_POST['ready_wallet_transfer'] ) && isset( $_POST['ready_wallet_transfer_nonce'] ) ) {

            // الف: بررسی امنیتی
            if ( ! wp_verify_nonce( $_POST['ready_wallet_transfer_nonce'], 'ready_wallet_transfer' ) ) {
                wc_add_notice( __( 'نشست شما منقضی شده است.', 'ready-wallet' ), 'error' );
                return;
            }

            $sender_id = get_current_user_id();
            $recipient_input = sanitize_text_field( $_POST['rw_transfer_recipient'] );
            $amount_str = isset( $_POST['rw_transfer_amount'] ) ? wc_clean( $_POST['rw_transfer_amount'] ) : 0;
            $amount = floatval( str_replace( ',', '', $amount_str ) );
            $note = sanitize_textarea_field( $_POST['rw_transfer_note'] );

            // ب: اعتبارسنجی‌های اولیه
            if ( empty( $recipient_input ) ) {
                wc_add_notice( __( 'لطفاً ایمیل یا نام کاربری گیرنده را وارد کنید.', 'ready-wallet' ), 'error' );
                return;
            }

            if ( $amount <= 0 ) {
                wc_add_notice( __( 'مبلغ انتقال باید معتبر و بیشتر از صفر باشد.', 'ready-wallet' ), 'error' );
                return;
            }

            // ج: پیدا کردن کاربر گیرنده
            $recipient = get_user_by( 'email', $recipient_input );
            if ( ! $recipient ) {
                $recipient = get_user_by( 'login', $recipient_input );
            }

            if ( ! $recipient ) {
                wc_add_notice( __( 'کاربری با مشخصات وارد شده یافت نشد.', 'ready-wallet' ), 'error' );
                return;
            }

            $recipient_id = $recipient->ID;

            // جلوگیری از انتقال به خود
            if ( $sender_id == $recipient_id ) {
                wc_add_notice( __( 'شما نمی‌توانید به خودتان وجه انتقال دهید.', 'ready-wallet' ), 'error' );
                return;
            }

            // د: بررسی موجودی فرستنده
            $sender_balance = Ready_Wallet()->db->get_wallet_balance( $sender_id );

            if ( $sender_balance < $amount ) {
                wc_add_notice( __( 'موجودی کیف پول شما برای انجام این تراکنش کافی نیست.', 'ready-wallet' ), 'error' );
                return;
            }

            // ه: انجام تراکنش دو مرحله‌ای (Double-Entry)
            try {
                
                // 1. کسر از حساب فرستنده (Debit)
                $debit_result = Ready_Wallet()->db->add_transaction([
                    'user_id'      => $sender_id,
                    'amount'       => $amount,
                    'type'         => 'debit', 
                    'description'  => sprintf( __( 'انتقال وجه به %s (%s). یادداشت: %s', 'ready-wallet' ), $recipient->display_name, $recipient->user_email, $note )
                ]);

                if ( is_wp_error( $debit_result ) ) {
                    throw new Exception( $debit_result->get_error_message() );
                }

                // 2. واریز به حساب گیرنده (Credit)
                $credit_result = Ready_Wallet()->db->add_transaction([
                    'user_id'      => $recipient_id,
                    'amount'       => $amount,
                    'type'         => 'credit', 
                    'description'  => sprintf( __( 'دریافت وجه از %s. یادداشت: %s', 'ready-wallet' ), wp_get_current_user()->display_name, $note )
                ]);

                if ( is_wp_error( $credit_result ) ) {
                    // هشدار بحرانی: پول کسر شده اما واریز نشده.
                    // در یک سیستم بانکی واقعی، اینجا باید Rollback انجام شود.
                    // ما خطا را لاگ می‌کنیم تا ادمین بررسی کند.
                    error_log( "ReadyWallet Critical Transfer Error: Money deducted from User $sender_id but failed to credit User $recipient_id. Transaction ID: $debit_result" );
                    
                    throw new Exception( __( 'خطایی در واریز وجه به گیرنده رخ داد. لطفاً با کد رهگیری تراکنش با پشتیبانی تماس بگیرید.', 'ready-wallet' ) );
                }

                wc_add_notice( sprintf( __( 'مبلغ %s با موفقیت به %s منتقل شد.', 'ready-wallet' ), wc_price( $amount ), $recipient->display_name ), 'success' );

                // اجرای هوک برای ارسال پیامک به گیرنده و فرستنده
                // کلاس SMS Manager به این هوک گوش می‌دهد
                do_action( 'ready_wallet_transfer_completed', $sender_id, $recipient_id, $amount, $debit_result );

            } catch ( Exception $e ) {
                wc_add_notice( $e->getMessage(), 'error' );
            }
        }
    }
}

return new Ready_Wallet_Form_Handler();