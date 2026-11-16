( function () {
    'use strict';
    function initProduct() {
        var product = document.querySelector( 'body.deprat-client-product div.product' );
        if ( ! product ) { return; }
        var card = product.querySelector( '.deprat-client-quote' );
        var quantity = card ? card.querySelector( 'form.cart input.qty' ) : null;
        var calculator = card ? card.querySelector( '[data-package-calculator]' ) : null;

        function readQuantity() {
            if ( ! quantity ) { return 1; }
            var n = Number( quantity.value );
            return Number.isSafeInteger( n ) && n > 0 ? n : 1;
        }
        var packageChoice = card ? card.querySelector( '[data-package-choice]' ) : null;
        var unitaryWarning = card ? card.querySelector( '[data-unitary-warning]' ) : null;
        var quantityLabel = null;
        var packageRows = [];
        if ( calculator && calculator.dataset.packages ) {
            try { packageRows = JSON.parse( calculator.dataset.packages ); }
            catch ( error ) { packageRows = []; }
        }
        function displayCalc() {
            if ( ! calculator ) { return; }
            var qty = readQuantity();
            var unitary = !!( packageChoice && packageChoice.value === 'unitary' );
            if ( unitaryWarning ) { unitaryWarning.hidden = ! unitary; }
            if ( quantityLabel ) {
                quantityLabel.textContent = unitary ? 'Nombre de pièces souhaitées' : 'Quantité de conditionnements';
            }
            if ( unitary ) {
                calculator.textContent = '';
                calculator.appendChild( document.createTextNode( 'Votre demande : ' ) );
                var unitaryStrong = document.createElement( 'strong' );
                unitaryStrong.textContent = qty.toLocaleString( 'fr-FR' ) + ( qty > 1 ? ' pièces à l’unité' : ' pièce à l’unité' );
                calculator.appendChild( unitaryStrong );
                calculator.appendChild( document.createTextNode( ' (conditionnement non standard).' ) );
                return;
            }
            if ( ! packageRows.length ) { return; }
            var index = packageChoice ? Number( packageChoice.value ) : 0;
            var row = packageRows[index] || packageRows[0];
            calculator.textContent = '';
            var strong = document.createElement( 'strong' );
            var number = value => new Intl.NumberFormat( 'fr-FR', { maximumFractionDigits: 3 } ).format( value );
            if ( row.mode === 'length' ) {
                strong.textContent = number( row.total_m * qty ) + ' m';
                calculator.appendChild( document.createTextNode( 'Votre demande : ' + qty + ' × ' + number( row.total_m ) + ' m, soit ' ) );
                calculator.appendChild( strong );
                calculator.appendChild( document.createTextNode( ' livrés en ' + ( row.sections * qty ) + ' longueurs de ' + number( row.length_m ) + ' m.' ) );
            } else {
                var cartons = qty * row.count;
                var label = row.type + ( cartons > 1 && ! /[sxz]$/i.test( row.type ) ? 's' : '' );
                strong.textContent = number( cartons * row.pieces ) + ' pièces';
                calculator.appendChild( document.createTextNode( 'Votre demande : ' + qty + ' × ' + row.count + ' ' + label + ' de ' + row.pieces + ' pièces, soit ' ) );
                calculator.appendChild( strong );
                calculator.appendChild( document.createTextNode( '.' ) );
            }
        }
        if ( packageChoice ) { packageChoice.addEventListener( 'change', displayCalc ); }
        displayCalc();

        if ( quantity ) {
            var wrap = quantity.closest( '.quantity' );
            if ( wrap ) {
                // Certains thèmes (Astra notamment) ajoutent leurs propres + / −
                // après le chargement de WooCommerce. Garder une seule paire DEPRAT.
                function clearThemeQuantityButtons() {
                    wrap.querySelectorAll( 'button, input[type="button"], a' ).forEach( function (control) {
                        if ( control.classList.contains( 'deprat-qty-button' ) ) { return; }
                        var text = ( control.value || control.textContent || '' ).trim();
                        var nativeQuantity = control.matches( '.plus, .minus, .qty-plus, .qty-minus, .quantity-plus, .quantity-minus' ) ||
                            ( /^[+−-]$/.test( text ) && ! control.classList.contains( 'single_add_to_cart_button' ) );
                        if ( nativeQuantity ) { control.remove(); }
                    } );
                }
                clearThemeQuantityButtons();
                if ( window.MutationObserver ) {
                    new MutationObserver( clearThemeQuantityButtons ).observe( wrap, { childList: true, subtree: true } );
                }
                quantityLabel = document.createElement( 'label' );
                quantityLabel.className = 'deprat-client-quantity-label';
                if ( ! quantity.id ) { quantity.id = 'deprat-quote-quantity'; }
                quantityLabel.setAttribute( 'for', quantity.id );
                wrap.parentNode.insertBefore( quantityLabel, wrap );
                var minus = document.createElement( 'button' );
                minus.type = 'button';
                minus.className = 'deprat-qty-button';
                minus.textContent = '−';
                minus.setAttribute( 'aria-label', 'Diminuer la quantité' );
                var plus = document.createElement( 'button' );
                plus.type = 'button';
                plus.className = 'deprat-qty-button';
                plus.textContent = '+';
                plus.setAttribute( 'aria-label', 'Augmenter la quantité' );
                wrap.insertBefore( minus, quantity );
                quantity.after( plus );
                function adjust( direction ) {
                    var current = readQuantity();
                    var step = parseInt( quantity.step, 10 ) || 1;
                    var min = Math.max( 1, parseInt( quantity.min, 10 ) || 1 );
                    var max = quantity.max ? parseInt( quantity.max, 10 ) : Infinity;
                    var next = Math.min( max, Math.max( min, current + direction * step ) );
                    quantity.value = String( next );
                    quantity.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                    displayCalc();
                }
                minus.addEventListener( 'click', function () { adjust( -1 ); } );
                plus.addEventListener( 'click', function () { adjust( 1 ); } );
            }
            quantity.addEventListener( 'input', displayCalc );
            quantity.addEventListener( 'change', displayCalc );
            displayCalc();
        }

        // L'endpoint natif WooCommerce renvoie les fragments de mini-panier ;
        // on ne quitte pas la fiche pour ajouter à la demande de devis.
        if ( card ) {
            var form = card.querySelector( 'form.cart' );
            var feedback = card.querySelector( '[data-quote-feedback]' );
            var settings = window.depratClientProduct || {};
            if ( form && form.classList.contains( 'cart' ) && ! form.classList.contains( 'variations_form' ) && settings.ajaxUrl ) {
                form.addEventListener( 'submit', function ( event ) {
                    var button = form.querySelector( '.single_add_to_cart_button' );
                    if ( ! button || button.disabled ) { return; }
                    var id = form.querySelector( '[name="add-to-cart"]' );
                    var productId = id ? id.value : button.value;
                    if ( ! /^\d+$/.test( String( productId || '' ) ) ) { return; }
                    event.preventDefault();
                    var body = new URLSearchParams();
                    body.set( 'product_id', String( productId ) );
                    body.set( 'quantity', String( readQuantity() ) );
                    if ( packageChoice ) { body.set( 'deprat_package_choice', packageChoice.value ); }
                    feedback.hidden = true;
                    button.disabled = true;
                    var previous = button.textContent;
                    button.textContent = 'Ajout en cours…';
                    fetch( settings.ajaxUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: body.toString()
                    } ).then( function ( response ) {
                        if ( ! response.ok ) { throw new Error( 'Le serveur a refusé la demande.' ); }
                        return response.json();
                    } ).then( function ( response ) {
                        if ( response.error ) { throw new Error( 'Le produit n’a pas pu être ajouté. Vérifiez le stock et la quantité.' ); }
                        if ( response.fragments ) {
                            Object.keys( response.fragments ).forEach( function ( selector ) {
                                document.querySelectorAll( selector ).forEach( function ( node ) {
                                    var template = document.createElement( 'template' );
                                    template.innerHTML = response.fragments[selector].trim();
                                    if ( template.content.firstElementChild ) { node.replaceWith( template.content.firstElementChild.cloneNode( true ) ); }
                                } );
                            } );
                        }
                        if ( window.jQuery ) {
                            window.jQuery( document.body ).trigger( 'added_to_cart', [ response.fragments || {}, response.cart_hash || '', window.jQuery( button ) ] );
                        }
                        feedback.textContent = 'Produit ajouté à votre demande de devis.';
                        feedback.className = 'deprat-client-quote-feedback is-success';
                        feedback.hidden = false;
                    } ).catch( function ( error ) {
                        feedback.textContent = error.message || 'Erreur de connexion. Veuillez réessayer.';
                        feedback.className = 'deprat-client-quote-feedback is-error';
                        feedback.hidden = false;
                    } ).finally( function () {
                        button.disabled = false;
                        button.textContent = previous;
                    } );
                } );
            }
        }

        // Les trois onglets remplacent les onglets WooCommerce redondants.
        var tabs = product.querySelector( '[data-deprat-tabs]' );
        if ( tabs ) {
            var buttons = Array.from( tabs.querySelectorAll( '[role="tab"]' ) );
            var panels = Array.from( tabs.querySelectorAll( '[role="tabpanel"]' ) );
            function activate( button, focus ) {
                var target = button.getAttribute( 'data-tab-target' );
                buttons.forEach( function ( item ) {
                    var selected = item === button;
                    item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
                    item.tabIndex = selected ? 0 : -1;
                } );
                panels.forEach( function ( panel ) { panel.hidden = panel.dataset.tabPanel !== target; } );
                if ( focus ) { button.focus(); }
            }
            buttons.forEach( function ( button, index ) {
                button.addEventListener( 'click', function () { activate( button, false ); } );
                button.addEventListener( 'keydown', function ( event ) {
                    var next = index;
                    if ( event.key === 'ArrowRight' ) { next = ( index + 1 ) % buttons.length; }
                    else if ( event.key === 'ArrowLeft' ) { next = ( index + buttons.length - 1 ) % buttons.length; }
                    else if ( event.key === 'Home' ) { next = 0; }
                    else if ( event.key === 'End' ) { next = buttons.length - 1; }
                    else { return; }
                    event.preventDefault();
                    activate( buttons[next], true );
                } );
            } );
        }

        var dialog = product.querySelector( '[data-contact-dialog]' );
        var open = product.querySelector( '[data-contact-open]' );
        if ( dialog && open ) {
            var closer = dialog.querySelector( '.deprat-client-contact-close' );
            function closeDialog() { dialog.hidden = true; open.focus(); }
            open.addEventListener( 'click', function () {
                dialog.hidden = false;
                if ( closer ) { closer.focus(); }
            } );
            dialog.querySelectorAll( '[data-contact-close]' ).forEach( function ( item ) {
                item.addEventListener( 'click', closeDialog );
            } );
            document.addEventListener( 'keydown', function ( event ) {
                if ( event.key === 'Escape' && ! dialog.hidden ) { closeDialog(); }
            } );
        }
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', initProduct ); }
    else { initProduct(); }
} )();
