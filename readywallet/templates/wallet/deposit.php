<?php
/**
 * The Template for displaying deposit form.
 *
 * فرم افزایش موجودی "خارق‌العاده" با قابلیت انتخاب سریع و UX مدرن.
 *
 * @package     ReadyWallet/Templates
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

// دریافت تنظیمات محدودیت شارژ
$min_deposit = (float) get_option( 'ready_wallet_min_deposit', 1000 );
$max_deposit = (float) get_option( 'ready_wallet_max_deposit', 0 );

// مبالغ پیشنهادی برای شارژ سریع (قابل فیلتر توسط توسعه‌دهندگان)
$quick_amounts = apply_filters( 'ready_wallet_quick_amounts', array( 50000, 100000, 200000, 500000 ) );
?>

<div class="woo-wallet-deposit-wrapper ready-wallet-card">
    
    <!-- 1. معرفی بخش -->
    <div class="rw-card-intro" style="text-align: center; margin-bottom: 30px;">
        <div style="background: #e0e7ff; color: var(--rw-primary); width: 64px; height: 64px; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 8px 20px -5px rgba(93, 52, 242, 0.2);">
            <span class="dashicons dashicons-plus-alt2" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </div>
        <h4 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: var(--rw-text-main);"><?php _e( 'شارژ کیف پول', 'ready-wallet' ); ?></h4>
        <p style="color: var(--rw-text-muted); margin: 0; font-size: 14px; line-height: 1.6;"><?php _e( 'موجودی حساب خود را به صورت آنی و امن افزایش دهید.', 'ready-wallet' ); ?></p>
    </div>

    <form method="post" action="" id="woo_wallet_deposit_form" class="rw-form">
        
        <!-- 2. انتخاب سریع مبلغ (Chips) -->
        <div class="rw-quick-amounts-wrapper" style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 10px; font-size: 13px; font-weight: 600; color: var(--rw-text-muted); text-align: center;"><?php _e( 'انتخاب سریع مبلغ:', 'ready-wallet' ); ?></label>
            <div class="rw-quick-amounts" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                <?php foreach( $quick_amounts as $amt ): ?>
                    <button type="button" class="rw-chip" 
                        onclick="document.getElementById('woo_wallet_deposit_amount').value='<?php echo number_format($amt); ?>'; document.getElementById('woo_wallet_deposit_amount').dispatchEvent(new Event('input'));">
                        <?php echo wc_price( $amt ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. ورودی مبلغ اصلی -->
        <div class="rw-input-group">
            <label for="woo_wallet_deposit_amount"><?php _e( 'مبلغ دلخواه (تومان)', 'ready-wallet' ); ?></label>
            <div style="position: relative;">
                <input type="text" inputmode="numeric" name="woo_wallet_deposit_amount" id="woo_wallet_deposit_amount" class="rw-input" placeholder="مثلاً: ۵۰,۰۰۰" required style="padding-left: 50px; font-size: 20px; font-weight: 800; text-align: center; height: 56px; letter-spacing: 1px;" />
                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--rw-text-muted); font-size: 13px; font-weight: 600;">IRT</span>
            </div>
            
            <!-- راهنمای محدودیت -->
            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--rw-text-muted); margin-top: 8px; padding: 0 5px;">
                <span><?php printf( __( 'حداقل: %s', 'ready-wallet' ), wc_price( $min_deposit ) ); ?></span>
                <?php if( $max_deposit > 0 ): ?>
                    <span><?php printf( __( 'حداکثر: %s', 'ready-wallet' ), wc_price( $max_deposit ) ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- هوک برای افزودن فیلدهای اضافه توسط پلاگین‌های دیگر -->
        <?php do_action( 'woo_wallet_before_deposit_form_submit' ); ?>

        <!-- 4. دکمه پرداخت -->
        <div class="rw-action-row">
            <button type="submit" class="rw-btn rw-btn-primary" name="woo_wallet_deposit" value="Add">
                <?php _e( 'پرداخت و شارژ آنی', 'ready-wallet' ); ?> 
                <span class="dashicons dashicons-arrow-left-alt2" style="margin-right: 8px; font-size: 18px; width: 18px; height: 18px;"></span>
            </button>
        </div>
        
        <?php wp_nonce_field( 'woo_wallet_deposit', 'woo_wallet_deposit_nonce' ); ?>
    </form>
</div>

<style>
/* استایل اختصاصی برای چیپ‌های انتخاب سریع */
.rw-chip {
    background: #fff;
    border: 1px solid var(--rw-border);
    border-radius: 50px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    color: var(--rw-text-main);
    display: inline-flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.rw-chip:hover {
    border-color: var(--rw-primary);
    color: var(--rw-primary);
    background: #eef2ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(93, 52, 242, 0.15);
}

.rw-chip:active {
    transform: translateY(0);
}
</style>