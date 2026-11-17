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


/* 0.1.33 — accordéons des catégories réelles (menu + page catalogue). */
( function () {
    'use strict';
    document.addEventListener( 'click', function ( event ) {
        var button = event.target.closest( '[data-deprat-section-toggle]' );
        if ( !button ) { return; }
        var id = button.getAttribute( 'aria-controls' );
        var content = id ? document.getElementById( id ) : null;
        if ( !content ) { return; }
        var expanded = button.getAttribute( 'aria-expanded' ) === 'true';
        button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
        content.hidden = expanded;
        var term = button.closest( '.deprat-section-term' );
        var link = term && term.querySelector( ':scope > .deprat-section-term-row > .deprat-section-term-link' );
        var name = link ? link.textContent.trim() : 'cette catégorie';
        button.setAttribute( 'aria-label', ( expanded ? 'Déplier' : 'Replier' ) + ' les sous-catégories de ' + name );
    } );
    document.addEventListener( 'keydown', function ( event ) {
        if ( event.key !== 'Escape' ) { return; }
        var focus = document.activeElement;
        var root = focus && focus.closest( '.deprat-catalog-sections' );
        if ( !root ) { return; }
        root.querySelectorAll( '[data-deprat-section-toggle][aria-expanded="true"]' ).forEach( function ( button ) {
            var content = document.getElementById( button.getAttribute( 'aria-controls' ) );
            button.setAttribute( 'aria-expanded', 'false' );
            if ( content ) { content.hidden = true; }
        } );
    } );
} )();
