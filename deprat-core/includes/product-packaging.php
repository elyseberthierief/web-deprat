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

function deprat_packaging_plural( $type, $count ) {
    return $type . ( $count > 1 && ! preg_match( '/[sxz]$/iu', $type ) ? 's' : '' );
}

/** Texte exact d'une unité de commande, utilisé en admin, dans la boutique et le devis. */
function deprat_packaging_label( $row ) {
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
    $rows = deprat_product_tech_packages( deprat_product_tech_data( $product_id ) );
    if ( ! is_scalar( $choice ) || ! ctype_digit( (string) $choice ) ) { return null; }
    $index = (int) $choice;
    return isset( $rows[ $index ] ) ? $rows[ $index ] : null;
}

/** L'option choisie reste attachée à la ligne du panier/devis même pour le même produit. */
function deprat_packaging_cart_item_data( $cart_item_data, $product_id, $variation_id = 0, $quantity = 1 ) {
    $rows = deprat_product_tech_packages( deprat_product_tech_data( $product_id ) );
    if ( ! $rows ) { return $cart_item_data; }
    $choice = isset( $_POST['deprat_package_choice'] ) && is_scalar( $_POST['deprat_package_choice'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_package_choice'] ) ) : '0';
    $row = deprat_packaging_request_row( $product_id, $choice );
    if ( $row ) {
        $cart_item_data['deprat_package'] = $row;
        $cart_item_data['deprat_package_index'] = (int) $choice;
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
}
add_action( 'woocommerce_checkout_create_order_line_item', 'deprat_packaging_order_item', 10, 4 );
