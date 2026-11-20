/* DEPRAT — aperçu immédiat du fond de chaque slide, sans écraser les options enregistrées. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-deprat-slide-background]').forEach(function (root) {
            var modes = Array.from(root.querySelectorAll('input[name="deprat_cat_slide_bg_mode"]'));
            var preview = root.querySelector('[data-deprat-slide-bg-preview]');
            var imagePreview = root.querySelector('[data-bg-controls="image"] [data-deprat-image-preview]');
            var start = root.querySelector('input[name="deprat_cat_slide_bg_start"]');
            var end = root.querySelector('input[name="deprat_cat_slide_bg_end"]');
            var solid = root.querySelector('input[name="deprat_cat_slide_bg_solid"]');
            function refresh() {
                var chosen = modes.find(function (radio) { return radio.checked; });
                var mode = chosen ? chosen.value : 'standard';
                root.querySelectorAll('[data-bg-controls]').forEach(function (section) {
                    section.hidden = section.getAttribute('data-bg-controls') !== mode;
                });
                if (!preview) { return; }
                preview.className = 'deprat-slide-bg-preview is-' + mode;
                preview.style.backgroundImage = '';
                preview.style.backgroundColor = '';
                var label = preview.querySelector('span');
                if (label) {
                    label.textContent = mode === 'standard' ? 'Fond standard DEPRAT' :
                        mode === 'gradient' ? 'Dégradé personnalisé' :
                        mode === 'solid' ? 'Couleur unie' : 'Image de fond';
                }
                if (mode === 'gradient') {
                    preview.style.backgroundImage = 'linear-gradient(115deg,' + start.value + ',' + end.value + ')';
                } else if (mode === 'solid') {
                    preview.style.backgroundColor = solid.value;
                } else if (mode === 'image') {
                    var image = imagePreview && imagePreview.querySelector('img');
                    preview.style.backgroundImage = image && image.src ?
                        'linear-gradient(90deg,rgba(0,25,65,.52),rgba(0,25,65,.12)),url("' + image.src.replace(/"/g, '%22') + '")' :
                        'linear-gradient(115deg,#072e67,#1264b1)';
                }
            }
            modes.forEach(function (radio) { radio.addEventListener('change', refresh); });
            [start, end, solid].forEach(function (input) {
                if (input) { input.addEventListener('input', refresh); input.addEventListener('change', refresh); }
            });
            // L'éditeur d'image existant met à jour le DOM de l'aperçu par jQuery.
            // MutationObserver reflète instantanément l'ajout et le retrait.
            if (imagePreview && window.MutationObserver) {
                var observer = new MutationObserver(refresh);
                observer.observe(imagePreview, { childList: true, subtree: true, attributes: true, attributeFilter: ['src'] });
            }
            refresh();
        });
    });
}());