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

        function submitFilters( select ) {
            // Ces filtres appartiennent à notre bandeau, pas au formulaire
            // natif WP. La requête GET ne modifie aucun produit.
            var target = new URL( window.location.href );
            if ( select.value ) { target.searchParams.set( select.name, select.value ); }
            else { target.searchParams.delete( select.name ); }
            target.searchParams.delete( 'paged' );
            navigateProducts( target.href, true );
        }
        layout.querySelectorAll( '[data-deprat-filter]' ).forEach( function ( select ) {
            select.addEventListener( 'change', function () { submitFilters( select ); } );
        } );

        // Une seule liste WooCommerce, deux présentations. La vue Grille ne
        // clone pas les produits : cases à cocher, liens, pagination, tris et
        // menus conservent leur comportement natif.
        var viewButtons = Array.from( layout.querySelectorAll( '[data-deprat-view]' ) );
        var viewStorageKey = 'deprat_products_admin_view_v1';
        var table = form.querySelector( 'table.wp-list-table' );
        var currentView = 'list';
        function setProductView( view, remember ) {
            view = view === 'grid' ? 'grid' : 'list';
            currentView = view;
            document.body.classList.toggle( 'deprat-product-grid-mode', view === 'grid' );
            if ( table ) { table.classList.toggle( 'deprat-product-grid', view === 'grid' ); }
            viewButtons.forEach( function ( button ) {
                var selected = button.getAttribute( 'data-deprat-view' ) === view;
                button.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
                button.classList.toggle( 'is-active', selected );
            } );
            if ( remember ) {
                try { window.localStorage.setItem( viewStorageKey, view ); }
                catch ( error ) { /* Le mode reste utilisable si le stockage est interdit. */ }
            }
        }
        var initialView = 'list';
        try { initialView = window.localStorage.getItem( viewStorageKey ) || 'list'; }
        catch ( error ) { /* Le mode Liste reste le choix par défaut. */ }
        setProductView( initialView, false );
        viewButtons.forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                setProductView( button.getAttribute( 'data-deprat-view' ), true );
            } );
        } );

        // Le champ secondaire filtre les lignes DÉJÀ visibles (pagination
        // WordPress inchangée). La recherche globale reste en haut à droite.
        var quickSearch = layout.querySelector( '[data-deprat-list-search]' );
        var displayCount = layout.querySelector( '[data-deprat-visible-count]' );
        var rows = [];
        function refreshRows() {
            rows = Array.from( form.querySelectorAll( '#the-list > tr' ) ).filter( function ( row ) {
                return !row.classList.contains('no-items') && !row.classList.contains('deprat-products-create-card');
            } );
        }
        refreshRows();
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
        // Délégation : les nouveaux produits chargés en AJAX ont les mêmes
        // actions ⋮, sans devoir réinitialiser chaque ligne.
        form.addEventListener( 'toggle', function ( event ) {
            var menu = event.target;
            if ( !menu.matches || !menu.matches( '.deprat-products-row-menu[open]' ) ) { return; }
            form.querySelectorAll( '.deprat-products-row-menu[open]' ).forEach( function ( other ) {
                if ( other !== menu ) { other.open = false; }
            } );
        }, true );
        document.addEventListener( 'click', function ( event ) {
            if ( event.target.closest( '.deprat-products-row-menu' ) ) { return; }
            form.querySelectorAll( '.deprat-products-row-menu[open]' ).forEach( function ( node ) {
                node.open = false;
            } );
        } );

        // Une carte « + Nouveau produit » au début de la grille : même
        // destination que le bouton principal, enrichie par la catégorie active.
        function ensureCreateCard() {
            var tbody=form.querySelector('#the-list');
            if (!tbody) { return; }
            var source=layout.querySelector('[data-deprat-new-product-link]');
            var tile=tbody.querySelector('.deprat-products-create-card');
            if (!source) { if (tile) {tile.remove();} return; }
            if (!tile) {
                tile=document.createElement('tr');
                tile.className='deprat-products-create-card';
                var cell=document.createElement('td');
                cell.colSpan=12;
                var link=document.createElement('a');
                link.className='deprat-products-create-card-link';
                link.innerHTML='<span class="deprat-products-create-card-plus" aria-hidden="true">＋</span>'
                    + '<strong>Nouveau produit</strong><span class="deprat-products-create-card-context"></span>';
                cell.appendChild(link);
                tile.appendChild(cell);
            }
            var link=tile.querySelector('a');
            link.href=source.href;
            var context=tile.querySelector('.deprat-products-create-card-context');
            context.textContent=source.textContent.trim().replace(/^.*?Nouveau produit/, '').trim() || 'Dans la catégorie sélectionnée';
            tbody.prepend(tile);
            var topButton=layout.querySelector('.deprat-products-header .deprat-products-create');
            if(topButton){topButton.href=source.href;}
        }

        // Le même modal gère une catégorie racine et une sous-catégorie.
        // On utilise l'endpoint WooCommerce DEPRAT existant, qui vérifie
        // nonce, capacités et parent réel avant toute création.
        var categoryDialog=layout.querySelector('[data-deprat-category-dialog]');
        var categoryForm=layout.querySelector('[data-deprat-category-form]');
        var categoryFeedback=layout.querySelector('[data-deprat-category-feedback]');
        var categoryContext=layout.querySelector('[data-deprat-category-context]');
        var pendingCategory=null;
        if(categoryDialog && categoryForm){
            form.addEventListener('click',function(event){
                var button=event.target.closest('[data-deprat-create-category]');
                if(!button){return;}
                event.preventDefault();
                pendingCategory={
                    parent:Number(button.dataset.parent)||0,
                    section:button.dataset.section||'',
                    name:button.dataset.parentName||''
                };
                categoryForm.reset();
                categoryFeedback.textContent='';
                categoryContext.textContent=pendingCategory.parent
                    ? 'Créer une sous-catégorie dans « '+pendingCategory.name+' ».'
                    : 'Créer une catégorie dans « '+pendingCategory.name+' ».';
                categoryDialog.showModal();
                categoryForm.querySelector('[name=name]').focus();
            });
            categoryForm.querySelector('[data-deprat-dialog-close]').addEventListener('click',function(){
                categoryDialog.close();
            });
            categoryForm.addEventListener('submit',async function(event){
                event.preventDefault();
                if(!pendingCategory || !window.depratProductCreate){return;}
                var value=categoryForm.querySelector('[name=name]').value.trim();
                if(!value){return;}
                var submit=categoryForm.querySelector('[type=submit]');
                submit.disabled=true;
                categoryFeedback.textContent='Création en cours…';
                var body=new URLSearchParams({
                    action:'deprat_category_tree_create',
                    nonce:window.depratProductCreate.nonce,
                    name:value,
                    parent:String(pendingCategory.parent),
                    section:pendingCategory.section
                });
                try {
                    var result=await fetch(window.depratProductCreate.ajaxUrl,{
                        method:'POST',
                        credentials:'same-origin',
                        body:body
                    });
                    var json=await result.json();
                    if(!result.ok || !json.success || !json.data || !json.data.id){
                        throw new Error(json.data && json.data.message || 'Création impossible.');
                    }
                    var destination=new URL(window.location.href);
                    destination.searchParams.set('deprat_cat',String(json.data.id));
                    if(pendingCategory.section){destination.searchParams.set('deprat_section',pendingCategory.section);}
                    destination.searchParams.delete('paged');
                    categoryDialog.close();
                    // Le terme doit être visible dans toute l'arborescence :
                    // recharger la liste serveur pour des compteurs cohérents.
                    window.location.assign(destination.href);
                }catch(error){
                    categoryFeedback.textContent=error.message || 'Erreur réseau. Réessayez.';
                }finally{
                    submit.disabled=false;
                }
            });
        }

        // Navigation de catalogue sans rechargement complet. On récupère
        // la sortie serveur de WooCommerce pour ne jamais inventer de données
        // produit ni réimplémenter son filtrage ou ses droits.
        var pendingNavigation = null;
        var requestNumber = 0;
        function isProductListUrl( href ) {
            try {
                var target = new URL( href, window.location.href );
                // Les URL WordPress peuvent utiliser l'hôte canonique
                // (ex. localhost) alors que l'admin est ouvert via un alias
                // (ex. 127.0.0.1). Le chemin reste le même et l'AJAX doit
                // toujours viser l'origine de la page actuelle.
                return target.pathname === window.location.pathname
                    && target.searchParams.get( 'post_type' ) === 'product';
            } catch ( error ) { return false; }
        }
        function replaceNavigation( newTemplate, fetchedForm ) {
            var updated = newTemplate.content;
            var nextSections = updated.querySelector( '.deprat-products-sections' );
            var nextHierarchy = updated.querySelector( '.deprat-products-hierarchy' );
            var currentSections = layout.querySelector( '.deprat-products-sections' );
            var currentHierarchy = layout.querySelector( '.deprat-products-hierarchy' );
            var nextTable = fetchedForm.querySelector( ':scope > table.wp-list-table' );
            if ( !nextSections || !nextTable || !currentSections || !table ) {
                throw new Error( 'Contenu de catalogue incomplet.' );
            }

            // Importer des noeuds externes depuis le HTML de notre propre
            // administration, jamais exécuter ses scripts.
            currentSections.replaceWith( document.importNode( nextSections, true ) );
            if ( currentHierarchy ) { currentHierarchy.remove(); }
            if ( nextHierarchy ) {
                layout.querySelector( '.deprat-products-sections' ).after(
                    document.importNode( nextHierarchy, true )
                );
            }
            // Préserver les noeuds table/#the-list : WordPress et WooCommerce
            // attachent parfois les handlers Quick Edit aux noeuds existants.
            // Seul le contenu des lignes change ; ces handlers restent actifs.
            var currentBody = table.querySelector( '#the-list' );
            var newBody = nextTable.querySelector( '#the-list' );
            if ( !currentBody || !newBody ) {
                throw new Error( 'Lignes WooCommerce introuvables.' );
            }
            currentBody.replaceChildren( ...Array.from( newBody.childNodes ).map( function ( item ) {
                return document.importNode( item, true );
            } ) );
            [ 'thead', 'tfoot' ].forEach( function ( section ) {
                var existing = table.querySelector( ':scope > ' + section );
                var next = nextTable.querySelector( ':scope > ' + section );
                if ( existing && next ) {
                    existing.replaceChildren( ...Array.from( next.childNodes ).map( function ( node ) {
                        return document.importNode( node, true );
                    } ) );
                }
            } );

            // Conserver les actions groupées et la vraie pagination WP,
            // sans toucher au formulaire principal ni aux cases cochées de la
            // nouvelle table.
            var currentBottom = form.querySelector( ':scope > .tablenav.bottom' );
            var nextBottom = fetchedForm.querySelector( ':scope > .tablenav.bottom' );
            if ( currentBottom && nextBottom ) {
                currentBottom.replaceWith( document.importNode( nextBottom, true ) );
            }
            var nextNewProduct=updated.querySelector('[data-deprat-new-product-link]');
            var existingNewProduct=layout.querySelector('[data-deprat-new-product-link]');
            if(nextNewProduct && existingNewProduct){
                existingNewProduct.href=nextNewProduct.href;
                existingNewProduct.textContent=nextNewProduct.textContent;
            }
            ensureCreateCard();
            setProductView( currentView, false );
            refreshRows();
            updateVisibleRows();
        }
        async function navigateProducts( href, addHistory ) {
            if ( !isProductListUrl( href ) ) {
                window.location.assign( href );
                return;
            }
            // Ne jamais effectuer de fetch cross-origin. Les URL des
            // sections sont relatives au même admin que cette page.
            var normalized = new URL( href, window.location.href );
            normalized.protocol = window.location.protocol;
            normalized.host = window.location.host;
            href = normalized.href;
            if ( pendingNavigation ) { pendingNavigation.abort(); }
            var controller = new AbortController();
            pendingNavigation = controller;
            var ticket = ++requestNumber;
            layout.classList.add( 'is-loading' );
            layout.setAttribute( 'aria-busy', 'true' );
            var status = layout.querySelector( '[data-deprat-navigation-status]' );
            if ( status ) { status.textContent = 'Chargement des produits…'; }
            try {
                var response = await fetch( href, {
                    method: 'GET',
                    credentials: 'same-origin',
                    redirect: 'follow',
                    headers: { 'Accept': 'text/html' },
                    signal: controller.signal
                } );
                if ( !response.ok || !isProductListUrl( response.url ) ) {
                    throw new Error( 'Réponse de catalogue invalide.' );
                }
                var html = await response.text();
                if ( ticket !== requestNumber ) { return; }
                var documentFromServer = new DOMParser().parseFromString( html, 'text/html' );
                var nextTemplate = documentFromServer.querySelector( '#deprat-products-layout-template' );
                var fetchedForm = documentFromServer.querySelector( '#posts-filter' );
                if ( !nextTemplate || !fetchedForm ) {
                    throw new Error( 'La page Produits n’a pas été retournée.' );
                }
                replaceNavigation( nextTemplate, fetchedForm );
                if ( addHistory && href !== window.location.href ) {
                    window.history.pushState( { depratProducts: true }, '', href );
                }
                // Mettre à jour les filtres qui doivent suivre l’URL active.
                var query = new URL( href, window.location.href ).searchParams;
                layout.querySelectorAll( '[data-deprat-filter]' ).forEach( function ( select ) {
                    select.value = query.get( select.name ) || '';
                } );
                if ( status ) { status.textContent = 'Liste des produits mise à jour.'; }
            } catch ( error ) {
                if ( error.name !== 'AbortError' && ticket === requestNumber ) {
                    // Journal utile pour diagnostiquer un thème/plugin tiers :
                    // aucune donnée client ni URL secrète n’est écrite.
                    console.warn( 'DEPRAT navigation : retour à WordPress', error.message );
                    // Pour toute erreur réseau/session/plugin, laisser la
                    // navigation native reprendre la main plutôt que rester
                    // sur un catalogue incomplet.
                    window.location.assign( href );
                }
            } finally {
                if ( ticket === requestNumber ) {
                    pendingNavigation = null;
                    layout.classList.remove( 'is-loading' );
                    layout.removeAttribute( 'aria-busy' );
                }
            }
        }
        form.addEventListener( 'click', function ( event ) {
            var link = event.target.closest(
                '.deprat-products-sections a.deprat-products-tab, '
                + '.deprat-products-categories a.deprat-products-tab, '
                + '.deprat-products-children a.deprat-products-tab, '
                + '.deprat-products-breadcrumb a'
            );
            if ( !link || event.defaultPrevented || event.button !== 0
                || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                || link.hasAttribute( 'download' ) || link.target === '_blank' ) { return; }
            if ( !isProductListUrl( link.href ) ) { return; }
            event.preventDefault();
            navigateProducts( link.href, true );
        } );
        window.addEventListener( 'popstate', function () {
            if ( !isProductListUrl( window.location.href ) ) { return; }
            navigateProducts( window.location.href, false );
        } );

        ensureCreateCard();
        refreshRows();
        updateVisibleRows();
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
