<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="deprat-tech-front" aria-label="Informations techniques du produit">
    <div class="deprat-tech-front-header"><h2>Informations techniques</h2></div>
    <div class="deprat-tech-front-grid">
        <?php if ( $features ) : ?>
            <section class="deprat-tech-front-card deprat-card-features">
                <h3><?php echo deprat_product_tech_icon( 'features' ); ?> Caractéristiques techniques</h3>
                <dl><?php foreach ( $features as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div><?php endforeach; ?></dl>
            </section>
        <?php endif; ?>
        <?php if ( $norms ) : ?>
            <section class="deprat-tech-front-card deprat-card-norms">
                <h3><?php echo deprat_product_tech_icon( 'norms' ); ?> Normes</h3>
                <ul class="deprat-tech-norms"><?php foreach ( $norms as $norm ) : ?><li><?php echo esc_html( $norm ); ?></li><?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
        <?php if ( $tests ) : ?>
            <section class="deprat-tech-front-card deprat-card-tests">
                <h3><?php echo deprat_product_tech_icon( 'tests' ); ?> Valeurs testées</h3>
                <dl><?php foreach ( $tests as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div><?php endforeach; ?></dl>
            </section>
        <?php endif; ?>
        <?php if ( '' !== $delivery || '' !== $packaging || $package_quantity || $package_summary ) : ?>
            <section class="deprat-tech-front-card deprat-card-package">
                <h3><?php echo deprat_product_tech_icon( 'package' ); ?> Livraison et conditionnement</h3>
                <?php if ( $package_summary ) : ?><p class="deprat-tech-package-generated"><?php echo esc_html( $package_summary ); ?></p><?php endif; ?>
                <dl>
                    <?php if ( '' !== $delivery ) : ?><div><dt>Livraison</dt><dd><?php echo esc_html( $delivery ); ?></dd></div><?php endif; ?>
                    <?php if ( '' !== $packaging && ! $package_summary ) : ?><div><dt>Conditionnement</dt><dd><?php echo esc_html( $packaging ); ?></dd></div><?php endif; ?>
                    <?php if ( $package_quantity && ! $package_summary ) : ?><div><dt>Pièces par conditionnement</dt><dd><?php echo esc_html( $package_quantity ); ?></dd></div><?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>
        <?php if ( $diagrams ) : ?>
            <section class="deprat-tech-front-card deprat-tech-schematics">
                <h3><?php echo deprat_product_tech_icon( 'diagram' ); ?> Schémas et dimensions</h3>
                <div class="deprat-tech-images">
                    <?php foreach ( $diagrams as $diagram ) :
                        $image_url = wp_get_attachment_url( $diagram['image'] );
                        if ( ! $image_url ) { continue; }
                        ?>
                        <figure>
                            <a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo wp_get_attachment_image( $diagram['image'], 'large', false, array( 'alt' => $diagram['title'], 'loading' => 'lazy' ) ); ?>
                            </a>
                            <figcaption><?php echo esc_html( $diagram['title'] ); ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if ( $datasheets ) : ?>
            <section class="deprat-tech-front-card deprat-tech-document">
                <h3><?php echo deprat_product_tech_icon( 'pdf' ); ?> Fiches techniques</h3>
                <p>Consultez ou téléchargez les documents techniques du produit au format PDF.</p>
                <div class="deprat-tech-documents">
                    <?php foreach ( $datasheets as $sheet ) :
                        $url = wp_get_attachment_url( $sheet['file'] );
                        if ( ! $url ) { continue; } ?>
                        <a class="deprat-tech-download" href="<?php echo esc_url( $url ); ?>" download>
                            <?php echo esc_html( $sheet['title'] ); ?> — Télécharger la fiche technique PDF
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
