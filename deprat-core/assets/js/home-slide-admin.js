/* DEPRAT — import d’images pour les slides d’accueil, indépendant du thème. */
(function ($) {
    'use strict';

    function setupPicker(root) {
        var $root = $(root);
        var $input = $root.find('[data-deprat-image-id]');
        var $preview = $root.find('[data-deprat-image-preview]');
        var $select = $root.find('[data-deprat-image-select]');
        var $remove = $root.find('[data-deprat-image-remove]');
        var frame;

        function updatePreview(id, url) {
            $input.val(id || '');
            $preview.empty();
            if (id && url) {
                $('<img>', {
                    src: url,
                    alt: 'Aperçu de l’image du slide'
                }).css({
                    width: '100%',
                    height: 'auto',
                    maxHeight: '220px',
                    objectFit: 'cover',
                    border: '1px solid #ccd0d4',
                    borderRadius: '5px'
                }).appendTo($preview);
                $select.text('Remplacer l’image');
                $remove.prop('hidden', false);
            } else {
                $('<p>', { 'class': 'description' })
                    .text('Aucune image sélectionnée. Choisissez une photo pour l’arrière-plan du slide.')
                    .appendTo($preview);
                $select.text('Ajouter une image');
                $remove.prop('hidden', true);
            }
        }

        $select.on('click', function (event) {
            event.preventDefault();
            if (!window.wp || !window.wp.media) {
                window.alert('La médiathèque WordPress ne peut pas être ouverte.');
                return;
            }
            if (!frame) {
                frame = window.wp.media({
                    title: 'Choisir l’image du slide DEPRAT',
                    button: { text: 'Utiliser cette image' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection').first();
                    if (!selection) { return; }
                    var attachment = selection.toJSON();
                    if (!attachment.id || !attachment.type || attachment.type !== 'image') { return; }
                    var url = attachment.url;
                    if (attachment.sizes) {
                        var size = attachment.sizes.medium_large || attachment.sizes.large || attachment.sizes.medium;
                        if (size && size.url) { url = size.url; }
                    }
                    updatePreview(attachment.id, url);
                });
            }
            frame.open();
        });

        $remove.on('click', function (event) {
            event.preventDefault();
            updatePreview('', '');
        });
    }

    $(function () {
        $('[data-deprat-home-image-picker]').each(function () { setupPicker(this); });
    });
}(jQuery));
