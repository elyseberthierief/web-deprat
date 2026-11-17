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

        // Dans la demande : la quantité est le nombre de conditionnements standard
        // ou le nombre de pièces si le client choisit l'option unitaire.
        catalog.querySelectorAll( '[data-catalog-add]' ).forEach( function ( form ) {
            var qty = form.querySelector( '[name="quantity"]' );
            var choice = form.querySelector( '[name="deprat_package_choice"]' );
            var warning = form.querySelector( '[data-unitary-warning]' );
            var submit = form.querySelector( '[type="submit"]' );
            var feedback = form.querySelector( '[data-catalog-feedback]' );
            var qtyLabel = form.querySelector( '.deprat-catalog-qty-label > span:first-child' );
            function currentQty() {
                var value = Number( qty && qty.value );
                return Number.isSafeInteger( value ) && value > 0 ? value : 1;
            }
            function syncChoice() {
                var unitary = choice && choice.value === 'unitary';
                if ( warning ) { warning.hidden = !unitary; }
                if ( qtyLabel ) { qtyLabel.textContent = unitary ? 'Nombre de pièces' : 'Quantité'; }
            }
            if ( choice ) { choice.addEventListener( 'change', syncChoice ); }
            syncChoice();
            var minus = form.querySelector( '[data-qty-minus]' );
            var plus = form.querySelector( '[data-qty-plus]' );
            if ( minus ) {
                minus.addEventListener( 'click', function () {
                    qty.value = String( Math.max( 1, currentQty() - 1 ) );
                    qty.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                } );
            }
            if ( plus ) {
                plus.addEventListener( 'click', function () {
                    qty.value = String( Math.min( 999999, currentQty() + 1 ) );
                    qty.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                } );
            }
            function message( value, success ) {
                if ( ! feedback ) { return; }
                feedback.hidden = false;
                feedback.className = 'deprat-catalog-feedback ' + ( success ? 'is-success' : 'is-error' );
                feedback.textContent = value;
            }
            form.addEventListener( 'submit', function ( event ) {
                event.preventDefault();
                if ( submit.disabled ) { return; }
                var cfg = window.depratCatalogueConfig || {};
                var id = form.querySelector( '[name="product_id"]' );
                if ( ! cfg.ajaxUrl || ! id || !/^\d+$/.test( id.value ) ) {
                    message( 'La demande ne peut pas être enregistrée pour le moment.', false );
                    return;
                }
                var params = new URLSearchParams();
                params.set( 'product_id', id.value );
                params.set( 'quantity', String( currentQty() ) );
                if ( choice ) { params.set( 'deprat_package_choice', choice.value ); }
                submit.disabled = true;
                var label = submit.textContent;
                submit.textContent = 'Ajout en cours…';
                if ( feedback ) { feedback.hidden = true; }
                fetch( cfg.ajaxUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: params.toString()
                } ).then( function ( response ) {
                    if ( ! response.ok ) { throw new Error( 'Le serveur a refusé la demande.' ); }
                    return response.json();
                } ).then( function ( response ) {
                    if ( response.error ) {
                        throw new Error( 'Impossible d’ajouter cette quantité. Vérifiez le conditionnement et la disponibilité.' );
                    }
                    // Actualiser le mini-panier existant sans changer de page.
                    if ( response.fragments ) {
                        Object.keys( response.fragments ).forEach( function ( selector ) {
                            document.querySelectorAll( selector ).forEach( function ( node ) {
                                var template = document.createElement( 'template' );
                                template.innerHTML = response.fragments[selector].trim();
                                if ( template.content.firstElementChild ) {
                                    node.replaceWith( template.content.firstElementChild.cloneNode( true ) );
                                }
                            } );
                        } );
                    }
                    if ( window.jQuery ) {
                        window.jQuery( document.body ).trigger( 'added_to_cart', [
                            response.fragments || {}, response.cart_hash || '', window.jQuery( submit )
                        ] );
                    }
                    message( 'Produit ajouté à votre demande de devis.', true );
                } ).catch( function ( error ) {
                    message( error.message || 'Erreur de connexion. Réessayez.', false );
                } ).finally( function () {
                    submit.disabled = false;
                    submit.textContent = label;
                } );
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
