<?php
/**
 * DEPRAT — navigation hiérarchique sur la liste native des produits WooCommerce.
 *
 * La table de WordPress et ses actions natives restent en place ; cet écran
 * ajoute des filtres section > catégorie > sous-catégorie à partir des VRAIS
 * termes WooCommerce. Aucune catégorie n'est créée à l'affichage.
 */
defined( 'ABSPATH' ) || exit;

function deprat_product_list_is_screen() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return $screen && 'edit-product' === $screen->id && current_user_can( 'edit_products' );
}

/** Ne jamais recycler des paramètres d'action / de suppression dans les liens. */
function deprat_product_list_url( $changes = array() ) {
    $allowed = array( 'post_status', 's', 'deprat_section', 'deprat_cat', 'deprat_stock', 'deprat_type', 'deprat_sort' );
    $params = array( 'post_type' => 'product' );
    foreach ( $allowed as $key ) {
        if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) { continue; }
        $value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        if ( '' !== $value ) { $params[ $key ] = $value; }
    }
    foreach ( $changes as $key => $value ) {
        if ( ! in_array( $key, $allowed, true ) ) { continue; }
        if ( null === $value || '' === $value ) { unset( $params[ $key ] ); }
        else { $params[ $key ] = $value; }
    }
    return add_query_arg( $params, admin_url( 'edit.php' ) );
}

/** Retourne les parents réels, depuis la racine, en évitant les cycles. */
function deprat_product_list_ancestors( $term ) {
    $ancestors = array();
    $seen = array();
    $current = $term;
    while ( $current instanceof WP_Term && ! isset( $seen[ $current->term_id ] ) ) {
        $ancestors[] = $current;
        $seen[ $current->term_id ] = true;
        if ( ! $current->parent ) { break; }
        $current = get_term( (int) $current->parent, 'product_cat' );
    }
    return array_reverse( $ancestors );
}

function deprat_product_list_state() {
    $definitions = deprat_catalog_section_definitions();
    $raw_section = isset( $_GET['deprat_section'] ) && is_scalar( $_GET['deprat_section'] )
        ? sanitize_key( wp_unslash( $_GET['deprat_section'] ) ) : '';
    $section = isset( $definitions[ $raw_section ] ) ? $raw_section : '';
    $cat_id = isset( $_GET['deprat_cat'] ) && is_scalar( $_GET['deprat_cat'] ) ? absint( $_GET['deprat_cat'] ) : 0;
    $category = $cat_id ? get_term( $cat_id, 'product_cat' ) : null;
    if ( ! $category instanceof WP_Term || (int) get_option( 'default_product_cat' ) === $cat_id ) {
        $category = null;
    }
    $path = $category ? deprat_product_list_ancestors( $category ) : array();
    if ( $category && $path ) {
        $derived = deprat_catalog_section_for_term( $path[0] );
        if ( $section && $derived !== $section ) {
            $category = null;
            $path = array();
        } else {
            $section = $derived;
        }
    }
    return array(
        'section' => $section,
        'category' => $category,
        'path' => $path,
    );
}

/** Un comptage distinct des produits : pas de double comptage des parents/enfants. */
function deprat_product_list_count( $term_ids = null ) {
    global $wpdb;
    static $memo = array();
    if ( is_array( $term_ids ) ) {
        $term_ids = array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
        sort( $term_ids );
        if ( ! $term_ids ) { return 0; }
        $key = implode( '-', $term_ids );
    } else { $key = 'all'; }
    if ( isset( $memo[ $key ] ) ) { return $memo[ $key ]; }
    $status = "p.post_type = 'product' AND p.post_status IN ('publish','private','draft','pending','future')";
    if ( null === $term_ids ) {
        $sql = "SELECT COUNT(1) FROM {$wpdb->posts} p WHERE {$status}";
        $memo[ $key ] = (int) $wpdb->get_var( $sql ); // Tables et statuts constants.
    } else {
        $placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
        $sql = "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} rel ON rel.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tx ON tx.term_taxonomy_id = rel.term_taxonomy_id
            WHERE {$status} AND tx.taxonomy = 'product_cat' AND tx.term_id IN ({$placeholders})";
        $memo[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( $sql, $term_ids ) );
    }
    return $memo[ $key ];
}

function deprat_product_list_term_ids( $term ) {
    $ids = array( (int) $term->term_id );
    $children = get_term_children( (int) $term->term_id, 'product_cat' );
    if ( is_array( $children ) ) {
        foreach ( $children as $id ) { $ids[] = (int) $id; }
    }
    return array_values( array_unique( $ids ) );
}

