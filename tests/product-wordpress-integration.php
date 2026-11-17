<?php
/**
 * Exécuté par WP-CLI sur une installation WordPress/WooCommerce temporaire.
 */
function deprat_integration_assert( $condition, $message ) {
    if ( ! $condition ) {
        WP_CLI::error( $message );
    }
}

$admin = get_user_by( 'login', 'deprat-test' );
deprat_integration_assert( (bool) $admin, 'Compte administrateur de test introuvable.' );
wp_set_current_user( $admin->ID );

$product = new WC_Product_Simple();
$product->set_name( 'Tube de test' );
$product->set_sku( 'TEST-DP62' );
$product->set_status( 'publish' );
$product_id = $product->save();
deprat_integration_assert( (bool) $product_id, 'Création du produit de test impossible.' );
$no_price_tube = wc_get_product( $product_id );
deprat_integration_assert( '' === $no_price_tube->get_price( 'edit' ), 'Aucun prix ne doit être enregistré pour le tube de test.' );
deprat_integration_assert( '0' === (string) $no_price_tube->get_price(), 'WooCommerce doit utiliser un zéro technique uniquement dans les calculs.' );
deprat_integration_assert( $no_price_tube->is_purchasable(), 'Un tube publié sans prix doit rester ajoutable au devis.' );
deprat_integration_assert( '' === $no_price_tube->get_price_html(), 'Aucun 0 € ne doit être visible dans le catalogue.' );

$tube_category = wp_insert_term( 'Tubes', 'product_cat' );
$end_category = wp_insert_term( 'Embouts', 'product_cat' );
deprat_integration_assert( ! is_wp_error( $tube_category ) && ! is_wp_error( $end_category ), 'Création des catégories de test impossible.' );
wp_set_object_terms( $product_id, array( (int) $tube_category['term_id'] ), 'product_cat' );
deprat_integration_assert( deprat_packaging_is_tube_product( $product_id ), 'Un produit de la catégorie Tubes doit être reconnu comme tube.' );
deprat_integration_assert( ! deprat_packaging_allow_unitary( $product_id ), 'La catégorie Tubes doit interdire le conditionnement unitaire.' );

// Un véritable produit pièce pour tester le second parcours dans le navigateur.
$piece_product = new WC_Product_Simple();
$piece_product->set_name( 'Embout DEPRAT de test' );
$piece_product->set_sku( 'TEST-EMBOUT' );
$piece_product->set_status( 'publish' );
$piece_id = $piece_product->save();
$no_price_piece = wc_get_product( $piece_id );
deprat_integration_assert( '' === $no_price_piece->get_price( 'edit' ), 'La pièce sans prix doit rester sans tarif enregistré.' );
deprat_integration_assert( $no_price_piece->is_purchasable(), 'La pièce sans prix doit pouvoir être ajoutée au devis.' );
deprat_integration_assert( 'Sur devis' === deprat_quote_cart_price_label( '0 €', array( 'data' => $no_price_piece ) ), 'Le panier affiche Sur devis, jamais 0 €.' );

wp_set_object_terms( $piece_id, array( (int) $end_category['term_id'] ), 'product_cat' );
update_post_meta( $piece_id, '_deprat_product_technical', array(
    'description' => 'Pièce détachée pour volet roulant.',
    'packages' => array(
        array( 'mode' => 'pieces', 'title' => 'Conditionnement professionnel',
            'count' => 1, 'type' => 'carton', 'pieces' => 50 ),
    ),
) );
deprat_integration_assert( deprat_packaging_allow_unitary( $piece_id ), 'La catégorie pièces doit autoriser la quantité libre.' );
deprat_integration_assert( null === deprat_packaging_request_row( $product_id, 'unitary' ), 'Le tube ne peut jamais recevoir le mode unitaire.' );
deprat_integration_assert( 'unitary' === deprat_packaging_request_row( $piece_id, 'unitary' )['mode'], 'La pièce propose la vente unitaire.' );


// Un tarif réel, s'il existe, ne doit pas être modifié par le mode sans prix.
$priced_product = new WC_Product_Simple();
$priced_product->set_name( 'Référence avec prix explicite' );
$priced_product->set_regular_price( '24.90' );
$priced_product->set_status( 'publish' );
$priced_id = $priced_product->save();
deprat_integration_assert( '24.90' === (string) wc_get_product( $priced_id )->get_price(), 'Un prix réellement renseigné ne doit pas devenir zéro.' );

