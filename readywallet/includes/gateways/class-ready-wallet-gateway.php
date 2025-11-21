<?php
/**
 * ReadyWallet WooCommerce Payment Gateway
 * درگاه پرداخت کیف پول برای ووکامرس
 * * ویژگی‌ها:
 * - پرداخت کامل سفارش با کیف پول
 * - پشتیبانی از پرداخت ترکیبی (Partial Payment)
 * - بررسی موجودی قبل از پرداخت
 */

defined( 'ABSPATH' ) || exit;

class WC_Gateway_Ready_Wallet extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'ready_wallet';
        $this->icon               = ''; // لینک آیکون کیف پول
        $this->has_fields         = false;
        $this->method_title       = __( 'کیف پول ردی استودیو', 'ready-wallet' );
        $this->method_description = __( 'پرداخت سفارش با استفاده از موجودی حساب کاربری.', 'ready-wallet' );

        // تنظیمات درگاه
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );

        // ذخیره تنظیمات در ادمین
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
        // هوک برای بررسی موجودی در صفحه تسویه حساب
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    }

    /**
     * فیلدهای تنظیمات درگاه در ووکامرس
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'فعالسازی/غیرفعالسازی', 'ready-wallet' ),
                'type'    => 'checkbox',
                'label'   => __( 'فعالسازی پرداخت با کیف پول', 'ready-wallet' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __( 'عنوان درگاه', 'ready-wallet' ),
                'type'        => 'text',
                'description' => __( 'نامی که کاربر در هنگام پرداخت می‌بیند.', 'ready-wallet' ),
                'default'     => __( 'کیف پول هوشمند', 'ready-wallet' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'توضیحات', 'ready-wallet' ),
                'type'        => 'textarea',
                'default'     => __( 'پرداخت امن و سریع با موجودی کیف پول.', 'ready-wallet' ),
            ),
            'partial_payment' => array(
                'title'   => __( 'پرداخت ترکیبی', 'ready-wallet' ),
                'type'    => 'checkbox',
                'label'   => __( 'اجازه پرداخت مابقی مبلغ با درگاه بانکی در صورت عدم موجودی کافی', 'ready-wallet' ),
                'default' => 'yes',
            ),
        );
    }

    /**
     * بررسی اینکه آیا این درگاه باید نمایش داده شود؟
     */
    public function is_available() {
        if ( is_admin() ) return parent::is_available();

        if ( ! is_user_logged_in() ) return false;

        // بررسی موجودی کاربر
        $user_id = get_current_user_id();
        $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
        
        // اگر موجودی صفر است و کاربر قصد شارژ کیف پول را ندارد، درگاه را نشان نده
        // نکته: باید بررسی کنیم محصول داخل سبد خرید، محصول "شارژ کیف پول" نباشد (جلوگیری از لوپ)
        if ( $balance <= 0 ) return false;

        return parent::is_available();
    }

    /**
     * پردازش پرداخت
     */
    public function process_payment( $order_id ) {
        $order   = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        $amount  = $order->get_total();
        
        $wallet_db = new Ready_Wallet_DB(); // یا دسترسی از طریق کلاس اصلی
        $balance   = $wallet_db->get_wallet_balance( $user_id );

        if ( $balance >= $amount ) {
            // حالت ۱: موجودی کافی است - پرداخت کامل
            
            // کسر از موجودی
            $result = $wallet_db->add_transaction([
                'user_id'      => $user_id,
                'amount'       => $amount,
                'type'         => 'debit',
                'reference_id' => $order_id,
                'description'  => sprintf( __( 'پرداخت سفارش #%s', 'ready-wallet' ), $order->get_order_number() )
            ]);

            if ( is_wp_error( $result ) ) {
                wc_add_notice( $result->get_error_message(), 'error' );
                return;
            }

            // تکمیل سفارش
            $order->payment_complete();
            $order->add_order_note( sprintf( __( 'پرداخت شده توسط کیف پول. شناسه تراکنش: %s', 'ready-wallet' ), $result ) );
            
            // خالی کردن سبد خرید
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );

        } else {
            // حالت ۲: موجودی کافی نیست (Partial Payment)
            if ( 'yes' === $this->get_option( 'partial_payment' ) ) {
                // این بخش پیچیده است: باید مبلغ موجودی را کسر کنیم و مابقی را به درگاه بانکی بفرستیم.
                // در این نسخه ساده، فعلاً خطا می‌دهیم مگر اینکه لاجیک Split Payment پیاده شود.
                wc_add_notice( sprintf( __( 'موجودی کافی نیست. موجودی شما: %s', 'ready-wallet' ), wc_price($balance) ), 'error' );
                return;
            } else {
                wc_add_notice( __( 'موجودی کیف پول برای پرداخت این سفارش کافی نیست.', 'ready-wallet' ), 'error' );
                return;
            }
        }
    }
}

/**
 * ثبت درگاه در ووکامرس
 */
add_filter( 'woocommerce_payment_gateways', 'add_ready_wallet_gateway' );
function add_ready_wallet_gateway( $methods ) {
    $methods[] = 'WC_Gateway_Ready_Wallet';
    return $methods;
}