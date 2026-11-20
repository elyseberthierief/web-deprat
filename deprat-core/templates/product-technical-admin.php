<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
wp_nonce_field( 'deprat_product_tech_save', 'deprat_product_tech_nonce' );
$seo_description = isset( $data['seo_description'] ) && is_scalar( $data['seo_description'] ) ? (string) $data['seo_description'] : '';
$description = isset( $data['description'] ) && is_scalar( $data['description'] ) ? (string) $data['description'] : '';
$delivery = isset( $data['delivery'] ) && is_scalar( $data['delivery'] ) ? (string) $data['delivery'] : '';
$packaging = isset( $data['packaging'] ) && is_scalar( $data['packaging'] ) ? (string) $data['packaging'] : '';
$all_packages = deprat_product_tech_packages( $data );
$package_count = isset( $data['package_count'] ) && is_scalar( $data['package_count'] ) ? (string) $data['package_count'] : '';
$all_diagrams = deprat_product_tech_diagrams( $data );
$package_quantity = isset( $data['package_quantity'] ) && is_scalar( $data['package_quantity'] ) ? (string) $data['package_quantity'] : '';
$all_datasheets = deprat_product_tech_datasheets( $data );
$gallery_product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : false;
$gallery_ids = $gallery_product ? $gallery_product->get_gallery_image_ids() : array();
?>
<div class="deprat-tech-editor">
    <div class="deprat-tech-chrome" data-editor-chrome hidden>
        <div class="deprat-tech-breadcrumb"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Produits</a><span>/</span><span>Modifier un produit</span></div>
        <header class="deprat-tech-page-header">
            <div class="deprat-tech-heading"><h1>Modifier le produit</h1><span class="deprat-tech-status" data-product-status></span></div><p class="deprat-autosave-status" data-autosave-status role="status" aria-live="polite">Préparation de la sauvegarde automatique…</p>
            <div class="deprat-tech-page-actions">
                <button type="button" class="deprat-tech-preview" data-product-preview><span class="dashicons dashicons-visibility" aria-hidden="true"></span> Prévisualiser</button>
                <button type="button" class="deprat-tech-save" data-product-save><span class="dashicons dashicons-saved" aria-hidden="true"></span> Enregistrer le brouillon</button>
                <button type="button" class="button button-primary deprat-tech-publish" data-product-publish hidden>Publier après validation</button>
            </div>
        </header>
        <section class="deprat-tech-identity" aria-label="Informations du produit">
            <div class="deprat-tech-identity-photo" data-featured-panel hidden>
                <div class="deprat-featured-preview" data-featured-preview></div>
                <div class="deprat-tech-media-actions"><button type="button" class="button" data-featured-select>Médiathèque</button><button type="button" class="button deprat-tech-quick-upload" data-quick-upload="featured">Importer une image</button><button type="button" class="button-link-delete" data-featured-remove hidden>Retirer</button></div>
            </div>
            <div class="deprat-tech-identity-fields">
            <label class="deprat-tech-field"><span>Nom du produit</span><input type="text" data-product-name autocomplete="off"></label>
            <label class="deprat-tech-field"><span>Référence du produit</span><input type="text" data-product-sku autocomplete="off"></label>
            <label class="deprat-tech-field"><span>Type de produit</span><select data-product-type></select></label>
            <div class="deprat-tech-field"><span>Catégorie</span><details class="deprat-tech-category" data-category-picker><summary data-category-summary>Choisir une catégorie</summary><div class="deprat-tech-category-options" data-category-options></div></details>
                <button class="button deprat-category-create-button" type="button" data-category-open>+ Nouvelle catégorie / sous-catégorie</button>
                <a class="button deprat-category-organize-button" href="<?php echo esc_url( deprat_category_tree_page_url() ); ?>">⠿ Organiser l’arborescence des catégories</a>
                </div>
            </div>
        </section>
    </div>

    <section class="deprat-tech-card deprat-tech-description-card">
        <h3><span class="dashicons dashicons-edit" aria-hidden="true"></span> Description du produit</h3>
        <label class="deprat-tech-field"><span>Présentation et utilisation du produit</span>
            <textarea name="deprat_product_tech[description]" rows="5" placeholder="Décrivez le produit, son utilisation et ses avantages."><?php echo esc_textarea( $description ); ?></textarea>
        </label>
        <details class="deprat-seo-options">
            <summary>Référencement SEO : description automatique et personnalisable</summary>
            <label class="deprat-tech-field"><span>Description SEO personnalisée (facultatif)</span>
                <textarea name="deprat_product_tech[seo_description]" rows="2" maxlength="240" placeholder="Laisser vide pour une description générée à partir du produit et de sa référence"><?php echo esc_textarea( $seo_description ); ?></textarea>
            </label>
            <p class="deprat-tech-help">Si ce champ est vide, une description courte sera générée automatiquement. Les textes ALT des images importées le seront également et resteront modifiables dans les médias.</p>
        </details>
    </section>
    <div class="deprat-tech-layout">
        <div class="deprat-tech-main">
            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Caractéristiques techniques</h3>
                <div class="deprat-shape-admin-field">
                    <label class="deprat-tech-field"><span>Forme du produit (section / profil)</span>
                        <select name="deprat_product_tech[shape]" data-product-shape>
                            <option value="">Non renseignée — ne pas afficher d'icône</option>
                            <?php foreach ( deprat_product_shape_choices() as $shape_key => $shape_label ) : ?>
                                <option value="<?php echo esc_attr( $shape_key ); ?>" <?php selected( deprat_product_shape( $post->ID, $data ), $shape_key ); ?>><?php echo esc_html( $shape_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="deprat-tech-help">La forme choisie crée automatiquement un pictogramme vectoriel sur la boutique. Aucune image à importer. La sélection sert aussi de filtre.</p>
                </div>
                <div class="deprat-tech-rows" data-rows="features">
                    <?php foreach ( $features as $index => $row ) :
                        $label = is_array( $row ) && isset( $row['label'] ) && is_scalar( $row['label'] ) ? (string) $row['label'] : '';
                        $value = is_array( $row ) && isset( $row['value'] ) && is_scalar( $row['value'] ) ? (string) $row['value'] : '';
                        ?>
                        <div class="deprat-tech-pair" data-row>
                            <input aria-label="Nom de la caractéristique" name="deprat_product_tech[features][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="Caractéristique">
                            <input aria-label="Valeur de la caractéristique" name="deprat_product_tech[features][<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $value ); ?>" placeholder="Valeur">
                            <button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette caractéristique">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-add-row="features">+ Ajouter une caractéristique</button>

            </section>

            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-awards" aria-hidden="true"></span> Normes</h3>
                <div class="deprat-tech-rows" data-rows="norms">
                    <?php foreach ( $norms as $index => $norm ) : ?>
                        <div class="deprat-tech-norm-row" data-row>
                            <input aria-label="Norme" name="deprat_product_tech[norms][<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( is_scalar( $norm ) ? $norm : '' ); ?>" placeholder="Ex. NF EN 12194">
                            <button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette norme">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-add-row="norms">+ Ajouter une norme</button>
            </section>

            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> Valeurs testées</h3>
                <div class="deprat-tech-rows" data-rows="tests">
                    <?php foreach ( $tests as $index => $row ) :
                        $label = is_array( $row ) && isset( $row['label'] ) && is_scalar( $row['label'] ) ? (string) $row['label'] : '';
                        $value = is_array( $row ) && isset( $row['value'] ) && is_scalar( $row['value'] ) ? (string) $row['value'] : '';
                        ?>
                        <div class="deprat-tech-pair" data-row>
                            <input aria-label="Nom de l’essai ou de la mesure" name="deprat_product_tech[tests][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="Essai ou mesure">
                            <textarea class="deprat-tech-auto-grow" data-auto-grow aria-label="Valeur de l’essai ou de la mesure" name="deprat_product_tech[tests][<?php echo esc_attr( $index ); ?>][value]" rows="1" placeholder="Valeur et unité"><?php echo esc_textarea( $value ); ?></textarea>
                            <button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cet essai">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-add-row="tests">+ Ajouter un essai</button>
            </section>


            <section class="deprat-tech-card deprat-package-editor" data-package-editor>
                <h3><span class="dashicons dashicons-archive" aria-hidden="true"></span> Livraison et conditionnements</h3>
                <p class="deprat-tech-help">Définissez ici les <strong>conditionnements standard</strong> du produit : en pièces (ex. 2 cartons de 50) ou au mètre (ex. 504 m en longueurs de 6 m). Vous pouvez en proposer plusieurs.</p>
                <p class="deprat-tech-help"><strong>Règle boutique :</strong> dans la catégorie <strong>Tubes</strong> et ses sous-catégories, seuls ces conditionnements sont proposés. Pour les autres pièces, le client peut également choisir une <strong>quantité libre à l’unité</strong>, avec un avertissement indiquant qu’une plus-value sera appliquée au prix pour conditionnement non standard.</p>
                <input type="hidden" name="deprat_product_tech[packages_present]" value="1">
                <div data-package-list class="deprat-package-list">
                    <?php foreach ( $all_packages as $index => $package ) :
                        $mode = $package['mode'];
                        $prefix = 'deprat_product_tech[packages][' . $index . ']'; ?>
                        <article class="deprat-package-row" data-package-row>
                            <header class="deprat-package-row-head">
                                <strong data-package-index>Conditionnement <?php echo esc_html( $index + 1 ); ?></strong>
                                <button type="button" class="button-link-delete" data-package-delete>Supprimer</button>
                            </header>
                            <div class="deprat-package-row-fields">
                                <label class="deprat-tech-field"><span>Mode de calcul</span>
                                    <select data-package-mode name="<?php echo esc_attr( $prefix . '[mode]' ); ?>">
                                        <option value="pieces" <?php selected( $mode, 'pieces' ); ?>>À la pièce</option>
                                        <option value="length" <?php selected( $mode, 'length' ); ?>>Au mètre</option>
                                    </select>
                                </label>
                                <label class="deprat-tech-field"><span>Nom du conditionnement (facultatif)</span>
                                    <input data-package-title type="text" name="<?php echo esc_attr( $prefix . '[title]' ); ?>" value="<?php echo esc_attr( $package['title'] ); ?>" placeholder="Ex. Lot pro, grande longueur">
                                </label>
                            </div>
                            <div class="deprat-package-row-fields" data-package-fields="pieces" <?php echo 'length' === $mode ? 'hidden' : ''; ?>>
                                <label class="deprat-tech-field"><span>Nombre de conditionnements</span>
                                    <input type="number" min="1" max="999999" step="1" data-package-count name="<?php echo esc_attr( $prefix . '[count]' ); ?>" value="<?php echo esc_attr( isset( $package['count'] ) ? $package['count'] : '' ); ?>" placeholder="Ex. 2">
                                </label>
                                <label class="deprat-tech-field"><span>Type (libre avec suggestions)</span>
                                    <input type="text" list="deprat-packaging-suggestions" data-package-type name="<?php echo esc_attr( $prefix . '[type]' ); ?>" value="<?php echo esc_attr( isset( $package['type'] ) ? $package['type'] : '' ); ?>" placeholder="Ex. carton, lot, unité">
                                </label>
                                <label class="deprat-tech-field"><span>Pièces par conditionnement</span>
                                    <input type="number" min="1" max="99999999" step="1" data-package-pieces name="<?php echo esc_attr( $prefix . '[pieces]' ); ?>" value="<?php echo esc_attr( isset( $package['pieces'] ) ? $package['pieces'] : '' ); ?>" placeholder="Ex. 50">
                                </label>
                            </div>
                            <div class="deprat-package-row-fields" data-package-fields="length" <?php echo 'pieces' === $mode ? 'hidden' : ''; ?>>
                                <label class="deprat-tech-field"><span>Nombre de mètres par conditionnement</span>
                                    <input type="number" min="0.001" step="0.001" data-package-total-m name="<?php echo esc_attr( $prefix . '[total_m]' ); ?>" value="<?php echo esc_attr( isset( $package['total_m'] ) ? $package['total_m'] : '' ); ?>" placeholder="Ex. 504">
                                </label>
                                <label class="deprat-tech-field"><span>Longueur de chaque barre (m)</span>
                                    <input type="number" min="0.001" step="0.001" data-package-length-m name="<?php echo esc_attr( $prefix . '[length_m]' ); ?>" value="<?php echo esc_attr( isset( $package['length_m'] ) ? $package['length_m'] : '' ); ?>" placeholder="Ex. 6">
                                </label>
                            </div>
                            <div class="deprat-package-summary" data-package-preview aria-live="polite"></div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <datalist id="deprat-packaging-suggestions">
                    <?php foreach ( deprat_product_tech_packaging_suggestions() as $suggestion ) : ?>
                        <option value="<?php echo esc_attr( $suggestion ); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <button type="button" class="button deprat-tech-add" data-package-add>+ Ajouter un conditionnement</button>
                <label class="deprat-tech-field deprat-package-delivery"><span>Informations complémentaires de livraison</span>
                    <input name="deprat_product_tech[delivery]" value="<?php echo esc_attr( $delivery ); ?>" placeholder="Ex. transport sur palette">
                </label>
                <p class="deprat-tech-help">La quantité commandée par le client multiplie le conditionnement choisi. Une répartition en longueurs doit tomber juste (504 ÷ 6 = 84).</p>
            </section>
        </div>

        <aside class="deprat-tech-side" aria-label="Documents et schémas">

            <section class="deprat-tech-card deprat-gallery-card">
                <h3><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span> Autres images du produit</h3>
                <p class="deprat-tech-help">Ces photos compléteront l’image principale dans la galerie de la fiche produit.</p>
                <input type="hidden" name="deprat_product_gallery" data-gallery-ids value="<?php echo esc_attr( implode( ',', array_map( 'absint', $gallery_ids ) ) ); ?>">
                <div class="deprat-product-gallery-list" data-gallery-list>
                    <?php foreach ( $gallery_ids as $id ) :
                        if ( ! wp_attachment_is_image( $id ) ) { continue; } ?>
                        <article class="deprat-product-gallery-item" data-gallery-item data-gallery-id="<?php echo esc_attr( $id ); ?>">
                            <?php echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => 'Photo complémentaire du produit' ) ); ?>
                            <button type="button" class="button-link-delete" data-gallery-remove>Retirer</button>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-gallery-add>+ Médiathèque</button>
                <button type="button" class="button deprat-tech-add deprat-tech-quick-upload" data-quick-upload="gallery">+ Importer des photos</button>
            </section>

            <?php deprat_product_3d_render_admin( $post->ID ); ?>

            <section class="deprat-tech-card deprat-tech-pdf-panel">
                <h3><span class="dashicons dashicons-media-document" aria-hidden="true"></span> Fiches techniques PDF</h3>
                <p class="deprat-tech-help">Ajoutez plusieurs documents, chacun avec son titre. Tous seront téléchargeables par le client.</p>
                <input type="hidden" name="deprat_product_tech[datasheets_present]" value="1">
                <div class="deprat-datasheets-list" data-datasheets-list>
                    <?php foreach ( $all_datasheets as $index => $sheet ) :
                        $url = wp_get_attachment_url( $sheet['file'] ); ?>
                        <article class="deprat-datasheet-entry" data-datasheet-row>
                            <div class="deprat-tech-diagram-toolbar"><strong>Document <?php echo esc_html( $index + 1 ); ?></strong><button type="button" class="button-link-delete" data-datasheet-delete>Supprimer</button></div>
                            <label class="deprat-tech-field"><span>Nom du document</span>
                                <input data-datasheet-title name="deprat_product_tech[datasheets][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $sheet['title'] ); ?>" placeholder="Ex. Notice d'installation">
                            </label>
                            <input type="hidden" data-datasheet-file name="deprat_product_tech[datasheets][<?php echo esc_attr( $index ); ?>][file]" value="<?php echo esc_attr( $sheet['file'] ); ?>">
                            <div class="deprat-tech-media-preview" data-datasheet-preview><?php if ( $url ) : ?><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $sheet['file'] ) ); ?></a><?php endif; ?></div>
                            <button type="button" class="button" data-datasheet-select>Médiathèque</button>
                            <button type="button" class="button deprat-tech-quick-upload" data-quick-upload="datasheet">Importer un PDF</button>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-datasheet-add>+ Ajouter une fiche technique PDF</button>
            </section>
            <section class="deprat-tech-card deprat-tech-diagrams-panel">
                <h3><span class="dashicons dashicons-editor-contract" aria-hidden="true"></span> Schémas techniques et dimensionnels</h3>
                <p class="deprat-tech-help">Ajoutez des schémas et organisez-les avec les flèches.</p>
                <input type="hidden" name="deprat_product_tech[diagrams_present]" value="1">
                <div class="deprat-tech-diagrams-list" data-diagrams-list>
                <?php foreach ( $all_diagrams as $index => $diagram ) : ?>
                    <article class="deprat-tech-diagram-entry" data-diagram-row>
                        <div class="deprat-tech-diagram-toolbar">
                            <span class="deprat-tech-diagram-index">Schéma <?php echo esc_html( $index + 1 ); ?></span>
                            <div><button type="button" class="button" data-diagram-up aria-label="Monter le schéma">↑</button>
                            <button type="button" class="button" data-diagram-down aria-label="Descendre le schéma">↓</button>
                            <button type="button" class="button-link-delete" data-diagram-delete>Supprimer</button></div>
                        </div>
                        <label class="deprat-tech-field"><span>Titre du schéma</span><input data-diagram-title name="deprat_product_tech[diagrams][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $diagram['title'] ); ?>" placeholder="Ex. Schéma de montage"></label>
                        <input type="hidden" data-diagram-image name="deprat_product_tech[diagrams][<?php echo esc_attr( $index ); ?>][image]" value="<?php echo esc_attr( $diagram['image'] ); ?>">
                        <div class="deprat-tech-media-preview" data-diagram-preview><?php echo wp_get_attachment_image( $diagram['image'], 'medium', false, array( 'alt' => $diagram['title'] ) ); ?></div>
                        <button type="button" class="button" data-diagram-select>Médiathèque</button>
                        <button type="button" class="button deprat-tech-quick-upload" data-quick-upload="diagram">Importer un schéma</button>
                    </article>
                <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-diagram-add>+ Ajouter un schéma</button>
            </section>
        </aside>
    </div>

    <div class="deprat-category-modal" data-category-modal hidden>
        <div class="deprat-category-backdrop" data-category-close></div>
        <div class="deprat-category-dialog" role="dialog" aria-modal="true" aria-labelledby="deprat-category-title">
            <header><h2 id="deprat-category-title">Créer une catégorie</h2><button type="button" class="button" data-category-close aria-label="Fermer">×</button></header>
            <label class="deprat-tech-field"><span>Nom de la catégorie</span><input type="text" maxlength="160" data-category-name placeholder="Ex. Tubes acier"></label>
            <label class="deprat-tech-field"><span>Section DEPRAT</span><select data-category-section>
                <?php foreach ( deprat_catalog_section_definitions() as $section_key => $section_title ) : ?>
                    <option value="<?php echo esc_attr( $section_key ); ?>"><?php echo esc_html( $section_title ); ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="deprat-tech-field"><span>Catégorie parente</span><select data-category-parent><option value="0">Aucune — catégorie principale</option>
                <?php $previous_section = ''; foreach ( deprat_category_tree_sectioned_options() as $term ) :
                    if ( $previous_section !== $term['section'] ) :
                        if ( '' !== $previous_section ) { echo '</optgroup>'; }
                        $previous_section = $term['section'];
                        echo '<optgroup label="' . esc_attr( $term['sectionTitle'] ) . '">';
                    endif; ?>
                    <option value="<?php echo esc_attr( $term['id'] ); ?>"><?php echo esc_html( str_repeat( '— ', min( 10, (int) $term['depth'] ) ) . $term['name'] ); ?></option>
                <?php endforeach; if ( '' !== $previous_section ) { echo '</optgroup>'; } ?>
            </select></label>
            <p class="deprat-category-section-hint">L’ordre des sections et des catégories suit « Organiser les catégories ». Une sous-catégorie hérite de la section de son parent.</p>
            <p class="deprat-category-error" role="alert" data-category-error hidden></p>
            <footer><button type="button" class="button" data-category-close>Annuler</button><button type="button" class="button button-primary" data-category-submit>Créer et sélectionner</button></footer>
        </div>
    </div>
    <button type="button" class="deprat-tech-native-toggle" data-native-toggle aria-expanded="false" hidden>Afficher les autres options WooCommerce</button>

    <template data-row-template="features"><div class="deprat-tech-pair" data-row><input aria-label="Nom de la caractéristique" name="deprat_product_tech[features][__index__][label]" placeholder="Caractéristique"><input aria-label="Valeur de la caractéristique" name="deprat_product_tech[features][__index__][value]" placeholder="Valeur"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette caractéristique">×</button></div></template>
    <template data-row-template="norms"><div class="deprat-tech-norm-row" data-row><input aria-label="Norme" name="deprat_product_tech[norms][__index__]" placeholder="Ex. NF EN 12194"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette norme">×</button></div></template>
    <template data-row-template="tests"><div class="deprat-tech-pair" data-row><input aria-label="Nom de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][label]" placeholder="Essai ou mesure"><textarea class="deprat-tech-auto-grow" data-auto-grow aria-label="Valeur de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][value]" rows="1" placeholder="Valeur et unité"></textarea><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cet essai">×</button></div></template>
</div>