function deprat_product_list_section_ids( $key ) {
    $groups = deprat_catalog_grouped_roots();
    if ( ! isset( $groups[ $key ] ) ) { return array(); }
    $ids = array();
    foreach ( $groups[ $key ] as $term ) {
        $ids = array_merge( $ids, deprat_product_list_term_ids( $term ) );
    }
    return array_values( array_unique( $ids ) );
}

/** Réemploi du moteur natif d'édition, recherche, pagination et actions groupées. */
add_action( 'pre_get_posts', static function ( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' )
        || ! deprat_product_list_is_screen() ) { return; }
    $state = deprat_product_list_state();
    $ids = array();
    if ( $state['category'] ) { $ids = deprat_product_list_term_ids( $state['category'] ); }
    elseif ( $state['section'] ) { $ids = deprat_product_list_section_ids( $state['section'] ); }
    if ( $state['section'] || $state['category'] ) {
        if ( ! $ids ) {
            $query->set( 'post__in', array( 0 ) );
        } else {
            $tax = (array) $query->get( 'tax_query', array() );
            $tax[] = array(
                'taxonomy' => 'product_cat', 'field' => 'term_id',
                'terms' => $ids, 'include_children' => false, 'operator' => 'IN',
            );
            $query->set( 'tax_query', $tax );
        }
    }
    $stock = isset( $_GET['deprat_stock'] ) && is_scalar( $_GET['deprat_stock'] )
        ? sanitize_key( wp_unslash( $_GET['deprat_stock'] ) ) : '';
    if ( in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
        $meta = (array) $query->get( 'meta_query', array() );
        $meta[] = array( 'key' => '_stock_status', 'value' => $stock, 'compare' => '=' );
        $query->set( 'meta_query', $meta );
    }
    $type = isset( $_GET['deprat_type'] ) && is_scalar( $_GET['deprat_type'] )
        ? sanitize_key( wp_unslash( $_GET['deprat_type'] ) ) : '';
    if ( in_array( $type, array( 'simple', 'variable', 'grouped', 'external' ), true ) ) {
        $tax = (array) $query->get( 'tax_query', array() );
        $tax[] = array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => $type );
        $query->set( 'tax_query', $tax );
    }
    $sort = isset( $_GET['deprat_sort'] ) && is_scalar( $_GET['deprat_sort'] )
        ? sanitize_key( wp_unslash( $_GET['deprat_sort'] ) ) : '';
    switch ( $sort ) {
        case 'oldest': $query->set( 'orderby', 'date' ); $query->set( 'order', 'ASC' ); break;
        case 'name_asc': $query->set( 'orderby', 'title' ); $query->set( 'order', 'ASC' ); break;
        case 'name_desc': $query->set( 'orderby', 'title' ); $query->set( 'order', 'DESC' ); break;
        case 'newest': $query->set( 'orderby', 'date' ); $query->set( 'order', 'DESC' ); break;
    }
}, 999 );

function deprat_product_list_nav_item( $title, $count, $url, $active, $level, $icon = '' ) {
    echo '<a class="deprat-products-tab deprat-products-tab--' . esc_attr( $level )
        . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"'
        . ( $active ? ' aria-current="page"' : '' ) . '>';
    if ( $icon ) {
        // Uniquement des SVG issus de la palette constante DEPRAT.
        echo '<span class="deprat-products-tab-icon">' . deprat_catalog_section_icon( $icon ) . '</span>';
    }
    echo '<span>' . esc_html( $title ) . '</span><span class="deprat-products-count">'
        . esc_html( (string) $count ) . '</span></a>';
}

