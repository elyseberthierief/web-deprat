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
    $clean = array(
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
    wp_enqueue_style( 'deprat-product-tech-admin', plugins_url( 'assets/css/product-technical-admin.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.15' );
    wp_enqueue_script( 'deprat-product-tech-admin', plugins_url( 'assets/js/product-technical-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'media-views' ), '0.1.15', true );
}
add_action( 'admin_enqueue_scripts', 'deprat_product_tech_admin_assets' );

function deprat_product_tech_front_assets() {
    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style( 'deprat-product-tech-front', plugins_url( 'assets/css/product-technical-front.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.14' );
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
    $datasheet = deprat_product_tech_attachment( isset( $data['datasheet'] ) ? $data['datasheet'] : 0, 'pdf' );
    if ( ! $features && ! $tests && ! $norms && '' === $description && '' === $delivery && '' === $packaging && ! $package_quantity && ! $technical_image && ! $dimensions_image && ! $datasheet ) {
        return;
    }
    require dirname( __DIR__ ) . '/templates/product-technical-front.php';
}
add_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front', 15 );
