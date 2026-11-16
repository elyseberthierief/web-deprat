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
?>
<div class="deprat-tech-editor">
    <p class="deprat-tech-intro">Renseignez les informations utiles pour ce produit. Les rubriques complétées seront affichées sur sa fiche dans la boutique.</p>
    <div class="deprat-tech-layout">
        <div class="deprat-tech-main">
            <section class="deprat-tech-card">
                <h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Caractéristiques techniques</h3>
                <label class="deprat-tech-field">
                    <span>Description technique</span>
                    <textarea name="deprat_product_tech[description]" rows="3" placeholder="Présentation et utilisation du produit"><?php echo esc_textarea( $description ); ?></textarea>
                </label>
                <div class="deprat-tech-pair-head" aria-hidden="true"><span>Caractéristique</span><span>Valeur</span><span></span></div>
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
                <div class="deprat-tech-pair-head" aria-hidden="true"><span>Essai ou mesure</span><span>Valeur</span><span></span></div>
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
        </div>

        <aside class="deprat-tech-side" aria-label="Documents et schémas">
            <?php foreach ( $media_fields as $key => $field ) :
                $attachment_id = deprat_product_tech_attachment( isset( $data[ $key ] ) ? $data[ $key ] : 0, $field['kind'] );
                $url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
                ?>
                <section class="deprat-tech-card deprat-tech-media" data-media-kind="<?php echo esc_attr( $field['kind'] ); ?>">
                    <h3><span class="dashicons <?php echo 'pdf' === $field['kind'] ? 'dashicons-media-document' : 'dashicons-format-image'; ?>" aria-hidden="true"></span> <?php echo esc_html( $field['label'] ); ?></h3>
                    <input type="hidden" name="deprat_product_tech[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" data-media-id>
                    <div class="deprat-tech-media-preview" data-media-preview>
                        <?php if ( $attachment_id && 'image' === $field['kind'] ) : ?>
                            <?php echo wp_get_attachment_image( $attachment_id, 'medium', false, array( 'alt' => $field['label'] ) ); ?>
                        <?php elseif ( $attachment_id && $url ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( get_the_title( $attachment_id ) ); ?></a>
                        <?php else : ?>
                            <span>Aucun fichier sélectionné</span>
                        <?php endif; ?>
                    </div>
                    <div class="deprat-tech-media-actions">
                        <button type="button" class="button" data-media-select><?php echo $attachment_id ? 'Remplacer' : 'Ajouter'; ?></button>
                        <button type="button" class="button-link-delete" data-media-remove <?php echo $attachment_id ? '' : 'hidden'; ?>>Retirer</button>
                    </div>
                    <?php if ( 'pdf' === $field['kind'] ) : ?><p class="deprat-tech-help">Le document pourra être téléchargé depuis la fiche du produit.</p><?php endif; ?>
                </section>
            <?php endforeach; ?>
        </aside>
    </div>

    <template data-row-template="features"><div class="deprat-tech-pair" data-row><input aria-label="Nom de la caractéristique" name="deprat_product_tech[features][__index__][label]" placeholder="Caractéristique"><input aria-label="Valeur de la caractéristique" name="deprat_product_tech[features][__index__][value]" placeholder="Valeur"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette caractéristique">×</button></div></template>
    <template data-row-template="norms"><div class="deprat-tech-norm-row" data-row><input aria-label="Norme" name="deprat_product_tech[norms][__index__]" placeholder="Ex. NF EN 12194"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cette norme">×</button></div></template>
    <template data-row-template="tests"><div class="deprat-tech-pair" data-row><input aria-label="Nom de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][label]" placeholder="Essai ou mesure"><input aria-label="Valeur de l’essai ou de la mesure" name="deprat_product_tech[tests][__index__][value]" placeholder="Valeur et unité"><button type="button" class="deprat-tech-remove" data-remove-row aria-label="Supprimer cet essai">×</button></div></template>
</div>
