const { chromium } = require( 'playwright-core' );
const assert = require( 'assert' );

( async function () {
    const tubeId = process.argv[2];
    const emboutId = process.argv[3];
    const base = 'http://127.0.0.1:8099';
    const browser = await chromium.launch( {
        executablePath: '/usr/bin/google-chrome',
        args: [ '--no-sandbox' ],
        headless: true
    } );
    const page = await browser.newPage( { viewport: { width: 1650, height: 1040 }, deviceScaleFactor: 1 } );
    page.on( 'console', msg => {
        if ( msg.type() === 'warning' && msg.text().includes( 'DEPRAT navigation' ) ) {
            console.log( 'NAV_DIAG:', msg.text() );
        }
    } );
    try {
        await page.goto( base + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
        await page.fill( '#user_login', 'deprat-test' );
        await page.fill( '#user_pass', 'deprat-test' );
        await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

        const listing = base + '/wp-admin/edit.php?post_type=product';
        await page.goto( listing, { waitUntil: 'domcontentloaded' } );
        // Une propriété de window disparaîtrait si la navigation rechargeait
        // tout WordPress. Elle doit survivre aux clics de tous les niveaux.
        await page.evaluate( () => { window.__depratDocumentMarker = 'same-document'; } );
        await page.waitForSelector( 'body.deprat-product-list-active [data-deprat-products-ui]' );

        const ui = page.locator( '[data-deprat-products-ui]' );
        const sectionTabs = ui.locator( '.deprat-products-sections .deprat-products-tab' );
        assert( await sectionTabs.count() >= 10, 'Toutes les sections DEPRAT sont dans la barre persistante.' );
        assert( await ui.locator( '.deprat-products-title h1', { hasText: 'Produits' } ).isVisible() );
        assert.strictEqual( await ui.locator( '.deprat-products-explainer' ).count(), 0,
            'Le bandeau bleu expliquant les trois lignes ne doit plus être affiché.' );
        const textStyles = await ui.evaluate( root => {
            const css = selector => {
                const node = root.querySelector( selector );
                if ( !node ) { throw new Error( 'Sélecteur manquant : ' + selector ); }
                const style = getComputedStyle( node );
                return { size: parseFloat( style.fontSize ), weight: parseFloat( style.fontWeight ) };
            };
            return {
                section: css( '.deprat-products-tab--section' ),
                title: css( '.deprat-products-title h1' ),
                filters: css( '.deprat-products-filters select' )
            };
        } );
        assert( textStyles.section.size >= 12 && textStyles.section.weight <= 600,
            'Les sections restent lisibles et ne sont plus en très gras.' );
        assert( textStyles.title.weight <= 700, 'Le titre reste visuellement léger.' );
        assert( textStyles.filters.size >= 13 && textStyles.filters.weight <= 500,
            'Les filtres sont suffisamment grands et de graisse normale.' );

        assert( await ui.locator( '.deprat-products-level-title', { hasText: 'Sections' } ).isVisible(),
            'La navigation affiche son titre Sections sans bandeau bleu.' );
        assert.strictEqual( await ui.locator( '.deprat-products-explainer' ).count(), 0,
            'Le bandeau explicatif bleu a été supprimé.' );
        // Liste et Grille utilisent la même table et les mêmes produits.
        const viewList = ui.locator( '[data-deprat-view="list"]' );
        const viewGrid = ui.locator( '[data-deprat-view="grid"]' );
        assert( await viewList.isVisible() && await viewGrid.isVisible(),
            'Les deux modes sont accessibles depuis les filtres.' );
        assert.strictEqual( await viewList.getAttribute( 'aria-pressed' ), 'true',
            'La vue Liste est le mode initial.' );
        const beforeGridIds = await page.locator( '#the-list > tr[id^="post-"]' )
            .evaluateAll( nodes => nodes.map( row => row.id ) );
        await viewGrid.click();
        assert.strictEqual( await viewGrid.getAttribute( 'aria-pressed' ), 'true',
            'La vue Grille est activée.' );
        assert( await page.locator( '#the-list > tr.deprat-products-create-card .deprat-products-create-card-link' ).isVisible(),
            'La grille comporte une fausse fiche « Nouveau produit » au début.' );
        assert( ( await page.locator( '#the-list > tr.deprat-products-create-card .deprat-products-create-card-link' ).getAttribute( 'href' ) ).includes( 'post_type=product' ),
            'La fiche d’ajout ouvre le vrai formulaire WooCommerce.' );
        assert( await page.locator( 'body.deprat-product-grid-mode' ).count() === 1,
            'Le mode Grille utilise un style dédié.' );
        const gridStyles = await page.evaluate( () => ( {
            container: getComputedStyle( document.querySelector( '#the-list' ) ).display,
            product: getComputedStyle( document.querySelector( '#the-list > tr[id^="post-"]' ) ).display
        } ) );
        assert.strictEqual( gridStyles.container, 'grid', 'Les produits sont présentés dans une grille CSS.' );
        assert.strictEqual( gridStyles.product, 'grid', 'Chaque produit devient une carte.' );
        const afterGridIds = await page.locator( '#the-list > tr[id^="post-"]' )
            .evaluateAll( nodes => nodes.map( row => row.id ) );
        assert.deepStrictEqual( afterGridIds, beforeGridIds,
            'La grille ne duplique, ne supprime et ne réordonne aucun produit.' );
        assert( await page.locator( '#the-list > tr[id^="post-"] .column-thumb img' ).first().isVisible(),
            'La grille conserve la photo des produits.' );
        const gridImageHeight = await page.locator( '#the-list > tr[id^="post-"] .column-thumb img' )
            .first().evaluate( img => img.getBoundingClientRect().height );
        assert( gridImageHeight >= 135 && gridImageHeight <= 145, 'Les photos en grille compacte doivent mesurer environ 140 px.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-produits-admin-grille.png', fullPage: true } );
        await viewList.click();
        assert.strictEqual( await viewList.getAttribute( 'aria-pressed' ), 'true',
            'Un clic sur Liste restaure le tableau WordPress.' );
        assert.strictEqual( await page.locator( '#the-list' ).evaluate( node => getComputedStyle( node ).display ),
            'table-row-group', 'La présentation native est restaurée.' );
        await viewGrid.click();
        await page.reload( { waitUntil: 'domcontentloaded' } );
        // Le rechargement volontaire de la préférence Grille supprime
        // naturellement notre marqueur : on le réinstalle ici avant les
        // tests de navigation qui, eux, ne doivent PLUS recharger.
        await page.evaluate( () => { window.__depratDocumentMarker = 'same-document'; } );
        assert.strictEqual( await ui.locator( '[data-deprat-view="grid"]' ).getAttribute( 'aria-pressed' ), 'true',
            'Le mode Grille est mémorisé entre deux visites.' );
        await ui.locator( '[data-deprat-view="list"]' ).click();
        assert.strictEqual( await ui.locator( '[data-deprat-view="list"]' ).getAttribute( 'aria-pressed' ), 'true' );

        assert( await ui.locator( '.deprat-products-header-search #post-search-input' ).isVisible(),
            'La vraie recherche WordPress doit être déplacée en haut.' );
        assert( await ui.locator( '.deprat-products-create' ).isVisible(),
            'Ajouter un produit conserve un accès direct à WooCommerce.' );
        assert( await page.locator( '#the-list > tr' ).count() >= 4,
            'La table WooCommerce et ses produits doivent rester présents.' );
        assert( await page.locator( '.column-deprat_section' ).count() >= 1,
            'La colonne Section doit refléter les rubriques du méga-menu.' );
        const listImageHeight = await page.locator( '#the-list > tr[id^="post-"] .column-thumb img' )
            .first().evaluate( img => img.getBoundingClientRect().height );
        assert( listImageHeight >= 80, 'Les photos en liste doivent atteindre environ 84 px.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-produits-admin-tous.png', fullPage: true } );

        // Première ligne : filtrer par section et conserver toutes les sections.
        await ui.locator( '.deprat-products-sections a', { hasText: 'Tubes acier & accessoires' } ).click();
        await page.waitForURL( /deprat_section=tubes/ );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), 'same-document',
            'Le clic sur une section ne doit pas recharger le document WordPress.' );

        assert( await ui.locator( '.deprat-products-sections a' ).count() >= 10,
            'La barre de sections reste visible après choix d’une section.' );
        assert( await ui.locator( '[data-deprat-create-category][data-parent="0"]' ).isVisible(),
            'Une catégorie racine peut être créée depuis la section sélectionnée.' );
        assert( await ui.locator( '.deprat-products-level-title', { hasText: 'Catégories' } ).isVisible(),
            'La deuxième rangée est identifiée par un titre discret.' );
        assert( await ui.locator( '.deprat-products-categories a', { hasText: 'Tubes' } ).count() === 1,
            'La deuxième ligne contient les catégories WooCommerce réelles.' );
        assert( await ui.locator( '.deprat-products-categories a', { hasText: 'Embouts' } ).count() === 1,
            'Le filtre affiche les catégories de la même section.' );
        const rowsSection = page.locator( '#the-list > tr' );
        assert( ( await rowsSection.allTextContents() ).join( ' ' ).includes( 'Tube de test modifié' ),
            'Le tube doit apparaître dans la section Tubes acier.' );
        assert( ( await rowsSection.allTextContents() ).join( ' ' ).includes( 'Embout DEPRAT de test' ),
            'Les embouts doivent aussi apparaître dans la même section.' );
        assert( !( await rowsSection.allTextContents() ).join( ' ' ).includes( 'Produit sans stock et sans prix' ),
            'Les produits non classés ne doivent pas être inventés dans Tubes acier.' );

        // Deuxième ligne : catégorie Tubes ; troisième ligne : niveaux inférieurs.
        await ui.locator( '.deprat-products-categories a', { hasText: 'Tubes' } ).click();
        await page.waitForURL( /deprat_cat=\d+/ );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), 'same-document',
            'La catégorie charge uniquement les onglets et la table, sans rechargement.' );
        assert( await ui.locator( '[data-deprat-create-category].deprat-products-add-subcategory' ).isVisible(),
            'Une sous-catégorie peut être créée sous la catégorie courante.' );
        const contextualProductHref = await ui.locator( '[data-deprat-new-product-link]' ).getAttribute( 'href' );
        assert( /deprat_prefill_cat=\d+/.test( contextualProductHref ),
            'Nouveau produit reprend la catégorie sélectionnée.' );
        await ui.locator( '[data-deprat-create-category].deprat-products-add-subcategory' ).click();
        assert( await ui.locator( '[data-deprat-category-dialog]' ).isVisible(),
            'Le formulaire de création de sous-catégorie s’ouvre en modal.' );
        await ui.locator( '[data-deprat-dialog-close]' ).click();
        assert( !await ui.locator( '[data-deprat-category-dialog]' ).isVisible(),
            'Le modal peut être fermé sans modifier les catégories.' );
        assert( await ui.locator( '.deprat-products-breadcrumb' ).isVisible() );
        assert( await ui.locator( '.deprat-products-level-title', { hasText: 'Sous-catégories' } ).isVisible(),
            'La troisième rangée dispose également de son titre.' );
        const subsection = ui.locator( 'details[data-deprat-subcategories]' );
        assert( await subsection.isVisible(), 'Les sous-catégories disposent d’un bloc repliable.' );
        assert( await subsection.evaluate( node => node.open ),
            'Les sous-catégories sont ouvertes automatiquement à l’arrivée.' );
        await subsection.locator( 'summary' ).click();
        assert( !await subsection.evaluate( node => node.open ),
            'Un clic sur l’intitulé replie les sous-catégories sans rechargement.' );
        await subsection.locator( 'summary' ).click();
        assert( await subsection.evaluate( node => node.open ),
            'Un second clic les redéplie immédiatement.' );
        assert( await ui.locator( '.deprat-products-children a', { hasText: 'Tous' } ).isVisible(),
            'Troisième niveau avec un onglet Tous.' );
        assert( await ui.locator( '.deprat-products-children a', { hasText: 'Tubes de test' } ).isVisible(),
            'Le troisième niveau affiche les vraies sous-catégories.' );
        assert( ( await page.locator( '#the-list' ).textContent() ).includes( 'Tube de test modifié' ) );
        assert( !( await page.locator( '#the-list' ).textContent() ).includes( 'Embout DEPRAT de test' ) );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-produits-admin-tubes.png', fullPage: true } );

        const parentUrl = page.url();
        await ui.locator( '.deprat-products-children a', { hasText: 'Tubes de test' } ).click();
        // Le parent possède DÉJÀ un deprat_cat : attendre une nouvelle URL
        // exacte et non une regexp qui correspondait avant le clic.
        await page.waitForURL( url => url.href !== parentUrl && url.searchParams.has( 'deprat_cat' ) );
        await page.waitForFunction( () => {
            const crumb = document.querySelector( '.deprat-products-breadcrumb' );
            return crumb && crumb.textContent.includes( 'Tubes de test' );
        } );
        assert( ( await ui.locator( '.deprat-products-breadcrumb' ).textContent() ).includes( 'Tubes de test' ),
            'La navigation descend dans la hiérarchie sans masquer les sections.' );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), 'same-document',
            'La sous-catégorie change le contenu sans charger une nouvelle page.' );
        const selectedPath = page.url();
        await page.evaluate( () => window.history.back() );
        await page.waitForURL( parentUrl );
        await page.waitForFunction( () => {
            const crumb = document.querySelector( '.deprat-products-breadcrumb' );
            return crumb && !crumb.textContent.includes( 'Tubes de test' );
        } );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), 'same-document',
            'Le bouton Précédent conserve le même document WordPress.' );
        await page.evaluate( () => window.history.forward() );
        await page.waitForURL( selectedPath );
        await page.waitForFunction( () => {
            const crumb = document.querySelector( '.deprat-products-breadcrumb' );
            return crumb && crumb.textContent.includes( 'Tubes de test' );
        } );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), 'same-document',
            'Le bouton Suivant retrouve la même catégorie sans rechargement.' );

        assert( await ui.locator( '.deprat-products-sections a' ).count() >= 10 );

        // Filtrer instantanément les seules lignes actuellement paginées.
        await ui.locator( '[data-deprat-list-search]' ).fill( 'référence introuvable' );
        assert.strictEqual( await page.locator( '#the-list > tr:visible' ).count(), 0,
            'Le filtre rapide n’affiche pas de produits ne correspondant pas à la saisie.' );
        await ui.locator( '[data-deprat-list-search]' ).fill( '' );
        assert( await page.locator( '#the-list > tr:visible' ).count() >= 1 );

        // La liste conserve les commandes habituelles et les actions sécurisées.
        await page.goto( listing, { waitUntil: 'domcontentloaded' } );
        const menu = page.locator( '#the-list .deprat-products-row-menu' ).first();
        await menu.locator( 'summary' ).click();
        assert( await menu.locator( 'a', { hasText: 'Modifier le produit' } ).isVisible() );
        assert( await menu.locator( 'a', { hasText: 'Mettre à la corbeille' } ).isVisible() );
        await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );

        // Stock : vrai filtrage côté serveur, et non simple masque des lignes.
        await ui.locator( 'select[name="deprat_stock"]' ).selectOption( 'outofstock' );
        await page.waitForURL( /deprat_stock=outofstock/ );
        assert.strictEqual( await page.evaluate( () => window.__depratDocumentMarker ), undefined,
            'Une navigation native précédente réinitialise le marqueur du document.' );
        const stockRows = ( await page.locator( '#the-list' ).textContent() );
        assert( stockRows.includes( 'Produit sans stock et sans prix' ), 'Le filtre rupture doit retourner le vrai produit hors stock.' );
        assert( !stockRows.includes( 'Tube de test modifié' ), 'Le tube en stock ne doit pas rester visible en rupture.' );

        // La recherche globale utilise la recherche native, au-delà de la page.
        await page.goto( listing, { waitUntil: 'domcontentloaded' } );
        await ui.locator( '#post-search-input' ).fill( 'TEST-EMBOUT' );
        await Promise.all( [
            page.waitForURL( /[?&]s=/ ),
            ui.locator( '#search-submit' ).click()
        ] );
        assert( ( await page.locator( '#the-list' ).textContent() ).includes( 'Embout DEPRAT de test' ),
            'La recherche WordPress complète fonctionne encore après la refonte.' );

        assert( tubeId && emboutId );
        console.log( 'Produits DEPRAT : sections, catégories, sous-catégories, stock, recherche et actions natives validés.' );
    } finally {
        await browser.close();
    }
} )().catch( error => {
    console.error( error );
    process.exit( 1 );
} );
