<?php
/**
 * Archives DEPRAT / WooCommerce.
 * Aucun menu WordPress ni en-tête du thème n'est remplacé.
 */
defined( 'ABSPATH' ) || exit;
get_header( 'shop' );
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$is_hub = function_exists( 'is_shop' ) && is_shop();
$category = ! $is_hub ? get_queried_object() : null;
$roots = deprat_catalog_root_categories();
?>
<main id="primary" class="deprat-catalog" aria-label="Catalogue professionnel DEPRAT">
    <nav class="deprat-catalog-breadcrumb" aria-label="Fil d’Ariane">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
        <span aria-hidden="true">/</span>
        <?php if ( $is_hub ) : ?>
            <strong aria-current="page">Pièces détachées</strong>
        <?php else : ?>
            <a href="<?php echo esc_url( $shop_url ); ?>">Pièces détachées</a>
            <span aria-hidden="true">/</span>
            <strong aria-current="page"><?php echo esc_html( single_term_title( '', false ) ); ?></strong>
        <?php endif; ?>
    </nav>
    <header class="deprat-catalog-header">
        <h1><?php echo $is_hub ? 'Pièces détachées' : esc_html( single_term_title( '', false ) ); ?></h1>
        <p><?php
            if ( $is_hub ) {
                echo 'Découvrez nos pièces détachées pour volets roulants et portes de garage. Sélectionnez une famille de produits.';
            } else {
                $description = $category && isset( $category->description ) ? wp_strip_all_tags( $category->description ) : '';
                echo esc_html( $description ?: 'Consultez les références DEPRAT de cette catégorie et affinez votre recherche avec les filtres.' );
            }
        ?></p>
    </header>
    <nav class="deprat-catalog-nav" aria-label="Catégories des pièces détachées">
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

    <?php if ( $is_hub ) : ?>
        <section class="deprat-catalog-hub" aria-labelledby="deprat-families-title">
            <h2 id="deprat-families-title">Choisissez une famille de produits</h2>
            <div class="deprat-catalog-hub-grid">
                <?php foreach ( $roots as $root ) :
                    $url = get_term_link( $root );
                    if ( is_wp_error( $url ) ) { continue; }
                    $thumb = absint( get_term_meta( $root->term_id, 'thumbnail_id', true ) );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="deprat-catalog-hub-card">
                        <span class="deprat-catalog-hub-image">
                            <?php if ( $thumb ) : ?>
                                <?php echo wp_get_attachment_image( $thumb, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy', 'alt' => $root->name ) ); ?>
                            <?php else : ?>
                                <span aria-hidden="true" class="deprat-catalog-hub-symbol">▤</span>
                            <?php endif; ?>
                        </span>
                        <strong><?php echo esc_html( $root->name ); ?></strong>
                        <span class="deprat-catalog-hub-link">Voir les produits <span aria-hidden="true">›</span></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ( ! $roots ) : ?><p>Créez les catégories de produits dans WooCommerce pour les afficher ici.</p><?php endif; ?>
        </section>
    <?php elseif ( $category && isset( $category->term_id ) ) :
        $index = deprat_catalog_category_index( $category );
        $filters = deprat_catalog_filters();
        $products = deprat_catalog_query( $category, $index, $filters );
        $material_facets = array();
        $shape_facets = array();
        foreach ( $index as $spec ) {
            if ( $spec['material'] ) { $material_facets[ sanitize_title( $spec['material'] ) ] = $spec['material']; }
            if ( $spec['shape'] ) { $shape_facets[ sanitize_title( $spec['shape'] ) ] = $spec['shape']; }
        }
        asort( $material_facets );
        asort( $shape_facets );
        $current_url = get_term_link( $category );
        ?>
        <div class="deprat-catalog-layout">
            <aside class="deprat-catalog-sidebar" aria-label="Filtres des produits">
                <form method="get" action="<?php echo esc_url( $current_url ); ?>" class="deprat-catalog-filter-form" data-catalog-filters>
                    <?php // Conserver la catégorie avec les permaliens classiques (?product_cat=tubes). ?>
                    <?php if ( false !== strpos( $current_url, '?' ) ) : ?>
                        <input type="hidden" name="product_cat" value="<?php echo esc_attr( $category->slug ); ?>">
                    <?php endif; ?>
                    <h2><span aria-hidden="true">☷</span> Filtrer les produits</h2>
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
            <section class="deprat-catalog-results" aria-label="Produits de la catégorie <?php echo esc_attr( $category->name ); ?>">
                <div class="deprat-catalog-toolbar">
                    <strong><?php echo esc_html( sprintf( _n( '%d résultat affiché', '%d résultats affichés', $products->found_posts, 'deprat' ), $products->found_posts ) ); ?></strong>
                    <div class="deprat-catalog-toolbar-controls">
                        <form method="get" action="<?php echo esc_url( $current_url ); ?>" class="deprat-catalog-sort">
                            <?php if ( false !== strpos( $current_url, '?' ) ) : ?>
                                <input type="hidden" name="product_cat" value="<?php echo esc_attr( $category->slug ); ?>">
                            <?php endif; ?>
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
                        $base_url = add_query_arg( array_merge( $args, array( 'deprat_page' => '%#%' ) ), $current_url );
                        echo '<nav class="deprat-catalog-pagination" aria-label="Pagination des produits">';
                        echo paginate_links( array( 'base' => str_replace( '%25%23%25', '%#%', esc_url( $base_url ) ), 'format' => '', 'total' => $max, 'current' => $current_page, 'type' => 'list', 'prev_text' => '‹ Précédent', 'next_text' => 'Suivant ›' ) );
                        echo '</nav>';
                    }
                    ?>
                <?php else : ?>
                    <div class="deprat-catalog-no-results">
                        <h2>Aucun produit pour ces filtres</h2>
                        <p>Essayez de modifier les critères de recherche ou de les réinitialiser.</p>
                        <a href="<?php echo esc_url( $current_url ); ?>">Voir tous les produits <?php echo esc_html( $category->name ); ?></a>
                    </div>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </section>
        </div>
    <?php endif; ?>
    <dialog class="deprat-catalog-model-dialog" data-catalog-model-dialog aria-label="Vue 3D du produit">
        <div class="deprat-catalog-model-header">
            <strong data-model-title>Vue 360°</strong>
            <button type="button" data-model-close aria-label="Fermer la vue 3D">×</button>
        </div>
        <model-viewer data-model-viewer camera-controls auto-rotate shadow-intensity="0.7" loading="lazy" reveal="auto" interaction-prompt="auto"></model-viewer>
        <p>Faites glisser la pièce pour la faire tourner ; utilisez la molette pour zoomer.</p>
    </dialog>
</main>
<?php get_footer( 'shop' ); ?>
