/* DEPRAT — ordre des slides : glisser-déposer et sauvegarde sécurisée. */
(function ($) {
    'use strict';

    $(function () {
        var cfg = window.depratSlideOrderConfig;
        var $table = $('.wp-list-table');
        var $rows = $table.find('tbody#the-list');
        if (!cfg || !$rows.length || !$.fn.sortable) { return; }

        // Ne pas modifier l'ordre avec un sous-ensemble filtré/recherché.
        var query = new URLSearchParams(window.location.search);
        var status = query.get('post_status');
        var filtered = !!query.get('s') || !!query.get('m') || !!query.get('orderby')
            || !!query.get('paged') && query.get('paged') !== '1'
            || !!status && status !== 'all';
        var $pages = $('.tablenav-pages .total-pages').first();
        if ($pages.length && Number($pages.text()) > 1) { filtered = true; }
        if (filtered) { return; }

        function status(message, error) {
            var $notice = $('.deprat-slide-sort-status');
            if (!$notice.length) {
                $notice = $('<div>', {'class': 'deprat-slide-sort-status', 'role': 'status', 'aria-live': 'polite'});
                $table.before($notice);
            }
            $notice.text(message);
            $notice.css({
                backgroundColor: error ? '#fff2f2' : '#eff8ee',
                borderColor: error ? '#e6b4b4' : '#c8dfc2',
                borderLeftColor: error ? '#b22c2c' : '#238149',
                color: error ? '#8c2828' : '#1b623b'
            });
        }

        $rows.sortable({
            items: '> tr[id^="post-"]',
            handle: '.deprat-slide-sort-handle',
            axis: 'y',
            cursor: 'grabbing',
            tolerance: 'pointer',
            placeholder: 'deprat-slide-sort-placeholder',
            helper: function (event, row) {
                var $cells = row.children();
                var $clone = row.clone();
                $clone.children().each(function (index) {
                    $(this).width($cells.eq(index).outerWidth());
                });
                return $clone;
            },
            start: function (event, ui) { ui.item.addClass('deprat-slide-row-dragging'); },
            stop: function (event, ui) { ui.item.removeClass('deprat-slide-row-dragging'); },
            update: function () {
                var $items = $rows.children('tr[id^="post-"]');
                var ids = $items.map(function () {
                    return Number(this.id.replace(/^post-/, ''));
                }).get();
                if (!ids.length || ids.some(function (id) { return !Number.isInteger(id) || id <= 0; })) {
                    status('Impossible de sauvegarder cet ordre.', true);
                    return;
                }
                $rows.sortable('disable');
                status('Enregistrement du nouvel ordre…', false);
                $.ajax({
                    url: cfg.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'deprat_home_slide_reorder',
                        nonce: cfg.nonce,
                        ids: ids
                    }
                }).done(function (data) {
                    if (!data || !data.success) {
                        status((data && data.data && data.data.message) || 'Enregistrement impossible. Rechargez la page.', true);
                        return;
                    }
                    $items.each(function (index) {
                        $(this).find('.deprat-slide-order-number').text(index + 1);
                    });
                    status('Ordre enregistré. Le diaporama de l’accueil suit ce nouvel ordre.', false);
                }).fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                    status(message || 'Erreur de sauvegarde. Rechargez la page avant de réessayer.', true);
                }).always(function () {
                    $rows.sortable('enable');
                });
            }
        });
    });
}(jQuery));
