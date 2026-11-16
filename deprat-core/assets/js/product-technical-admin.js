( function () {
    'use strict';

    function start() {
        var editor = document.querySelector( '.deprat-tech-editor' );
        if ( ! editor ) {
            return;
        }

        function nextIndex( container, type ) {
            var highest = -1;
            var pattern = new RegExp( '\\[' + type + '\\]\\[(\\d+)\\]' );
            container.querySelectorAll( '[name]' ).forEach( function ( input ) {
                var match = input.name.match( pattern );
                if ( match ) {
                    highest = Math.max( highest, Number( match[ 1 ] ) );
                }
            } );
            return highest + 1;
        }

        editor.addEventListener( 'click', function ( event ) {
            var add = event.target.closest( '[data-add-row]' );
            if ( add && editor.contains( add ) ) {
                var type = add.getAttribute( 'data-add-row' );
                var container = editor.querySelector( '[data-rows="' + type + '"]' );
                var template = editor.querySelector( '[data-row-template="' + type + '"]' );
                if ( ! container || ! template ) {
                    return;
                }
                var index = nextIndex( container, type );
                var row = template.content.cloneNode( true );
                row.querySelectorAll( '[name]' ).forEach( function ( input ) {
                    input.name = input.name.replace( '__index__', String( index ) );
                } );
                container.appendChild( row );
                var added = container.lastElementChild;
                if ( added ) {
                    var firstInput = added.querySelector( 'input' );
                    if ( firstInput ) {
                        firstInput.focus();
                    }
                }
                return;
            }

            var remove = event.target.closest( '[data-remove-row]' );
            if ( remove && editor.contains( remove ) ) {
                var existingRow = remove.closest( '[data-row]' );
                if ( existingRow ) {
                    existingRow.remove();
                }
                return;
            }

            var mediaRemove = event.target.closest( '[data-media-remove]' );
            if ( mediaRemove && editor.contains( mediaRemove ) ) {
                var removeCard = mediaRemove.closest( '[data-media-kind]' );
                removeCard.querySelector( '[data-media-id]' ).value = '';
                removeCard.querySelector( '[data-media-preview]' ).textContent = 'Aucun fichier sélectionné';
                removeCard.querySelector( '[data-media-select]' ).textContent = 'Ajouter';
                mediaRemove.hidden = true;
                return;
            }

            var mediaSelect = event.target.closest( '[data-media-select]' );
            if ( ! mediaSelect || ! editor.contains( mediaSelect ) || ! window.wp || ! window.wp.media ) {
                return;
            }
            var card = mediaSelect.closest( '[data-media-kind]' );
            var kind = card.getAttribute( 'data-media-kind' );
            var frame = window.wp.media( {
                title: kind === 'pdf' ? 'Choisir une fiche technique PDF' : 'Choisir un schéma',
                button: { text: 'Utiliser ce fichier' },
                library: { type: kind === 'pdf' ? 'application/pdf' : 'image' },
                multiple: false
            } );
            frame.on( 'select', function () {
                var selected = frame.state().get( 'selection' ).first();
                if ( ! selected ) {
                    return;
                }
                var file = selected.toJSON();
                if ( ( kind === 'pdf' && file.mime !== 'application/pdf' ) || ( kind === 'image' && file.type !== 'image' ) ) {
                    window.alert( kind === 'pdf' ? 'Sélectionnez un fichier PDF.' : 'Sélectionnez une image.' );
                    return;
                }
                card.querySelector( '[data-media-id]' ).value = file.id;
                var preview = card.querySelector( '[data-media-preview]' );
                preview.textContent = '';
                if ( kind === 'image' ) {
                    var img = document.createElement( 'img' );
                    img.src = file.sizes && file.sizes.medium ? file.sizes.medium.url : file.url;
                    img.alt = file.alt || 'Aperçu du schéma';
                    preview.appendChild( img );
                } else {
                    var link = document.createElement( 'a' );
                    link.href = file.url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = file.filename || file.title || 'Fiche technique PDF';
                    preview.appendChild( link );
                }
                mediaSelect.textContent = 'Remplacer';
                card.querySelector( '[data-media-remove]' ).hidden = false;
            } );
            frame.open();
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', start );
    } else {
        start();
    }
} )();
