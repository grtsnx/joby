jQuery(document).ready(function($) {
    
    // Toast helper
    function showToast(message, type = 'success') {
        const toast = $('<div class="ajs-toast ' + type + '">' + message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.addClass('show'), 100);
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Dynamic Provider Fields
    $('#ajs_provider_select').on('change', function() {
        const selected = $(this).val();
        $('.provider-field').hide();
        $('.provider-' + selected).fadeIn();
        
        // Show a helpful tip for Arbeitnow
        if (selected === 'arbeitnow') {
            showToast('Arbeitnow selected: Public API (no keys needed)', 'success');
        }
    });

    // Start Manual Sync
    $('#ajs-trigger-sync').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text('Syncing...');

        $.post(ajs_vars.ajax_url, {
            action: 'ajs_trigger_sync',
            nonce: ajs_vars.nonce
        }, function(response) {
            if (response.success) {
                showToast('Sync process started in background!', 'success');
                location.reload(); // Reload to show progress bar
            } else {
                showToast('Error: ' + response.data, 'error');
                $btn.prop('disabled', false).text('Start Manual Sync');
            }
        });
    });

    // Cancel Sync
    $('#ajs-cancel-sync').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to stop the current sync?')) return;
        
        $.post(ajs_vars.ajax_url, {
            action: 'ajs_cancel_sync',
            nonce: ajs_vars.nonce
        }, function(response) {
            if (response.success) {
                showToast('Sync cancelled.', 'error');
                location.reload();
            }
        });
    });

    // Check for Updates Manual Trigger
    $('#ajs-check-updates').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Checking...');

        $.post(ajs_vars.ajax_url, {
            action: 'ajs_check_updates',
            nonce: ajs_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            if (response.success) {
                showToast(response.data, 'success');
                // Optional: redirect to plugins page after a delay
                setTimeout(() => {
                    window.location.href = window.location.href; // Refresh to show any new status
                }, 2000);
            } else {
                showToast('Error: ' + response.data, 'error');
            }
        });
    });

    // Dynamic Row Handling for Countries
    $('#ajs-add-country').on('click', function() {
        const index = $('#ajs-countries-table tbody tr').not('.empty-row').length;
        const template = $('#ajs-row-template').html().replace(/{{index}}/g, index);
        
        $('#ajs-countries-table tbody').find('.empty-row').remove();
        $('#ajs-countries-table tbody').append(template);
    });

    $('#ajs-countries-table').on('click', '.ajs-remove-row', function() {
        $(this).closest('tr').remove();
        if ($('#ajs-countries-table tbody tr').length === 0) {
            $('#ajs-countries-table tbody').append('<tr class="empty-row"><td colspan="3">Click "Add Location" to get started.</td></tr>');
        }
    });

    // Progress bar animation if in progress
    if ($('.ajs-progress-bar').length > 0) {
        let lastQueue = -1;
        setInterval(function() {
            // In a real app, we might poll an endpoint, 
            // but for now, we'll just simulate progress based on queue count
            // Or just reload the page occasionally.
        }, 5000);
    }

    // Show toast if settings just saved
    if (window.location.search.indexOf('settings-updated=true') > -1) {
        showToast('Settings saved successfully!');
    }
});