// Un produit hors stock ne doit pas réapparaître comme disponible.
$unavailable_product = new WC_Product_Simple();
$unavailable_product->set_name( 'Produit sans stock et sans prix' );
$unavailable_product->set_status( 'publish' );
$unavailable_product->set_stock_status( 'outofstock' );
$unavailable_id = $unavailable_product->save();
deprat_integration_assert( ! wc_get_product( $unavailable_id )->is_in_stock(), 'Le mode devis ne doit pas contourner la disponibilité du stock.' );

$image = wp_insert_attachment( array(
    'post_title' => 'Schéma de test',
    'post_mime_type' => 'image/png',
    'post_status' => 'inherit',
) );
$pdf = wp_insert_attachment( array(
    'post_title' => 'Fiche de test',
    'post_mime_type' => 'application/pdf',
    'post_status' => 'inherit',
) );
$gallery_image = wp_insert_attachment( array( 'post_title' => 'Photo galerie', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
$pdf2 = wp_insert_attachment( array( 'post_title' => 'Notice de montage', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ) );
$model3d = wp_insert_attachment( array( 'post_title' => 'Tube DP62 – Vue 3D', 'post_mime_type' => 'model/gltf-binary', 'post_status' => 'inherit' ) );
update_post_meta( $image, '_wp_attached_file', 'integration-test/schema.png' );
update_post_meta( $gallery_image, '_wp_attached_file', 'integration-test/galerie.png' );
update_post_meta( $pdf2, '_wp_attached_file', 'integration-test/notice.pdf' );
update_post_meta( $model3d, '_wp_attached_file', 'integration-test/modele.glb' );
update_post_meta( $pdf, '_wp_attached_file', 'integration-test/fiche.pdf' );
deprat_integration_assert( ! is_wp_error( $image ) && ! is_wp_error( $pdf ), 'Création des médias de test impossible.' );
$uploads = wp_upload_dir();
wp_mkdir_p( $uploads['basedir'] . '/integration-test' );
file_put_contents( $uploads['basedir'] . '/integration-test/fiche.pdf', "%PDF-1.4\n%%EOF\n" );
file_put_contents( $uploads['basedir'] . '/integration-test/schema.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRr0AAAAASUVORK5CYII=' ) );
file_put_contents( $uploads['basedir'] . '/integration-test/galerie.png', file_get_contents( $uploads['basedir'] . '/integration-test/schema.png' ) );
file_put_contents( $uploads['basedir'] . '/integration-test/notice.pdf', "%PDF-1.4\\n%%EOF\\n" );
// Modèle GLB réel de test : un cube composé de 12 triangles.
// Sert à vérifier les angles nets sans montrer les diagonales du maillage.
$vertex_positions = array(
    array( -1, -1, -1 ), array( 1, -1, -1 ), array( 1, 1, -1 ), array( -1, 1, -1 ),
    array( -1, -1, 1 ), array( 1, -1, 1 ), array( 1, 1, 1 ), array( -1, 1, 1 ),
);
$face_indices = array(
    4, 5, 6, 4, 6, 7, 0, 2, 1, 0, 3, 2,
    0, 4, 7, 0, 7, 3, 1, 2, 6, 1, 6, 5,
    3, 7, 6, 3, 6, 2, 0, 1, 5, 0, 5, 4,
);
$glb_data = '';
foreach ( $vertex_positions as $coords ) {
    foreach ( $coords as $v ) { $glb_data .= pack( 'g', (float) $v ); }
}
foreach ( $face_indices as $v ) { $glb_data .= pack( 'v', $v ); }
$glb_doc = array(
    'asset' => array( 'version' => '2.0' ),
    'scene' => 0,
    'scenes' => array( array( 'nodes' => array( 0 ) ) ),
    'nodes' => array( array( 'mesh' => 0 ) ),
    'meshes' => array( array( 'primitives' => array( array( 'attributes' => array( 'POSITION' => 0 ), 'indices' => 1, 'mode' => 4 ) ) ) ),
    'buffers' => array( array( 'byteLength' => strlen( $glb_data ) ) ),
    'bufferViews' => array(
        array( 'buffer' => 0, 'byteOffset' => 0, 'byteLength' => 96, 'target' => 34962 ),
        array( 'buffer' => 0, 'byteOffset' => 96, 'byteLength' => 72, 'target' => 34963 ),
    ),
    'accessors' => array(
        array( 'bufferView' => 0, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC3', 'min' => array( -1, -1, -1 ), 'max' => array( 1, 1, 1 ) ),
        array( 'bufferView' => 1, 'componentType' => 5123, 'count' => 36, 'type' => 'SCALAR' ),
    ),
);
$glb_json = wp_json_encode( $glb_doc );
$glb_json .= str_repeat( ' ', ( 4 - strlen( $glb_json ) % 4 ) % 4 );
$glb_binary = 'glTF' . pack( 'V', 2 ) . pack( 'V', 28 + strlen( $glb_json ) + strlen( $glb_data ) )
    . pack( 'V', strlen( $glb_json ) ) . 'JSON' . $glb_json
    . pack( 'V', strlen( $glb_data ) ) . "BIN\0" . $glb_data;
file_put_contents( $uploads['basedir'] . '/integration-test/modele.glb', $glb_binary );
deprat_integration_assert( deprat_product_3d_valid_binary( $uploads['basedir'] . '/integration-test/modele.glb', strlen( $glb_binary ) ), 'Le GLB exemple doit être valide.' );
set_post_thumbnail( $product_id, $image );
$product_for_gallery = wc_get_product( $product_id );
$product_for_gallery->set_gallery_image_ids( array( $gallery_image ) );
$product_for_gallery->save();

$_POST = array(
    'deprat_product_tech_nonce' => wp_create_nonce( 'deprat_product_tech_save' ),
    'deprat_product_glb' => (string) $model3d,
    'deprat_product_tech' => array(
        'description' => 'Description technique \\ spéciale',
        'shape' => 'octogonal',
        'features' => array(
            array( 'label' => 'Matière', 'value' => 'Acier galvanisé' ),
            array( 'label' => 'Épaisseur', 'value' => '0,6 mm' ),
            array( 'label' => 'Finition', 'value' => 'Z275' ),
            array( 'label' => 'Zéro', 'value' => '0' ),
        ),
        'norms' => array( 'NF EN 12194' ),
        'tests' => array( array( 'label' => 'Couple de destruction', 'value' => '5 m.kg' ) ),
        'delivery' => 'Longueurs de 6 m',
        'packaging' => 'Lot',
        'package_count' => '2',
        'package_quantity' => '96',
        'technical_image' => (string) $image,
        'dimensions_image' => (string) $image,
        'datasheet' => (string) $pdf,
        'datasheets_present' => '1',
        'datasheets' => array(
            array( 'title' => 'Fiche produit', 'file' => (string) $pdf ),
            array( 'title' => 'Notice complémentaire', 'file' => (string) $pdf2 ),
        ),
        'diagrams_present' => '1',
        'diagrams' => array( array( 'title' => 'Schéma dimensionnel personnalisé', 'image' => (string) $image ) ),
    ),
);
$_POST = wp_slash( $_POST );
do_action( 'save_post_product', $product_id, get_post( $product_id ), true );
$saved = deprat_product_tech_data( $product_id );
deprat_integration_assert( deprat_product_3d_model( $product_id ) === (int) $model3d, 'Modèle GLB non enregistré.' );
deprat_integration_assert( isset( $saved['features'][0]['value'] ) && 'Acier galvanisé' === $saved['features'][0]['value'], 'La caractéristique ne s’enregistre pas.' );
deprat_integration_assert( 'octogonal' === deprat_product_shape( $product_id ), 'La forme Octogonale doit être enregistrée.' );
deprat_integration_assert( false !== strpos( deprat_product_shape_icon( 'octogonal' ), '<polygon' ), 'L’icône octogonale doit être vectorielle.' );
deprat_integration_assert( false !== strpos( deprat_product_shape_icon( 'rond' ), '<circle' ), 'L’icône ronde doit être vectorielle.' );
deprat_integration_assert( '' === deprat_product_shape_normalize( 'profil inconnu' ), 'Une forme non prévue ne doit pas générer d’icône.' );
deprat_integration_assert( 'Description technique \\ spéciale' === $saved['description'], 'Un caractère spécial de la description a été perdu.' );
deprat_integration_assert( (int) $pdf === $saved['datasheet'] && (int) $image === $saved['technical_image'], 'Les médias ne s’enregistrent pas.' );
deprat_integration_assert( 'TEST-DP62' === wc_get_product( $product_id )->get_sku(), 'La référence WooCommerce a été modifiée.' );
deprat_integration_assert( 2 === count( deprat_product_tech_datasheets( $saved ) ), 'Les deux PDF sont enregistrés.' );
deprat_integration_assert( '' !== get_post_meta( $image, '_wp_attachment_image_alt', true ), 'Le schéma doit recevoir un ALT automatique.' );
deprat_integration_assert( false !== strpos( deprat_product_tech_seo_description( $product_id ), 'TEST-DP62' ), 'La méta-description doit intégrer la référence.' );
deprat_integration_assert( (int) $gallery_image === (int) wc_get_product( $product_id )->get_gallery_image_ids()[0], 'La galerie WooCommerce doit être conservée.' );
deprat_integration_assert( false !== has_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front' ), 'L’affichage boutique n’est pas raccordé à WooCommerce.' );
$tabs = apply_filters( 'woocommerce_product_tabs', array(
    'description' => array( 'title' => 'Description' ),
    'additional_information' => array( 'title' => 'Informations complémentaires' ),
    'reviews' => array( 'title' => 'Avis' ),
) );
deprat_integration_assert( ! isset( $tabs['description'] ) && ! isset( $tabs['additional_information'] ), 'Les deux onglets WooCommerce en doublon doivent disparaître.' );
deprat_integration_assert( isset( $tabs['reviews'] ), 'Les autres onglets personnalisés ou avis doivent être conservés.' );


ob_start();
deprat_product_tech_render_admin( get_post( $product_id ) );
$admin_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé', 'Normes', 'Valeurs testées', 'Mode de calcul', 'Fiches techniques PDF', 'Visualisation 3D', 'Fichier 3D SolidWorks (.GLB)', 'Schéma dimensionnel personnalisé' ) as $expected ) {
    deprat_integration_assert( false !== strpos( $admin_html, $expected ), 'Champ absent de l’administration : ' . $expected );
}

$GLOBALS['product'] = wc_get_product( $product_id );
ob_start();
deprat_product_tech_render_front();
$front_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé', '>0<', 'NF EN 12194', 'Couple de destruction', 'Longueurs de 6 m', '192 pièces', 'Schéma dimensionnel personnalisé', 'Notice complémentaire', 'Télécharger la fiche technique PDF' ) as $expected ) {
    deprat_integration_assert( false !== strpos( $front_html, $expected ), 'Information absente de la boutique : ' . $expected );
}

$GLOBALS['product'] = wc_get_product( $product_id );
ob_start();
deprat_product_3d_gallery_toggle();
$viewer_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $viewer_html, 'Vue 3D' ) && false !== strpos( $viewer_html, 'model-viewer' ) && false !== strpos( $viewer_html, 'modele.glb' ), 'La vue 3D doit être proposée au client.' );
ob_start();
$GLOBALS['product'] = wc_get_product( $product_id );
deprat_product_single_intro();
$header_html = ob_get_clean();
deprat_integration_assert( strpos( $header_html, 'Référence produit' ) < strpos( $header_html, 'Tube de test' ), 'Référence avant nom du produit.' );
$_POST['deprat_product_tech_nonce'] = 'invalide';
$_POST['deprat_product_glb'] = '';
$_POST['deprat_product_tech']['delivery'] = 'Ne doit pas être enregistré';
do_action( 'save_post_product', $product_id, get_post( $product_id ), true );

