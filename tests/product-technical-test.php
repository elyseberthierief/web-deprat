<?php
// Tests isolés de sauvegarde et de rendu ; aucun accès à une base WordPress.
define( 'ABSPATH', __DIR__ );
$GLOBALS['test_meta'] = array();
$GLOBALS['test_permissions'] = true;
$GLOBALS['test_autosave'] = false;
$GLOBALS['test_revision'] = false;
function add_action() {}
function add_filter() {}
function get_the_terms( $id, $taxonomy ) { return isset( $GLOBALS['test_categories'][ $id ] ) ? $GLOBALS['test_categories'][ $id ] : false; }
function get_ancestors( $id, $taxonomy ) { return 44 === $id ? array( 5 ) : array(); }
function get_term( $id, $taxonomy ) { return isset( $GLOBALS['test_term_index'][ $id ] ) ? $GLOBALS['test_term_index'][ $id ] : false; }
function is_wp_error( $value ) { return false; }
function wc_add_notice( $message, $type ) { $GLOBALS['test_notices'][] = $message; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['test_meta'][$id][$key] ); }
function add_meta_box() {}
function get_post_meta( $id, $key, $single = true ) { return isset( $GLOBALS['test_meta'][ $id ][ $key ] ) ? $GLOBALS['test_meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['test_meta'][ $id ][ $key ] = $value; }
function absint( $v ) { return abs( (int) $v ); }
function get_post_type( $id ) { return isset( $GLOBALS['test_attachments'][ $id ] ) ? 'attachment' : false; }
function get_post_mime_type( $id ) { return isset( $GLOBALS['test_attachments'][ $id ] ) ? $GLOBALS['test_attachments'][ $id ] : ''; }
function wp_attachment_is_image( $id ) { return 0 === strpos( get_post_mime_type( $id ), 'image/' ); }
function wp_unslash( $value ) { return $value; }
function wp_slash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ); }
function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $value ) ), '-' ) ); }
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
require __DIR__ . '/../deprat-core/includes/product-shape.php';
require __DIR__ . '/../deprat-core/includes/product-packaging.php';
require __DIR__ . '/../deprat-core/includes/product-single-layout.php';
require __DIR__ . '/../deprat-core/includes/product-3d.php';

$GLOBALS['test_attachments'] = array( 11 => 'image/png', 12 => 'application/pdf', 13 => 'image/jpeg', 14 => 'text/html', 15 => 'application/pdf', 16 => 'model/gltf-binary' );
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
        'datasheets_present' => '1',
        'datasheets' => array(
            array( 'title' => 'Fiche produit DP62', 'file' => '12' ),
            array( 'title' => 'Notice de pose DP62', 'file' => '15' ),
        ),
        'diagrams_present' => '1',
        'diagrams' => array( array( 'title' => 'Schéma dimensionnel personnalisé', 'image' => '13' ), array( 'title' => 'Schéma technique', 'image' => '11' ) ),
    ),
    'deprat_product_glb' => '16',
);
deprat_product_tech_save( 42 );
deprat_product_3d_save( 42 );
$saved = deprat_product_tech_data( 42 );
expect( 'F2000/G00' === $GLOBALS['test_meta'][ 42 ]['_sku'], 'La référence existante doit rester inchangée.' );
expect( 16 === deprat_product_3d_model( 42 ), 'Le modèle 3D associé est conservé.' );
expect( 0 === deprat_product_3d_attachment( 12 ), 'Un PDF ne peut pas être un modèle GLB.' );
expect( 'Épaisseur' === $saved['features'][2]['label'], 'Les champs sont nettoyés avant sauvegarde.' );
expect( 2 === count( $saved['norms'] ), 'Les normes vides sont ignorées.' );
expect( 96 === $saved['package_quantity'], 'Le nombre de pièces est enregistré.' );
expect( 2 === $saved['package_count'], 'Le nombre de conditionnements est enregistré.' );
expect( false !== strpos( deprat_product_tech_package_summary( $saved ), '192 pièces' ), 'Le total de conditionnement est calculé.' );
expect( 'Schéma dimensionnel personnalisé' === deprat_product_tech_diagrams( $saved )[0]['title'], 'Les schémas personnalisés gardent leur titre.' );
expect( 11 === $saved['technical_image'] && 13 === $saved['dimensions_image'] && 12 === $saved['datasheet'], 'Les images et le PDF sont enregistrés.' );
expect( 2 === count( deprat_product_tech_datasheets( $saved ) ), 'Deux PDF sont conservés.' );
expect( false !== strpos( deprat_product_tech_icon( 'norms' ), '<svg' ), 'Les icônes SVG sont générées.' );
expect( 'Notice de pose DP62' === deprat_product_tech_datasheets( $saved )[1]['title'], 'Les titres des PDF sont conservés.' );
expect( 12 === deprat_product_tech_datasheets( array( 'datasheet' => 12 ) )[0]['file'], 'L’ancien PDF unique reste visible.' );

