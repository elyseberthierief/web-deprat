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
update_post_meta( $image, '_wp_attached_file', 'integration-test/schema.png' );
update_post_meta( $pdf, '_wp_attached_file', 'integration-test/fiche.pdf' );
deprat_integration_assert( ! is_wp_error( $image ) && ! is_wp_error( $pdf ), 'Création des médias de test impossible.' );

$_POST = array(
    'deprat_product_tech_nonce' => wp_create_nonce( 'deprat_product_tech_save' ),
    'deprat_product_tech' => array(
        'description' => 'Description technique de test',
        'features' => array(
            array( 'label' => 'Matière', 'value' => 'Acier galvanisé' ),
            array( 'label' => 'Zéro', 'value' => '0' ),
        ),
        'norms' => array( 'NF EN 12194' ),
        'tests' => array( array( 'label' => 'Couple de destruction', 'value' => '5 m.kg' ) ),
        'delivery' => 'Longueurs de 6 m',
        'packaging' => 'Lot',
        'package_quantity' => '96',
        'technical_image' => (string) $image,
        'dimensions_image' => (string) $image,
        'datasheet' => (string) $pdf,
    ),
);
do_action( 'save_post_product', $product_id, get_post( $product_id ), true );
$saved = deprat_product_tech_data( $product_id );
deprat_integration_assert( isset( $saved['features'][0]['value'] ) && 'Acier galvanisé' === $saved['features'][0]['value'], 'La caractéristique ne s’enregistre pas.' );
deprat_integration_assert( (int) $pdf === $saved['datasheet'] && (int) $image === $saved['technical_image'], 'Les médias ne s’enregistrent pas.' );
deprat_integration_assert( 'TEST-DP62' === wc_get_product( $product_id )->get_sku(), 'La référence WooCommerce a été modifiée.' );
deprat_integration_assert( false !== has_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front' ), 'L’affichage boutique n’est pas raccordé à WooCommerce.' );

ob_start();
deprat_product_tech_render_admin( get_post( $product_id ) );
$admin_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé', 'Normes', 'Valeurs testées', 'Conditionnement', 'Fiche technique PDF', 'Schéma des dimensions' ) as $expected ) {
    deprat_integration_assert( false !== strpos( $admin_html, $expected ), 'Champ absent de l’administration : ' . $expected );
}

$GLOBALS['product'] = wc_get_product( $product_id );
ob_start();
deprat_product_tech_render_front();
$front_html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé', '>0<', 'NF EN 12194', 'Couple de destruction', 'Longueurs de 6 m', 'Pièces par conditionnement', '>96<', 'Schéma technique', 'Schéma des dimensions', 'Télécharger la fiche technique PDF' ) as $expected ) {
    deprat_integration_assert( false !== strpos( $front_html, $expected ), 'Information absente de la boutique : ' . $expected );
}

$_POST['deprat_product_tech_nonce'] = 'invalide';
$_POST['deprat_product_tech']['delivery'] = 'Ne doit pas être enregistré';
do_action( 'save_post_product', $product_id, get_post( $product_id ), true );
$unchanged = deprat_product_tech_data( $product_id );
deprat_integration_assert( 'Longueurs de 6 m' === $unchanged['delivery'], 'Une sauvegarde sans nonce valide a été acceptée.' );
WP_CLI::success( 'WordPress et WooCommerce : sauvegarde, médias et affichages vérifiés.' );