$GLOBALS['product'] = wc_get_product( $product_id );
$package = deprat_client_product_package( $product_id );
deprat_integration_assert( '2 Lots de 96 pièces chacun' === deprat_client_product_package_label( $package ), 'Le conditionnement B2B doit reprendre les données du produit.' );
ob_start();
deprat_client_product_intro();
$client_intro_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $client_intro_html, 'Réf.' ) && false !== strpos( $client_intro_html, 'En stock' )
    && false !== strpos( $client_intro_html, 'Conditionnement' ), 'Identité et disponibilité du client non affichées.' );
ob_start();
deprat_client_product_quote();
$client_quote_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $client_quote_html, 'Ajouter à ma demande de devis' )
    && false !== strpos( $client_quote_html, 'data-package-calculator' )
    && false !== strpos( $client_quote_html, 'Contacter un conseiller' ), 'Encart devis incomplet.' );
ob_start();
deprat_client_product_tabs();
$client_tabs_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Tests &amp; normes', 'Documents &amp; schémas', 'Schéma dimensionnel personnalisé', 'Notice complémentaire' ) as $label ) {
    deprat_integration_assert( false !== strpos( $client_tabs_html, $label ), 'Onglet DEPRAT manquant : ' . $label );
}

$unchanged = deprat_product_tech_data( $product_id );
deprat_integration_assert( 'Longueurs de 6 m' === $unchanged['delivery'], 'Une sauvegarde sans nonce valide a été acceptée.' );
deprat_integration_assert( (int) $model3d === deprat_product_3d_model( $product_id ), 'Un nonce invalide ne doit pas supprimer la vue 3D.' );

