<?php
/**
 * Visualisation SolidWorks GLB pour les fiches produit DEPRAT.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_product_3d_attachment( $value ) {
    $id = is_scalar( $value ) ? absint( $value ) : 0;
    return $id && 'attachment' === get_post_type( $id )
        && 'model/gltf-binary' === get_post_mime_type( $id ) ? $id : 0;
}

function deprat_product_3d_model( $product_id ) {
    return deprat_product_3d_attachment( get_post_meta( $product_id, '_deprat_product_glb', true ) );
}

/** Vérification du conteneur binaire glTF 2 avant toute mise en ligne. */
function deprat_product_3d_valid_binary( $path, $size ) {
    if ( ! is_string( $path ) || ! is_readable( $path ) || ! is_numeric( $size ) ||
        $size < 20 || $size > 50 * 1024 * 1024 ) { return false; }
    $stream = fopen( $path, 'rb' );
    if ( ! $stream ) { return false; }
    $header = fread( $stream, 20 );
    fclose( $stream );
    if ( strlen( $header ) !== 20 || substr( $header, 0, 4 ) !== 'glTF' ) { return false; }
    $values = unpack( 'Vversion/Vsize/VchunkSize', substr( $header, 4, 12 ) );
    return 2 === $values['version'] && (int) $size === $values['size']
        && $values['chunkSize'] >= 2 && $values['chunkSize'] <= $size - 20
        && 'JSON' === substr( $header, 16, 4 );
}

function deprat_product_3d_ajax_upload() {
    $product_id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $product_id || 'product' !== get_post_type( $product_id )
        || ! current_user_can( 'edit_post', $product_id ) || ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => 'Envoi non autorisé.' ), 403 );
    }
    if ( ! check_ajax_referer( 'deprat_product_media_upload_' . $product_id, 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Session expirée. Rechargez la fiche.' ), 403 );
    }
    if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
        wp_send_json_error( array( 'message' => 'Aucun modèle sélectionné.' ), 400 );
    }
    $file = $_FILES['file'];
    $name = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
    $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
    $tmp = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
    if ( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) !== 'glb'
        || ! is_uploaded_file( $tmp ) || ! deprat_product_3d_valid_binary( $tmp, $size ) ) {
        wp_send_json_error( array( 'message' => 'Choisissez un fichier GLB 2.0 valide de 50 Mo maximum.' ), 400 );
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $file, array( 'test_form' => false, 'test_type' => false ) );
    if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['url'] ) ) {
        wp_send_json_error( array( 'message' => ! empty( $upload['error'] ) ? $upload['error'] : 'Import impossible.' ), 400 );
    }
    $attachment = array(
        'post_mime_type' => 'model/gltf-binary',
        'post_title' => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ),
        'post_content' => '',
        'post_status' => 'inherit',
    );
    $id = wp_insert_attachment( $attachment, $upload['file'], $product_id, true );
    if ( is_wp_error( $id ) ) {
        wp_delete_file( $upload['file'] );
        wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 );
    }
    wp_send_json_success( array(
        'id' => $id, 'url' => wp_get_attachment_url( $id ),
        'filename' => basename( $upload['file'] ),
    ) );
}
add_action( 'wp_ajax_deprat_product_glb_upload', 'deprat_product_3d_ajax_upload' );

