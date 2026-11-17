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
    try {
        await page.goto( base + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
        await page.fill( '#user_login', 'deprat-test' );
        await page.fill( '#user_pass', 'deprat-test' );
        await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

        const listing = base + '/wp-admin/edit.php?post_type=product';
        await page.goto( listing, { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( 'body.deprat-product-list-active [data-deprat-products-ui]' );

        const ui = page.locator( '[data-deprat-products-ui]' );
        const sectionTabs = ui.locator( '.deprat-products-sections .deprat-products-tab' );
        assert( await sectionTabs.count() >= 10, 'Toutes les sections DEPRAT sont dans la barre persistante.' );
        assert( await ui.locator( '.deprat-products-title h1', { hasText: 'Produits' } ).isVisible() );
        assert( await ui.locator( '.deprat-products-header-search #post-search-input' ).isVisible(),
            'La vraie recherche WordPress doit être déplacée en haut.' );
        assert( await ui.locator( '.deprat-products-create' ).isVisible(),
            'Ajouter un produit conserve un accès direct à WooCommerce.' );
        assert( await page.locator( '#the-list > tr' ).count() >= 4,
            'La table WooCommerce et ses produits doivent rester présents.' );
        assert( await page.locator( '.column-deprat_section' ).count() >= 1,
            'La colonne Section doit refléter les rubriques du méga-menu.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-produits-admin-tous.png', fullPage: true } );

        // Première ligne : filtrer par section et conserver toutes les sections.
        await ui.locator( '.deprat-products-sections a', { hasText: 'Tubes acier & accessoires' } ).click();
        await page.waitForURL( /deprat_section=tubes/ );
        assert( await ui.locator( '.deprat-products-sections a' ).count() >= 10,
            'La barre de sections reste visible après choix d’une section.' );
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
        assert( await ui.locator( '.deprat-products-breadcrumb' ).isVisible() );
        assert( await ui.locator( '.deprat-products-children a', { hasText: 'Tous' } ).isVisible(),
            'Troisième niveau avec un onglet Tous.' );
        assert( await ui.locator( '.deprat-products-children a', { hasText: 'Tubes de test' } ).isVisible(),
            'Le troisième niveau affiche les vraies sous-catégories.' );
        assert( ( await page.locator( '#the-list' ).textContent() ).includes( 'Tube de test modifié' ) );
        assert( !( await page.locator( '#the-list' ).textContent() ).includes( 'Embout DEPRAT de test' ) );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-produits-admin-tubes.png', fullPage: true } );

        await ui.locator( '.deprat-products-children a', { hasText: 'Tubes de test' } ).click();
        await page.waitForURL( /deprat_cat=\d+/ );
        assert( ( await ui.locator( '.deprat-products-breadcrumb' ).textContent() ).includes( 'Tubes de test' ),
            'La navigation descend dans la hiérarchie sans masquer les sections.' );
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
