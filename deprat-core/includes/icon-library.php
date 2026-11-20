<?php
/**
 * DEPRAT — bibliothèque partagée de pictogrammes du catalogue.
 * Les icônes historiques sont incluses automatiquement ; les personnaliser ne
 * modifie jamais les noms des catégories ni les affectations enregistrées.
 */
defined( 'ABSPATH' ) || exit;

function deprat_icon_library_entries() {
    $defaults = array(
        'tubes' => 'Tubes', 'embouts' => 'Embouts',
        'flasque_guidage_bague' => 'Flasques guidage et bagues',
        'supports_arbres' => 'Supports d’arbres',
        'liaison' => 'Liaison tube-tablier', 'treuil' => 'Treuil',
        'sangle' => 'Sangle', 'guide_sangle' => 'Guide-sangle', 'tirage' => 'Tirage direct',
        'coulisses' => 'Coulisses, lames et lames finales',
        'bras' => 'Bras de projection', 'jalousie' => 'Persiennes et jalousies',
        'garage' => 'Porte', 'autres' => 'Composants',
    );
    $saved = get_option( 'deprat_icon_library_v1', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }
    $result = array();
    foreach ( $defaults as $key => $label ) {
        $entry = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
        if ( ! empty( $entry['hidden'] ) ) { continue; }
        $name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : $label;
        $media = isset( $entry['media_id'] ) ? absint( $entry['media_id'] ) : 0;
        $result[ $key ] = array(
            'name' => $name ?: $label,
            'media_id' => deprat_catalog_section_media_allowed( $media ) ? $media : 0,
            'builtin' => true,
            'position_x' => isset( $entry['position_x'] ) ? min( 100, max( 0, (int) $entry['position_x'] ) ) : 50,
            'position_y' => isset( $entry['position_y'] ) ? min( 100, max( 0, (int) $entry['position_y'] ) ) : 50,
        );
    }
    foreach ( $saved as $key => $entry ) {
        if ( ! is_string( $key ) || ! preg_match( '/^icon_[a-f0-9]{12}$/D', $key )
            || ! is_array( $entry ) || ! empty( $entry['hidden'] ) ) { continue; }
        $name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
        $media = isset( $entry['media_id'] ) ? absint( $entry['media_id'] ) : 0;
        if ( ! $name || ! deprat_catalog_section_media_allowed( $media ) ) { continue; }
        $result[ $key ] = array( 'name' => $name, 'media_id' => $media, 'builtin' => false,
            'position_x' => isset( $entry['position_x'] ) ? min( 100, max( 0, (int) $entry['position_x'] ) ) : 50,
            'position_y' => isset( $entry['position_y'] ) ? min( 100, max( 0, (int) $entry['position_y'] ) ) : 50 );
        if ( count( $result ) >= 150 ) { break; }
    }
    return $result;
}
function deprat_icon_library_palette() {
    $labels = array();
    foreach ( deprat_icon_library_entries() as $key => $entry ) { $labels[ $key ] = $entry['name']; }
    return $labels;
}
/** Icône utilisée dans toutes les interfaces : bibliothèque personnalisée en priorité. */
function deprat_icon_library_markup( $key, $class = 'deprat-section-icon', $size = 27 ) {
    $entries = deprat_icon_library_entries();
    if ( ! isset( $entries[ $key ] ) ) { return ''; }
    $row = $entries[ $key ];
    if ( $row['media_id'] ) {
        return (string) wp_get_attachment_image( $row['media_id'], 'medium', false, array(
            'class' => $class . ' deprat-library-image',
            'alt' => '', 'aria-hidden' => 'true', 'loading' => 'lazy',
            'style' => 'object-fit:cover;object-position:' . (int) $row['position_x'] . '% ' . (int) $row['position_y'] . '%',
        ) );
    }
    // Rendus 3D livrés avec DEPRAT Core, par identifiant stable.
    // Une image choisie en médiathèque par l'ADV reste prioritaire.
    // Le guide-sangle est DISTINCT de l'icône de section « Sangle ».
    $trial_icons = array(
        'liaison' => 'attache-3d.webp',
        'treuil' => 'treuil-3d.webp',
        'sangle' => 'sangle-3d.webp',
        'supports_arbres' => 'support-arbres-3d.webp',
        'flasque_guidage_bague' => 'flasque-guidage-3d.webp',
        'garage' => 'porte-garage-3d.webp',
        'jalousie' => 'persienne-jalousie-3d.webp',
        'bras' => 'bras-projection-3d.webp',
        'guide_sangle' => 'guide-sangle-3d.webp',
    );
    if ( isset( $trial_icons[ $key ] ) ) {
        $url = plugins_url( 'assets/icons/' . $trial_icons[ $key ], dirname( __DIR__ ) . '/deprat-core.php' );
        return '<img class="' . esc_attr( $class . ' deprat-library-3d-image' ) . '"'
            . ' src="' . esc_url( $url ) . '"'
            . ' alt="" aria-hidden="true" loading="lazy"'
            . ' width="' . absint( $size ) . '" height="' . absint( $size ) . '">';
    }
    // Illustrations techniques fournies par DEPRAT : priorite au PDF,
    // sauf si l'administrateur a choisi une image personnalisee.
    if ( function_exists( 'deprat_pdf_icon_markup' ) ) {
        $pdf = deprat_pdf_icon_markup( $key, $class, $size );
        if ( $pdf ) { return $pdf; }
    }
    $paths = deprat_catalog_section_icon_paths();
    if ( ! isset( $paths[ $key ] ) ) { return ''; }
    return '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" width="' . absint( $size )
        . '" height="' . absint( $size )
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">'
        . $paths[ $key ] . '</svg>';
}
function deprat_icon_library_render_picker( $name, $selected = '' ) {
    foreach ( deprat_icon_library_entries() as $key => $row ) {
        echo '<label title="' . esc_attr( $row['name'] ) . '"><input type="radio" name="' . esc_attr( $name )
            . '" value="' . esc_attr( $key ) . '" ' . checked( $selected, $key, false )
            . '><span class="deprat-section-icon-choice">';
        echo deprat_icon_library_markup( $key );
        echo '<small>' . esc_html( $row['name'] ) . '</small></span></label>';
    }
}
function deprat_icon_library_render_admin() {
    if ( ! current_user_can( 'manage_product_terms' ) ) { return; }
    $entries = deprat_icon_library_entries();
    ?>
    <section class="deprat-library-panel" data-deprat-icon-library>
        <div class="deprat-library-heading">
            <div>
                <h2>Bibliothèque d’icônes</h2>
                <p>Vos pictogrammes communs aux sections, catégories et sous-catégories. Modifiez une icône ici pour la remplacer partout où elle est utilisée.</p>
            </div>
            <button type="button" class="button button-primary" data-library-new>+ Ajouter une icône</button>
        </div>
        <div class="deprat-library-grid">
            <?php foreach ( $entries as $key => $entry ) : ?>
                <div class="deprat-library-card" data-library-key="<?php echo esc_attr( $key ); ?>"
                    data-library-name="<?php echo esc_attr( $entry['name'] ); ?>"
                    data-library-media="<?php echo absint( $entry['media_id'] ); ?>"
                    data-library-position-x="<?php echo (int) $entry['position_x']; ?>"
                    data-library-position-y="<?php echo (int) $entry['position_y']; ?>">
                    <div class="deprat-library-actions">
                        <button type="button" data-library-edit title="Modifier" aria-label="<?php echo esc_attr( 'Modifier ' . $entry['name'] ); ?>">✎</button>
                        <button type="button" data-library-delete title="Supprimer" aria-label="<?php echo esc_attr( 'Supprimer ' . $entry['name'] ); ?>">×</button>
                    </div>
                    <div class="deprat-library-art"><?php echo deprat_icon_library_markup( $key, 'deprat-library-icon', 39 ); ?></div>
                    <strong><?php echo esc_html( $entry['name'] ); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="deprat-library-message" data-library-message role="status" aria-live="polite"></p>
        <div class="deprat-library-overlay" data-library-overlay hidden>
            <form class="deprat-library-dialog" data-library-form role="dialog" aria-modal="true" aria-labelledby="deprat-library-title">
                <button type="button" class="deprat-library-close" data-library-cancel aria-label="Fermer">×</button>
                <h3 id="deprat-library-title" data-library-title>Ajouter une icône</h3>
                <input type="hidden" data-library-id>
                <label>Nom de l’icône
                    <input type="text" data-library-name maxlength="60" required placeholder="Ex. Moteur filaire">
                </label>
                <div class="deprat-library-image-field">
                    <div class="deprat-library-edit-preview" data-library-preview tabindex="0" role="group" aria-label="Cadrage carré de l’icône : faites glisser l’image pour la déplacer">Choisir une image</div>
                    <div>
                        <button type="button" class="button" data-library-choose>Choisir ou importer une image</button>
                        <button type="button" class="button" data-library-clear hidden>Retirer l’image</button>
                        <p>PNG, JPG ou WebP. Faites glisser l’image dans le carré pour choisir son cadrage. Utilisez les flèches du clavier pour affiner. Le nom et le cadrage seront enregistrés.</p><p class="deprat-library-position-status" data-library-position-status>Position : centre</p>
                    </div>
                </div>
                <p class="deprat-library-error" data-library-error role="alert" hidden></p>
                <div class="deprat-library-dialog-actions">
                    <button type="button" class="button" data-library-cancel>Annuler</button>
                    <button type="submit" class="button button-primary" data-library-save>Enregistrer</button>
                </div>
            </form>
        </div>
    </section>
    <?php
}

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
    if ( ! in_array( $hook, array( 'product_page_deprat-categories-tree', 'toplevel_page_deprat-shop-settings' ), true ) ) { return; }
    wp_enqueue_style( 'deprat-icon-library', plugins_url( 'assets/css/icon-library.css', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'deprat-category-tree' ), '0.1.54' );
    wp_enqueue_script( 'deprat-icon-library', plugins_url( 'assets/js/icon-library.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'media-views' ), '0.1.48', true );
    wp_localize_script( 'deprat-icon-library', 'depratIconLibrary', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'deprat_icon_library' ),
        'canUpload' => current_user_can( 'upload_files' ),
    ) );
}, 30 );

