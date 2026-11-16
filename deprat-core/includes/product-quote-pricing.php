<?php
/**
 * Catalogue DEPRAT exclusivement sur devis : WooCommerce accepte une pièce
 * sans prix renseigné sans créer de faux tarif de vente dans WordPress.
 *
 * Le 0 technique n'est exposé qu'au calcul du panier / devis WooCommerce.
 * La valeur stockée (_price, regular_price) reste vide.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Produits simples sans aucun prix commercial défini par l'ADV. */
function deprat_quote_product_has_no_price( $product ) {
    if ( ! is_object( $product ) || ! is_callable( array( $product, 'is_type' ) )
        || ! $product->is_type( 'simple' )
        || ! is_callable( array( $product, 'get_price' ) )
        || ! is_callable( array( $product, 'get_regular_price' ) )
        || ! is_callable( array( $product, 'get_sale_price' ) ) ) {
        return false;
    }
    // 'edit' lit la valeur brute sans appliquer le filtre WooCommerce 'view'.
    return '' === (string) $product->get_price( 'edit' )
        && '' === (string) $product->get_regular_price( 'edit' )
        && '' === (string) $product->get_sale_price( 'edit' );
}

/**
 * En vitrine et sur la demande AJAX, WooCommerce voit un prix calculatoire
 * nul et autorise naturellement is_purchasable() / add_to_cart().
 * Ne touche pas aux produits ayant un tarif, aux variations ni aux brouillons
 * invisibles au public. Ne crée ou modifie aucun champ de prix en base.
 */
function deprat_quote_virtual_price( $price, $product ) {
    if ( '' !== (string) $price || ! deprat_quote_product_has_no_price( $product ) ) {
        return $price;
    }
    if ( function_exists( 'is_admin' ) && is_admin()
        && ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) ) {
        return $price;
    }
    if ( ! is_callable( array( $product, 'get_status' ) ) ) { return $price; }
    $published = 'publish' === $product->get_status( 'edit' );
    $editor_preview = ! $published && is_callable( array( $product, 'get_id' ) )
        && function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $product->get_id() );
    if ( ! $published && ! $editor_preview ) { return $price; }
    return '0';
}
add_filter( 'woocommerce_product_get_price', 'deprat_quote_virtual_price', 10, 2 );

/**
 * Ne pas présenter le zéro technique comme un prix de vente :
 * le site DEPRAT traite une demande, pas un paiement.
 * Les autres tarifs et types de produits ne sont pas modifiés.
 */
function deprat_quote_no_price_html( $html, $product ) {
    if ( deprat_quote_product_has_no_price( $product ) ) {
        return '';
    }
    return $html;
}
add_filter( 'woocommerce_get_price_html', 'deprat_quote_no_price_html', 20, 2 );

/** Sur le panier de demande, une pièce sans tarif doit rester « sur devis ». */
function deprat_quote_cart_price_label( $html, $cart_item, $cart_item_key = '' ) {
    $product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
    if ( deprat_quote_product_has_no_price( $product ) ) {
        return 'Sur devis';
    }
    return $html;
}
add_filter( 'woocommerce_cart_item_price', 'deprat_quote_cart_price_label', 20, 3 );
add_filter( 'woocommerce_cart_item_subtotal', 'deprat_quote_cart_price_label', 20, 3 );