function deprat_product_list_admin_markup() {
    if ( ! deprat_product_list_is_screen() ) { return; }
    $state = deprat_product_list_state();
    $section = $state['section'];
    $selected = $state['category'];
    $path = $state['path'];
    $definitions = deprat_catalog_section_definitions();
    $groups = deprat_catalog_grouped_roots();
    $all_url = deprat_product_list_url( array( 'deprat_section' => null, 'deprat_cat' => null ) );
    ?>
    <template id="deprat-products-layout-template">
        <div class="deprat-products-ui" data-deprat-products-ui>
            <header class="deprat-products-header">
                <div class="deprat-products-title"><span class="deprat-products-cube" aria-hidden="true">◇</span>
                    <h1>Produits</h1></div>
                <div class="deprat-products-header-actions">
                    <a class="deprat-products-create" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>"><span aria-hidden="true">＋</span> Ajouter un produit</a>
                    <span data-deprat-native-actions></span>
                </div>
                <div class="deprat-products-header-search" data-deprat-search-destination></div>
            </header>
            <nav class="deprat-products-sections" aria-label="Filtrer les produits par section">
                <?php
                deprat_product_list_nav_item( 'Tous les produits', deprat_product_list_count(), $all_url, ! $section, 'section' );
                foreach ( $definitions as $key => $title ) {
                    deprat_product_list_nav_item( $title,
                        deprat_product_list_count( deprat_product_list_section_ids( $key ) ),
                        deprat_product_list_url( array( 'deprat_section' => $key, 'deprat_cat' => null ) ),
                        $section === $key, 'section' );
                }
                ?>
            </nav>
            <?php if ( $section ) : ?>
            <div class="deprat-products-hierarchy">
                <nav class="deprat-products-categories" aria-label="Catégories de la section sélectionnée">
                    <?php
                    $current_root_id = $path ? (int) $path[0]->term_id : 0;
                    foreach ( isset( $groups[ $section ] ) ? $groups[ $section ] : array() as $term ) {
                        deprat_product_list_nav_item( $term->name,
                            deprat_product_list_count( deprat_product_list_term_ids( $term ) ),
                            deprat_product_list_url( array( 'deprat_cat' => (string) $term->term_id ) ),
                            $current_root_id === (int) $term->term_id, 'category',
                            deprat_product_list_category_icon( $term, $section ) );
                    }
                    if ( ! isset( $groups[ $section ] ) ) {
                        echo '<span class="deprat-products-empty">Aucune catégorie créée dans cette section.</span>';
                    }
                    ?>
                </nav>
                <div class="deprat-products-deep">
                    <nav class="deprat-products-breadcrumb" aria-label="Chemin de navigation">
                        <a href="<?php echo esc_url( $all_url ); ?>">⌂ &nbsp; Tous les produits</a>
                        <span aria-hidden="true">›</span>
                        <a href="<?php echo esc_url( deprat_product_list_url( array(
                            'deprat_cat' => null ) ) ); ?>"><?php echo esc_html( $definitions[ $section ] ); ?></a>
                        <?php foreach ( $path as $term ) : ?>
                            <span aria-hidden="true">›</span>
                            <a href="<?php echo esc_url( deprat_product_list_url( array(
                                'deprat_cat' => (string) $term->term_id ) ) ); ?>"
                                <?php if ( $selected && $term->term_id === $selected->term_id ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $term->name ); ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <?php if ( $selected ) :
                        $children = deprat_category_tree_front_children( (int) $selected->term_id ); ?>
                        <nav class="deprat-products-children" aria-label="Sous-catégories de <?php echo esc_attr( $selected->name ); ?>">
                            <?php deprat_product_list_nav_item( 'Tous',
                                deprat_product_list_count( deprat_product_list_term_ids( $selected ) ),
                                deprat_product_list_url( array( 'deprat_cat' => (string) $selected->term_id ) ),
                                true, 'child' ); ?>
                            <?php foreach ( $children as $child ) {
                                deprat_product_list_nav_item( $child->name,
                                    deprat_product_list_count( deprat_product_list_term_ids( $child ) ),
                                    deprat_product_list_url( array( 'deprat_cat' => (string) $child->term_id ) ),
                                    false, 'child' );
                            } ?>
                        </nav>
                    <?php else : ?>
                        <span class="deprat-products-hint">Choisissez une catégorie pour afficher ses sous-catégories.</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="deprat-products-filters">
                <label><span class="screen-reader-text">État du stock</span><select name="deprat_stock" data-deprat-filter>
                    <option value="">État du stock</option>
                    <?php foreach ( array( 'instock' => 'En stock', 'outofstock' => 'Rupture de stock', 'onbackorder' => 'En réapprovisionnement' ) as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $_GET['deprat_stock'] ) && $_GET['deprat_stock'] === $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span class="screen-reader-text">Type de produit</span><select name="deprat_type" data-deprat-filter>
                    <option value="">Type de produit</option>
                    <?php foreach ( array( 'simple' => 'Produit simple', 'variable' => 'Produit variable', 'grouped' => 'Produit groupé', 'external' => 'Produit externe' ) as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $_GET['deprat_type'] ) && $_GET['deprat_type'] === $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span class="screen-reader-text">Trier les produits</span><select name="deprat_sort" data-deprat-filter>
                    <option value="">Trier par</option>
                    <?php foreach ( array( 'newest' => 'Date : récents d’abord', 'oldest' => 'Date : anciens d’abord', 'name_asc' => 'Nom : A à Z', 'name_desc' => 'Nom : Z à A' ) as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $_GET['deprat_sort'] ) && $_GET['deprat_sort'] === $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <div class="deprat-products-filters-right">
                    <label><span class="screen-reader-text">Filtrer les lignes visibles</span>
                        <input type="search" data-deprat-list-search placeholder="Filtrer dans la liste…" autocomplete="off"></label>
                    <strong data-deprat-visible-count></strong>
                </div>
            </div>
        </div>
    </template>
    <?php
}

