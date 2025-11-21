/**
 * ReadyWallet Frontend Scripts
 * مدیریت تعاملات کاربری (تب‌ها، اعتبارسنجی فرم‌ها و ...)
 */

jQuery(document).ready(function($) {
    
    // --- مدیریت تب‌ها (Tabs Logic) ---
    $('.rw-tabs-nav li').on('click', function() {
        var tab_id = $(this).attr('data-tab');

        // حذف کلاس فعال از همه تب‌ها و محتواها
        $('.rw-tabs-nav li').removeClass('active');
        $('.rw-tab-content').removeClass('active');

        // افزودن کلاس فعال به تب کلیک شده
        $(this).addClass('active');
        
        // نمایش محتوای مربوطه با افکت
        $("#" + tab_id).addClass('active');
    });

    // --- اعتبارسنجی ساده فرم‌ها قبل از ارسال ---
    
    // فرم انتقال وجه
    $('#woo_wallet_transfer_form').on('submit', function(e) {
        var recipient = $('#rw_transfer_recipient').val();
        var amount = $('#rw_transfer_amount').val();

        if (!recipient || !amount) {
            e.preventDefault();
            alert('لطفاً تمام فیلدهای ضروری را پر کنید.');
            return false;
        }
        
        if (parseFloat(amount) <= 0) {
            e.preventDefault();
            alert('مبلغ انتقال باید بیشتر از صفر باشد.');
            return false;
        }

        // تاییدیه نهایی از کاربر
        if (!confirm('آیا از انتقال این مبلغ اطمینان دارید؟ عملیات غیرقابل بازگشت است.')) {
            e.preventDefault();
            return false;
        }
    });

    // فرم برداشت وجه
    $('#woo_wallet_withdraw_form').on('submit', function(e) {
        var amount = $('#woo_wallet_withdraw_amount').val();
        if (parseFloat(amount) < 50000) { // حداقل برداشت فرضی
            // نکته: این مقدار بهتر است از ready_wallet_params خوانده شود
        }
    });

    // --- حل مشکل پرش صفحه هنگام کلیک روی تب‌ها در برخی قالب‌ها ---
    $('.rw-tabs-nav li').on('mousedown', function(e){
        e.preventDefault();
    });

});