( function () {
    'use strict';

    function setupViewer( widget ) {
        var gallery = widget.closest( '.woocommerce-product-gallery' ) || widget.parentElement;
        // Le bloc est sorti du wrapper photos : sinon il disparaît en mode Vue 3D.
        if ( gallery && gallery.classList.contains( 'woocommerce-product-gallery' ) && widget.parentElement !== gallery ) {
            gallery.appendChild( widget );
        }
        var panel = widget.querySelector( '.deprat-three-viewport' );
        var model = widget.querySelector( 'model-viewer' );
        var outline = widget.querySelector( '[data-three-outline]' );
        var buttons = widget.querySelectorAll( '[data-three-mode]' );
        var modes = widget.querySelectorAll( '[data-render-mode]' );
        var switchBackground = widget.querySelector( '[data-background-cycle]' );
        var backgroundOverride = 'auto';
        var automaticBackground = 'light';
        var contourInstance = null;
        var contourLoading = null;
        var renderMode = 'texture';

        function currentBackground() {
            return backgroundOverride === 'auto' ? automaticBackground : backgroundOverride;
        }
        function applyBackground() {
            var shade = currentBackground();
            panel.setAttribute( 'data-background', shade );
            if ( switchBackground ) {
                switchBackground.textContent = 'Fond : ' + ( backgroundOverride === 'auto' ? 'automatique' : shade === 'dark' ? 'gris contrasté' : 'clair' );
                switchBackground.setAttribute( 'aria-label', 'Fond du modèle 3D : ' + switchBackground.textContent );
            }
            if ( contourInstance ) { contourInstance.recolor(); }
        }

        function materialBrightness() {
            if ( ! model || ! model.model || ! model.model.materials ) { return null; }
            var values = [];
            model.model.materials.forEach( function ( material ) {
                var pbr = material && material.pbrMetallicRoughness;
                var rgba = pbr && pbr.baseColorFactor;
                if ( ! rgba || rgba.length < 3 ) { return; }
                // Pondération de luminance (sRGB approximée) ; model-viewer expose la couleur du GLB.
                var lum = .2126 * rgba[0] + .7152 * rgba[1] + .0722 * rgba[2];
                if ( Number.isFinite( lum ) ) { values.push( lum ); }
            } );
            return values.length ? values.reduce( ( sum, value ) => sum + value, 0 ) / values.length : null;
        }
        function detectBackground() {
            var brightness = materialBrightness();
            // Une pièce blanche ou gris très clair ressort sur fond gris ; une pièce foncée ressort sur fond clair.
            automaticBackground = brightness !== null && brightness >= .65 ? 'dark' : 'light';
            applyBackground();
        }

        if ( switchBackground ) {
            switchBackground.addEventListener( 'click', function () {
                backgroundOverride = backgroundOverride === 'auto'
                    ? ( panel.getAttribute( 'data-background' ) === 'dark' ? 'light' : 'dark' )
                    : backgroundOverride === 'dark' ? 'light' : 'auto';
                applyBackground();
            } );
        }

        function showError( error ) {
            var message = widget.querySelector( '.deprat-three-error' );
            if ( ! message ) {
                message = document.createElement( 'p' );
                message.className = 'deprat-three-error';
                message.setAttribute( 'role', 'alert' );
                panel.appendChild( message );
            }
            message.textContent = error && error.message
                ? 'La vue en contours ne peut pas être chargée : ' + error.message
                : 'Le modèle 3D ne peut pas être chargé. Vérifiez le fichier GLB.';
        }

        function setRendering( mode ) {
            renderMode = mode;
            modes.forEach( function ( btn ) {
                var active = btn.getAttribute( 'data-render-mode' ) === mode;
                btn.classList.toggle( 'is-selected', active );
                btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
            } );
            if ( ! model || ! outline ) { return; }
            var isOutline = mode === 'contours';
            model.hidden = isOutline;
            outline.hidden = ! isOutline;
            if ( contourInstance ) {
                if ( isOutline && ! panel.hidden ) { contourInstance.show(); }
                else { contourInstance.hide(); }
            }
            if ( ! isOutline || panel.hidden || contourInstance || contourLoading ) { return; }
            var url = window.deprat3DConfig && window.deprat3DConfig.contoursUrl;
            if ( ! url ) { showError( new Error( 'Module de contours introuvable.' ) ); return; }
            outline.textContent = 'Chargement des contours…';
            contourLoading = import( url ).then( function ( module ) {
                outline.textContent = '';
                return module.createContourViewer( outline, model.src, currentBackground );
            } ).then( function ( instance ) {
                contourInstance = instance;
                if ( renderMode === 'contours' && ! panel.hidden ) { instance.show(); }
                else { instance.hide(); }
            } ).catch( function ( error ) {
                outline.textContent = '';
                model.hidden = false;
                outline.hidden = true;
                renderMode = 'texture';
                modes.forEach( function ( btn ) {
                    var selected = btn.getAttribute( 'data-render-mode' ) === 'texture';
                    btn.classList.toggle( 'is-selected', selected );
                    btn.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
                } );
                showError( error );
            } ).finally( function () { contourLoading = null; } );
        }

        modes.forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                setRendering( btn.getAttribute( 'data-render-mode' ) );
            } );
        } );
        buttons.forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                var active = button.getAttribute( 'data-three-mode' ) === 'model';
                gallery.classList.toggle( 'deprat-three-active', active );
                panel.hidden = ! active;
                buttons.forEach( function ( item ) {
                    var selected = item === button;
                    item.classList.toggle( 'is-selected', selected );
                    item.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
                } );
                if ( model ) {
                    if ( active ) { model.setAttribute( 'camera-controls', '' ); }
                    else if ( typeof model.pause === 'function' ) { model.pause(); }
                }
                if ( contourInstance ) {
                    if ( active && renderMode === 'contours' ) { contourInstance.show(); }
                    else { contourInstance.hide(); }
                }
            } );
        } );
        if ( model ) {
            model.addEventListener( 'load', detectBackground );
            model.addEventListener( 'error', showError );
            if ( model.loaded ) { detectBackground(); }
        }
        applyBackground();
    }
    function start() {
        document.querySelectorAll( '[data-deprat-3d]' ).forEach( setupViewer );
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', start ); }
    else { start(); }
} )();
