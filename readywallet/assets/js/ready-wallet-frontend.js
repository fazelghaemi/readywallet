/**
 * ReadyWallet Pro Frontend Scripts
 * * مدیریت تعاملات کاربری پیشرفته (UX):
 * - تب‌بندی با پشتیبانی از History API
 * - فرمت‌دهی پول (3 رقم 3 رقم)
 * - اعتبارسنجی سمت کلاینت
 * - مدیریت حالت‌های Loading
 * * @version 2.9.0
 * @author Ready Studio
 */

jQuery(document).ready(function($) {
    
    const ReadyWallet = {
        
        init: function() {
            this.initTabs();
            this.initCurrencyFormatter();
            this.initFormValidation();
            this.handleNotices();
        },

        /**
         * 1. مدیریت تب‌ها با قابلیت Deep Linking
         */
        initTabs: function() {
            const $tabs = $('.rw-tabs-nav li');
            const $contents = $('.rw-tab-content');

            // تابع تغییر تب
            const switchTab = (tabId) => {
                if (!tabId || !$(`#${tabId}`).length) return;

                // آپدیت کلاس‌ها
                $tabs.removeClass('active');
                $contents.removeClass('active');

                $(`[data-tab="${tabId}"]`).addClass('active');
                $(`#${tabId}`).addClass('active');

                // آپدیت URL بدون رفرش
                if (history.pushState) {
                    history.pushState(null, null, '#' + tabId);
                } else {
                    location.hash = tabId;
                }
            };

            // رویداد کلیک روی تب‌ها
            $tabs.on('click', function(e) {
                e.preventDefault();
                const tabId = $(this).data('tab');
                switchTab(tabId);
            });

            // بررسی Hash در آدرس بار هنگام لود (مثلاً #deposit)
            const currentHash = window.location.hash.replace('#', '');
            if (currentHash) {
                switchTab(currentHash);
            }
        },

        /**
         * 2. فرمت‌دهی مبالغ (Separators)
         * تبدیل 100000 به 100,000 هنگام تایپ
         */
        initCurrencyFormatter: function() {
            // انتخاب تمام اینپوت‌های مبلغ
            const $amountInputs = $('input[name*="amount"]');

            $amountInputs.on('input', function() {
                let val = $(this).val();
                
                // حذف تمام کاراکترهای غیر عددی (به جز نقطه برای اعشار)
                val = val.replace(/[^\d.]/g, '');
                
                // جلوگیری از ورود بیش از یک نقطه
                if ((val.match(/\./g) || []).length > 1) {
                    val = val.replace(/\.+$/, "");
                }

                // فرمت‌دهی 3 رقم 3 رقم
                if (val) {
                    const parts = val.split('.');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    $(this).val(parts.join('.'));
                }
            });

            // نکته مهم: قبل از ارسال فرم، باید کاماها حذف شوند
            $('form').on('submit', function() {
                const $form = $(this);
                const $inputs = $form.find('input[name*="amount"]');
                
                $inputs.each(function() {
                    let rawValue = $(this).val().replace(/,/g, '');
                    $(this).val(rawValue);
                });
            });
        },

        /**
         * 3. اعتبارسنجی فرم‌ها و مدیریت دکمه‌ها
         */
        initFormValidation: function() {
            
            // فرم انتقال وجه (Transfer)
            $('#woo_wallet_transfer_form').on('submit', function(e) {
                const $form = $(this);
                const recipient = $('#rw_transfer_recipient').val().trim();
                const amountVal = $('#rw_transfer_amount').val().replace(/,/g, ''); // حذف کاما برای بررسی
                const amount = parseFloat(amountVal);

                // بررسی خالی بودن
                if (!recipient || !amount) {
                    e.preventDefault();
                    ReadyWallet.showNotice('error', ready_wallet_params.error_empty || 'لطفاً تمام فیلدها را پر کنید.');
                    // برگرداندن فرمت پول (چون فرم ارسال نشده)
                    ReadyWallet.reformatCurrency($form);
                    return false;
                }

                // بررسی صفر بودن
                if (amount <= 0) {
                    e.preventDefault();
                    ReadyWallet.showNotice('error', 'مبلغ باید بیشتر از صفر باشد.');
                    ReadyWallet.reformatCurrency($form);
                    return false;
                }

                // تاییدیه نهایی
                if (!confirm(ready_wallet_params.confirm_transfer || 'آیا از انتقال وجه اطمینان دارید؟ این عملیات غیرقابل بازگشت است.')) {
                    e.preventDefault();
                    ReadyWallet.reformatCurrency($form);
                    return false;
                }

                // حالت Loading
                ReadyWallet.setLoading($form);
            });

            // فرم‌های شارژ و برداشت
            $('#woo_wallet_deposit_form, #woo_wallet_withdraw_form').on('submit', function(e) {
                const $form = $(this);
                const amountVal = $form.find('input[name*="amount"]').val().replace(/,/g, '');
                
                if (!amountVal || parseFloat(amountVal) <= 0) {
                    e.preventDefault();
                    ReadyWallet.showNotice('error', 'لطفاً مبلغ معتبری وارد کنید.');
                    ReadyWallet.reformatCurrency($form);
                    return false;
                }

                ReadyWallet.setLoading($form);
            });
        },

        /**
         * ابزار کمکی: فعال‌سازی حالت لودینگ روی دکمه
         */
        setLoading: function($form) {
            const $btn = $form.find('button[type="submit"]');
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).addClass('rw-loading');
            $btn.html('<span class="dashicons dashicons-update spin"></span> در حال پردازش...');
            
            // اگر فرم بعد از مدتی ارسال نشد (مثلا خطای سرور)، دکمه را برگردان (timeout حفاظتی)
            setTimeout(() => {
                if($btn.prop('disabled')) {
                    $btn.prop('disabled', false).removeClass('rw-loading').text(originalText);
                }
            }, 10000);
        },

        /**
         * ابزار کمکی: نمایش نوتیفیکیشن موقت (JS)
         */
        showNotice: function(type, message) {
            // حذف نوتیفیکیشن‌های قبلی
            $('.rw-js-notice').remove();

            const colorClass = type === 'error' ? 'woocommerce-error' : 'woocommerce-message';
            const html = `
                <div class="rw-notices-wrapper rw-js-notice">
                    <div class="${colorClass}" role="alert">
                        ${message}
                    </div>
                </div>
            `;
            
            // درج در بالای کانتینر
            $('.ready-wallet-container').prepend(html);

            // اسکرول به بالا
            $('html, body').animate({
                scrollTop: $(".ready-wallet-container").offset().top - 100
            }, 500);
        },

        /**
         * ابزار کمکی: برگرداندن فرمت پول در صورت لغو ارسال
         */
        reformatCurrency: function($form) {
            const $input = $form.find('input[name*="amount"]');
            let val = $input.val();
            if (val && val.indexOf(',') === -1) {
                val = val.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                $input.val(val);
            }
        },

        /**
         * پنهان کردن نوتیفیکیشن‌ها بعد از چند ثانیه
         */
        handleNotices: function() {
            setTimeout(function() {
                $('.rw-notices-wrapper').fadeOut(500);
            }, 8000);
        }
    };

    // راه‌اندازی
    ReadyWallet.init();

});