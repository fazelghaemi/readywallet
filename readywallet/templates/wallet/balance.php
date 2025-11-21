<?php
/**
 * The Template for displaying wallet balance (Card UI).
 *
 * این تمپلیت کارت بانکی شیشه‌ای (Glassmorphism) را رندر می‌کند.
 *
 * @package     ReadyWallet/Templates
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();

// دریافت موجودی از کلاس دیتابیس اختصاصی
$balance = Ready_Wallet()->db->get_wallet_balance( $user_id );

// اطلاعات کاربر
$user = get_userdata( $user_id );
$display_name = $user ? $user->display_name : __( 'کاربر مهمان', 'ready-wallet' );

// تولید شماره کارت مجازی (صرفاً جهت زیبایی UI)
// الگوریتم: 6037 + تاریخ عضویت + شناسه کاربر
$registered_year = date( 'y', strtotime( $user->user_registered ) );
$card_number = sprintf( '6037 99%s %04d %04d', $registered_year, $user_id, rand(1000, 9999) );

?>

<div class="woo-wallet-balance">
    <div class="woo-wallet-balance-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <h3><?php _e( 'موجودی کیف پول', 'ready-wallet' ); ?></h3>
            <span style="font-size: 11px; opacity: 0.7; letter-spacing: 0.5px;">Ready Studio Wallet</span>
        </div>
        
        <span class="wallet-icon" style="opacity: 0.8;">
            <!-- آیکون چیپ کارت -->
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="20" rx="3"></rect>
                <path d="M2 10h20"></path>
                <path d="M6 10v10"></path>
                <path d="M18 10v10"></path>
                <path d="M12 10v4"></path>
                <path d="M12 18v2"></path>
            </svg>
        </span>
    </div>
    
    <div class="woo-wallet-balance-amount">
        <span class="amount">
            <?php echo wc_price( $balance ); ?>
        </span>
    </div>

    <div class="woo-wallet-balance-footer">
        <div style="text-align: right;">
            <span class="card-label" style="display: block; font-size: 10px; opacity: 0.6; margin-bottom: 2px;"><?php _e('دارنده کارت', 'ready-wallet'); ?></span>
            <span class="card-holder" style="font-weight: 600; letter-spacing: 0.5px;"><?php echo esc_html( $display_name ); ?></span>
        </div>
        
        <div style="text-align: left; dir: ltr;">
            <span class="card-number" style="font-family: monospace; font-size: 14px; letter-spacing: 2px; opacity: 0.9;"><?php echo $card_number; ?></span>
        </div>
    </div>
    
    <!-- آیکون پس‌زمینه تزئینی -->
    <div style="position: absolute; right: -20px; bottom: -20px; opacity: 0.05; transform: rotate(-15deg);">
        <svg width="150" height="150" viewBox="0 0 24 24" fill="currentColor"><path d="M21 18v1c0 1.1-.9 2-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14c1.1 0 2 .9 2 2v1h-9a2 2 0 00-2 2v8a2 2 0 002 2h9zm-9-2h9a1 1 0 001-1v-2a1 1 0 00-1-1h-9a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
    </div>
</div>