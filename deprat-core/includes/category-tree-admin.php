<?php
/**
 * DEPRAT — arborescence éditoriale des catégories WooCommerce.
 *
 * Le parent WP et l'ordre natif WooCommerce (term meta « order ») sont les
 * sources uniques de vérité. Le catalogue et le méga-menu les réutilisent.
 */
defined( 'ABSPATH' ) || exit;

function deprat_category_tree_terms() {
    if ( ! taxonomy_exists( 'product_cat' ) ) { return array(); }
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) { return array(); }
    $default = (int) get_option( 'default_product_cat', 0 );
    return array_values( array_filter( $terms, static function( $term ) use ( $default ) {
        return (int) $term->term_id !== $default;
    } ) );
}
/** Trier comme WooCommerce, puis par nom tant que l'admin n'a pas défini l'ordre. */
function deprat_category_tree_sort_terms( $terms ) {
    usort( $terms, static function( $left, $right ) {
        $a = get_term_meta( (int) $left->term_id, 'order', true );
        $b = get_term_meta( (int) $right->term_id, 'order', true );
        $a = '' !== (string) $a ? (int) $a : PHP_INT_MAX;
        $b = '' !== (string) $b ? (int) $b : PHP_INT_MAX;
        if ( $a !== $b ) { return $a <=> $b; }
        return strnatcasecmp( $left->name, $right->name );
    } );
    return $terms;
}
function deprat_category_tree_children( $terms, $parent ) {
    $children = array();
    // « Non classé » est masquée de l'éditeur ; ses éventuels enfants
    // doivent cependant rester visibles et pouvoir être réorganisés.
    $default = (int) get_option( 'default_product_cat', 0 );
    foreach ( $terms as $term ) {
        if ( (int) $term->parent === (int) $parent
            || ( 0 === (int) $parent && $default && (int) $term->parent === $default ) ) {
            $children[] = $term;
        }
    }
    return deprat_category_tree_sort_terms( $children );
}
function deprat_category_tree_front_children( $parent ) {
    $terms = get_terms( array(
        'taxonomy' => 'product_cat', 'parent' => (int) $parent,
        'hide_empty' => false, 'number' => 0,
    ) );
    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) { return array(); }
    return deprat_category_tree_sort_terms( $terms );
}
/** Arborescence compacte destinée au sélecteur de la fiche produit. */
function deprat_category_tree_flat_options() {
    $terms = deprat_category_tree_terms();
    $result = array();
    $visited = array();
    $walk = static function ( $parent, $depth ) use ( &$walk, &$result, &$visited, $terms ) {
        if ( $depth > 30 ) { return; }
        foreach ( deprat_category_tree_children( $terms, $parent ) as $term ) {
            $id = (int) $term->term_id;
            if ( isset( $visited[ $id ] ) ) { continue; }
            $visited[ $id ] = true;
            $result[] = array( 'id' => $id, 'parent' => (int) $term->parent, 'depth' => $depth, 'name' => $term->name );
            $walk( $id, $depth + 1 );
        }
    };
    $walk( 0, 0 );
    // Ne jamais perdre une ancienne sous-catégorie orpheline.
    foreach ( $terms as $term ) {
        if ( ! isset( $visited[ $term->term_id ] ) ) {
            $result[] = array( 'id' => (int) $term->term_id, 'parent' => (int) $term->parent, 'depth' => 0, 'name' => $term->name );
        }
    }
    return $result;
}
function deprat_category_tree_page_url() {
    return admin_url( 'edit.php?post_type=product&page=deprat-categories-tree' );
}
add_action( 'admin_menu', static function () {
    if ( ! taxonomy_exists( 'product_cat' ) ) { return; }
    add_submenu_page(
        'edit.php?post_type=product',
        'Arborescence des catégories DEPRAT',
        'Organiser les catégories',
        'manage_product_terms',
        'deprat-categories-tree',
        'deprat_category_tree_admin_page'
    );
}, 50 );

