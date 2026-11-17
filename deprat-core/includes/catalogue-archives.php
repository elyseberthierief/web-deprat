<?php
/**
 * Catalogue professionnel DEPRAT : pages catégories WooCommerce indépendantes.
 * Les filtres s'appliquent uniquement aux produits de la catégorie visitée.
 * La page Boutique devient l'accueil « Pièces détachées ».
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** URL de la liste complète, distincte de la page d'entrée par familles. */
function deprat_catalog_all_products_url() {
    // Une archive produits réelle est reconnue par is_shop(), même si
    // la page Boutique de WordPress n'est pas encore configurée.
    $url = get_post_type_archive_link( 'product' );
    if ( ! $url ) { $url = wc_get_page_permalink( 'shop' ); }
    return add_query_arg( 'deprat_all', '1', $url );
}
/**
 * WordPress sans permaliens personnalisés : conserver le paramètre de routage
 * du magasin (page_id, post_type, pagename) lors des formulaires GET.
 */
function deprat_catalog_shop_route_params() {
    $query_string = wp_parse_url( deprat_catalog_all_products_url(), PHP_URL_QUERY );
    if ( ! is_string( $query_string ) || '' === $query_string ) { return array(); }
    parse_str( $query_string, $parts );
    $allowed = array( 'page_id', 'post_type', 'pagename' );
    $params = array();
    foreach ( $allowed as $key ) {
        if ( isset( $parts[ $key ] ) && is_scalar( $parts[ $key ] ) ) {
            $params[ $key ] = sanitize_text_field( (string) $parts[ $key ] );
        }
    }
    return $params;
}
function deprat_catalog_show_all_products() {
    return function_exists( 'is_shop' ) && is_shop()
        && isset( $_GET['deprat_all'] ) && is_scalar( $_GET['deprat_all'] )
        && '1' === (string) wp_unslash( $_GET['deprat_all'] );
}
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
    wp_enqueue_style( 'deprat-catalogue', plugins_url( 'assets/css/catalogue-archive.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.31' );
    wp_enqueue_script( 'deprat-catalogue', plugins_url( 'assets/js/catalogue-archive.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.31', true );
    wp_localize_script( 'deprat-catalogue', 'depratCatalogueConfig', array(
        'ajaxUrl' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
        'contoursUrl' => plugins_url( 'assets/js/product-3d-contours.js', dirname( __DIR__ ) . '/deprat-core.php' ),
    ) );
    // Le moteur glTF est enregistré en module sur les archives contenant une 3D.
    wp_enqueue_script( 'deprat-model-viewer', 'https://ajax.googleapis.com/ajax/libs/model-viewer/4.4.0/model-viewer.min.js', array(), '4.4.0', true );
}
add_action( 'wp_enqueue_scripts', 'deprat_catalog_assets', 35 );

/**
 * Reconnaître la page boutique WordPress même si le menu utilise un lien personnalisé.
 * Ne remplace pas les menus du thème Astra.
 */
function deprat_catalog_is_shop_menu_link( $item, $shop_id ) {
    if ( ! $item || $shop_id < 1 ) { return false; }
    if ( 'post_type' === $item->type && 'page' === $item->object && (int) $item->object_id === (int) $shop_id ) {
        return true;
    }
    if ( 'custom' !== $item->type || empty( $item->url ) ) { return false; }
    $shop_path = wp_parse_url( get_permalink( $shop_id ), PHP_URL_PATH );
    $link_path = wp_parse_url( $item->url, PHP_URL_PATH );
    if ( ! $shop_path || ! $link_path ) { return false; }
    return trim( $shop_path, '/' ) === trim( $link_path, '/' );
}

/** Renommer seulement le lien de la page boutique dans les menus existants. */
function deprat_catalog_relabel_menu( $items ) {
    if ( ! function_exists( 'wc_get_page_id' ) ) { return $items; }
    $shop_id = wc_get_page_id( 'shop' );
    foreach ( $items as $item ) {
        if ( deprat_catalog_is_shop_menu_link( $item, $shop_id ) ) {
            $item->title = 'Pièces détachées';
            $item->classes[] = 'deprat-mega-parent';
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
        // Les liens du bandeau portant « Mes demandes » ne doivent pas
        // apparaître avant la connexion, même s'ils renvoient vers le panier.
        $title = function_exists( 'remove_accents' ) ? remove_accents( wp_strip_all_tags( $item->title ) ) : wp_strip_all_tags( $item->title );
        if ( preg_match( '/^\\s*(?:mes demandes|ma demande|mes devis)(?:\\b|\\s|$)/i', $title ) ) { return false; }
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
    return deprat_category_tree_sort_terms( $group );
}

function deprat_catalog_specs( $id ) {
    $data = deprat_product_tech_data( $id );
    $features = isset( $data['features'] ) && is_array( $data['features'] ) ? $data['features'] : array();
    $fields = array( 'material' => '', 'length' => 0, 'diameter' => 0, 'thickness' => 0, 'shape' => '', 'features' => array() );
    foreach ( $features as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $key = sanitize_title( isset( $row['label'] ) ? (string) $row['label'] : '' );
        $value = isset( $row['value'] ) && is_scalar( $row['value'] ) ? trim( (string) $row['value'] ) : '';
        if ( ! $value ) { continue; }
        // Conserver les caractéristiques renseignées pour les cartes et les facettes.
        // Une mesure longue ou du texte de description ne devient pas un filtre.
        $label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? trim( (string) $row['label'] ) : '';
        if ( $key && $label && ! isset( $fields['features'][ $key ] ) ) {
            $fields['features'][ $key ] = array( 'label' => $label, 'value' => $value );
        }
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
        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! is_object( $attribute ) || ! is_callable( array( $attribute, 'get_name' ) ) ) { continue; }
            $name = $attribute->get_name();
            $value = trim( wp_strip_all_tags( (string) $product->get_attribute( $name ) ) );
            $key = sanitize_title( preg_replace( '/^pa_/', '', $name ) );
            if ( $value && $key && ! isset( $fields['features'][ $key ] ) ) {
                $fields['features'][ $key ] = array( 'label' => wc_attribute_label( $name ), 'value' => $value );
            }
        }
    } else { $fields['in_stock'] = false; }
    $shape = deprat_product_shape( $id, $data );
    if ( $shape ) {
        $fields['shape'] = deprat_product_shape_choices()[ $shape ];
        $fields['features']['forme'] = array( 'label' => 'Forme', 'value' => $fields['shape'] );
    }
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


/** Facettes construites à partir des seules données de la catégorie. */
function deprat_catalog_feature_facets( $index ) {
    $facets = array();
    $exclude = array( 'matiere', 'materiau', 'matieres', 'forme', 'forme-du-produit',
        'profil', 'type-de-tube', 'longueur', 'longueur-mm', 'longueur-m',
        'diametre', 'diametre-mm', 'diametre-exterieur', 'epaisseur', 'epaisseur-mm' );
    foreach ( $index as $spec ) {
        if ( empty( $spec['features'] ) || ! is_array( $spec['features'] ) ) { continue; }
        foreach ( $spec['features'] as $key => $feature ) {
            $key = sanitize_title( $key );
            $value = isset( $feature['value'] ) ? trim( (string) $feature['value'] ) : '';
            $label = isset( $feature['label'] ) ? trim( (string) $feature['label'] ) : '';
            // Ne jamais proposer du texte libre très long ou une liste multiligne comme filtre.
            if ( ! $key || in_array( $key, $exclude, true ) || !$label || !$value ||
                strlen( $label ) > 65 || strlen( $value ) > 70 ||
                strpbrk( $value, "\r\n" ) !== false ) { continue; }
            if ( ! isset( $facets[ $key ] ) ) {
                $facets[ $key ] = array( 'label' => $label, 'options' => array() );
            }
            $facets[ $key ]['options'][ sanitize_title( $value ) ] = $value;
        }
    }
    // Eviter une barre latérale interminable.
    uasort( $facets, static function( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );
    $facets = array_slice( $facets, 0, 12, true );
    foreach ( $facets as &$facet ) { asort( $facet['options'] ); }
    unset( $facet );
    return $facets;
}
/** Les champs affichés sur une carte proviennent uniquement de la fiche réelle. */
function deprat_catalog_card_features( $spec ) {
    $result = array();
    $excluded = array( 'matiere', 'materiau', 'matieres', 'forme', 'forme-du-produit', 'profil' );
    foreach ( (array) $spec['features'] as $key => $feature ) {
        if ( in_array( $key, $excluded, true ) || ! is_array( $feature ) ) { continue; }
        $label = isset( $feature['label'] ) ? trim( (string) $feature['label'] ) : '';
        $value = isset( $feature['value'] ) ? trim( (string) $feature['value'] ) : '';
        if ( ! $label || ! $value ) { continue; }
        $result[] = array( 'label' => $label, 'value' => $value );
        if ( count( $result ) >= 4 ) { break; }
    }
    return $result;
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
/**
 * La vue « Toutes les pièces » agrège les produits publiés, sans limite de
 * catégorie. Le filtrage utilise exactement les mêmes données que les pages
 * Tubes, Embouts, etc., et reste paginé à l'affichage.
 */
function deprat_catalog_all_products_index() {
    $cached = get_transient( 'deprat_catalog_all_ix' );
    if ( is_array( $cached ) ) { return $cached; }
    $ids = get_posts( array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'no_found_rows' => true,
    ) );
    $index = array();
    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( $product && $product->is_visible() ) {
            $index[ (int) $id ] = deprat_catalog_specs( (int) $id );
        }
    }
    set_transient( 'deprat_catalog_all_ix', $index, 5 * MINUTE_IN_SECONDS );
    return $index;
}
function deprat_catalog_clear_cache( $post_id = 0 ) {
    // Lorsque le catalogue est modifié, la durée de vie courte des facettes évite
    // de devoir connaître toutes les anciennes catégories du produit.
    if ( ! $post_id ) { return; }
    delete_transient( 'deprat_catalog_all_ix' );
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
    $result['features'] = array();
    if ( isset( $_GET['deprat_feature'] ) && is_array( $_GET['deprat_feature'] ) ) {
        foreach ( array_slice( wp_unslash( $_GET['deprat_feature'] ), 0, 12, true ) as $key => $value ) {
            if ( ! is_string( $key ) || ! is_scalar( $value ) ) { continue; }
            $key = sanitize_title( $key );
            $value = sanitize_title( (string) $value );
            if ( $key && $value && strlen( $value ) <= 80 ) { $result['features'][ $key ] = $value; }
        }
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
    foreach ( $filters['features'] as $key => $selected ) {
        if ( ! isset( $spec['features'][ $key ]['value'] )
            || sanitize_title( $spec['features'][ $key ]['value'] ) !== $selected ) { return false; }
    }
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
        'orderby' => 'title', 'order' => 'ASC',
    );
    if ( $term && isset( $term->term_id ) ) {
        $args['tax_query'] = array( array(
            'taxonomy' => 'product_cat',
            'terms' => (int) $term->term_id,
            'include_children' => true,
        ) );
    }
    if ( 'recent' === $sort ) { $args['orderby'] = 'date'; $args['order'] = 'DESC'; }
    elseif ( 'name_desc' === $sort ) { $args['orderby'] = 'title'; $args['order'] = 'DESC'; }
    return new WP_Query( $args );
}


/** Carte professionnelle : données réelles du produit, images Woo et GLB facultatif. */
function deprat_catalog_render_card( $product ) {
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $id = $product->get_id();
    $data = deprat_product_tech_data( $id );
    $spec = deprat_catalog_specs( $id );
    $url = get_permalink( $id );
    $images = array();
    foreach ( array_merge( array( (int) $product->get_image_id() ), $product->get_gallery_image_ids() ) as $image_id ) {
        $image_id = absint( $image_id );
        if ( $image_id && wp_attachment_is_image( $image_id ) && ! in_array( $image_id, $images, true ) ) {
            $images[] = $image_id;
        }
        if ( count( $images ) >= 7 ) { break; }
    }
    $glb_id = deprat_product_3d_model( $id );
    $glb = $glb_id ? wp_get_attachment_url( $glb_id ) : '';
    $description = isset( $data['description'] ) && is_scalar( $data['description'] )
        ? trim( (string) $data['description'] ) : '';
    if ( '' === $description ) { $description = $product->get_short_description(); }
    if ( '' === trim( wp_strip_all_tags( $description ) ) ) { $description = $product->get_description(); }
    $description = trim( wp_strip_all_tags( $description ) );
    $excerpt = wp_trim_words( $description, 21, '…' );
    $packages = deprat_product_tech_packages( $data );
    $allow_unitary = deprat_packaging_allow_unitary( $id );
    $can_add = $product->is_type( 'simple' ) && $product->is_purchasable()
        && $product->is_in_stock() && ( $packages || $allow_unitary );
    ?>
    <article class="deprat-catalog-card" data-catalog-card>
        <div class="deprat-catalog-image" data-catalog-gallery>
            <a href="<?php echo esc_url( $url ); ?>" class="deprat-catalog-image-link" aria-label="<?php echo esc_attr( 'Voir la fiche ' . $product->get_name() ); ?>">
                <?php if ( $images ) : ?>
                    <?php foreach ( $images as $position => $image_id ) : ?>
                        <?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array(
                            'class' => 'deprat-catalog-photo' . ( 0 === $position ? ' is-active' : '' ),
                            'data-gallery-slide' => $position,
                            'loading' => 0 === $position ? 'eager' : 'lazy',
                            'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: $product->get_name(),
                        ) ); ?>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php echo function_exists( 'wc_placeholder_img' ) ? wc_placeholder_img( 'woocommerce_thumbnail' ) : ''; ?>
                <?php endif; ?>
            </a>
            <?php if ( count( $images ) > 1 ) : ?>
                <button type="button" class="deprat-catalog-gallery-prev" data-gallery-prev aria-label="Photo précédente">‹</button>
                <button type="button" class="deprat-catalog-gallery-next" data-gallery-next aria-label="Photo suivante">›</button>
                <div class="deprat-catalog-gallery-dots" aria-hidden="true">
                    <?php foreach ( $images as $position => $image_id ) : ?>
                        <span class="<?php echo 0 === $position ? 'is-active' : ''; ?>" data-gallery-dot="<?php echo esc_attr( $position ); ?>"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ( $glb ) : ?>
                <button type="button" class="deprat-catalog-3d" data-open-model
                    data-model-src="<?php echo esc_url( $glb ); ?>"
                    data-model-name="<?php echo esc_attr( $product->get_name() ); ?>"
                    aria-label="<?php echo esc_attr( 'Voir ' . $product->get_name() . ' en 3D' ); ?>">⟳ Vue 360°</button>
            <?php endif; ?>
            <?php
            $shape = deprat_product_shape( $id, $data );
            if ( $shape ) : ?>
                <span class="deprat-catalog-shape-badge" title="<?php echo esc_attr( 'Forme : ' . deprat_product_shape_choices()[ $shape ] ); ?>">
                    <?php echo deprat_product_shape_icon( $shape ); ?>
                    <span><?php echo esc_html( deprat_product_shape_choices()[ $shape ] ); ?></span>
                </span>
            <?php endif; ?>
        </div>
        <div class="deprat-catalog-card-info">
            <?php if ( $product->get_sku() ) : ?>
                <p class="deprat-catalog-ref">Réf. : <strong><?php echo esc_html( $product->get_sku() ); ?></strong></p>
            <?php endif; ?>
            <h2><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h2>
            <?php if ( $spec['material'] ) : ?>
                <p class="deprat-catalog-matter"><span aria-hidden="true">▱</span>
                    <strong>Matière :</strong> <?php echo esc_html( $spec['material'] ); ?></p>
            <?php endif; ?>
            <?php $card_features = deprat_catalog_card_features( $spec ); ?>
            <?php if ( $spec['shape'] || $card_features ) : ?>
                <dl class="deprat-catalog-spec-list" aria-label="Caractéristiques techniques principales">
                    <?php if ( $spec['shape'] ) : ?><div><dt>Forme</dt><dd><?php echo esc_html( $spec['shape'] ); ?></dd></div><?php endif; ?>
                    <?php foreach ( $card_features as $feature ) : ?>
                        <div><dt><?php echo esc_html( $feature['label'] ); ?></dt><dd><?php echo esc_html( wp_html_excerpt( $feature['value'], 90, '…' ) ); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <?php if ( $excerpt ) : ?><p class="deprat-catalog-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
            <a class="deprat-catalog-readmore" href="<?php echo esc_url( $url ); ?>">Lire la suite <span aria-hidden="true">→</span></a>
        </div>
        <div class="deprat-catalog-card-footer">
            <?php if ( $can_add ) : ?>
                <form class="deprat-catalog-add" data-catalog-add>
                    <input type="hidden" name="product_id" value="<?php echo esc_attr( $id ); ?>">
                    <?php if ( count( $packages ) > 1 || $allow_unitary ) : ?>
                        <label class="deprat-catalog-package-label deprat-catalog-package-selectable">
                            <span>Conditionnement <small>— choisissez une option ↓</small></span>
                            <select name="deprat_package_choice" aria-label="Choisir un conditionnement">
                                <?php foreach ( $packages as $index => $package ) : ?>
                                    <option value="<?php echo esc_attr( $index ); ?>">
                                        <?php echo esc_html( ( $package['title'] ? $package['title'] . ' – ' : '' ) . deprat_packaging_label( $package ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ( $allow_unitary ) : ?><option value="unitary">À l’unité – quantité libre</option><?php endif; ?>
                            </select>
                        </label>
                    <?php else : ?>
                        <input type="hidden" name="deprat_package_choice" value="0">
                        <div class="deprat-catalog-package-fixed">
                            <span>Conditionnement standard</span>
                            <p class="deprat-catalog-package-summary"><?php echo esc_html( deprat_packaging_label( $packages[0] ) ); ?></p>
                        </div>
                    <?php endif; ?>
                    <p class="deprat-catalog-unitary-warning" data-unitary-warning hidden>
                        <?php echo esc_html( deprat_packaging_surcharge_warning() ); ?>
                    </p>
                    <div class="deprat-catalog-action-row">
                        <label class="deprat-catalog-qty-label">
                            <span>Quantité</span>
                            <span class="deprat-catalog-stepper">
                                <button type="button" data-qty-minus aria-label="Diminuer la quantité">−</button>
                                <input type="number" name="quantity" min="1" step="1" value="1" inputmode="numeric" aria-label="Quantité souhaitée">
                                <button type="button" data-qty-plus aria-label="Augmenter la quantité">+</button>
                            </span>
                        </label>
                        <button type="submit" class="deprat-catalog-add-button">Ajouter à ma demande</button>
                    </div>
                    <p class="deprat-catalog-feedback" data-catalog-feedback aria-live="polite" hidden></p>
                </form>
            <?php else : ?>
                <?php if ( ! $product->is_in_stock() ) : ?>
                    <span class="deprat-catalog-unavailable">Indisponible</span>
                <?php elseif ( $product->is_type( 'variable' ) ) : ?>
                    <a class="deprat-catalog-details" href="<?php echo esc_url( $url ); ?>">Choisir une variation</a>
                <?php else : ?>
                    <a class="deprat-catalog-details" href="<?php echo esc_url( $url ); ?>">Consulter la fiche</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * Arborescence multinueau : même ordre et mêmes parents qu'en administration.
 * Chaque étage ouvre la catégorie correspondante et ses descendantes.
 */
function deprat_catalog_render_mega_children( $parent, $depth = 0 ) {
    if ( $depth > 20 ) { return; }
    $children = deprat_category_tree_front_children( $parent );
    if ( ! $children ) { return; }
    ?>
    <ul class="deprat-mega-subcategories" data-mega-depth="<?php echo esc_attr( $depth ); ?>">
        <?php foreach ( $children as $child ) :
            $url = get_term_link( $child );
            if ( is_wp_error( $url ) ) { continue; }
            ?>
            <li>
                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $child->name ); ?></a>
                <?php deprat_catalog_render_mega_children( $child->term_id, $depth + 1 ); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/**
 * Méga-menu « Pièces détachées » inspiré de la maquette :
 * 3 à 4 colonnes de familles, vignettes et liens vers les sous-catégories réelles.
 * Les liens sont issus de product_cat, sans recopier les noms dans le code.
 */
function deprat_catalog_mega_menu( $item_output, $item, $depth, $args ) {
    if ( $depth > 0 || ! $item || ! in_array( 'deprat-mega-parent', (array) $item->classes, true ) ) {
        return $item_output;
    }
    $location = isset( $args->theme_location ) ? (string) $args->theme_location : '';
    if ( preg_match( '/footer|secondary|social/i', $location ) ) { return $item_output; }
    $roots = deprat_catalog_root_categories();
    if ( ! $roots ) { return $item_output; }
    ob_start();
    ?>
    <nav class="deprat-mega-panel" aria-label="Familles de pièces détachées">
        <div class="deprat-mega-intro">
            <strong>Pièces détachées</strong>
            <a class="deprat-mega-viewall" href="<?php echo esc_url( deprat_catalog_all_products_url() ); ?>">
                Voir toutes les pièces <span aria-hidden="true">→</span>
            </a>
        </div>
        <div class="deprat-mega-grid">
            <?php foreach ( $roots as $root ) :
                $root_url = get_term_link( $root );
                if ( is_wp_error( $root_url ) ) { continue; }
                $image = absint( get_term_meta( $root->term_id, 'thumbnail_id', true ) );
                ?>
                <section class="deprat-mega-family">
                    <a class="deprat-mega-family-heading" href="<?php echo esc_url( $root_url ); ?>">
                        <span class="deprat-mega-family-illustration" aria-hidden="true">
                            <?php if ( $image ) : ?>
                                <?php echo wp_get_attachment_image( $image, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
                            <?php else : ?><span class="deprat-mega-fallback">▤</span><?php endif; ?>
                        </span>
                        <span><?php echo esc_html( $root->name ); ?></span>
                        <span class="deprat-mega-heading-arrow" aria-hidden="true">›</span>
                    </a>
                    <?php deprat_catalog_render_mega_children( $root->term_id ); ?>
                </section>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
    return $item_output . ob_get_clean();
}
add_filter( 'walker_nav_menu_start_el', 'deprat_catalog_mega_menu', 20, 4 );

function deprat_catalog_mega_assets() {
    wp_enqueue_style( 'deprat-catalog-mega-menu',
        plugins_url( 'assets/css/catalogue-mega-menu.css', dirname( __DIR__ ) . '/deprat-core.php' ),
        array(), '0.1.31'
    );
    wp_enqueue_script( 'deprat-catalog-mega-menu',
        plugins_url( 'assets/js/catalogue-mega-menu.js', dirname( __DIR__ ) . '/deprat-core.php' ),
        array(), '0.1.31', true
    );
}
add_action( 'wp_enqueue_scripts', 'deprat_catalog_mega_assets', 20 );


/**
 * Compatibilité avec le menu automatique du thème (wp_page_menu/wp_list_pages)
 * lorsque l'utilisateur n'a pas encore créé de menu principal personnalisé.
 * Le menu généré reste celui du thème : seul « Shop » reçoit le méga-menu DEPRAT.
 */
function deprat_catalog_fallback_page_menu( $html, $args = null ) {
    if ( ! is_string( $html ) || false !== strpos( $html, 'deprat-mega-panel' ) ) { return $html; }
    $shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
    if ( $shop_id < 1 || false === strpos( $html, 'page-item-' . $shop_id ) ) { return $html; }
    $fake_item = (object) array( 'classes' => array( 'deprat-mega-parent' ) );
    $mega = deprat_catalog_mega_menu( '', $fake_item, 0, (object) array( 'theme_location' => 'primary' ) );
    $pattern = '~(<li\b[^>]*\bpage-item-' . $shop_id . '\b[^>]*>)(\s*<a\b[^>]*>)(.*?)(</a>)~is';
    return preg_replace_callback( $pattern, static function( $matches ) use ( $shop_id, $mega ) {
        $li = str_replace( 'page-item-' . $shop_id, 'page-item-' . $shop_id . ' deprat-mega-parent', $matches[1] );
        return $li . $matches[2] . 'Pièces détachées' . $matches[4] . $mega;
    }, $html, 1 );
}
add_filter( 'wp_page_menu', 'deprat_catalog_fallback_page_menu', 30, 2 );
add_filter( 'wp_list_pages', 'deprat_catalog_fallback_page_menu', 30, 2 );

/** Cacher l'historique des demandes même dans un menu WordPress automatique. */
function deprat_catalog_hide_requests_from_page_lists( $excluded ) {
    if ( is_user_logged_in() ) { return $excluded; }
    $page = get_page_by_path( 'mes-demandes-devis', OBJECT, 'page' );
    if ( $page ) { $excluded[] = (int) $page->ID; }
    return array_unique( array_map( 'intval', $excluded ) );
}
add_filter( 'wp_list_pages_excludes', 'deprat_catalog_hide_requests_from_page_lists', 20 );
