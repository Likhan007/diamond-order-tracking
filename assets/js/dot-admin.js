jQuery(function ($) {

    // Find the save button on the order page
    const saveBtn = $('button[name="dot_save_nonce"], button.dot-save-stages, button.save-stages, button[type="submit"].btn');

    if (saveBtn.length === 0) {
        return; // Not on order page → exit
    }

    // Create floating toast
    const toast = $('<div id="dot-toast" style="position:fixed;right:20px;bottom:20px;padding:12px 20px;background:#2a9d8f;color:#fff;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);opacity:0;pointer-events:none;transition:all .3s;z-index:99999;">Saved</div>');
    $('body').append(toast);

    function showToast(msg, ok = true) {
        toast.text(msg);
        toast.css({
            background: ok ? '#2a9d8f' : '#b00020'
        });
        toast.css({ opacity: 1 });

        setTimeout(() => {
            toast.css({ opacity: 0 });
        }, 1800);
    }

    // Intercept save click
    saveBtn.on('click', function (e) {
        // If this is the primary form submit button (regular form), still intercept to do AJAX for stages
        e.preventDefault();

        const btn = $(this);
        btn.prop('disabled', true).css({ opacity: 0.6 });

        // Collect all form fields
        const form = btn.closest('form');
        if (!form.length) {
            btn.prop('disabled', false).css({ opacity: 1 });
            return;
        }

        const formData = new FormData(form[0]);

        formData.append('action', 'dot_update_stage_bulk');
        if (typeof dot_ajax !== 'undefined' && dot_ajax.nonce) {
            formData.append('nonce', dot_ajax.nonce);
        }

        $.ajax({
            url: (typeof dot_ajax !== 'undefined' && dot_ajax.url) ? dot_ajax.url : ajaxurl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            success: function (res) {
                if (res && res.success) {
                    showToast("Saved");
                    // optionally reload or update timestamp on page
                    // location.reload(); // avoid auto reload by default
                } else {
                    showToast("Save failed", false);
                }
            },

            error: function () {
                showToast("Network error", false);
            },

            complete: function () {
                btn.prop('disabled', false).css({ opacity: 1 });
            }
        });

    });

});
