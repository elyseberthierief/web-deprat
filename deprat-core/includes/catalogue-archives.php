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
            <?php if ( $excerpt ) : ?><p class="deprat-catalog-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
            <a class="deprat-catalog-readmore" href="<?php echo esc_url( $url ); ?>">Lire la suite <span aria-hidden="true">→</span></a>
        </div>
        <div class="deprat-catalog-card-footer">
            <?php if ( $can_add ) : ?>
                <form class="deprat-catalog-add" data-catalog-add>
                    <input type="hidden" name="product_id" value="<?php echo esc_attr( $id ); ?>">
                    <?php if ( count( $packages ) > 1 || $allow_unitary ) : ?>
                        <label class="deprat-catalog-package-label">
                            <span>Conditionnement</span>
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
                        <p class="deprat-catalog-package-summary"><?php echo esc_html( deprat_packaging_label( $packages[0] ) ); ?></p>
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
            <div>
                <span class="deprat-mega-eyebrow">CATALOGUE DEPRAT</span>
                <strong>Pièces détachées pour professionnels</strong>
                <span>Accédez directement à une famille de produits.</span>
            </div>
            <a class="deprat-mega-viewall" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
                Toutes les pièces <span aria-hidden="true">→</span>
            </a>
        </div>
        <div class="deprat-mega-grid">
            <?php foreach ( array_slice( $roots, 0, 16 ) as $root ) :
                $root_url = get_term_link( $root );
                if ( is_wp_error( $root_url ) ) { continue; }
                $image = absint( get_term_meta( $root->term_id, 'thumbnail_id', true ) );
                $children = get_terms( array(
                    'taxonomy' => 'product_cat', 'parent' => (int) $root->term_id,
                    'hide_empty' => false, 'orderby' => 'menu_order', 'order' => 'ASC', 'number' => 7,
                ) );
                if ( is_wp_error( $children ) || ! is_array( $children ) ) { $children = array(); }
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
                    <?php if ( $children ) : ?>
                        <ul>
                            <?php foreach ( $children as $child ) :
                                $url = get_term_link( $child );
                                if ( is_wp_error( $url ) ) { continue; }
                                ?>
                                <li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $child->name ); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <a class="deprat-mega-family-all" href="<?php echo esc_url( $root_url ); ?>">Voir les produits <span aria-hidden="true">›</span></a>
                    <?php endif; ?>
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
        array(), '0.1.28'
    );
}
add_action( 'wp_enqueue_scripts', 'deprat_catalog_mega_assets', 20 );
