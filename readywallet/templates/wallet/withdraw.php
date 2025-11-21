<?php
/**
 * The Template for displaying withdraw form.
 *
 * فرم درخواست برداشت وجه با طراحی اعتماد‌ساز و رابط کاربری مدرن.
 *
 * @package     ReadyWallet/Templates
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$balance = Ready_Wallet()->db->get_wallet_balance( $user_id );
?>

<div class="woo-wallet-withdraw-wrapper ready-wallet-card">
    
    <!-- 1. هدر و موجودی -->
    <div class="rw-card-intro" style="text-align: center; margin-bottom: 30px;">
        <div style="background: #fee2e2; color: #ef4444; width: 64px; height: 64px; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 8px 20px -5px rgba(239, 68, 68, 0.2);">
            <span class="dashicons dashicons-download" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </div>
        <h4 style="margin: 0 0 5px; font-size: 20px; font-weight: 800; color: var(--rw-text-main);"><?php _e( 'درخواست برداشت وجه', 'ready-wallet' ); ?></h4>
        
        <div style="background: #f9fafb; display: inline-block; padding: 5px 15px; border-radius: 20px; margin-top: 10px; border: 1px solid var(--rw-border);">
            <span style="font-size: 12px; color: var(--rw-text-muted);"><?php _e( 'موجودی قابل برداشت:', 'ready-wallet' ); ?></span>
            <strong style="color: var(--rw-text-main); font-size: 14px; margin-right: 5px;"><?php echo wc_price( $balance ); ?></strong>
        </div>
    </div>

    <!-- 2. باکس راهنما -->
    <div class="rw-alert rw-alert-info" style="background: #eff6ff; color: #1e40af; padding: 15px; border-radius: 12px; font-size: 13px; margin-bottom: 25px; border: 1px solid #dbeafe; line-height: 1.6; display: flex; gap: 10px;">
        <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px; flex-shrink: 0; color: #3b82f6;"></span>
        <div>
            <strong><?php _e( 'توجه:', 'ready-wallet' ); ?></strong>
            <?php _e( 'مبلغ درخواستی پس از بررسی واحد مالی (بین ۱ تا ۲۴ ساعت) به شماره شبای ثبت شده در حساب کاربری شما واریز خواهد شد.', 'ready-wallet' ); ?>
        </div>
    </div>

    <form method="post" action="" id="woo_wallet_withdraw_form" class="rw-form">
        
        <!-- 3. فیلد مبلغ -->
        <div class="rw-input-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 0 5px;">
                <label for="woo_wallet_withdraw_amount" style="margin: 0;"><?php _e( 'مبلغ برداشت (تومان)', 'ready-wallet' ); ?></label>
                
                <!-- دکمه برداشت کل موجودی -->
                <a href="#" onclick="document.getElementById('woo_wallet_withdraw_amount').value='<?php echo $balance; ?>'; document.getElementById('woo_wallet_withdraw_amount').dispatchEvent(new Event('input')); return false;" 
                   style="font-size: 11px; color: var(--rw-primary); text-decoration: none; font-weight: 700; background: #eef2ff; padding: 2px 8px; border-radius: 6px; transition: all 0.2s;">
                    <?php _e( 'برداشت کل موجودی', 'ready-wallet' ); ?>
                </a>
            </div>
            
            <div style="position: relative;">
                <input type="text" inputmode="numeric" name="woo_wallet_withdraw_amount" id="woo_wallet_withdraw_amount" class="rw-input" placeholder="مبلغ را وارد کنید" required style="padding-left: 50px; font-size: 18px; font-weight: bold;" />
                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--rw-text-muted); font-size: 12px;">IRT</span>
            </div>
        </div>

        <!-- هوک برای افزودن فیلد شبا یا کارت بانکی توسط سایر پلاگین‌ها -->
        <?php do_action( 'woo_wallet_before_withdraw_form_submit' ); ?>

        <!-- 4. دکمه اقدام -->
        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-danger" name="woo_wallet_withdraw" value="Withdraw" style="background: #ef4444; color: white; box-shadow: 0 8px 15px -3px rgba(239, 68, 68, 0.3);">
                <?php _e( 'ثبت درخواست برداشت', 'ready-wallet' ); ?> 
                <span class="dashicons dashicons-arrow-left-alt2" style="margin-right: 8px;"></span>
            </button>
        </div>

        <?php wp_nonce_field( 'woo_wallet_withdraw', 'woo_wallet_withdraw_nonce' ); ?>
    </form>
</div>