/* globals ws404Data, jQuery */
(function ($) {
    'use strict';

    // ── Find Match (single row) ───────────────────────────────────────────────
    $(document).on('click', '.ws404-find-btn', function () {
        var $btn = $(this);
        var id   = $btn.data('id');
        var url  = $btn.data('url');
        var $row = $('#ws404-row-' + id);

        $row.addClass('ws404-loading');
        $btn.prop('disabled', true).html('<span class="ws404-spinner"></span> Matching…');

        $.post(ws404Data.ajaxUrl, {
            action: 'ws404_find_match',
            nonce:  ws404Data.nonce,
            id:     id,
            url:    url,
        })
        .done(function (res) {
            if (!res.success) {
                alert('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                $btn.prop('disabled', false).html('🔍 Find Match');
                return;
            }

            var d = res.data;

            // Update suggestion cell.
            $row.find('.ws404-suggestion-cell').html(
                '<div class="ws404-suggestion">' +
                    '<a href="' + d.url + '" target="_blank" class="ws404-suggest-link">' + (d.title || d.url) + '</a>' +
                    '<span class="ws404-confidence ws404-conf-' + d.confidence + '">' + d.confidence + '</span>' +
                    '<span style="font-size:11px;color:#888;font-style:italic;">' + d.reason + '</span>' +
                '</div>'
            );

            // Update action cell — replace Find btn with Save + Rematch.
            $row.find('.ws404-action-cell').html(
                '<button class="button button-primary button-small ws404-save-btn"' +
                    ' data-id="' + id + '" data-from="' + url + '" data-to="' + d.url + '">' +
                    '↪ Save Redirect' +
                '</button>' +
                '<button class="button button-small ws404-find-btn" style="margin-top:4px;"' +
                    ' data-id="' + id + '" data-url="' + url + '">' +
                    '🔄 Re-match' +
                '</button>' +
                '<button class="button button-small ws404-delete-btn" style="margin-top:4px;color:#a00;"' +
                    ' data-id="' + id + '">🗑 Delete</button>'
            );
        })
        .fail(function () {
            alert('Network error. Please try again.');
            $btn.prop('disabled', false).html('🔍 Find Match');
        })
        .always(function () {
            $row.removeClass('ws404-loading');
        });
    });

    // ── Save Redirect (single row) ────────────────────────────────────────────
    $(document).on('click', '.ws404-save-btn', function () {
        var $btn  = $(this);
        var id    = $btn.data('id');
        var from  = $btn.data('from');
        var to    = $btn.data('to');
        var $row  = $('#ws404-row-' + id);

        $btn.prop('disabled', true).text('Saving…');

        $.post(ws404Data.ajaxUrl, {
            action:   'ws404_save_redirect',
            nonce:    ws404Data.nonce,
            id:       id,
            from_url: from,
            to_url:   to,
        })
        .done(function (res) {
            if (!res.success) {
                alert('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('↪ Save Redirect');
                return;
            }
            // Show saved state.
            $row.find('.ws404-suggestion-cell').html('<span class="ws404-badge ws404-badge-ok">✓ Redirected</span>');
            $row.find('.ws404-action-cell').html(
                '<button class="button button-small ws404-delete-btn" style="color:#a00;" data-id="' + id + '">🗑 Delete</button>'
            );
        })
        .fail(function () { alert('Network error.'); $btn.prop('disabled', false).text('↪ Save Redirect'); })
        .always(function () { $row.removeClass('ws404-loading'); });
    });

    // ── Delete log ────────────────────────────────────────────────────────────
    $(document).on('click', '.ws404-delete-btn', function () {
        if (!confirm('Delete this 404 log entry?')) return;

        var $btn = $(this);
        var id   = $btn.data('id');
        var $row = $('#ws404-row-' + id);

        $btn.prop('disabled', true);

        $.post(ws404Data.ajaxUrl, {
            action: 'ws404_delete_log',
            nonce:  ws404Data.nonce,
            id:     id,
        })
        .done(function (res) {
            if (res.success) {
                $row.fadeOut(300, function () { $row.remove(); });
            } else {
                alert('Error deleting.');
                $btn.prop('disabled', false);
            }
        })
        .fail(function () { alert('Network error.'); $btn.prop('disabled', false); });
    });

    // ── Auto-Match All ────────────────────────────────────────────────────────
    $('#ws404-auto-match-all').on('click', function () {
        var $btn    = $(this);
        var $status = $('#ws404-bulk-status');

        if (!confirm('This will use Claude AI to match all unmatched 404 URLs. Continue?')) return;

        $btn.prop('disabled', true).text('⏳ Matching…');
        $status.show().text('Sending to Claude AI — this may take 10–30 seconds…');

        $.post(ws404Data.ajaxUrl, {
            action: 'ws404_auto_match_all',
            nonce:  ws404Data.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $status.text('✓ ' + res.data.message + ' Refresh to see results.');
                $btn.text('✨ Done — Refresh Page');
                $btn.off('click').on('click', function () { location.reload(); });
                $btn.prop('disabled', false);
            } else {
                $status.text('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('✨ Auto-Match All');
            }
        })
        .fail(function () {
            $status.text('Network error. Please try again.');
            $btn.prop('disabled', false).text('✨ Auto-Match All');
        });
    });

}(jQuery));
