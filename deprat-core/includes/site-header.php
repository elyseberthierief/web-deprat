<?php
/**
 * Identité visuelle DEPRAT dans l'en-tête Astra.
 * On ne remplace que la source de l'image, jamais les menus ni leurs liens.
 */
defined( 'ABSPATH' ) || exit;

function deprat_site_header_official_logo( $html, $blog_id = 0 ) {
    if ( is_admin() || ! is_string( $html ) ) { return $html; }

    // Logo réel déjà présent dans la médiathèque du site DEPRAT.
    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
        return $html;
    }
    $relative = '/2026/11/cropped-Logo-Deprat.png';
    if ( ! is_file( $uploads['basedir'] . $relative ) ) {
        // Ne jamais montrer une image cassée quand le site est en migration.
        return $html;
    }
    $logo_url = $uploads['baseurl'] . $relative;
    return '<a class="custom-logo-link deprat-official-logo" href="' . esc_url( home_url( '/' ) ) . '" rel="home">'
        . '<img class="custom-logo" src="' . esc_url( $logo_url ) . '" alt="DEPRAT"'
        . ' loading="eager" decoding="async"></a>';
}
add_filter( 'get_custom_logo', 'deprat_site_header_official_logo', 20, 2 );
