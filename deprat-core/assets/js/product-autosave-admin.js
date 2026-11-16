( function () {
    'use strict';
    function setupAutosave() {
        var editor = document.querySelector( '.deprat-tech-editor' );
        var form = document.getElementById( 'post' );
        var config = window.depratAutoSaveConfig || {};
        var id = Number( config.postId || ( document.getElementById( 'post_ID' ) || {} ).value );
        var status = config.postStatus || '';
        if ( ! editor || ! form || ! id || ! config.nonce || ! config.ajaxUrl ) { return; }
        var canAutosave = [ 'auto-draft', 'draft', 'pending' ].indexOf( status ) !== -1;
        var feedback = editor.querySelector( '[data-autosave-status]' );
        var publish = editor.querySelector( '[data-product-publish]' );
        var timer = null, pending = false, dirty = false, queued = false;

        function announce( text, error ) {
            if ( ! feedback ) { return; }
            feedback.textContent = text;
            feedback.classList.toggle( 'is-error', !!error );
        }
        if ( publish ) { publish.hidden = ! canAutosave; }
        if ( ! canAutosave ) {
            announce( 'Produit déjà publié : les modifications restent manuelles.' );
            return;
        }
        announce( 'Brouillon — sauvegarde automatique activée' );

        function requestSave() {
            if ( pending ) { queued = true; return Promise.resolve( false ); }
            if ( ! dirty ) { return Promise.resolve( true ); }
            pending = true;
            dirty = false;
            announce( 'Enregistrement du brouillon…' );
            var payload = new FormData( form );
            payload.set( 'action', 'deprat_product_draft_autosave' );
            payload.set( 'autosave_nonce', config.nonce );
            payload.set( 'product_id', String( id ) );
            var titleField = editor.querySelector( '[data-product-name]' ) || document.getElementById( 'title' );
            payload.set( 'deprat_auto_title', titleField ? titleField.value : '' );
            return fetch( config.ajaxUrl, {
                method: 'POST', credentials: 'same-origin', body: payload
            } ).then( function ( response ) { return response.json(); } ).then( function ( response ) {
                if ( ! response.success ) {
                    throw new Error( response.data && response.data.message ? response.data.message : 'Enregistrement impossible.' );
                }
                status = 'draft';
                // Le bouton WordPress Publier reste l'unique chemin de publication manuelle.
                var wpStatus = document.getElementById( 'post_status' );
                var originalStatus = document.getElementById( 'original_post_status' );
                if ( wpStatus ) { wpStatus.value = 'draft'; }
                if ( originalStatus ) { originalStatus.value = 'draft'; }
                announce( 'Brouillon enregistré automatiquement à ' + response.data.time );
                return true;
            } ).catch( function ( error ) {
                dirty = true;
                announce( 'Non enregistré — ' + error.message, true );
                return false;
            } ).finally( function () {
                pending = false;
                if ( queued || dirty ) {
                    queued = false;
                    schedule( 5000 );
                }
            } );
        }
        function schedule( delay ) {
            if ( timer ) { clearTimeout( timer ); }
            timer = setTimeout( requestSave, delay || 3500 );
        }
        function changed( event ) {
            if ( ! event.target || event.target.matches( '[data-product-publish]' ) ) { return; }
            // Éviter de surveiller des champs de pilotage de WordPress ou des nonces.
            if ( event.target.name && /nonce|^action$|^post_status$/.test( event.target.name ) ) { return; }
            dirty = true;
            announce( 'Modifications en attente d’enregistrement…' );
            schedule( 3500 );
        }
        form.addEventListener( 'input', changed );
        form.addEventListener( 'change', changed );
        // Une création de catégorie, de schéma ou de fichier change le DOM ; l'enregistrer également.
        editor.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-package-add],[data-package-delete],[data-diagram-add],[data-diagram-delete],[data-datasheet-add],[data-datasheet-delete],[data-gallery-add],[data-category-submit]' ) ) {
                dirty = true;
                schedule( 3500 );
            }
        } );
        window.depratDraftAutosave = {
            saveNow: function () { dirty = true; clearTimeout( timer ); return requestSave(); }
        };

        if ( publish ) {
            publish.addEventListener( 'click', async function () {
                if ( ! window.confirm( 'La fiche produit est-elle terminée et validée ? Elle sera visible dans la boutique après publication.' ) ) { return; }
                var success = await window.depratDraftAutosave.saveNow();
                if ( ! success ) {
                    window.alert( 'Le brouillon n’a pas pu être enregistré. Corrigez les erreurs avant de publier.' );
                    return;
                }
                var nativePublish = document.getElementById( 'publish' );
                if ( nativePublish ) { nativePublish.click(); }
            } );
        }
        window.addEventListener( 'beforeunload', function ( event ) {
            if ( dirty && ! pending ) {
                event.preventDefault();
                event.returnValue = '';
            }
        } );
    }
    if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', setupAutosave ); }
    else { setupAutosave(); }
} )();
