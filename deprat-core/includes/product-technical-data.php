<?php
/**
 * Fiche technique facultative pour les produits WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function deprat_product_tech_default_rows( $type ) {
    if ( 'features' === $type ) {
        $labels = array( 'Matière', 'Revêtement', 'Tenue', 'Épaisseur', 'Poids linéique' );
    } elseif ( 'tests' === $type ) {
        $labels = array( 'Essai d’endurance', 'Poids du tablier', 'Couple de destruction' );
    } else {
        return array();
    }

    $rows = array();
    foreach ( $labels as $label ) {
        $rows[] = array( 'label' => $label, 'value' => '' );
    }
    return $rows;
}

function deprat_product_tech_data( $product_id ) {
    $stored = get_post_meta( $product_id, '_deprat_product_technical', true );
    return is_array( $stored ) ? $stored : array();
}

function deprat_product_tech_attachment( $value, $kind ) {
    $attachment_id = is_scalar( $value ) ? absint( $value ) : 0;
    if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
        return 0;
    }
    if ( 'pdf' === $kind ) {
        return 'application/pdf' === get_post_mime_type( $attachment_id ) ? $attachment_id : 0;
    }
    return wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
}

function deprat_product_tech_text( $value, $multiline = false ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }
    return $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
}

function deprat_product_tech_pairs( $rows ) {
    if ( ! is_array( $rows ) ) {
        return array();
    }
    $clean = array();
    foreach ( array_slice( $rows, 0, 50 ) as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $label = deprat_product_tech_text( isset( $row['label'] ) ? $row['label'] : '' );
        $value = deprat_product_tech_text( isset( $row['value'] ) ? $row['value'] : '' );
        if ( '' !== $label || '' !== $value ) {
            $clean[] = array( 'label' => $label, 'value' => $value );
        }
    }
    return $clean;
}


/** Schémas ordonnés : les deux anciens médias sont conservés pour les produits existants. */
function deprat_product_tech_diagrams( $data ) {
    if ( array_key_exists( 'diagrams', $data ) && is_array( $data['diagrams'] ) ) {
        $rows = $data['diagrams'];
    } else {
        $rows = array(
            array( 'title' => 'Schéma technique', 'image' => isset( $data['technical_image'] ) ? $data['technical_image'] : 0 ),
            array( 'title' => 'Schéma des dimensions', 'image' => isset( $data['dimensions_image'] ) ? $data['dimensions_image'] : 0 ),
        );
    }
    $visible = array();
    foreach ( array_slice( $rows, 0, 50 ) as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $image = deprat_product_tech_attachment( isset( $row['image'] ) ? $row['image'] : 0, 'image' );
        if ( ! $image ) { continue; }
        $visible[] = array(
            'title' => deprat_product_tech_text( isset( $row['title'] ) ? $row['title'] : '' ) ?: 'Schéma',
            'image' => $image,
        );
    }
    return $visible;
}


/** Documents PDF ordonnés, compatibles avec l'ancien PDF unique. */
function deprat_product_tech_datasheets( $data ) {
    if ( array_key_exists( 'datasheets', $data ) && is_array( $data['datasheets'] ) ) {
        $rows = $data['datasheets'];
    } else {
        $rows = array( array(
            'title' => 'Fiche technique PDF',
            'file' => isset( $data['datasheet'] ) ? $data['datasheet'] : 0,
        ) );
    }
    $result = array();
    foreach ( array_slice( $rows, 0, 30 ) as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $file = deprat_product_tech_attachment( isset( $row['file'] ) ? $row['file'] : 0, 'pdf' );
        if ( ! $file ) { continue; }
        $title = deprat_product_tech_text( isset( $row['title'] ) ? $row['title'] : '' );
        $result[] = array(
            'title' => '' !== $title ? $title : 'Fiche technique PDF',
            'file' => $file,
        );
    }
    return $result;
}

/**
 * Met à jour la galerie native WooCommerce. Les photos sont donc visibles
 * dans la galerie du produit Astra, sans nouvelle galerie indépendante.
 */
