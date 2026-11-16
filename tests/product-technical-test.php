<?php
// Tests isolés de sauvegarde et de rendu ; aucun accès à une base WordPress.
define( 'ABSPATH', __DIR__ );
$GLOBALS['test_meta'] = array();
$GLOBALS['test_permissions'] = true;
$GLOBALS['test_autosave'] = false;
$GLOBALS['test_revision'] = false;
function add_action() {}
function add_meta_box() {}
function get_post_meta( $id, $key, $single = true ) { return isset( $GLOBALS['test_meta'][ $id ][ $key ] ) ? $GLOBALS['test_meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['test_meta'][ $id ][ $key ] = $value; }
function absint( $v ) { return abs( (int) $v ); }
function get_post_type( $id ) { return isset( $GLOBALS['test_attachments'][ $id ] ) ? 'attachment' : false; }
function get_post_mime_type( $id ) { return isset( $GLOBALS['test_attachments'][ $id ] ) ? $GLOBALS['test_attachments'][ $id ] : ''; }
function wp_attachment_is_image( $id ) { return 0 === strpos( get_post_mime_type( $id ), 'image/' ); }
function wp_unslash( $value ) { return $value; }
function wp_slash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'deprat_product_tech_save' === $action; }
function current_user_can() { return $GLOBALS['test_permissions']; }
function wp_is_post_revision() { return $GLOBALS['test_revision']; }
function wp_is_post_autosave() { return $GLOBALS['test_autosave']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function wp_get_attachment_url( $id ) { return get_post_type( $id ) ? 'https://example.test/media/' . $id : ''; }
function wp_get_attachment_image( $id ) { return '<img src="https://example.test/media/' . (int) $id . '" alt="schéma">'; }
function wp_nonce_field() {}
function get_the_title( $id ) { return 'Fichier ' . (int) $id; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function expect( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "ÉCHEC : " . $message . "\n" ); exit( 1 ); } }

require __DIR__ . '/../deprat-core/includes/product-technical-data.php';

$GLOBALS['test_attachments'] = array( 11 => 'image/png', 12 => 'application/pdf', 13 => 'image/jpeg', 14 => 'text/html' );
$GLOBALS['test_meta'][ 42 ] = array( '_sku' => 'F2000/G00' );
$_POST = array(
    'deprat_product_tech_nonce' => 'valid',
    'deprat_product_tech' => array(
        'description' => 'Tube de 62',
        'features' => array(
            array( 'label' => 'Matière', 'value' => 'Acier galvanisé Z275' ),
            array( 'label' => 'Test zéro', 'value' => '0' ),
            array( 'label' => '<b>Épaisseur</b>', 'value' => '6/10' ),
            array( 'label' => 'Champ vide', 'value' => '' ),
        ),
        'norms' => array( 'NF EN 12194', '', 'NF EN 13659' ),
        'tests' => array( array( 'label' => 'Couple de destruction', 'value' => '5 m.kg' ) ),
        'delivery' => 'Longueurs de 6 m',
        'packaging' => 'Lot',
        'package_count' => '2',
        'package_quantity' => '96',
        'technical_image' => '11',
        'dimensions_image' => '13',
        'datasheet' => '12',
        'diagrams_present' => '1',
        'diagrams' => array( array( 'title' => 'Schéma dimensionnel personnalisé', 'image' => '13' ), array( 'title' => 'Schéma technique', 'image' => '11' ) ),
    ),
);
deprat_product_tech_save( 42 );
$saved = deprat_product_tech_data( 42 );
expect( 'F2000/G00' === $GLOBALS['test_meta'][ 42 ]['_sku'], 'La référence existante doit rester inchangée.' );
expect( 'Épaisseur' === $saved['features'][2]['label'], 'Les champs sont nettoyés avant sauvegarde.' );
expect( 2 === count( $saved['norms'] ), 'Les normes vides sont ignorées.' );
expect( 96 === $saved['package_quantity'], 'Le nombre de pièces est enregistré.' );
expect( 2 === $saved['package_count'], 'Le nombre de conditionnements est enregistré.' );
expect( false !== strpos( deprat_product_tech_package_summary( $saved ), '192 pièces' ), 'Le total de conditionnement est calculé.' );
expect( 'Schéma dimensionnel personnalisé' === deprat_product_tech_diagrams( $saved )[0]['title'], 'Les schémas personnalisés gardent leur titre.' );
expect( 11 === $saved['technical_image'] && 13 === $saved['dimensions_image'] && 12 === $saved['datasheet'], 'Les images et le PDF sont enregistrés.' );

class Deprat_Test_Product { public function get_id() { return 42; } }
$product = new Deprat_Test_Product();
ob_start();
deprat_product_tech_render_front();
$html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé Z275', 'Test zéro', '>0<', 'NF EN 12194', 'Couple de destruction', '5 m.kg', 'Longueurs de 6 m', 'Pièces par conditionnement', '>96<', 'Schéma technique', 'Schéma dimensionnel personnalisé', 'Télécharger la fiche technique PDF', 'https://example.test/media/12' ) as $fragment ) {
    expect( false !== strpos( $html, $fragment ), 'Affichage boutique manquant : ' . $fragment );
}
expect( false === strpos( $html, 'Champ vide' ), 'Les champs sans valeur ne sont pas affichés.' );

// Les rubriques sans données ne sont pas montrées ; les produits n’ont pas de faux exemples.
class Deprat_Empty_Product { public function get_id() { return 43; } }
$product = new Deprat_Empty_Product();
ob_start(); deprat_product_tech_render_front(); $empty = ob_get_clean();
expect( '' === $empty, 'Aucune rubrique technique ne doit apparaître sur un produit vide.' );

// Un nonce invalide, un compte non autorisé ou une sauvegarde automatique ne modifie pas les données.
$original = $GLOBALS['test_meta'][42]['_deprat_product_technical'];
$_POST['deprat_product_tech']['delivery'] = 'Modification non autorisée';
$_POST['deprat_product_tech_nonce'] = 'invalid';
deprat_product_tech_save( 42 );
expect( $original === $GLOBALS['test_meta'][42]['_deprat_product_technical'], 'Nonce invalide refusé.' );
$_POST['deprat_product_tech_nonce'] = 'valid';
$GLOBALS['test_permissions'] = false;
deprat_product_tech_save( 42 );
expect( $original === $GLOBALS['test_meta'][42]['_deprat_product_technical'], 'Droits insuffisants refusés.' );
$GLOBALS['test_permissions'] = true;
$GLOBALS['test_autosave'] = true;
deprat_product_tech_save( 42 );
expect( $original === $GLOBALS['test_meta'][42]['_deprat_product_technical'], 'Sauvegarde automatique ignorée.' );
$GLOBALS['test_autosave'] = false;

// Un PDF ne peut pas être utilisé comme schéma, ni une page HTML comme fiche PDF.
$_POST['deprat_product_tech']['technical_image'] = '12';
$_POST['deprat_product_tech']['datasheet'] = '14';
$_POST['deprat_product_tech']['package_quantity'] = '-1';
deprat_product_tech_save( 42 );
$saved = deprat_product_tech_data( 42 );
expect( 0 === $saved['technical_image'] && 0 === $saved['datasheet'], 'Types de médias non autorisés refusés.' );
expect( '' === $saved['package_quantity'], 'Quantité de conditionnement invalide ignorée.' );
echo "Tests de sauvegarde, droits, médias et rendu boutique réussis.\n";
