/* DEPRAT : mini-panier de devis au survol + confirmation après ajout AJAX. */
(function () {
    'use strict';
    var autoClose = null;
    var refreshTimeout = null;
    var lastHash = '';
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function widget() { return document.querySelector('[data-deprat-quote-widget]'); }
    function stop() {
        if (autoClose) { clearTimeout(autoClose); autoClose = null; }
    }
    function announceAdded() {
        var el = widget();
        if (!el) { return; }
        stop();
        el.classList.remove('is-just-added');
        // Force replay if two products are added successively.
        void el.offsetWidth;
        el.classList.add('is-just-added', 'is-open');
        var panel = el.querySelector('[data-deprat-mini-cart]');
        if (panel) { panel.setAttribute('aria-live', 'polite'); }
        if (!reduced) {
            autoClose = setTimeout(function () {
                var current = widget();
                if (current) {
                    current.classList.remove('is-just-added', 'is-open');
                    var p = current.querySelector('[data-deprat-mini-cart]');
                    if (p) { p.removeAttribute('aria-live'); }
                }
            }, 4200);
        } else {
            autoClose = setTimeout(function () {
                var current = widget();
                if (current) { current.classList.remove('is-just-added', 'is-open'); }
            }, 4200);
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        // Le mini-panier reste utilisable au clavier. Sur mobile, le lien
        // continue vers la liste de devis, sans fenêtre qui masque l'écran.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var el = widget();
                if (el) { el.classList.remove('is-open'); }
                stop();
            }
        });
        // WooCommerce et le catalogue DEPRAT émettent added_to_cart.
        // Le plugin actualise le composant entier via les fragments WC,
        // donc la fenêtre montre immédiatement les bons produits.
        if (window.jQuery) {
            window.jQuery(document.body).on('added_to_cart', function (event, fragments, hash) {
                if (hash) { lastHash = hash; }
                // Différer d'une frame : laisser les fragments remplacer le DOM.
                requestAnimationFrame(function () { announceAdded(); });
            });
            window.jQuery(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function () {
                // Ne pas ouvrir la fenêtre après un chargement normal du site.
                var el = widget();
                if (el && el.classList.contains('is-open')) { el.classList.add('is-open'); }
            });
        }
        // Secours pour les modules tiers qui actualisent le panier sans émettre
        // added_to_cart : un fragment actualisé ne doit pas relancer une animation
        // intempestive. L'événement WooCommerce reste la source d'ouverture.
    });
}());