<?php
/**
 * Sauvegarde automatique des nouvelles fiches DEPRAT.
 * Ne publie jamais un produit, et ne remet jamais en brouillon un produit déjà publié.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_product_autosave_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post-new.php', 'post.php' ), true ) ) { return; }
    if ( ! function_exists( 'get_current_screen' ) ) { return; }
    $screen = get_current_screen();
    if ( ! $screen || 'product' !== $screen->post_type ) { return; }
    global $post;
    $id = isset( $post->ID ) ? (int) $post->ID : 0;
    wp_enqueue_script(
        'deprat-product-autosave',
        plugins_url( 'assets/js/product-autosave-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ),
        array( 'deprat-product-tech-admin' ), '0.1.23', true
    );
    wp_localize_script( 'deprat-product-autosave', 'depratAutoSaveConfig', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'deprat_product_draft_autosave' ),
        'postId' => $id,
        'postStatus' => $id ? get_post_status( $id ) : 'auto-draft',
    ) );
}
add_action( 'admin_enqueue_scripts', 'deprat_product_autosave_assets', 35 );

/** Sauvegarde ciblée avec validation côté serveur : seule une fiche non publiée est modifiable. */
function deprat_product_draft_autosave() {
    if ( ! check_ajax_referer( 'deprat_product_draft_autosave', 'autosave_nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Session expirée. Rechargez la fiche.' ), 403 );
    }
    $id = isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $id || 'product' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
        wp_send_json_error( array( 'message' => 'Sauvegarde non autorisée.' ), 403 );
    }
    $status = get_post_status( $id );
    if ( ! in_array( $status, array( 'auto-draft', 'draft', 'pending' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Cette fiche est déjà publiée : utilisez Mettre à jour.' ), 409 );
    }
    $nonce = isset( $_POST['deprat_product_tech_nonce'] ) && is_scalar( $_POST['deprat_product_tech_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_product_tech_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'deprat_product_tech_save' ) ) {
        wp_send_json_error( array( 'message' => 'La fiche a expiré. Rechargez la page.' ), 403 );
    }
    $title = isset( $_POST['deprat_auto_title'] ) && is_scalar( $_POST['deprat_auto_title'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_auto_title'] ) ) : '';
    if ( '' === $title ) { $title = 'Produit sans titre'; }
    $save = wp_update_post( array(
        'ID' => $id,
        'post_title' => $title,
        // IMPORTANT : même si le navigateur envoie "publish", la sauvegarde est TOUJOURS un brouillon.
        'post_status' => 'draft',
    ), true );
    if ( is_wp_error( $save ) ) {
        wp_send_json_error( array( 'message' => $save->get_error_message() ), 400 );
    }
    if ( isset( $_POST['tax_input']['product_cat'] ) && is_array( $_POST['tax_input']['product_cat'] ) ) {
        $terms = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['tax_input']['product_cat'] ) ) ) ) );
        wp_set_object_terms( $id, $terms, 'product_cat' );
    }
    if ( function_exists( 'wc_get_product' ) ) {
        $product = wc_get_product( $id );
        if ( $product ) {
            if ( isset( $_POST['_sku'] ) && is_scalar( $_POST['_sku'] ) ) {
                try {
                    $product->set_sku( sanitize_text_field( wp_unslash( $_POST['_sku'] ) ) );
                } catch ( Exception $error ) {
                    wp_send_json_error( array( 'message' => $error->getMessage() ), 409 );
                }
            }
            if ( isset( $_POST['deprat_product_gallery'] ) ) {
                deprat_product_tech_save_gallery( $product );
            }
            $product->save();
        }
    }
    // Les hooks save_post_product déclenchés ci-dessus ont également sauvegardé
    // les métadonnées techniques, conditionnements, documents et modèle 3D.
    wp_send_json_success( array(
        'id' => $id,
        'status' => 'draft',
        'message' => 'Brouillon enregistré automatiquement',
        'time' => wp_date( 'H:i' ),
    ) );
}
add_action( 'wp_ajax_deprat_product_draft_autosave', 'deprat_product_draft_autosave' );
