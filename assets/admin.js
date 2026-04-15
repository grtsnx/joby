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

    // Clear Cache
    $('#ajs-clear-cache').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text('Clearing...');

        $.post(ajs_vars.ajax_url, {
            action: 'ajs_clear_cache',
            nonce: ajs_vars.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Clear Cache');
            if (response.success) {
                showToast(response.data, 'success');
                setTimeout(() => location.reload(), 1500);
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

    // Real-time Logs Polling
    let logInterval = null;

    function pollLogs() {
        $.post(ajs_vars.ajax_url, {
            action: 'ajs_get_logs',
            nonce: ajs_vars.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                updateLogConsole(data.logs);
                updateProgress(data.queue);
                
                if (data.status === 'in_progress') {
                    if (!logInterval) logInterval = setInterval(pollLogs, 3000);
                } else {
                    clearInterval(logInterval);
                    logInterval = null;
                    if (data.status === 'completed' || data.status === 'error') {
                        setTimeout(() => location.reload(), 2000);
                    }
                }
            }
        });
    }

    function updateLogConsole(logs) {
        const $console = $('#ajs-log-console');
        if (!logs || logs.length === 0) return;
        
        let html = '';
        logs.forEach(log => {
            html += `<div class="ajs-log-entry"><span class="ajs-log-time">[${log.time}]</span> ${log.msg}</div>`;
        });
        $console.html(html);
        $console.scrollTop($console[0].scrollHeight);
    }

    function updateProgress(queueCount) {
        $('.tasks-count strong').text(queueCount);
        // Progress estimation
        const progress = Math.min(100, Math.max(10, 100 - (queueCount * 2)));
        $('.ajs-progress-bar').css('width', progress + '%');
    }

    $('#ajs-toggle-logs').on('click', function() {
        const $console = $('#ajs-log-console');
        if ($console.is(':visible')) {
            $console.slideUp();
            $(this).text('Show Logs Console');
        } else {
            $console.slideDown();
            $(this).text('Hide Logs Console');
            pollLogs();
        }
    });

    // Start polling immediately if sync is in progress
    if ($('.status-card').hasClass('in_progress')) {
        $('#ajs-log-console').show();
        $('#ajs-toggle-logs').text('Hide Logs Console');
        pollLogs();
    }

    // Show toast if settings just saved
    if (window.location.search.indexOf('settings-updated=true') > -1) {
        showToast('Settings saved successfully!');
    }
});
