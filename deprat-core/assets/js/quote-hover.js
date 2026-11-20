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
        // Supprimer une ligne depuis le mini-panier, sans quitter la page.
        // Délégation : les fragments WooCommerce remplacent ce widget en AJAX.
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-deprat-cart-remove]');
            if (!button || !button.closest('[data-deprat-quote-widget]')) { return; }
            event.preventDefault();
            event.stopPropagation();
            if (button.disabled || !window.depratQuoteActions) { return; }
            var key = button.getAttribute('data-deprat-cart-remove');
            if (!key) { return; }
            var el = widget();
            if (!el) { return; }
            stop();
            el.classList.add('is-open', 'is-removing');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            var payload = new URLSearchParams();
            payload.set('security', depratQuoteActions.nonce);
            payload.set('cart_item_key', key);
            fetch(depratQuoteActions.removeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: payload.toString()
            }).then(function (response) { return response.json(); })
              .then(function (data) {
                  if (!data || !data.success || !data.data || !data.data.fragments) {
                      throw new Error(data && data.data && data.data.message ? data.data.message : 'Suppression impossible');
                  }
                  var fragments = data.data.fragments;
                  Object.keys(fragments).forEach(function (selector) {
                      var target = document.querySelector(selector);
                      if (target && typeof fragments[selector] === 'string') {
                          var holder = document.createElement('div');
                          holder.innerHTML = fragments[selector];
                          var next = holder.firstElementChild;
                          if (next) { target.replaceWith(next); }
                      }
                  });
                  var current = widget();
                  if (current) { current.classList.add('is-open'); }
                  if (window.jQuery) {
                      window.jQuery(document.body).trigger('wc_fragments_refreshed');
                  }
              }).catch(function (err) {
                  var current = widget();
                  if (current) {
                      var alert = current.querySelector('[data-deprat-cart-error]');
                      if (!alert) {
                          alert = document.createElement('p');
                          alert.className = 'deprat-mini-cart-error';
                          alert.setAttribute('role', 'alert');
                          alert.setAttribute('data-deprat-cart-error', '');
                          current.querySelector('[data-deprat-mini-cart]').appendChild(alert);
                      }
                      alert.textContent = err.message || 'La référence n’a pas pu être retirée.';
                      current.classList.remove('is-removing');
                  }
                  button.disabled = false;
                  button.removeAttribute('aria-busy');
              });
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