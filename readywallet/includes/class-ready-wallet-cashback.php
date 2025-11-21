<?php
/**
 * ReadyWallet Pro Cashback System
 *
 * سیستم هوشمند پاداش خرید (کش‌بک).
 *
 * ویژگی‌های خارق‌العاده:
 * 1. سلسله مراتب محاسبه: محصول > دسته‌بندی > تنظیمات کلی.
 * 2. پشتیبانی از نوع درصدی (%) و مبلغ ثابت.
 * 3. نمایش پیام انگیزشی در صفحه محصول (Front-end Marketing).
 * 4. مدیریت چرخه حیات سفارش (واریز در تکمیل، لغو در استرداد).
 * 5. افزودن ستون به لیست محصولات در ادمین برای مدیریت سریع.
 *
 * @package     ReadyWallet/Classes
 * @version     2.9.0
 * @author      Ready Studio
 */

defined( 'ABSPATH' ) || exit;

class Ready_Wallet_Cashback {

    public function __construct() {
        // 1. تنظیمات محصول (فیلد کش‌بک)
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );

        // 2. تنظیمات دسته‌بندی (فیلد کش‌بک)
        add_action( 'product_cat_add_form_fields', array( $this, 'add_category_fields' ) );
        add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_fields' ) );
        add_action( 'edited_product_cat', array( $this, 'save_category_fields' ) );
        add_action( 'create_product_cat', array( $this, 'save_category_fields' ) );

