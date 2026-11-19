<?php
/**
 * Test d'intégration du diaporama DEPRAT sur une installation WordPress réelle.
 * À lancer avec WP-CLI : wp eval-file tests/home-hero-integration.php.
 */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
if ( ! function_exists( 'deprat_home_get_slides' ) ) { throw new RuntimeException( 'Module diaporama indisponible.' ); }

$created = array();
try {
    $first = wp_insert_post( array(
        'post_type' => 'deprat_slide', 'post_status' => 'publish',
        'post_title' => 'Test slide DEPRAT premier', 'menu_order' => 1,
    ) );
    $second = wp_insert_post( array(
        'post_type' => 'deprat_slide', 'post_status' => 'publish',
        'post_title' => 'Test slide DEPRAT second', 'menu_order' => 2,
    ) );
    if ( is_wp_error( $first ) || is_wp_error( $second ) || ! $first || ! $second ) {
        throw new RuntimeException( 'Création des deux slides impossible.' );
    }
    $created = array( $first, $second );
    update_post_meta( $first, '_deprat_home_slide_description', 'Description test.' );
    update_post_meta( $first, '_deprat_home_slide_button_text', 'Découvrir' );
    update_post_meta( $first, '_deprat_home_slide_button_url', '/boutique/' );

    // Un slide peut garder son nom dans l'administration sans avoir de titre public.
    update_post_meta( $second, '_deprat_home_slide_headline', '' );
    $slides = deprat_home_get_slides();
    if ( count( $slides ) !== 2 || (int) $slides[0]['id'] !== (int) $first ) {
        throw new RuntimeException( 'Les deux slides publiés doivent apparaître dans le bon ordre.' );
    }
    $render = deprat_home_hero_html();
    if ( strpos( $render, 'is-image-only' ) === false || '' !== $slides[1]['title'] ) {
        throw new RuntimeException( 'Un slide sans texte ni bouton doit être affiché sans superposition.' );
    }
    ob_start();
    deprat_home_slide_image_html( get_post( $first ) );
    $image_picker = ob_get_clean();
    if ( strpos( $image_picker, 'data-deprat-image-select' ) === false
        || strpos( $image_picker, 'data-deprat-image-id' ) === false ) {
        throw new RuntimeException( 'Le sélecteur d’image devrait toujours être disponible.' );
    }
    if ( substr_count( $render, 'data-deprat-home-slide ' ) !== 2
        || strpos( $render, 'data-deprat-home-next' ) === false
        || strpos( $render, '/boutique/' ) === false ) {
        throw new RuntimeException( 'Le rendu multi-slide ou le bouton sont incorrects.' );
    }

    // L’ordre des slides est modifiable indépendamment de leur date de création.
    wp_update_post( array( 'ID' => $second, 'menu_order' => 0 ) );
    $ordered = deprat_home_get_slides();
    if ( count( $ordered ) !== 2 || (int) $ordered[0]['id'] !== (int) $second ) {
        throw new RuntimeException( 'La modification du rang du slide doit être prise en compte.' );
    }
    wp_update_post( array( 'ID' => $second, 'menu_order' => 2 ) );

    update_post_meta( $first, '_deprat_home_slide_hidden', '1' );
    if ( ! deprat_home_slide_is_hidden( $first ) ) {
        throw new RuntimeException( 'Le slide masqué devrait être signalé comme masqué.' );
    }
    $slides = deprat_home_get_slides();
    if ( count( $slides ) !== 1 || (int) $slides[0]['id'] !== (int) $second ) {
        throw new RuntimeException( 'Le slide masqué ne doit plus apparaître sur la page.' );
    }
    $render = deprat_home_hero_html();
    if ( strpos( $render, 'data-deprat-home-next' ) !== false ) {
        throw new RuntimeException( 'Une seule slide visible ne doit pas afficher de flèches.' );
    }

    update_post_meta( $second, '_deprat_home_slide_hidden', '1' );
    if ( count( deprat_home_get_slides() ) !== 0 ) {
        throw new RuntimeException( 'Tous les slides masqués : aucun ne doit être affiché.' );
    }
    $render = deprat_home_hero_html();
    if ( strpos( $render, 'data-deprat-home-slider' ) !== false
        || strpos( $render, 'deprat-home-benefits' ) === false ) {
        throw new RuntimeException( 'Quand tous les slides sont masqués, la bande des avantages doit rester.' );
    }

    update_post_meta( $first, '_deprat_home_slide_hidden', '0' );
    if ( count( deprat_home_get_slides() ) !== 1 ) {
        throw new RuntimeException( 'Réafficher un slide doit fonctionner.' );
    }
    echo "OK : slides multiples, visuel seul, sélecteur image, ordre, masquage, réactivation.\n";
} finally {
    foreach ( $created as $id ) { wp_delete_post( $id, true ); }
}
