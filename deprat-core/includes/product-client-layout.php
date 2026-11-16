<?php
/**
 * Fiche produit DEPRAT B2B — présentation client.
 * Réutilise les données techniques et les formulaires WooCommerce existants.
 * Aucun en-tête/menu de navigation n'est créé ici.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_client_product_setup() {
    if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) { return; }
    // Identité et demande de devis sont affichées une seule fois.
    remove_action( 'woocommerce_single_product_summary', 'deprat_product_single_intro', 4 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    add_action( 'woocommerce_single_product_summary', 'deprat_client_product_intro', 4 );

    // Placement : galerie native à gauche, résumé au centre, formulaire de demande à droite.
    add_action( 'woocommerce_after_single_product_summary', 'deprat_client_product_quote', 3 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
    remove_action( 'woocommerce_after_single_product_summary', 'deprat_product_tech_render_front', 15 );
    add_action( 'woocommerce_after_single_product_summary', 'deprat_client_product_tabs', 12 );
    add_action( 'woocommerce_after_single_product_summary', 'deprat_client_product_related', 22 );
}
add_action( 'wp', 'deprat_client_product_setup', 35 );

function deprat_client_product_body_class( $classes ) {
    if ( function_exists( 'is_product' ) && is_product() ) { $classes[] = 'deprat-client-product'; }
    return $classes;
}
add_filter( 'body_class', 'deprat_client_product_body_class' );

function deprat_client_product_assets() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
    wp_enqueue_style( 'deprat-client-product', plugins_url( 'assets/css/product-client-layout.css', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'deprat-product-tech-front' ), '0.1.21' );
    wp_enqueue_script( 'deprat-client-product', plugins_url( 'assets/js/product-client-layout.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.21', true );
    wp_localize_script( 'deprat-client-product', 'depratClientProduct', array(
        'ajaxUrl' => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : '',
        'cartUrl' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
    ) );
}
add_action( 'wp_enqueue_scripts', 'deprat_client_product_assets', 30 );

function deprat_client_product_package( $id ) {
    $data = deprat_product_tech_data( $id );
    $type = isset( $data['packaging'] ) && is_scalar( $data['packaging'] ) ? trim( sanitize_text_field( $data['packaging'] ) ) : '';
    $pieces = isset( $data['package_quantity'] ) ? absint( $data['package_quantity'] ) : 0;
    $count = isset( $data['package_count'] ) ? max( 1, absint( $data['package_count'] ) ) : 1;
    return array( 'type' => $type, 'pieces' => $pieces, 'count' => $count );
}

function deprat_client_product_package_label( $package ) {
    $type = $package['type'];
    if ( '' === $type || ! $package['pieces'] ) { return ''; }
    $count = $package['count'];
    $plural = $count > 1 && ! preg_match( '/[sxz]$/iu', $type ) ? 's' : '';
    return $count > 1
        ? sprintf( '%d %s%s de %d pièces chacun', $count, $type, $plural, $package['pieces'] )
        : sprintf( '1 %s de %d %s', $type, $package['pieces'], $package['pieces'] > 1 ? 'pièces' : 'pièce' );
}

function deprat_client_product_intro() {
    global $product;
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $id = $product->get_id();
    $sku = trim( (string) $product->get_sku() );
    $data = deprat_product_tech_data( $id );
    $description = isset( $data['description'] ) && is_scalar( $data['description'] ) && '' !== trim( (string) $data['description'] )
        ? trim( (string) $data['description'] ) : trim( wp_strip_all_tags( $product->get_short_description() ) );
    $terms = get_the_terms( $id, 'product_cat' );
    $package = deprat_client_product_package( $id );
    $pack_label = deprat_client_product_package_label( $package );
    $status = $product->get_stock_status();
    ?>
    <section class="deprat-client-info" aria-label="Présentation du produit">
        <?php if ( is_array( $terms ) && $terms ) : ?>
            <div class="deprat-client-categories">
            <?php foreach ( $terms as $term ) :
                $url = get_term_link( $term );
                if ( ! is_wp_error( $url ) ) : ?>
                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $term->name ); ?></a>
            <?php endif; endforeach; ?>
            </div>
        <?php endif; ?>
        <h1 class="product_title entry-title"><?php echo esc_html( $product->get_name() ); ?></h1>
        <?php if ( $sku ) : ?><p class="deprat-client-sku">Réf. : <strong><?php echo esc_html( $sku ); ?></strong></p><?php endif; ?>
        <?php if ( $description ) : ?><div class="deprat-client-description"><?php echo nl2br( esc_html( $description ) ); ?></div><?php endif; ?>
        <div class="deprat-client-stock">
            <?php if ( 'onbackorder' === $status ) : ?>
                <span class="deprat-client-stock-pill is-backorder">Sur commande</span>
            <?php elseif ( $product->is_in_stock() ) : ?>
                <span class="deprat-client-stock-pill is-available">En stock</span>
            <?php else : ?>
                <span class="deprat-client-stock-pill is-unavailable">Indisponible</span>
            <?php endif; ?>
        </div>
        <?php if ( $pack_label ) : ?>
            <div class="deprat-client-packaging">
                <span class="deprat-client-pack-icon" aria-hidden="true"><?php echo deprat_product_tech_icon( 'package' ); ?></span>
                <div><strong>Conditionnement</strong>
                    <p>Le produit est conditionné en <b><?php echo esc_html( $pack_label ); ?>.</b></p>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function deprat_client_product_quote() {
    global $product;
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $package = deprat_client_product_package( $product->get_id() );
    $single = deprat_client_product_package_label( $package );
    // Les coordonnées peuvent être configurées dans WordPress via ces deux options.
    $phone = (string) get_option( 'deprat_contact_phone', '03 20 89 68 00' );
    $email = sanitize_email( (string) get_option( 'deprat_contact_email', get_option( 'admin_email' ) ) );
    ?>
    <aside class="deprat-client-quote" aria-label="Demande de devis">
        <h2><span aria-hidden="true">▤</span> Ajouter à ma demande de devis</h2>
        <div class="deprat-client-quote-form">
            <?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
                <?php woocommerce_template_single_add_to_cart(); ?>
            <?php else : ?>
                <p class="deprat-client-quote-unavailable">Ce produit ne peut pas être ajouté à la demande actuellement. Contactez un conseiller.</p>
            <?php endif; ?>
        </div>
        <?php if ( $single ) : ?>
            <div class="deprat-client-quote-calculation" data-package-calculator
                data-type="<?php echo esc_attr( $package['type'] ); ?>"
                data-count="<?php echo esc_attr( $package['count'] ); ?>"
                data-pieces="<?php echo esc_attr( $package['pieces'] ); ?>"
                aria-live="polite">
                Pour 1 unité demandée : <?php echo esc_html( $single ); ?>,
                soit <strong><?php echo esc_html( $package['count'] * $package['pieces'] ); ?> pièces</strong>.
            </div>
        <?php endif; ?>
        <p class="deprat-client-quote-feedback" data-quote-feedback aria-live="polite" hidden></p>
        <div class="deprat-client-quote-help">
            <button type="button" data-contact-open>◉ &nbsp;Contacter un conseiller <span aria-hidden="true">›</span></button>
        </div>
    </aside>
    <div class="deprat-client-contact" data-contact-dialog hidden>
        <div class="deprat-client-contact-backdrop" data-contact-close></div>
        <div role="dialog" aria-modal="true" aria-labelledby="deprat-contact-title" class="deprat-client-contact-panel">
            <button type="button" class="deprat-client-contact-close" data-contact-close aria-label="Fermer">×</button>
            <h2 id="deprat-contact-title">Contacter un conseiller DEPRAT</h2>
            <p>Une question technique ou besoin d'aide pour votre demande de devis ?</p>
            <?php if ( $phone ) : ?><p><strong>Téléphone :</strong> <a href="tel:<?php echo esc_attr( preg_replace( '/[^+0-9]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p><?php endif; ?>
            <?php if ( $email ) : ?><p><strong>E-mail :</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p><?php endif; ?>
        </div>
    </div>
    <?php
}

function deprat_client_product_tabs() {
    global $product;
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) { return; }
    $data = deprat_product_tech_data( $product->get_id() );
    $features = deprat_product_tech_display_pairs( isset( $data['features'] ) ? $data['features'] : array() );
    $tests = deprat_product_tech_display_pairs( isset( $data['tests'] ) ? $data['tests'] : array() );
    $norms = isset( $data['norms'] ) && is_array( $data['norms'] ) ? array_filter( $data['norms'], 'is_scalar' ) : array();
    $datasheets = deprat_product_tech_datasheets( $data );
    $diagrams = deprat_product_tech_diagrams( $data );

    // Ajouter les attributs visibles WooCommerce en évitant de répéter les champs DEPRAT.
    $used = array_map( static function ( $row ) { return mb_strtolower( trim( $row['label'] ) ); }, $features );
    foreach ( $product->get_attributes() as $attribute ) {
        if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_visible' ) || ! $attribute->get_visible() ) { continue; }
        $key = $attribute->get_name();
        $label = wc_attribute_label( $key );
        if ( in_array( mb_strtolower( trim( $label ) ), $used, true ) ) { continue; }
        $value = $product->get_attribute( $key );
        if ( '' !== trim( $value ) ) {
            $features[] = array( 'label' => $label, 'value' => $value );
            $used[] = mb_strtolower( trim( $label ) );
        }
    }
    ?>
    <section class="deprat-client-tech" data-deprat-tabs aria-label="Informations techniques">
        <div class="deprat-client-tech-tabs" role="tablist" aria-label="Rubriques de la fiche produit">
            <button id="deprat-tab-specs" type="button" role="tab" aria-selected="true" aria-controls="deprat-panel-specs" tabindex="0" data-tab-target="specs"><?php echo deprat_product_tech_icon( 'features' ); ?> Caractéristiques techniques</button>
            <button id="deprat-tab-tests" type="button" role="tab" aria-selected="false" aria-controls="deprat-panel-tests" tabindex="-1" data-tab-target="tests"><?php echo deprat_product_tech_icon( 'norms' ); ?> Tests &amp; normes</button>
            <button id="deprat-tab-docs" type="button" role="tab" aria-selected="false" aria-controls="deprat-panel-docs" tabindex="-1" data-tab-target="docs"><?php echo deprat_product_tech_icon( 'pdf' ); ?> Documents &amp; schémas</button>
        </div>
        <div class="deprat-client-tech-panel" id="deprat-panel-specs" role="tabpanel" aria-labelledby="deprat-tab-specs" data-tab-panel="specs">
            <?php if ( $features ) : ?>
                <dl class="deprat-client-feature-table">
                <?php foreach ( $features as $row ) : ?>
                    <div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo nl2br( esc_html( $row['value'] ) ); ?></dd></div>
                <?php endforeach; ?>
                </dl>
            <?php else : ?><p class="deprat-client-no-data">Les caractéristiques détaillées seront prochainement disponibles.</p><?php endif; ?>
        </div>
        <div class="deprat-client-tech-panel" id="deprat-panel-tests" role="tabpanel" aria-labelledby="deprat-tab-tests" data-tab-panel="tests" hidden>
            <div class="deprat-client-test-grid">
                <div><h3><?php echo deprat_product_tech_icon( 'tests' ); ?> Valeurs testées</h3>
                    <?php if ( $tests ) : ?><dl class="deprat-client-feature-table">
                    <?php foreach ( $tests as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo nl2br( esc_html( $row['value'] ) ); ?></dd></div><?php endforeach; ?>
                    </dl><?php else : ?><p>Aucune valeur testée renseignée.</p><?php endif; ?>
                </div>
                <div><h3><?php echo deprat_product_tech_icon( 'norms' ); ?> Normes</h3>
                    <?php if ( $norms ) : ?><ul class="deprat-client-norms"><?php foreach ( $norms as $norm ) : ?><li><?php echo esc_html( $norm ); ?></li><?php endforeach; ?></ul>
                    <?php else : ?><p>Aucune norme renseignée.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="deprat-client-tech-panel" id="deprat-panel-docs" role="tabpanel" aria-labelledby="deprat-tab-docs" data-tab-panel="docs" hidden>
            <div class="deprat-client-doc-grid">
                <div><h3><?php echo deprat_product_tech_icon( 'pdf' ); ?> Documents techniques</h3>
                    <?php if ( $datasheets ) : ?>
                    <div class="deprat-client-documents">
                        <?php foreach ( $datasheets as $sheet ) :
                            $url = wp_get_attachment_url( $sheet['file'] );
                            if ( ! $url ) { continue; } ?>
                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
                                <span class="deprat-client-pdf-icon">PDF</span>
                                <span><?php echo esc_html( $sheet['title'] ); ?></span>
                                <span aria-hidden="true">↓</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?><p>Aucun document disponible.</p><?php endif; ?>
                </div>
                <div><h3><?php echo deprat_product_tech_icon( 'diagram' ); ?> Schémas</h3>
                    <?php if ( $diagrams ) : ?>
                    <div class="deprat-client-diagrams">
                    <?php foreach ( $diagrams as $diagram ) :
                        $url = wp_get_attachment_url( $diagram['image'] );
                        if ( ! $url ) { continue; } ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo wp_get_attachment_image( $diagram['image'], 'medium', false, array( 'alt' => $diagram['title'], 'loading' => 'lazy' ) ); ?>
                            <span><?php echo esc_html( $diagram['title'] ); ?></span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                    <?php else : ?><p>Aucun schéma disponible.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function deprat_client_product_related() {
    global $product;
    if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) || ! function_exists( 'wc_get_related_products' ) ) { return; }
    $id = $product->get_id();
    $suggested = array_map( 'absint', (array) $product->get_upsell_ids() );
    $automatic = wc_get_related_products( $id, 6, array_merge( array( $id ), $suggested ) );
    $ids = array_values( array_unique( array_merge( $suggested, $automatic ) ) );
    $cards = array();
    foreach ( array_slice( $ids, 0, 6 ) as $related_id ) {
        $related = wc_get_product( $related_id );
        if ( $related && $related->is_visible() ) { $cards[] = $related; }
    }
    if ( ! $cards ) { return; }
    ?>
    <section class="deprat-client-related" aria-labelledby="deprat-related-title">
        <h2 id="deprat-related-title"><span aria-hidden="true">—</span> Produits associés</h2>
        <div class="deprat-client-related-grid">
        <?php foreach ( $cards as $related ) : ?>
            <a class="deprat-client-related-card" href="<?php echo esc_url( get_permalink( $related->get_id() ) ); ?>">
                <span class="deprat-client-related-img"><?php echo $related->get_image( 'woocommerce_thumbnail' ); ?></span>
                <span class="deprat-client-related-text"><strong><?php echo esc_html( $related->get_name() ); ?></strong>
                <?php if ( $related->get_sku() ) : ?><small>Réf. : <?php echo esc_html( $related->get_sku() ); ?></small><?php endif; ?>
                </span><span class="deprat-client-related-arrow" aria-hidden="true">›</span>
            </a>
        <?php endforeach; ?>
        </div>
    </section>
    <?php
}