function deprat_category_tree_admin_assets( $hook ) {
    if ( 'product_page_deprat-categories-tree' !== $hook ) { return; }
    wp_enqueue_style( 'deprat-category-tree', plugins_url( 'assets/css/category-tree-admin.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.33' );
    wp_enqueue_script( 'deprat-category-tree', plugins_url( 'assets/js/category-tree-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'jquery-ui-sortable' ), '0.1.33', true );
    wp_localize_script( 'deprat-category-tree', 'depratCategoryTree', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'deprat_categories_tree' ),
        'editBase' => admin_url( 'term.php?taxonomy=product_cat&post_type=product&tag_ID=' ),
        'maxDepth' => 30,
        'sectionLabels' => deprat_catalog_section_definitions(),
    ) );
}
add_action( 'admin_enqueue_scripts', 'deprat_category_tree_admin_assets' );

function deprat_category_tree_render_list( $parent, $terms, $depth = 0 ) {
    if ( $depth > 30 ) { return; }
    $children = deprat_category_tree_children( $terms, $parent );
    ?>
    <ul class="deprat-tree-list <?php echo $depth ? 'deprat-tree-children' : 'deprat-tree-root'; ?>"
        data-tree-list data-parent-id="<?php echo esc_attr( $parent ); ?>">
        <?php foreach ( $children as $term ) : ?>
            <?php deprat_category_tree_render_item( $term, $terms, $depth ); ?>
        <?php endforeach; ?>
    </ul>
    <?php
}
function deprat_category_tree_render_item( $term, $terms, $depth = 0 ) {
    $id = (int) $term->term_id;
    $children = deprat_category_tree_children( $terms, $id );
    $url = get_edit_term_link( $id, 'product_cat', 'product' );
    ?>
    <li class="deprat-tree-item" data-tree-item data-term-id="<?php echo esc_attr( $id ); ?>">
        <div class="deprat-tree-row">
            <button type="button" class="deprat-tree-handle" data-drag-handle title="Faire glisser pour réorganiser" aria-label="<?php echo esc_attr( 'Déplacer ' . $term->name ); ?>">⠿</button>
            <div class="deprat-tree-label">
                <strong data-tree-name><?php echo esc_html( $term->name ); ?></strong>
                <span class="deprat-tree-meta"><?php echo esc_html( (int) $term->count . ' produit(s)' ); ?></span>
            </div>
            <div class="deprat-tree-actions">
                <button type="button" class="button" data-tree-up title="Monter dans cette liste" aria-label="<?php echo esc_attr( 'Monter ' . $term->name ); ?>">↑</button>
                <button type="button" class="button" data-tree-down title="Descendre dans cette liste" aria-label="<?php echo esc_attr( 'Descendre ' . $term->name ); ?>">↓</button>
                <button type="button" class="button" data-tree-indent title="Ranger dans la catégorie précédente" aria-label="Augmenter le niveau">↳</button>
                <button type="button" class="button" data-tree-outdent title="Remonter d'un niveau" aria-label="Diminuer le niveau">↰</button>
                <button type="button" class="button" data-tree-add-child>+ Sous-catégorie</button>
                <label class="deprat-tree-section-editor" title="Rubrique visuelle non cliquable du menu. Aucune catégorie créée.">
                    Rubrique
                    <select data-tree-section data-term-id="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( 'Rubrique du menu pour ' . $term->name ); ?>">
                        <option value="">Automatique</option>
                        <?php
                        $selected_section = get_term_meta( $id, '_deprat_catalog_section', true );
                        foreach ( deprat_catalog_section_definitions() as $section_key => $section_title ) : ?>
                            <option value="<?php echo esc_attr( $section_key ); ?>" <?php selected( $selected_section, $section_key ); ?>><?php echo esc_html( $section_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ( $url ) : ?>
                    <a class="button" href="<?php echo esc_url( $url ); ?>">Modifier</a>
                <?php endif; ?>
            </div>
        </div>
        <?php deprat_category_tree_render_list( $id, $terms, $depth + 1 ); ?>
    </li>
    <?php
}
function deprat_category_tree_admin_page() {
    if ( ! current_user_can( 'manage_product_terms' ) ) { wp_die( 'Vous ne pouvez pas organiser les catégories.' ); }
    $terms = deprat_category_tree_terms();
    ?>
    <div class="wrap deprat-tree-admin" data-deprat-category-tree>
        <div class="deprat-tree-heading">
            <div>
                <p class="deprat-tree-overline">CATALOGUE DEPRAT</p>
                <h1>Arborescence des catégories</h1>
                <p>Construisez votre menu comme vous souhaitez le voir sur le site : catégories principales, sous-catégories et niveaux supplémentaires.</p>
            </div>
            <div class="deprat-tree-heading-actions">
                <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button" target="_blank" rel="noopener">Voir le catalogue ↗</a>
                <button type="button" class="button button-primary" data-tree-save>Enregistrer l’arborescence</button>
            </div>
        </div>
        <div class="deprat-tree-instructions">
            <strong>Comment organiser ?</strong> Faites glisser une catégorie avec la poignée <strong>⠿</strong>
            pour changer l’ordre ou déposez-la dans la zone pointillée sous une autre pour en faire une sous-catégorie.
            Utilisez <strong>↑ / ↓</strong> pour changer l’ordre sans glisser et les flèches <strong>↳ / ↰</strong> pour modifier le niveau.
            Le sélecteur <strong>Rubrique</strong> classe une catégorie existante sous un simple titre visuel, sans créer de catégorie WooCommerce.
            <strong>Enregistrez</strong> lorsque le plan vous convient.
        </div>
        <div class="deprat-tree-toolbar">
            <button type="button" class="button button-secondary" data-tree-create-root>+ Nouvelle catégorie principale</button>
            <span data-tree-unsaved class="deprat-tree-unsaved" hidden>Modifications non enregistrées</span>
            <span data-tree-status role="status" aria-live="polite"></span>
        </div>
        <div class="deprat-tree-area">
            <?php deprat_category_tree_render_list( 0, $terms ); ?>
        </div>
        <div class="deprat-tree-bottom">
            <p>La catégorie WooCommerce « Non classé » reste gérée séparément. L’organisation enregistrée est utilisée sur les pages de catégories et dans le méga-menu DEPRAT.</p>
            <button type="button" class="button button-primary" data-tree-save>Enregistrer l’arborescence</button>
        </div>
        <form class="deprat-tree-create-form" data-tree-create-form hidden>
            <h2 data-tree-create-title>Nouvelle catégorie</h2>
            <input type="hidden" name="parent" value="0" data-tree-create-parent>
            <label>Nom de la catégorie
                <input type="text" name="name" maxlength="160" required placeholder="Ex. Tubes acier" data-tree-create-name>
            </label>
            <div class="deprat-tree-create-actions">
                <button type="button" class="button" data-tree-create-cancel>Annuler</button>
                <button type="submit" class="button button-primary" data-tree-create-submit>Créer cette catégorie</button>
            </div>
            <p data-tree-create-error role="alert" hidden></p>
        </form>
    </div>
    <?php
}
/** Ajout natif ou AJAX : nouvelle catégorie toujours à la fin de sa fratrie. */
add_action( 'created_product_cat', static function ( $term_id ) {
    $term = get_term( $term_id, 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) { return; }
    $siblings = get_terms( array(
        'taxonomy' => 'product_cat', 'parent' => (int) $term->parent, 'hide_empty' => false,
    ) );
    $max = 0;
    if ( is_array( $siblings ) ) {
        foreach ( $siblings as $sibling ) {
            if ( (int) $sibling->term_id === (int) $term_id ) { continue; }
            $max = max( $max, (int) get_term_meta( $sibling->term_id, 'order', true ) );
        }
    }
    update_term_meta( (int) $term_id, 'order', $max + 1 );
}, 99 );

function deprat_category_tree_ajax_guard() {
    if ( ! current_user_can( 'manage_product_terms' ) || ! taxonomy_exists( 'product_cat' ) ) {
        wp_send_json_error( array( 'message' => 'Vous ne disposez pas des droits requis.' ), 403 );
    }
    if ( ! check_ajax_referer( 'deprat_categories_tree', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Votre session a expiré. Rechargez la page.' ), 403 );
    }
}
function deprat_category_tree_ajax_create() {
    deprat_category_tree_ajax_guard();
    $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
    $parent = isset( $_POST['parent'] ) && is_scalar( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
    if ( ! $name || strlen( $name ) > 160 ) {
        wp_send_json_error( array( 'message' => 'Renseignez un nom de catégorie valide.' ), 400 );
    }
    if ( $parent && ! term_exists( $parent, 'product_cat' ) ) {
        wp_send_json_error( array( 'message' => 'Cette catégorie parente est introuvable.' ), 400 );
    }
    if ( term_exists( $name, 'product_cat', $parent ) ) {
        wp_send_json_error( array( 'message' => 'Cette catégorie existe déjà sous ce parent.' ), 409 );
    }
    $created = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
    if ( is_wp_error( $created ) ) {
        wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
    }
    $id = (int) $created['term_id'];
    wp_send_json_success( array( 'id' => $id, 'name' => $name, 'parent' => $parent, 'count' => 0 ) );
}
add_action( 'wp_ajax_deprat_category_tree_create', 'deprat_category_tree_ajax_create' );

/** Validation exhaustive AVANT toute modification de la hiérarchie WordPress. */
function deprat_category_tree_validate( $tree, $allowed_ids ) {
    if ( ! is_array( $tree ) || count( $allowed_ids ) > 2000 ) {
        return new WP_Error( 'invalid_tree', 'L’arborescence est trop importante ou mal formée.' );
    }
    $allowed = array_fill_keys( array_map( 'intval', $allowed_ids ), true );
    $parents = array();
    $orders = array();
    $visited = array();
    $walk = static function ( $nodes, $parent, $depth ) use ( &$walk, &$parents, &$orders, &$visited, $allowed ) {
        if ( ! is_array( $nodes ) || $depth > 30 ) { return new WP_Error( 'invalid_depth', 'La hiérarchie doit contenir moins de 30 niveaux.' ); }
        foreach ( $nodes as $position => $node ) {
            if ( ! is_array( $node ) || ! isset( $node['id'], $node['children'] )
                || ! is_scalar( $node['id'] ) || ! is_array( $node['children'] ) ) {
                return new WP_Error( 'invalid_node', 'Une catégorie transmise est invalide.' );
            }
            $id = filter_var( $node['id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
            if ( ! $id || ! isset( $allowed[ $id ] ) || isset( $visited[ $id ] ) ) {
                return new WP_Error( 'duplicate_node', 'Une catégorie manque, est répétée ou ne peut pas être déplacée.' );
            }
            $visited[ $id ] = true;
            $parents[ $id ] = (int) $parent;
            $orders[ $id ] = (int) $position + 1;
            $error = $walk( $node['children'], $id, $depth + 1 );
            if ( is_wp_error( $error ) ) { return $error; }
        }
        return true;
    };
    $error = $walk( $tree, 0, 0 );
    if ( is_wp_error( $error ) ) { return $error; }
    if ( count( $visited ) !== count( $allowed ) ) {
        return new WP_Error( 'missing_terms', 'Certaines catégories n’ont pas été transmises. Rechargez la page.' );
    }
    return array( 'parents' => $parents, 'orders' => $orders );
}
function deprat_category_tree_ajax_save() {
    deprat_category_tree_ajax_guard();
    $raw = isset( $_POST['tree'] ) && is_scalar( $_POST['tree'] )
        ? wp_unslash( $_POST['tree'] ) : '';
    if ( ! is_string( $raw ) || strlen( $raw ) > 200000 ) {
        wp_send_json_error( array( 'message' => 'La structure enregistrée est invalide.' ), 400 );
    }
    $tree = json_decode( $raw, true );
    $terms = deprat_category_tree_terms();
    $ids = array_map( static function ( $term ) { return (int) $term->term_id; }, $terms );
    $validated = deprat_category_tree_validate( $tree, $ids );
    if ( is_wp_error( $validated ) ) {
        wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
    }
    // Le mapping de rubriques est facultatif et ne crée jamais de terme WooCommerce.
    // Toutes les clés doivent correspondre à des IDs réels et à nos titres autorisés.
    $section_changes = array();
    if ( isset( $_POST['sections'] ) ) {
        $raw_sections = is_scalar( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : '';
        $requested_sections = is_string( $raw_sections ) ? json_decode( $raw_sections, true ) : null;
        if ( ! is_array( $requested_sections ) || count( $requested_sections ) > 2000 ) {
            wp_send_json_error( array( 'message' => 'Affectation des rubriques invalide.' ), 400 );
        }
        $valid_ids = array_fill_keys( $ids, true );
        $definitions = deprat_catalog_section_definitions();
        foreach ( $requested_sections as $term_id => $section ) {
            $id = filter_var( $term_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
            if ( ! $id || ! isset( $valid_ids[ $id ] ) || ! is_string( $section )
                || ( '' !== $section && ! isset( $definitions[ $section ] ) ) ) {
                wp_send_json_error( array( 'message' => 'Une rubrique ne correspond pas aux catégories existantes.' ), 400 );
            }
            $section_changes[ $id ] = $section;
        }
    }
    $parents = $validated['parents'];
    // Détacher d'abord les parents modifiés pour éviter les cycles transitoires
    // (ex. A/B deviennent B/A) pendant l'application séquentielle WP.
    foreach ( $terms as $term ) {
        $id = (int) $term->term_id;
        if ( (int) $term->parent !== (int) $parents[ $id ] && (int) $term->parent !== 0 ) {
            $updated = wp_update_term( $id, 'product_cat', array( 'parent' => 0 ) );
            if ( is_wp_error( $updated ) ) {
                wp_send_json_error( array( 'message' => $updated->get_error_message() ), 500 );
            }
        }
    }
    foreach ( $parents as $id => $parent ) {
        $term = get_term( $id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( array( 'message' => 'Une catégorie a disparu pendant l’enregistrement.' ), 409 );
        }
        if ( (int) $term->parent !== (int) $parent ) {
            $updated = wp_update_term( $id, 'product_cat', array( 'parent' => (int) $parent ) );
            if ( is_wp_error( $updated ) ) {
                wp_send_json_error( array( 'message' => $updated->get_error_message() ), 500 );
            }
        }
        // Compatibilité avec le tri natif WooCommerce.
        wc_set_term_order( (int) $id, (int) $validated['orders'][ $id ], 'product_cat' );
        clean_term_cache( (int) $id, 'product_cat' );
    }
    foreach ( $section_changes as $id => $section ) {
        if ( '' === $section ) { delete_term_meta( $id, '_deprat_catalog_section' ); }
        else { update_term_meta( $id, '_deprat_catalog_section', $section ); }
    }
    delete_transient( 'deprat_catalog_all_ix' );
    update_option( 'deprat_category_tree_saved_at', time(), false );
    wp_send_json_success( array( 'message' => 'L’arborescence DEPRAT est enregistrée.' ) );
}
add_action( 'wp_ajax_deprat_category_tree_save', 'deprat_category_tree_ajax_save' );
