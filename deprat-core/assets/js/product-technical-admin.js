( function () {
    'use strict';

    function setupProductEditor( editor ) {
        if ( ! document.getElementById ) {
            return;
        }
        var box = document.getElementById( 'deprat-product-technical' );
        var content = document.getElementById( 'post-body-content' );
        var form = document.getElementById( 'post' );
        var nativeTitle = document.getElementById( 'title' );
        var chrome = editor.querySelector( '[data-editor-chrome]' );
        if ( ! box || ! content || ! form || ! nativeTitle || ! chrome || ! form.contains( box ) ) {
            return;
        }

        var title = editor.querySelector( '[data-product-name]' );
        var sku = editor.querySelector( '[data-product-sku]' );
        var type = editor.querySelector( '[data-product-type]' );
        var nativeSku = document.getElementById( '_sku' );
        var nativeType = document.getElementById( 'product-type' );

        function connectText( proxy, original ) {
            if ( ! proxy ) {
                return;
            }
            if ( ! original ) {
                proxy.disabled = true;
                proxy.placeholder = 'Non disponible';
                return;
            }
            proxy.value = original.value;
            proxy.disabled = original.disabled;
            proxy.addEventListener( 'input', function () {
                original.value = proxy.value;
                original.dispatchEvent( new Event( 'input', { bubbles: true } ) );
            } );
            proxy.addEventListener( 'change', function () {
                original.dispatchEvent( new Event( 'change', { bubbles: true } ) );
            } );
            original.addEventListener( 'input', function () { proxy.value = original.value; } );
            original.addEventListener( 'change', function () { proxy.value = original.value; } );
        }
        connectText( title, nativeTitle );
        connectText( sku, nativeSku );

        if ( type && nativeType ) {
            Array.prototype.forEach.call( nativeType.options, function ( option ) {
                type.appendChild( option.cloneNode( true ) );
            } );
            type.value = nativeType.value;
            type.addEventListener( 'change', function () {
                nativeType.value = type.value;
                nativeType.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                if ( nativeSku && sku ) {
                    sku.disabled = nativeSku.disabled;
                }
            } );
            nativeType.addEventListener( 'change', function () { type.value = nativeType.value; } );
        } else if ( type ) {
            type.disabled = true;
        }

        var categoryList = document.getElementById( 'product_catchecklist' );
        var categoryOptions = editor.querySelector( '[data-category-options]' );
        var categorySummary = editor.querySelector( '[data-category-summary]' );
        function refreshCategories() {
            if ( ! categoryOptions || ! categorySummary ) {
                return;
            }
            categoryOptions.textContent = '';
            var chosen = [];
            var seen = {};
            if ( categoryList ) {
                categoryList.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( original ) {
                    if ( seen[ original.value ] ) {
                        return;
                    }
                    seen[ original.value ] = true;
                    var nativeLabel = original.closest( 'label' );
                    var name = nativeLabel ? nativeLabel.textContent.trim() : original.value;
                    var label = document.createElement( 'label' );
                    var proxy = document.createElement( 'input' );
                    var text = document.createElement( 'span' );
                    proxy.type = 'checkbox';
                    proxy.checked = original.checked;
                    text.textContent = name;
                    label.appendChild( proxy );
                    label.appendChild( text );
                    categoryOptions.appendChild( label );
                    if ( original.checked ) {
                        chosen.push( name );
                    }
                    proxy.addEventListener( 'change', function () {
                        document.querySelectorAll( '#product_catdiv input[type="checkbox"]' ).forEach( function ( input ) {
                            if ( input.value === original.value ) {
                                input.checked = proxy.checked;
                            }
                        } );
                        original.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                        refreshCategories();
                    } );
                } );
            }
            if ( ! categoryOptions.children.length ) {
                categoryOptions.textContent = 'Aucune catégorie disponible.';
            }
            categorySummary.textContent = chosen.length ? chosen.join( ', ' ) : 'Choisir une catégorie';
        }
        refreshCategories();
        if ( categoryList ) {
            categoryList.addEventListener( 'change', refreshCategories );
            if ( window.MutationObserver ) {
                new window.MutationObserver( refreshCategories ).observe( categoryList, { childList: true, subtree: true } );
            }
        }

        var originalStatus = document.getElementById( 'post-status-display' );
        var status = editor.querySelector( '[data-product-status]' );
        if ( status ) {
            status.textContent = originalStatus ? originalStatus.textContent.trim() : 'Brouillon';
        }

        var featured = document.getElementById( 'postimagediv' );
        var featuredPanel = editor.querySelector( '[data-featured-panel]' );
        function refreshFeatured() {
            if ( ! featured || ! featuredPanel ) {
                return;
            }
            var preview = featuredPanel.querySelector( '[data-featured-preview]' );
            var image = featured.querySelector( '#set-post-thumbnail img' );
            var select = featuredPanel.querySelector( '[data-featured-select]' );
            var remove = featuredPanel.querySelector( '[data-featured-remove]' );
            preview.textContent = '';
            if ( image ) {
                var copy = document.createElement( 'img' );
                copy.src = image.currentSrc || image.src;
                copy.alt = image.alt || 'Image du produit';
                preview.appendChild( copy );
            } else {
                preview.textContent = 'Aucune image sélectionnée';
            }
            select.textContent = image ? 'Modifier l’image' : 'Ajouter une image';
            remove.hidden = ! image || ! featured.querySelector( '#remove-post-thumbnail' );
        }
        if ( featured && featuredPanel ) {
            refreshFeatured();
            featuredPanel.hidden = false;
            if ( window.MutationObserver ) {
                new window.MutationObserver( refreshFeatured ).observe( featured, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'src', 'srcset' ] } );
            }
        }

        var delivery = editor.querySelector( '[name="deprat_product_tech[delivery]"]' );
        var deliveryProxy = editor.querySelector( '[data-delivery-proxy]' );
        var deliveryMirror = editor.querySelector( '[data-delivery-mirror]' );
        connectText( deliveryProxy, delivery );
        if ( delivery && deliveryProxy && deliveryMirror ) {
            deliveryMirror.hidden = false;
        }

        box.classList.remove( 'closed' );
        content.insertBefore( box, content.firstChild );
        chrome.hidden = false;
        var toggle = editor.querySelector( '[data-native-toggle]' );
        if ( toggle ) {
            toggle.hidden = false;
        }
        document.body.classList.add( 'deprat-product-editor-ready' );
    }

    function start() {
        var editor = document.querySelector( '.deprat-tech-editor' );
        if ( ! editor ) {
            return;
        }
        setupProductEditor( editor );

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
            var save = event.target.closest( '[data-product-save]' );
            if ( save && editor.contains( save ) ) {
                var originalSave = document.getElementById( 'publish' );
                if ( originalSave ) {
                    originalSave.click();
                }
                return;
            }
            var preview = event.target.closest( '[data-product-preview]' );
            if ( preview && editor.contains( preview ) ) {
                var originalPreview = document.getElementById( 'post-preview' );
                if ( originalPreview ) {
                    originalPreview.click();
                }
                return;
            }
            var nativeToggle = event.target.closest( '[data-native-toggle]' );
            if ( nativeToggle && editor.contains( nativeToggle ) ) {
                var expanded = document.body.classList.toggle( 'deprat-product-editor-advanced' );
                nativeToggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
                nativeToggle.textContent = expanded ? 'Masquer les autres options WooCommerce' : 'Afficher les autres options WooCommerce';
                return;
            }
            var featuredSelect = event.target.closest( '[data-featured-select]' );
            if ( featuredSelect && editor.contains( featuredSelect ) ) {
                var nativeFeaturedSelect = document.getElementById( 'set-post-thumbnail' );
                if ( nativeFeaturedSelect ) {
                    nativeFeaturedSelect.click();
                }
                return;
            }
            var featuredRemove = event.target.closest( '[data-featured-remove]' );
            if ( featuredRemove && editor.contains( featuredRemove ) ) {
                var nativeFeaturedRemove = document.getElementById( 'remove-post-thumbnail' );
                if ( nativeFeaturedRemove ) {
                    nativeFeaturedRemove.click();
                }
                return;
            }
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
                var removeKind = removeCard.getAttribute( 'data-media-kind' );
                removeCard.querySelector( '[data-media-preview]' ).textContent = removeKind === 'pdf' ? 'Aucun PDF sélectionné' : 'Aucun schéma sélectionné';
                removeCard.querySelector( '[data-media-select]' ).textContent = removeKind === 'pdf' ? 'Ajouter un PDF' : 'Ajouter un schéma';
                var removeDownload = removeCard.querySelector( '[data-media-download]' );
                if ( removeDownload ) {
                    removeDownload.hidden = true;
                    removeDownload.removeAttribute( 'href' );
                }
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
                mediaSelect.textContent = kind === 'pdf' ? 'Remplacer le PDF' : 'Remplacer le schéma';
                card.querySelector( '[data-media-remove]' ).hidden = false;
                var download = card.querySelector( '[data-media-download]' );
                if ( download ) {
                    download.href = file.url;
                    download.hidden = false;
                }
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
