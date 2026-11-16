<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="deprat-tech-front" aria-label="Informations techniques du produit">
    <div class="deprat-tech-front-header"><h2>Informations techniques</h2></div>
    <?php if ( '' !== $description ) : ?><p class="deprat-tech-description"><?php echo nl2br( esc_html( $description ) ); ?></p><?php endif; ?>
    <div class="deprat-tech-front-grid">
        <?php if ( $features ) : ?>
            <section class="deprat-tech-front-card">
                <h3>Caractéristiques techniques</h3>
                <dl><?php foreach ( $features as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div><?php endforeach; ?></dl>
            </section>
        <?php endif; ?>
        <?php if ( $norms ) : ?>
            <section class="deprat-tech-front-card">
                <h3>Normes</h3>
                <ul class="deprat-tech-norms"><?php foreach ( $norms as $norm ) : ?><li><?php echo esc_html( $norm ); ?></li><?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
        <?php if ( $tests ) : ?>
            <section class="deprat-tech-front-card">
                <h3>Valeurs testées</h3>
                <dl><?php foreach ( $tests as $row ) : ?><div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div><?php endforeach; ?></dl>
            </section>
        <?php endif; ?>
        <?php if ( '' !== $delivery || '' !== $packaging || $package_quantity ) : ?>
            <section class="deprat-tech-front-card">
                <h3>Livraison et conditionnement</h3>
                <dl>
                    <?php if ( '' !== $delivery ) : ?><div><dt>Livraison</dt><dd><?php echo esc_html( $delivery ); ?></dd></div><?php endif; ?>
                    <?php if ( '' !== $packaging ) : ?><div><dt>Conditionnement</dt><dd><?php echo esc_html( $packaging ); ?></dd></div><?php endif; ?>
                    <?php if ( $package_quantity ) : ?><div><dt>Pièces par conditionnement</dt><dd><?php echo esc_html( $package_quantity ); ?></dd></div><?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>
        <?php if ( $technical_image || $dimensions_image ) : ?>
            <section class="deprat-tech-front-card deprat-tech-schematics">
                <h3>Schémas et dimensions</h3>
                <div class="deprat-tech-images">
                    <?php foreach ( array( 'Schéma technique' => $technical_image, 'Schéma des dimensions' => $dimensions_image ) as $label => $attachment_id ) :
                        if ( ! $attachment_id ) { continue; }
                        $image_url = wp_get_attachment_url( $attachment_id );
                        if ( ! $image_url ) { continue; }
                        ?>
                        <figure><a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_get_attachment_image( $attachment_id, 'large', false, array( 'alt' => $label, 'loading' => 'lazy' ) ); ?></a><figcaption><?php echo esc_html( $label ); ?></figcaption></figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if ( $datasheet && wp_get_attachment_url( $datasheet ) ) : ?>
            <section class="deprat-tech-front-card deprat-tech-document">
                <h3>Fiche technique</h3>
                <p>Retrouvez la fiche technique de ce produit au format PDF.</p>
                <a class="deprat-tech-download" href="<?php echo esc_url( wp_get_attachment_url( $datasheet ) ); ?>" download>Télécharger la fiche technique PDF</a>
            </section>
        <?php endif; ?>
    </div>
</section>
