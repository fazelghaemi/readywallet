<?php
/**
 * The Template for displaying transfer form.
 *
 * فرم انتقال اعتبار "خارق‌العاده" با تمرکز بر امنیت و دقت در ورود اطلاعات.
 *
 * @package     ReadyWallet/Templates
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woo-wallet-transfer-wrapper ready-wallet-card">
    
    <!-- 1. معرفی بخش -->
    <div class="rw-card-intro" style="text-align: center; margin-bottom: 30px;">
        <div style="background: #fef3c7; color: #d97706; width: 64px; height: 64px; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 8px 20px -5px rgba(245, 158, 11, 0.2);">
            <span class="dashicons dashicons-migrate" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </div>
        <h4 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: var(--rw-text-main);"><?php _e( 'انتقال اعتبار', 'ready-wallet' ); ?></h4>
        <p style="color: var(--rw-text-muted); margin: 0; font-size: 14px; line-height: 1.6;"><?php _e( 'انتقال آنی و بدون کارمزد اعتبار به کیف پول سایر کاربران.', 'ready-wallet' ); ?></p>
    </div>

    <form method="post" action="" id="woo_wallet_transfer_form" class="rw-form">
        
        <!-- 2. مشخصات گیرنده -->
        <div class="rw-input-group">
            <label for="rw_transfer_recipient"><?php _e( 'مشخصات گیرنده', 'ready-wallet' ); ?></label>
            <div style="position: relative;">
                <input type="text" name="rw_transfer_recipient" id="rw_transfer_recipient" class="rw-input" placeholder="ایمیل یا نام کاربری" required style="padding-left: 40px;" />
                <!-- آیکون کاربر -->
                <span class="dashicons dashicons-admin-users" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></span>
            </div>
            <span class="rw-input-hint" style="color: var(--rw-text-muted); font-size: 11px; margin-top: 5px; display: block;">
                <?php _e( 'ایمیل، نام کاربری یا شماره موبایل (در صورت ثبت بودن) را وارد کنید.', 'ready-wallet' ); ?>
            </span>
        </div>

        <!-- 3. مبلغ انتقال -->
        <div class="rw-input-group">
            <label for="rw_transfer_amount"><?php _e( 'مبلغ انتقال (تومان)', 'ready-wallet' ); ?></label>
            <div style="position: relative;">
                <input type="text" inputmode="numeric" name="rw_transfer_amount" id="rw_transfer_amount" class="rw-input" placeholder="مبلغ را وارد کنید" required style="padding-left: 50px; font-size: 18px; font-weight: bold;" />
                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--rw-text-muted); font-size: 12px;">IRT</span>
            </div>
        </div>

        <!-- 4. یادداشت تراکنش -->
        <div class="rw-input-group">
            <label for="rw_transfer_note"><?php _e( 'یادداشت (اختیاری)', 'ready-wallet' ); ?></label>
            <div style="position: relative;">
                <textarea name="rw_transfer_note" id="rw_transfer_note" class="rw-input" rows="3" placeholder="توضیحاتی برای گیرنده..." style="padding-left: 40px; min-height: 80px; resize: vertical;"></textarea>
                <span class="dashicons dashicons-edit" style="position: absolute; left: 12px; top: 15px; color: #9ca3af;"></span>
            </div>
        </div>

        <!-- 5. دکمه اقدام -->
        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-primary" name="ready_wallet_transfer" value="Transfer">
                <?php _e( 'بررسی و انتقال وجه', 'ready-wallet' ); ?> 
                <span class="dashicons dashicons-arrow-left-alt2" style="margin-right: 8px;"></span>
            </button>
        </div>

        <?php wp_nonce_field( 'ready_wallet_transfer', 'ready_wallet_transfer_nonce' ); ?>
    </form>
</div>