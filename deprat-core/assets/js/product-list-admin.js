( function () {
    'use strict';

    function initialize() {
        var form = document.getElementById( 'posts-filter' );
        var template = document.getElementById( 'deprat-products-layout-template' );
        if ( !form || !template || !template.content ) { return; }
        var layout = template.content.firstElementChild.cloneNode( true );
        form.insertBefore( layout, form.firstChild );
        document.body.classList.add( 'deprat-product-list-active' );

        // Les boutons Importer / Exporter et la recherche sont ceux de WooCommerce
        // et WordPress, déplacés dans notre bandeau sans changer leur action.
        var headerActions = layout.querySelector( '[data-deprat-native-actions]' );
        var headerSearch = layout.querySelector( '[data-deprat-search-destination]' );
        var wrap = form.closest( '.wrap' );
        if ( wrap ) {
            Array.from( wrap.querySelectorAll( ':scope > a.page-title-action' ) ).forEach( function ( link ) {
                if ( /import|export/i.test( link.textContent ) ) {
                    headerActions.appendChild( link );
                }
            } );
        }
        // Également compatible avec le WooCommerce qui insère des actions
        // différentes (sans inventer de liens d'import/export).
        var nativeSearch = form.querySelector( ':scope > .search-box' ) || form.querySelector( 'p.search-box' );
        if ( nativeSearch ) {
            headerSearch.appendChild( nativeSearch );
            var input = nativeSearch.querySelector( '#post-search-input' );
            if ( input ) {
                input.placeholder = 'Rechercher un produit…';
                input.setAttribute( 'aria-label', 'Rechercher parmi tous les produits' );
            }
        }

        function submitFilters() {
            var paged = form.querySelector( '[name="paged"]' );
            if ( paged ) { paged.value = '1'; }
            if ( typeof form.requestSubmit === 'function' ) { form.requestSubmit(); }
            else { form.submit(); }
        }
        layout.querySelectorAll( '[data-deprat-filter]' ).forEach( function ( select ) {
            select.addEventListener( 'change', submitFilters );
        } );

        // Le champ secondaire filtre les lignes DÉJÀ visibles (pagination
        // WordPress inchangée). La recherche globale reste en haut à droite.
        var quickSearch = layout.querySelector( '[data-deprat-list-search]' );
        var displayCount = layout.querySelector( '[data-deprat-visible-count]' );
        var rows = Array.from( form.querySelectorAll( '#the-list > tr' ) ).filter( function ( row ) {
            return !row.classList.contains( 'no-items' );
        } );
        function updateVisibleRows() {
            var query = ( quickSearch.value || '' ).trim().toLocaleLowerCase( 'fr-FR' );
            var number = 0;
            rows.forEach( function ( row ) {
                var name = row.textContent.toLocaleLowerCase( 'fr-FR' );
                var visible = !query || name.includes( query );
                row.hidden = !visible;
                row.style.display = visible ? '' : 'none';
                if ( visible ) { number++; }
            } );
            displayCount.textContent = number + ' produit' + ( number > 1 ? 's' : '' )
                + ( query ? ' affiché' + ( number > 1 ? 's' : '' ) : '' );
        }
        if ( quickSearch ) {
            quickSearch.addEventListener( 'input', updateVisibleRows );
            updateVisibleRows();
        }

        // Le volet natif des actions à 3 points peut être ouvert à la souris
        // ou au clavier ; un autre volet se referme automatiquement.
        form.querySelectorAll( '.deprat-products-row-menu' ).forEach( function ( menu ) {
            menu.addEventListener( 'toggle', function () {
                if ( menu.open ) {
                    form.querySelectorAll( '.deprat-products-row-menu[open]' ).forEach( function ( other ) {
                        if ( other !== menu ) { other.open = false; }
                    } );
                }
            } );
        } );
        document.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '.deprat-products-row-menu' ) ) { return; }
            form.querySelectorAll( '.deprat-products-row-menu[open]' ).forEach( function ( node ) {
                node.open = false;
            } );
        } );

        var selectedSection = layout.querySelector( '.deprat-products-sections .is-active' );
        if ( selectedSection && typeof selectedSection.scrollIntoView === 'function' ) {
            selectedSection.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initialize );
    } else {
        initialize();
    }
} )();
