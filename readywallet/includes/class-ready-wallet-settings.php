<?php
/**
 * ReadyWallet Settings Tab
 * ایجاد تب اختصاصی در تنظیمات ووکامرس
 */

defined( 'ABSPATH' ) || exit;

// اطمینان از اینکه کلاس تنظیمات ووکامرس وجود دارد
if ( class_exists( 'WC_Settings_Page' ) ) :

class WC_Settings_Ready_Wallet extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'ready_wallet';
        $this->label = __( 'کیف پول ردی', 'ready-wallet' );

        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
    }

    /**
     * تعریف سکشن‌ها (زیرمنوها)
     */
    public function get_sections() {
        $sections = array(
            ''         => __( 'عمومی', 'ready-wallet' ),
            'sms'      => __( 'تنظیمات پیامک (MessageWay)', 'ready-wallet' ),
        );
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    /**
     * تعریف فیلدها
     */
    public function get_settings( $current_section = '' ) {
        $settings = array();

        if ( 'sms' === $current_section ) {
            // --- تنظیمات پیامک ---
            $settings = array(
                array(
                    'title' => __( 'تنظیمات درگاه پیامک', 'ready-wallet' ),
                    'type'  => 'title',
                    'desc'  => __( 'تنظیمات اتصال به سامانه پیامکی MessageWay', 'ready-wallet' ),
                    'id'    => 'ready_wallet_sms_options',
                ),
                array(
                    'title'   => __( 'فعالسازی پیامک', 'ready-wallet' ),
                    'id'      => 'ready_wallet_sms_enable',
                    'type'    => 'checkbox',
                    'default' => 'yes',
                ),
                array(
                    'title'    => __( 'کلید API', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_api_key',
                    'type'     => 'text',
                    'css'      => 'min-width:300px;',
                ),
                array(
                    'title'    => __( 'قالب شارژ (Credit)', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_tpl_charge',
                    'type'     => 'number',
                    'desc'     => __( 'شناسه پترن در پنل پیامک', 'ready-wallet' ),
                    'desc_tip' => true,
                ),
                array(
                    'title'    => __( 'قالب برداشت (Debit)', 'ready-wallet' ),
                    'id'       => 'ready_wallet_sms_tpl_debit',
                    'type'     => 'number',
                    'desc'     => __( 'شناسه پترن در پنل پیامک', 'ready-wallet' ),
                    'desc_tip' => true,
                ),
                array( 'type' => 'sectionend', 'id' => 'ready_wallet_sms_options' ),
            );
        } else {
            // --- تنظیمات عمومی ---
            $settings = array(
                array(
                    'title' => __( 'تنظیمات عمومی کیف پول', 'ready-wallet' ),
                    'type'  => 'title',
                    'id'    => 'ready_wallet_general_options',
                ),
                array(
                    'title'    => __( 'عنوان در حساب کاربری', 'ready-wallet' ),
                    'id'       => 'ready_wallet_menu_title',
                    'type'     => 'text',
                    'default'  => __( 'کیف پول من', 'ready-wallet' ),
                ),
                array( 'type' => 'sectionend', 'id' => 'ready_wallet_general_options' ),
            );
        }

        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
    }
}

return new WC_Settings_Ready_Wallet();

endif;