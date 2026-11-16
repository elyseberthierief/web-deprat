<?php
/**
 * Présentation DEPRAT des pages produit WooCommerce.
 * Affiche la référence avant le nom, puis l'introduction et les catégories.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_product_single_activate_layout() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
    add_action( 'woocommerce_single_product_summary', 'deprat_product_single_intro', 4 );
    add_action( 'woocommerce_before_single_product', 'deprat_product_single_breadcrumb', 4 );
}
add_action( 'wp', 'deprat_product_single_activate_layout', 20 );

/**
 * Les onglets WooCommerce « Description » et « Informations complémentaires »
 * répètent les données de notre en-tête et des cartes techniques DEPRAT.
 * Ils sont masqués sur la page publique sans modifier ni effacer les données
 * des produits. Les éventuels autres onglets restent disponibles.
 */
function deprat_product_single_remove_duplicate_tabs( $tabs ) {
    if ( ! is_array( $tabs ) ) { return $tabs; }
    unset( $tabs['description'], $tabs['additional_information'] );
    return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'deprat_product_single_remove_duplicate_tabs', 99 );


function deprat_product_single_breadcrumb() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
    global $product;
    $product_id = get_queried_object_id();
    $home = home_url( '/' );
    $shop_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
    echo '<nav class="deprat-product-breadcrumb" aria-label="Fil d’Ariane">';
    echo '<a href="' . esc_url( $home ) . '">Accueil</a><span aria-hidden="true">/</span>';
    if ( $shop_id > 0 ) {
        echo '<a href="' . esc_url( get_permalink( $shop_id ) ) . '">Catalogue</a><span aria-hidden="true">/</span>';
    }
    $terms = get_the_terms( $product_id, 'product_cat' );
    if ( is_array( $terms ) && $terms ) {
        // Catégorie la plus précise : chaîne de parents remontée dans le bon ordre.
        usort( $terms, static function ( $a, $b ) {
            return count( get_ancestors( $b->term_id, 'product_cat' ) ) - count( get_ancestors( $a->term_id, 'product_cat' ) );
        } );
        $term = $terms[0];
        $ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat' ) );
        foreach ( array_merge( $ancestors, array( $term->term_id ) ) as $id ) {
            $item = get_term( $id, 'product_cat' );
            if ( ! $item || is_wp_error( $item ) ) { continue; }
            $url = get_term_link( $item );
            if ( is_wp_error( $url ) ) { continue; }
            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $item->name ) . '</a><span aria-hidden="true">/</span>';
        }
    }
    echo '<span class="deprat-breadcrumb-current" aria-current="page">' . esc_html( get_the_title( $product_id ) ) . '</span>';
    echo '</nav>';
}

function deprat_product_single_intro() {
    global $product;
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $id = $product->get_id();
    $sku = trim( (string) $product->get_sku() );
    $data = deprat_product_tech_data( $id );
    $tech_description = isset( $data['description'] ) && is_scalar( $data['description'] )
        ? trim( (string) $data['description'] ) : '';
    $short_description = trim( wp_strip_all_tags( $product->get_short_description() ) );
    $intro = '' !== $tech_description ? $tech_description : $short_description;
    $terms = get_the_terms( $id, 'product_cat' );
    echo '<div class="deprat-product-intro">';
    if ( '' !== $sku ) {
        echo '<p class="deprat-product-ref"><span>Référence produit</span><strong>' . esc_html( $sku ) . '</strong></p>';
    }
    echo '<h1 class="product_title entry-title">' . esc_html( $product->get_name() ) . '</h1>';
    if ( '' !== $intro ) {
        echo '<div class="deprat-product-intro-description">' . nl2br( esc_html( $intro ) ) . '</div>';
    }
    if ( is_array( $terms ) && $terms ) {
        echo '<div class="deprat-product-categories"><span>Catégorie' . ( count( $terms ) > 1 ? 's' : '' ) . '</span><div>';
        foreach ( $terms as $term ) {
            $url = get_term_link( $term );
            if ( is_wp_error( $url ) ) { continue; }
            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}

function deprat_product_tech_icon( $kind ) {
    $icons = array(
        'features' => '<path d="M4 7h16M4 12h16M4 17h16"/><path d="M8 5v4M16 10v4M11 15v4"/>',
        'norms' => '<path d="M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'tests' => '<path d="M4 19h16M6 16l4-5 3 3 5-7"/><path d="M15 7h3v3"/>',
        'package' => '<path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/>',
        'diagram' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 16h10M7 8h6M7 12h4"/>',
        'pdf' => '<path d="M6 2h8l5 5v14H6z"/><path d="M14 2v6h5M9 13h6M9 17h5"/>',
    );
    return '<span class="deprat-tech-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' .
        ( isset( $icons[$kind] ) ? $icons[$kind] : $icons['features'] ) . '</svg></span>';
}
