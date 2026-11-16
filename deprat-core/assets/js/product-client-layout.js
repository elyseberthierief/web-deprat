( function () {
    'use strict';
    function initProduct() {
        var product = document.querySelector( '.deprat-client-product .woocommerce div.product' );
        if ( ! product ) { return; }
        var card = product.querySelector( '.deprat-client-quote' );
        var quantity = card ? card.querySelector( 'form.cart input.qty' ) : null;
        var calculator = card ? card.querySelector( '[data-package-calculator]' ) : null;

        function readQuantity() {
            if ( ! quantity ) { return 1; }
            var n = Number( quantity.value );
            return Number.isSafeInteger( n ) && n > 0 ? n : 1;
        }
        function displayCalc() {
            if ( ! calculator ) { return; }
            var qty = readQuantity();
            var pieces = Number( calculator.dataset.pieces );
            var count = Number( calculator.dataset.count ) || 1;
            var type = ( calculator.dataset.type || '' ).trim();
            if ( ! type || pieces < 1 ) { return; }
            var cartons = qty * count;
            var name = type + ( cartons > 1 && ! /[sxz]$/i.test( type ) ? 's' : '' );
            var total = cartons * pieces;
            calculator.textContent = '';
            var sentence = document.createElement( 'span' );
            sentence.textContent = 'Votre demande : ' + qty + ' × ' + count + ' ' + name +
                ' de ' + pieces + ' ' + ( pieces > 1 ? 'pièces' : 'pièce' ) + ', soit ';
            var strong = document.createElement( 'strong' );
            strong.textContent = total.toLocaleString( 'fr-FR' ) + ' pièces';
            calculator.append( sentence, strong );
            calculator.appendChild( document.createTextNode( '.' ) );
        }

        if ( quantity ) {
            var wrap = quantity.closest( '.quantity' );
            if ( wrap ) {
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
