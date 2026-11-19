<?php
/**
 * WordPress integration smoke test — category visuals and home banner.
 * The test only adds temporary product_cat terms and cleans them up.
 */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! function_exists( 'deprat_catalog_category_icon' ) || ! function_exists( 'deprat_catalog_category_visual' ) ) {
    throw new RuntimeException( 'Category visual helpers unavailable.' );
}

$root = 0;
$child = 0;
$attachment = 0;
try {
    $suffix = substr( wp_generate_uuid4(), 0, 8 );
    $created_root = wp_insert_term( 'DEPRAT test Tubes visuels ' . $suffix, 'product_cat' );
    if ( is_wp_error( $created_root ) ) { throw new RuntimeException( $created_root->get_error_message() ); }
    $root = (int) $created_root['term_id'];

    $created_child = wp_insert_term( 'DEPRAT test Embouts visuels ' . $suffix, 'product_cat', array( 'parent' => $root ) );
    if ( is_wp_error( $created_child ) ) { throw new RuntimeException( $created_child->get_error_message() ); }
    $child = (int) $created_child['term_id'];

    deprat_category_tree_save_category_visual( $root, array( 'icon' => 'tubes', 'media_id' => 0 ) );
    deprat_category_tree_save_category_visual( $child, array( 'icon' => 'liaison', 'media_id' => 0 ) );

    $root_html = deprat_catalog_category_icon( get_term( $root, 'product_cat' ) );
    $child_html = deprat_catalog_category_icon( get_term( $child, 'product_cat' ) );
    if ( strpos( $root_html, 'deprat-category-icon' ) === false
        || strpos( $child_html, 'deprat-category-icon' ) === false
        || $root_html === $child_html ) {
        throw new RuntimeException( 'Root/child categories must retain independent SVG icons.' );
    }
    $row = deprat_category_tree_visual_response( get_term( $child, 'product_cat' ) );
    if ( $row['icon'] !== 'liaison' || $row['media_id'] !== 0
        || strpos( $row['icon_html'], 'deprat-category-icon' ) === false ) {
        throw new RuntimeException( 'Admin category visual response is invalid.' );
    }

    ob_start();
    deprat_category_tree_render_icon_picker( 'create' );
    deprat_category_tree_render_icon_picker( 'edit' );
    $picker = ob_get_clean();
    if ( strpos( $picker, 'data-category-icon-picker="create"' ) === false
        || strpos( $picker, 'data-category-icon-picker="edit"' ) === false ) {
        throw new RuntimeException( 'Category/subcategory icon selectors must appear in both dialogs.' );
    }

    // Image option must use a real WP image attachment, not a PDF/SVG.
    $attachment = wp_insert_post( array(
        'post_type' => 'attachment',
        'post_title' => 'DEPRAT test image',
        'post_mime_type' => 'image/png',
        'post_status' => 'inherit',
    ) );
    if ( ! $attachment || is_wp_error( $attachment ) ) {
        throw new RuntimeException( 'Unable to create test attachment.' );
    }
    deprat_category_tree_save_category_visual( $child, array( 'icon' => 'liaison', 'media_id' => $attachment ) );
    $visual = deprat_catalog_category_visual( $child );
    if ( (int) $visual['media_id'] !== (int) $attachment ) {
        throw new RuntimeException( 'Category image metadata has not been saved.' );
    }
    $nav = deprat_home_category_carousel_html();
    if ( strpos( $nav, 'data-deprat-home-categories' ) === false
        || strpos( $nav, 'DEPRAT test Tubes visuels' ) === false ) {
        throw new RuntimeException( 'The real WooCommerce category should appear on the homepage carousel.' );
    }

    echo "OK: independent category and subcategory icons, image meta and editor controls.\n";
} finally {
    if ( $child ) { wp_delete_term( $child, 'product_cat' ); }
    if ( $root ) { wp_delete_term( $root, 'product_cat' ); }
    if ( $attachment ) { wp_delete_post( $attachment, true ); }
}