function deprat_product_tech_save_gallery( $product ) {
    if ( ! is_object( $product ) || ! is_callable( array( $product, 'set_gallery_image_ids' ) ) ) { return; }
    $id = $product->get_id();
    if ( ! isset( $_POST['deprat_product_tech_nonce'] ) || ! is_scalar( $_POST['deprat_product_tech_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['deprat_product_tech_nonce'] ) ), 'deprat_product_tech_save' )
        || ! current_user_can( 'edit_post', $id )
        || ! isset( $_POST['deprat_product_gallery'] ) || ! is_scalar( $_POST['deprat_product_gallery'] ) ) {
        return;
    }
    $raw = sanitize_text_field( wp_unslash( $_POST['deprat_product_gallery'] ) );
    $ids = array();
    foreach ( array_slice( explode( ',', $raw ), 0, 50 ) as $value ) {
        if ( ! ctype_digit( $value ) ) { continue; }
        $image = deprat_product_tech_attachment( $value, 'image' );
        if ( $image && ! in_array( $image, $ids, true ) ) { $ids[] = $image; }
    }
    $product->set_gallery_image_ids( $ids );
}
add_action( 'woocommerce_admin_process_product_object', 'deprat_product_tech_save_gallery', 20 );

function deprat_product_tech_package_summary( $data ) {
    $count = isset( $data['package_count'] ) ? absint( $data['package_count'] ) : 0;
    $pieces = isset( $data['package_quantity'] ) ? absint( $data['package_quantity'] ) : 0;
    $type = isset( $data['packaging'] ) ? trim( deprat_product_tech_text( $data['packaging'] ) ) : '';
    if ( ! $count || ! $pieces || '' === $type ) { return ''; }
    if ( $count > 1 && ! preg_match( '/[sxz]$/iu', $type ) ) { $type .= 's'; }
    $pieces_label = $pieces > 1 ? 'pièces' : 'pièce';
    $verb = $count > 1 ? 'contenant chacun' : 'contenant';
    return sprintf(
        'Le client recevra %d %s %s %d %s, soit un total de %d pièces.',
        $count, $type, $verb, $pieces, $pieces_label, $count * $pieces
    );
}

/** Suggestions libres : valeurs déjà saisies, sans imposer de vocabulaire fermé. */
function deprat_product_tech_packaging_suggestions() {
    $suggestions = array( 'carton', 'lot', 'unité', 'palette', 'boîte', 'sachet', 'colis' );
    global $wpdb;
    if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) { return $suggestions; }
    $values = $wpdb->get_col( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id DESC LIMIT 300",
        '_deprat_product_technical'
    ) );
    foreach ( (array) $values as $value ) {
        $data = maybe_unserialize( $value );
        if ( is_array( $data ) && isset( $data['packaging'] ) && is_scalar( $data['packaging'] ) ) {
            $name = trim( deprat_product_tech_text( $data['packaging'] ) );
            if ( '' !== $name && ! in_array( $name, $suggestions, true ) ) { $suggestions[] = $name; }
        }
    }
    return array_slice( $suggestions, 0, 100 );
}

