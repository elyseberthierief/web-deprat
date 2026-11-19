( function ( $ ) {
    'use strict';
    $( function () {
        var app = document.querySelector( '[data-deprat-category-tree]' );
        if ( !app || !window.depratCategoryTree ) { return; }
        var config = window.depratCategoryTree;
        var preview = app.querySelector( '[data-tree-preview]' );
        var sourceRoot = app.querySelector( '.deprat-tree-root' );
        var grid = preview && preview.querySelector( '[data-preview-grid]' );
        if ( !preview || !sourceRoot ) { return; }
        var expanded = new Set();
        var rebuilding = false;

        function directItems( list, selector ) {
            return list ? Array.from( list.children ).filter( function ( item ) {
                return item.matches( selector );
            } ) : [];
        }
        function sourceChildList( item ) {
            return item && item.querySelector( ':scope > [data-tree-list]' );
        }
        function sourceName( item ) {
            var title = item.querySelector( ':scope > .deprat-tree-row [data-tree-name]' );
            return title ? title.textContent.trim() : '';
        }
        function sourceSection( item ) {
            var select = item.querySelector( ':scope > .deprat-tree-row [data-tree-section]' );
            var key = select && select.value ? select.value : item.getAttribute( 'data-section-guess' );
            return config.sectionLabels[ key ] ? key : 'autres';
        }
        function listFor( section ) {
            return preview.querySelector( '[data-section-key="' + section + '"]' );
        }
        function innerList( item ) {
            return item && item.querySelector( ':scope > .deprat-section-children > [data-preview-list]' );
        }
        function makeControl( content, action, title ) {
            var button = document.createElement( 'button' );
            button.type = 'button';
            button.textContent = content;
            button.setAttribute( action, '' );
            button.setAttribute( 'aria-label', title );
            button.title = title;
            return button;
        }
        function buildItem( real, depth ) {
            var id = real.getAttribute( 'data-term-id' );
            var name = sourceName( real );
            var li = document.createElement( 'li' );
            li.className = 'deprat-section-term deprat-preview-item';
            li.setAttribute( 'data-preview-item', '' );
            li.setAttribute( 'data-term-id', id );
            var row = document.createElement( 'div' );
            row.className = 'deprat-section-term-row';
            var drag = makeControl( '⠿', 'data-preview-handle', 'Déplacer ' + name );
            drag.className = 'deprat-preview-handle';
            var label = document.createElement( 'span' );
            label.className = 'deprat-section-term-link';
            label.textContent = name;
            var sourceIcon = real.querySelector( ':scope > .deprat-tree-row [data-tree-icon]' );
            var previewIcon = sourceIcon ? sourceIcon.cloneNode( true ) : null;
            if ( previewIcon ) {
                previewIcon.className = 'deprat-preview-category-icon';
                previewIcon.removeAttribute( 'data-tree-icon' );
            }
            var actions = document.createElement( 'div' );
            actions.className = 'deprat-preview-actions';
            actions.append(
                makeControl( '↑', 'data-preview-up', 'Monter ' + name ),
                makeControl( '↓', 'data-preview-down', 'Descendre ' + name ),
                makeControl( '↳', 'data-preview-indent', 'Ranger sous la catégorie précédente : ' + name ),
                makeControl( '↰', 'data-preview-outdent', 'Remonter d’un niveau : ' + name )
            );
            if ( depth === 0 ) {
                var selection = document.createElement( 'select' );
                selection.setAttribute( 'data-preview-section-select', '' );
                selection.setAttribute( 'aria-label', 'Déplacer ' + name + ' vers une rubrique' );
                Object.keys( config.sectionLabels ).forEach( function ( key ) {
                    var option = document.createElement( 'option' );
                    option.value = key;
                    option.textContent = config.sectionLabels[ key ];
                    selection.appendChild( option );
                } );
                selection.value = sourceSection( real );
                actions.appendChild( selection );
            }
            var edit = makeControl( '✎', 'data-preview-edit-category', 'Modifier la catégorie ' + name );
            edit.className = 'deprat-preview-edit';
            // Toujours visible à droite de la ligne ; les options de tri
            // restent séparées pour ne jamais masquer le crayon.

            var children = directItems( sourceChildList( real ), '[data-tree-item]' );
            var toggle = makeControl( '⌄', 'data-preview-toggle', 'Déplier les sous-catégories de ' + name );
            toggle.className = 'deprat-section-toggle';
            toggle.setAttribute( 'aria-expanded', expanded.has( id ) ? 'true' : 'false' );
            toggle.disabled = children.length === 0;
            var nested = document.createElement( 'div' );
            nested.className = 'deprat-section-children';
            nested.hidden = !expanded.has( id );
            var sublist = document.createElement( 'ul' );
            sublist.className = 'deprat-section-categories deprat-preview-list deprat-preview-sublist';
            sublist.setAttribute( 'data-preview-list', '' );
            sublist.setAttribute( 'aria-label', 'Sous-catégories de ' + name );
            children.forEach( function ( item ) {
                sublist.appendChild( buildItem( item, depth + 1 ) );
            } );
            nested.appendChild( sublist );
            row.appendChild( drag );
            if ( previewIcon ) { row.appendChild( previewIcon ); }
            row.append( label, actions, edit, toggle );
            li.append( row, nested );
            li.classList.toggle( 'has-children', children.length > 0 );
            return li;
        }
        function render() {
            rebuilding = true;
            try {
                $( preview ).find( '.deprat-preview-list.ui-sortable' ).sortable( 'destroy' );
                preview.querySelectorAll( '[data-section-key]' ).forEach( function ( list ) {
                    list.replaceChildren();
                } );
                directItems( sourceRoot, '[data-tree-item]' ).forEach( function ( real ) {
                    var destination = listFor( sourceSection( real ) );
                    if ( destination ) { destination.appendChild( buildItem( real, 0 ) ); }
                } );
                preview.querySelectorAll( '[data-preview-section]' ).forEach( function ( section ) {
                    var sectionList = section.querySelector( ':scope > [data-preview-list]' );
                    var empty = !directItems( sectionList, '[data-preview-item]' ).length;
                    section.classList.toggle( 'is-empty', empty );
                    var childAction = section.querySelector( '[data-preview-add-subcategory]' );
                    if ( childAction ) {
                        childAction.disabled = empty;
                        childAction.title = empty
                            ? 'Créez d’abord une catégorie dans cette rubrique.'
                            : 'Créer une sous-catégorie sous une catégorie existante.';
                    }
                } );
                setupDrag();
                setupSectionDrag();
            } finally { rebuilding = false; }
        }
        function treeSnapshot( list ) {
            return directItems( list, '[data-preview-item]' ).map( function ( item ) {
                return {
                    id: Number( item.getAttribute( 'data-term-id' ) ),
                    children: treeSnapshot( innerList( item ) )
                };
            } );
        }
        function synchronize() {
            var graph = [];
            var sections = {};
            preview.querySelectorAll( '[data-section-key]' ).forEach( function ( sectionList ) {
                var key = sectionList.getAttribute( 'data-section-key' );
                treeSnapshot( sectionList ).forEach( function ( item ) {
                    graph.push( item );
                    sections[ item.id ] = key;
                } );
            } );
            var originals = new Map();
            sourceRoot.querySelectorAll( '[data-tree-item]' ).forEach( function ( item ) {
                originals.set( Number( item.getAttribute( 'data-term-id' ) ), item );
            } );
            var seen = new Set();
            function validate( nodes, ancestry, depth ) {
                if ( depth > 30 ) { throw new Error( 'Trop de niveaux de catégories.' ); }
                nodes.forEach( function ( node ) {
                    if ( !originals.has( node.id ) || seen.has( node.id ) || ancestry.has( node.id ) ) {
                        throw new Error( 'Ce déplacement créerait une catégorie dupliquée ou circulaire.' );
                    }
                    seen.add( node.id );
                    var path = new Set( ancestry );
                    path.add( node.id );
                    validate( node.children, path, depth + 1 );
                } );
            }
            validate( graph, new Set(), 0 );
            if ( seen.size !== originals.size ) {
                throw new Error( 'Certaines catégories sont manquantes. Rechargez la page.' );
            }
            function apply( nodes, target ) {
                nodes.forEach( function ( node ) {
                    var real = originals.get( node.id );
                    // Sécurité supplémentaire : ne jamais imbriquer un parent dans son enfant.
                    if ( real.contains( target ) ) {
                        throw new Error( 'Impossible de déposer une catégorie dans son propre enfant.' );
                    }
                    target.appendChild( real );
                    apply( node.children, sourceChildList( real ) );
                } );
            }
            apply( graph, sourceRoot );
            Object.keys( sections ).forEach( function ( id ) {
                var real = originals.get( Number( id ) );
                var select = real.querySelector( ':scope > .deprat-tree-row [data-tree-section]' );
                var section = sections[ id ];
                if ( select && sourceSection( real ) !== section ) { select.value = section; }
            } );
            app.dispatchEvent( new CustomEvent( 'deprat-tree-preview-changed' ) );
        }
        function updateSafely() {
            try { synchronize(); }
            catch ( error ) {
                render();
                var status = app.querySelector( '[data-tree-status]' );
                status.textContent = error.message;
                status.classList.add( 'is-error' );
            }
        }
        function setupDrag() {
            $( preview ).find( '[data-preview-list]' ).sortable( {
                items: '> [data-preview-item]',
                connectWith: '.deprat-preview-list',
                handle: '[data-preview-handle]',
                cancel: 'a, input, select, textarea, button:not([data-preview-handle])',
                placeholder: 'deprat-preview-placeholder',
                forcePlaceholderSize: true,
                dropOnEmpty: true,
                tolerance: 'pointer',
                scroll: true,
                scrollSensitivity: 40,
                scrollSpeed: 14,
                start: function ( event, ui ) {
                    preview.classList.add( 'is-dragging' );
                    ui.placeholder.height( Math.max( 30, ui.item.children( '.deprat-section-term-row' ).outerHeight() ) );
                    preview.querySelectorAll( '.deprat-section-children' ).forEach( function ( div ) {
                        div.hidden = false;
                    } );
                },
                receive: function ( event, ui ) {
                    if ( ui.item[0].contains( this ) ) {
                        $( this ).sortable( 'cancel' );
                    }
                },
                stop: function () {
                    preview.classList.remove( 'is-dragging' );
                    updateSafely();
                }
            } );
        }
        function setupSectionDrag() {
            if ( !grid ) { return; }
            var node = $( grid );
            if ( node.hasClass( 'ui-sortable' ) ) { node.sortable( 'refresh' ); return; }
            node.sortable( {
                items: '> [data-preview-section]',
                handle: '[data-section-drag-handle]',
                cancel: 'a, input, select, textarea, button:not([data-section-drag-handle]), summary',
                placeholder: 'deprat-section-placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                scroll: true,
                scrollSensitivity: 55,
                scrollSpeed: 13,
                start: function ( event, ui ) {
                    ui.placeholder.height( ui.item.outerHeight() );
                    ui.placeholder.width( ui.item.outerWidth() );
                },
                stop: function () {
                    app.dispatchEvent( new CustomEvent( 'deprat-tree-preview-changed' ) );
                }
            } );
        }
        app.addEventListener( 'deprat-tree-section-upsert', function ( event ) {
            var entry = event.detail || {};
            if ( !entry.key || !entry.html || !grid ) { return; }
            var template = document.createElement( 'template' );
            template.innerHTML = entry.html.trim();
            var section = template.content.firstElementChild;
            if ( !section || !section.matches( '[data-preview-section]' ) ) { return; }
            var existing = grid.querySelector( '[data-preview-section="' + entry.key + '"]' );
            if ( existing ) { existing.replaceWith( section ); }
            else { grid.appendChild( section ); }
            render();
            app.dispatchEvent( new CustomEvent( 'deprat-tree-preview-changed' ) );
        } );
        function move( item, position ) {
            var previous = item.previousElementSibling;
            var next = item.nextElementSibling;
            var list = item.parentElement;
            if ( position === 'up' && previous ) { previous.before( item ); }
            else if ( position === 'down' && next ) { next.after( item ); }
            else if ( position === 'indent' && previous && previous.matches( '[data-preview-item]' ) ) {
                var dest = innerList( previous );
                expanded.add( previous.getAttribute( 'data-term-id' ) );
                dest.appendChild( item );
            } else if ( position === 'outdent' ) {
                var parentItem = list.closest( '[data-preview-item]' );
                if ( parentItem ) {
                    parentItem.after( item );
                }
            } else { return; }
            updateSafely();
        }
        preview.addEventListener( 'click', function ( event ) {
            var sectionMoveUp = event.target.closest( '[data-section-up]' );
            var sectionMoveDown = event.target.closest( '[data-section-down]' );
            if ( sectionMoveUp || sectionMoveDown ) {
                var sectionItem = ( sectionMoveUp || sectionMoveDown ).closest( '[data-preview-section]' );
                var neighbor = sectionMoveUp ? sectionItem.previousElementSibling : sectionItem.nextElementSibling;
                if ( neighbor && neighbor.matches( '[data-preview-section]' ) ) {
                    if ( sectionMoveUp ) { neighbor.before( sectionItem ); }
                    else { neighbor.after( sectionItem ); }
                    app.dispatchEvent( new CustomEvent( 'deprat-tree-preview-changed' ) );
                }
                return;
            }
            var createCategory = event.target.closest( '[data-preview-add-category]' );
            var createChild = event.target.closest( '[data-preview-add-subcategory]' );
            if ( createCategory || createChild ) {
                var action = createCategory || createChild;
                if ( action.disabled ) { return; }
                var section = action.closest( '[data-preview-section]' );
                var details = action.closest( '[data-preview-create-menu]' );
                var key = section && section.getAttribute( 'data-preview-section' );
                if ( details ) { details.open = false; }
                if ( key && config.sectionLabels[ key ] ) {
                    app.dispatchEvent( new CustomEvent( 'deprat-tree-create-from-section', {
                        detail: { section: key, mode: createChild ? 'child' : 'root',
                            trigger: details ? details.querySelector( 'summary' ) : action }
                    } ) );
                }
                return;
            }
            var toggle = event.target.closest( '[data-preview-toggle]' );
            if ( toggle ) {
                var li = toggle.closest( '[data-preview-item]' );
                var id = li.getAttribute( 'data-term-id' );
                if ( expanded.has( id ) ) { expanded.delete( id ); }
                else { expanded.add( id ); }
                var content = li.querySelector( ':scope > .deprat-section-children' );
                content.hidden = !expanded.has( id );
                toggle.setAttribute( 'aria-expanded', expanded.has( id ) ? 'true' : 'false' );
                $( content ).find( '[data-preview-list].ui-sortable' ).sortable( 'refresh' );
                return;
            }
            var actions = { 'data-preview-up': 'up', 'data-preview-down': 'down',
                'data-preview-indent': 'indent', 'data-preview-outdent': 'outdent' };
            Object.keys( actions ).some( function ( attr ) {
                var control = event.target.closest( '[' + attr + ']' );
                if ( !control ) { return false; }
                move( control.closest( '[data-preview-item]' ), actions[ attr ] );
                return true;
            } );
        } );
        preview.addEventListener( 'change', function ( event ) {
            var select = event.target.closest( '[data-preview-section-select]' );
            if ( !select ) { return; }
            var li = select.closest( '[data-preview-item]' );
            var target = listFor( select.value );
            if ( !li || !target ) { return; }
            target.appendChild( li );
            updateSafely();
        } );
        // Refermer les autres menus lorsque l'on en ouvre un, comme un vrai menu déroulant.
        preview.addEventListener( 'click', function ( event ) {
            var summary = event.target.closest( '[data-preview-create-menu] > summary' );
            if ( !summary ) { return; }
            var current = summary.closest( '[data-preview-create-menu]' );
            preview.querySelectorAll( '[data-preview-create-menu][open]' ).forEach( function ( menu ) {
                if ( menu !== current ) { menu.open = false; }
            } );
        } );
        document.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '[data-preview-create-menu]' ) ) { return; }
            preview.querySelectorAll( '[data-preview-create-menu][open]' ).forEach( function ( menu ) {
                menu.open = false;
            } );
        } );
        app.addEventListener( 'deprat-tree-expand-parent', function ( event ) {
            if ( event.detail && event.detail.id ) { expanded.add( String( event.detail.id ) ); }
        } );
        app.addEventListener( 'deprat-tree-changed', function () {
            if ( !rebuilding ) { render(); }
        } );
        render();
    } );
} )( jQuery );
