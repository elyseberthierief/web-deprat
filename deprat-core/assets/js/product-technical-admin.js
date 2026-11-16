( function () {
    'use strict';


    function setupPackageSummary( editor ) {
        var count = editor.querySelector( '[data-package-count]' );
        var type = editor.querySelector( '[data-package-type]' );
        var pieces = editor.querySelector( '[data-package-pieces]' );
        var result = editor.querySelector( '[data-package-summary]' );
        if ( ! count || ! type || ! pieces || ! result ) { return; }
        function update() {
            var n = Number( count.value ), p = Number( pieces.value );
            var label = type.value.trim();
            if ( ! Number.isSafeInteger( n ) || ! Number.isSafeInteger( p ) || n < 1 || p < 1 || ! label || n > 999999 || p > 99999999 ) {
                result.textContent = 'Renseignez les trois champs pour afficher le récapitulatif.';
                return;
            }
            var plural = n > 1 && ! /[sxz]$/i.test( label ) ? 's' : '';
            result.textContent = 'Le client recevra ' + n + ' ' + label + plural + ' contenant ' + ( n > 1 ? 'chacun ' : '' ) + p + ' ' + ( p > 1 ? 'pièces' : 'pièce' ) + ', soit un total de ' + ( n * p ) + ' pièces.';
        }
        [ count, type, pieces ].forEach( function ( field ) { field.addEventListener( 'input', update ); field.addEventListener( 'change', update ); } );
        update();
    }

    function setupCategoryModal( editor ) {
        var dialog = editor.querySelector( '[data-category-modal]' );
        var name = editor.querySelector( '[data-category-name]' );
        var parent = editor.querySelector( '[data-category-parent]' );
        var error = editor.querySelector( '[data-category-error]' );
        var submit = editor.querySelector( '[data-category-submit]' );
        if ( ! dialog || ! name || ! parent || ! submit ) { return; }

        function close() { dialog.hidden = true; document.body.classList.remove( 'deprat-modal-open' ); }
        function open() {
            dialog.hidden = false;
            document.body.classList.add( 'deprat-modal-open' );
            error.hidden = true;
            name.value = '';
            name.focus();
        }
        function fail( message ) { error.textContent = message; error.hidden = false; }
        function addNativeCategory( id, label, parentId ) {
            var list = document.getElementById( 'product_catchecklist' );
            if ( ! list ) { return; }
            var target = list;
            if ( parentId ) {
                var parentInput = list.querySelector( 'input[value="' + parentId + '"]' );
                if ( parentInput ) {
                    var li = parentInput.closest( 'li' );
                    if ( li ) {
                        target = li.querySelector( 'ul.children' );
                        if ( ! target ) {
                            target = document.createElement( 'ul' );
                            target.className = 'children';
                            li.appendChild( target );
                        }
                    }
                }
            }
            var li = document.createElement( 'li' );
            li.id = 'product_cat-' + id;
            var text = document.createElement( 'label' );
            var checkbox = document.createElement( 'input' );
            checkbox.type = 'checkbox';
            checkbox.name = 'tax_input[product_cat][]';
            checkbox.value = String( id );
            checkbox.checked = true;
            text.appendChild( checkbox );
            text.appendChild( document.createTextNode( ' ' + label ) );
            li.appendChild( text );
            target.appendChild( li );
            list.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        }
        editor.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-category-open]' ) ) { open(); }
            if ( event.target.closest( '[data-category-close]' ) ) { close(); }
        } );
        document.addEventListener( 'keydown', function ( event ) { if ( event.key === 'Escape' && ! dialog.hidden ) { close(); } } );
        submit.addEventListener( 'click', function () {
            var value = name.value.trim();
            if ( ! value ) { fail( 'Renseignez un nom de catégorie.' ); name.focus(); return; }
            if ( ! window.depratProductEditor || ! window.depratProductEditor.ajaxUrl ) { fail( 'Configuration AJAX indisponible.' ); return; }
            submit.disabled = true;
            error.hidden = true;
            var body = new URLSearchParams();
            body.set( 'action', 'deprat_product_category_create' );
            body.set( 'nonce', window.depratProductEditor.categoryNonce );
            body.set( 'name', value );
            body.set( 'parent', parent.value );
            window.fetch( window.depratProductEditor.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            } ).then( function ( response ) { return response.json(); } ).then( function (json) {
                if ( ! json.success || ! json.data ) { fail( json.data && json.data.message ? json.data.message : 'La création a échoué.' ); return; }
                addNativeCategory( json.data.id, json.data.name, json.data.parent );
                var option = document.createElement( 'option' );
                option.value = String( json.data.id );
                option.textContent = json.data.name;
                parent.appendChild( option );
                close();
            } ).catch( function () { fail( 'Erreur réseau. Réessayez.' ); } ).finally( function () { submit.disabled = false; } );
        } );
    }

    function setupDiagrams( editor ) {
        var list = editor.querySelector( '[data-diagrams-list]' );
        if ( ! list ) { return; }
        function renumber() {
            list.querySelectorAll( '[data-diagram-row]' ).forEach( function ( row, index ) {
                var label = row.querySelector( '.deprat-tech-diagram-index' );
                if ( label ) { label.textContent = 'Schéma ' + ( index + 1 ); }
                row.querySelector( '[data-diagram-title]' ).name = 'deprat_product_tech[diagrams][' + index + '][title]';
                row.querySelector( '[data-diagram-image]' ).name = 'deprat_product_tech[diagrams][' + index + '][image]';
            } );
        }
        function choose( row ) {
            if ( ! window.wp || ! window.wp.media ) { return; }
            var frame = window.wp.media( { title: 'Choisir un schéma', button: { text: 'Utiliser cette image' }, library: { type: 'image' }, multiple: false } );
            frame.on( 'select', function () {
                var selected = frame.state().get( 'selection' ).first();
                if ( ! selected ) { return; }
                var file = selected.toJSON();
                if ( file.type !== 'image' ) { return; }
                row.querySelector( '[data-diagram-image]' ).value = file.id;
                var preview = row.querySelector( '[data-diagram-preview]' );
                preview.textContent = '';
                var img = document.createElement( 'img' );
                img.src = file.sizes && file.sizes.medium ? file.sizes.medium.url : file.url;
                img.alt = row.querySelector( '[data-diagram-title]' ).value || 'Schéma technique';
                preview.appendChild( img );
            } );
            frame.open();
        }
        editor.addEventListener( 'click', function ( event ) {
            var add = event.target.closest( '[data-diagram-add]' );
            if ( add ) {
                var row = document.createElement( 'article' );
                row.className = 'deprat-tech-diagram-entry';
                row.setAttribute( 'data-diagram-row', '' );
                row.innerHTML = '<div class="deprat-tech-diagram-toolbar"><span class="deprat-tech-diagram-index"></span><div><button type="button" class="button" data-diagram-up aria-label="Monter le schéma">↑</button> <button type="button" class="button" data-diagram-down aria-label="Descendre le schéma">↓</button> <button type="button" class="button-link-delete" data-diagram-delete>Supprimer</button></div></div><label class="deprat-tech-field"><span>Titre du schéma</span><input data-diagram-title placeholder="Ex. Schéma de raccordement électrique"></label><input type="hidden" data-diagram-image><div class="deprat-tech-media-preview" data-diagram-preview>Aucun schéma sélectionné</div><button type="button" class="button" data-diagram-select>Choisir une image</button>';
                list.appendChild( row ); renumber();
                row.querySelector( '[data-diagram-title]' ).focus();
                return;
            }
            var row = event.target.closest( '[data-diagram-row]' );
            if ( ! row || ! list.contains( row ) ) { return; }
            if ( event.target.closest( '[data-diagram-select]' ) ) { choose( row ); }
            if ( event.target.closest( '[data-diagram-delete]' ) ) { row.remove(); renumber(); }
            if ( event.target.closest( '[data-diagram-up]' ) && row.previousElementSibling ) { list.insertBefore( row, row.previousElementSibling ); renumber(); }
            if ( event.target.closest( '[data-diagram-down]' ) && row.nextElementSibling ) { list.insertBefore( row.nextElementSibling, row ); renumber(); }
        } );
        renumber();
    }


    function setupGallery( editor ) {
        var field = editor.querySelector( '[data-gallery-ids]' );
        var list = editor.querySelector( '[data-gallery-list]' );
        if ( ! field || ! list ) { return; }

        function syncIds() {
            field.value = Array.prototype.map.call( list.querySelectorAll( '[data-gallery-item]' ), function ( item ) {
                return item.getAttribute( 'data-gallery-id' );
            } ).join( ',' );
        }
        function addFile( file ) {
            if ( ! file || file.type !== 'image' || ! file.id ||
                 list.querySelector( '[data-gallery-id="' + Number( file.id ) + '"]' ) ) { return; }
            var item = document.createElement( 'article' );
            item.className = 'deprat-product-gallery-item';
            item.setAttribute( 'data-gallery-item', '' );
            item.setAttribute( 'data-gallery-id', String( file.id ) );
            var picture = document.createElement( 'img' );
            picture.src = file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url;
            picture.alt = file.alt || 'Photo complémentaire du produit';
            var remove = document.createElement( 'button' );
            remove.type = 'button';
            remove.className = 'button-link-delete';
            remove.setAttribute( 'data-gallery-remove', '' );
            remove.textContent = 'Retirer';
            item.appendChild( picture );
            item.appendChild( remove );
            list.appendChild( item );
        }
        editor.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-gallery-remove]' ) ) {
                var item = event.target.closest( '[data-gallery-item]' );
                if ( item && list.contains( item ) ) { item.remove(); syncIds(); }
                return;
            }
            if ( ! event.target.closest( '[data-gallery-add]' ) || ! window.wp || ! window.wp.media ) { return; }
            var frame = window.wp.media( {
                title: 'Photos complémentaires du produit',
                button: { text: 'Ajouter à la galerie' },
                library: { type: 'image' },
                multiple: true
            } );
            frame.on( 'select', function () {
                frame.state().get( 'selection' ).each( function ( attachment ) {
                    addFile( attachment.toJSON() );
                } );
                syncIds();
            } );
            frame.open();
        } );
        syncIds();
    }

    function setupDatasheets( editor ) {
        var list = editor.querySelector( '[data-datasheets-list]' );
        if ( ! list ) { return; }

        function renumber() {
            list.querySelectorAll( '[data-datasheet-row]' ).forEach( function ( row, index ) {
                row.querySelector( 'strong' ).textContent = 'Document ' + ( index + 1 );
                row.querySelector( '[data-datasheet-title]' ).name = 'deprat_product_tech[datasheets][' + index + '][title]';
                row.querySelector( '[data-datasheet-file]' ).name = 'deprat_product_tech[datasheets][' + index + '][file]';
            } );
        }

        function choose( row ) {
            if ( ! window.wp || ! window.wp.media ) { return; }
            var frame = window.wp.media( {
                title: 'Choisir une fiche technique PDF',
                button: { text: 'Ajouter ce PDF' },
                library: { type: 'application/pdf' },
                multiple: false
            } );
            frame.on( 'select', function () {
                var selected = frame.state().get( 'selection' ).first();
                if ( ! selected ) { return; }
                var file = selected.toJSON();
                if ( file.mime !== 'application/pdf' ) {
                    window.alert( 'Sélectionnez un fichier PDF.' );
                    return;
                }
                row.querySelector( '[data-datasheet-file]' ).value = file.id;
                var preview = row.querySelector( '[data-datasheet-preview]' );
                preview.textContent = '';
                var link = document.createElement( 'a' );
                link.textContent = file.filename || file.title || 'Fiche technique PDF';
                link.href = file.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                preview.appendChild( link );
                row.querySelector( '[data-datasheet-select]' ).textContent = 'Remplacer le PDF';
            } );
            frame.open();
        }

        editor.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-datasheet-add]' ) ) {
                var row = document.createElement( 'article' );
                row.className = 'deprat-datasheet-entry';
                row.setAttribute( 'data-datasheet-row', '' );
                row.innerHTML = '<div class="deprat-tech-diagram-toolbar"><strong></strong><button type="button" class="button-link-delete" data-datasheet-delete>Supprimer</button></div><label class="deprat-tech-field"><span>Nom du document</span><input data-datasheet-title placeholder="Ex. Notice de montage"></label><input type="hidden" data-datasheet-file><div class="deprat-tech-media-preview" data-datasheet-preview>Aucun PDF sélectionné</div><button type="button" class="button" data-datasheet-select>Choisir un PDF</button>';
                list.appendChild( row );
                renumber();
                row.querySelector( '[data-datasheet-title]' ).focus();
                return;
            }
            var row = event.target.closest( '[data-datasheet-row]' );
            if ( ! row || ! list.contains( row ) ) { return; }
            if ( event.target.closest( '[data-datasheet-delete]' ) ) { row.remove(); renumber(); return; }
            if ( event.target.closest( '[data-datasheet-select]' ) ) { choose( row ); }
        } );
        renumber();
    }

    function setupAutoGrow( editor ) {
        if ( ! editor.querySelectorAll ) { return; }
        function adjust( textarea ) {
            if ( ! textarea || ! textarea.matches || ! textarea.matches( '[data-auto-grow]' ) ) { return; }
            textarea.style.height = 'auto';
            textarea.style.height = Math.max( 40, textarea.scrollHeight + 3 ) + 'px';
        }
        editor.querySelectorAll( '[data-auto-grow]' ).forEach( adjust );
        editor.addEventListener( 'input', function ( event ) { adjust( event.target ); } );
        if ( window.addEventListener ) {
            window.addEventListener( 'resize', function () {
                editor.querySelectorAll( '[data-auto-grow]' ).forEach( adjust );
            } );
        }
        editor.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-add-row="tests"]' ) ) {
                var list = editor.querySelector( '[data-rows="tests"]' );
                if ( list ) {
                    // L'ajout dynamique est traité après le clic par l'autre gestionnaire.
                    if ( window.requestAnimationFrame ) {
                        window.requestAnimationFrame( function () {
                            list.querySelectorAll( '[data-auto-grow]' ).forEach( adjust );
                        } );
                    }
                }
            }
        } );
    }

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
                if ( type.value !== 'simple' ) {
                    document.body.classList.add( 'deprat-product-editor-advanced' );
                    var advancedToggle = editor.querySelector( '[data-native-toggle]' );
                    if ( advancedToggle ) {
                        advancedToggle.setAttribute( 'aria-expanded', 'true' );
                        advancedToggle.textContent = 'Masquer les autres options WooCommerce';
                    }
                    var productData = document.getElementById( 'woocommerce-product-data' );
                    if ( productData && productData.scrollIntoView ) {
                        productData.scrollIntoView( { block: 'start', behavior: 'auto' } );
                    }
                }
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
        setupPackageSummary( editor );
        setupCategoryModal( editor );
        setupDiagrams( editor );
        setupGallery( editor );
        setupDatasheets( editor );
        setupAutoGrow( editor );

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