$GLOBALS['product'] = wc_get_product( $product_id );
ob_start();
deprat_client_product_package_selector();
$tube_selector = ob_get_clean();
deprat_integration_assert( false === strpos( $tube_selector, 'value="unitary"' ), 'Aucun choix de conditionnement unitaire pour les tubes.' );
$GLOBALS['product'] = wc_get_product( $piece_id );
ob_start();
deprat_client_product_package_selector();
$piece_selector = ob_get_clean();
deprat_integration_assert( false !== strpos( $piece_selector, 'value="unitary"' ), 'Le sélecteur de pièces doit offrir la quantité libre.' );
ob_start();
deprat_client_product_quote();
$piece_quote = ob_get_clean();
deprat_integration_assert( false !== strpos( $piece_quote, 'data-unitary-warning' ), 'L’avertissement de plus-value doit être présent.' );

$_POST = array( 'deprat_package_choice' => 'unitary' );
deprat_integration_assert( true === deprat_packaging_validate_cart_choice( true, $piece_id, 7 ), 'La demande libre doit être autorisée sur une pièce.' );
$unit_cart_data = deprat_packaging_cart_item_data( array(), $piece_id, 0, 7 );
deprat_integration_assert( ! empty( $unit_cart_data['deprat_nonstandard_packaging'] ) && 'unitary' === $unit_cart_data['deprat_package_index'], 'Le panier doit mémoriser le caractère non standard.' );
$unit_cart_display = deprat_packaging_cart_item_display( array(), array_merge( $unit_cart_data, array( 'quantity' => 7 ) ) );
$unit_cart_values = implode( ' ', array_column( $unit_cart_display, 'value' ) );
deprat_integration_assert( false !== strpos( $unit_cart_values, '7 pièces' ) && false !== strpos( $unit_cart_values, 'plus-value' ), 'La quantité libre et la plus-value doivent être visibles dans la demande.' );
$_POST = array( 'deprat_package_choice' => '0' );
$standard_cart_data = deprat_packaging_cart_item_data( array(), $piece_id, 0, 2 );
deprat_integration_assert( 'pieces' === $standard_cart_data['deprat_package']['mode'] && empty( $standard_cart_data['deprat_nonstandard_packaging'] ), 'La commande standard ne doit pas inclure de plus-value.' );


