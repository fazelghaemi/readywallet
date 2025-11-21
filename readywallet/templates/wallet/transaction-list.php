<?php
/**
 * The Template for displaying transaction list.
 *
 * @package ReadyWallet/Templates
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woo-wallet-transactions-wrapper">
    <h4 class="rw-section-title"><?php _e( 'تراکنش‌های اخیر', 'ready-wallet' ); ?></h4>

    <table class="woo-wallet-transactions">
        <thead>
            <tr>
                <th><?php _e( 'شناسه', 'ready-wallet' ); ?></th>
                <th><?php _e( 'نوع تراکنش', 'ready-wallet' ); ?></th>
                <th><?php _e( 'مبلغ', 'ready-wallet' ); ?></th>
                <th><?php _e( 'تاریخ', 'ready-wallet' ); ?></th>
                <th><?php _e( 'جزئیات', 'ready-wallet' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $transactions && count( $transactions ) > 0 ) : ?>
                <?php foreach ( $transactions as $transaction ) : ?>
                    <tr class="<?php echo esc_attr( $transaction->type ); ?>">
                        <td>#<?php echo $transaction->transaction_id; ?></td>
                        <td>
                            <?php 
                            if($transaction->type == 'credit') {
                                echo '<span class="rw-badge credit">' . __('واریز', 'ready-wallet') . '</span>';
                            } else {
                                echo '<span class="rw-badge debit">' . __('برداشت', 'ready-wallet') . '</span>';
                            }
                            ?>
                        </td>
                        <td class="amount">
                            <?php echo wc_price( $transaction->amount ); ?>
                        </td>
                        <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $transaction->date ) ); ?></td>
                        <td class="description"><?php echo $transaction->details; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5" class="rw-no-transaction">
                        <?php _e( 'هنوز تراکنشی انجام نشده است.', 'ready-wallet' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>