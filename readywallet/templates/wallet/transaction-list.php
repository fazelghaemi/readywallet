<?php
/**
 * The Template for displaying transaction list.
 *
 * لیست تراکنش‌های "خارق‌العاده" با طراحی مدرن، ریسپانسیو و صفحه‌بندی.
 *
 * @package     ReadyWallet/Templates
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

// دریافت متغیرهای ارسال شده از شورتکد
$transactions = get_query_var( 'rw_transactions', [] );
$current_page = get_query_var( 'rw_current_page', 1 );
$total_pages  = get_query_var( 'rw_total_pages', 1 );
?>

<div class="woo-wallet-transactions-wrapper">
    
    <!-- 1. هدر بخش تراکنش‌ها -->
    <div class="rw-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h4 class="rw-section-title" style="margin: 0; font-size: 18px; color: var(--rw-text-main); font-weight: 700;"><?php _e( 'تراکنش‌های اخیر', 'ready-wallet' ); ?></h4>
            <span class="rw-meta-info" style="font-size: 12px; color: var(--rw-text-muted); background: #f3f4f6; padding: 2px 8px; border-radius: 6px; margin-top: 4px; display: inline-block;">
                <?php printf( __( 'صفحه %d از %d', 'ready-wallet' ), $current_page, max(1, $total_pages) ); ?>
            </span>
        </div>
        
        <!-- دکمه ابزار: رفرش صفحه -->
        <div class="rw-tools">
            <button type="button" class="rw-icon-btn" title="<?php _e('به‌روزرسانی لیست', 'ready-wallet'); ?>" onclick="location.reload();" 
                style="background: transparent; border: 1px solid var(--rw-border); border-radius: 8px; padding: 8px; cursor: pointer; color: var(--rw-text-muted); transition: all 0.2s;">
                <span class="dashicons dashicons-update"></span>
            </button>
        </div>
    </div>

    <!-- 2. جدول ریسپانسیو -->
    <div class="rw-table-responsive">
        <table class="woo-wallet-transactions">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th><?php _e( 'نوع عملیات', 'ready-wallet' ); ?></th>
                    <th><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                    <th><?php _e( 'تاریخ و زمان', 'ready-wallet' ); ?></th>
                    <th class="rw-col-details"><?php _e( 'توضیحات', 'ready-wallet' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $transactions && count( $transactions ) > 0 ) : ?>
                    <?php foreach ( $transactions as $transaction ) : ?>
                        <?php 
                            // تعیین استایل و آیکون بر اساس نوع تراکنش
                            $row_class = '';
                            $icon = '';
                            $amount_prefix = '';
                            $label = '';
                            $badge_class = '';
                            
                            switch($transaction->type) {
                                case 'credit': 
                                    $row_class = 'type-credit'; 
                                    $icon = 'dashicons-arrow-down-alt';
                                    $label = __('واریز', 'ready-wallet');
                                    $badge_class = 'credit';
                                    $amount_prefix = '+';
                                    break;
                                case 'debit': 
                                    $row_class = 'type-debit'; 
                                    $icon = 'dashicons-arrow-up-alt';
                                    $label = __('برداشت', 'ready-wallet');
                                    $badge_class = 'debit';
                                    $amount_prefix = '-';
                                    break;
                                case 'cashback':
                                    $row_class = 'type-cashback';
                                    $icon = 'dashicons-awards';
                                    $label = __('پاداش', 'ready-wallet');
                                    $badge_class = 'cashback';
                                    $amount_prefix = '+';
                                    break;
                                case 'refund':
                                    $row_class = 'type-credit';
                                    $icon = 'dashicons-undo';
                                    $label = __('بازگشت وجه', 'ready-wallet');
                                    $badge_class = 'credit'; // سبز
                                    $amount_prefix = '+';
                                    break;
                                default:
                                    $icon = 'dashicons-marker';
                                    $label = $transaction->type;
                                    $badge_class = 'default';
                            }
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <!-- شناسه -->
                            <td class="rw-id" style="color: var(--rw-text-muted); font-family: sans-serif; font-size: 12px;">
                                <?php echo $transaction->id; ?>
                            </td>
                            
                            <!-- وضعیت -->
                            <td class="rw-status">
                                <span class="rw-badge <?php echo $badge_class; ?>" style="display: inline-flex; align-items: center; gap: 6px; width: fit-content;">
                                    <span class="dashicons <?php echo $icon; ?>" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            
                            <!-- مبلغ -->
                            <td class="rw-amount">
                                <span class="amount-val" style="font-weight: 700; font-size: 15px; direction: ltr; display: inline-block; font-family: sans-serif;">
                                    <?php echo $amount_prefix . ' ' . wc_price( $transaction->amount ); ?>
                                </span>
                            </td>
                            
                            <!-- تاریخ -->
                            <td class="rw-date">
                                <div style="display: flex; flex-direction: column; font-size: 12px; line-height: 1.4;">
                                    <span style="font-weight: 600; color: var(--rw-text-main);"><?php echo date_i18n( 'Y/m/d', strtotime( $transaction->date ) ); ?></span>
                                    <span style="color: var(--rw-text-muted);"><?php echo date_i18n( 'H:i', strtotime( $transaction->date ) ); ?></span>
                                </div>
                            </td>
                            
                            <!-- جزئیات -->
                            <td class="rw-details" style="max-width: 250px; font-size: 13px; line-height: 1.6; color: var(--rw-text-main);">
                                <?php echo wp_trim_words( $transaction->description, 12 ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <!-- 3. حالت خالی (Empty State) -->
                    <tr class="rw-empty-state">
                        <td colspan="5">
                            <div class="rw-empty-content" style="padding: 50px 20px; text-align: center;">
                                <div style="background: #f3f4f6; width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                                    <span class="dashicons dashicons-list-view" style="font-size: 40px; color: #d1d5db; width: 40px; height: 40px;"></span>
                                </div>
                                <h5 style="margin: 0 0 8px; color: var(--rw-text-main); font-size: 16px; font-weight: 700;"><?php _e( 'تراکنشی یافت نشد!', 'ready-wallet' ); ?></h5>
                                <p style="margin: 0; color: var(--rw-text-muted); font-size: 14px;"><?php _e( 'شما هنوز هیچ تراکنشی انجام نداده‌اید.', 'ready-wallet' ); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 4. کنترل‌های صفحه‌بندی (Pagination) -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="rw-pagination">
            <?php $current_url = remove_query_arg( 'rw_page' ); ?>
            
            <!-- دکمه قبلی -->
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rw_page', $current_page - 1, $current_url ) ); ?>" class="rw-page-btn prev">
                    <span class="dashicons dashicons-arrow-right-alt2"></span> <?php _e( 'قبلی', 'ready-wallet' ); ?>
                </a>
            <?php else: ?>
                <span class="rw-page-btn disabled"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php _e( 'قبلی', 'ready-wallet' ); ?></span>
            <?php endif; ?>

            <!-- شماره صفحات -->
            <div class="rw-page-numbers" style="display: flex; align-items: center; gap: 5px; font-size: 14px;">
                <span class="page-num active" style="font-weight: bold; color: var(--rw-primary);"><?php echo $current_page; ?></span>
                <span style="color: var(--rw-border);">/</span>
                <span class="page-num" style="color: var(--rw-text-muted);"><?php echo $total_pages; ?></span>
            </div>

            <!-- دکمه بعدی -->
            <?php if ( $current_page < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rw_page', $current_page + 1, $current_url ) ); ?>" class="rw-page-btn next">
                    <?php _e( 'بعدی', 'ready-wallet' ); ?> <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
            <?php else: ?>
                <span class="rw-page-btn disabled"><?php _e( 'بعدی', 'ready-wallet' ); ?> <span class="dashicons dashicons-arrow-left-alt2"></span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>