// Catalogue : chaque famille doit présenter uniquement ses références.
$tubes_term = get_term( (int) $tube_category['term_id'], 'product_cat' );
$embouts_term = get_term( (int) $end_category['term_id'], 'product_cat' );
$families = deprat_catalog_root_categories();
$family_ids = array_map( static function ( $term ) { return (int) $term->term_id; }, $families );
deprat_integration_assert( in_array( (int) $tubes_term->term_id, $family_ids, true ), 'Tubes doit figurer dans le méga-menu.' );
deprat_integration_assert( in_array( (int) $embouts_term->term_id, $family_ids, true ), 'Embouts doit figurer dans le méga-menu.' );
$tube_index = deprat_catalog_category_index( $tubes_term );
$embout_index = deprat_catalog_category_index( $embouts_term );
deprat_integration_assert( isset( $tube_index[ $product_id ] ) && ! isset( $tube_index[ $piece_id ] ), 'La page Tubes doit exclure les embouts.' );
deprat_integration_assert( isset( $embout_index[ $piece_id ] ) && ! isset( $embout_index[ $product_id ] ), 'La page Embouts doit exclure les tubes.' );
deprat_integration_assert( 'Acier galvanisé' === $tube_index[ $product_id ]['material'], 'La matière de la fiche doit être disponible dans les filtres.' );
deprat_integration_assert( 'Octogonal' === $tube_index[ $product_id ]['shape'], 'Le choix de forme doit alimenter le filtre des tubes.' );
$facets = deprat_catalog_feature_facets( $tube_index );
deprat_integration_assert( isset( $facets['finition']['options']['z275'] ), 'La caractéristique Finition doit être un filtre de catégorie.' );
$technical_card = deprat_catalog_card_features( $tube_index[ $product_id ] );
deprat_integration_assert( count( $technical_card ) >= 2, 'La carte doit reprendre les caractéristiques disponibles.' );
$_GET['deprat_feature'] = array( 'finition' => 'z275' );
$feature_filter = deprat_catalog_filters();
deprat_integration_assert( deprat_catalog_matches( $tube_index[ $product_id ], $feature_filter ), 'Une caractéristique de fiche doit filtrer son produit.' );
$_GET['deprat_feature'] = array( 'finition' => 'autre' );
$feature_filter = deprat_catalog_filters();
deprat_integration_assert( ! deprat_catalog_matches( $tube_index[ $product_id ], $feature_filter ), 'Une valeur de caractéristique différente doit exclure le produit.' );
unset( $_GET['deprat_feature'] );