function deprat_product_3d_save( $product_id ) {
    if ( ! isset( $_POST['deprat_product_tech_nonce'] ) || ! is_scalar( $_POST['deprat_product_tech_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['deprat_product_tech_nonce'] ) ), 'deprat_product_tech_save' )
        || ! current_user_can( 'edit_post', $product_id ) || wp_is_post_revision( $product_id ) || wp_is_post_autosave( $product_id )
        || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        || ! isset( $_POST['deprat_product_glb'] ) || ! is_scalar( $_POST['deprat_product_glb'] ) ) { return; }
    $value = sanitize_text_field( wp_unslash( $_POST['deprat_product_glb'] ) );
    $id = '' === $value ? 0 : deprat_product_3d_attachment( $value );
    if ( $id ) { update_post_meta( $product_id, '_deprat_product_glb', $id ); }
    elseif ( '' === $value ) { delete_post_meta( $product_id, '_deprat_product_glb' ); }
    // Un identifiant non reconnu n'efface jamais le modèle précédent.
}
add_action( 'save_post_product', 'deprat_product_3d_save', 25 );

function deprat_product_3d_render_admin( $product_id ) {
    $id = deprat_product_3d_model( $product_id );
    $url = $id ? wp_get_attachment_url( $id ) : '';
    ?>
    <section class="deprat-tech-card deprat-three-admin" aria-label="Visualisation 3D">
        <h3><span class="dashicons dashicons-art" aria-hidden="true"></span> Visualisation 3D</h3>
        <label class="deprat-tech-field"><span>Fichier 3D SolidWorks (.GLB)</span></label>
        <p class="deprat-tech-help">Exportez votre pièce depuis SolidWorks au format GLB (glTF 2.0). La vue 3D s'affichera à côté des photos sur la fiche produit.</p>
        <input type="hidden" name="deprat_product_glb" data-glb-id value="<?php echo esc_attr( $id ); ?>">
        <div class="deprat-three-selected" data-glb-status>
            <?php if ( $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $id ) ); ?>.glb</a>
            <?php else : ?>Aucun fichier 3D sélectionné<?php endif; ?>
        </div>
        <div class="deprat-three-actions">
            <button type="button" class="button deprat-tech-quick-upload" data-glb-upload>Choisir / importer un fichier GLB</button>
            <button type="button" class="button" data-glb-remove <?php echo $id ? '' : 'disabled'; ?>>Retirer</button>
        </div>
        <p class="deprat-tech-help">Sans fichier renseigné, le bouton « Vue 3D » n'apparaîtra pas sur la fiche client. Taille maximale : 50 Mo.</p>
    </section>
    <?php
}

/** La galerie photo reste le premier affichage ; la 3D est une option facultative. */
function deprat_product_3d_gallery_toggle() {
    global $product;
    if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $id = deprat_product_3d_model( $product->get_id() );
    if ( ! $id ) { return; }
    $url = wp_get_attachment_url( $id );
    if ( ! $url ) { return; }
    $label = $product->get_name();
    ?>
    <div class="deprat-three-widget" data-deprat-3d>
        <div class="deprat-three-tabs" role="group" aria-label="Affichage du produit">
            <button type="button" data-three-mode="photos" class="is-selected" aria-pressed="true">Photos</button>
            <button type="button" data-three-mode="model" aria-pressed="false" aria-controls="deprat-3d-viewer-<?php echo esc_attr( $product->get_id() ); ?>">Vue 3D</button>
        </div>
        <div class="deprat-three-viewport" id="deprat-3d-viewer-<?php echo esc_attr( $product->get_id() ); ?>" hidden>
            <model-viewer src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( 'Modèle 3D du produit ' . $label ); ?>"
                camera-controls auto-rotate rotation-per-second="12deg" shadow-intensity="0.7"
                interaction-prompt="auto" loading="lazy" reveal="auto" touch-action="pan-y">
                <div slot="poster">Chargement du modèle 3D…</div>
            </model-viewer>
            <div class="deprat-three-outline" data-three-outline hidden aria-label="Vue en contours de la pièce 3D"></div>
            <div class="deprat-three-view-options" role="group" aria-label="Mode de rendu 3D">
                <button type="button" data-render-mode="texture" class="is-selected" aria-pressed="true">Pièce texturée</button>
                <button type="button" data-render-mode="contours" aria-pressed="false">Contours</button>
                <button type="button" data-background-cycle aria-label="Changer le fond 3D" title="Adapter le fond à la couleur de la pièce">Fond : automatique</button>
            </div>
            <p>Faites glisser pour tourner la pièce et utilisez la molette pour zoomer.</p>
        </div>
    </div>
    <?php
}
add_action( 'woocommerce_product_thumbnails', 'deprat_product_3d_gallery_toggle', 30 );

function deprat_product_3d_front_assets() {
    if ( ! function_exists( 'is_product' ) || ! is_product() || ! deprat_product_3d_model( get_queried_object_id() ) ) { return; }
    // Version figée pour une présentation stable du modèle GLB.
    wp_enqueue_script( 'deprat-model-viewer', 'https://ajax.googleapis.com/ajax/libs/model-viewer/4.4.0/model-viewer.min.js', array(), '4.4.0', true );
    wp_enqueue_script( 'deprat-model-switch', plugins_url( 'assets/js/product-3d-front.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.22', true );
    wp_localize_script( 'deprat-model-switch', 'deprat3DConfig', array(
        'contoursUrl' => plugins_url( 'assets/js/product-3d-contours.js', dirname( __DIR__ ) . '/deprat-core.php' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'deprat_product_3d_front_assets' );
/**
 * Imports Three.js uniquement quand le client choisit la vue en contours.
 * Un importmap assure que le chargeur GLB et les contrôles utilisent la même version.
 */
function deprat_product_3d_importmap() {
    if ( ( ! function_exists( 'is_product' ) || ! is_product() || ! deprat_product_3d_model( get_queried_object_id() ) )
        && ( ! function_exists( 'deprat_catalog_is_archive' ) || ! deprat_catalog_is_archive() ) ) { return; }
    echo '<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.175.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.175.0/examples/jsm/"}}</script>' . "\n";
}
add_action( 'wp_head', 'deprat_product_3d_importmap', 2 );

function deprat_product_3d_script_module( $tag, $handle ) {
    if ( 'deprat-model-viewer' !== $handle ) { return $tag; }
    return str_replace( '<script ', '<script type="module" ', $tag );
}
add_filter( 'script_loader_tag', 'deprat_product_3d_script_module', 10, 2 );
