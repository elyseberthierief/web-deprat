/* Connexion depuis la liste de devis : vrai formulaire WooCommerce en popup. */
(function () {
  'use strict';
  function init() {
    var dialog = document.getElementById('deprat-quote-login-dialog');
    if (!dialog) { return; }
    var trigger = document.querySelector('[data-deprat-quote-open-login]');
    var close = dialog.querySelector('[data-deprat-quote-close-login]');
    var input = dialog.querySelector('#deprat-quote-login-username');
    var opener = null;
    function open() {
      opener = document.activeElement;
      if (typeof dialog.showModal === 'function') {
        if (!dialog.open) { dialog.showModal(); }
      } else {
        dialog.setAttribute('open', '');
        dialog.classList.add('is-fallback-open');
      }
      document.body.classList.add('deprat-quote-login-modal-open');
      if (input) { input.focus(); }
    }
    function hide() {
      if (dialog.open && typeof dialog.close === 'function') { dialog.close(); }
      else { dialog.removeAttribute('open'); dialog.classList.remove('is-fallback-open'); }
      document.body.classList.remove('deprat-quote-login-modal-open');
      if (opener && opener.focus) { opener.focus(); }
    }
    if (trigger) { trigger.addEventListener('click', function (e) { e.preventDefault(); open(); }); }
    if (close) { close.addEventListener('click', function () { hide(); }); }
    dialog.addEventListener('close', function () { document.body.classList.remove('deprat-quote-login-modal-open'); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) { hide(); } });
    if (dialog.hasAttribute('data-deprat-quote-login-reopen')) { open(); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
}());