/** Création AJAX sécurisée : fonctionne depuis la fiche sans quitter l'éditeur. */
function deprat_product_tech_create_category() {
    if ( ! current_user_can( 'manage_product_terms' ) || ! taxonomy_exists( 'product_cat' ) ) {
        wp_send_json_error( array( 'message' => 'Droits insuffisants.' ), 403 );
    }
    if ( ! check_ajax_referer( 'deprat_create_product_category', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Session expirée. Rechargez la page.' ), 403 );
    }
    $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
    $parent = isset( $_POST['parent'] ) && is_scalar( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
    if ( '' === $name || strlen( $name ) > 160 ) {
        wp_send_json_error( array( 'message' => 'Renseignez un nom de catégorie valide.' ), 400 );
    }
    if ( $parent && ! term_exists( $parent, 'product_cat' ) ) {
        wp_send_json_error( array( 'message' => 'La catégorie parente n’existe pas.' ), 400 );
    }
    if ( term_exists( $name, 'product_cat', $parent ) ) {
        wp_send_json_error( array( 'message' => 'Cette catégorie existe déjà à cet emplacement.' ), 409 );
    }
    $created = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
    if ( is_wp_error( $created ) ) {
        wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
    }
    wp_send_json_success( array( 'id' => (int) $created['term_id'], 'name' => $name, 'parent' => $parent ) );
}
add_action( 'wp_ajax_deprat_product_category_create', 'deprat_product_tech_create_category' );

function deprat_product_tech_register_box() {
    add_meta_box(
        'deprat-product-technical',
        'Fiche technique DEPRAT',
        'deprat_product_tech_render_admin',
        'product',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'deprat_product_tech_register_box' );

function deprat_product_tech_render_admin( $post ) {
    $data = deprat_product_tech_data( $post->ID );
    $features = isset( $data['features'] ) && is_array( $data['features'] ) ? $data['features'] : deprat_product_tech_default_rows( 'features' );
    $tests = isset( $data['tests'] ) && is_array( $data['tests'] ) ? $data['tests'] : deprat_product_tech_default_rows( 'tests' );
    $norms = isset( $data['norms'] ) && is_array( $data['norms'] ) ? $data['norms'] : array();
    require dirname( __DIR__ ) . '/templates/product-technical-admin.php';
}

function deprat_product_tech_save( $post_id ) {
    if ( ! isset( $_POST['deprat_product_tech_nonce'] ) || ! is_scalar( $_POST['deprat_product_tech_nonce'] ) ) {
        return;
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST['deprat_product_tech_nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'deprat_product_tech_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return;
    }
    if ( ! isset( $_POST['deprat_product_tech'] ) || ! is_array( $_POST['deprat_product_tech'] ) ) {
        return;
    }
    $input = wp_unslash( $_POST['deprat_product_tech'] );
    $norms = array();
    if ( isset( $input['norms'] ) && is_array( $input['norms'] ) ) {
        foreach ( array_slice( $input['norms'], 0, 50 ) as $norm ) {
            $norm = deprat_product_tech_text( $norm );
            if ( '' !== $norm ) {
                $norms[] = $norm;
            }
        }
    }
    $quantity = isset( $input['package_quantity'] ) && is_scalar( $input['package_quantity'] ) ? (string) $input['package_quantity'] : '';
    $quantity = preg_match( '/^[1-9][0-9]{0,7}$/', $quantity ) ? (int) $quantity : '';
    $count = isset( $input['package_count'] ) && is_scalar( $input['package_count'] ) ? (string) $input['package_count'] : '';
    $count = preg_match( '/^[1-9][0-9]{0,5}$/', $count ) ? (int) $count : '';
    $diagrams = array();
    if ( isset( $input['diagrams'] ) && is_array( $input['diagrams'] ) ) {
        foreach ( array_slice( $input['diagrams'], 0, 50 ) as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $image = deprat_product_tech_attachment( isset( $row['image'] ) ? $row['image'] : 0, 'image' );
            if ( ! $image ) { continue; }
            $diagrams[] = array(
                'title' => deprat_product_tech_text( isset( $row['title'] ) ? $row['title'] : '' ),
                'image' => $image,
            );
        }
    }
    $datasheets = array();
    if ( isset( $input['datasheets'] ) && is_array( $input['datasheets'] ) ) {
        foreach ( array_slice( $input['datasheets'], 0, 30 ) as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $file = deprat_product_tech_attachment( isset( $row['file'] ) ? $row['file'] : 0, 'pdf' );
            if ( ! $file ) { continue; }
            $datasheets[] = array(
                'title' => deprat_product_tech_text( isset( $row['title'] ) ? $row['title'] : '' ),
                'file' => $file,
            );
        }
    }
    $clean = array(
        'package_count' => $count,
        'description' => deprat_product_tech_text( isset( $input['description'] ) ? $input['description'] : '', true ),
        'features' => deprat_product_tech_pairs( isset( $input['features'] ) ? $input['features'] : array() ),
        'norms' => $norms,
        'tests' => deprat_product_tech_pairs( isset( $input['tests'] ) ? $input['tests'] : array() ),
        'delivery' => deprat_product_tech_text( isset( $input['delivery'] ) ? $input['delivery'] : '' ),
        'packaging' => deprat_product_tech_text( isset( $input['packaging'] ) ? $input['packaging'] : '' ),
        'package_quantity' => $quantity,
        'technical_image' => deprat_product_tech_attachment( isset( $input['technical_image'] ) ? $input['technical_image'] : 0, 'image' ),
        'dimensions_image' => deprat_product_tech_attachment( isset( $input['dimensions_image'] ) ? $input['dimensions_image'] : 0, 'image' ),
        'datasheet' => deprat_product_tech_attachment( isset( $input['datasheet'] ) ? $input['datasheet'] : 0, 'pdf' ),
    );
    if ( isset( $input['diagrams_present'] ) ) { $clean['diagrams'] = $diagrams; }
    if ( isset( $input['datasheets_present'] ) ) {
        $clean['datasheets'] = $datasheets;
        $clean['datasheet'] = $datasheets ? $datasheets[0]['file'] : 0;
    }
    // WordPress déséchappe les métadonnées à l’enregistrement.
    update_post_meta( $post_id, '_deprat_product_technical', wp_slash( $clean ) );
}
add_action( 'save_post_product', 'deprat_product_tech_save' );

function deprat_product_tech_admin_assets( $hook ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'product' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style( 'deprat-product-tech-admin', plugins_url( 'assets/css/product-technical-admin.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.17' );
    wp_enqueue_script( 'deprat-product-tech-admin', plugins_url( 'assets/js/product-technical-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'media-views' ), '0.1.17', true );
    wp_localize_script( 'deprat-product-tech-admin', 'depratProductEditor', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'categoryNonce' => wp_create_nonce( 'deprat_create_product_category' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'deprat_product_tech_admin_assets' );

function deprat_product_tech_front_assets() {
    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style( 'deprat-product-tech-front', plugins_url( 'assets/css/product-technical-front.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.17' );
    }
}
add_action( 'wp_enqueue_scripts', 'deprat_product_tech_front_assets' );

function deprat_product_tech_display_pairs( $rows ) {
    $visible = array();
    foreach ( is_array( $rows ) ? $rows : array() as $row ) {
        if ( ! is_array( $row ) || ! isset( $row['value'] ) || ! is_scalar( $row['value'] ) || '' === trim( (string) $row['value'] ) ) {
            continue;
        }
        $visible[] = array(
            'label' => isset( $row['label'] ) && is_scalar( $row['label'] ) && '' !== (string) $row['label'] ? (string) $row['label'] : 'Information',
            'value' => (string) $row['value'],
        );
    }
    return $visible;
}

function deprat_product_tech_render_front() {
    global $product;
    if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_id' ) ) ) {
        return;
    }
    $data = deprat_product_tech_data( $product->get_id() );
    $features = deprat_product_tech_display_pairs( isset( $data['features'] ) ? $data['features'] : array() );
    $tests = deprat_product_tech_display_pairs( isset( $data['tests'] ) ? $data['tests'] : array() );
    $norms = isset( $data['norms'] ) && is_array( $data['norms'] ) ? array_filter( $data['norms'], static function ( $norm ) {
        return is_scalar( $norm ) && '' !== trim( (string) $norm );
    } ) : array();
    $description = isset( $data['description'] ) && is_scalar( $data['description'] ) ? trim( (string) $data['description'] ) : '';
    $delivery = isset( $data['delivery'] ) && is_scalar( $data['delivery'] ) ? trim( (string) $data['delivery'] ) : '';
    $packaging = isset( $data['packaging'] ) && is_scalar( $data['packaging'] ) ? trim( (string) $data['packaging'] ) : '';
    $package_quantity = isset( $data['package_quantity'] ) && is_scalar( $data['package_quantity'] ) ? absint( $data['package_quantity'] ) : 0;
    $technical_image = deprat_product_tech_attachment( isset( $data['technical_image'] ) ? $data['technical_image'] : 0, 'image' );
    $dimensions_image = deprat_product_tech_attachment( isset( $data['dimensions_image'] ) ? $data['dimensions_image'] : 0, 'image' );
    $datasheets = deprat_product_tech_datasheets( $data );
    $diagrams = deprat_product_tech_diagrams( $data );
    $package_summary = deprat_product_tech_package_summary( $data );
    $package_count = isset( $data['package_count'] ) ? absint( $data['package_count'] ) : 0;
    if ( ! $features && ! $tests && ! $norms && '' === $description && '' === $delivery && '' === $packaging && ! $package_quantity && ! $diagrams && ! $datasheets ) {
        return;
    }
    require dirname( __DIR__ ) . '/templates/product-technical-front.php';
}
add_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front', 15 );
