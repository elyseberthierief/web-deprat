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
        assert( Number( await page.locator( '[data-glb-id]' ).inputValue() ) > 0, 'Le modèle 3D doit apparaître dans le formulaire.' );
        assert( await page.locator( '[data-glb-upload]' ).isVisible(), 'Importer un modèle 3D sans quitter le produit.' );
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
                image: box( '.deprat-tech-identity-photo' ),
                identityFields: box( '.deprat-tech-identity-fields' ),
                gallery: box( '.deprat-gallery-card' ),
                pdf: box( '.deprat-tech-pdf-panel' ),
                description: box( '.deprat-tech-description-card' ),
                diagramsPanel: box( '.deprat-tech-diagrams-panel' )
            };
        } );
        assert( layout.identity.bottom <= layout.description.top + 1, 'La description doit suivre l’identité.' );
        assert( layout.description.bottom <= layout.main.top + 1, 'La description doit précéder les caractéristiques.' );
        assert( layout.main.right <= layout.aside.left + 1, 'Les deux colonnes doivent être côte à côte.' );
        assert( layout.image.right <= layout.identityFields.left + 1, 'La photo principale doit être à gauche du nom.' );
        assert( layout.gallery.bottom <= layout.pdf.top + 1, 'Les fiches PDF doivent être sous les photos complémentaires.' );
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
        assert( ( await page.locator( '[data-package-preview]' ).first().textContent() ).includes( '100 pièces' ) );
        await page.click( '[data-package-add]' );
        const meters = page.locator( '[data-package-row]' ).last();
        await meters.locator( '[data-package-mode]' ).selectOption( 'length' );
        await meters.locator( '[data-package-total-m]' ).fill( '504' );
        await meters.locator( '[data-package-length-m]' ).fill( '6' );
        assert( ( await meters.locator( '[data-package-preview]' ).textContent() ).includes( '84 longueurs' ), '504 m sont livrés en 84 longueurs de 6 m.' );
        await page.locator( '[name="deprat_product_tech[description]"]' ).fill( 'Description visible directement.' );
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


        // Les champs de test doivent s'agrandir avec les descriptions longues.
        await page.click( '[data-add-row="tests"]' );
        const testRow = page.locator( '[data-rows="tests"] [data-row]' ).last();
        await testRow.locator( 'input' ).fill( 'Contrôle mécanique' );
        const multiValue = testRow.locator( 'textarea' );
        await multiValue.fill( 'Essai d’endurance sur 7 000 cycles avec tablier de 60 kg. '.repeat( 9 ) );
        const textareaSize = await multiValue.evaluate( node => ( { viewport: node.clientHeight, content: node.scrollHeight } ) );
        assert( textareaSize.viewport >= textareaSize.content - 3, 'Le texte du test ne doit pas être coupé.' );
        assert.strictEqual( await page.locator( '[data-datasheet-row]' ).count(), 2, 'Deux documents doivent être éditables.' );

        // Import direct depuis le disque : la médiathèque est remplie en arrière-plan.
        const imageFile = {
            name: 'photo-deprat-test.png', mimeType: 'image/png',
            buffer: Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRr0AAAAASUVORK5CYII=', 'base64' )
        };
        let photoChooser = page.waitForEvent( 'filechooser' );
        await page.click( '[data-quick-upload="gallery"]' );
        await ( await photoChooser ).setFiles( imageFile );
        await page.waitForFunction( () => document.querySelectorAll( '[data-gallery-item]' ).length >= 2 );

        await page.click( '[data-datasheet-add]' );
        const newPdfRow = page.locator( '[data-datasheet-row]' ).last();
        await newPdfRow.locator( '[data-datasheet-title]' ).fill( 'Notice de sécurité' );
        const pdfChooser = page.waitForEvent( 'filechooser' );
        await newPdfRow.locator( '[data-quick-upload="datasheet"]' ).click();
        await ( await pdfChooser ).setFiles( { name: 'notice-securite.pdf', mimeType: 'application/pdf', buffer: Buffer.from( '%PDF-1.4\\n%%EOF\\n' ) } );
        await page.waitForFunction( () => {
            const field = Array.from( document.querySelectorAll( '[data-datasheet-file]' ) ).pop();
            return field && Number( field.value ) > 0;
        } );
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
        assert( ( await page.locator( '[data-package-preview]' ).first().textContent() ).includes( '100 pièces' ) );
        assert.strictEqual( await page.locator( '[data-package-row]' ).count(), 2, 'Les deux conditionnements doivent rester enregistrés.' );
        assert( ( await page.locator( '[data-package-preview]' ).last().textContent() ).includes( '84 longueurs' ) );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Tubes de test' ) );
        assert.strictEqual( await page.locator( '[name="deprat_product_tech[description]"]' ).inputValue(), 'Description visible directement.' );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Tubes' ) );
        assert( ( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Embouts' ) );
        assert.strictEqual( await page.locator( '[data-rows="features"] [data-row]' ).last().locator( 'input' ).nth( 1 ).inputValue(), 'Test' );
        assert.strictEqual( await page.locator( '[data-datasheet-row]' ).count(), 3, 'Trois PDF conservés après enregistrement.' );
        assert( ( await page.locator( '[data-gallery-ids]' ).inputValue() ).split( ',' ).length >= 2, 'Les photos complémentaires sont enregistrées.' );

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
        await page.goto( base + '/?post_type=product&p=' + productId, { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '[data-three-mode="model"]:visible' );
        assert.strictEqual( await page.locator( '#tab-title-description, #tab-description, #tab-title-additional_information, #tab-additional_information' ).count(), 0, 'Les onglets Description et Informations complémentaires ne doivent plus faire doublon.' );
        assert( await page.locator( '.deprat-client-tech-panel' ).count() === 3, 'Les trois panneaux techniques DEPRAT remplacent les anciennes cartes.' );

        assert( await page.locator( '[data-three-mode="model"]' ).isVisible(), 'Le bouton Vue 3D doit apparaître avec un modèle.' );
        assert( await page.locator( '.deprat-three-viewport' ).isHidden(), 'La galerie photo doit rester sélectionnée à l’ouverture.' );
        await page.locator( '[data-three-mode="model"]' ).click();
        assert( await page.locator( '[data-render-mode="texture"]' ).isVisible(), 'Le mode pièce texturée est proposé.' );
        assert( await page.locator( '[data-render-mode="contours"]' ).isVisible(), 'Le mode vrais contours est proposé.' );
        const backgroundBefore = await page.locator( '.deprat-three-viewport' ).getAttribute( 'data-background' );
        await page.locator( '[data-background-cycle]' ).click();
        const backgroundAfter = await page.locator( '.deprat-three-viewport' ).getAttribute( 'data-background' );
        assert.notStrictEqual( backgroundAfter, backgroundBefore, 'Le bouton Fond change effectivement le contraste.' );
        assert( await page.locator( '.deprat-three-viewport' ).isVisible(), 'La visionneuse GLB doit s’ouvrir.' );
        assert( await page.locator( '.woocommerce-product-gallery.deprat-three-active' ).count() > 0, 'La galerie passe en mode 3D.' );
        await page.locator( '[data-three-mode="photos"]' ).click();
        assert( await page.locator( '.deprat-three-viewport' ).isHidden(), 'Le retour aux photos fonctionne.' );
        // Valider la nouvelle disposition client sans créer de menu supplémentaire.
        await page.setViewportSize( { width: 1600, height: 1000 } );
        await page.waitForSelector( '.deprat-client-product .deprat-client-quote' );
        const desktopLayout = await page.evaluate( () => {
            const rect = selector => document.querySelector( selector ).getBoundingClientRect();
            return {
                gallery: rect( '.woocommerce-product-gallery' ).toJSON(),
                summary: rect( 'div.product .summary .deprat-client-info' ).toJSON(),
                quote: rect( '.deprat-client-quote' ).toJSON(),
                tabs: rect( '.deprat-client-tech' ).toJSON()
            };
        } );
        assert( desktopLayout.gallery.right <= desktopLayout.summary.left + 2, 'Images à gauche de la description.' );
        assert( desktopLayout.summary.right <= desktopLayout.quote.left + 2, 'Demande de devis à droite de la description.' );
        assert( desktopLayout.tabs.top >= desktopLayout.gallery.top, 'Onglets sous la partie produit.' );
        assert( await page.locator( '.deprat-client-product .deprat-client-categories a' ).count() > 0, 'Catégories affichées.' );
        assert( await page.locator( '.deprat-client-product [data-package-calculator]' ).count() === 1, 'Récapitulatif du conditionnement.' );
        const quantityField = page.locator( '.deprat-client-quote form.cart input.qty' );
        assert( await quantityField.count() === 1, 'Champ de quantité WooCommerce.' );
        await page.locator( '.deprat-client-quote .deprat-qty-button' ).last().click();
        assert.strictEqual( await quantityField.inputValue(), '2', 'Quantité mise à jour sans rechargement.' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).includes( '200' ), 'Deux demandes de deux cartons de 50 donnent 200 pièces.' );
        await page.locator( '.deprat-client-quote [data-package-choice]' ).selectOption( '1' );
        assert( /1[\\s\\u202f]008/.test( await page.locator( '[data-package-calculator]' ).textContent() ), 'Deux lots de 504 m donnent 1 008 m.' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).includes( '168 longueurs' ), 'Deux conditionnements contiennent 168 longueurs de 6 m.' );
        await page.locator( '[data-tab-target="tests"]' ).click();
        assert( await page.locator( '[data-tab-panel="tests"]' ).isVisible(), 'Tests et normes visibles.' );
        assert( await page.locator( '[data-tab-panel="specs"]' ).isHidden(), 'Les autres onglets sont masqués.' );
        await page.locator( '[data-tab-target="docs"]' ).click();
        assert( await page.locator( '[data-tab-panel="docs"]' ).isVisible(), 'Schémas et PDF visibles.' );
        await page.locator( '[data-contact-open]' ).click();
        assert( await page.locator( '[data-contact-dialog]' ).isVisible(), 'Le popup de contact s’ouvre.' );
        await page.locator( '.deprat-client-contact-close' ).click();
        assert( await page.locator( '[data-contact-dialog]' ).isHidden(), 'Le popup se ferme.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-fiche-client-desktop.png', fullPage: true } );
        const pageUrl = page.url();
        await page.locator( '.deprat-client-quote .single_add_to_cart_button' ).click();
        await page.locator( '[data-quote-feedback].is-success' ).waitFor( { timeout: 20000 } );
        assert.strictEqual( page.url(), pageUrl, 'Ajouter à ma demande ne doit pas changer de page.' );
        await page.setViewportSize( { width: 390, height: 844 } );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-fiche-client-mobile.png', fullPage: true } );
        assert( await page.locator( '.deprat-client-quote' ).isVisible(), 'Demande de devis visible sur mobile.' );
        // Création d'une nouvelle fiche : l'autosave reste strictement en brouillon.
        await page.goto( base + '/wp-admin/post-new.php?post_type=product', { waitUntil: 'load' } );
        await page.waitForSelector( 'body.deprat-product-editor-ready [data-editor-chrome]:visible' );
        assert( await page.locator( '[data-product-publish]' ).isVisible(), 'La publication doit être un bouton manuel distinct.' );
        const newDraftId = await page.locator( '#post_ID' ).inputValue();
        assert( Number( newDraftId ) > 0, 'La création doit avoir un brouillon initial WordPress.' );
        await page.locator( '[data-product-name]' ).fill( 'Produit auto-brouillon DEPRAT test' );
        await page.locator( '[name="deprat_product_tech[description]"]' ).fill( 'Sauvegarde automatique non publiée.' );
        await page.waitForFunction( () => {
            const label = document.querySelector( '[data-autosave-status]' );
            return label && label.textContent.includes( 'Brouillon enregistré automatiquement' );
        }, { timeout: 20000 } );
        assert.strictEqual( await page.locator( '#post_status' ).inputValue(), 'draft', 'La sauvegarde ne doit jamais publier le produit.' );
        await page.goto( base + '/wp-admin/post.php?post=' + newDraftId + '&action=edit', { waitUntil: 'load' } );
        await page.waitForSelector( 'body.deprat-product-editor-ready [data-editor-chrome]:visible' );
        assert.strictEqual( await page.locator( '[data-product-name]' ).inputValue(), 'Produit auto-brouillon DEPRAT test', 'Le nom est retrouvé après réouverture.' );
        assert.strictEqual( await page.locator( '[name="deprat_product_tech[description]"]' ).inputValue(), 'Sauvegarde automatique non publiée.', 'La description est retrouvée.' );
        assert( await page.locator( '[data-product-publish]' ).isVisible(), 'Le brouillon reste soumis à une publication volontaire.' );
        console.log( 'Éditeur, conditionnements, 3D, devis AJAX et autosave brouillon validés.' );
    } catch ( error ) {
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-editor-error.png', fullPage: true } ).catch( () => {} );
        throw error;
    } finally {
        await browser.close();
    }
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
