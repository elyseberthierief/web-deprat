<?php
/**
 * Catalogue professionnel DEPRAT : pages catégories WooCommerce indépendantes.
 * Les filtres s'appliquent uniquement aux produits de la catégorie visitée.
 * La page Boutique devient l'accueil « Pièces détachées ».
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_catalog_is_archive() {
    return function_exists( 'is_shop' ) && ( is_shop() || is_product_category() );
}
function deprat_catalog_template( $template ) {
    if ( ! deprat_catalog_is_archive() ) { return $template; }
    return dirname( __DIR__ ) . '/templates/catalogue-archive.php';
}
add_filter( 'template_include', 'deprat_catalog_template', 45 );

function deprat_catalog_assets() {
    if ( ! deprat_catalog_is_archive() ) { return; }
    wp_enqueue_style( 'deprat-catalogue', plugins_url( 'assets/css/catalogue-archive.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.28' );
    wp_enqueue_script( 'deprat-catalogue', plugins_url( 'assets/js/catalogue-archive.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.28', true );
    wp_localize_script( 'deprat-catalogue', 'depratCatalogueConfig', array(
        'ajaxUrl' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
    ) );
    // Le moteur glTF est enregistré en module sur les archives contenant une 3D.
    wp_enqueue_script( 'deprat-model-viewer', 'https://ajax.googleapis.com/ajax/libs/model-viewer/4.4.0/model-viewer.min.js', array(), '4.4.0', true );
}
add_action( 'wp_enqueue_scripts', 'deprat_catalog_assets', 35 );

/** Renommer seulement le lien de la page boutique dans les menus existants. */
function deprat_catalog_relabel_menu( $items ) {
    if ( ! function_exists( 'wc_get_page_id' ) ) { return $items; }
    $shop_id = wc_get_page_id( 'shop' );
    foreach ( $items as $item ) {
        if ( 'post_type' === $item->type && 'page' === $item->object && (int) $item->object_id === $shop_id ) {
            $item->title = 'Pièces détachées';
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_objects', 'deprat_catalog_relabel_menu', 20 );

/**
 * Empêcher les visiteurs de voir Mes demandes, y compris en consultant son
 * URL directement. Les fonctions de demande AJAX/PDF vérifient déjà le compte.
 */
function deprat_catalog_is_private_requests_page() {
    if ( is_page( 'mes-demandes-devis' ) ) { return true; }
    return function_exists( 'is_account_page' ) && is_account_page()
        && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'mes-demandes' );
}
function deprat_catalog_guard_requests() {
    if ( is_user_logged_in() || ! deprat_catalog_is_private_requests_page() ) { return; }
    nocache_headers();
    $login = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
    if ( ! $login ) { $login = home_url( '/' ); }
    wp_safe_redirect( $login, 302 );
    exit;
}
add_action( 'template_redirect', 'deprat_catalog_guard_requests', 1 );
function deprat_catalog_private_menu_items( $items ) {
    if ( is_user_logged_in() ) { return $items; }
    $page = get_page_by_path( 'mes-demandes-devis', OBJECT, 'page' );
    return array_values( array_filter( $items, static function( $item ) use ( $page ) {
        if ( $page && 'post_type' === $item->type && (int) $item->object_id === (int) $page->ID ) { return false; }
        $path = (string) wp_parse_url( $item->url, PHP_URL_PATH );
        return ! preg_match( '~/(?:mes-demandes-devis|mes-demandes)/?$~i', rtrim( $path, '/' ) . '/' );
    } ) );
}
add_filter( 'wp_nav_menu_objects', 'deprat_catalog_private_menu_items', 100 );
add_filter( 'woocommerce_account_menu_items', static function( $items ) {
    if ( ! is_user_logged_in() ) { unset( $items['mes-demandes'] ); }
    return $items;
}, 100 );

/** Un groupe de liens : pas de regroupement implicite de produits ou catégories. */
function deprat_catalog_root_categories() {
    if ( ! taxonomy_exists( 'product_cat' ) ) { return array(); }
    $all = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    if ( is_wp_error( $all ) || ! is_array( $all ) ) { return array(); }
    $hub = null;
    foreach ( $all as $term ) {
        if ( in_array( $term->slug, array( 'pieces-detachees', 'piece-detachee' ), true ) ) { $hub = $term; break; }
    }
    $group = array();
    foreach ( $all as $term ) {
        if ( in_array( $term->slug, array( 'uncategorized', 'non-classe', 'non-classe-1' ), true ) ) { continue; }
        if ( $hub && (int) $term->parent === (int) $hub->term_id ) { $group[] = $term; }
        elseif ( ! $hub && 0 === (int) $term->parent ) { $group[] = $term; }
    }
    usort( $group, static function( $a, $b ) {
        $order = array( 'tubes' => 1, 'embouts' => 2, 'axes' => 3, 'supports' => 4, 'accessoires' => 5 );
        $ra = isset( $order[$a->slug] ) ? $order[$a->slug] : 99;
        $rb = isset( $order[$b->slug] ) ? $order[$b->slug] : 99;
        return $ra === $rb ? strcasecmp( $a->name, $b->name ) : $ra - $rb;
    } );
    return $group;
}

function deprat_catalog_specs( $id ) {
    $data = deprat_product_tech_data( $id );
    $features = isset( $data['features'] ) && is_array( $data['features'] ) ? $data['features'] : array();
    $fields = array( 'material' => '', 'length' => 0, 'diameter' => 0, 'thickness' => 0, 'shape' => '' );
    foreach ( $features as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $key = sanitize_title( isset( $row['label'] ) ? (string) $row['label'] : '' );
        $value = isset( $row['value'] ) && is_scalar( $row['value'] ) ? trim( (string) $row['value'] ) : '';
        if ( ! $value ) { continue; }
        if ( in_array( $key, array( 'matiere', 'materiau', 'matieres' ), true ) ) { $fields['material'] = $value; }
        elseif ( in_array( $key, array( 'forme', 'profil', 'type-de-tube' ), true ) ) { $fields['shape'] = $value; }
        elseif ( in_array( $key, array( 'longueur', 'longueur-mm', 'longueur-m' ), true ) ) {
            $fields['length'] = deprat_catalog_number_mm( $value, $key );
        } elseif ( in_array( $key, array( 'diametre', 'diametre-mm', 'diametre-exterieur' ), true ) ) {
            $fields['diameter'] = deprat_catalog_number_mm( $value, $key );
        } elseif ( in_array( $key, array( 'epaisseur', 'epaisseur-mm' ), true ) ) {
            $fields['thickness'] = deprat_catalog_number_mm( $value, $key );
        }
    }
    $product = wc_get_product( $id );
    if ( $product ) {
        if ( '' === $fields['material'] ) {
            foreach ( array( 'pa_matiere', 'Matière', 'matiere', 'pa_materiau' ) as $key ) {
                $value = $product->get_attribute( $key );
                if ( $value ) { $fields['material'] = wp_strip_all_tags( $value ); break; }
            }
        }
        $fields['in_stock'] = $product->is_in_stock();
    } else { $fields['in_stock'] = false; }
    $fields['has3d'] = (bool) deprat_product_3d_model( $id );
    return $fields;
}
function deprat_catalog_number_mm( $value, $label = '' ) {
    $value = trim( (string) $value );
    // Les valeurs « 6/10 » et les plages restent non filtrables sans unité claire.
    if ( preg_match( '~/|[–—]|\\b(?:entre|à|jusqu)\\b~iu', $value ) ) { return 0; }
    if ( ! preg_match( '/^(\\d+(?:[,.]\\d{1,3})?)\\s*(mm|m)?$/iu', $value, $matches ) ) { return 0; }
    $number = (float) str_replace( ',', '.', $matches[1] );
    $unit = isset( $matches[2] ) ? strtolower( $matches[2] ) : '';
    if ( 'm' === $unit || ( ! $unit && 'longueur-m' === $label ) ) { $number *= 1000; }
    return $number > 0 && $number < 100000000 ? $number : 0;
}

/** Facettes et filtrage issus des vrais produits de la catégorie courante. */
function deprat_catalog_category_index( $term ) {
    $key = 'deprat_catalog_ix_' . (int) $term->term_id;
    $cached = get_transient( $key );
    if ( is_array( $cached ) ) { return $cached; }
    $ids = get_posts( array(
        'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids',
        'posts_per_page' => -1, 'no_found_rows' => true,
        'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => (int) $term->term_id, 'include_children' => true ) ),
    ) );
    $index = array();
    foreach ( $ids as $id ) { $index[(int) $id] = deprat_catalog_specs( (int) $id ); }
    set_transient( $key, $index, 5 * MINUTE_IN_SECONDS );
    return $index;
}
function deprat_catalog_clear_cache( $post_id = 0 ) {
    // Lorsque le catalogue est modifié, la durée de vie courte des facettes évite
    // de devoir connaître toutes les anciennes catégories du produit.
    if ( ! $post_id ) { return; }
    $categories = get_the_terms( $post_id, 'product_cat' );
    if ( ! is_array( $categories ) ) { return; }
    foreach ( $categories as $term ) {
        delete_transient( 'deprat_catalog_ix_' . $term->term_id );
        foreach ( get_ancestors( $term->term_id, 'product_cat' ) as $ancestor ) {
            delete_transient( 'deprat_catalog_ix_' . $ancestor );
        }
    }
}
add_action( 'save_post_product', 'deprat_catalog_clear_cache', 100 );

function deprat_catalog_filters() {
    $fields = array( 'material', 'shape', 'length_min', 'length_max',
        'diameter_min', 'diameter_max', 'thickness_min', 'thickness_max', 'stock', 'view3d' );
    $result = array();
    foreach ( $fields as $field ) {
        $key = 'deprat_' . $field;
        $v = isset( $_GET[$key] ) && is_scalar( $_GET[$key] )
            ? sanitize_text_field( wp_unslash( $_GET[$key] ) ) : '';
        if ( in_array( $field, array( 'stock', 'view3d' ), true ) ) { $result[$field] = '1' === $v; }
        elseif ( preg_match( '/_(?:min|max)$/', $field ) ) {
            $result[$field] = ( preg_match( '/^\\d{1,7}(?:[,.]\\d{1,2})?$/', $v ) ) ? (float) str_replace( ',', '.', $v ) : 0;
        } else { $result[$field] = sanitize_title( $v ); }
    }
    return $result;
}
function deprat_catalog_matches( $spec, $filters ) {
    if ( $filters['material'] && $filters['material'] !== sanitize_title( $spec['material'] ) ) { return false; }
    if ( $filters['shape'] && $filters['shape'] !== sanitize_title( $spec['shape'] ) ) { return false; }
    foreach ( array( 'length', 'diameter', 'thickness' ) as $dimension ) {
        if ( $filters[$dimension.'_min'] > 0 && ( ! $spec[$dimension] || $spec[$dimension] < $filters[$dimension.'_min'] ) ) { return false; }
        if ( $filters[$dimension.'_max'] > 0 && ( ! $spec[$dimension] || $spec[$dimension] > $filters[$dimension.'_max'] ) ) { return false; }
    }
    if ( $filters['stock'] && ! $spec['in_stock'] ) { return false; }
    if ( $filters['view3d'] && ! $spec['has3d'] ) { return false; }
    return true;
}
function deprat_catalog_query( $term, $index, $filters ) {
    $ids = array();
    foreach ( $index as $id => $spec ) {
        if ( deprat_catalog_matches( $spec, $filters ) ) { $ids[] = (int) $id; }
    }
    $page = isset( $_GET['deprat_page'] ) ? max( 1, min( 10000, absint( $_GET['deprat_page'] ) ) ) : 1;
    $sort = isset( $_GET['deprat_sort'] ) && is_scalar( $_GET['deprat_sort'] )
        ? sanitize_key( wp_unslash( $_GET['deprat_sort'] ) ) : 'default';
    $args = array(
        'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 12,
        'paged' => $page, 'post__in' => $ids ? $ids : array( 0 ),
        'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => $term->term_id,
            'include_children' => true ) ),
        'orderby' => 'title', 'order' => 'ASC',
    );
    if ( 'recent' === $sort ) { $args['orderby'] = 'date'; $args['order'] = 'DESC'; }
    elseif ( 'name_desc' === $sort ) { $args['orderby'] = 'title'; $args['order'] = 'DESC'; }
    return new WP_Query( $args );
}