$_GET['deprat_material'] = 'acier-galvanise';
$filters = deprat_catalog_filters();
deprat_integration_assert( deprat_catalog_matches( $tube_index[ $product_id ], $filters ), 'La matière filtrée doit correspondre au tube.' );
deprat_integration_assert( ! deprat_catalog_matches( $embout_index[ $piece_id ], $filters ), 'La matière absente ne doit pas correspondre au filtre.' );
$filtered_tubes = deprat_catalog_query( $tubes_term, $tube_index, $filters );
deprat_integration_assert( $filtered_tubes->have_posts(), 'Le filtre matière doit retourner le tube.' );
wp_reset_postdata();
unset( $_GET['deprat_material'] );

$shop_page_id = wc_get_page_id( 'shop' );
if ( $shop_page_id <= 0 ) {
    $shop_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => 'Boutique', 'post_name' => 'boutique' ) );
    update_option( 'woocommerce_shop_page_id', $shop_page_id );
}
// « Voir toutes les pièces » doit être un vrai catalogue transversal, et
// non un simple retour à la page qui liste les familles de produits.
$all_url = deprat_catalog_all_products_url();
deprat_integration_assert( false !== strpos( $all_url, 'deprat_all=1' ),
    'Le bouton Voir toutes les pièces doit ouvrir la vue globale.' );
$all_index = deprat_catalog_all_products_index();
deprat_integration_assert( isset( $all_index[ $product_id ], $all_index[ $piece_id ] ),
    'Toutes les pièces doit inclure les tubes ET les embouts sans limiter la catégorie.' );
$all_filter = deprat_catalog_filters();
$all_query = deprat_catalog_query( null, $all_index, $all_filter );
deprat_integration_assert( $all_query->found_posts >= 2,
    'La vue complète doit retourner plusieurs références de catégories différentes.' );
wp_reset_postdata();

$shop_item = (object) array(
    'type' => 'post_type', 'object' => 'page', 'object_id' => (int) $shop_page_id,
    'title' => 'Boutique', 'url' => get_permalink( $shop_page_id ),
    'classes' => array(), 'menu_item_parent' => 0,
);
$menu_items = deprat_catalog_relabel_menu( array( $shop_item ) );
deprat_integration_assert( 'Nos composants' === $menu_items[0]->title, 'Le menu Boutique doit devenir Nos composants.' );
ob_start();
$mega_html = deprat_catalog_mega_menu( '<a href="#">Nos composants</a>', $menu_items[0], 0,
    (object) array( 'theme_location' => 'primary' ) );
$extra_html = ob_get_clean();
// Ordre défini par l'admin : aucune priorité codée en dur (Tubes, Embouts...).
update_term_meta( (int) $tube_category['term_id'], 'order', 9 );
update_term_meta( (int) $end_category['term_id'], 'order', 1 );
$roots_ordered = deprat_catalog_root_categories();
$ordered_ids = array_map( static function( $term ) { return (int) $term->term_id; }, $roots_ordered );
deprat_integration_assert( array_search( (int) $end_category['term_id'], $ordered_ids, true )
    < array_search( (int) $tube_category['term_id'], $ordered_ids, true ),
    'Le méga-menu doit respecter l’ordre enregistré dans WooCommerce, et non placer Tubes en premier.' );

$nested_tree = array( array( 'id' => (int) $end_category['term_id'], 'children' => array(
    array( 'id' => (int) $tube_category['term_id'], 'children' => array() ),
) ) );
$validated_tree = deprat_category_tree_validate( $nested_tree, array(
    (int) $tube_category['term_id'], (int) $end_category['term_id'],
) );
deprat_integration_assert( ! is_wp_error( $validated_tree )
    && (int) $validated_tree['parents'][ $tube_category['term_id'] ] === (int) $end_category['term_id'],
    'Une catégorie doit pouvoir devenir sous-catégorie à plusieurs niveaux.' );
$bad_tree = array( array( 'id' => (int) $tube_category['term_id'], 'children' => array(
    array( 'id' => (int) $tube_category['term_id'], 'children' => array() ),
) ) );
deprat_integration_assert( is_wp_error( deprat_category_tree_validate( $bad_tree, array(
    (int) $tube_category['term_id'], (int) $end_category['term_id'],
) ) ), 'Une arborescence contenant un doublon ou un cycle doit être rejetée.' );

// L'administration reproduit le vrai méga-menu : mêmes sections et icônes,
 // aucune catégorie n'est créée par l'aperçu ; les sous-catégories restent réelles.
