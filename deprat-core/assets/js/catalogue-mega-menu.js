( function () {
    'use strict';
    function init() {
        document.querySelectorAll( '.deprat-mega-parent' ).forEach( function ( parent ) {
            var panel = parent.querySelector( ':scope > .deprat-mega-panel' );
            if ( ! panel ) { return; }
            function position() {
                if ( window.matchMedia( '(max-width: 921px)' ).matches ) {
                    panel.style.removeProperty( '--deprat-mega-top' );
                    return;
                }
                // Déplier le méga-menu sur toute la largeur du viewport,
                // immédiatement sous la ligne de navigation principale Astra.
                var nav = parent.closest( '.ast-primary-header-bar, .main-header-bar, .site-header-primary-section, .ast-builder-grid-row' );
                if ( ! nav ) { nav = parent; }
                var bottom = Math.max( 0, Math.round( nav.getBoundingClientRect().bottom ) );
                // Un menu fixe ne doit jamais recouvrir la barre d'administration.
                panel.style.setProperty( '--deprat-mega-top', bottom + 'px' );
            }
            parent.addEventListener( 'mouseenter', position );
            parent.addEventListener( 'focusin', position );
            parent.addEventListener( 'touchstart', position, { passive: true } );
            window.addEventListener( 'resize', position, { passive: true } );
            window.addEventListener( 'scroll', position, { passive: true } );
            position();
        } );
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); }
    else { init(); }
} )();
