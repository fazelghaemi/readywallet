<?php
/**
 * ReadyWallet Pro Gateway
 *
 * درگاه پرداخت اختصاصی کیف پول برای ووکامرس.
 *
 * ویژگی‌های برجسته (Extraordinary Features):
 * 1. پشتیبانی کامل از بازگشت وجه (Refund): بازگشت خودکار مبلغ به کیف پول هنگام استرداد سفارش.
 * 2. اعتبارسنجی دقیق: بررسی موجودی و واحد پولی قبل از پردازش.
 * 3. آیکون سفارشی: امکان تغییر آیکون درگاه از تنظیمات.
 * 4. لاگ‌گیری دقیق: ثبت تمام مراحل پرداخت و خطاها برای عیب‌یابی.
 *
 * @package     ReadyWallet/Gateways
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

// اطمینان از وجود کلاس پایه پرداخت ووکامرس
if ( class_exists( 'WC_Payment_Gateway' ) ) :

class WC_Gateway_Ready_Wallet extends WC_Payment_Gateway {

    /**
     * سازنده کلاس
     */
    public function __construct() {
        $this->id                 = 'ready_wallet';
        $this->icon               = $this->get_option( 'icon' ); // آیکون از تنظیمات خوانده می‌شود
        $this->has_fields         = false;
        $this->method_title       = __( 'کیف پول ردی', 'ready-wallet' );
        $this->method_description = __( 'پرداخت سفارشات با استفاده از اعتبار کیف پول کاربران.', 'ready-wallet' );

        // قابلیت‌های پشتیبانی شده توسط درگاه
        $this->supports = array(
            'products',
            'refunds', // فعال‌سازی دکمه استرداد وجه در پنل سفارش
        );

        // راه‌اندازی تنظیمات
        $this->init_form_fields();
        $this->init_settings();

        // مقادیر تنظیمات
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled' );

        // ذخیره تنظیمات در پنل ادمین
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * فیلدهای تنظیمات درگاه
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'فعالسازی/غیرفعالسازی', 'ready-wallet' ),
                'type'    => 'checkbox',
                'label'   => __( 'فعال کردن پرداخت با کیف پول', 'ready-wallet' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __( 'عنوان درگاه', 'ready-wallet' ),
                'type'        => 'text',
                'description' => __( 'نامی که کاربر در صفحه تسویه حساب می‌بیند.', 'ready-wallet' ),
                'default'     => __( 'کیف پول هوشمند', 'ready-wallet' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'توضیحات', 'ready-wallet' ),
                'type'        => 'textarea',
                'description' => __( 'توضیحاتی که زیر عنوان درگاه نمایش داده می‌شود.', 'ready-wallet' ),
                'default'     => __( 'پرداخت آنی و امن با استفاده از موجودی حساب کاربری.', 'ready-wallet' ),
                'desc_tip'    => true,
            ),
            'icon' => array(
                'title'       => __( 'آدرس آیکون', 'ready-wallet' ),
                'type'        => 'text',
                'description' => __( 'لینک تصویر آیکون درگاه (اختیاری).', 'ready-wallet' ),
                'default'     => '',
                'placeholder' => 'https://example.com/wallet-icon.png',
            ),
        );
    }

    /**
     * بررسی در دسترس بودن درگاه
     * اگر کاربر لاگین نباشد یا موجودی کافی نداشته باشد، درگاه مخفی می‌شود.
     */
    public function is_available() {
        if ( ! parent::is_available() ) return false;
        
        // فقط برای کاربران عضو
        if ( ! is_user_logged_in() ) return false;

        // در صفحه چک‌اوت، اگر موجودی صفر باشد مخفی شود (اختیاری - فعلا برای UX بهتر همیشه نشان می‌دهیم تا کاربر پیام خطا ببیند)
        // اما برای جلوگیری از لوپ شارژ: اگر سبد خرید حاوی محصول "شارژ کیف پول" است، درگاه کیف پول باید غیرفعال شود.
        if ( ! is_admin() && WC()->cart ) {
            $deposit_product_id = get_option( 'ready_wallet_deposit_product_id' );
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( $cart_item['product_id'] == $deposit_product_id ) {
                    return false; // نمی‌توان برای شارژ کیف پول از خود کیف پول استفاده کرد!
                }
            }
        }

        return true;
    }

    /**
     * پردازش پرداخت (Process Payment)
     * کسر موجودی از کاربر و تکمیل سفارش
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // 1. اعتبارسنجی‌های اولیه
        if ( ! $order ) {
            wc_add_notice( __( 'سفارش نامعتبر است.', 'ready-wallet' ), 'error' );
            return;
        }

        $user_id = $order->get_user_id();
        $amount  = $order->get_total();

        if ( $amount <= 0 ) {
            $order->payment_complete();
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        // 2. بررسی موجودی کاربر
        $balance = Ready_Wallet()->db->get_wallet_balance( $user_id );

        if ( $balance < $amount ) {
            wc_add_notice( sprintf( __( 'موجودی کیف پول شما کافی نیست. موجودی فعلی: %s', 'ready-wallet' ), wc_price( $balance ) ), 'error' );
            return;
        }

        // 3. کسر از موجودی (Debit Transaction)
        $result = Ready_Wallet()->db->add_transaction([
            'user_id'      => $user_id,
            'amount'       => $amount,
            'type'         => 'debit',
            'reference_id' => $order_id,
            'description'  => sprintf( __( 'بابت سفارش #%s', 'ready-wallet' ), $order->get_order_number() )
        ]);

        // 4. بررسی نتیجه تراکنش
        if ( is_wp_error( $result ) ) {
            wc_add_notice( __( 'خطا در پردازش کیف پول: ', 'ready-wallet' ) . $result->get_error_message(), 'error' );
            return;
        }

        // 5. تکمیل سفارش
        // ثبت شناسه تراکنش ما در متای سفارش ووکامرس
        $order->payment_complete( $result ); // $result همان Transaction ID است
        $order->add_order_note( sprintf( __( 'پرداخت موفق با کیف پول. شناسه تراکنش: %s. مبلغ کسر شده: %s', 'ready-wallet' ), $result, wc_price( $amount ) ) );
        
        // ذخیره موجودی باقیمانده در یادداشت برای ارجاع بعدی
        $new_balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
        $order->add_order_note( sprintf( __( 'موجودی باقیمانده کاربر: %s', 'ready-wallet' ), wc_price( $new_balance ) ) );

        // 6. خالی کردن سبد خرید و هدایت کاربر
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    /**
     * پردازش بازگشت وجه (Refund)
     * وقتی مدیر در صفحه سفارش دکمه "بازگشت وجه" را می‌زند، این تابع اجرا می‌شود.
     *
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();

        if ( ! $order || ! $user_id ) {
            return new WP_Error( 'error', __( 'اطلاعات سفارش یا کاربر نامعتبر است.', 'ready-wallet' ) );
        }

        if ( $amount <= 0 ) {
            return new WP_Error( 'error', __( 'مبلغ بازگشتی باید بیشتر از صفر باشد.', 'ready-wallet' ) );
        }

        // ثبت تراکنش واریز (Refund/Credit)
        $description = sprintf( __( 'بازگشت وجه سفارش #%s', 'ready-wallet' ), $order->get_order_number() );
        if ( ! empty( $reason ) ) {
            $description .= ' - ' . __( 'دلیل:', 'ready-wallet' ) . ' ' . $reason;
        }

        $result = Ready_Wallet()->db->add_transaction([
            'user_id'      => $user_id,
            'amount'       => $amount,
            'type'         => 'refund', // نوع تراکنش مخصوص ریفاند
            'reference_id' => $order_id,
            'description'  => $description,
            'admin_id'     => get_current_user_id()
        ]);

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $order->add_order_note( sprintf( __( 'مبلغ %s به کیف پول کاربر بازگردانده شد. شناسه تراکنش: %s', 'ready-wallet' ), wc_price( $amount ), $result ) );

        return true;
    }
}

endif;

/**
 * ثبت کلاس درگاه در فیلتر ووکامرس
 */
add_filter( 'woocommerce_payment_gateways', 'add_ready_wallet_gateway_class_pro' );
function add_ready_wallet_gateway_class_pro( $methods ) {
    $methods[] = 'WC_Gateway_Ready_Wallet';
    return $methods;
}