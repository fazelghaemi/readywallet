<?php
/**
 * ReadyWallet Settings Class
 * افزودن تنظیمات پیامک MessageWay به پنل تنظیمات ووکامرس
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Settings {

    public function __construct() {
        // افزودن سکشن جدید به تب کیف پول
        add_filter( 'woocommerce_get_sections_woo_wallet', array( $this, 'add_sms_section' ) );
        
        // افزودن فیلدها به سکشن پیامک
        add_filter( 'woocommerce_get_settings_woo_wallet', array( $this, 'add_sms_settings' ), 10, 2 );
    }

    /**
     * ثبت تب (Section) جدید در تنظیمات
     */
    public function add_sms_section( $sections ) {
        $sections['ready_sms'] = __( 'تنظیمات پیامک (MessageWay)', 'ready-wallet' );
        return $sections;
    }

    /**
     * تعریف فیلدهای تنظیمات
     */
    public function add_sms_settings( $settings, $current_section ) {
        
        if ( 'ready_sms' === $current_section ) {
            $custom_settings = array(
                array(
                    'title' => __( 'تنظیمات درگاه پیامک راه پیام', 'ready-wallet' ),
                    'type'  => 'title',
                    'desc'  => __( 'اطلاعات پنل کاربری MessageWay خود را اینجا وارد کنید.', 'ready-wallet' ),
                    'id'    => 'ready_wallet_sms_options',
                ),
                array(
                    'title'    => __( 'کلید API', 'ready-wallet' ),
                    'desc'     => __( 'کلید دسترسی (API Key) خود را از پنل MessageWay دریافت کنید.', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_api_key',
                    'type'     => 'text',
                    'css'      => 'min-width:300px;',
                    'desc_tip' => true,
                ),
                array(
                    'title'    => __( 'شناسه قالب شارژ (Credit)', 'ready-wallet' ),
                    'desc'     => __( 'Template ID برای زمانی که کیف پول شارژ می‌شود.', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_tpl_charge',
                    'type'     => 'number',
                    'desc_tip' => __( 'مثال: 1001. پارامترها: مبلغ، موجودی، شماره تراکنش', 'ready-wallet' ),
                ),
                array(
                    'title'    => __( 'شناسه قالب برداشت (Debit)', 'ready-wallet' ),
                    'desc'     => __( 'Template ID برای زمانی که از کیف پول برداشت می‌شود (خرید).', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_tpl_debit',
                    'type'     => 'number',
                    'desc_tip' => __( 'مثال: 1002. پارامترها: مبلغ، موجودی، شماره تراکنش', 'ready-wallet' ),
                ),
                array(
                    'title'   => __( 'فعالسازی پیامک', 'ready-wallet' ),
                    'desc'    => __( 'ارسال پیامک فعال باشد', 'ready-wallet' ),
                    'id'      => 'ready_wallet_sms_enable',
                    'type'    => 'checkbox',
                    'default' => 'yes',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'ready_wallet_sms_options',
                ),
            );

            return $custom_settings;
        }

        return $settings;
    }
}

return new Ready_Wallet_Settings();