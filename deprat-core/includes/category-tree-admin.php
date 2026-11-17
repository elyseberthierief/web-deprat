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
    wp_enqueue_style( 'deprat-category-tree', plugins_url( 'assets/css/category-tree-admin.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.44' );
    wp_enqueue_style( 'deprat-catalog-sections-editor',
        plugins_url( 'assets/css/catalogue-sections.css', dirname( __DIR__ ) . '/deprat-core.php' ),
        array( 'deprat-category-tree' ), '0.1.44' );
    wp_enqueue_script( 'deprat-category-tree', plugins_url( 'assets/js/category-tree-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'jquery-ui-sortable' ), '0.1.44', true );
    wp_enqueue_script( 'deprat-category-tree-preview',
        plugins_url( 'assets/js/category-tree-preview.js', dirname( __DIR__ ) . '/deprat-core.php' ),
        array( 'deprat-category-tree', 'jquery', 'jquery-ui-sortable' ), '0.1.44', true );
    if ( current_user_can( 'upload_files' ) ) { wp_enqueue_media(); }
    wp_localize_script( 'deprat-category-tree', 'depratCategoryTree', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'deprat_categories_tree' ),
        'editBase' => admin_url( 'term.php?taxonomy=product_cat&post_type=product&tag_ID=' ),
        'maxDepth' => 30,
        'sectionLabels' => deprat_catalog_section_definitions(),
        'sectionSettings' => deprat_catalog_section_registry(),
        'sectionPalette' => deprat_catalog_section_palette(),
        'canUploadIcons' => current_user_can( 'upload_files' ),
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
    ?>
    <li class="deprat-tree-item" data-tree-item data-term-id="<?php echo esc_attr( $id ); ?>"
        data-section-guess="<?php echo esc_attr( deprat_catalog_section_guess( $term ) ); ?>">
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
                <button type="button" class="button" data-tree-edit-category
                    aria-label="<?php echo esc_attr( 'Modifier la catégorie ' . $term->name ); ?>">✎ Modifier</button>
            </div>
        </div>
        <?php deprat_category_tree_render_list( $id, $terms, $depth + 1 ); ?>
    </li>
    <?php
}
/**
 * Reproduit la mise en page du vrai méga-menu, avec les mêmes icônes
 * SVG et classes CSS. Les rubriques sont VISUELLES, pas des termes WP.
 * Les zones vides restent affichées en admin pour recevoir un glisser-déposer.
 * Les catégories sont ajoutées en JS depuis l'arborescence WooCommerce.
 */
function deprat_category_tree_render_visual_section( $key, $title ) {
    ?>
    <section class="deprat-section" data-preview-section="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
        <div class="deprat-section-heading">
            <button type="button" class="deprat-section-drag-handle" data-section-drag-handle
                aria-label="<?php echo esc_attr( 'Déplacer la section ' . $title ); ?>" title="Glisser pour réorganiser la section">⠿</button>
            <span class="deprat-section-icon-wrap"><?php echo deprat_catalog_section_icon( $key ); // SVG interne ou média raster validé. ?></span>
            <h2><?php echo esc_html( $title ); ?></h2>
            <button type="button" class="deprat-section-edit" data-preview-edit-section
                aria-label="<?php echo esc_attr( 'Modifier la section ' . $title ); ?>" title="Modifier le titre et l’icône">✎</button>
            <details class="deprat-section-create-menu" data-preview-create-menu>
                <summary aria-label="<?php echo esc_attr( 'Ajouter une catégorie dans ' . $title ); ?>" title="<?php echo esc_attr( 'Ajouter dans ' . $title ); ?>">
                    <span aria-hidden="true">+</span>
                </summary>
                <div class="deprat-section-create-dropdown">
                    <button type="button" data-preview-add-category><span aria-hidden="true">+</span> Créer une catégorie</button>
                    <button type="button" data-preview-add-subcategory><span aria-hidden="true">↳</span> Créer une sous-catégorie</button>
                </div>
            </details>
        </div>
        <ul class="deprat-section-categories deprat-preview-list" data-preview-list
            data-section-key="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( 'Catégories : ' . $title ); ?>"></ul>
    </section>
    <?php
}
function deprat_category_tree_admin_visual_menu() {
    ?>
    <section class="deprat-preview-shell" data-tree-preview aria-label="Prévisualisation modifiable du méga-menu">
        <div class="deprat-preview-bar">
            <div>
                <strong>Nos composants</strong>
                <p>La même présentation que sur le site : créez vos sections, choisissez leurs icônes et déplacez les blocs et les catégories.</p>
            </div>
            <span class="deprat-preview-help">⠿ Déplacer les sections et les catégories · + Ajouter</span>
        </div>
        <div class="deprat-catalog-sections deprat-catalog-sections--admin" data-preview-grid>
            <?php foreach ( deprat_catalog_section_definitions() as $key => $title ) : ?>
                <?php deprat_category_tree_render_visual_section( $key, $title ); ?>
            <?php endforeach; ?>
        </div>
        <p class="deprat-preview-footnote">Les sections sont de simples regroupements visuels : elles ne créent pas de catégorie WooCommerce. Une section vide reste disponible en administration, mais n’apparaît pas sur le site.</p>
    </section>
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
                <h1>Organiser le méga-menu</h1>
                <p>Modifiez directement le menu « Nos composants » : les catégories que vous déplacez ici seront réorganisées sur le site après enregistrement.</p>
            </div>
            <div class="deprat-tree-heading-actions">
                <a href="<?php echo esc_url( wp_nonce_url(
                    admin_url( 'admin-post.php?action=deprat_export_megamenu_pdf' ),
                    'deprat_export_megamenu_pdf'
                ) ); ?>" class="button deprat-tree-export-pdf" data-tree-export-pdf
                    target="_blank" rel="noopener" title="Télécharger le PDF de l’arborescence enregistrée">
                    <span aria-hidden="true">↓</span> Exporter l’arborescence en PDF
                </a>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button" target="_blank" rel="noopener">Voir le catalogue ↗</a>
                <button type="button" class="button button-primary" data-tree-save>Enregistrer l’arborescence</button>
            </div>
        </div>

        <div class="deprat-tree-instructions">
            <strong>Édition directe du méga-menu :</strong> déplacez les sections avec leur poignée ⠿, modifiez leur titre et leur icône avec ✎, puis faites glisser vos catégories d’une rubrique à l’autre.
            Les chevrons déplient les sous-catégories ; ↳ ↰ changent le niveau. Les titres de rubriques et leurs icônes ne sont pas cliquables.
            Cliquez sur <strong>Enregistrer</strong> pour répercuter les modifications sur le site.
        </div>
        <div class="deprat-tree-toolbar">
            <button type="button" class="button button-primary" data-section-add>+ Ajouter une section</button>
            <button type="button" class="button button-secondary" data-tree-create-root>+ Nouvelle catégorie principale</button>
            <span data-tree-unsaved class="deprat-tree-unsaved" hidden>Modifications non enregistrées</span>
            <span data-tree-status role="status" aria-live="polite"></span>
        </div>
        <?php deprat_category_tree_admin_visual_menu(); ?>
        <details class="deprat-tree-advanced" data-tree-advanced>
            <summary>Gestion avancée de l’arborescence (optionnelle)</summary>
            <p>Retrouvez ici la vue hiérarchique classique pour organiser des sous-catégories complexes, ou modifier individuellement les catégories. Les changements sont immédiatement reflétés dans le menu ci-dessus.</p>
            <div class="deprat-tree-area">
                <?php deprat_category_tree_render_list( 0, $terms ); ?>
            </div>
        </details>
        <div class="deprat-tree-bottom">
            <p>Aucune rubrique visuelle ne crée de catégorie WooCommerce. Le menu et la boutique utilisent les mêmes références et sous-catégories. « Non classé » reste géré séparément.</p>
            <button type="button" class="button button-primary" data-tree-save>Enregistrer le méga-menu</button>
        </div>
        <div class="deprat-tree-create-overlay" data-tree-create-overlay hidden>
            <form class="deprat-tree-create-form" data-tree-create-form role="dialog"
                aria-modal="true" aria-labelledby="deprat-tree-create-title" hidden>
                <button type="button" class="deprat-tree-create-close" data-tree-create-cancel
                    aria-label="Fermer sans créer">×</button>
                <span class="deprat-tree-create-kicker">CATALOGUE DEPRAT</span>
                <h2 id="deprat-tree-create-title" data-tree-create-title>Nouvelle catégorie</h2>
                <p class="deprat-tree-create-context" data-tree-create-context hidden></p>
                <input type="hidden" name="section" value="" data-tree-create-section>
                <input type="hidden" name="parent" value="0" data-tree-create-parent>
                <label class="deprat-tree-create-parent-picker" data-tree-create-parent-picker hidden>
                    Catégorie parente
                    <select data-tree-create-parent-select></select>
                </label>
                <label>Nom de la catégorie
                    <input type="text" name="name" maxlength="160" required autocomplete="off"
                        placeholder="Saisissez le nom de la catégorie" data-tree-create-name>
                </label>
                <p class="deprat-tree-create-error" data-tree-create-error role="alert" hidden></p>
                <div class="deprat-tree-create-actions">
                    <button type="button" class="button" data-tree-create-cancel>Annuler</button>
                    <button type="submit" class="button button-primary" data-tree-create-submit>Créer cette catégorie</button>
                </div>
            </form>
        </div>
        <div class="deprat-tree-create-overlay deprat-tree-category-edit-overlay" data-tree-edit-overlay hidden>
            <form class="deprat-tree-create-form" data-tree-edit-form role="dialog"
                aria-modal="true" aria-labelledby="deprat-tree-edit-title">
                <button type="button" class="deprat-tree-create-close" data-tree-edit-close
                    aria-label="Fermer sans enregistrer">×</button>
                <span class="deprat-tree-create-kicker">CATALOGUE DEPRAT</span>
                <h2 id="deprat-tree-edit-title">Modifier la catégorie</h2>
                <p class="deprat-tree-create-context" data-tree-edit-context></p>
                <input type="hidden" data-tree-edit-id>
                <label>Nom de la catégorie
                    <input type="text" data-tree-edit-name maxlength="160" autocomplete="off" required>
                </label>
                <p class="deprat-tree-create-error" data-tree-edit-error role="alert" hidden></p>
                <div class="deprat-tree-edit-actions">
                    <a class="button" data-tree-edit-full-link href="" rel="noopener">Réglages avancés WooCommerce ↗</a>
                    <div class="deprat-tree-create-actions">
                        <button type="button" class="button" data-tree-edit-close>Annuler</button>
                        <button type="submit" class="button button-primary" data-tree-edit-save>Enregistrer le nom</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="deprat-section-editor-overlay" data-section-editor-overlay hidden>
            <form class="deprat-section-editor-form" data-section-editor-form role="dialog"
                aria-modal="true" aria-labelledby="deprat-section-editor-title">
                <button type="button" class="deprat-section-editor-close" data-section-editor-close aria-label="Fermer">×</button>
                <span class="deprat-tree-create-kicker">CATALOGUE DEPRAT</span>
                <h2 id="deprat-section-editor-title" data-section-editor-title>Ajouter une section</h2>
                <input type="hidden" data-section-editor-key value="">
                <label>Nom de la section
                    <input type="text" data-section-editor-name maxlength="90" autocomplete="off" required
                        placeholder="Ex. Motorisations & accessoires">
                </label>
                <fieldset class="deprat-section-icon-fieldset">
                    <legend>Choisir une icône</legend>
                    <div class="deprat-section-icon-palette" data-section-icon-palette>
                        <?php foreach ( deprat_catalog_section_palette() as $icon_key => $caption ) : ?>
                            <label title="<?php echo esc_attr( $caption ); ?>">
                                <input type="radio" name="deprat-section-icon" value="<?php echo esc_attr( $icon_key ); ?>" <?php checked( 'autres', $icon_key ); ?>>
                                <span class="deprat-section-icon-choice">
                                    <svg class="deprat-section-icon" aria-hidden="true" width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><?php echo deprat_catalog_section_icon_paths()[ $icon_key ]; // Tracés constants. ?></svg>
                                    <small><?php echo esc_html( $caption ); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php if ( current_user_can( 'upload_files' ) ) : ?>
                    <div class="deprat-section-media-picker">
                        <button type="button" class="button" data-section-media-pick>↑ Importer une image (PNG, JPG, WebP)</button>
                        <button type="button" class="button" data-section-media-clear hidden>Retirer l’image</button>
                        <span data-section-media-preview></span>
                    </div>
                <?php endif; ?>
                <p class="deprat-section-editor-hint">La section n’est pas une catégorie WooCommerce. Les fichiers SVG importés ne sont pas acceptés.</p>
                <p class="deprat-tree-create-error" data-section-editor-error role="alert" hidden></p>
                <div class="deprat-tree-create-actions">
                    <button type="button" class="button" data-section-editor-close>Annuler</button>
                    <button type="submit" class="button button-primary" data-section-editor-save>Enregistrer la section</button>
                </div>
            </form>
        </div>
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
/** Création/édition de titres VISUELS : aucun nouveau terme product_cat. */
function deprat_category_tree_ajax_section_upsert() {
    deprat_category_tree_ajax_guard();
    $title = isset( $_POST['title'] ) && is_scalar( $_POST['title'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) : '';
    $key = isset( $_POST['key'] ) && is_scalar( $_POST['key'] )
        ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
    $icon = isset( $_POST['icon'] ) && is_scalar( $_POST['icon'] )
        ? sanitize_key( wp_unslash( $_POST['icon'] ) ) : 'autres';
    $media_id = isset( $_POST['media_id'] ) && is_scalar( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;
    $registry = deprat_catalog_section_registry();
    if ( '' === $title || strlen( $title ) > 180 ) {
        wp_send_json_error( array( 'message' => 'Le nom de la section est obligatoire (90 caractères maximum).' ), 400 );
    }
    if ( ! isset( deprat_catalog_section_palette()[ $icon ] ) ) {
        wp_send_json_error( array( 'message' => 'Cette icône n’appartient pas à la palette DEPRAT.' ), 400 );
    }
    if ( $media_id && ( ! current_user_can( 'upload_files' ) || ! deprat_catalog_section_media_allowed( $media_id ) ) ) {
        wp_send_json_error( array( 'message' => 'Image invalide : utilisez PNG, JPG ou WebP.' ), 400 );
    }
    if ( $key && ! isset( $registry[ $key ] ) ) {
        wp_send_json_error( array( 'message' => 'Cette section est introuvable.' ), 400 );
    }
    if ( ! $key ) {
        if ( count( $registry ) >= 40 ) {
            wp_send_json_error( array( 'message' => 'La limite de 40 sections est atteinte.' ), 400 );
        }
        do {
            $key = 'custom_' . bin2hex( random_bytes( 6 ) );
        } while ( isset( $registry[ $key ] ) );
    }
    $registry[ $key ] = array( 'title' => $title, 'icon' => $icon, 'media_id' => $media_id );
    update_option( 'deprat_catalog_section_settings_v1', $registry, false );
    ob_start();
    deprat_category_tree_render_visual_section( $key, $title );
    $html = ob_get_clean();
    wp_send_json_success( array( 'key' => $key, 'title' => $title, 'icon' => $icon,
        'media_id' => $media_id, 'html' => $html ) );
}
add_action( 'wp_ajax_deprat_category_tree_section_upsert', 'deprat_category_tree_ajax_section_upsert' );

function deprat_category_tree_ajax_create() {
    deprat_category_tree_ajax_guard();
    $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
    $parent = isset( $_POST['parent'] ) && is_scalar( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
    // Facultatif : seul un nouveau terme RACINE peut être affecté à une
    // rubrique visuelle. Les sous-catégories héritent de leur catégorie parente.
    $section = isset( $_POST['section'] ) && is_scalar( $_POST['section'] )
        ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
    $sections_autorisees = deprat_catalog_section_definitions();
    if ( '' !== $section && ( $parent || ! isset( $sections_autorisees[ $section ] ) ) ) {
        wp_send_json_error( array( 'message' => 'Rubrique de menu invalide.' ), 400 );
    }
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
    if ( '' !== $section ) {
        // La catégorie créée depuis un titre de rubrique reste dans cette
        // rubrique dès sa création, même avant une réorganisation ultérieure.
        update_term_meta( $id, '_deprat_catalog_section', $section );
    }
    wp_send_json_success( array( 'id' => $id, 'name' => $name, 'parent' => $parent, 'count' => 0,
        'section' => $section,
        'sectionGuess' => deprat_catalog_section_guess( get_term( $id, 'product_cat' ) ) ) );
}
add_action( 'wp_ajax_deprat_category_tree_create', 'deprat_category_tree_ajax_create' );
/** Modification rapide d'un nom de catégorie dans la popup du méga-menu. */
function deprat_category_tree_ajax_edit_category() {
    deprat_category_tree_ajax_guard();
    $id = isset( $_POST['term_id'] ) && is_scalar( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
    $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
    if ( ! $id || '' === $name || strlen( $name ) > 160 ) {
        wp_send_json_error( array( 'message' => 'Nom ou catégorie invalide.' ), 400 );
    }
    $term = get_term( $id, 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) {
        wp_send_json_error( array( 'message' => 'Cette catégorie n’existe plus.' ), 404 );
    }
    if ( ! current_user_can( 'edit_term', $id ) ) {
        wp_send_json_error( array( 'message' => 'Vous ne pouvez pas modifier cette catégorie.' ), 403 );
    }
    if ( $term->name !== $name ) {
        $other = term_exists( $name, 'product_cat', (int) $term->parent );
        if ( is_array( $other ) && (int) $other['term_id'] !== $id ) {
            wp_send_json_error( array( 'message' => 'Une catégorie porte déjà ce nom sous ce parent.' ), 409 );
        }
        $updated = wp_update_term( $id, 'product_cat', array( 'name' => $name ) );
        if ( is_wp_error( $updated ) ) {
            wp_send_json_error( array( 'message' => $updated->get_error_message() ), 400 );
        }
        clean_term_cache( $id, 'product_cat' );
        delete_transient( 'deprat_catalog_all_ix' );
    }
    $term = get_term( $id, 'product_cat' );
    wp_send_json_success( array( 'id' => $id, 'name' => $term->name ) );
}
add_action( 'wp_ajax_deprat_category_tree_edit_category', 'deprat_category_tree_ajax_edit_category' );



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
    $section_order = null;
    if ( isset( $_POST['section_order'] ) ) {
        $raw_order = is_scalar( $_POST['section_order'] ) ? wp_unslash( $_POST['section_order'] ) : '';
        $requested_order = is_string( $raw_order ) ? json_decode( $raw_order, true ) : null;
        $section_order = deprat_catalog_section_validate_order( $requested_order );
        if ( is_wp_error( $section_order ) ) {
            wp_send_json_error( array( 'message' => $section_order->get_error_message() ), 400 );
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
    if ( null !== $section_order ) {
        update_option( 'deprat_catalog_section_settings_v1', $section_order, false );
    }
    delete_transient( 'deprat_catalog_all_ix' );
    update_option( 'deprat_category_tree_saved_at', time(), false );
    wp_send_json_success( array( 'message' => 'Le méga-menu DEPRAT est enregistré.' ) );
}
add_action( 'wp_ajax_deprat_category_tree_save', 'deprat_category_tree_ajax_save' );
