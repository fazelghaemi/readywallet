<?php
/**
 * The Template for displaying transaction list with Pagination.
 *
 * @package ReadyWallet/Templates
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

// دریافت متغیرهایی که از کلاس Shortcodes ارسال شده‌اند
$transactions = get_query_var( 'rw_transactions', [] );
$current_page = get_query_var( 'rw_current_page', 1 );
$total_pages  = get_query_var( 'rw_total_pages', 1 );
?>

<div class="woo-wallet-transactions-wrapper">
    <div class="rw-section-header">
        <h4 class="rw-section-title"><?php _e( 'تاریخچه تراکنش‌ها', 'ready-wallet' ); ?></h4>
        <span class="rw-meta-info"><?php printf( __( 'صفحه %d از %d', 'ready-wallet' ), $current_page, max(1, $total_pages) ); ?></span>
    </div>

    <div class="rw-table-responsive">
        <table class="woo-wallet-transactions">
            <thead>
                <tr>
                    <th><?php _e( 'شناسه', 'ready-wallet' ); ?></th>
                    <th><?php _e( 'نوع', 'ready-wallet' ); ?></th>
                    <th><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                    <th><?php _e( 'تاریخ', 'ready-wallet' ); ?></th>
                    <th class="rw-col-details"><?php _e( 'توضیحات', 'ready-wallet' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $transactions && count( $transactions ) > 0 ) : ?>
                    <?php foreach ( $transactions as $transaction ) : ?>
                        <?php 
                            // تعیین کلاس رنگی بر اساس نوع تراکنش
                            $row_class = '';
                            $icon = '';
                            switch($transaction->type) {
                                case 'credit': 
                                    $row_class = 'type-credit'; 
                                    $icon = '<span class="rw-badge credit">' . __('واریز', 'ready-wallet') . '</span>';
                                    break;
                                case 'debit': 
                                    $row_class = 'type-debit'; 
                                    $icon = '<span class="rw-badge debit">' . __('برداشت', 'ready-wallet') . '</span>';
                                    break;
                                case 'cashback':
                                    $row_class = 'type-cashback';
                                    $icon = '<span class="rw-badge cashback" style="background:#fff3cd; color:#856404;">' . __('پاداش', 'ready-wallet') . '</span>';
                                    break;
                                default:
                                    $icon = '<span class="rw-badge default">' . esc_html($transaction->type) . '</span>';
                            }
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td class="rw-id">#<?php echo $transaction->id; ?></td>
                            <td class="rw-status"><?php echo $icon; ?></td>
                            <td class="rw-amount">
                                <?php 
                                    $prefix = ($transaction->type == 'credit' || $transaction->type == 'cashback') ? '+' : '-';
                                    echo '<span class="amount-val">' . $prefix . ' ' . wc_price( $transaction->amount ) . '</span>';
                                ?>
                            </td>
                            <td class="rw-date">
                                <?php echo date_i18n( 'Y/m/d', strtotime( $transaction->date ) ); ?>
                                <small><?php echo date_i18n( 'H:i', strtotime( $transaction->date ) ); ?></small>
                            </td>
                            <td class="rw-details">
                                <?php echo wp_trim_words( $transaction->description, 10 ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="rw-empty-state">
                        <td colspan="5">
                            <div class="rw-empty-content">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e0e0e0" stroke-width="1"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                                <p><?php _e( 'هنوز تراکنشی انجام نشده است.', 'ready-wallet' ); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- کنترل‌های صفحه‌بندی -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="rw-pagination">
            <?php 
            // ساخت آدرس پایه
            $current_url = remove_query_arg( 'rw_page' ); 
            ?>
            
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rw_page', $current_page - 1, $current_url ) ); ?>" class="rw-page-btn prev">
                    &larr; <?php _e( 'قبلی', 'ready-wallet' ); ?>
                </a>
            <?php else: ?>
                <span class="rw-page-btn disabled">&larr; <?php _e( 'قبلی', 'ready-wallet' ); ?></span>
            <?php endif; ?>

            <div class="rw-page-numbers">
                <?php for($i = 1; $i <= min(5, $total_pages); $i++): ?>
                    <a href="<?php echo esc_url( add_query_arg( 'rw_page', $i, $current_url ) ); ?>" class="page-num <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if($total_pages > 5): ?><span>...</span><?php endif; ?>
            </div>

            <?php if ( $current_page < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'rw_page', $current_page + 1, $current_url ) ); ?>" class="rw-page-btn next">
                    <?php _e( 'بعدی', 'ready-wallet' ); ?> &rarr;
                </a>
            <?php else: ?>
                <span class="rw-page-btn disabled"><?php _e( 'بعدی', 'ready-wallet' ); ?> &rarr;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* استایل داخلی برای صفحه‌بندی */
.rw-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.rw-meta-info { font-size: 12px; color: #999; background: #eee; padding: 3px 8px; border-radius: 4px; }
.rw-pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
.rw-page-btn { padding: 8px 16px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #555; font-size: 13px; transition: all 0.2s; }
.rw-page-btn:hover:not(.disabled) { background: var(--rw-primary, #5D34F2); color: #fff; border-color: var(--rw-primary, #5D34F2); }
.rw-page-btn.disabled { opacity: 0.5; cursor: not-allowed; }
.rw-page-numbers .page-num { display: inline-block; padding: 5px 10px; margin: 0 2px; border-radius: 5px; text-decoration: none; color: #777; }
.rw-page-numbers .page-num.active { background: var(--rw-primary, #5D34F2); color: #fff; }
.rw-empty-content { text-align: center; padding: 30px; color: #aaa; }
.rw-table-responsive { overflow-x: auto; }
</style>