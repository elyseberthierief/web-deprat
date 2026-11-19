(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-deprat-icon-library]');
        if (!root || !window.depratIconLibrary) { return; }
        var config = window.depratIconLibrary;
        var overlay = root.querySelector('[data-library-overlay]');
        var form = root.querySelector('[data-library-form]');
        var heading = root.querySelector('[data-library-title]');
        var keyInput = root.querySelector('[data-library-id]');
        var nameInput = root.querySelector('[data-library-name]');
        var preview = root.querySelector('[data-library-preview]');
        var clear = root.querySelector('[data-library-clear]');
        var error = root.querySelector('[data-library-error]');
        var message = root.querySelector('[data-library-message]');
        var submit = root.querySelector('[data-library-save]');
        var imageId = 0;
        var frame = null;

        function setError(text) {
            error.textContent = text || '';
            error.hidden = !text;
        }
        function setPreview(url) {
            preview.replaceChildren();
            if (url) {
                var img = document.createElement('img');
                img.src = url;
                img.alt = 'Aperçu de l’icône';
                preview.appendChild(img);
            } else {
                preview.textContent = imageId ? 'Image sélectionnée' : 'Aucune image choisie';
            }
            clear.hidden = !imageId;
        }
        function openEditor(card) {
            form.reset();
            setError('');
            keyInput.value = card ? card.getAttribute('data-library-key') : '';
            nameInput.value = card ? card.getAttribute('data-library-name') : '';
            imageId = card ? Number(card.getAttribute('data-library-media')) || 0 : 0;
            heading.textContent = card ? 'Modifier l’icône' : 'Ajouter une icône';
            var img = card && card.querySelector('.deprat-library-art img');
            setPreview(img ? img.src : '');
            overlay.hidden = false;
            nameInput.focus();
        }
        function closeEditor() { overlay.hidden = true; setError(''); }
        function request(operation, data) {
            var params = new URLSearchParams();
            params.append('action', 'deprat_icon_library_save');
            params.append('nonce', config.nonce);
            params.append('operation', operation);
            Object.keys(data).forEach(function (key) { params.append(key, data[key]); });
            return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).then(function (response) { return response.json(); }).then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error(payload && payload.data && payload.data.message || 'Enregistrement impossible.');
                }
                return payload.data;
            });
        }
        root.querySelector('[data-library-new]').addEventListener('click', function () { openEditor(null); });
        root.addEventListener('click', function (event) {
            var edit = event.target.closest('[data-library-edit]');
            var remove = event.target.closest('[data-library-delete]');
            if (edit) {
                openEditor(edit.closest('[data-library-key]'));
            } else if (remove) {
                var card = remove.closest('[data-library-key]');
                var label = card.getAttribute('data-library-name');
                if (!window.confirm('Retirer « ' + label + ' » de la bibliothèque ? Les anciennes affectations sont conservées.')) { return; }
                remove.disabled = true;
                request('delete', { key: card.getAttribute('data-library-key') }).then(function () {
                    window.location.reload();
                }).catch(function (err) {
                    message.textContent = err.message;
                    remove.disabled = false;
                });
            }
            if (event.target.matches('[data-library-cancel]')) { closeEditor(); }
            if (event.target === overlay) { closeEditor(); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !overlay.hidden) { closeEditor(); }
        });
        root.querySelector('[data-library-choose]').addEventListener('click', function () {
            if (!config.canUpload || !window.wp || !wp.media) {
                setError('La médiathèque n’est pas accessible avec votre compte.');
                return;
            }
            if (!frame) {
                frame = wp.media({ title: 'Choisir une icône DEPRAT', button: { text: 'Utiliser cette image' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(attachment.mime) === -1) {
                        setError('Image JPG, PNG ou WebP uniquement.'); return;
                    }
                    imageId = Number(attachment.id) || 0;
                    var size = attachment.sizes && (attachment.sizes.thumbnail || attachment.sizes.medium);
                    setPreview(size && size.url || attachment.url);
                    setError('');
                });
            }
            frame.open();
        });
        clear.addEventListener('click', function () { imageId = 0; setPreview(''); });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            setError('');
            submit.disabled = true;
            request('save', { key: keyInput.value, name: nameInput.value, media_id: String(imageId) })
                .then(function () { window.location.reload(); })
                .catch(function (err) { setError(err.message); submit.disabled = false; });
        });
    });
}());
