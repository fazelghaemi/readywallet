<?php
/**
 * The Template for displaying withdraw form.
 *
 * @package ReadyWallet/Templates
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woo-wallet-withdraw-wrapper ready-wallet-card">
    <div class="rw-card-header withdrawal-header">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        <h4><?php _e( 'درخواست برداشت وجه', 'ready-wallet' ); ?></h4>
    </div>

    <div class="rw-alert rw-alert-info">
        <?php _e( 'مبلغ درخواستی پس از بررسی توسط مدیریت به حساب بانکی شما واریز خواهد شد.', 'ready-wallet' ); ?>
    </div>

    <form method="post" action="" id="woo_wallet_withdraw_form" class="rw-form">
        
        <!-- نمایش فیلد مبلغ -->
        <div class="rw-input-group">
            <label for="woo_wallet_withdraw_amount"><?php _e( 'مبلغ برداشت (تومان)', 'ready-wallet' ); ?></label>
            <input type="number" step="0.01" name="woo_wallet_withdraw_amount" id="woo_wallet_withdraw_amount" class="rw-input" placeholder="مبلغ را وارد کنید" required />
        </div>

        <!-- هوک برای اضافه شدن فیلدهای شبا/کارت بانکی توسط افزونه -->
        <?php do_action( 'woo_wallet_before_withdraw_form_submit' ); ?>

        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-danger" name="woo_wallet_withdraw" value="<?php _e( 'Withdraw', 'ready-wallet' ); ?>">
                <?php _e( 'ثبت درخواست برداشت', 'ready-wallet' ); ?>
            </button>
        </div>

        <?php wp_nonce_field( 'woo_wallet_withdraw', 'woo_wallet_withdraw_nonce' ); ?>
    </form>
</div>