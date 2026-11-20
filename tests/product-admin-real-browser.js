const { chromium } = require( 'playwright-core' );
const assert = require( 'assert' );

( async function () {
    const productId = process.argv[2];
    const pieceProductId = process.argv[3];
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
        await page.waitForFunction( () => document.querySelector( '[data-product-save]' ).getAttribute( 'data-floating' ) === 'false' );
        assert.strictEqual( await page.locator( '[data-product-save]' ).getAttribute( 'data-floating' ), 'false', 'Le bouton est d’abord à son emplacement normal.' );
        await page.evaluate( () => window.scrollTo( 0, document.documentElement.scrollHeight ) );
        await page.waitForFunction( () => document.querySelector( '[data-product-save]' ).getAttribute( 'data-floating' ) === 'true' );
        const floatingSave = page.locator( '[data-product-save]' );
        assert( await floatingSave.isVisible(), 'Le bouton Enregistrer doit rester visible en bas de page.' );
        assert( await floatingSave.evaluate( node => getComputedStyle( node ).position ) === 'fixed', 'La sauvegarde doit flotter pendant le défilement.' );
        await page.evaluate( () => window.scrollTo( 0, 0 ) );
        await page.waitForFunction( () => document.querySelector( '[data-product-save]' ).getAttribute( 'data-floating' ) === 'false' );

        assert( Number( await page.locator( '[data-glb-id]' ).inputValue() ) > 0, 'Le modèle 3D doit apparaître dans le formulaire.' );
        assert( await page.locator( '[data-glb-upload]' ).isVisible(), 'Importer un modèle 3D sans quitter le produit.' );
        assert.strictEqual( await page.locator( '[data-product-sku]' ).inputValue(), 'TEST-DP62' );
        assert.strictEqual( await page.locator( '[data-product-type]' ).inputValue(), 'simple' );
        const categorySummary = await page.locator( '[data-category-summary]' ).textContent();
        assert( categorySummary.includes( 'Tubes' ) && !categorySummary.includes( 'Embouts' ), 'La fiche Tube est uniquement classée dans Tubes.' );

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
        assert( !( await page.locator( '[data-category-summary]' ).textContent() ).includes( 'Embouts' ), 'Un tube ne doit pas appartenir aux embouts.' );
        assert.strictEqual( await page.locator( '[data-rows="features"] [data-row]' ).last().locator( 'input' ).nth( 1 ).inputValue(), 'Test' );
        assert.strictEqual( await page.locator( '[data-datasheet-row]' ).count(), 3, 'Trois PDF conservés après enregistrement.' );
        assert( ( await page.locator( '[data-gallery-ids]' ).inputValue() ).split( ',' ).length >= 2, 'Les photos complémentaires sont enregistrées.' );

        await page.locator( '[data-category-picker] summary' ).click();
        await page.locator( '[data-category-options] label', { hasText: 'Embouts' } ).locator( 'input' ).check();
        await page.locator( '[data-category-options] label', { hasText: 'Embouts' } ).locator( 'input' ).uncheck();
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
        const productBackgroundSwitch = await page.locator( '[data-background-cycle]' ).evaluate( button => {
            const panel = document.querySelector( '.deprat-three-viewport' );
            const before = panel.getAttribute( 'data-background' );
            button.click();
            return { before, after: panel.getAttribute( 'data-background' ) };
        } );
        assert.notStrictEqual( productBackgroundSwitch.after, productBackgroundSwitch.before, 'Le bouton Fond change effectivement le contraste.' );
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
        assert.strictEqual( await page.locator( '.deprat-client-quote-unavailable' ).count(), 0, 'Le produit sans prix ne doit plus afficher le message d’indisponibilité.' );
        assert( await page.locator( '.deprat-client-quote .single_add_to_cart_button' ).isVisible(), 'Le bouton de demande est visible sans prix commercial.' );
        assert.strictEqual( await page.locator( '.deprat-client-quote .quantity .deprat-qty-button' ).count(), 2, 'Une seule paire de boutons quantité.' );
        await page.evaluate( () => {
            const quantity = document.querySelector( '.deprat-client-quote .quantity' );
            const injectedPlus = document.createElement( 'button' );
            injectedPlus.type = 'button';
            injectedPlus.className = 'plus';
            injectedPlus.textContent = '+';
            quantity.appendChild( injectedPlus );
        } );
        await page.waitForFunction( () => !document.querySelector( '.deprat-client-quote .quantity button.plus' ) );
        assert.strictEqual( await page.locator( '.deprat-client-quote .quantity .deprat-qty-button' ).count(), 2, 'Le bouton + Astra supplémentaire doit être retiré.' );
        await page.locator( '.deprat-client-quote .deprat-qty-button' ).last().click();
        assert.strictEqual( await quantityField.inputValue(), '2', 'Quantité mise à jour sans rechargement.' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).includes( '200' ), 'Deux demandes de deux cartons de 50 donnent 200 pièces.' );
        await page.locator( '.deprat-client-quote [data-package-choice]' ).selectOption( '1' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).replace( /\s/g, '' ).includes( '1008' ), 'Deux lots de 504 m donnent 1 008 m.' );
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

        // La catégorie Tubes ne peut pas proposer un conditionnement unitaire.
        await page.goto( base + '/?post_type=product&p=' + productId, { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '.deprat-client-quote [data-package-choice]' );
        assert.strictEqual( await page.locator( '.deprat-client-quote [data-package-choice] option[value="unitary"]' ).count(), 0, 'Aucun choix unitaire dans les Tubes.' );

        // Une pièce propose simultanément le standard défini par l’admin et une quantité libre.
        await page.goto( base + '/?post_type=product&p=' + pieceProductId, { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '.deprat-client-quote [data-package-choice]' );
        const pieceSelect = page.locator( '.deprat-client-quote [data-package-choice]' );
        assert.strictEqual( await page.locator( '.deprat-client-quote-unavailable' ).count(), 0, 'La pièce sans prix reste ajoutable.' );
        assert.strictEqual( await pieceSelect.locator( 'option' ).count(), 2, 'Une pièce propose le conditionnement standard et le choix unitaire.' );
        assert( await page.locator( '[data-unitary-warning]' ).isHidden(), 'Pas de plus-value sur le conditionnement standard.' );
        await pieceSelect.selectOption( 'unitary' );
        assert( await page.locator( '[data-unitary-warning]' ).isVisible(), 'Avertissement affiché pour la quantité libre.' );
        assert( ( await page.locator( '[data-unitary-warning]' ).textContent() ).includes( 'plus-value' ), 'La mention plus-value doit être explicite.' );
        assert( ( await page.locator( '.deprat-client-quantity-label' ).textContent() ).includes( 'pièces souhaitées' ), 'Le client saisit des pièces et non des cartons.' );
        const pieceQty = page.locator( '.deprat-client-quote form.cart input.qty' );
        await pieceQty.fill( '7' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).includes( '7 pièces à l’unité' ), 'La demande de 7 pièces doit rester de 7 pièces.' );
        const pieceUrl = page.url();
        await page.locator( '.deprat-client-quote .single_add_to_cart_button' ).click();
        await page.locator( '[data-quote-feedback].is-success' ).waitFor( { timeout: 20000 } );
        assert.strictEqual( page.url(), pieceUrl, 'La pièce unitaire s’ajoute sans navigation.' );
        await pieceSelect.selectOption( '0' );
        assert( await page.locator( '[data-unitary-warning]' ).isHidden(), 'Pas de plus-value au retour sur conditionnement standard.' );
        assert( ( await page.locator( '.deprat-client-quantity-label' ).textContent() ).includes( 'conditionnements' ), 'Le champ repasse en nombre de conditionnements.' );
        await pieceQty.fill( '2' );
        assert( ( await page.locator( '[data-package-calculator]' ).textContent() ).includes( '100 pièces' ), 'Deux cartons de 50 représentent 100 pièces.' );
        console.log( 'Tubes standard et pièces libres : calculs, avertissement et panier AJAX validés.' );
        // Méga-menu et archives : chaque sous-page contient sa catégorie uniquement.
        await page.setViewportSize( { width: 1550, height: 1000 } );
        await page.goto( base + '/?product_cat=tubes', { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '.deprat-catalog-layout' );
        assert( await page.locator( '.deprat-mega-parent .deprat-mega-panel' ).count() > 0, 'Le menu principal Astra doit ouvrir le méga-menu des catégories.' );
        assert( ( await page.locator( '.deprat-mega-panel' ).first().textContent() ).includes( 'Embouts' ), 'Les sous-pages de catégorie apparaissent dans le méga-menu.' );
        assert( ( await page.locator( '.deprat-mega-parent' ).first().textContent() ).includes( 'Nos composants' ), 'Le menu Boutique est renommé Nos composants.' );
        await page.locator( '.deprat-mega-parent' ).first().hover();
        const megaPanel = page.locator( '.deprat-mega-panel' ).first();
        await megaPanel.waitFor( { state: 'visible' } );
        const megaRect = await megaPanel.boundingBox();
        const viewportWidth = page.viewportSize().width;
        assert( megaRect.x >= 0 && megaRect.x + megaRect.width <= viewportWidth + 2,
            'Le méga-menu doit rester entièrement visible même depuis un lien à droite de l’en-tête.' );
        assert( await megaPanel.locator( '.deprat-section' ).count() >= 1, 'Les familles réelles sont groupées sous un titre visuel.' );
        assert( await megaPanel.locator( '.deprat-section-heading h2' ).first().isVisible(), 'Le titre de section est visible.' );
        assert.strictEqual( await megaPanel.locator( '.deprat-section-heading a' ).count(), 0,
            'Les titres de section ne doivent jamais être des liens.' );
        assert( megaRect.width >= viewportWidth - 4, 'Le méga-menu occupe désormais la largeur complète de l’écran.' );
        assert.strictEqual( ( await megaPanel.locator( ' .deprat-mega-copy > strong' ).textContent() ).trim(),
            'Nos composants', 'Le bandeau doit afficher Nos composants.' );
        assert.strictEqual( await megaPanel.locator( '.deprat-mega-family-all' ).count(), 0,
            'Aucun doublon Voir les produits ne doit apparaître sous les catégories.' );
        const fullShopLink = megaPanel.locator( '.deprat-mega-viewall' );
        assert( ( await fullShopLink.textContent() ).includes( 'Voir tous les composants' ),
            'Le bouton Voir tous les composants reste distinct des catégories.' );
        const fullShopUrl = await fullShopLink.getAttribute( 'href' );
        assert( fullShopUrl.includes( 'deprat_all=1' ), 'Le bouton renvoie sur toutes les références, pas sur la grille des catégories.' );
        const introHeight = await megaPanel.locator( '.deprat-mega-intro' ).evaluate( element => element.getBoundingClientRect().height );
        assert( introHeight <= 125, 'Le bandeau doit rester compact et aligné.' );
        // La catégorie reste un lien, seul le chevron déroule ses sous-catégories existantes.
        const tubeFamily = megaPanel.locator( '.deprat-section-term', { has: megaPanel.locator( '.deprat-section-term-link', { hasText: 'Tubes' } ) } ).first();
        const tubeToggle = tubeFamily.locator( ':scope > .deprat-section-term-row > [data-deprat-section-toggle]' );
        if ( await tubeToggle.count() ) {
            const id = await tubeToggle.getAttribute( 'aria-controls' );
            assert.strictEqual( await page.locator( '[id="' + id + '"]' ).isVisible(), false,
                'Les sous-catégories sont fermées par défaut.' );
            await tubeToggle.click();
            assert.strictEqual( await tubeToggle.getAttribute( 'aria-expanded' ), 'true',
                'Le chevron déplie les vraies sous-catégories.' );
            assert( await page.locator( '[id="' + id + '"] .deprat-section-term-link' ).count() > 0,
                'Aucune sous-catégorie vide n’est inventée.' );
        }
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-mega-menu.png', fullPage: false } );

        assert( ( await page.locator( '.deprat-cat-hero h1' ).textContent() ).includes( 'Tubes' ), 'La page Tubes a son propre titre.' );
        assert( await page.locator( '.deprat-catalog-filter-form' ).isVisible(), 'La page Tubes affiche ses filtres.' );
        assert.strictEqual( await page.locator( '[data-catalog-card]' ).count(), 1, 'La page Tubes contient uniquement le tube.' );
        assert( ( await page.locator( '[data-catalog-card]' ).textContent() ).includes( 'Tube de test' ), 'Le tube doit apparaître.' );
        assert( ! ( await page.locator( '[data-catalog-card]' ).textContent() ).includes( 'Embout DEPRAT de test' ), 'Ne pas mélanger Embouts dans Tubes.' );
        assert( await page.locator( '[data-catalog-gallery] [data-gallery-slide]' ).count() >= 2, 'La carte récupère les photos WooCommerce de la fiche produit.' );
        await page.locator( '[data-catalog-gallery]' ).hover();
        await page.waitForFunction( () => document.querySelector( '[data-catalog-gallery] [data-gallery-slide="1"]' ).classList.contains( 'is-active' ) );
        assert( await page.locator( '[data-open-model]' ).isVisible(), 'La vue 360° apparaît si un fichier GLB est présent.' );
        assert( await page.locator( '.deprat-catalog-shape-badge' ).isVisible(), 'L’icône octogonale est visible en bas de l’image.' );
        assert( ( await page.locator( '.deprat-catalog-spec-list' ).textContent() ).includes( 'Finition' ), 'Les caractéristiques de la fiche sont visibles sur la carte.' );
        await page.locator( '[data-open-model]' ).click();
        assert( await page.locator( '[data-catalog-model-dialog]' ).isVisible(), 'La fenêtre 3D s’ouvre depuis la carte.' );
        assert( await page.locator( '[data-model-background]' ).isVisible(), 'Le bouton de fond 3D doit être visible.' );
        assert( await page.locator( '[data-model-render="contours"]' ).isVisible(), 'Le bouton de contours 3D doit être visible.' );
        const catalogueBackgroundSwitch = await page.locator( '[data-model-background]' ).evaluate( button => {
            const stage = document.querySelector( '.deprat-catalog-3d-stage' );
            const before = stage.getAttribute( 'data-3d-background' );
            // Le chargement GLB peut changer le fond automatique ENTRE deux appels
            // Playwright ; mesurer avant et après clic dans le même tour JS.
            button.click();
            return { before, after: stage.getAttribute( 'data-3d-background' ) };
        } );
        assert.notStrictEqual( catalogueBackgroundSwitch.after, catalogueBackgroundSwitch.before, 'Le fond change de contraste au premier clic quelle que soit la matière.' );
        await page.locator( '[data-model-render="contours"]' ).click();
        await page.locator( '[data-model-outline] canvas' ).waitFor( { state: 'visible', timeout: 30000 } );
        const renderedOutline = await page.locator( '[data-model-outline] canvas' ).evaluate( c => ( { width: c.width, height: c.height } ) );
        assert( renderedOutline.width > 10 && renderedOutline.height > 10, 'Un modèle GLB triangulé doit produire une vraie vue en contours.' );
        assert( await page.locator( '[data-model-viewer]' ).isHidden(), 'Le rendu texturé est masqué en vue Contours.' );
        await page.locator( '[data-model-render="texture"]' ).click();
        assert( await page.locator( '[data-model-viewer]' ).isVisible(), 'Le retour vers la texture fonctionne après les contours.' );
        await page.locator( '[data-model-close]' ).click();
        await page.locator( '[data-view="list"]' ).click();
        assert( await page.locator( '[data-catalog-products]' ).evaluate( node => node.classList.contains( 'is-list' ) ), 'Vue liste accessible.' );
        await page.locator( '[data-view="grid"]' ).click();
        assert( await page.locator( '[data-catalog-products]' ).evaluate( node => !node.classList.contains( 'is-list' ) ), 'Retour vue grille.' );
        const tubeFilter = page.locator( '[name="deprat_material"][value="acier-galvanise"]' );
        assert( await tubeFilter.count() === 1, 'La matière réelle du tube est proposée en filtre.' );
        assert( await page.locator( '.deprat-filter-feature [name="deprat_feature[finition]"]' ).count() === 1, 'Les autres caractéristiques du tube peuvent devenir des filtres.' );
        assert( await page.locator( '[name="deprat_shape"][value="octogonal"]' ).count() === 1, 'La forme octogonale doit pouvoir filtrer les tubes.' );
        await tubeFilter.check();
        await page.locator( '.deprat-catalog-apply' ).click();
        await page.waitForSelector( '[data-catalog-card]' );
        assert.strictEqual( await page.locator( '[data-catalog-card]' ).count(), 1, 'Le filtre matière conserve le tube.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-catalogue-tubes.png', fullPage: true } );
        const tubeArchiveUrl = page.url();
        const tubeCard = page.locator( '[data-catalog-card]' ).first();
        assert( await tubeCard.getAttribute( 'data-product-url' ), 'La carte connaît l’URL de la fiche détaillée.' );
        assert.strictEqual( await tubeCard.locator( '.deprat-catalog-package-fixed' ).count(), 0,
            'Un conditionnement n’allonge plus les cartes de boutique.' );
        const archiveAdd = page.locator( '[data-catalog-add] [type="submit"]' );
        assert( await archiveAdd.isVisible(), 'Ajout rapide de la demande disponible pour le tube sans prix.' );
        const compactActionLayout = await page.locator( '.deprat-catalog-action-row' ).evaluate( row => {
            const quantity = row.querySelector( '.deprat-catalog-stepper' ).getBoundingClientRect();
            const button = row.querySelector( '.deprat-catalog-add-button' ).getBoundingClientRect();
            return { sameLine: Math.abs( quantity.bottom - button.bottom ) < 5,
                nextTo: button.left >= quantity.right - 1, buttonWidth: button.width };
        } );
        assert( compactActionLayout.sameLine && compactActionLayout.nextTo && compactActionLayout.buttonWidth <= 160,
            'Le bouton Ajouter reste compact et placé à côté de la quantité.' );
        await archiveAdd.hover();
        const hoverColor = await archiveAdd.evaluate( element => getComputedStyle( element ).color );
        assert( /rgb\(255,\s*255,\s*255\)/.test( hoverColor ), 'Au survol le texte du bouton reste blanc et lisible.' );
        await archiveAdd.click();
        const packagePopup = page.locator( '[data-catalog-package-dialog]' );
        assert( await packagePopup.isVisible(), 'Plusieurs conditionnements ouvrent une popup avant toute requête.' );
        assert.strictEqual( await packagePopup.locator( 'input[name="deprat_popup_package"]' ).count(), 2 );
        assert( ( await packagePopup.locator( '[data-package-dialog-product-name]' ).textContent() ).includes( 'Tube de test' ),
            'La popup affiche la vraie fiche produit.' );
        await packagePopup.locator( 'input[name="deprat_popup_package"][value="1"]' ).check();
        await packagePopup.locator( '[data-package-dialog-quantity]' ).fill( '2' );
        assert( ( await packagePopup.locator( '[data-package-dialog-summary]' ).textContent() ).replace( /\s/g, '' ).includes( '1008' ),
            'Deux conditionnements de 504 mètres représentent 1 008 mètres.' );
        await packagePopup.locator( '[data-package-dialog-close]' ).first().click();
        assert( await packagePopup.isHidden(), 'Fermer la popup n’ajoute rien.' );
        assert.strictEqual( await page.locator( '[data-catalog-feedback].is-success' ).count(), 0,
            'La popup ne déclenche aucun ajout tant que le client ne confirme pas.' );
        await archiveAdd.click();
        await packagePopup.locator( '[data-package-dialog-confirm]' ).click();
        await page.locator( '[data-catalog-feedback].is-success' ).waitFor( { timeout: 20000 } );
        assert( await packagePopup.isHidden(), 'La popup se ferme une fois le devis mis à jour.' );
        assert.strictEqual( page.url(), tubeArchiveUrl, 'Ajout à la demande sans quitter Tubes.' );
        const productLink = await tubeCard.getAttribute( 'data-product-url' );
        await Promise.all( [
            page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
            tubeCard.locator( '.deprat-catalog-matter' ).click()
        ] );
        assert( page.url().includes( 'product' ) || page.url() === productLink,
            'Cliquer sur le texte de la carte ouvre la fiche produit complète.' );

        await page.goto( base + '/?product_cat=embouts', { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '.deprat-catalog-layout' );
        assert( ( await page.locator( '.deprat-cat-hero h1' ).textContent() ).includes( 'Embouts' ), 'La page Embouts a son propre titre.' );
        assert.strictEqual( await page.locator( '[data-catalog-card]' ).count(), 1, 'La page Embouts contient uniquement l’embout.' );
        assert( ( await page.locator( '[data-catalog-card]' ).textContent() ).includes( 'Embout DEPRAT de test' ), 'L’embout doit apparaître.' );
        assert.strictEqual( await page.locator( '[data-open-model]' ).count(), 0, 'Aucun bouton 360° inventé si le produit n’a pas de GLB.' );
        assert.strictEqual( await page.locator( '.deprat-catalog-package-selectable' ).count(), 0,
            'La carte reste compacte et ne montre plus le sélecteur permanent.' );
        await page.locator( '[data-catalog-add] [name="quantity"]' ).fill( '7' );
        await page.locator( '[data-catalog-add] [type="submit"]' ).click();
        const piecePopup = page.locator( '[data-catalog-package-dialog]' );
        assert( await piecePopup.isVisible(), 'Les deux modes de conditionnement ouvrent la fenêtre.' );
        await piecePopup.locator( 'input[name="deprat_popup_package"][value="unitary"]' ).check();
        assert( await piecePopup.locator( '[data-package-dialog-warning]' ).isVisible(),
            'La plus-value est bien signalée pour un conditionnement non standard.' );
        assert( ( await piecePopup.locator( '[data-package-dialog-summary]' ).textContent() ).includes( '7 pièces à l’unité' ),
            'Le récapitulatif est mis à jour avec la quantité de pièces.' );
        await piecePopup.locator( '[data-package-dialog-confirm]' ).click();
        await page.locator( '[data-catalog-feedback].is-success' ).waitFor( { timeout: 20000 } );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-catalogue-embouts.png', fullPage: true } );

        // Un produit sans aucun carton standard n'a qu'un seul choix (unité).
        // Son ajout AJAX reste direct, sans ouverture de fenêtre.
        await page.goto( fullShopUrl, { waitUntil: 'domcontentloaded' } );
        const singleOptionCard = page.locator( '[data-catalog-card]' ).filter( { hasText: 'Référence avec prix explicite' } ).first();
        assert( await singleOptionCard.isVisible(), 'La référence test avec une seule option est affichée.' );
        assert.strictEqual( await singleOptionCard.locator( '[data-catalog-add]' ).getAttribute( 'data-multiple-packages' ), 'false' );
        await singleOptionCard.locator( '[data-catalog-add] [type="submit"]' ).click();
        await singleOptionCard.locator( '[data-catalog-feedback].is-success' ).waitFor( { timeout: 20000 } );
        assert( await page.locator( '[data-catalog-package-dialog]' ).isHidden(),
            'Une seule option est ajoutée directement, sans popup.' );

        await page.goto( base + '/boutique/', { waitUntil: 'domcontentloaded' } );
        if ( await page.locator( '.deprat-catalog-hub' ).count() ) {
            assert( await page.locator( '.deprat-catalog-hub .deprat-section' ).count() >= 1,
                'La page Nos composants groupe les catégories existantes.' );
            assert( await page.locator( '.deprat-catalog-hub .deprat-section-term-link' ).count() >= 2,
                'Tubes et Embouts restent chacun un lien de catégorie.' );
            assert.strictEqual( await page.locator( '.deprat-catalog-hub-link' ).count(), 0,
                'Pas de texte Voir les produits sous les familles de la page boutique.' );
            assert( await page.locator( '.deprat-catalog-hub-viewall' ).isVisible(),
                'L’accès à tous les composants reste possible sur la page d’accueil des familles.' );
        }
        await page.goto( fullShopUrl, { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '.deprat-catalog-results' );
        assert( ( await page.locator( '.deprat-cat-hero h1' ).textContent() ).includes( 'Tous les composants' ),
            'Le bouton ouvre une page Tous les composants et non la page des catégories.' );
        const allProductsText = await page.locator( '[data-catalog-products]' ).textContent();
        assert( allProductsText.includes( 'Tube de test' ) && allProductsText.includes( 'Embout DEPRAT de test' ),
            'La vue globale liste les références de plusieurs catégories.' );
        assert( await page.locator( '.deprat-catalog-filter-form' ).isVisible(),
            'Le catalogue général conserve les filtres disponibles.' );
        const fullAllSort = page.locator( '[data-sort-select]' );
        await Promise.all( [
            page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
            fullAllSort.selectOption( 'recent' ),
        ] );
        assert( page.url().includes( 'deprat_all=1' ),
            'Le tri doit conserver la vue globale Toutes les pièces.' );
        assert( await page.locator( '[data-catalog-card]' ).count() >= 2,
            'Le tri doit conserver les références multi-catégories.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-catalogue-toutes-pieces.png', fullPage: true } );
        // Éditeur visuel : catégories racines, création imbriquée sur trois niveaux
        // et enregistrement de la hiérarchie native WooCommerce.
        await page.goto( base + '/wp-admin/edit.php?post_type=product&page=deprat-categories-tree', { waitUntil: 'domcontentloaded' } );
        await page.waitForSelector( '[data-deprat-category-tree] [data-tree-preview] .deprat-preview-item' );
        const editor = page.locator( '[data-tree-preview]' );
        assert( await editor.locator( '.deprat-catalog-sections--admin > [data-preview-section]' ).count() === 10,
            'Les rubriques du vrai méga-menu, y compris les zones vides de dépôt, sont en administration.' );
        assert( await editor.locator( '.deprat-section-heading h2' ).first().isVisible(), 'Les titres de section sont affichés.' );
        assert.strictEqual( await editor.locator( '.deprat-section-heading a' ).count(), 0,
            'Un titre de rubrique ne doit pas être cliquable, même en admin.' );
        assert( await editor.locator( '[data-section-key="tubes"] > [data-preview-item]' ).count() >= 2,
            'Les catégories Tubes et Embouts apparaissent réellement dans la grille administrable.' );
        assert( await editor.locator( '[data-preview-handle]' ).first().isVisible(),
            'La grille publique reproduite en admin permet de glisser les vraies catégories.' );
        await page.locator( '[data-tree-advanced] > summary' ).click();
        await page.waitForSelector( '[data-deprat-category-tree] .deprat-tree-root' );
        assert( await page.locator( '[data-tree-item]' ).count() >= 2, 'Les catégories déjà présentes sont visibles dans l’arborescence.' );
        assert( await page.locator( '[data-tree-item] [data-drag-handle]' ).first().isVisible(), 'La poignée drag-and-drop est visible.' );
        assert( await page.locator( '[data-tree-item] [data-tree-up]' ).first().isVisible(), 'Le bouton de réorganisation sans souris est disponible.' );
        assert( await page.locator( '[data-tree-item] [data-tree-section]' ).first().isVisible(),
            'Une rubrique peut être attribuée à une catégorie sans créer de famille WooCommerce.' );
        assert( await page.locator( '.deprat-tree-root' ).evaluate( list => !!window.jQuery( list ).data( 'ui-sortable' ) ),
            'Le déplacement à la souris doit être activé via jQuery UI Sortable.' );
        await page.locator( '[data-tree-create-root]' ).click();
        await page.locator( '[data-tree-create-name]' ).fill( 'Supports DEPRAT test' );
        await page.locator( '[data-tree-create-submit]' ).click();
        await page.locator( '.deprat-tree-root > [data-tree-item]', { hasText: 'Supports DEPRAT test' } ).waitFor();
        const supports = page.locator( '.deprat-tree-root > [data-tree-item]' ).filter( { hasText: 'Supports DEPRAT test' } ).first();
        // Tester le glisser-déposer réellement (la présence de sortable ne suffit pas).
        const originalFirstRoot = await page.locator( '.deprat-tree-root > [data-tree-item]' ).first();
        const originalFirstId = await originalFirstRoot.getAttribute( 'data-term-id' );
        const supportsId = await supports.getAttribute( 'data-term-id' );
        const sortableCancel = await page.locator( '.deprat-tree-root' ).evaluate( list => window.jQuery( list ).sortable( 'option', 'cancel' ) );
        assert( !sortableCancel.includes( 'button,' ) && sortableCancel.includes( 'button:not([data-drag-handle])' ),
            'La poignée-bouton doit être autorisée par jQuery UI Sortable.' );
        await supports.locator( ':scope > .deprat-tree-row [data-drag-handle]' )
            .dragTo( originalFirstRoot.locator( ':scope > .deprat-tree-row' ),
                { targetPosition: { x: 15, y: 5 }, steps: 20 } );
        assert.strictEqual( await page.locator( '.deprat-tree-root > [data-tree-item]' ).first().getAttribute( 'data-term-id' ), supportsId,
            'Glisser la poignée doit réellement modifier l’ordre des catégories.' );
        // Alternative accessible utilisable sans glisser (et sur les écrans tactiles).
        await supports.locator( ':scope > .deprat-tree-row [data-tree-down]' ).click();
        assert.strictEqual( await page.locator( '.deprat-tree-root > [data-tree-item]' ).first().getAttribute( 'data-term-id' ), originalFirstId,
            'Le bouton Descendre doit modifier l’ordre.' );
        await supports.locator( ':scope > .deprat-tree-row [data-tree-up]' ).click();
        assert.strictEqual( await page.locator( '.deprat-tree-root > [data-tree-item]' ).first().getAttribute( 'data-term-id' ), supportsId,
            'Le bouton Monter doit restaurer l’ordre.' );
        await supports.locator( ':scope > .deprat-tree-row [data-tree-add-child]' ).click();
        await page.locator( '[data-tree-create-name]' ).fill( 'Supports moteurs test' );
        await page.locator( '[data-tree-create-submit]' ).click();
        const motors = supports.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).filter( { hasText: 'Supports moteurs test' } ).first();
        await motors.waitFor();
        await motors.locator( ':scope > .deprat-tree-row [data-tree-add-child]' ).click();
        await page.locator( '[data-tree-create-name]' ).fill( 'Plaques fixation test' );
        await page.locator( '[data-tree-create-submit]' ).click();
        const plates = motors.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).filter( { hasText: 'Plaques fixation test' } ).first();
        await plates.waitFor();
        assert.strictEqual( await supports.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).count(), 1,
            'Une catégorie peut contenir une sous-catégorie.' );
        assert.strictEqual( await motors.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).count(), 1,
            'La sous-catégorie peut à son tour contenir un troisième niveau.' );
        // Les commandes ↑ / ↓ sont un complément accessible au glisser-déposer.
        await plates.locator( ':scope > .deprat-tree-row [data-tree-outdent]' ).click();
        assert.strictEqual( await supports.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).count(), 2,
            'Le bouton Remonter déplace effectivement la catégorie au niveau supérieur.' );
        await page.locator( '.deprat-tree-root > [data-tree-item]' ).filter( { hasText: 'Supports DEPRAT test' } )
            .locator( ':scope > .deprat-tree-list > [data-tree-item]' ).filter( { hasText: 'Plaques fixation test' } )
            .locator( ':scope > .deprat-tree-row [data-tree-indent]' ).click();
        assert.strictEqual( await motors.locator( ':scope > .deprat-tree-list > [data-tree-item]' ).count(), 1,
            'Le bouton Imbriquer restaure un troisième niveau de catégorie.' );
        await page.locator( '[data-tree-save]' ).first().click();
        await page.waitForFunction( () => {
            const status = document.querySelector( '[data-tree-status]' );
            return status && status.textContent.includes( 'enregistré' );
        }, { timeout: 25000 } );
        await page.reload( { waitUntil: 'domcontentloaded' } );
        assert( await page.locator( '.deprat-tree-root > [data-tree-item]' ).filter( { hasText: 'Supports DEPRAT test' } )
            .locator( ':scope > .deprat-tree-list > [data-tree-item] > .deprat-tree-list > [data-tree-item]' )
            .filter( { hasText: 'Plaques fixation test' } ).count() === 1,
            'La nouvelle arborescence doit être sauvegardée définitivement dans WooCommerce.' );
        // L'éditeur visuel doit reconstituer l'arborescence, pas seulement l'afficher.
        const visual = page.locator( '[data-tree-preview]' );
        const supportsVisual = visual.locator( '[data-section-key="autres"] > [data-preview-item]' )
            .filter( { hasText: 'Supports DEPRAT test' } ).first();
        await supportsVisual.waitFor();
        const moveSection = supportsVisual.locator( ':scope > .deprat-section-term-row [data-preview-section-select]' );
        await moveSection.selectOption( 'liaison' );
        assert( await visual.locator( '[data-section-key="liaison"] > [data-preview-item]' )
            .filter( { hasText: 'Supports DEPRAT test' } ).count() === 1,
            'Changer de rubrique dans la vue visuelle doit déplacer la catégorie immédiatement.' );
        // Le tri visuel doit alimenter la sauvegarde WooCommerce et la boutique.
        const emboutVisual = visual.locator( '[data-section-key="tubes"] > [data-preview-item]' )
            .filter( { hasText: 'Embouts' } ).first();
        const tubeVisual = visual.locator( '[data-section-key="tubes"] > [data-preview-item]' )
            .filter( { hasText: 'Tubes' } ).first();
        await emboutVisual.locator( ':scope > .deprat-section-term-row [data-preview-handle]' )
            .dragTo( tubeVisual.locator( ':scope > .deprat-section-term-row' ),
                { targetPosition: { x: 15, y: 4 }, steps: 20 } );
        assert.strictEqual( await visual.locator( '[data-section-key="tubes"] > [data-preview-item]' ).first()
            .getAttribute( 'data-term-id' ),
            await emboutVisual.getAttribute( 'data-term-id' ),
            'Le glisser-déposer visuel change réellement l’ordre sous la rubrique.' );
        await page.locator( '[data-tree-save]' ).first().click();
        await page.waitForFunction( () => {
            const status = document.querySelector( '[data-tree-status]' );
            return status && status.textContent.includes( 'enregistré' );
        }, { timeout: 25000 } );
        await page.reload( { waitUntil: 'domcontentloaded' } );
        assert( await page.locator( '[data-section-key="liaison"] > [data-preview-item]' )
            .filter( { hasText: 'Supports DEPRAT test' } ).count() === 1,
            'La rubrique choisie visuellement doit rester enregistrée après rechargement.' );
        // Depuis le titre d'une rubrique, le + propose deux créations distinctes.
        const garage = page.locator( '[data-preview-section="garage"]' );
        const plusGarage = garage.locator( '[data-preview-create-menu]' );
        await plusGarage.locator( 'summary' ).click();
        assert( await plusGarage.locator( '[data-preview-add-category]' ).isVisible(),
            'Le + affiche Créer une catégorie dans la rubrique.' );
        assert( await plusGarage.locator( '[data-preview-add-subcategory]' ).isDisabled(),
            'Une rubrique vide ne permet pas de créer une sous-catégorie sans parent.' );
        await plusGarage.locator( '[data-preview-add-category]' ).click();
        assert( await page.locator( '[data-tree-create-form]' ).isVisible(), 'Le choix ouvre la popup de création.' );
        assert.strictEqual( await page.locator( '[data-tree-create-form]' ).getAttribute( 'aria-modal' ), 'true' );
        assert( ( await page.locator( '[data-tree-create-context]' ).textContent() ).includes( 'Portes de garage' ),
            'La popup reprend le titre exact de la rubrique sélectionnée.' );
        assert( await page.locator( '[data-tree-create-parent-picker]' ).isHidden(),
            'Une catégorie principale n’a pas de parent à choisir.' );
        await page.locator( '[data-tree-create-name]' ).fill( 'Catégorie garage créée depuis menu' );
        await page.locator( '[data-tree-create-submit]' ).click();
        const newGarageFamily = garage.locator( '[data-section-key="garage"] > [data-preview-item]' )
            .filter( { hasText: 'Catégorie garage créée depuis menu' } ).first();
        await newGarageFamily.waitFor();
        await page.locator( '[data-tree-create-overlay]' ).waitFor( { state: 'hidden' } );
        const newGarageId = await newGarageFamily.getAttribute( 'data-term-id' );
        assert( newGarageId && Number( newGarageId ) > 0, 'La nouvelle catégorie WooCommerce a un identifiant réel.' );
        await plusGarage.locator( 'summary' ).click();
        assert( await plusGarage.locator( '[data-preview-add-subcategory]' ).isEnabled(),
            'La création de sous-catégories est activée lorsqu’un parent existe.' );
        await plusGarage.locator( '[data-preview-add-subcategory]' ).click();
        assert( await page.locator( '[data-tree-create-parent-picker]' ).isVisible(),
            'La popup propose de sélectionner la catégorie parente.' );
        await page.locator( '[data-tree-create-parent-select]' ).selectOption( newGarageId );
        await page.locator( '[data-tree-create-name]' ).fill( 'Sous-catégorie garage créée depuis menu' );
        await page.locator( '[data-tree-create-submit]' ).click();
        await page.locator( '[data-tree-create-overlay]' ).waitFor( { state: 'hidden' } );
        assert( await newGarageFamily.locator( ':scope > .deprat-section-children [data-preview-item]' )
            .filter( { hasText: 'Sous-catégorie garage créée depuis menu' } ).first().isVisible(),
            'La sous-catégorie créée apparaît immédiatement dépliée sous son parent.' );
        // Le formulaire utilise les mêmes droits/nonce que le gestionnaire existant.
        const invalidSectionStatus = await page.evaluate( async () => {
            const params = new URLSearchParams( {
                action: 'deprat_category_tree_create',
                nonce: window.depratCategoryTree.nonce,
                name: 'Catégorie interdite',
                parent: '0',
                section: 'rubrique-inconnue'
            } );
            const response = await fetch( window.depratCategoryTree.ajaxUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            } );
            return response.status;
        } );
        assert.strictEqual( invalidSectionStatus, 400, 'Une rubrique inconnue doit être rejetée côté serveur.' );
        await page.locator( '[data-tree-save]' ).first().click();
        await page.waitForFunction( () => {
            const status = document.querySelector( '[data-tree-status]' );
            return status && status.textContent.includes( 'enregistré' );
        }, { timeout: 25000 } );
        await page.reload( { waitUntil: 'domcontentloaded' } );
        const persistedGarage = page.locator( '[data-section-key="garage"] > [data-preview-item]' )
            .filter( { hasText: 'Catégorie garage créée depuis menu' } ).first();
        assert( await persistedGarage.isVisible(), 'La rubrique reste affectée à la catégorie après rechargement.' );
        await persistedGarage.locator( ':scope > .deprat-section-term-row [data-preview-toggle]' ).click();
        assert( await persistedGarage.locator( ':scope > .deprat-section-children [data-preview-item]' )
            .filter( { hasText: 'Sous-catégorie garage créée depuis menu' } ).first().isVisible(),
            'Le lien entre parent et sous-catégorie est réellement conservé dans WooCommerce.' );

        // Les titres de section ne sont PAS des termes WooCommerce :
        // création, choix d’icône, modification, ajout d’une catégorie et tri global.
        await page.locator( '[data-section-add]' ).click();
        assert( await page.locator( '[data-section-editor-form]' ).isVisible(),
            'Ajouter une section ouvre la popup de configuration.' );
        await page.locator( '[data-section-editor-name]' ).fill( 'Motorisations DEPRAT test' );
        await page.locator( 'input[name="deprat-section-icon"][value="treuil"]' ).check();
        await page.locator( '[data-section-editor-save]' ).click();
        await page.locator( '[data-section-editor-overlay]' ).waitFor( { state: 'hidden' } );
        const newSection = page.locator( '[data-preview-section^="custom_"]' )
            .filter( { has: page.locator( '.deprat-section-heading h2', { hasText: 'Motorisations DEPRAT test' } ) } ).first();
        await newSection.waitFor();
        const customKey = await newSection.getAttribute( 'data-preview-section' );
        assert( /^custom_[a-f0-9]{12}$/.test( customKey ),
            'Une section personnalisée a une clé stable, distincte des catégories.' );
        assert( await newSection.locator( '.deprat-section-icon-wrap svg' ).isVisible(),
            'L’icône issue de la palette apparaît dans la même grille.' );
        await newSection.locator( '[data-preview-edit-section]' ).click();
        await page.locator( '[data-section-editor-name]' ).fill( 'Motorisations et accessoires test' );
        await page.locator( 'input[name="deprat-section-icon"][value="liaison"]' ).check();
        await page.locator( '[data-section-editor-save]' ).click();
        await page.locator( '[data-section-editor-overlay]' ).waitFor( { state: 'hidden' } );
        const updatedSection = page.locator( '[data-preview-section="' + customKey + '"]' );
        assert( ( await updatedSection.locator( '.deprat-section-heading h2' ).textContent() ).includes( 'Motorisations et accessoires test' ),
            'Renommer une section garde sa clé et ses futures catégories.' );
        await updatedSection.locator( '[data-preview-create-menu] summary' ).click();
        await updatedSection.locator( '[data-preview-add-category]' ).click();
        await page.locator( '[data-tree-create-name]' ).fill( 'Composants moteur test' );
        await page.locator( '[data-tree-create-submit]' ).click();
        await page.locator( '[data-tree-create-overlay]' ).waitFor( { state: 'hidden' } );
        assert( await updatedSection.locator( '[data-preview-list] > [data-preview-item]' )
            .filter( { hasText: 'Composants moteur test' } ).first().isVisible(),
            'La nouvelle section peut recevoir une véritable catégorie WooCommerce.' );
        const firstSection = page.locator( '[data-preview-grid] > [data-preview-section]' ).first();
        await updatedSection.locator( '[data-section-drag-handle]' ).dragTo(
            firstSection.locator( '.deprat-section-heading' ),
            { targetPosition: { x: 20, y: 4 }, steps: 20 }
        );
        assert.strictEqual(
            await page.locator( '[data-preview-grid] > [data-preview-section]' ).first().getAttribute( 'data-preview-section' ),
            customKey, 'Glisser la section déplace l’intégralité du bloc et ses catégories.'
        );
        await page.locator( '[data-tree-save]' ).first().click();
        await page.waitForFunction( () => {
            const status = document.querySelector( '[data-tree-status]' );
            return status && status.textContent.includes( 'enregistré' );
        }, { timeout: 25000 } );
        await page.reload( { waitUntil: 'domcontentloaded' } );
        assert.strictEqual( await page.locator( '[data-preview-grid] > [data-preview-section]' ).first()
            .getAttribute( 'data-preview-section' ), customKey,
            'L’ordre des sections reste identique après sauvegarde et rechargement.' );
        assert( ( await page.locator( '[data-preview-section="' + customKey + '"] .deprat-section-heading h2' ).textContent() )
            .includes( 'Motorisations et accessoires test' ), 'Le nouveau nom est conservé.' );
        assert( await page.locator( '[data-preview-section="' + customKey + '"] [data-preview-item]' )
            .filter( { hasText: 'Composants moteur test' } ).count() === 1,
            'Les catégories de la section déplacée restent rattachées.' );
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-arborescence-admin.png', fullPage: true } );
        await page.goto( base + '/?product_cat=tubes', { waitUntil: 'domcontentloaded' } );
        const megaSectionHeaders = page.locator( '.deprat-mega-panel .deprat-section-heading h2' );
        assert( ( await megaSectionHeaders.first().textContent() ).includes( 'Motorisations et accessoires test' ),
            'Le vrai méga-menu reprend le titre et l’ordre de la section créée.' );
        assert( ( await page.locator( '.deprat-mega-panel' ).first().textContent() ).includes( 'Plaques fixation test' ),
            'Le méga-menu reprend toutes les sous-catégories de la nouvelle arborescence.' );
        // Les crayons des sections ET des catégories restent dans la même page.
        await page.goto( base + '/wp-admin/edit.php?post_type=product&page=deprat-categories-tree',
            { waitUntil: 'domcontentloaded' } );
        const editPageUrl = page.url();
        const editableCategory = page.locator( '[data-preview-section="' + customKey + '"] [data-preview-item]' )
            .filter( { hasText: 'Composants moteur test' } ).first();
        await editableCategory.locator( '[data-preview-edit-category]' ).click();
        assert.strictEqual( page.url(), editPageUrl,
            'Le crayon de catégorie ne doit pas ouvrir une autre page.' );
        assert( await page.locator( '[data-tree-edit-form]' ).isVisible(),
            'Une fenêtre avec le nom de catégorie s’ouvre directement sur le menu.' );
        assert.strictEqual( await page.locator( '[data-tree-edit-form]' ).getAttribute( 'aria-modal' ), 'true' );
        assert.strictEqual( await page.locator( '[data-tree-edit-name]' ).inputValue(), 'Composants moteur test',
            'Le nom actuel est prérempli dans la popup.' );
        assert( ( await page.locator( '[data-tree-edit-full-link]' ).getAttribute( 'href' ) ).includes( 'term.php' ),
            'La fiche WooCommerce complète reste accessible volontairement depuis la popup.' );
        await page.locator( '[data-tree-edit-close]' ).last().click();
        assert( await page.locator( '[data-tree-edit-overlay]' ).isHidden(), 'Annuler ferme la popup.' );
        assert.strictEqual( page.url(), editPageUrl, 'Annuler ne navigue pas.' );
        await editableCategory.locator( '[data-preview-edit-category]' ).click();
        await page.locator( '[data-tree-edit-name]' ).fill( 'Composants moteur renommés test' );
        await page.locator( '[data-tree-edit-save]' ).click();
        await page.locator( '[data-tree-edit-overlay]' ).waitFor( { state: 'hidden' } );
        assert.strictEqual( page.url(), editPageUrl, 'Le nom est modifié sans redirection.' );
        assert( await page.locator( '[data-preview-section="' + customKey + '"] [data-preview-item]' )
            .filter( { hasText: 'Composants moteur renommés test' } ).first().isVisible(),
            'Le méga-menu est rafraîchi immédiatement après modification du nom.' );
        await page.locator( '[data-tree-advanced] > summary' ).click();
        const categoryAdvanced = page.locator( '[data-tree-item]' )
            .filter( { has: page.locator( ':scope > .deprat-tree-row [data-tree-name]',
                { hasText: 'Composants moteur renommés test' } ) } ).first();
        await categoryAdvanced.locator( ':scope > .deprat-tree-row [data-tree-edit-category]' ).click();
        assert( await page.locator( '[data-tree-edit-form]' ).isVisible(),
            'Le bouton Modifier dans la gestion avancée ouvre la même popup.' );
        await page.keyboard.press( 'Escape' );
        assert( await page.locator( '[data-tree-edit-overlay]' ).isHidden(), 'La touche Escape ferme la popup.' );
        await page.reload( { waitUntil: 'domcontentloaded' } );
        assert( await page.locator( '[data-preview-section="' + customKey + '"] [data-preview-item]' )
            .filter( { hasText: 'Composants moteur renommés test' } ).first().isVisible(),
            'Le nouveau nom WooCommerce persiste après rechargement sans altérer le classement.' );
        const rejectedDuplicate = await page.evaluate( async () => {
            const term = document.querySelector( '[data-preview-section^="custom_"] [data-preview-item]' );
            const payload = new URLSearchParams( {
                action: 'deprat_category_tree_edit_category',
                nonce: window.depratCategoryTree.nonce,
                term_id: term.getAttribute( 'data-term-id' ),
                name: 'Tubes'
            } );
            const response = await fetch( window.depratCategoryTree.ajaxUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload.toString()
            } );
            return response.status;
        } );
        assert.strictEqual( rejectedDuplicate, 409, 'Un doublon de catégorie au même niveau doit être refusé.' );


        // Un visiteur non connecté ne voit pas et ne peut pas ouvrir son historique.
        const guest = await browser.newPage();
        try {
            await guest.goto( base + '/?pagename=mes-demandes-devis', { waitUntil: 'domcontentloaded' } );
            assert( ! guest.url().includes( 'mes-demandes-devis' ), 'Mes demandes privées redirige vers Mon compte pour les visiteurs.' );
        } finally { await guest.close(); }
        console.log( 'Archives Tubes/Embouts, filtres, galerie, 360°, grille/liste, ajout AJAX et accès privé validés.' );

    } catch ( error ) {
        await page.screenshot( { path: process.env.RUNNER_TEMP + '/deprat-editor-error.png', fullPage: true } ).catch( () => {} );
        throw error;
    } finally {
        await browser.close();
    }
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