class Deprat_Test_Product { public function get_id() { return 42; } }
$product = new Deprat_Test_Product();
ob_start();
deprat_product_tech_render_front();
$html = ob_get_clean();
foreach ( array( 'Caractéristiques techniques', 'Acier galvanisé Z275', 'Test zéro', '>0<', 'NF EN 12194', 'Couple de destruction', '5 m.kg', 'Longueurs de 6 m', '192 pièces', 'Schéma technique', 'Schéma dimensionnel personnalisé', 'Fiche produit DP62', 'Notice de pose DP62', 'Télécharger la fiche technique PDF', 'https://example.test/media/12', 'https://example.test/media/15' ) as $fragment ) {
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
$_POST['deprat_product_tech']['datasheets'] = array( array( 'title' => 'Mauvais PDF', 'file' => '14' ) );
$_POST['deprat_product_tech']['package_quantity'] = '-1';
deprat_product_tech_save( 42 );
$saved = deprat_product_tech_data( 42 );
expect( 0 === $saved['technical_image'] && 0 === $saved['datasheet'], 'Types de médias non autorisés refusés.' );
expect( 0 === count( deprat_product_tech_datasheets( $saved ) ), 'Les documents invalides sont refusés.' );
expect( '' === $saved['package_quantity'], 'Quantité de conditionnement invalide ignorée.' );

$valid_gltf_json = '{"asset":{"version":"2.0"}}';
$padding = ( 4 - strlen( $valid_gltf_json ) % 4 ) % 4;
$valid_gltf_json .= str_repeat( ' ', $padding );
$valid_glb = 'glTF' . pack( 'V', 2 ) . pack( 'V', 20 + strlen( $valid_gltf_json ) )
    . pack( 'V', strlen( $valid_gltf_json ) ) . 'JSON' . $valid_gltf_json;
$tmp_glb = tempnam( sys_get_temp_dir(), 'deprat-glb-' );
file_put_contents( $tmp_glb, $valid_glb );
expect( deprat_product_3d_valid_binary( $tmp_glb, strlen( $valid_glb ) ), 'Un GLB 2.0 valide est reconnu.' );
expect( ! deprat_product_3d_valid_binary( $tmp_glb, strlen( $valid_glb ) + 1 ), 'La taille annoncée du fichier GLB doit correspondre.' );
file_put_contents( $tmp_glb, "%PDF-1.4\n" );
expect( ! deprat_product_3d_valid_binary( $tmp_glb, filesize( $tmp_glb ) ), 'Un PDF renommé en GLB est refusé.' );
unlink( $tmp_glb );

$meter_package = deprat_packaging_clean_row( array(
    'mode' => 'length', 'title' => 'Grand lot',
    'total_m' => '504', 'length_m' => '6',
) );
expect( $meter_package && 84 === $meter_package['sections'], '504 m par 6 m donnent 84 longueurs.' );
expect( false !== strpos( deprat_packaging_total_text( $meter_package, 2 ), '168 longueurs' ), 'Deux conditionnements donnent 168 longueurs.' );
expect( false !== strpos( deprat_packaging_total_text( $meter_package, 2 ), '1 008 m' ), 'Deux conditionnements donnent 1 008 mètres.' );
expect( ! deprat_packaging_clean_row( array( 'mode' => 'length', 'total_m' => '500', 'length_m' => '6' ) ), 'Les longueurs fractionnaires sont interdites.' );
$piece_package = deprat_packaging_clean_row( array( 'mode' => 'pieces', 'count' => '2', 'type' => 'carton', 'pieces' => '50' ) );
expect( false !== strpos( deprat_packaging_total_text( $piece_package, 2 ), '200 pièces' ), 'Deux lots de deux cartons de 50 donnent 200 pièces.' );
$choice_data = array( 'packages' => array( $meter_package, $piece_package ) );
expect( 2 === count( deprat_product_tech_packages( $choice_data ) ), 'Les choix mètres et pièces sont sauvegardables.' );


$GLOBALS['test_term_index'] = array(
    5 => (object) array( 'term_id' => 5, 'slug' => 'tubes-acier', 'name' => 'Tubes acier' ),
    6 => (object) array( 'term_id' => 6, 'slug' => 'embouts', 'name' => 'Embouts' ),
    44 => (object) array( 'term_id' => 44, 'slug' => 'serie-2000', 'name' => 'Série 2000' ),
);
$GLOBALS['test_categories'] = array(
    42 => array( $GLOBALS['test_term_index'][5] ),
    43 => array( $GLOBALS['test_term_index'][6] ),
    44 => array( $GLOBALS['test_term_index'][44] ),
);
expect( deprat_packaging_is_tube_product( 42 ), 'Tubes acier doit imposer le conditionnement standard.' );
expect( deprat_packaging_is_tube_product( 44 ), 'Les sous-catégories de tubes suivent la règle du parent.' );
expect( null === deprat_packaging_request_row( 42, 'unitary' ), 'Interdire le conditionnement unitaire sur les tubes.' );
expect( ! deprat_packaging_allow_unitary( 42 ) && deprat_packaging_allow_unitary( 43 ), 'L’unitaire n’est proposé que pour les pièces.' );
expect( null === deprat_packaging_clean_row( array( 'mode' => 'unitary' ) ), 'Une offre unitaire ne peut pas être stockée comme standard en admin.' );
$_POST['deprat_package_choice'] = 'unitary';
expect( false === deprat_packaging_validate_cart_choice( true, 42, 7 ), 'Refus serveur d’une demande unitaire sur un tube.' );
expect( true === deprat_packaging_validate_cart_choice( true, 43, 7 ), 'Autoriser 7 pièces libres.' );
$custom = deprat_packaging_cart_item_data( array(), 43, 0, 7 );
expect( 'unitary' === $custom['deprat_package_index'], 'Le panier mémorise le mode unitaire.' );
expect( ! empty( $custom['deprat_nonstandard_packaging'] ), 'Le panier marque le conditionnement non standard.' );
$display = deprat_packaging_cart_item_display( array(), array_merge( $custom, array( 'quantity' => 7 ) ) );
expect( false !== strpos( implode( ' ', array_column( $display, 'value' ) ), '7 pièces' ), 'Le panier conserve la quantité choisie.' );
expect( false !== strpos( implode( ' ', array_column( $display, 'value' ) ), 'plus-value' ), 'Le panier affiche la plus-value.' );


expect( 'hexagonal' === deprat_product_shape_normalize( 'hexagone' ), 'Une variante hexagonale doit être reconnue.' );
expect( 'octogonal' === deprat_product_shape_normalize( 'octogonale' ), 'Les libellés de forme se normalisent.' );
expect( '' === deprat_product_shape_normalize( 'étoile' ), 'Ne pas créer d’icône pour une forme inconnue.' );
expect( false !== strpos( deprat_product_shape_icon( 'rond' ), '<circle' ), 'Le rond est dessiné en SVG.' );
expect( false !== strpos( deprat_product_shape_icon( 'hexagonal' ), '<polygon' ), 'L’hexagone est dessiné en SVG.' );

echo "Tests de sauvegarde, droits, médias et rendu boutique réussis.\n";
