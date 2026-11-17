( function () {
    'use strict';
    function init() {
        var parents = document.querySelectorAll( '.deprat-mega-parent' );
        parents.forEach( function ( parent ) {
            var panel = parent.querySelector( ':scope > .deprat-mega-panel' );
            if ( ! panel ) { return; }
            function position() {
                if ( window.matchMedia && window.matchMedia( '(max-width: 921px)' ).matches ) { return; }
                // Le panneau est positionné dans le LI ; on compense sa position
                // pour qu'il reste dans la largeur de l'écran, même tout à droite.
                var parentRect = parent.getBoundingClientRect();
                var width = panel.offsetWidth;
                if ( ! width ) { return; }
                var ideal = parentRect.left - width * .26;
                var left = Math.max( 12, Math.min( ideal, window.innerWidth - width - 12 ) );
                panel.style.left = Math.round( left - parentRect.left ) + 'px';
            }
            parent.addEventListener( 'mouseenter', position );
            parent.addEventListener( 'focusin', position );
            window.addEventListener( 'resize', position, { passive: true } );
            position();
        } );
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); }
    else { init(); }
} )();
