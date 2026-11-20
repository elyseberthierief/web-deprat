<?php
/**
 * Diaporamas éditoriaux propres à chaque catégorie WooCommerce.
 * Aucun slide d'une famille n'est hérité par une autre famille.
 */
defined( 'ABSPATH' ) || exit;

function deprat_category_slide_register() {
    register_post_type( 'deprat_cat_slide', array(
        'labels' => array(
            'name' => 'Slides des catégories',
            'singular_name' => 'Slide de catégorie',
            'add_new_item' => 'Créer un slide de catégorie',
            'edit_item' => 'Modifier le slide de catégorie',
            'all_items' => 'Tous les slides de catégories',
            'menu_name' => 'Slides catégories',
        ),
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => 'deprat-shop-settings',
        'supports' => array( 'title', 'page-attributes' ),
        'map_meta_cap' => true,
    ) );
}
add_action( 'init', 'deprat_category_slide_register' );

function deprat_category_slide_fields() {
    add_meta_box( 'deprat-cat-slide-settings', 'Catégorie et contenu du slide', 'deprat_category_slide_fields_html', 'deprat_cat_slide', 'normal', 'high' );
    add_meta_box( 'deprat-cat-slide-image', 'Image technique du slide', 'deprat_category_slide_image_html', 'deprat_cat_slide', 'normal', 'high' );
}
add_action( 'add_meta_boxes_deprat_cat_slide', 'deprat_category_slide_fields' );

