<?php
/**
 * Plugin Name: DEPRAT Core
 * Description: Fonctionnalités du portail professionnel DEPRAT et de l’interface ADV.
 * Version: 0.1.77
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/product-reference.php';
require_once __DIR__ . '/includes/product-technical-data.php';
require_once __DIR__ . '/includes/product-shape.php';
require_once __DIR__ . '/includes/product-packaging.php';
require_once __DIR__ . '/includes/product-quote-pricing.php';
require_once __DIR__ . '/includes/product-autosave.php';
require_once __DIR__ . '/includes/product-single-layout.php';
require_once __DIR__ . '/includes/product-3d.php';
require_once __DIR__ . '/includes/product-client-layout.php';
require_once __DIR__ . '/includes/catalogue-sections.php';
require_once __DIR__ . '/includes/pdf-icons.php';
require_once __DIR__ . '/includes/icon-library.php';
require_once __DIR__ . '/includes/category-tree-admin.php';
require_once __DIR__ . '/includes/product-list-admin.php';
require_once __DIR__ . '/includes/catalogue-archives.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/home-hero.php';
require_once __DIR__ . '/includes/category-slides.php';
require_once __DIR__ . '/includes/admin-navigation.php';

add_action( 'plugins_loaded', static function () {
    // Conserver le contexte et l’ordre d’exécution des extraits actifs.
    $snippets = array(
        5 => 'global-05.php',
        6 => 'global-06.php',
        7 => 'global-07.php',
        8 => 'global-08.php',
        9 => 'global-09.php',
        10 => 'global-10.php',
        11 => 'global-11.php',
        12 => 'admin-12.php',
        14 => 'admin-14.php',
        15 => 'global-15.php',
    );

    foreach ( $snippets as $file ) {
        if ( 0 === strpos( $file, 'admin-' ) && ! is_admin() ) {
            continue;
        }

        // Isoler les variables locales de chaque extrait.
        ( static function ( $path ) {
            require_once $path;
        } )( __DIR__ . '/snippets/' . $file );
    }
}, 1 );

add_action( 'wp_head', static function () {
    // Conserver le chargement en ligne dans l’en-tête public.
    foreach ( array( '678-historique-client', '679-typographie' ) as $name ) {
        $css = file_get_contents( __DIR__ . '/assets/css/' . $name . '.css' );
        if ( false !== $css ) {
            echo '<style id="deprat-core-' . esc_attr( $name ) . '">' . $css . '</style>' . "\n";
        }
    }
}, 10 );


/** Identité DEPRAT dans la navigation Astra (toutes les pages). */
add_action( 'wp_enqueue_scripts', static function () {
    wp_enqueue_style(
        'deprat-navigation',
        plugins_url( 'assets/css/navigation-deprat.css', __FILE__ ),
        array(),
        '0.1.62'
    );
    wp_enqueue_script(
        'deprat-sticky-header',
        plugins_url( 'assets/js/sticky-header.js', __FILE__ ),
        array(),
        '0.1.61',
        true
    );
}, 99 );
