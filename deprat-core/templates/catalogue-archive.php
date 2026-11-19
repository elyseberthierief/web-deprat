<?php
/**
 * Archives DEPRAT / WooCommerce.
 * Aucun menu WordPress ni en-tête du thème n'est remplacé.
 */
defined( 'ABSPATH' ) || exit;
get_header( 'shop' );
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$is_hub = function_exists( 'is_shop' ) && is_shop();
$is_all = $is_hub && deprat_catalog_show_all_products();
$category = ! $is_hub ? get_queried_object() : null;
$roots = deprat_catalog_root_categories();
?>
<main id="primary" class="deprat-catalog" aria-label="Catalogue professionnel DEPRAT">
    <nav class="deprat-catalog-breadcrumb" aria-label="Fil d’Ariane">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
        <span aria-hidden="true">/</span>
        <?php if ( $is_hub && ! $is_all ) : ?>
            <strong aria-current="page">Nos composants</strong>
        <?php elseif ( $is_all ) : ?>
            <a href="<?php echo esc_url( $shop_url ); ?>">Nos composants</a>
            <span aria-hidden="true">/</span>
            <strong aria-current="page">Tous les composants</strong>
        <?php else : ?>
            <a href="<?php echo esc_url( $shop_url ); ?>">Nos composants</a>
            <span aria-hidden="true">/</span>
            <strong aria-current="page"><?php echo esc_html( single_term_title( '', false ) ); ?></strong>
        <?php endif; ?>
    </nav>
    <header class="deprat-catalog-header<?php echo ! $is_hub ? ' deprat-catalog-header--category' : ''; ?>">
        <div class="deprat-catalog-title-band">
            <h1><?php echo $is_all ? 'Tous les composants' : ( $is_hub ? 'Nos composants' : esc_html( single_term_title( '', false ) ) ); ?></h1>
        </div>
        <?php
        // La description renseignée dans Produits > Catégories est la source unique.
        // Aucune phrase générique ne remplace une description vide.
        $category_description = ! $is_hub && ! $is_all && $category && isset( $category->description )
            ? trim( (string) $category->description ) : '';
        ?>
        <?php if ( $category_description ) : ?>
            <div class="deprat-catalog-category-description"><?php echo wp_kses_post( wpautop( $category_description ) ); ?></div>
        <?php elseif ( $is_all || $is_hub ) : ?>
            <p><?php echo $is_all
                ? 'Retrouvez toutes les références disponibles dans notre catalogue professionnel, quelle que soit leur catégorie.'
                : 'L’ensemble de nos composants pour volets roulants, stores et portes de garage : tubes, liaisons, manœuvres, lames, coulisses, supports et accessoires.'; ?></p>
        <?php endif; ?>
    </header>
    <?php if ( ! $is_hub || $is_all ) : ?>
    <nav class="deprat-catalog-nav" aria-label="Catégories des composants">
        <?php foreach ( $roots as $root ) :
            $url = get_term_link( $root );
            if ( is_wp_error( $url ) ) { continue; }
            $active = ! $is_hub && $category && (
                (int) $category->term_id === (int) $root->term_id
                || in_array( (int) $root->term_id, array_map( 'intval', get_ancestors( $category->term_id, 'product_cat' ) ), true )
            );
            ?>
            <a class="deprat-catalog-nav-item <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html( $root->name ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php
    // Dans une famille comme « Tubes », afficher les sous-catégories directes
    // sous forme de cartes illustrées, avant les produits et filtres.
    // Les sous-niveaux ne sont pas mélangés avec ceux de la famille parente.
    $direct_children = ! $is_hub && $category && isset( $category->term_id )
        ? deprat_category_tree_front_children( (int) $category->term_id ) : array();
    ?>
    <?php if ( $direct_children ) : ?>
        <section class="deprat-catalog-children" aria-labelledby="deprat-catalog-children-title">
            <div class="deprat-catalog-children-heading">
                <h2 id="deprat-catalog-children-title">Sous-catégories de <?php echo esc_html( $category->name ); ?></h2>
                <p>Choisissez une famille de composants pour consulter ses références.</p>
            </div>
            <div class="deprat-catalog-children-grid">
                <?php foreach ( $direct_children as $child ) :
                    $child_url = get_term_link( $child );
                    if ( is_wp_error( $child_url ) ) { continue; }
                ?>
                    <a class="deprat-catalog-child-card" href="<?php echo esc_url( $child_url ); ?>">
                        <span class="deprat-catalog-child-icon" aria-hidden="true"><?php echo deprat_catalog_category_icon( $child ); // SVG DEPRAT interne ou image validée par WP. ?></span>
                        <span class="deprat-catalog-child-name"><?php echo esc_html( $child->name ); ?></span>
                        <span class="deprat-catalog-child-arrow" aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ( $is_hub && ! $is_all ) : ?>
        <section class="deprat-catalog-hub" aria-label="Familles de composants DEPRAT">
            <?php deprat_catalog_render_sections( 'page' ); ?>
            <div class="deprat-catalog-hub-footer">
                <a class="deprat-catalog-hub-viewall" href="<?php echo esc_url( deprat_catalog_all_products_url() ); ?>">Voir tous les composants <span aria-hidden="true">→</span></a>
            </div>
            <?php if ( ! $roots ) : ?><p>Les catégories WooCommerce existantes apparaîtront ici lorsqu’elles seront renseignées.</p><?php endif; ?>
        </section>
    <?php elseif ( $is_all || ( $category && isset( $category->term_id ) ) ) :
        $index = $is_all ? deprat_catalog_all_products_index() : deprat_catalog_category_index( $category );
        $filters = deprat_catalog_filters();
        $products = deprat_catalog_query( $is_all ? null : $category, $index, $filters );
        $material_facets = array();
        $shape_facets = array();
        foreach ( $index as $spec ) {
            if ( $spec['material'] ) { $material_facets[ sanitize_title( $spec['material'] ) ] = $spec['material']; }
            if ( $spec['shape'] ) { $shape_facets[ sanitize_title( $spec['shape'] ) ] = $spec['shape']; }
        }
        asort( $material_facets );
        asort( $shape_facets );
        $feature_facets = deprat_catalog_feature_facets( $index );
        $current_url = $is_all ? deprat_catalog_all_products_url() : get_term_link( $category );
        ?>
        <div class="deprat-catalog-layout">
            <aside class="deprat-catalog-sidebar" aria-label="Filtres des produits">
                <form method="get" action="<?php echo esc_url( $current_url ); ?>" class="deprat-catalog-filter-form" data-catalog-filters>
                    <?php // Maintenir la liste complète ou la catégorie après un filtre GET. ?>
                    <?php if ( $is_all ) : ?>
                        <input type="hidden" name="deprat_all" value="1">
                        <?php foreach ( deprat_catalog_shop_route_params() as $route_key => $route_value ) : ?>
                            <input type="hidden" name="<?php echo esc_attr( $route_key ); ?>" value="<?php echo esc_attr( $route_value ); ?>">
                        <?php endforeach; ?>
                    <?php elseif ( false !== strpos( $current_url, '?' ) ) : ?>
                        <input type="hidden" name="product_cat" value="<?php echo esc_attr( $category->slug ); ?>">
                    <?php endif; ?>
                    <h2><span aria-hidden="true">☷</span> Filtres <small>par famille</small></h2>
                    <?php if ( $material_facets ) : ?>
                        <fieldset>
                            <legend>Matière</legend>
                            <label class="deprat-filter-choice">
                                <input type="radio" name="deprat_material" value="" <?php checked( $filters['material'], '' ); ?>>
                                <span>Toutes les matières</span>
                            </label>
                            <?php foreach ( $material_facets as $slug => $value ) : ?>
                                <label class="deprat-filter-choice">
                                    <input type="radio" name="deprat_material" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $filters['material'], $slug ); ?>>
                                    <span><?php echo esc_html( $value ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>
                    <?php if ( $shape_facets ) : ?>
                        <fieldset>
                            <legend>Type / forme</legend>
                            <label class="deprat-filter-choice"><input type="radio" name="deprat_shape" value="" <?php checked( $filters['shape'], '' ); ?>> Toutes les formes</label>
                            <?php foreach ( $shape_facets as $slug => $value ) : ?>
                                <label class="deprat-filter-choice">
                                    <input type="radio" name="deprat_shape" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $filters['shape'], $slug ); ?>>
                                    <span><?php echo esc_html( $value ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>
                    <?php if ( $feature_facets ) : ?>
                        <div class="deprat-filter-feature-heading">Autres caractéristiques</div>
                        <?php foreach ( $feature_facets as $key => $facet ) : ?>
                            <label class="deprat-filter-feature">
                                <span><?php echo esc_html( $facet['label'] ); ?></span>
                                <select name="deprat_feature[<?php echo esc_attr( $key ); ?>]">
                                    <option value="">Toutes les valeurs</option>
                                    <?php foreach ( $facet['options'] as $slug => $value ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( isset( $filters['features'][ $key ] ) ? $filters['features'][ $key ] : '', $slug ); ?>>
                                            <?php echo esc_html( $value ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php
                    foreach ( array( 'length' => 'Longueur (mm)', 'diameter' => 'Diamètre (mm)', 'thickness' => 'Épaisseur (mm)' ) as $dimension => $label ) :
                        $exists = false;
                        foreach ( $index as $spec ) { if ( ! empty( $spec[$dimension] ) ) { $exists = true; break; } }
                        if ( ! $exists ) { continue; }
                        ?>
                        <fieldset>
                            <legend><?php echo esc_html( $label ); ?></legend>
                            <div class="deprat-catalog-range">
                                <label><span>Minimum</span><input type="number" min="0" step="0.01" name="deprat_<?php echo esc_attr( $dimension ); ?>_min" placeholder="Min" value="<?php echo $filters[$dimension.'_min'] > 0 ? esc_attr( $filters[$dimension.'_min'] ) : ''; ?>"></label>
                                <span aria-hidden="true">–</span>
                                <label><span>Maximum</span><input type="number" min="0" step="0.01" name="deprat_<?php echo esc_attr( $dimension ); ?>_max" placeholder="Max" value="<?php echo $filters[$dimension.'_max'] > 0 ? esc_attr( $filters[$dimension.'_max'] ) : ''; ?>"></label>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <fieldset>
                        <legend>Disponibilité</legend>
                        <label class="deprat-filter-choice"><input type="checkbox" name="deprat_stock" value="1" <?php checked( $filters['stock'] ); ?>><span>En stock</span></label>
                        <label class="deprat-filter-choice"><input type="checkbox" name="deprat_view3d" value="1" <?php checked( $filters['view3d'] ); ?>><span>Vue 360° disponible</span></label>
                    </fieldset>
                    <?php if ( isset( $_GET['deprat_sort'] ) && is_scalar( $_GET['deprat_sort'] ) ) : ?>
                        <input type="hidden" name="deprat_sort" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['deprat_sort'] ) ) ); ?>">
                    <?php endif; ?>
                    <button class="deprat-catalog-apply" type="submit">Appliquer les filtres</button>
                    <a class="deprat-catalog-reset" href="<?php echo esc_url( $current_url ); ?>">Réinitialiser les filtres</a>
                </form>
            </aside>
            <section class="deprat-catalog-results" aria-label="<?php echo esc_attr( $is_all ? 'Tous les composants détachées' : 'Produits de la catégorie ' . $category->name ); ?>">
                <div class="deprat-catalog-toolbar">
                    <strong><?php echo esc_html( sprintf( _n( '%d résultat affiché', '%d résultats affichés', $products->found_posts, 'deprat' ), $products->found_posts ) ); ?></strong>
                    <div class="deprat-catalog-toolbar-controls">
                        <form method="get" action="<?php echo esc_url( $current_url ); ?>" class="deprat-catalog-sort">
                            <?php if ( $is_all ) : ?>
                                <input type="hidden" name="deprat_all" value="1">
                                <?php foreach ( deprat_catalog_shop_route_params() as $route_key => $route_value ) : ?>
                                    <input type="hidden" name="<?php echo esc_attr( $route_key ); ?>" value="<?php echo esc_attr( $route_value ); ?>">
                                <?php endforeach; ?>
                            <?php elseif ( false !== strpos( $current_url, '?' ) ) : ?>
                                <input type="hidden" name="product_cat" value="<?php echo esc_attr( $category->slug ); ?>">
                            <?php endif; ?>
                            <?php foreach ( $filters['features'] as $feature_key => $feature_value ) : ?>
                                <input type="hidden" name="deprat_feature[<?php echo esc_attr( $feature_key ); ?>]" value="<?php echo esc_attr( $feature_value ); ?>">
                            <?php endforeach; ?>
                            <?php foreach ( $_GET as $key => $value ) :
                                if ( ! is_scalar( $value ) || 'deprat_sort' === $key || 'deprat_page' === $key || ! preg_match( '/^deprat_(?:material|shape|length_min|length_max|diameter_min|diameter_max|thickness_min|thickness_max|stock|view3d)$/', $key ) ) { continue; }
                                ?>
                                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $value ) ) ); ?>">
                            <?php endforeach; ?>
                            <label for="deprat-catalog-sort-control">Trier les produits</label>
                            <select name="deprat_sort" id="deprat-catalog-sort-control" data-sort-select>
                                <option value="default">Nom A → Z</option>
                                <option value="name_desc" <?php selected( isset( $_GET['deprat_sort'] ) ? sanitize_key( wp_unslash( $_GET['deprat_sort'] ) ) : '', 'name_desc' ); ?>>Nom Z → A</option>
                                <option value="recent" <?php selected( isset( $_GET['deprat_sort'] ) ? sanitize_key( wp_unslash( $_GET['deprat_sort'] ) ) : '', 'recent' ); ?>>Nouveautés</option>
                            </select>
                        </form>
                        <div class="deprat-catalog-display" role="group" aria-label="Mode d’affichage">
                            <button type="button" class="is-active" data-view="grid" aria-pressed="true" title="Vue grille" aria-label="Vue grille">▦</button>
                            <button type="button" data-view="list" aria-pressed="false" title="Vue liste" aria-label="Vue liste">☷</button>
                        </div>
                    </div>
                </div>
                <?php if ( $products->have_posts() ) : ?>
                    <div class="deprat-catalog-products" data-catalog-products>
                        <?php while ( $products->have_posts() ) : $products->the_post();
                            $product = wc_get_product( get_the_ID() );
                            if ( $product && $product->is_visible() ) { deprat_catalog_render_card( $product ); }
                        endwhile; ?>
                    </div>
                    <?php
                    $max = (int) $products->max_num_pages;
                    if ( $max > 1 ) {
                        $current_page = isset( $_GET['deprat_page'] ) ? max( 1, absint( $_GET['deprat_page'] ) ) : 1;
                        $args = array();
                        foreach ( $_GET as $key => $value ) {
                            if ( 'deprat_page' === $key || ! is_scalar( $value ) || ! preg_match( '/^deprat_(?:sort|material|shape|length_min|length_max|diameter_min|diameter_max|thickness_min|thickness_max|stock|view3d)$/', $key ) ) { continue; }
                            $args[$key] = sanitize_text_field( wp_unslash( $value ) );
                        }
                        if ( $filters['features'] ) { $args['deprat_feature'] = $filters['features']; }
                        $base_url = add_query_arg( array_merge( $args, array( 'deprat_page' => '%#%' ) ), $current_url );
                        echo '<nav class="deprat-catalog-pagination" aria-label="Pagination des produits">';
                        // paginate_links renvoie du HTML : filtrer les balises
                        // autorisées sans casser les liens de pagination WordPress.
                        // Faux positif « echoed-request » : les paramètres GET
                        // sont filtrés, la base URL échappée, et le HTML issu de
                        // paginate_links est limité par wp_kses_post.
                        echo wp_kses_post( paginate_links( array( 'base' => str_replace( '%25%23%25', '%#%', esc_url( $base_url ) ), 'format' => '', 'total' => $max, 'current' => $current_page, 'type' => 'list', 'prev_text' => '‹ Précédent', 'next_text' => 'Suivant ›' ) ) ); // nosemgrep: php.lang.security.injection.echoed-request.echoed-request
                        echo '</nav>';
                    }
                    ?>
                <?php else : ?>
                    <div class="deprat-catalog-no-results">
                        <h2>Aucun produit pour ces filtres</h2>
                        <p>Essayez de modifier les critères de recherche ou de les réinitialiser.</p>
                        <a href="<?php echo esc_url( $current_url ); ?>"><?php echo esc_html( $is_all ? 'Voir tous les composants' : 'Voir tous les produits ' . $category->name ); ?></a>
                    </div>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </section>
        </div>
    <?php endif; ?>
    <dialog class="deprat-catalog-package-dialog" data-catalog-package-dialog aria-labelledby="deprat-package-dialog-title">
        <div class="deprat-package-dialog-heading">
            <div>
                <span class="deprat-package-dialog-eyebrow">DEPRAT · DEMANDE DE DEVIS</span>
                <h2 id="deprat-package-dialog-title">Choisir un conditionnement</h2>
            </div>
            <button type="button" class="deprat-package-dialog-close" data-package-dialog-close aria-label="Fermer sans ajouter">×</button>
        </div>
        <div class="deprat-package-dialog-product">
            <img data-package-dialog-image alt="" hidden>
            <div class="deprat-package-dialog-product-info">
                <p class="deprat-package-dialog-ref" data-package-dialog-ref></p>
                <h3 data-package-dialog-product-name></h3>
                <p data-package-dialog-material hidden></p>
                <p data-package-dialog-description hidden></p>
                <a data-package-dialog-link href="#">Voir la fiche détaillée →</a>
            </div>
        </div>
        <fieldset class="deprat-package-dialog-fieldset">
            <legend>Quel conditionnement souhaitez-vous ?</legend>
            <p class="deprat-package-dialog-help">Sélectionnez le conditionnement pour cette référence.</p>
            <div class="deprat-package-dialog-options" data-package-dialog-options></div>
        </fieldset>
        <p class="deprat-package-dialog-warning" data-package-dialog-warning hidden><?php echo esc_html( deprat_packaging_surcharge_warning() ); ?></p>
        <div class="deprat-package-dialog-bottom">
            <label class="deprat-package-dialog-qty-label">
                <span data-package-dialog-qty-label>Nombre de conditionnements</span>
                <span class="deprat-package-dialog-stepper">
                    <button type="button" data-package-dialog-minus aria-label="Diminuer la quantité">−</button>
                    <input type="number" data-package-dialog-quantity min="1" step="1" max="999999" value="1" inputmode="numeric">
                    <button type="button" data-package-dialog-plus aria-label="Augmenter la quantité">+</button>
                </span>
            </label>
            <p class="deprat-package-dialog-summary" data-package-dialog-summary aria-live="polite"></p>
        </div>
        <p class="deprat-package-dialog-feedback" data-package-dialog-feedback role="status" aria-live="polite" hidden></p>
        <div class="deprat-package-dialog-actions">
            <button type="button" class="deprat-package-dialog-cancel" data-package-dialog-close>Annuler</button>
            <button type="button" class="deprat-package-dialog-confirm" data-package-dialog-confirm>Ajouter à ma demande</button>
        </div>
    </dialog>
    <dialog class="deprat-catalog-model-dialog" data-catalog-model-dialog aria-label="Vue 3D du produit">
        <div class="deprat-catalog-model-header">
            <strong data-model-title>Vue 360°</strong>
            <button type="button" data-model-close aria-label="Fermer la vue 3D">×</button>
        </div>
        <div class="deprat-catalog-3d-stage" data-3d-background="light">
            <model-viewer data-model-viewer camera-controls auto-rotate shadow-intensity="0.7" loading="lazy" reveal="auto" interaction-prompt="auto"></model-viewer>
            <div class="deprat-catalog-3d-outline" data-model-outline hidden></div>
        </div>
        <div class="deprat-catalog-3d-controls" role="group" aria-label="Réglages du modèle 3D">
            <button type="button" data-model-render="texture" class="is-active" aria-pressed="true">Pièce texturée</button>
            <button type="button" data-model-render="contours" aria-pressed="false">Contours</button>
            <button type="button" data-model-background aria-label="Changer la couleur du fond">Fond : automatique</button>
        </div>
        <p>Faites glisser la pièce pour la faire tourner ; utilisez la molette pour zoomer.</p>
    </dialog>
</main>
<?php get_footer( 'shop' ); ?>
