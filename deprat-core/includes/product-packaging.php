<?php
/**
 * Conditionnements B2B DEPRAT : vente à la pièce ou en longueurs de mètres.
 * Les anciens produits restent lisibles et les nouvelles fiches acceptent 30 offres.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_packaging_decimal( $value ) {
    if ( ! is_scalar( $value ) ) { return 0; }
    $value = str_replace( ',', '.', trim( (string) $value ) );
    if ( ! preg_match( '/^\d{1,8}(?:\.\d{1,3})?$/D', $value ) ) { return 0; }
    $number = (float) $value;
    return $number > 0 && $number <= 10000000 ? $number : 0;
}

function deprat_packaging_number( $value ) {
    $formatted = number_format( (float) $value, 3, ',', ' ' );
    return rtrim( rtrim( $formatted, '0' ), ',' );
}

function deprat_packaging_clean_row( $row ) {
    if ( ! is_array( $row ) ) { return null; }
    $mode = isset( $row['mode'] ) && is_scalar( $row['mode'] ) ? sanitize_key( $row['mode'] ) : '';
    $title = isset( $row['title'] ) && is_scalar( $row['title'] ) ? substr( sanitize_text_field( $row['title'] ), 0, 110 ) : '';
    if ( 'unitary' === $mode ) {
        return array( 'mode' => 'unitary', 'title' => 'À l’unité – quantité libre' );
    }
    if ( 'length' === $mode ) {
        $total = deprat_packaging_decimal( isset( $row['total_m'] ) ? $row['total_m'] : 0 );
        $length = deprat_packaging_decimal( isset( $row['length_m'] ) ? $row['length_m'] : 0 );
        if ( ! $total || ! $length ) { return null; }
        $ratio = $total / $length;
        $sections = (int) round( $ratio );
        if ( $sections < 1 || $sections > 10000000 || abs( $ratio - $sections ) > 0.00000001 ) { return null; }
        return array( 'mode' => 'length', 'title' => $title, 'total_m' => $total, 'length_m' => $length, 'sections' => $sections );
    }
    if ( 'pieces' !== $mode ) { return null; }
    $count = isset( $row['count'] ) && is_scalar( $row['count'] ) ? (string) $row['count'] : '';
    $pieces = isset( $row['pieces'] ) && is_scalar( $row['pieces'] ) ? (string) $row['pieces'] : '';
    $type = isset( $row['type'] ) && is_scalar( $row['type'] ) ? trim( sanitize_text_field( $row['type'] ) ) : '';
    $type = substr( $type, 0, 70 );
    if ( ! preg_match( '/^[1-9][0-9]{0,5}$/D', $count ) || ! preg_match( '/^[1-9][0-9]{0,7}$/D', $pieces ) || '' === $type ) { return null; }
    $count = (int) $count;
    $pieces = (int) $pieces;
    if ( $count * $pieces > 1000000000 ) { return null; }
    return array( 'mode' => 'pieces', 'title' => $title, 'type' => $type, 'count' => $count, 'pieces' => $pieces );
}

function deprat_product_tech_packages( $data ) {
    if ( ! is_array( $data ) ) { return array(); }
    if ( array_key_exists( 'packages', $data ) && is_array( $data['packages'] ) ) {
        $rows = $data['packages'];
    } else {
        $legacy = array(
            'mode' => 'pieces',
            'count' => isset( $data['package_count'] ) ? $data['package_count'] : '',
            'type' => isset( $data['packaging'] ) ? $data['packaging'] : '',
            'pieces' => isset( $data['package_quantity'] ) ? $data['package_quantity'] : '',
        );
        $rows = deprat_packaging_clean_row( $legacy ) ? array( $legacy ) : array();
    }
    $result = array();
    foreach ( array_slice( $rows, 0, 30 ) as $row ) {
        $clean = deprat_packaging_clean_row( $row );
        if ( $clean ) { $result[] = $clean; }
    }
    return $result;
}

/**
 * Un produit est un tube si sa catégorie ou l'un de ses parents correspond
 * à « Tubes » / « Tubes acier » / « Tubes volets roulants ».
 * Les autres pièces peuvent aussi être demandées en quantité libre.
 */