$terms_before_preview = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
ob_start();
deprat_category_tree_admin_visual_menu();
$visual_menu_html = ob_get_clean();
deprat_integration_assert(
    false !== strpos( $visual_menu_html, 'deprat-catalog-sections--admin' )
    && false !== strpos( $visual_menu_html, 'data-preview-section="tubes"' )
    && false !== strpos( $visual_menu_html, 'data-preview-section="garage"' )
    && false !== strpos( $visual_menu_html, 'deprat-section-icon' )
    && false !== strpos( $visual_menu_html, '<h2>Tubes acier &amp; accessoires</h2>' )
    && false === strpos( $visual_menu_html, '<a class="deprat-section-heading"' ),
    'La grille d’administration doit reprendre le design et les titres NON cliquables du méga-menu.'
);
deprat_integration_assert( $terms_before_preview === wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ),
    'La prévisualisation ne doit créer aucun terme WooCommerce.' );
deprat_integration_assert(
    substr_count( $visual_menu_html, 'data-preview-create-menu' ) === count( deprat_catalog_section_definitions() )
    && substr_count( $visual_menu_html, 'data-preview-add-category' ) === count( deprat_catalog_section_definitions() )
    && substr_count( $visual_menu_html, 'data-preview-add-subcategory' ) === count( deprat_catalog_section_definitions() ),
    'Chaque rubrique doit proposer la création ciblée de catégories et sous-catégories.'
);
ob_start();
deprat_category_tree_admin_page();
$category_admin_page_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $category_admin_page_html, 'data-tree-create-overlay' )
    && false !== strpos( $category_admin_page_html, 'aria-modal="true"' )
    && false !== strpos( $category_admin_page_html, 'data-tree-create-parent-select' )
    && false !== strpos( $category_admin_page_html, 'data-tree-create-section' ),
    'La popup admin doit pouvoir choisir la catégorie parente et la rubrique.'
);
deprat_integration_assert( $terms_before_preview === wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ),
    'Afficher les boutons + et la popup ne doit créer aucune catégorie sans action utilisateur.' );

ob_start();
deprat_category_tree_render_list( 0, deprat_category_tree_terms() );
$tree_editor_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $tree_editor_html, 'data-drag-handle' )
    && false !== strpos( $tree_editor_html, 'data-tree-add-child' )
    && false !== strpos( $tree_editor_html, 'data-term-id="' . (int) $tube_category['term_id'] . '"' ),
    'Le gestionnaire admin doit afficher les familles existantes et les poignées de déplacement.' );

// Les titres de section sont purement visuels. Aucun terme n'est créé par le rendu.
$terms_before = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
$groups = deprat_catalog_grouped_roots();
deprat_integration_assert( count( $groups ) === 1 && isset( $groups['tubes'] )
    && count( $groups['tubes'] ) === 2, 'Tubes et Embouts doivent partager une section, sans section vide.' );
deprat_integration_assert( deprat_catalog_section_for_term( $tubes_term ) === 'tubes',
    'Les catégories existantes sont regroupées sans en créer de nouvelles.' );
update_term_meta( (int) $end_category['term_id'], '_deprat_catalog_section', 'liaison' );
deprat_integration_assert( deprat_catalog_section_for_term( $embouts_term ) === 'liaison'
    && isset( deprat_catalog_grouped_roots()['liaison'] ),
    'Le choix d’une rubrique visuelle doit prévaloir sur le classement automatique.' );
delete_term_meta( (int) $end_category['term_id'], '_deprat_catalog_section' );
deprat_integration_assert( wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) === $terms_before,
    'Ni les rubriques ni leurs icônes ne doivent créer de catégories WooCommerce.' );
deprat_integration_assert( false !== strpos( $mega_html, 'deprat-mega-panel' )
    && false !== strpos( $mega_html, 'Tubes' ) && false !== strpos( $mega_html, 'Embouts' ),
    'Le méga-menu doit reprendre les catégories WooCommerce sans liens inventés.' );
deprat_integration_assert( false !== strpos( $mega_html, '<h2>Tubes acier &amp; accessoires</h2>' )
    && false === strpos( $mega_html, '<a class="deprat-section-heading"' )
    && false === strpos( $mega_html, 'Portes de garage</h2>' )
    && false !== strpos( $mega_html, 'deprat-section-icon' ),
    'Le titre non cliquable a une icône et les sections sans catégories sont absentes.' );
deprat_integration_assert( false !== strpos( $tree_editor_html, 'data-tree-section' ),
    'L’administration permet de choisir le titre visuel sans créer de catégorie.' );
deprat_integration_assert(
    false !== strpos( $mega_html, 'deprat-mega-intro' )
    && false !== strpos( $mega_html, 'Voir tous les composants' )
    && false !== strpos( $mega_html, 'deprat_all=1' )
    && false === strpos( $mega_html, 'Catalogue professionnel' )
    && false === strpos( $mega_html, 'Voir les produits' ),
    'Le bandeau doit être condensé et aucun lien de catégorie ne doit être dupliqué.'
);


