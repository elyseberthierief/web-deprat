( function () {
    'use strict';
    function setupViewer( widget ) {
        var gallery = widget.closest( '.woocommerce-product-gallery' ) || widget.parentElement;
        var panel = widget.querySelector( '.deprat-three-viewport' );
        var model = widget.querySelector( 'model-viewer' );
        widget.querySelectorAll( '[data-three-mode]' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                var active = button.getAttribute( 'data-three-mode' ) === 'model';
                gallery.classList.toggle( 'deprat-three-active', active );
                panel.hidden = ! active;
                widget.querySelectorAll( '[data-three-mode]' ).forEach( function ( item ) {
                    var selected = item === button;
                    item.classList.toggle( 'is-selected', selected );
                    item.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
                } );
                if ( model ) {
                    if ( active ) { model.setAttribute( 'camera-controls', '' ); }
                    else if ( typeof model.pause === 'function' ) { model.pause(); }
                }
            } );
        } );
        if ( model ) {
            model.addEventListener( 'error', function () {
                var message = widget.querySelector( '.deprat-three-error' );
                if ( ! message ) {
                    message = document.createElement( 'p' );
                    message.className = 'deprat-three-error';
                    message.setAttribute( 'role', 'alert' );
                    panel.appendChild( message );
                }
                message.textContent = 'Le modèle 3D ne peut pas être chargé. Vérifiez le fichier GLB.';
            } );
        }
    }
    function start() {
        document.querySelectorAll( '[data-deprat-3d]' ).forEach( setupViewer );
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', start ); }
    else { start(); }
} )();
