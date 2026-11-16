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
$product->set_regular_price( '0' );
$product->set_status( 'publish' );
$product_id = $product->save();
deprat_integration_assert( (bool) $product_id, 'Création du produit de test impossible.' );
$tube_category = wp_insert_term( 'Tubes', 'product_cat' );
$end_category = wp_insert_term( 'Embouts', 'product_cat' );
deprat_integration_assert( ! is_wp_error( $tube_category ) && ! is_wp_error( $end_category ), 'Création des catégories de test impossible.' );
wp_set_object_terms( $product_id, array( (int) $tube_category['term_id'], (int) $end_category['term_id'] ), 'product_cat' );

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
$glb_json = '{"asset":{"version":"2.0"},"scene":0,"scenes":[{"nodes":[]}]}';
$glb_json .= str_repeat( ' ', ( 4 - strlen( $glb_json ) % 4 ) % 4 );
$glb_binary = 'glTF' . pack( 'V', 2 ) . pack( 'V', 20 + strlen( $glb_json ) ) . pack( 'V', strlen( $glb_json ) ) . 'JSON' . $glb_json;
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
        'features' => array(
            array( 'label' => 'Matière', 'value' => 'Acier galvanisé' ),
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
deprat_integration_assert( 'Description technique \\ spéciale' === $saved['description'], 'Un caractère spécial de la description a été perdu.' );
deprat_integration_assert( (int) $pdf === $saved['datasheet'] && (int) $image === $saved['technical_image'], 'Les médias ne s’enregistrent pas.' );
deprat_integration_assert( 'TEST-DP62' === wc_get_product( $product_id )->get_sku(), 'La référence WooCommerce a été modifiée.' );
deprat_integration_assert( 2 === count( deprat_product_tech_datasheets( $saved ) ), 'Les deux PDF sont enregistrés.' );
deprat_integration_assert( '' !== get_post_meta( $image, '_wp_attachment_image_alt', true ), 'Le schéma doit recevoir un ALT automatique.' );
deprat_integration_assert( false !== strpos( deprat_product_tech_seo_description( $product_id ), 'TEST-DP62' ), 'La méta-description doit intégrer la référence.' );
deprat_integration_assert( (int) $gallery_image === (int) wc_get_product( $product_id )->get_gallery_image_ids()[0], 'La galerie WooCommerce doit être conservée.' );
deprat_integration_assert( false !== has_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front' ), 'L’affichage boutique n’est pas raccordé à WooCommerce.' );

ob_start();
deprat_product_tech_render_admin( get_post( $product_id ) );
$admin_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé', 'Normes', 'Valeurs testées', 'Type de conditionnement', 'Fiches techniques PDF', 'Visualisation 3D', 'Fichier 3D SolidWorks (.GLB)', 'Schéma dimensionnel personnalisé' ) as $expected ) {
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
$unchanged = deprat_product_tech_data( $product_id );
deprat_integration_assert( 'Longueurs de 6 m' === $unchanged['delivery'], 'Une sauvegarde sans nonce valide a été acceptée.' );
deprat_integration_assert( (int) $model3d === deprat_product_3d_model( $product_id ), 'Un nonce invalide ne doit pas supprimer la vue 3D.' );
WP_CLI::success( 'WordPress et WooCommerce : sauvegarde, médias et affichages vérifiés.' );
