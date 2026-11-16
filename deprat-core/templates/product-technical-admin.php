<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
wp_nonce_field( 'deprat_product_tech_save', 'deprat_product_tech_nonce' );
$description = isset( $data['description'] ) && is_scalar( $data['description'] ) ? (string) $data['description'] : '';
$delivery = isset( $data['delivery'] ) && is_scalar( $data['delivery'] ) ? (string) $data['delivery'] : '';
$packaging = isset( $data['packaging'] ) && is_scalar( $data['packaging'] ) ? (string) $data['packaging'] : '';
$package_quantity = isset( $data['package_quantity'] ) && is_scalar( $data['package_quantity'] ) ? (string) $data['package_quantity'] : '';
$media_fields = array(
    'datasheet' => array( 'label' => 'Fiche technique PDF', 'kind' => 'pdf' ),
    'technical_image' => array( 'label' => 'Schéma technique', 'kind' => 'image' ),
    'dimensions_image' => array( 'label' => 'Schéma des dimensions', 'kind' => 'image' ),
);
$render_media = static function ( $key, $field ) use ( $data ) {
    $attachment_id = deprat_product_tech_attachment( isset( $data[ $key ] ) ? $data[ $key ] : 0, $field['kind'] );
    $url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
    $is_pdf = 'pdf' === $field['kind'];
    ?>
    <section class="deprat-tech-media <?php echo $is_pdf ? 'deprat-tech-card deprat-tech-pdf' : 'deprat-tech-diagram'; ?>" data-media-kind="<?php echo esc_attr( $field['kind'] ); ?>">
        <?php if ( $is_pdf ) : ?><h3><span class="dashicons dashicons-media-document" aria-hidden="true"></span> <?php echo esc_html( $field['label'] ); ?></h3>
        <?php else : ?><h4><?php echo esc_html( $field['label'] ); ?></h4><?php endif; ?>
        <input type="hidden" name="deprat_product_tech[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" data-media-id>
        <div class="deprat-tech-media-content">
            <div class="deprat-tech-media-preview" data-media-preview>
                <?php if ( $attachment_id && ! $is_pdf ) : ?>
                    <?php echo wp_get_attachment_image( $attachment_id, 'medium', false, array( 'alt' => $field['label'] ) ); ?>
                <?php elseif ( $attachment_id && $url ) : ?>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $attachment_id ) ); ?></a>
                <?php else : ?>
                    <span><?php echo $is_pdf ? 'Aucun PDF sélectionné' : 'Aucun schéma sélectionné'; ?></span>
                <?php endif; ?>
            </div>
            <div class="deprat-tech-media-actions">
                <button type="button" class="button deprat-tech-media-select" data-media-select><?php echo $attachment_id ? ( $is_pdf ? 'Remplacer le PDF' : 'Remplacer le schéma' ) : ( $is_pdf ? 'Ajouter un PDF' : 'Ajouter un schéma' ); ?></button>
                <button type="button" class="button-link-delete" data-media-remove <?php echo $attachment_id ? '' : 'hidden'; ?>>Retirer</button>
            </div>
        </div>
        <?php if ( $is_pdf ) : ?>
            <a class="deprat-tech-pdf-download" data-media-download href="<?php echo esc_url( $url ); ?>" download <?php echo $url ? '' : 'hidden'; ?>>↓ Télécharger la fiche technique</a>
            <p class="deprat-tech-help">Le client pourra télécharger ce document.</p>
        <?php endif; ?>
    </section>
    <?php
};
?>
<div class="deprat-tech-editor">
    <div class="deprat-tech-chrome" data-editor-chrome hidden>
        <div class="deprat-tech-breadcrumb"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Produits</a><span>/</span><span>Modifier un produit</span></div>
        <header class="deprat-tech-page-header">
            <div class="deprat-tech-heading"><h1>Modifier le produit</h1><span class="deprat-tech-status" data-product-status></span></div>
            <div class="deprat-tech-page-actions">
                <button type="button" class="deprat-tech-preview" data-product-preview><span class="dashicons dashicons-visibility" aria-hidden="true"></span> Prévisualiser</button>
                <button type="button" class="deprat-tech-save" data-product-save><span class="dashicons dashicons-saved" aria-hidden="true"></span> Enregistrer les modifications</button>
            </div>
        </header>
        <section class="deprat-tech-identity" aria-label="Informations du produit">
            <label class="deprat-tech-field"><span>Nom du produit</span><input type="text" data-product-name autocomplete="off"></label>
            <label class="deprat-tech-field"><span>Référence du produit</span><input type="text" data-product-sku autocomplete="off"></label>
            <label class="deprat-tech-field"><span>Type de produit</span><select data-product-type></select></label>
            <div class="deprat-tech-field"><span>Catégorie</span><details class="deprat-tech-category" data-category-picker><summary data-category-summary>Choisir une catégorie</summary><div class="deprat-tech-category-options" data-category-options></div></details></div>
        </section>
    </div>
    <div class="deprat-tech-layout">
        <div class="deprat-tech-main">
            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Caractéristiques techniques</h3>
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
                <div class="deprat-tech-delivery-mirror" data-delivery-mirror hidden><label for="deprat-delivery-proxy">Livraison</label><input id="deprat-delivery-proxy" type="text" data-delivery-proxy value="<?php echo esc_attr( $delivery ); ?>"></div>
                <button type="button" class="button deprat-tech-add" data-add-row="features">+ Ajouter une caractéristique</button>
                <details class="deprat-tech-description-editor" <?php echo '' !== $description ? 'open' : ''; ?>><summary>Description technique</summary><label class="deprat-tech-field"><span class="screen-reader-text">Description technique</span><textarea name="deprat_product_tech[description]" rows="3" placeholder="Présentation et utilisation du produit"><?php echo esc_textarea( $description ); ?></textarea></label></details>
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
                            <input aria-label="Valeur de l’essai ou de la mesure" name="deprat_product_tech[tests][<?php echo esc_attr( $index ); ?>][value]" value="<?php echo esc_attr( $value ); ?>" placeholder="Valeur et unité">
                            <button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cet essai">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button deprat-tech-add" data-add-row="tests">+ Ajouter un essai</button>
            </section>

            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-archive" aria-hidden="true"></span> Livraison et conditionnement</h3>
                <div class="deprat-tech-fields">
                    <label class="deprat-tech-field"><span>Livraison</span><input name="deprat_product_tech[delivery]" value="<?php echo esc_attr( $delivery ); ?>" placeholder="Ex. longueurs de 6 m"></label>
                    <label class="deprat-tech-field"><span>Conditionnement</span><input name="deprat_product_tech[packaging]" value="<?php echo esc_attr( $packaging ); ?>" placeholder="Ex. carton, lot ou unité"></label>
                    <label class="deprat-tech-field"><span>Nombre de pièces par conditionnement</span><input type="number" min="1" max="99999999" step="1" name="deprat_product_tech[package_quantity]" value="<?php echo esc_attr( $package_quantity ); ?>" placeholder="À renseigner"></label>
                </div>
                <p class="deprat-tech-help">Ces informations décrivent le produit. La quantité saisie dans une demande reste celle choisie par le client.</p>
            </section>
            <section class="deprat-tech-card deprat-tech-schematics-editor">
                <h3><span class="dashicons dashicons-editor-contract" aria-hidden="true"></span> Schémas et dimensions</h3>
                <div class="deprat-tech-diagrams">
                    <?php $render_media( 'technical_image', $media_fields['technical_image'] ); ?>
                    <?php $render_media( 'dimensions_image', $media_fields['dimensions_image'] ); ?>
                </div>
            </section>
        </div>

        <aside class="deprat-tech-side" aria-label="Documents et schémas">
            <section class="deprat-tech-card deprat-featured-card" data-featured-panel hidden>
                <h3><span class="dashicons dashicons-format-image" aria-hidden="true"></span> Image du produit</h3>
                <div class="deprat-featured-preview" data-featured-preview></div>
                <div class="deprat-tech-media-actions"><button type="button" class="button" data-featured-select>Choisir une image</button><button type="button" class="button-link-delete" data-featured-remove hidden>Retirer</button></div>
            </section>
            <?php $render_media( 'datasheet', $media_fields['datasheet'] ); ?>
        </aside>
    </div>
    <button type="button" class="deprat-tech-native-toggle" data-native-toggle aria-expanded="false" hidden>Afficher les autres options WooCommerce</button>

    <template data-row-template="features"><div class="deprat-tech-pair" data-row><input aria-label="Nom de la caractéristique" name="deprat_product_tech[features][__index__][label]" placeholder="Caractéristique"><input aria-label="Valeur de la caractéristique" name="deprat_product_tech[features][__index__][value]" placeholder="Valeur"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette caractéristique">×</button></div></template>
    <template data-row-template="norms"><div class="deprat-tech-norm-row" data-row><input aria-label="Norme" name="deprat_product_tech[norms][__index__]" placeholder="Ex. NF EN 12194"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette norme">×</button></div></template>
    <template data-row-template="tests"><div class="deprat-tech-pair" data-row><input aria-label="Nom de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][label]" placeholder="Essai ou mesure"><input aria-label="Valeur de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][value]" placeholder="Valeur et unité"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cet essai">×</button></div></template>
</div>