/**
 * Empêcher la suppression d'une icône encore utilisée. Les associations
 * demeurent valides, et les pictogrammes n'apparaissent jamais cassés.
 */
function deprat_icon_library_is_used( $key ) {
    foreach ( deprat_catalog_section_registry() as $section ) {
        if ( isset( $section['icon'] ) && $section['icon'] === $key ) { return true; }
    }
    $terms = get_terms( array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_deprat_category_icon', 'value' => $key ) ),
    ) );
    return ! is_wp_error( $terms ) && ! empty( $terms );
}

function deprat_icon_library_ajax_save() {
    if ( ! current_user_can( 'manage_product_terms' ) || ! check_ajax_referer( 'deprat_icon_library', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Accès refusé ou session expirée.' ), 403 );
    }
    $action = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
    $key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
    $saved = get_option( 'deprat_icon_library_v1', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }
    $entries = deprat_icon_library_entries();
    if ( 'delete' === $action ) {
        if ( ! isset( $entries[ $key ] ) ) {
            wp_send_json_error( array( 'message' => 'Icône introuvable.' ), 404 );
        }
        if ( deprat_icon_library_is_used( $key ) ) {
            wp_send_json_error( array( 'message' => 'Cette icône est utilisée sur le site. Choisissez une autre icône pour les rubriques et catégories concernées avant de la retirer.' ), 409 );
        }
        // Les pictogrammes initiaux peuvent être retirés, mais leurs tracés restent dans le code.
        $saved[ $key ] = array_merge( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array(), array( 'hidden' => 1 ) );
        update_option( 'deprat_icon_library_v1', $saved, false );
        wp_send_json_success( array( 'message' => 'Icône retirée de la bibliothèque.' ) );
    }
    if ( 'save' !== $action ) { wp_send_json_error( array( 'message' => 'Action invalide.' ), 400 ); }
    $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
    $media = isset( $_POST['media_id'] ) && is_scalar( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
    if ( '' === $name || strlen( $name ) > 180 ) {
        wp_send_json_error( array( 'message' => 'Renseignez un nom de 1 à 60 caractères.' ), 400 );
    }
    if ( $media && ( ! current_user_can( 'upload_files' ) || ! deprat_catalog_section_media_allowed( $media ) ) ) {
        wp_send_json_error( array( 'message' => 'Sélectionnez une image JPG, PNG ou WebP valide.' ), 400 );
    }
    if ( $key ) {
        if ( ! isset( $entries[ $key ] ) ) { wp_send_json_error( array( 'message' => 'Icône inconnue.' ), 404 ); }
        $builtin = $entries[ $key ]['builtin'];
        if ( ! $builtin && ! $media ) { wp_send_json_error( array( 'message' => 'Une image est nécessaire pour cette icône.' ), 400 ); }
    } else {
        if ( count( $entries ) >= 150 ) { wp_send_json_error( array( 'message' => 'Bibliothèque pleine.' ), 400 ); }
        if ( ! $media ) { wp_send_json_error( array( 'message' => 'Choisissez une image pour votre nouvelle icône.' ), 400 ); }
        do { $key = 'icon_' . bin2hex( random_bytes( 6 ) ); } while ( isset( $saved[ $key ] ) );
    }
    $x = isset( $_POST['position_x'] ) ? max( 0, min( 100, (int) $_POST['position_x'] ) ) : 50;
    $y = isset( $_POST['position_y'] ) ? max( 0, min( 100, (int) $_POST['position_y'] ) ) : 50;
    $saved[ $key ] = array( 'name' => $name, 'media_id' => $media, 'position_x' => $x, 'position_y' => $y );
    update_option( 'deprat_icon_library_v1', $saved, false );
    wp_send_json_success( array( 'key' => $key, 'message' => 'Icône enregistrée.' ) );
}
add_action( 'wp_ajax_deprat_icon_library_save', 'deprat_icon_library_ajax_save' );
