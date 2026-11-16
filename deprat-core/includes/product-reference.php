<?php
/**
 * Libellé de la référence produit dans les interfaces WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function deprat_reference_uses_french_locale() {
    $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

    return 'fr' === $locale || 0 === strpos( $locale, 'fr_' );
}

function deprat_reference_translation_labels() {
    return array(
        'SKU' => 'Référence du produit',
        'SKU:' => 'Référence du produit :',
        'SKU: ' => 'Référence du produit : ',
        'Stock Keeping Unit' => 'Référence du produit',
        'SKU refers to a Stock-keeping unit, a unique identifier for each distinct product and service that can be purchased.' => 'Référence unique du produit ou de la variation.',
        'Invalid or duplicated SKU.' => 'La référence du produit est invalide ou déjà utilisée.',
        'SKU %s' => 'Référence du produit %s',
    );
}

function deprat_reference_translate_woocommerce( $translation, $text ) {
    if ( ! deprat_reference_uses_french_locale() ) {
        return $translation;
    }

    $labels = deprat_reference_translation_labels();
    return isset( $labels[ $text ] ) ? $labels[ $text ] : $translation;
}

add_filter( 'gettext_woocommerce', 'deprat_reference_translate_woocommerce', 10, 2 );
add_filter( 'gettext_with_context_woocommerce', 'deprat_reference_translate_woocommerce', 10, 2 );

/**
 * Conserver l’en-tête CSV existant pour les logiciels qui utilisent les exports.
 */
function deprat_reference_preserve_csv_export_header( $columns ) {
    if ( deprat_reference_uses_french_locale() && isset( $columns['sku'] ) && 'Référence du produit' === $columns['sku'] ) {
        $translations = get_translations_for_domain( 'woocommerce' );
        $columns['sku'] = $translations->translate( 'SKU' );
    }

    return $columns;
}
add_filter( 'woocommerce_product_export_product_default_columns', 'deprat_reference_preserve_csv_export_header' );

/**
 * Reconnaître également les anciens fichiers CSV lors de l’import.
 */
function deprat_reference_csv_import_aliases( $columns ) {
    if ( deprat_reference_uses_french_locale() ) {
        $columns['UGS'] = 'sku';
        $columns['SKU'] = 'sku';
    }

    return $columns;
}
add_filter( 'woocommerce_csv_product_import_mapping_default_columns', 'deprat_reference_csv_import_aliases', 20 );

/**
 * Appliquer aussi le libellé aux interfaces WooCommerce rendues en JavaScript.
 */
function deprat_enqueue_reference_labels() {
    if ( ! deprat_reference_uses_french_locale() ) {
        return;
    }

    wp_enqueue_script(
        'deprat-product-reference',
        plugins_url( 'assets/js/product-reference.js', dirname( __DIR__ ) . '/deprat-core.php' ),
        array( 'wp-hooks', 'wp-i18n' ),
        '0.1.13',
        false
    );
    wp_add_inline_script(
        'deprat-product-reference',
        'window.depratProductReferenceLabels = ' . wp_json_encode( deprat_reference_translation_labels() ) . ';',
        'before'
    );
}
add_action( 'wp_enqueue_scripts', 'deprat_enqueue_reference_labels', 1 );
add_action( 'admin_enqueue_scripts', 'deprat_enqueue_reference_labels', 1 );
