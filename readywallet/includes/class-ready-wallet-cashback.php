<?php
/**
 * ReadyWallet Cashback System
 * سیستم بازگشت وجه (پاداش خرید)
 * * ویژگی‌ها:
 * - افزودن تنظیمات کش‌بک به صفحه ویرایش محصول
 * - محاسبه خودکار پاداش پس از تکمیل سفارش
 * - پشتیبانی از مبلغ ثابت یا درصدی
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Cashback {

    public function __construct() {
        // افزودن فیلد به تنظیمات محصول (Tab General)
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_cashback_fields' ) );
        
        // ذخیره فیلد
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_cashback_fields' ) );
        
        // محاسبه و واریز هنگام تکمیل سفارش
        add_action( 'woocommerce_order_status_completed', array( $this, 'process_order_cashback' ) );
    }

    /**
     * افزودن فیلد ورودی به صفحه محصول
     */
    public function add_product_cashback_fields() {
        echo '<div class="options_group">';

        woocommerce_wp_text_input( array(
            'id'          => '_ready_wallet_cashback',
            'label'       => __( 'مبلغ کش‌بک (تومان)', 'ready-wallet' ),
            'description' => __( 'مبلغی که پس از خرید این محصول به کیف پول مشتری برمی‌گردد. برای عدم پرداخت، خالی بگذارید.', 'ready-wallet' ),
            'desc_tip'    => true,
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0'
            )
        ) );

        echo '</div>';
    }

    /**
     * ذخیره تنظیمات محصول
     */
    public function save_product_cashback_fields( $post_id ) {
        $cashback = isset( $_POST['_ready_wallet_cashback'] ) ? wc_clean( $_POST['_ready_wallet_cashback'] ) : '';
        update_post_meta( $post_id, '_ready_wallet_cashback', $cashback );
    }

    /**
     * پردازش سفارش و واریز کش‌بک
     * این تابع وقتی وضعیت سفارش به "تکمیل شده" تغییر می‌کند اجرا می‌شود.
     */
    public function process_order_cashback( $order_id ) {
        $order = wc_get_order( $order_id );
        
        // اگر سفارش برای مهمان است، کاری نکن
        if ( ! $order->get_user_id() ) return;

        // جلوگیری از پردازش تکراری (با استفاده از متای سفارش)
        if ( $order->get_meta( '_ready_wallet_cashback_processed' ) ) return;

        $total_cashback = 0;

        // بررسی آیتم‌های سفارش
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            
            if ( ! $product ) continue;

            // دریافت میزان کش‌بک محصول
            $cashback_amount = $product->get_meta( '_ready_wallet_cashback' );

            if ( $cashback_amount && is_numeric( $cashback_amount ) && $cashback_amount > 0 ) {
                // محاسبه کل: مبلغ کش‌بک * تعداد خریداری شده
                $total_cashback += ( floatval( $cashback_amount ) * $item->get_quantity() );
            }
        }

        // اگر مبلغ کش‌بک بیشتر از صفر بود، واریز کن
        if ( $total_cashback > 0 ) {
            $user_id = $order->get_user_id();
            
            $wallet_db = new Ready_Wallet_DB();
            
            $result = $wallet_db->add_transaction([
                'user_id'      => $user_id,
                'amount'       => $total_cashback,
                'type'         => 'cashback', // نوع تراکنش: پاداش خرید
                'reference_id' => $order_id,
                'description'  => sprintf( __( 'پاداش خرید سفارش #%s', 'ready-wallet' ), $order->get_order_number() )
            ]);

            if ( ! is_wp_error( $result ) ) {
                // علامت‌گذاری سفارش برای جلوگیری از واریز مجدد
                $order->update_meta_data( '_ready_wallet_cashback_processed', 'yes' );
                $order->add_order_note( sprintf( __( 'مبلغ %s به عنوان کش‌بک به کیف پول کاربر واریز شد.', 'ready-wallet' ), wc_price( $total_cashback ) ) );
                $order->save();
            }
        }
    }
}

return new Ready_Wallet_Cashback();