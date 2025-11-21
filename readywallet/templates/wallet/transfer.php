<?php
/**
 * The Template for displaying transfer form.
 *
 * @package ReadyWallet/Templates
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woo-wallet-transfer-wrapper ready-wallet-card">
    <div class="rw-card-header transfer-header">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        <h4><?php _e( 'انتقال اعتبار به کاربر دیگر', 'ready-wallet' ); ?></h4>
    </div>

    <div class="rw-alert rw-alert-warning">
        <?php _e( 'توجه: انتقال وجه غیرقابل بازگشت است. لطفاً در وارد کردن اطلاعات گیرنده دقت کنید.', 'ready-wallet' ); ?>
    </div>

    <form method="post" action="" id="woo_wallet_transfer_form" class="rw-form">
        
        <!-- فیلد گیرنده -->
        <div class="rw-input-group">
            <label for="rw_transfer_recipient"><?php _e( 'ایمیل یا نام کاربری گیرنده', 'ready-wallet' ); ?></label>
            <input type="text" name="rw_transfer_recipient" id="rw_transfer_recipient" class="rw-input" placeholder="example@email.com" required />
            <span class="rw-input-hint"><?php _e( 'کاربری که می‌خواهید به او اعتبار انتقال دهید.', 'ready-wallet' ); ?></span>
        </div>

        <!-- فیلد مبلغ -->
        <div class="rw-input-group">
            <label for="rw_transfer_amount"><?php _e( 'مبلغ انتقال (تومان)', 'ready-wallet' ); ?></label>
            <input type="number" step="0.01" name="rw_transfer_amount" id="rw_transfer_amount" class="rw-input" placeholder="مبلغ را وارد کنید" required />
        </div>

        <!-- فیلد یادداشت -->
        <div class="rw-input-group">
            <label for="rw_transfer_note"><?php _e( 'یادداشت (اختیاری)', 'ready-wallet' ); ?></label>
            <textarea name="rw_transfer_note" id="rw_transfer_note" class="rw-input" rows="2" placeholder="توضیحات تراکنش..."></textarea>
        </div>

        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-primary" name="ready_wallet_transfer" value="Transfer">
                <?php _e( 'تایید و انتقال وجه', 'ready-wallet' ); ?>
            </button>
        </div>

        <?php wp_nonce_field( 'ready_wallet_transfer', 'ready_wallet_transfer_nonce' ); ?>
    </form>
</div>