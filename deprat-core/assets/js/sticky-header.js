/* DEPRAT 0.1.61 — Toute la structure Astra est fixée pendant le scroll.
 * Un espace réservé évite que le contenu remonte derrière le header.
 */
(function () {
    'use strict';

    var header = document.getElementById('masthead');
    if (!header || !document.body) { return; }

    var body = document.body;
    var spacer = document.createElement('div');
    spacer.className = 'deprat-sticky-header-spacer';
    spacer.setAttribute('aria-hidden', 'true');
    header.parentNode.insertBefore(spacer, header);

    var raf = 0;
    function measure() {
        raf = 0;
        // offsetHeight ne tient pas compte des marges, mais Astra place les trois
        // bandeaux dans le même #masthead : la hauteur est donc complète.
        var height = header.getBoundingClientRect().height;
        spacer.style.height = Math.max(0, Math.ceil(height)) + 'px';
        var adminbar = document.getElementById('wpadminbar');
        var top = adminbar && getComputedStyle(adminbar).position === 'fixed'
            ? adminbar.getBoundingClientRect().height : 0;
        body.style.setProperty('--deprat-sticky-top', top + 'px');
    }
    function scheduleMeasure() {
        if (raf) { return; }
        raf = requestAnimationFrame(measure);
    }
    body.classList.add('deprat-sticky-header-enabled');
    measure();

    if (window.ResizeObserver) {
        var observer = new ResizeObserver(scheduleMeasure);
        observer.observe(header);
    }
    window.addEventListener('resize', scheduleMeasure, { passive: true });
    window.addEventListener('orientationchange', scheduleMeasure);
    window.addEventListener('load', scheduleMeasure);
    document.addEventListener('DOMContentLoaded', scheduleMeasure);
    // Une variation du menu hamburger / sous-menu change la hauteur du bloc.
    header.addEventListener('click', scheduleMeasure);
    // Lorsque WooCommerce reconstruit le mini-panier, le header demeure fixe.
    if (window.jQuery) {
        window.jQuery(document.body).on('wc_fragments_refreshed added_to_cart', scheduleMeasure);
    }
}());