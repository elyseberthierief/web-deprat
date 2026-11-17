( function ( $ ) {
    'use strict';
    $( function () {
        var app = document.querySelector( '[data-deprat-category-tree]' );
        if ( ! app || ! window.depratCategoryTree ) { return; }
        var config = window.depratCategoryTree;
        var root = app.querySelector( '.deprat-tree-root' );
        var form = app.querySelector( '[data-tree-create-form]' );
        var formName = app.querySelector( '[data-tree-create-name]' );
        var formParent = app.querySelector( '[data-tree-create-parent]' );
        var formTitle = app.querySelector( '[data-tree-create-title]' );
        var formError = app.querySelector( '[data-tree-create-error]' );
        var overlay = app.querySelector( '[data-tree-create-overlay]' );
        var formSection = app.querySelector( '[data-tree-create-section]' );
        var formContext = app.querySelector( '[data-tree-create-context]' );
        var parentPicker = app.querySelector( '[data-tree-create-parent-picker]' );
        var parentSelect = app.querySelector( '[data-tree-create-parent-select]' );
        var createTrigger = null;
        var creating = false;
        var status = app.querySelector( '[data-tree-status]' );
        var dirtyLabel = app.querySelector( '[data-tree-unsaved]' );
        var saveButtons = app.querySelectorAll( '[data-tree-save]' );
        var dirty = false;
        var saving = false;

        function message( value, error ) {
            status.textContent = value || '';
            status.classList.toggle( 'is-error', !!error );
            status.classList.toggle( 'is-success', !error && !!value );
        }
        function changed() {
            dirty = true;
            dirtyLabel.hidden = false;
            message( 'Menu modifié — pensez à enregistrer.', false );
            app.dispatchEvent( new CustomEvent( 'deprat-tree-changed' ) );
        }
        function directItems( list ) {
            return Array.from( list.children ).filter( function ( element ) {
                return element.matches( '[data-tree-item]' );
            } );
        }
        function childList( item ) {
            return Array.from( item.children ).find( function ( element ) {
                return element.matches( '[data-tree-list]' );
            } );
        }
        function serialized( list ) {
            return directItems( list ).map( function ( item ) {
                return {
                    id: Number( item.getAttribute( 'data-term-id' ) ),
                    children: serialized( childList( item ) )
                };
            } );
        }
        function itemName( item ) {
            var name = item && item.querySelector( ':scope > .deprat-tree-row [data-tree-name]' );
            return name ? name.textContent.trim() : '';
        }
        function initializeSortable() {
            $( app ).find( '[data-tree-list]' ).each( function () {
                var list = $( this );
                if ( list.hasClass( 'ui-sortable' ) ) { list.sortable( 'refresh' ); return; }
                list.sortable( {
                    items: '> [data-tree-item]',
                    connectWith: '.deprat-tree-list',
                    handle: '[data-drag-handle]',
                    // jQuery UI neutralise les clics sur tous les <button> par défaut.
                    // La poignée est elle-même un bouton : il faut l'autoriser explicitement.
                    cancel: 'input, textarea, select, option, button:not([data-drag-handle])',
                    placeholder: 'deprat-tree-placeholder',
                    tolerance: 'pointer',
                    dropOnEmpty: true,
                    forcePlaceholderSize: true,
                    scroll: true,
                    scrollSensitivity: 35,
                    scrollSpeed: 12,
                    start: function ( event, ui ) {
                        ui.placeholder.height( Math.max( 37, ui.item.children( '.deprat-tree-row' ).outerHeight() ) );
                        app.classList.add( 'is-dragging' );
                    },
                    receive: function ( event, ui ) {
                        // Ne jamais laisser une catégorie devenir sa propre descendante.
                        var destination = this;
                        if ( ui.item[0] === destination.closest( '[data-tree-item]' )
                            || ui.item[0].contains( destination ) ) {
                            $( this ).sortable( 'cancel' );
                            message( 'Une catégorie ne peut pas être sa propre sous-catégorie.', true );
                        }
                    },
                    stop: function () {
                        app.classList.remove( 'is-dragging' );
                        changed();
                    }
                } );
            } );
        }
        function makeButton( label, attr, title ) {
            var button = document.createElement( 'button' );
            button.type = 'button';
            button.className = 'button';
            button.textContent = label;
            button.setAttribute( attr, '' );
            if ( title ) { button.title = title; }
            return button;
        }
        function makeItem( id, name ) {
            var li = document.createElement( 'li' );
            li.className = 'deprat-tree-item';
            li.setAttribute( 'data-tree-item', '' );
            li.setAttribute( 'data-term-id', String( id ) );
            li.setAttribute( 'data-section-guess', 'autres' );
            var row = document.createElement( 'div' );
            row.className = 'deprat-tree-row';
            var handle = makeButton( '⠿', 'data-drag-handle', 'Faire glisser pour réorganiser' );
            handle.className = 'deprat-tree-handle';
            handle.setAttribute( 'aria-label', 'Déplacer ' + name );
            var label = document.createElement( 'div' );
            label.className = 'deprat-tree-label';
            var strong = document.createElement( 'strong' );
            strong.textContent = name;
            strong.setAttribute( 'data-tree-name', '' );
            var hint = document.createElement( 'span' );
            hint.className = 'deprat-tree-meta';
            hint.textContent = '0 produit(s)';
            label.append( strong, hint );
            var actions = document.createElement( 'div' );
            actions.className = 'deprat-tree-actions';
            var moveUp = makeButton( '↑', 'data-tree-up', 'Monter dans cette liste' );
            moveUp.setAttribute( 'aria-label', 'Monter ' + name );
            var moveDown = makeButton( '↓', 'data-tree-down', 'Descendre dans cette liste' );
            moveDown.setAttribute( 'aria-label', 'Descendre ' + name );
            var indent = makeButton( '↳', 'data-tree-indent', 'Ranger dans la catégorie précédente' );
            indent.setAttribute( 'aria-label', 'Augmenter le niveau' );
            var outdent = makeButton( '↰', 'data-tree-outdent', 'Remonter d’un niveau' );
            outdent.setAttribute( 'aria-label', 'Diminuer le niveau' );
            var addChild = makeButton( '+ Sous-catégorie', 'data-tree-add-child' );
            var edit = document.createElement( 'a' );
            edit.className = 'button';
            edit.href = config.editBase + id;
            edit.textContent = 'Modifier';
            var sectionLabel = document.createElement( 'label' );
            sectionLabel.className = 'deprat-tree-section-editor';
            sectionLabel.textContent = 'Rubrique ';
            var sectionSelect = document.createElement( 'select' );
            sectionSelect.setAttribute( 'data-tree-section', '' );
            sectionSelect.setAttribute( 'data-term-id', String( id ) );
            sectionSelect.setAttribute( 'aria-label', 'Rubrique du menu pour ' + name );
            var autoOption = document.createElement( 'option' );
            autoOption.value = '';
            autoOption.textContent = 'Automatique';
            sectionSelect.appendChild( autoOption );
            Object.keys( config.sectionLabels || {} ).forEach( function ( key ) {
                var option = document.createElement( 'option' );
                option.value = key;
                option.textContent = config.sectionLabels[ key ];
                sectionSelect.appendChild( option );
            } );
            sectionLabel.appendChild( sectionSelect );
            actions.append( moveUp, moveDown, indent, outdent, addChild, sectionLabel, edit );
            row.append( handle, label, actions );
            var nested = document.createElement( 'ul' );
            nested.className = 'deprat-tree-list deprat-tree-children';
            nested.setAttribute( 'data-tree-list', '' );
            nested.setAttribute( 'data-parent-id', String( id ) );
            li.append( row, nested );
            return li;
        }

        function closeCreate() {
            if ( creating ) { return; }
            overlay.hidden = true;
            form.hidden = true;
            document.body.classList.remove( 'deprat-tree-modal-open' );
            formError.hidden = true;
            if ( createTrigger && createTrigger.isConnected ) { createTrigger.focus(); }
        }
        function availableParents( section ) {
            var list = app.querySelector( '[data-tree-preview] [data-section-key="' + section + '"]' );
            if ( !list ) { return []; }
            return Array.from( list.querySelectorAll( '[data-preview-item]' ) ).map( function ( item ) {
                var depth = 0;
                var ancestor = item.parentElement.closest( '[data-preview-item]' );
                while ( ancestor ) {
                    depth += 1;
                    ancestor = ancestor.parentElement.closest( '[data-preview-item]' );
                }
                var label = item.querySelector( ':scope > .deprat-section-term-row .deprat-section-term-link' );
                return {
                    id: item.getAttribute( 'data-term-id' ),
                    name: label ? label.textContent.trim() : '',
                    depth: depth
                };
            } ).filter( function ( item ) { return item.depth < 30; } );
        }
        function openCreate( parent, options ) {
            options = options || {};
            var section = options.section && config.sectionLabels[ options.section ] ? options.section : '';
            var isSectionChild = section && options.mode === 'child';
            var names = isSectionChild ? availableParents( section ) : [];
            if ( isSectionChild && !names.length ) {
                message( 'Créez d’abord une catégorie dans cette rubrique.', true );
                return;
            }
            createTrigger = options.trigger || document.activeElement;
            formSection.value = section;
            formParent.value = parent ? parent.getAttribute( 'data-term-id' ) : '0';
            formTitle.textContent = isSectionChild
                ? 'Créer une sous-catégorie'
                : parent ? 'Créer une sous-catégorie de « ' + itemName( parent ) + ' »'
                    : 'Créer une catégorie';
            formContext.hidden = !section;
            formContext.textContent = section ? 'Rubrique : ' + config.sectionLabels[ section ] : '';
            parentPicker.hidden = !isSectionChild;
            parentSelect.replaceChildren();
            if ( isSectionChild ) {
                names.forEach( function ( item ) {
                    var option = document.createElement( 'option' );
                    option.value = item.id;
                    option.textContent = '— '.repeat( item.depth ) + item.name;
                    parentSelect.appendChild( option );
                } );
                formParent.value = names[ 0 ].id;
            }
            formName.value = '';
            formError.hidden = true;
            formError.textContent = '';
            overlay.hidden = false;
            form.hidden = false;
            document.body.classList.add( 'deprat-tree-modal-open' );
            formName.focus();
        }
        parentSelect.addEventListener( 'change', function () {
            formParent.value = parentSelect.value;
        } );
        app.addEventListener( 'deprat-tree-create-from-section', function ( event ) {
            var info = event.detail || {};
            if ( !config.sectionLabels[ info.section ] ) { return; }
            openCreate( null, { section: info.section, mode: info.mode, trigger: info.trigger } );
        } );
        app.addEventListener( 'deprat-tree-preview-changed', function () {
            initializeSortable();
            changed();
        } );
        app.addEventListener( 'change', function ( event ) {
            if ( event.target.matches( '[data-tree-section]' ) ) { changed(); }
        } );
        app.addEventListener( 'click', function ( event ) {
            var addRoot = event.target.closest( '[data-tree-create-root]' );
            if ( addRoot ) { openCreate( null, { trigger: addRoot } ); return; }
            var addChild = event.target.closest( '[data-tree-add-child]' );
            if ( addChild ) { openCreate( addChild.closest( '[data-tree-item]' ), { trigger: addChild } ); return; }
            var up = event.target.closest( '[data-tree-up]' );
            var down = event.target.closest( '[data-tree-down]' );
            if ( up || down ) {
                var moved = ( up || down ).closest( '[data-tree-item]' );
                var neighbor = up ? moved.previousElementSibling : moved.nextElementSibling;
                if ( !neighbor || !neighbor.matches( '[data-tree-item]' ) ) {
                    message( up ? 'Cette catégorie est déjà en première position.' : 'Cette catégorie est déjà en dernière position.', true );
                    return;
                }
                if ( up ) { neighbor.before( moved ); }
                else { neighbor.after( moved ); }
                initializeSortable();
                changed();
                return;
            }
            var indent = event.target.closest( '[data-tree-indent]' );
            if ( indent ) {
                var item = indent.closest( '[data-tree-item]' );
                var previous = item.previousElementSibling;
                if ( !previous || !previous.matches( '[data-tree-item]' ) ) {
                    message( 'Placez d’abord cette catégorie sous une autre pour pouvoir l’imbriquer.', true );
                    return;
                }
                childList( previous ).appendChild( item );
                initializeSortable();
                changed();
                return;
            }
            var outdent = event.target.closest( '[data-tree-outdent]' );
            if ( outdent ) {
                var child = outdent.closest( '[data-tree-item]' );
                var parentItem = child.parentElement.closest( '[data-tree-item]' );
                if ( !parentItem ) {
                    message( 'Cette catégorie est déjà au niveau principal.', true );
                    return;
                }
                parentItem.after( child );
                initializeSortable();
                changed();
            }
        } );
        overlay.addEventListener( 'click', function ( event ) {
            if ( event.target === overlay || event.target.closest( '[data-tree-create-cancel]' ) ) {
                closeCreate();
            }
        } );
        overlay.addEventListener( 'keydown', function ( event ) {
            if ( event.key === 'Escape' ) { event.preventDefault(); closeCreate(); return; }
            if ( event.key !== 'Tab' ) { return; }
            var focusable = Array.from( form.querySelectorAll( 'button, input:not([type="hidden"]), select' ) )
                .filter( function ( field ) { return !field.disabled && field.getClientRects().length > 0; } );
            if ( !focusable.length ) { return; }
            var first = focusable[ 0 ];
            var last = focusable[ focusable.length - 1 ];
            if ( event.shiftKey && document.activeElement === first ) {
                event.preventDefault(); last.focus();
            } else if ( !event.shiftKey && document.activeElement === last ) {
                event.preventDefault(); first.focus();
            }
        } );
        function post( action, data ) {
            var body = new URLSearchParams( data || {} );
            body.set( 'action', action );
            body.set( 'nonce', config.nonce );
            return fetch( config.ajaxUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            } ).then( function ( response ) {
                return response.json().then( function ( json ) {
                    if ( ! response.ok || ! json.success ) {
                        throw new Error( json && json.data && json.data.message
                            ? json.data.message : 'L’enregistrement n’a pas abouti.' );
                    }
                    return json.data || {};
                } );
            } );
        }
        form.addEventListener( 'submit', function ( event ) {
            event.preventDefault();
            var name = formName.value.trim();
            if ( !name ) { formName.focus(); return; }
            var parentId = Number( formParent.value ) || 0;
            var section = formSection.value;
            var parent = parentId
                ? app.querySelector( '[data-tree-item][data-term-id="' + parentId + '"]' ) : null;
            var destination = parent ? childList( parent ) : root;
            if ( !destination ) {
                formError.textContent = 'Catégorie parente introuvable. Rechargez la page.';
                formError.hidden = false;
                return;
            }
            var submit = app.querySelector( '[data-tree-create-submit]' );
            if ( parentId && !parent ) {
                formError.textContent = 'Catégorie parente introuvable. Rechargez la page.';
                formError.hidden = false;
                return;
            }
            creating = true;
            submit.disabled = true;
            formError.hidden = true;
            post( 'deprat_category_tree_create', {
                name: name,
                parent: String( parentId ),
                section: parentId ? '' : section
            } )
                .then( function ( response ) {
                    var created = makeItem( response.id, response.name );
                    created.setAttribute( 'data-section-guess', response.sectionGuess || 'autres' );
                    if ( !parentId && section ) {
                        var sectionSelect = created.querySelector( '[data-tree-section]' );
                        sectionSelect.value = section;
                    }
                    destination.appendChild( created );
                    initializeSortable();
                    // Le parent doit rester ouvert pour voir la sous-catégorie créée.
                    if ( parentId ) {
                        app.dispatchEvent( new CustomEvent( 'deprat-tree-expand-parent', {
                            detail: { id: String( parentId ) }
                        } ) );
                    }
                    creating = false;
                    closeCreate();
                    changed();
                    message( 'Catégorie créée dans le menu. Enregistrez le méga-menu pour conserver l’ordre.', false );
                } ).catch( function ( error ) {
                    formError.textContent = error.message;
                    formError.hidden = false;
                } ).finally( function () { creating = false; submit.disabled = false; } );
        } );
        function save() {
            if ( saving ) { return; }
            var tree = serialized( root );
            var sections = {};
            app.querySelectorAll( '[data-tree-section]' ).forEach( function ( select ) {
                var id = Number( select.getAttribute( 'data-term-id' ) );
                if ( id > 0 ) { sections[ id ] = select.value; }
            } );
            saving = true;
            saveButtons.forEach( function ( button ) { button.disabled = true; button.textContent = 'Enregistrement…'; } );
            message( 'Enregistrement de votre arborescence…', false );
            post( 'deprat_category_tree_save', { tree: JSON.stringify( tree ), sections: JSON.stringify( sections ) } )
                .then( function ( response ) {
                    dirty = false;
                    dirtyLabel.hidden = true;
                    message( response.message || 'Méga-menu enregistré.', false );
                } ).catch( function ( error ) {
                    message( error.message, true );
                } ).finally( function () {
                    saving = false;
                    saveButtons.forEach( function ( button ) { button.disabled = false; button.textContent = 'Enregistrer le méga-menu'; } );
                } );
        }
        saveButtons.forEach( function ( button ) { button.addEventListener( 'click', save ); } );
        window.addEventListener( 'beforeunload', function ( event ) {
            if ( !dirty || saving ) { return; }
            event.preventDefault();
            event.returnValue = '';
        } );
        initializeSortable();
    } );
} )( jQuery );
