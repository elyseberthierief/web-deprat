<?php
/**
 * Formes standard des sections / profils de pièces DEPRAT.
 * L'icône est vectorielle et ne nécessite aucune image à importer.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_product_shape_choices() {
    return array(
        'rond' => 'Rond',
        'carre' => 'Carré',
        'hexagonal' => 'Hexagonal',
        'octogonal' => 'Octogonal',
    );
}
function deprat_product_shape_normalize( $raw ) {
    if ( ! is_scalar( $raw ) ) { return ''; }
    $text = function_exists( 'remove_accents' ) ? remove_accents( (string) $raw ) : (string) $raw;
    $text = strtolower( trim( $text ) );
    $aliases = array(
        'rond' => 'rond', 'ronde' => 'rond', 'circulaire' => 'rond', 'cercle' => 'rond',
        'carre' => 'carre', 'carree' => 'carre', 'square' => 'carre',
        'hexagonal' => 'hexagonal', 'hexagonale' => 'hexagonal', 'hexagone' => 'hexagonal',
        'octogonal' => 'octogonal', 'octogonale' => 'octogonal', 'octogone' => 'octogonal',
    );
    return isset( $aliases[ $text ] ) ? $aliases[ $text ] : '';
}
/** Compatible avec les anciennes fiches ayant seulement une ligne « Forme ». */
function deprat_product_shape( $product_id, $technical = null ) {
    $data = is_array( $technical ) ? $technical : deprat_product_tech_data( $product_id );
    $saved = isset( $data['shape'] ) ? deprat_product_shape_normalize( $data['shape'] ) : '';
    if ( $saved ) { return $saved; }
    if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
        foreach ( $data['features'] as $feature ) {
            if ( ! is_array( $feature ) ) { continue; }
            $label = isset( $feature['label'] ) ? sanitize_title( $feature['label'] ) : '';
            if ( in_array( $label, array( 'forme', 'forme-du-produit', 'profil', 'section' ), true ) ) {
                $shape = deprat_product_shape_normalize( isset( $feature['value'] ) ? $feature['value'] : '' );
                if ( $shape ) { return $shape; }
            }
        }
    }
    $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
    if ( $product ) {
        foreach ( array( 'pa_forme', 'Forme', 'forme' ) as $key ) {
            $shape = deprat_product_shape_normalize( $product->get_attribute( $key ) );
            if ( $shape ) { return $shape; }
        }
    }
    return '';
}
function deprat_product_shape_icon( $shape ) {
    $shape = deprat_product_shape_normalize( $shape );
    if ( ! $shape ) { return ''; }
    $elements = array(
        'rond' => '<circle cx="24" cy="24" r="16"/>',
        'carre' => '<rect x="8" y="8" width="32" height="32" rx="1"/>',
        'hexagonal' => '<polygon points="24,6 39.6,15 39.6,33 24,42 8.4,33 8.4,15"/>',
        'octogonal' => '<polygon points="17.4,6 30.6,6 42,17.4 42,30.6 30.6,42 17.4,42 6,30.6 6,17.4"/>',
    );
    return '<svg class="deprat-shape-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round">'
        . $elements[ $shape ] . '</svg>';
}