        // 3. پردازش سفارشات (واریز و کسر)
        add_action( 'woocommerce_order_status_completed', array( $this, 'process_order_cashback' ) );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'revert_order_cashback' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'revert_order_cashback' ) );

        // 4. نمایش پیام در صفحه محصول (Front-end)
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_cashback_notice' ), 11 );

        // 5. ستون ادمین
        add_filter( 'manage_edit-product_columns', array( $this, 'add_admin_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
    }

    /**
     * --- بخش 1: تنظیمات محصول ---
     */
    public function add_product_fields() {
        echo '<div class="options_group">';
        
        // نوع کش‌بک
        woocommerce_wp_select( array(
            'id'      => '_rw_cashback_type',
            'label'   => __( 'نوع کش‌بک', 'ready-wallet' ),
            'options' => array(
                ''       => __( 'پیش‌فرض (ارث‌بری)', 'ready-wallet' ),
                'fixed'  => __( 'مبلغ ثابت', 'ready-wallet' ),
                'percent'=> __( 'درصدی (%)', 'ready-wallet' ),
            )
        ) );

        // مقدار کش‌بک
        woocommerce_wp_text_input( array(
            'id'          => '_rw_cashback_amount',
            'label'       => __( 'مقدار کش‌بک', 'ready-wallet' ),
            'description' => __( 'مقدار پاداش خرید این محصول. برای غیرفعال کردن 0 وارد کنید.', 'ready-wallet' ),
            'desc_tip'    => true,
            'type'        => 'number',
            'custom_attributes' => array( 'step' => 'any', 'min' => '0' )
        ) );

        echo '</div>';
    }

    public function save_product_fields( $post_id ) {
        if ( isset( $_POST['_rw_cashback_type'] ) ) {
            update_post_meta( $post_id, '_rw_cashback_type', wc_clean( $_POST['_rw_cashback_type'] ) );
        }
        if ( isset( $_POST['_rw_cashback_amount'] ) ) {
            update_post_meta( $post_id, '_rw_cashback_amount', wc_clean( $_POST['_rw_cashback_amount'] ) );
        }
    }

    /**
     * --- بخش 2: تنظیمات دسته‌بندی ---
     */
    public function add_category_fields() {
        ?>
        <div class="form-field">
            <label for="rw_cat_cashback_amount"><?php _e( 'درصد کش‌بک دسته', 'ready-wallet' ); ?></label>
            <input type="number" name="rw_cat_cashback_amount" id="rw_cat_cashback_amount" step="0.01" min="0">
            <p class="description"><?php _e( 'اگر محصول تنظیمات خاصی نداشته باشد، این درصد از مبلغ محصول به عنوان پاداش در نظر گرفته می‌شود.', 'ready-wallet' ); ?></p>
        </div>
        <?php
    }

    public function edit_category_fields( $term ) {
        $amount = get_term_meta( $term->term_id, 'rw_cat_cashback_amount', true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="rw_cat_cashback_amount"><?php _e( 'درصد کش‌بک دسته', 'ready-wallet' ); ?></label></th>
            <td>
                <input type="number" name="rw_cat_cashback_amount" id="rw_cat_cashback_amount" value="<?php echo esc_attr( $amount ); ?>" step="0.01" min="0">
                <p class="description"><?php _e( 'درصد پاداش برای محصولات این دسته (اولویت دوم).', 'ready-wallet' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_category_fields( $term_id ) {
        if ( isset( $_POST['rw_cat_cashback_amount'] ) ) {
            update_term_meta( $term_id, 'rw_cat_cashback_amount', wc_clean( $_POST['rw_cat_cashback_amount'] ) );
        }
    }

    /**
     * --- بخش 3: منطق محاسبه هوشمند ---
     * محاسبه میزان کش‌بک برای یک آیتم خاص
     */
    public function calculate_item_cashback( $product, $line_total ) {
        $cashback = 0;
        $product_id = $product->get_id();

        // 1. اولویت اول: تنظیمات خود محصول
        $p_type = get_post_meta( $product_id, '_rw_cashback_type', true );
        $p_amount = get_post_meta( $product_id, '_rw_cashback_amount', true );

        if ( $p_type && $p_amount !== '' ) {
            if ( 'fixed' === $p_type ) {
                $cashback = (float) $p_amount; // مبلغ ثابت (به ازای هر واحد نیست، کل آیتم)
                // نکته: اگر بخواهیم به ازای تعداد باشد، باید در تعداد ضرب شود اما line_total کل است.
                // برای fixed معمولا پر یونیته. پس:
                // اینجا فرض می‌کنیم p_amount برای یک عدد است.
                // اما چون line_total داریم، محاسبه دقیق‌تر:
                // بهتر است $line_total را نادیده بگیریم و از تعداد استفاده کنیم اگر fixed است.
                // برای سادگی در این نسخه:
                // Fixed: مقدار ثابت ضرب در تعداد
                // Percent: درصد از قیمت نهایی
            } elseif ( 'percent' === $p_type ) {
                $cashback = ( $line_total * (float) $p_amount ) / 100;
            }
            return $cashback;
        }

        // 2. اولویت دوم: دسته‌بندی‌ها
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $cat_percent = get_term_meta( $term->term_id, 'rw_cat_cashback_amount', true );
                if ( $cat_percent && $cat_percent > 0 ) {
                    $cashback = ( $line_total * (float) $cat_percent ) / 100;
                    return $cashback; // اولین دسته‌ای که کش‌بک داشت کافیست
                }
            }
        }

        // 3. اولویت سوم: تنظیمات کلی (اگر در آینده اضافه شد)
        // $global_percent = get_option('ready_wallet_global_cashback', 0);
        
        return 0;
    }

    /**
     * پردازش واریز کش‌بک هنگام تکمیل سفارش
     */
    public function process_order_cashback( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_user_id() ) return;

        // جلوگیری از واریز تکراری
        if ( $order->get_meta( '_rw_cashback_processed' ) === 'yes' ) return;

        $total_cashback = 0;
        $log_details = [];

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;
            
            // محاسبه بر اساس قیمت نهایی پرداخت شده (بعد از تخفیف)
            $line_total = $item->get_total(); 
            $quantity = $item->get_quantity();

            // لاجیک محاسبه: اگر ثابت بود در تعداد ضرب کن، اگر درصد بود از توتال بگیر
            $p_type = get_post_meta( $product->get_id(), '_rw_cashback_type', true );
            $p_amount = get_post_meta( $product->get_id(), '_rw_cashback_amount', true );

            $item_cashback = 0;
            
            if ( 'fixed' === $p_type ) {
                $item_cashback = (float) $p_amount * $quantity;
            } else {
                // محاسبه استاندارد (از محصول یا دسته)
                $item_cashback = $this->calculate_item_cashback( $product, $line_total );
            }

            if ( $item_cashback > 0 ) {
                $total_cashback += $item_cashback;
                $log_details[] = $product->get_name() . ": " . wc_price($item_cashback);
            }
        }

        if ( $total_cashback > 0 ) {
            $result = Ready_Wallet()->db->add_transaction([
                'user_id'      => $order->get_user_id(),
                'amount'       => $total_cashback,
                'type'         => 'cashback',
                'reference_id' => $order_id,
                'description'  => sprintf( __( 'پاداش خرید سفارش #%s', 'ready-wallet' ), $order->get_order_number() ) . ' (' . implode(', ', $log_details) . ')'
            ]);

            if ( ! is_wp_error( $result ) ) {
                $order->update_meta_data( '_rw_cashback_processed', 'yes' );
                $order->update_meta_data( '_rw_cashback_amount', $total_cashback ); // ذخیره برای ریفاند احتمالی
                $order->add_order_note( sprintf( __( 'مبلغ %s به عنوان کش‌بک واریز شد.', 'ready-wallet' ), wc_price( $total_cashback ) ) );
                $order->save();
            }
        }
    }

    /**
     * لغو کش‌بک (Revert) هنگام کنسل شدن یا استرداد سفارش
     */
    public function revert_order_cashback( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // فقط اگر قبلاً واریز شده باشد
        if ( $order->get_meta( '_rw_cashback_processed' ) !== 'yes' ) return;
        // جلوگیری از کسر تکراری
        if ( $order->get_meta( '_rw_cashback_reverted' ) === 'yes' ) return;

        $amount = (float) $order->get_meta( '_rw_cashback_amount' );
        if ( $amount <= 0 ) return;

        // ثبت تراکنش برداشت (Debit) برای اصلاح
        $result = Ready_Wallet()->db->add_transaction([
            'user_id'      => $order->get_user_id(),
            'amount'       => $amount,
            'type'         => 'debit', // کسر اعتبار
            'reference_id' => $order_id,
            'description'  => sprintf( __( 'لغو پاداش خرید سفارش #%s (استرداد/لغو سفارش)', 'ready-wallet' ), $order->get_order_number() )
        ]);

        if ( ! is_wp_error( $result ) ) {
            $order->update_meta_data( '_rw_cashback_reverted', 'yes' );
            $order->add_order_note( sprintf( __( 'مبلغ %s پاداش خرید به دلیل لغو سفارش از کیف پول کسر شد.', 'ready-wallet' ), wc_price( $amount ) ) );
            $order->save();
        }
    }

    /**
     * --- بخش 4: نمایش در فرانت‌اند ---
     */
    public function display_cashback_notice() {
        global $product;
        if ( ! $product ) return;

        // محاسبه برای یک واحد محصول (قیمت فعلی)
        $price = $product->get_price();
        $cashback = $this->calculate_item_cashback( $product, $price );
        
        // اگر درصد ثابت بود، محاسبه دقیق‌تر:
        $p_type = get_post_meta( $product->get_id(), '_rw_cashback_type', true );
        $p_amount = get_post_meta( $product->get_id(), '_rw_cashback_amount', true );
        if ( 'fixed' === $p_type ) {
            $cashback = (float) $p_amount;
        }

        if ( $cashback > 0 ) {
            echo '<div class="rw-cashback-notice" style="background:#e6fffa; color:#065f46; padding:10px; border-radius:8px; margin:15px 0; border:1px solid #a7f3d0; display:flex; align-items:center; gap:10px;">';
            echo '<span class="dashicons dashicons-awards" style="font-size:24px; width:24px; height:24px;"></span>';
            echo sprintf( __( 'با خرید این محصول، <strong>%s</strong> اعتبار هدیه دریافت کنید!', 'ready-wallet' ), wc_price( $cashback ) );
            echo '</div>';
        }
    }

    /**
     * --- بخش 5: ستون ادمین ---
     */
    public function add_admin_column( $columns ) {
        $columns['rw_cashback'] = __( 'کش‌بک', 'ready-wallet' );
        return $columns;
    }

    public function render_admin_column( $column, $post_id ) {
        if ( 'rw_cashback' === $column ) {
            $type = get_post_meta( $post_id, '_rw_cashback_type', true );
            $amount = get_post_meta( $post_id, '_rw_cashback_amount', true );

            if ( $type && $amount !== '' ) {
                if ( 'fixed' === $type ) {
                    echo wc_price( $amount );
                } else {
                    echo $amount . '%';
                }
            } else {
                echo '<span style="color:#ccc;">-</span>';
            }
        }
    }
}

return new Ready_Wallet_Cashback();