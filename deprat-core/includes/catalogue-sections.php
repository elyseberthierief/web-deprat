<?php
/**
 * DEPRAT — rubriques VISUELLES du catalogue.
 * Aucune création / modification de product_cat : seuls les termes existants
 * sont lus, leurs enfants suivent la hiérarchie WooCommerce réelle.
 */
defined( 'ABSPATH' ) || exit;

function deprat_catalog_section_definitions() {
    return array(
        'tubes'     => 'Tubes acier & accessoires',
        'liaison'   => 'Liaison tube-tablier',
        'treuil'    => 'Manœuvre par treuil',
        'sangle'    => 'Manœuvre par sangle',
        'tirage'    => 'Tirage direct et allègement',
        'coulisses' => 'Coulisses – lames – lames finales',
        'bras'      => 'Bras de projection',
        'jalousie'  => 'Persiennes et jalousies',
        'garage'    => 'Portes de garage',
        'autres'    => 'Autres composants',
    );
}

/** Affectation automatique initiale ; l'ADV peut la remplacer par catégorie. */
function deprat_catalog_section_guess( $term ) {
    $name = sanitize_title( $term->name . ' ' . $term->slug );
    // La priorité évite qu'un nom composite tombe dans la mauvaise rubrique.
    $patterns = array(
        'garage'    => '/garage|rideau-metallique|grille-roulante/',
        'jalousie'  => '/jalousie|persienne/',
        'bras'      => '/bras-de-projection|projection/',
        'coulisses' => '/coulisse|lame|serrure/',
        'tirage'    => '/tirage-direct|compensation|allegement|ressort/',
        'sangle'    => '/sangle|enrouleur|poulie/',
        'treuil'    => '/treuil|manoeuvre-par-treuil|manoeuvres-par-treuil|passe-caisson|manivelle/',
        'liaison'   => '/attache|verrou|liaison|tablier|fixation-atr/',
        'tubes'     => '/tube|embout|flasque|bague|supports?-d-arbres?|supports?-arbres?/',
    );
    foreach ( $patterns as $section => $regex ) {
        if ( preg_match( $regex, $name ) ) { return $section; }
    }
    return 'autres';
}

function deprat_catalog_section_for_term( $term ) {
    $sections = deprat_catalog_section_definitions();
    $override = get_term_meta( (int) $term->term_id, '_deprat_catalog_section', true );
    if ( is_string( $override ) && '' !== $override && isset( $sections[ $override ] ) ) {
        return $override;
    }
    return deprat_catalog_section_guess( $term );
}

/** Ne regroupe que les catégories effectivement présentes en base. */
function deprat_catalog_grouped_roots() {
    $groups = array_fill_keys( array_keys( deprat_catalog_section_definitions() ), array() );
    foreach ( deprat_catalog_root_categories() as $term ) {
        $key = deprat_catalog_section_for_term( $term );
        $groups[ $key ][] = $term;
    }
    return array_filter( $groups, static function ( $terms ) { return ! empty( $terms ); } );
}

