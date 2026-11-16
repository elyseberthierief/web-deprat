const { chromium } = require( 'playwright-core' );
const assert = require( 'assert' );

( async function () {
    const productId = process.argv[2];
    const base = 'http://127.0.0.1:8099';
    const browser = await chromium.launch( { executablePath: '/usr/bin/google-chrome', args: [ '--no-sandbox' ], headless: true } );
    const page = await browser.newPage( { viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 } );
    page.on( 'dialog', dialog => dialog.accept() );
    try {
        await page.goto( base + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
        await page.fill( '#user_login', 'deprat-test' );
        await page.fill( '#user_pass', 'deprat-test' );
        await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
        await page.goto( base + '/wp-admin/post.php?post=' + productId + '&action=edit', { waitUntil: 'load' } );
        await page.waitForSelector( 'body.deprat-product-editor-ready [data-editor-chrome]:visible' );

        assert.strictEqual( await page.locator( '[data-product-name]' ).inputValue(), 'Tube de test' );
        assert.strictEqual( await page.locator( '[data-product-sku]' ).inputValue(), 'TEST-DP62' );
        assert.strictEqual( await page.locator( '[data-product-type]' ).inputValue(), 'simple' );
        const categorySummary = await page.locator( '[data-category-summary]' ).textContent();
        assert( categorySummary.includes( 'Tubes' ) && categorySummary.includes( 'Embouts' ), 'Les catégories existantes doivent rester sélectionnées.' );

        const layout = await page.evaluate( () => {
            const box = selector => document.querySelector( selector ).getBoundingClientRect().toJSON();
            return {
                identity: box( '.deprat-tech-identity' ),
                main: box( '.deprat-tech-main' ),
                aside: box( '.deprat-tech-side' ),
                image: box( '.deprat-featured-card' ),
                pdf: box( '.deprat-tech-pdf' ),
                description: box( '.deprat-tech-description-card' ),
                diagramsPanel: box( '.deprat-tech-diagrams-panel' )
            };
        } );
        assert( layout.identity.bottom <= layout.description.top + 1, 'La description doit suivre l’identité.' );
        assert( layout.description.bottom <= layout.main.top + 1, 'La description doit précéder les caractéristiques.' );
        assert( layout.main.right <= layout.aside.left + 1, 'Les deux colonnes doivent être côte à côte.' );
        assert( layout.image.bottom <= layout.pdf.top + 1, 'Le PDF doit être sous l’image du produit.' );
        assert( layout.pdf.bottom <= layout.diagramsPanel.top + 1, 'Les schémas doivent être sous le PDF.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-editor-desktop.png', fullPage: true } );

        await page.click( '[data-native-toggle]' );
        assert( await page.locator( '#woocommerce-product-data' ).isVisible(), 'Les options WooCommerce doivent rester accessibles.' );
        await page.click( '[data-native-toggle]' );

        await page.fill( '[data-product-name]', 'Tube de test modifié' );
        await page.fill( '[data-product-sku]', 'TEST-DP62-MODIF' );
        assert.strictEqual( await page.locator( '#title' ).inputValue(), 'Tube de test modifié' );
        assert.strictEqual( await page.locator( '#_sku' ).inputValue(), 'TEST-DP62-MODIF' );
        await page.locator( '[data-product-type]' ).selectOption( 'variable' );
        assert.strictEqual( await page.locator( '#product-type' ).inputValue(), 'variable' );
        assert( await page.locator( '#woocommerce-product-data' ).isVisible(), 'Les réglages des variations doivent être ouverts.' );
        const gotIt = page.getByRole( 'button', { name: 'Got it' } );
        if ( await gotIt.count() && await gotIt.isVisible() ) {
            await gotIt.click();
        }
        await page.locator( '[data-product-type]' ).selectOption( 'simple' );
        await page.fill( '[name="deprat_product_tech[delivery]"]', 'Longueurs de 5 m' );
        await page.locator( '[data-package-count]' ).fill( '2' );
        await page.locator( '[data-package-type]' ).fill( 'carton' );
        await page.locator( '[data-package-pieces]' ).fill( '50' );
        assert( ( await page.locator( '[data-package-summary]' ).textContent() ).includes( '2 cartons contenant chacun 50 pièces, soit un total de 100 pièces.' ) );
        await page.locator( '.deprat-tech-description-card textarea' ).fill( 'Description visible directement.' );
        await page.click( '[data-category-open]' );
        await page.locator( '[data-category-name]' ).fill( 'Tubes de test' );
        await page.locator( '[data-category-parent]' ).selectOption( { label: 'Tubes' } );
        await page.click( '[data-category-submit]' );
        await page.waitForSelector( '[data-category-modal]', { state: 'hidden' } );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Tubes de test' ), 'La nouvelle sous-catégorie doit être sélectionnée.' );
        await page.click( '[data-diagram-add]' );
        const diagram = page.locator( '[data-diagram-row]' ).last();
        await diagram.locator( '[data-diagram-title]' ).fill( 'Schéma de montage personnalisé' );
        await page.locator( '[data-diagram-up]' ).last().click();
        assert.strictEqual( await page.locator( '[data-diagram-title]' ).first().inputValue(), 'Schéma de montage personnalisé' );

        await page.click( '[data-add-row="features"]' );
        const row = page.locator( '[data-rows="features"] [data-row]' ).last();
        await row.locator( 'input' ).nth( 0 ).fill( 'Finition' );
        await row.locator( 'input' ).nth( 1 ).fill( 'Test' );

        assert( await page.locator( '[data-featured-preview] img' ).count(), 'L’image WooCommerce doit être visible dans le nouveau panneau.' );
        await page.click( '[data-featured-select]' );
        await page.waitForSelector( '.media-modal:visible' );
        await page.locator( '.media-modal-close' ).click();
        await page.waitForSelector( '.media-modal:visible', { state: 'hidden' } );

        await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '[data-product-save]' ) ] );
        await page.waitForSelector( 'body.deprat-product-editor-ready [data-editor-chrome]:visible' );
        assert.strictEqual( await page.locator( '[data-product-name]' ).inputValue(), 'Tube de test modifié' );
        assert.strictEqual( await page.locator( '[data-product-sku]' ).inputValue(), 'TEST-DP62-MODIF' );
        assert.strictEqual( await page.locator( '[name="deprat_product_tech[delivery]"]' ).inputValue(), 'Longueurs de 5 m' );
        assert( ( await page.locator( '[data-package-summary]' ).textContent() ).includes( '100 pièces' ) );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Tubes de test' ) );
        assert.strictEqual( await page.locator( '.deprat-tech-description-card textarea' ).inputValue(), 'Description visible directement.' );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Tubes' ) );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Embouts' ) );
        assert.strictEqual( await page.locator( '[data-rows="features"] [data-row]' ).last().locator( 'input' ).nth( 1 ).inputValue(), 'Test' );

        await page.locator( '[data-category-picker] summary' ).click();
        await page.locator( '[data-category-options] label', { hasText: 'Embouts' } ).locator( 'input' ).click();
        await page.locator( '[data-category-picker] summary' ).click();
        await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '[data-product-save]' ) ] );
        await page.waitForSelector( 'body.deprat-product-editor-ready [data-editor-chrome]:visible' );
        const updatedCategories = await page.locator( '[data-category-summary]' ).textContent();
        assert( updatedCategories.includes( 'Tubes' ) && ! updatedCategories.includes( 'Embouts' ), 'La modification des catégories doit être conservée.' );

        await page.setViewportSize( { width: 390, height: 844 } );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-editor-mobile.png', fullPage: true } );
        const noOverflow = await page.locator( '.deprat-tech-editor' ).evaluate( element => element.scrollWidth <= element.clientWidth + 1 );
        assert( noOverflow, 'Le formulaire ne doit pas déborder sur mobile.' );
        console.log( 'Éditeur réel : disposition, liaison WooCommerce, image, catégories et enregistrement vérifiés.' );
    } catch ( error ) {
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-editor-error.png', fullPage: true } ).catch( () => {} );
        throw error;
    } finally {
        await browser.close();
    }
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
