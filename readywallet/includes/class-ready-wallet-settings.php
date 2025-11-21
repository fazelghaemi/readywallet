<?php
/**
 * ReadyWallet Pro Settings Class
 *
 * نسخه اصلاح شده: فقط تعریف کلاس (بدون اجرای خودکار)
 */

defined( 'ABSPATH' ) || exit;

// کلاس فقط زمانی تعریف می‌شود که کلاس والد وجود داشته باشد
if ( class_exists( 'WC_Settings_Page' ) ) :

class WC_Settings_Ready_Wallet extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'ready_wallet';
        $this->label = __( 'کیف پول ردی', 'ready-wallet' );

        // اتصال هوک‌ها
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
    }

    public function get_sections() {
        $sections = array(
            ''         => __( 'عمومی', 'ready-wallet' ),
            'sms'      => __( 'پیامک (MessageWay)', 'ready-wallet' ),
            'advanced' => __( 'پیشرفته', 'ready-wallet' ),
        );
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    public function get_settings( $current_section = '' ) {
        $settings = array();

        if ( 'sms' === $current_section ) {
            $settings = $this->get_sms_settings();
        } elseif ( 'advanced' === $current_section ) {
            $settings = $this->get_advanced_settings();
        } else {
            $settings = $this->get_general_settings();
        }

        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
    }

    private function get_general_settings() {
        return array(
            array( 'title' => __( 'تنظیمات نمایش', 'ready-wallet' ), 'type' => 'title', 'id' => 'ready_wallet_general_options' ),
            array(
                'title'    => __( 'عنوان منو', 'ready-wallet' ),
                'id'       => 'ready_wallet_menu_title',
                'type'     => 'text',
                'default'  => __( 'کیف پول من', 'ready-wallet' ),
            ),
            array(
                'title'    => __( 'آدرس صفحه (Endpoint)', 'ready-wallet' ),
                'desc'     => __( 'اسلاگ آدرس در حساب کاربری (مثلاً: wallet)', 'ready-wallet' ),
                'id'       => 'ready_wallet_endpoint',
                'type'     => 'text',
                'default'  => 'ready-wallet',
            ),
            array( 'type' => 'sectionend', 'id' => 'ready_wallet_general_options' ),
            
            array( 'title' => __( 'محدودیت‌های مالی', 'ready-wallet' ), 'type' => 'title', 'id' => 'ready_wallet_limits_options' ),
            array(
                'title'    => __( 'حداقل شارژ', 'ready-wallet' ),
                'id'       => 'ready_wallet_min_deposit',
                'type'     => 'number',
                'default'  => '1000',
            ),
            array(
                'title'    => __( 'حداکثر شارژ', 'ready-wallet' ),
                'id'       => 'ready_wallet_max_deposit',
                'type'     => 'number',
                'default'  => '0',
            ),
            array( 'type' => 'sectionend', 'id' => 'ready_wallet_limits_options' ),
        );
    }

    private function get_sms_settings() {
        return array(
            array( 'title' => __( 'تنظیمات درگاه پیامک', 'ready-wallet' ), 'type' => 'title', 'id' => 'ready_wallet_sms_options' ),
            array(
                'title'   => __( 'فعالسازی', 'ready-wallet' ),
                'id'      => 'ready_wallet_sms_enable',
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title'    => __( 'API Key', 'ready-wallet' ),
                'id'       => 'ready_wallet_sms_api_key',
                'type'     => 'password',
                'css'      => 'min-width:350px;',
            ),
            array( 'type' => 'sectionend', 'id' => 'ready_wallet_sms_options' ),
            
            array( 'title' => __( 'الگوهای پیامک', 'ready-wallet' ), 'type' => 'title', 'id' => 'ready_wallet_sms_patterns' ),
            array(
                'title'    => __( 'الگوی شارژ (Credit)', 'ready-wallet' ),
                'id'       => 'ready_wallet_sms_tpl_charge',
                'type'     => 'number',
                'desc_tip' => true,
                'desc'     => 'کد پترن برای افزایش موجودی',
            ),
            array(
                'title'    => __( 'الگوی کسر (Debit)', 'ready-wallet' ),
                'id'       => 'ready_wallet_sms_tpl_debit',
                'type'     => 'number',
                'desc_tip' => true,
                'desc'     => 'کد پترن برای کاهش موجودی',
            ),
            array( 'type' => 'sectionend', 'id' => 'ready_wallet_sms_patterns' ),
        );
    }

    private function get_advanced_settings() {
        return array(
            array( 'title' => __( 'پیشرفته', 'ready-wallet' ), 'type' => 'title', 'id' => 'ready_wallet_advanced_options' ),
            array(
                'title'   => __( 'حالت دیباگ', 'ready-wallet' ),
                'id'      => 'ready_wallet_debug',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc'    => __( 'ثبت لاگ در ووکامرس', 'ready-wallet' ),
            ),
            array(
                'title'   => __( 'حذف داده‌ها هنگام پاک کردن افزونه', 'ready-wallet' ),
                'id'      => 'ready_wallet_delete_data',
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array( 'type' => 'sectionend', 'id' => 'ready_wallet_advanced_options' ),
        );
    }
}

endif;