function deprat_packaging_is_tube_product( $product_id ) {
    if ( ! function_exists( 'get_the_terms' ) || ! function_exists( 'get_term' ) ) { return false; }
    $terms = get_the_terms( $product_id, 'product_cat' );
    if ( ! is_array( $terms ) || ! $terms ) { return false; }
    foreach ( $terms as $term ) {
        $lineage = array( $term );
        if ( function_exists( 'get_ancestors' ) ) {
            foreach ( get_ancestors( $term->term_id, 'product_cat' ) as $ancestor_id ) {
                $ancestor = get_term( $ancestor_id, 'product_cat' );
                if ( $ancestor && ! is_wp_error( $ancestor ) ) { $lineage[] = $ancestor; }
            }
        }
        foreach ( $lineage as $category ) {
            $slug = isset( $category->slug ) ? strtolower( (string) $category->slug ) : '';
            $name = isset( $category->name ) ? strtolower( (string) $category->name ) : '';
            if ( preg_match( '/^tubes?(?:$|[-_ ])/', $slug ) ||
                preg_match( '/^tubes?(?:$|[-_ ])/', $name ) ) {
                return true;
            }
        }
    }
    return false;
}

function deprat_packaging_allow_unitary( $product_id ) {
    return ! deprat_packaging_is_tube_product( $product_id );
}

function deprat_packaging_unitary_row() {
    return array( 'mode' => 'unitary', 'title' => 'À l’unité – quantité libre' );
}

function deprat_packaging_surcharge_warning() {
    return 'Une plus-value sera appliquée au prix car le conditionnement est non standard.';
}

function deprat_packaging_plural( $type, $count ) {
    return $type . ( $count > 1 && ! preg_match( '/[sxz]$/iu', $type ) ? 's' : '' );
}

/** Texte exact d'une unité de commande, utilisé en admin, dans la boutique et le devis. */
function deprat_packaging_label( $row ) {
    if ( 'unitary' === $row['mode'] ) {
        return 'Conditionnement unitaire, quantité libre (non standard)';
    }
    if ( 'length' === $row['mode'] ) {
        return sprintf( '%s m livrés en %d longueurs de %s m', deprat_packaging_number( $row['total_m'] ),
            $row['sections'], deprat_packaging_number( $row['length_m'] ) );
    }
    $count = $row['count'];
    return sprintf( '%d %s de %d %s', $count, deprat_packaging_plural( $row['type'], $count ),
        $row['pieces'], $row['pieces'] > 1 ? 'pièces' : 'pièce' );
}

function deprat_packaging_total_text( $row, $quantity ) {
    $quantity = max( 1, absint( $quantity ) );
    if ( 'unitary' === $row['mode'] ) {
        return sprintf( '%d %s à l’unité (conditionnement non standard)', $quantity, $quantity > 1 ? 'pièces' : 'pièce' );
    }
    if ( 'length' === $row['mode'] ) {
        return sprintf( '%s m, soit %d longueurs de %s m',
            deprat_packaging_number( $quantity * $row['total_m'] ), $quantity * $row['sections'],
            deprat_packaging_number( $row['length_m'] ) );
    }
    $count = $quantity * $row['count'];
    $pieces = $count * $row['pieces'];
    return sprintf( '%d %s de %d pièces, soit %d pièces au total',
        $count, deprat_packaging_plural( $row['type'], $count ), $row['pieces'], $pieces );
}

function deprat_packaging_request_row( $product_id, $choice ) {
    if ( 'unitary' === $choice && deprat_packaging_allow_unitary( $product_id ) ) {
        return deprat_packaging_unitary_row();
    }
    $rows = deprat_product_tech_packages( deprat_product_tech_data( $product_id ) );
    if ( ! is_scalar( $choice ) || ! ctype_digit( (string) $choice ) ) { return null; }
    $index = (int) $choice;
    return isset( $rows[ $index ] ) ? $rows[ $index ] : null;
}

