<?php
/**
 * The Template for displaying wallet balance.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/wallet/balance.php.
 *
 * @package ReadyWallet/Templates
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$balance = woo_wallet()->wallet->get_wallet_balance( $user_id );
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="woo-wallet-balance">
    <div class="woo-wallet-balance-header">
        <h3><?php _e( 'موجودی کیف پول شما', 'ready-wallet' ); ?></h3>
        <span class="wallet-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
        </span>
    </div>
    
    <div class="woo-wallet-balance-amount">
        <span class="amount"><?php echo wc_price( $balance ); ?></span>
    </div>

    <div class="woo-wallet-balance-footer">
        <span class="card-holder"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
        <span class="card-number">**** **** **** <?php echo rand(1000, 9999); ?></span>
    </div>
</div>