function deprat_category_slide_fields_html( $post ) {
    wp_nonce_field( 'deprat_category_slide_save', 'deprat_category_slide_nonce' );
    $term_id = (int) get_post_meta( $post->ID, '_deprat_category_slide_term', true );
    $description = (string) get_post_meta( $post->ID, '_deprat_category_slide_description', true );
    $heading = (string) get_post_meta( $post->ID, '_deprat_category_slide_heading', true );
    $hidden = '1' === (string) get_post_meta( $post->ID, '_deprat_category_slide_hidden', true );
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
    echo '<p><label for="deprat-cat-slide-term"><strong>Catégorie concernée (obligatoire)</strong></label></p>';
    echo '<select class="widefat" required name="deprat_cat_slide_term" id="deprat-cat-slide-term">';
    echo '<option value="">Choisir une catégorie précise…</option>';
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
            echo '<option value="' . (int) $term->term_id . '"' . selected( $term_id, $term->term_id, false ) . '>'
                . esc_html( str_repeat( '— ', count( $ancestors ) ) . $term->name ) . '</option>';
        }
    }
    echo '</select><p class="description">Un slide ne s’affiche que dans cette catégorie, jamais dans sa catégorie parente ou une autre sous-catégorie.</p>';
    echo '<p><label for="deprat-cat-slide-heading"><strong>Titre visible (facultatif)</strong></label></p>';
    echo '<input class="widefat" type="text" maxlength="140" id="deprat-cat-slide-heading" name="deprat_cat_slide_heading" value="' . esc_attr( $heading ) . '" placeholder="Par défaut : nom de la catégorie">';
    echo '<p><label for="deprat-cat-slide-description"><strong>Description (facultative)</strong></label></p>';
    echo '<textarea class="widefat" rows="4" id="deprat-cat-slide-description" name="deprat_cat_slide_description" placeholder="Par défaut : description de la catégorie">' . esc_textarea( $description ) . '</textarea>';
    echo '<p><label for="deprat-cat-slide-order"><strong>Ordre d’affichage</strong></label></p>';
    echo '<input type="number" min="0" max="9999" id="deprat-cat-slide-order" name="deprat_cat_slide_order" value="' . (int) $post->menu_order . '">';
    echo '<p><label><input type="checkbox" name="deprat_cat_slide_hidden" value="1"' . checked( $hidden, true, false ) . '> Masquer ce slide temporairement</label></p>';
}
function deprat_category_slide_image_html( $post ) {
    $image_id = (int) get_post_meta( $post->ID, '_deprat_category_slide_image', true );
    $url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
    echo '<div data-deprat-home-image-picker>';
    echo '<input type="hidden" data-deprat-image-id name="deprat_cat_slide_image" value="' . (int) $image_id . '">';
    echo '<div data-deprat-image-preview>';
    if ( $url ) {
        echo '<img src="' . esc_url( $url ) . '" alt="Aperçu" style="display:block;width:100%;max-height:230px;object-fit:contain">';
    } else {
        echo '<p class="description">Aucune image : le bandeau dégradé reste disponible.</p>';
    }
    echo '</div><p><button type="button" class="button button-primary" data-deprat-image-select>Choisir ou importer une image</button> ';
    echo '<button type="button" class="button" data-deprat-image-remove' . ( $image_id ? '' : ' hidden' ) . '>Retirer l’image</button></p>';
    echo '<p class="description">Pour le rendu de la maquette, utilisez de préférence une illustration technique détourée sur fond transparent (PNG ou WebP), à droite du texte.</p></div>';
}
function deprat_category_slide_admin_assets() {
    $screen = get_current_screen();
    if ( ! $screen || 'deprat_cat_slide' !== $screen->post_type ) { return; }
    wp_enqueue_media();
    wp_enqueue_script( 'deprat-category-slide-picker', plugins_url( 'assets/js/home-slide-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'media-views' ), '0.1.54', true );
}
add_action( 'admin_enqueue_scripts', 'deprat_category_slide_admin_assets' );

function deprat_category_slide_save( $id ) {
    if ( empty( $_POST['deprat_category_slide_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['deprat_category_slide_nonce'] ) ), 'deprat_category_slide_save' )
        || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }

    $term_id = isset( $_POST['deprat_cat_slide_term'] ) ? absint( $_POST['deprat_cat_slide_term'] ) : 0;
    if ( $term_id && term_exists( $term_id, 'product_cat' ) ) {
        update_post_meta( $id, '_deprat_category_slide_term', $term_id );
    } else {
        delete_post_meta( $id, '_deprat_category_slide_term' );
    }
    $image_id = isset( $_POST['deprat_cat_slide_image'] ) ? absint( $_POST['deprat_cat_slide_image'] ) : 0;
    if ( $image_id && ( ! current_user_can( 'upload_files' ) || ! wp_attachment_is_image( $image_id ) ) ) { $image_id = 0; }
    update_post_meta( $id, '_deprat_category_slide_image', $image_id );
    $heading = isset( $_POST['deprat_cat_slide_heading'] ) && is_scalar( $_POST['deprat_cat_slide_heading'] )
        ? sanitize_text_field( wp_unslash( $_POST['deprat_cat_slide_heading'] ) ) : '';
    $description = isset( $_POST['deprat_cat_slide_description'] ) && is_scalar( $_POST['deprat_cat_slide_description'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['deprat_cat_slide_description'] ) ) : '';
    update_post_meta( $id, '_deprat_category_slide_heading', $heading );
    update_post_meta( $id, '_deprat_category_slide_description', $description );
    update_post_meta( $id, '_deprat_category_slide_hidden', isset( $_POST['deprat_cat_slide_hidden'] ) ? '1' : '0' );
    $order = isset( $_POST['deprat_cat_slide_order'] ) ? min( 9999, max( 0, (int) $_POST['deprat_cat_slide_order'] ) ) : 0;
    if ( $order !== (int) get_post_field( 'menu_order', $id ) ) {
        remove_action( 'save_post_deprat_cat_slide', 'deprat_category_slide_save' );
        wp_update_post( array( 'ID' => $id, 'menu_order' => $order ) );
        add_action( 'save_post_deprat_cat_slide', 'deprat_category_slide_save' );
    }
}
add_action( 'save_post_deprat_cat_slide', 'deprat_category_slide_save' );

function deprat_category_slide_admin_list() {
    $slides = get_posts( array( 'post_type' => 'deprat_cat_slide', 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'numberposts' => 100, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
    echo '<div class="deprat-shop-slide-list"><h2>Slides des catégories</h2>';
    echo '<p>Créez un bandeau pour <strong>une seule catégorie</strong>. Son titre, sa description et sa photo sont modifiables. Sans slide actif, la catégorie utilise automatiquement son nom et sa description WooCommerce sur un dégradé bleu.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=deprat_cat_slide' ) ) . '">+ Créer un slide de catégorie</a></p>';
    if ( ! $slides ) {
        echo '<p>Aucun slide personnalisé pour le moment.</p></div>'; return;
    }
    echo '<table class="widefat striped"><thead><tr><th>Slide</th><th>Catégorie</th><th>Ordre</th><th>Affichage</th><th>Action</th></tr></thead><tbody>';
    foreach ( $slides as $slide ) {
        $term = get_term( (int) get_post_meta( $slide->ID, '_deprat_category_slide_term', true ), 'product_cat' );
        $label = $term && ! is_wp_error( $term ) ? $term->name : 'À sélectionner';
        $visible = 'publish' === $slide->post_status && '1' !== get_post_meta( $slide->ID, '_deprat_category_slide_hidden', true );
        echo '<tr><td>' . esc_html( $slide->post_title ) . '</td><td>' . esc_html( $label ) . '</td><td>' . (int) $slide->menu_order . '</td><td>' . ( $visible ? 'Visible' : 'Masqué / brouillon' ) . '</td><td><a href="' . esc_url( get_edit_post_link( $slide->ID ) ) . '">Modifier</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

/** Un terme précis => uniquement ses propres slides. */
function deprat_category_slides_for_term( $term_id ) {
    if ( ! $term_id ) { return array(); }
    $posts = get_posts( array(
        'post_type' => 'deprat_cat_slide',
        'post_status' => 'publish',
        'numberposts' => 30,
        'meta_key' => '_deprat_category_slide_term',
        'meta_value' => (int) $term_id,
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'suppress_filters' => true,
    ) );
    $slides = array();
    foreach ( $posts as $post ) {
        if ( '1' === (string) get_post_meta( $post->ID, '_deprat_category_slide_hidden', true ) ) { continue; }
        $slides[] = array(
            'heading' => trim( (string) get_post_meta( $post->ID, '_deprat_category_slide_heading', true ) ),
            'description' => trim( (string) get_post_meta( $post->ID, '_deprat_category_slide_description', true ) ),
            'image_id' => (int) get_post_meta( $post->ID, '_deprat_category_slide_image', true ),
        );
    }
    return $slides;
}

/** Fil d'Ariane de la vraie hiérarchie WooCommerce et de la section éditoriale. */
function deprat_category_slide_breadcrumb( $term ) {
    $shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
    echo '<nav class="deprat-cat-hero-breadcrumb" aria-label="Fil d’Ariane des composants">';
    echo '<a href="' . esc_url( $shop ) . '">Nos composants</a>';
    $ancestors = array_reverse( array_map( 'intval', get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) ) );
    $root_id = $ancestors ? $ancestors[0] : (int) $term->term_id;
    $root = get_term( $root_id, 'product_cat' );
    if ( $root && ! is_wp_error( $root ) && function_exists( 'deprat_catalog_section_for_term' ) ) {
        $key = deprat_catalog_section_for_term( $root );
        $sections = deprat_catalog_section_definitions();
        if ( isset( $sections[ $key ] ) ) {
            echo '<span aria-hidden="true">›</span><span>' . esc_html( $sections[ $key ] ) . '</span>';
        }
    }
    foreach ( $ancestors as $id ) {
        $parent = get_term( $id, 'product_cat' );
        if ( ! $parent || is_wp_error( $parent ) ) { continue; }
        $url = get_term_link( $parent );
        if ( is_wp_error( $url ) ) { continue; }
        echo '<span aria-hidden="true">›</span><a href="' . esc_url( $url ) . '">' . esc_html( $parent->name ) . '</a>';
    }
    echo '<span aria-hidden="true">›</span><strong aria-current="page">' . esc_html( $term->name ) . '</strong></nav>';
}

/** Le bandeau de catégorie est en pleine largeur et n'affecte jamais la boutique globale. */
function deprat_category_slide_render( $term ) {
    if ( ! $term || is_wp_error( $term ) || ! isset( $term->term_id ) ) { return; }
    $slides = deprat_category_slides_for_term( (int) $term->term_id );
    if ( ! $slides ) { $slides[] = array( 'heading' => '', 'description' => '', 'image_id' => 0 ); }
    $count = count( $slides );
    echo '<section class="deprat-cat-hero" aria-label="Présentation de la catégorie ' . esc_attr( $term->name ) . '" data-deprat-category-slider>';
    echo '<div class="deprat-cat-hero-slides">';
    foreach ( $slides as $i => $slide ) {
        $heading = $slide['heading'] ?: $term->name;
        $description = $slide['description'] ?: wp_strip_all_tags( (string) $term->description );
        $image_id = $slide['image_id'];
        if ( ! $image_id && 1 === $count ) {
            $custom = (int) get_term_meta( $term->term_id, '_deprat_category_image_id', true );
            $image_id = $custom ?: (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
        }
        echo '<article class="deprat-cat-hero-slide' . ( 0 === $i ? ' is-active' : '' ) . '" data-deprat-category-slide aria-hidden="' . ( 0 === $i ? 'false' : 'true' ) . '">';
        echo '<div class="deprat-cat-hero-inner"><div class="deprat-cat-hero-copy">';
        deprat_category_slide_breadcrumb( $term );
        echo '<span class="deprat-cat-hero-gold-rule" aria-hidden="true"></span>';
        if ( 0 === $i ) { echo '<h1>' . esc_html( $heading ) . '</h1>'; }
        else { echo '<h2>' . esc_html( $heading ) . '</h2>'; }
        if ( $description ) { echo '<p>' . nl2br( esc_html( $description ) ) . '</p>'; }
        echo '</div>';
        if ( $image_id && wp_attachment_is_image( $image_id ) ) {
            echo '<div class="deprat-cat-hero-image">';
            echo wp_get_attachment_image( $image_id, 'large', false, array( 'alt' => '', 'loading' => 0 === $i ? 'eager' : 'lazy', 'decoding' => 'async' ) );
            echo '</div>';
        }
        echo '</div></article>';
    }
    echo '</div>';
    if ( $count > 1 ) {
        echo '<button class="deprat-cat-hero-arrow deprat-cat-hero-prev" type="button" data-deprat-category-prev aria-label="Slide précédent">‹</button>';
        echo '<button class="deprat-cat-hero-arrow deprat-cat-hero-next" type="button" data-deprat-category-next aria-label="Slide suivant">›</button>';
        echo '<div class="deprat-cat-hero-dots" aria-label="Choisir un slide">';
        for ( $i = 0; $i < $count; $i++ ) {
            echo '<button type="button" data-deprat-category-dot="' . (int) $i . '" aria-label="Voir le slide ' . ( $i + 1 ) . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '"></button>';
        }
        echo '</div>';
    }
    echo '</section>';
}