/** Association visuelle catégorie / icône : uniquement des types connus. */
function deprat_product_list_category_icon( $term, $fallback ) {
    $name = sanitize_title( $term->name . ' ' . $term->slug );
    if ( preg_match( '/tube/', $name ) ) { return 'tubes'; }
    if ( preg_match( '/embout/', $name ) ) { return 'autres'; }
    if ( preg_match( '/flasque|bague/', $name ) ) { return 'coulisses'; }
    if ( preg_match( '/support|arbre/', $name ) ) { return 'bras'; }
    return $fallback;
}
add_action( 'admin_footer-edit.php', 'deprat_product_list_admin_markup' );

/** Garde les actions natives WooCommerce, tout en ajoutant Section et menu ⋮. */
add_filter( 'manage_edit-product_columns', static function ( $columns ) {
    if ( ! deprat_product_list_is_screen() ) { return $columns; }
    $result = array();
    foreach ( array( 'cb', 'thumb', 'name', 'sku', 'is_in_stock', 'product_cat' ) as $key ) {
        if ( isset( $columns[ $key ] ) ) {
            $result[ $key ] = $columns[ $key ];
        }
    }
    $result['deprat_section'] = 'Section';
    if ( isset( $columns['date'] ) ) { $result['date'] = 'Date de publication'; }
    $result['deprat_actions'] = '';
    return $result;
}, 150 );

/** Affiche la section de la racine existante, sans attribution fictive. */
add_action( 'manage_product_posts_custom_column', static function ( $column, $post_id ) {
    if ( ! in_array( $column, array( 'deprat_section', 'deprat_actions' ), true ) ) { return; }
    if ( 'deprat_section' === $column ) {
        $terms = get_the_terms( $post_id, 'product_cat' );
        $names = array();
        $definitions = deprat_catalog_section_definitions();
        $default = (int) get_option( 'default_product_cat' );
        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( $default === (int) $term->term_id ) { continue; }
                $path = deprat_product_list_ancestors( $term );
                if ( ! $path ) { continue; }
                $key = deprat_catalog_section_for_term( $path[0] );
                if ( isset( $definitions[ $key ] ) ) { $names[ $key ] = $definitions[ $key ]; }
            }
        }
        foreach ( $names as $key => $name ) {
            $href = deprat_product_list_url( array( 'deprat_section' => $key, 'deprat_cat' => null ) );
            echo '<a class="deprat-products-section-pill" href="' . esc_url( $href ) . '">' . esc_html( $name ) . '</a> ';
        }
        if ( ! $names ) { echo '<span class="deprat-products-not-classified">Non classé</span>'; }
        return;
    }
    echo '<details class="deprat-products-row-menu"><summary aria-label="Actions pour ce produit">⋮</summary><div>';
    if ( current_user_can( 'edit_post', $post_id ) ) {
        echo '<a href="' . esc_url( get_edit_post_link( $post_id, '' ) ) . '">Modifier le produit</a>';
    }
    if ( 'publish' === get_post_status( $post_id ) ) {
        echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" rel="noopener noreferrer">Voir sur le site</a>';
    }
    if ( current_user_can( 'delete_post', $post_id ) ) {
        echo '<a href="' . esc_url( get_delete_post_link( $post_id ) ) . '">Mettre à la corbeille</a>';
    }
    echo '</div></details>';
}, 10, 2 );

function deprat_product_list_admin_assets( $hook ) {
    if ( 'edit.php' !== $hook || ! deprat_product_list_is_screen() ) { return; }
    wp_enqueue_style( 'deprat-product-list', plugins_url( 'assets/css/product-list-admin.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.42' );
    wp_enqueue_script( 'deprat-product-list', plugins_url( 'assets/js/product-list-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.42', true );
}
add_action( 'admin_enqueue_scripts', 'deprat_product_list_admin_assets' );
