( function () {
    'use strict';
    function initCatalogue() {
        var catalog = document.querySelector( '.deprat-catalog' );
        if ( ! catalog ) { return; }

        // La grille et la liste utilisent les mêmes fiches, sans charger deux fois les produits.
        var products = catalog.querySelector( '[data-catalog-products]' );
        var viewButtons = catalog.querySelectorAll( '[data-view]' );
        viewButtons.forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                var list = button.getAttribute( 'data-view' ) === 'list';
                if ( products ) { products.classList.toggle( 'is-list', list ); }
                viewButtons.forEach( function ( item ) {
                    var active = item === button;
                    item.classList.toggle( 'is-active', active );
                    item.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
                } );
            } );
        } );
        var sort = catalog.querySelector( '[data-sort-select]' );
        if ( sort ) { sort.addEventListener( 'change', function () { sort.form.submit(); } ); }

        // Galerie : photos de la fiche produit. Survol = photo suivante
        // et défilement automatique ; boutons accessibles au clavier et sur mobile.
        catalog.querySelectorAll( '[data-catalog-gallery]' ).forEach( function ( gallery ) {
            var slides = Array.from( gallery.querySelectorAll( '[data-gallery-slide]' ) );
            if ( slides.length < 2 ) { return; }
            var dots = gallery.querySelectorAll( '[data-gallery-dot]' );
            var current = 0, rotation = null;
            function show( index ) {
                current = ( index + slides.length ) % slides.length;
                slides.forEach( function ( slide, i ) { slide.classList.toggle( 'is-active', i === current ); } );
                dots.forEach( function ( dot, i ) { dot.classList.toggle( 'is-active', i === current ); } );
            }
            var prev = gallery.querySelector( '[data-gallery-prev]' );
            var next = gallery.querySelector( '[data-gallery-next]' );
            if ( prev ) { prev.addEventListener( 'click', function () { show( current - 1 ); } ); }
            if ( next ) { next.addEventListener( 'click', function () { show( current + 1 ); } ); }
            function stop() { if ( rotation ) { clearInterval( rotation ); rotation = null; } }
            gallery.addEventListener( 'mouseenter', function () {
                if ( window.matchMedia && window.matchMedia( '(hover: none)' ).matches ) { return; }
                show( current + 1 );
                stop();
                rotation = setInterval( function () { show( current + 1 ); }, 2100 );
            } );
            gallery.addEventListener( 'mouseleave', function () { stop(); show( 0 ); } );
            document.addEventListener( 'visibilitychange', function () { if ( document.hidden ) { stop(); } } );
        } );

        // Toute la carte ouvre le détail, sauf les véritables commandes
        // (quantité, ajout AJAX, images/3D, liens de sous-catégories).
        catalog.querySelectorAll( '[data-catalog-card]' ).forEach( function ( card ) {
            card.addEventListener( 'click', function ( event ) {
                if ( event.defaultPrevented || event.button !== 0 || event.ctrlKey
                    || event.metaKey || event.shiftKey || event.altKey ) { return; }
                if ( event.target.closest( 'a, button, input, select, textarea, label, form, dialog, [role="button"]' ) ) { return; }
                if ( window.getSelection && String( window.getSelection() ).trim() ) { return; }
                var url = card.getAttribute( 'data-product-url' );
                if ( url ) { window.location.assign( url ); }
            } );
        } );

        var packageDialog = catalog.querySelector( '[data-catalog-package-dialog]' );
        var activeForm = null;
        var activeOptions = [];
        var pendingAdd = false;
        var previousFocus = null;
        var modalQty = packageDialog && packageDialog.querySelector( '[data-package-dialog-quantity]' );
        var modalOptions = packageDialog && packageDialog.querySelector( '[data-package-dialog-options]' );
        var modalConfirm = packageDialog && packageDialog.querySelector( '[data-package-dialog-confirm]' );
        var modalFeedback = packageDialog && packageDialog.querySelector( '[data-package-dialog-feedback]' );
        var modalWarning = packageDialog && packageDialog.querySelector( '[data-package-dialog-warning]' );
        function qtyValue( field ) {
            var n = Number( field && field.value );
            return Number.isSafeInteger( n ) && n > 0 ? Math.min( 999999, n ) : 1;
        }
        function choicesFor( form ) {
            try {
                var choices = JSON.parse( form.getAttribute( 'data-package-options' ) || '[]' );
                return Array.isArray( choices ) ? choices : [];
            } catch ( error ) { return []; }
        }
        function popupIsOpen() {
            return !!( packageDialog && packageDialog.hasAttribute( 'open' ) );
        }
        function closePackageDialog() {
            if ( pendingAdd || !packageDialog ) { return; }
            if ( typeof packageDialog.close === 'function' ) { packageDialog.close(); }
            else { packageDialog.removeAttribute( 'open' ); }
            activeForm = null;
            activeOptions = [];
            if ( previousFocus && previousFocus.isConnected ) { previousFocus.focus(); }
        }
        function selectedPackage() {
            var selected = modalOptions && modalOptions.querySelector( 'input[name="deprat_popup_package"]:checked' );
            if ( !selected ) { return null; }
            return activeOptions.find( function ( item ) { return item.value === selected.value; } ) || null;
        }
        function displayNumber( value ) {
            return new Intl.NumberFormat( 'fr-FR', { maximumFractionDigits: 3 } ).format( Number( value ) || 0 );
        }
        function updatePackageSummary() {
            var item = selectedPackage();
            if ( !item || !packageDialog ) { return; }
            var count = qtyValue( modalQty );
            var unitary = item.value === 'unitary';
            var label = packageDialog.querySelector( '[data-package-dialog-qty-label]' );
            if ( label ) { label.textContent = unitary ? 'Nombre de pièces' : 'Nombre de conditionnements'; }
            if ( modalWarning ) { modalWarning.hidden = !unitary; }
            var summary = packageDialog.querySelector( '[data-package-dialog-summary]' );
            if ( !summary ) { return; }
            var row = item.row || {};
            if ( unitary ) {
                summary.textContent = 'Votre demande : ' + displayNumber( count ) + ( count > 1 ? ' pièces à l’unité.' : ' pièce à l’unité.' );
            } else if ( row.mode === 'length' ) {
                summary.textContent = 'Votre demande : ' + displayNumber( count * Number( row.total_m ) )
                    + ' m, soit ' + displayNumber( count * Number( row.sections ) )
                    + ' longueurs de ' + displayNumber( row.length_m ) + ' m.';
            } else {
                var units = count * Number( row.count || 1 );
                var plural = units > 1 && !/[sxz]$/i.test( String( row.type || '' ) ) ? 's' : '';
                summary.textContent = 'Votre demande : ' + displayNumber( units ) + ' ' + ( row.type || 'conditionnement' ) + plural
                    + ' de ' + displayNumber( row.pieces ) + ' pièces, soit '
                    + displayNumber( units * Number( row.pieces ) ) + ' pièces au total.';
            }
        }
        function openPackageDialog( form ) {
            if ( !packageDialog || !modalOptions || pendingAdd ) { return; }
            activeOptions = choicesFor( form );
            if ( activeOptions.length < 2 ) { return; }
            activeForm = form;
            previousFocus = document.activeElement;
            var bind = function ( key, value, hideIfEmpty ) {
                var node = packageDialog.querySelector( '[data-package-dialog-' + key + ']' );
                if ( !node ) { return; }
                node.textContent = value || '';
                if ( hideIfEmpty ) { node.hidden = !value; }
            };
            bind( 'product-name', form.getAttribute( 'data-product-name' ) );
            bind( 'ref', form.getAttribute( 'data-product-sku' ) ? 'Réf. ' + form.getAttribute( 'data-product-sku' ) : '', true );
            bind( 'material', form.getAttribute( 'data-product-material' ) ? 'Matière : ' + form.getAttribute( 'data-product-material' ) : '', true );
            bind( 'description', form.getAttribute( 'data-product-description' ), true );
            var productLink = packageDialog.querySelector( '[data-package-dialog-link]' );
            if ( productLink ) { productLink.href = form.getAttribute( 'data-product-url' ) || '#'; }
            var image = packageDialog.querySelector( '[data-package-dialog-image]' );
            if ( image ) {
                var src = form.getAttribute( 'data-product-image' );
                image.hidden = !src;
                if ( src ) { image.src = src; image.alt = form.getAttribute( 'data-product-name' ) || ''; }
                else { image.removeAttribute( 'src' ); }
            }
            modalOptions.replaceChildren();
            activeOptions.forEach( function ( option, index ) {
                var choice = document.createElement( 'label' );
                choice.className = 'deprat-package-dialog-option';
                var radio = document.createElement( 'input' );
                radio.type = 'radio';
                radio.name = 'deprat_popup_package';
                radio.value = option.value;
                radio.checked = index === 0;
                var description = document.createElement( 'span' );
                description.className = 'deprat-package-dialog-option-content';
                var heading = document.createElement( 'strong' );
                heading.textContent = option.title || 'Conditionnement';
                var details = document.createElement( 'small' );
                details.textContent = option.label || '';
                description.append( heading, details );
                choice.append( radio, description );
                if ( option.unitary ) { choice.classList.add( 'is-unitary' ); }
                modalOptions.appendChild( choice );
            } );
            if ( modalQty ) { modalQty.value = String( qtyValue( form.querySelector( '[name="quantity"]' ) ) ); }
            if ( modalFeedback ) { modalFeedback.hidden = true; }
            updatePackageSummary();
            if ( typeof packageDialog.showModal === 'function' ) { packageDialog.showModal(); }
            else { packageDialog.setAttribute( 'open', '' ); }
            var first = modalOptions.querySelector( 'input[type="radio"]' );
            if ( first ) { first.focus(); }
        }
        if ( packageDialog ) {
            packageDialog.querySelectorAll( '[data-package-dialog-close]' ).forEach( function ( button ) {
                button.addEventListener( 'click', closePackageDialog );
            } );
            packageDialog.addEventListener( 'click', function ( event ) {
                if ( event.target === packageDialog ) { closePackageDialog(); }
            } );
            packageDialog.addEventListener( 'cancel', function ( event ) {
                if ( pendingAdd ) { event.preventDefault(); }
                else { activeForm = null; activeOptions = []; }
            } );
            modalOptions.addEventListener( 'change', updatePackageSummary );
            modalQty.addEventListener( 'input', updatePackageSummary );
            packageDialog.querySelector( '[data-package-dialog-minus]' ).addEventListener( 'click', function () {
                modalQty.value = String( Math.max( 1, qtyValue( modalQty ) - 1 ) );
                updatePackageSummary();
            } );
            packageDialog.querySelector( '[data-package-dialog-plus]' ).addEventListener( 'click', function () {
                modalQty.value = String( Math.min( 999999, qtyValue( modalQty ) + 1 ) );
                updatePackageSummary();
            } );
        }
        function showFeedback( form, text, success ) {
            var feedback = form.querySelector( '[data-catalog-feedback]' );
            if ( !feedback ) { return; }
            feedback.hidden = false;
            feedback.className = 'deprat-catalog-feedback ' + ( success ? 'is-success' : 'is-error' );
            feedback.textContent = text;
        }
        // L'endpoint WC-AJAX conserve l'identité et le conditionnement dans
        // la demande sans changement de page, comme dans la version précédente.
        function addProduct( form, packageValue, quantity, fromDialog ) {
            var cfg = window.depratCatalogueConfig || {};
            var productInput = form.querySelector( '[name="product_id"]' );
            if ( !cfg.ajaxUrl || !productInput || !/^\d+$/.test( productInput.value ) ) {
                if ( fromDialog && modalFeedback ) {
                    modalFeedback.textContent = 'La demande ne peut pas être enregistrée pour le moment.';
                    modalFeedback.hidden = false;
                } else { showFeedback( form, 'La demande ne peut pas être enregistrée pour le moment.', false ); }
                return;
            }
            var button = form.querySelector( '[type="submit"]' );
            if ( button.disabled || pendingAdd ) { return; }
            var params = new URLSearchParams();
            params.set( 'product_id', productInput.value );
            params.set( 'quantity', String( quantity ) );
            params.set( 'deprat_package_choice', packageValue );
            var oldText = button.textContent;
            button.disabled = true;
            button.textContent = 'Ajout…';
            pendingAdd = true;
            if ( modalConfirm ) {
                modalConfirm.disabled = true;
                if ( fromDialog ) { modalConfirm.textContent = 'Ajout en cours…'; }
            }
            var notice = form.querySelector( '[data-catalog-feedback]' );
            if ( notice ) { notice.hidden = true; }
            if ( modalFeedback ) { modalFeedback.hidden = true; }
            fetch( cfg.ajaxUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: params.toString()
            } ).then( function ( response ) {
                if ( !response.ok ) { throw new Error( 'Le serveur a refusé la demande.' ); }
                return response.json();
            } ).then( function ( response ) {
                if ( response.error ) { throw new Error( 'Conditionnement ou quantité indisponible.' ); }
                if ( response.fragments ) {
                    Object.keys( response.fragments ).forEach( function ( selector ) {
                        document.querySelectorAll( selector ).forEach( function ( node ) {
                            var template = document.createElement( 'template' );
                            template.innerHTML = String( response.fragments[ selector ] ).trim();
                            if ( template.content.firstElementChild ) {
                                node.replaceWith( template.content.firstElementChild.cloneNode( true ) );
                            }
                        } );
                    } );
                }
                if ( window.jQuery ) {
                    window.jQuery( document.body ).trigger( 'added_to_cart', [
                        response.fragments || {}, response.cart_hash || '', window.jQuery( button )
                    ] );
                }
                var hiddenChoice = form.querySelector( '[name="deprat_package_choice"]' );
                if ( hiddenChoice ) { hiddenChoice.value = packageValue; }
                var field = form.querySelector( '[name="quantity"]' );
                if ( field ) { field.value = String( quantity ); }
                showFeedback( form, 'Produit ajouté à votre demande de devis.', true );
                if ( fromDialog && packageDialog && popupIsOpen() ) {
                    if ( typeof packageDialog.close === 'function' ) { packageDialog.close(); }
                    else { packageDialog.removeAttribute( 'open' ); }
                    activeForm = null;
                    activeOptions = [];
                }
            } ).catch( function ( error ) {
                if ( fromDialog && modalFeedback ) {
                    modalFeedback.hidden = false;
                    modalFeedback.textContent = error.message || 'Erreur de connexion. Réessayez.';
                } else {
                    showFeedback( form, error.message || 'Erreur de connexion. Réessayez.', false );
                }
            } ).finally( function () {
                pendingAdd = false;
                button.disabled = false;
                button.textContent = oldText;
                if ( modalConfirm ) { modalConfirm.disabled = false; modalConfirm.textContent = 'Ajouter à ma demande'; }
            } );
        }
        if ( modalConfirm ) {
            modalConfirm.addEventListener( 'click', function () {
                if ( !activeForm || pendingAdd ) { return; }
                var choice = selectedPackage();
                if ( !choice ) { return; }
                addProduct( activeForm, choice.value, qtyValue( modalQty ), true );
            } );
        }
        catalog.querySelectorAll( '[data-catalog-add]' ).forEach( function ( form ) {
            var field = form.querySelector( '[name="quantity"]' );
            function adjust( delta ) {
                field.value = String( Math.min( 999999, Math.max( 1, qtyValue( field ) + delta ) ) );
                field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
            }
            var minus = form.querySelector( '[data-qty-minus]' );
            var plus = form.querySelector( '[data-qty-plus]' );
            if ( minus ) { minus.addEventListener( 'click', function () { adjust( -1 ); } ); }
            if ( plus ) { plus.addEventListener( 'click', function () { adjust( 1 ); } ); }
            form.addEventListener( 'submit', function ( event ) {
                event.preventDefault();
                if ( pendingAdd ) { return; }
                var choices = choicesFor( form );
                if ( choices.length > 1 && form.getAttribute( 'data-multiple-packages' ) === 'true' ) {
                    openPackageDialog( form );
                    return;
                }
                var chosen = choices.length === 1 ? choices[0].value : '0';
                addProduct( form, chosen, qtyValue( field ), false );
            } );
        } );

        // Le catalogue réutilise les vrais fichiers GLB et le moteur de contours
        // déjà utilisé par les fiches produit, sans recopier les modèles.
        var dialog = catalog.querySelector( '[data-catalog-model-dialog]' );
        var model = dialog ? dialog.querySelector( '[data-model-viewer]' ) : null;
        var close = dialog ? dialog.querySelector( '[data-model-close]' ) : null;
        var title = dialog ? dialog.querySelector( '[data-model-title]' ) : null;
        var stage = dialog ? dialog.querySelector( '.deprat-catalog-3d-stage' ) : null;
        var outline = dialog ? dialog.querySelector( '[data-model-outline]' ) : null;
        var renderButtons = dialog ? dialog.querySelectorAll( '[data-model-render]' ) : [];
        var backgroundButton = dialog ? dialog.querySelector( '[data-model-background]' ) : null;
        var backgroundOverride = 'auto';
        var automaticBackground = 'light';
        var renderMode = 'texture';
        var contourInstance = null;
        var contourLoading = null;

        function background() { return backgroundOverride === 'auto' ? automaticBackground : backgroundOverride; }
        function applyBackground() {
            if ( stage ) { stage.setAttribute( 'data-3d-background', background() ); }
            if ( backgroundButton ) {
                backgroundButton.textContent = 'Fond : ' + ( backgroundOverride === 'auto' ? 'automatique' : background() === 'dark' ? 'gris contrasté' : 'clair' );
            }
            if ( contourInstance ) { contourInstance.recolor(); }
        }
        function detectBackground() {
            if ( ! model || ! model.model || ! model.model.materials ) { return; }
            var values = [];
            model.model.materials.forEach( function ( material ) {
                var pbr = material && material.pbrMetallicRoughness;
                var rgba = pbr && pbr.baseColorFactor;
                if ( ! rgba || rgba.length < 3 ) { return; }
                var value = .2126 * rgba[0] + .7152 * rgba[1] + .0722 * rgba[2];
                if ( Number.isFinite( value ) ) { values.push( value ); }
            } );
            var luminance = values.length ? values.reduce( function ( sum, v ) { return sum + v; }, 0 ) / values.length : null;
            automaticBackground = luminance !== null && luminance >= .65 ? 'dark' : 'light';
            applyBackground();
        }
        if ( model ) { model.addEventListener( 'load', detectBackground ); }
        if ( backgroundButton ) {
            backgroundButton.addEventListener( 'click', function () {
                backgroundOverride = backgroundOverride === 'auto'
                    ? ( stage && stage.getAttribute( 'data-3d-background' ) === 'dark' ? 'light' : 'dark' )
                    : backgroundOverride === 'dark' ? 'light' : 'auto';
                applyBackground();
            } );
        }
        function render( mode ) {
            renderMode = mode;
            renderButtons.forEach( function ( button ) {
                var active = button.getAttribute( 'data-model-render' ) === mode;
                button.classList.toggle( 'is-active', active );
                button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
            } );
            if ( ! model || ! outline ) { return; }
            var contours = mode === 'contours';
            model.hidden = contours;
            outline.hidden = ! contours;
            if ( contourInstance ) {
                if ( contours && dialog && dialog.open ) { contourInstance.show(); }
                else { contourInstance.hide(); }
            }
            if ( ! contours || contourInstance || contourLoading || !dialog.open ) { return; }
            var url = window.depratCatalogueConfig && window.depratCatalogueConfig.contoursUrl;
            if ( ! url ) { outline.textContent = 'Vue en contours indisponible.'; return; }
            outline.textContent = 'Chargement des contours…';
            contourLoading = import( url ).then( function ( module ) {
                outline.textContent = '';
                return module.createContourViewer( outline, model.src, background );
            } ).then( function ( instance ) {
                contourInstance = instance;
                if ( renderMode === 'contours' && dialog.open ) { instance.show(); }
                else { instance.hide(); }
            } ).catch( function () {
                outline.textContent = 'La vue en contours n’est pas disponible pour ce modèle.';
                model.hidden = false;
                renderMode = 'texture';
                renderButtons.forEach( function ( button ) {
                    var active = button.getAttribute( 'data-model-render' ) === 'texture';
                    button.classList.toggle( 'is-active', active );
                    button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
                } );
            } ).finally( function () { contourLoading = null; } );
        }
        renderButtons.forEach( function ( button ) {
            button.addEventListener( 'click', function () { render( button.getAttribute( 'data-model-render' ) ); } );
        } );
        catalog.querySelectorAll( '[data-open-model]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                if ( ! dialog || ! model ) { return; }
                var src = button.getAttribute( 'data-model-src' );
                if ( ! src ) { return; }
                // Les géométries CAD sont liées au GLB de chaque référence.
                if ( contourInstance ) { contourInstance.destroy(); contourInstance = null; }
                if ( outline ) { outline.textContent = ''; }
                backgroundOverride = 'auto';
                automaticBackground = 'light';
                applyBackground();
                render( 'texture' );
                model.setAttribute( 'src', src );
                model.setAttribute( 'alt', 'Modèle 3D de ' + ( button.getAttribute( 'data-model-name' ) || 'la pièce' ) );
                if ( title ) { title.textContent = 'Vue 360° – ' + button.getAttribute( 'data-model-name' ); }
                if ( typeof dialog.showModal === 'function' ) { dialog.showModal(); }
                else { dialog.setAttribute( 'open', '' ); }
                if ( model.loaded ) { detectBackground(); }
            } );
        } );
        function closeModel() {
            if ( ! dialog ) { return; }
            if ( typeof dialog.close === 'function' ) { dialog.close(); }
            else { dialog.removeAttribute( 'open' ); }
            if ( model ) { model.removeAttribute( 'src' ); }
            if ( contourInstance ) { contourInstance.destroy(); contourInstance = null; }
        }
        if ( close ) { close.addEventListener( 'click', closeModel ); }
        if ( dialog ) {
            dialog.addEventListener( 'click', function ( event ) {
                if ( event.target === dialog ) { closeModel(); }
            } );
            dialog.addEventListener( 'close', function () {
                if ( model ) { model.removeAttribute( 'src' ); }
                if ( contourInstance ) { contourInstance.destroy(); contourInstance = null; }
            } );
        }
    }
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initCatalogue );
    } else { initCatalogue(); }
} )();
