<?php
/**
 * The Template for displaying deposit form.
 *
 * @package ReadyWallet/Templates
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woo-wallet-deposit-wrapper ready-wallet-card">
    <div class="rw-card-header">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        <h4><?php _e( 'افزایش موجودی کیف پول', 'ready-wallet' ); ?></h4>
    </div>
    
    <p class="rw-description"><?php _e( 'مبلغ مورد نظر جهت شارژ حساب کاربری خود را وارد کنید.', 'ready-wallet' ); ?></p>

    <form method="post" action="" id="woo_wallet_deposit_form" class="rw-form">
        <div class="rw-input-group">
            <label for="woo_wallet_deposit_amount"><?php _e( 'مبلغ (تومان)', 'ready-wallet' ); ?></label>
            <input type="number" step="0.01" min="1000" name="woo_wallet_deposit_amount" id="woo_wallet_deposit_amount" class="rw-input" placeholder="مثلاً ۵۰,۰۰۰" required />
        </div>

        <?php do_action( 'woo_wallet_before_deposit_form_submit' ); ?>

        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-primary" name="woo_wallet_deposit" value="<?php _e( 'Add', 'ready-wallet' ); ?>">
                <?php _e( 'پرداخت و شارژ حساب', 'ready-wallet' ); ?>
            </button>
        </div>
        
        <?php wp_nonce_field( 'woo_wallet_deposit', 'woo_wallet_deposit_nonce' ); ?>
    </form>
</div>