/** Icônes de section : tracés intégrés, pas d'images ni d'assets externes. */
function deprat_catalog_section_icon( $key ) {
    $paths = array(
        'tubes'     => '<ellipse cx="7" cy="16" rx="3" ry="4" transform="rotate(-42 7 16)"/><path d="M5 12.5 17.5 3a3.5 3.5 0 0 1 4.5 5.4L9.5 20"/>',
        'liaison'   => '<path d="m10 13 4-4"/><path d="M8.1 16.2 5.9 18.4a4 4 0 0 1-5.7-5.7l4.6-4.6a4 4 0 0 1 5.7 0"/><path d="m15.9 7.8 2.2-2.2a4 4 0 0 1 5.7 5.7l-4.6 4.6a4 4 0 0 1-5.7 0"/>',
        'treuil'    => '<rect x="2" y="17" width="6" height="3" rx="1"/><rect x="16" y="4" width="6" height="4" rx="1"/><path d="M8 18h5v-12h3M12.5 18v2"/>',
        'sangle'    => '<circle cx="10" cy="12" r="7"/><circle cx="10" cy="12" r="3.5"/><path d="M17 12v9h3v-8"/><path d="M10 8.5v7"/>',
        'tirage'    => '<path d="M12 2v3M12 19v3M10 5c-8 0-8 3 0 3h4c8 0 8 3 0 3h-4c-8 0-8 3 0 3h4c8 0 8 3 0 3H12"/>',
        'coulisses' => '<path d="m3 7 16-4 2 3-16 4zM3 13l16-4 2 3-16 4zM3 19l16-4 2 3-16 4z"/>',
        'bras'      => '<path d="M3 3h3v18H3zM6 8l13-5 2 4-13 6-2-1M18 4l3 3"/>',
        'jalousie'  => '<rect x="3" y="3" width="18" height="18" rx="1"/><path d="m5 8 14-2M5 12l14-2M5 16l14-2M5 20l14-2"/>',
        'garage'    => '<path d="M2 8V4h20v4M4 8v13h16V8zM4 12h16M4 16h16M4 20h16"/>',
        'autres'    => '<path d="m3 7 9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10"/>',
    );
    $body = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['autres'];
    return '<svg class="deprat-section-icon" aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

/**
 * Un lien navigue vers une VRAIE catégorie ; un bouton séparé déplie ses
 * VRAIES sous-catégories (plusieurs niveaux). Les titres de section ne lient rien.
 */
function deprat_catalog_render_section_terms( $terms, $prefix, $depth = 0 ) {
    if ( ! $terms || $depth > 20 ) { return; }
    echo '<ul class="deprat-section-categories deprat-section-depth-' . esc_attr( $depth ) . '">';
    foreach ( $terms as $term ) {
        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) { continue; }
        $children = deprat_category_tree_front_children( (int) $term->term_id );
        $has_children = ! empty( $children ) && $depth < 20;
        $id = $prefix . '-' . (int) $term->term_id;
        echo '<li class="deprat-section-term' . ( $has_children ? ' has-children' : '' ) . '">';
        echo '<div class="deprat-section-term-row">';
        echo '<a class="deprat-section-term-link" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
        if ( $has_children ) {
            echo '<button type="button" class="deprat-section-toggle" aria-controls="' . esc_attr( $id ) . '" aria-expanded="false" aria-label="' . esc_attr( 'Déplier les sous-catégories de ' . $term->name ) . '" data-deprat-section-toggle>';
            echo '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>';
        } else {
            echo '<span class="deprat-section-arrow" aria-hidden="true">›</span>';
        }
        echo '</div>';
        if ( $has_children ) {
            echo '<div id="' . esc_attr( $id ) . '" class="deprat-section-children" hidden>';
            deprat_catalog_render_section_terms( $children, $prefix, $depth + 1 );
            echo '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

function deprat_catalog_render_sections( $context = 'mega' ) {
    $groups = deprat_catalog_grouped_roots();
    if ( ! $groups ) { return; }
    $prefix = wp_unique_id( 'deprat-section-' );
    echo '<div class="deprat-catalog-sections deprat-catalog-sections--' . esc_attr( $context ) . '">';
    foreach ( $groups as $key => $terms ) {
        $titles = deprat_catalog_section_definitions();
        echo '<section class="deprat-section" aria-label="' . esc_attr( $titles[ $key ] ) . '">';
        echo '<div class="deprat-section-heading">';
        echo '<span class="deprat-section-icon-wrap">' . deprat_catalog_section_icon( $key ) . '</span>';
        echo '<h2>' . esc_html( $titles[ $key ] ) . '</h2>';
        echo '</div>';
        deprat_catalog_render_section_terms( $terms, $prefix );
        echo '</section>';
    }
    echo '</div>';
}