function deprat_packaging_validate_cart_choice( $valid, $product_id, $quantity, $variation_id = 0 ) {
    $rows = deprat_product_tech_packages( deprat_product_tech_data( $product_id ) );
    $is_tube = deprat_packaging_is_tube_product( $product_id );
    // Tubes : aucun conditionnement non standard n'est autorisé.
    if ( ! $rows && $is_tube ) {
        if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( 'Le conditionnement standard de ce tube doit être renseigné.', 'error' ); }
        return false;
    }
    $choice = isset( $_POST['deprat_package_choice'] ) && is_scalar( $_POST['deprat_package_choice'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_package_choice'] ) ) : ( $rows ? '0' : 'unitary' );
    if ( null === deprat_packaging_request_row( $product_id, $choice ) ) {
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( 'Veuillez sélectionner un conditionnement valide.', 'error' );
        }
        return false;
    }
    return $valid;
}
add_filter( 'woocommerce_add_to_cart_validation', 'deprat_packaging_validate_cart_choice', 10, 4 );

/** L'option choisie reste attachée à la ligne du panier/devis même pour le même produit. */
function deprat_packaging_cart_item_data( $cart_item_data, $product_id, $variation_id = 0, $quantity = 1 ) {
    $rows = deprat_product_tech_packages( deprat_product_tech_data( $product_id ) );
    if ( ! $rows && ! deprat_packaging_allow_unitary( $product_id ) ) { return $cart_item_data; }
    $choice = isset( $_POST['deprat_package_choice'] ) && is_scalar( $_POST['deprat_package_choice'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_package_choice'] ) ) : ( $rows ? '0' : 'unitary' );
    $row = deprat_packaging_request_row( $product_id, $choice );
    if ( $row ) {
        $cart_item_data['deprat_package'] = $row;
        $cart_item_data['deprat_package_index'] = 'unitary' === $choice ? 'unitary' : (int) $choice;
        if ( 'unitary' === $row['mode'] ) {
            // Marquer explicitement ce cas pour l'ADV / le devis.
            // Aucun montant de supplément n'est inventé ou appliqué automatiquement.
            $cart_item_data['deprat_nonstandard_packaging'] = true;
        }
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'deprat_packaging_cart_item_data', 10, 4 );

function deprat_packaging_cart_item_display( $item_data, $cart_item ) {
    if ( ! empty( $cart_item['deprat_package'] ) && is_array( $cart_item['deprat_package'] ) ) {
        $row = deprat_packaging_clean_row( $cart_item['deprat_package'] );
        if ( $row ) {
            $item_data[] = array( 'key' => 'Conditionnement', 'value' => deprat_packaging_label( $row ) );
            $item_data[] = array( 'key' => 'Total demandé', 'value' => deprat_packaging_total_text( $row, isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1 ) );
            if ( 'unitary' === $row['mode'] ) {
                $item_data[] = array( 'key' => 'Plus-value', 'value' => deprat_packaging_surcharge_warning() );
            }
        }
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'deprat_packaging_cart_item_display', 10, 2 );

/** Métadonnées du devis / commande si les demandes sont transformées en commandes. */
function deprat_packaging_order_item( $item, $cart_item_key, $values, $order ) {
    if ( empty( $values['deprat_package'] ) || ! is_array( $values['deprat_package'] ) ) { return; }
    $row = deprat_packaging_clean_row( $values['deprat_package'] );
    if ( ! $row ) { return; }
    $item->add_meta_data( 'Conditionnement', deprat_packaging_label( $row ) );
    $item->add_meta_data( 'Total demandé', deprat_packaging_total_text( $row, isset( $values['quantity'] ) ? $values['quantity'] : 1 ) );
    if ( 'unitary' === $row['mode'] ) {
        $item->add_meta_data( 'Plus-value', deprat_packaging_surcharge_warning() );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'deprat_packaging_order_item', 10, 4 );