$_GET = array();
$requests_page = get_page_by_path( 'mes-demandes-devis' );
if ( ! $requests_page ) {
    $request_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => 'Mes demandes de devis', 'post_name' => 'mes-demandes-devis',
        'post_content' => '[deprat_mes_demandes]' ) );
} else { $request_page_id = $requests_page->ID; }
deprat_integration_assert( $request_page_id > 0, 'La page privée Mes demandes doit exister pour le test.' );
$menu_request = (object) array(
    'type' => 'post_type', 'object' => 'page', 'object_id' => $request_page_id,
    'title' => 'Mes demandes de devis', 'url' => get_permalink( $request_page_id ),
    'classes' => array(),
);
$fallback_menu = wp_page_menu( array( 'echo' => false, 'show_home' => false ) );
deprat_integration_assert( false !== strpos( $fallback_menu, 'Nos composants' )
    && false !== strpos( $fallback_menu, 'deprat-mega-panel' ),
    'Le menu Astra automatique doit recevoir le nom Nos composants et le méga-menu.' );

$current_admin = get_current_user_id();
wp_set_current_user( 0 );
$guest_fallback_menu = wp_page_menu( array( 'echo' => false, 'show_home' => false ) );
deprat_integration_assert( false === strpos( $guest_fallback_menu, 'Mes demandes de devis' ),
    'La page Mes demandes doit être masquée du menu automatique pour les visiteurs.' );
deprat_integration_assert( ! count( deprat_catalog_private_menu_items( array( $menu_request ) ) ),
    'Le lien Mes demandes doit être absent du menu public.' );
wp_set_current_user( $current_admin );

// Vérifier qu'un paramètre GET ne peut pas injecter de balises exécutables.
$original_get = $_GET;
$_GET['deprat_q'] = '"><img src=x onerror=alert(1)>';
$_GET['deprat_statut'] = '"><svg onload=alert(1)>';
$_GET['deprat_periode'] = '<script>alert(1)</script>';
$history_html = deprat_html_historique();
deprat_integration_assert( is_string( $history_html ) && false !== strpos( $history_html, 'deprat-recherche-form' ),
    'Le formulaire d’historique reste fonctionnel après sanitation des requêtes.' );
deprat_integration_assert( false === strpos( $history_html, '<img src=x' )
    && false === strpos( $history_html, '<svg onload=' )
    && false === strpos( $history_html, '<script>alert(1)</script>' ),
    'Les paramètres GET ne doivent jamais injecter de HTML exécutable dans l’historique.' );
$_GET = $original_get;

// Test d'autorisation : connaître le numéro d'une demande ne suffit pas.
$security_customer = wp_create_user( 'deprat-security-client', wp_generate_password( 20 ), 'security-client@example.test' );
deprat_integration_assert( ! is_wp_error( $security_customer ), 'Le client de sécurité doit pouvoir être créé.' );
$security_order = wc_create_order( array( 'customer_id' => $current_admin ) );
deprat_integration_assert( $security_order instanceof WC_Order, 'Commande de test sécurité disponible.' );
$security_order->update_meta_data( '_deprat_demande', 'oui' );
$security_order->save();
wp_set_current_user( $security_customer );
deprat_integration_assert( ! deprat_devis_est_au_client( $security_order ),
    'Un client non propriétaire ne doit pas accéder à un devis privé.' );
wp_set_current_user( $current_admin );
deprat_integration_assert( deprat_devis_est_au_client( $security_order ),
    'Le propriétaire connecté doit accéder à sa demande.' );
$security_order->delete( true );

// Un produit sans tarif doit conserver le bouton de demande dans une carte archive.
ob_start();
deprat_catalog_render_card( wc_get_product( $piece_id ) );
$card_html = ob_get_clean();
ob_start();
deprat_catalog_render_card( wc_get_product( $product_id ) );
$tube_card_html = ob_get_clean();
deprat_integration_assert( false !== strpos( $tube_card_html, 'deprat-catalog-shape-badge' )
    && false !== strpos( $tube_card_html, 'deprat-catalog-spec-list' ),
    'La forme et les caractéristiques doivent figurer sur la carte produit.' );
deprat_integration_assert( false !== strpos( $card_html, 'data-catalog-add' )
    && false === strpos( $card_html, 'data-open-model' )
    && false !== strpos( $card_html, 'deprat-catalog-ref' ),
    'La carte Embouts doit offrir l’ajout rapide, la référence et ne pas inventer une vue 3D.' );

WP_CLI::success( 'WordPress et WooCommerce : sauvegarde, médias et affichages vérifiés.' );
