<?php
/**
 * Bandeau d'accueil DEPRAT : diaporama administrable et avantages professionnels.
 * Les diapositives utilisent le type de contenu "Slides d’accueil" (sans URL publique).
 */
defined( 'ABSPATH' ) || exit;

function deprat_home_register_slides() {
    register_post_type( 'deprat_slide', array(
        'labels' => array(
            'name'               => 'Slides d’accueil',
            'singular_name'      => 'Slide d’accueil',
            'menu_name'          => 'Slides d’accueil',
            'add_new'            => 'Ajouter un slide',
            'add_new_item'       => 'Ajouter un slide d’accueil',
            'edit_item'          => 'Modifier le slide',
            'all_items'          => 'Tous les slides',
            'not_found'          => 'Aucun slide créé.',
            'featured_image'     => 'Image du slide',
            'set_featured_image' => 'Choisir l’image du slide',
        ),
        'public'             => false,
        'publicly_queryable' => false,
        'exclude_from_search'=> true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-images-alt2',
        'menu_position'      => 25,
        'supports'           => array( 'title', 'thumbnail', 'page-attributes', 'revisions' ),
        'capability_type'    => 'post',
    ) );
}
add_action( 'init', 'deprat_home_register_slides' );

function deprat_home_slide_fields() {
    add_meta_box( 'deprat-home-slide-content', 'Texte et bouton du slide', 'deprat_home_slide_fields_html', 'deprat_slide', 'normal', 'high' );
    add_meta_box( 'deprat-home-slide-image', 'Image du slide', 'deprat_home_slide_image_html', 'deprat_slide', 'side', 'high' );
    add_meta_box( 'deprat-home-slide-photo-guide', 'Format conseillé pour l’image', 'deprat_home_slide_photo_guide', 'deprat_slide', 'side', 'default' );
    add_meta_box( 'deprat-home-slide-visibility', 'Affichage sur l’accueil', 'deprat_home_slide_visibility_html', 'deprat_slide', 'side', 'high' );
}
add_action( 'add_meta_boxes_deprat_slide', 'deprat_home_slide_fields' );

/** Masquer un slide sans en supprimer ni l'image ni les textes. */
function deprat_home_slide_is_hidden( $id ) {
    return '1' === (string) get_post_meta( $id, '_deprat_home_slide_hidden', true );
}

function deprat_home_slide_visibility_html( $post ) {
    echo '<p><label><input type="checkbox" name="deprat_home_slide_hidden" value="1" ' . checked( deprat_home_slide_is_hidden( $post->ID ), true, false ) . '> <strong>Masquer temporairement ce slide</strong></label></p>';
    echo '<p class="description">Le slide reste enregistré avec sa photo et son texte. Décochez cette case et enregistrez pour le réafficher. Plusieurs slides peuvent être visibles en même temps.</p>';
}

/**
 * Sélecteur d'image indépendant du thème Astra.
 * La metabox native « Image mise en avant » dépend du support du thème pour ce post type.
 */
function deprat_home_slide_image_id( $post_id ) {
    $id = absint( get_post_meta( $post_id, '_deprat_home_slide_image_id', true ) );
    if ( $id && wp_attachment_is_image( $id ) ) {
        return $id;
    }
    // Compatibilité avec les images mises en avant déjà renseignées auparavant.
    $legacy = (int) get_post_thumbnail_id( $post_id );
    return $legacy && wp_attachment_is_image( $legacy ) ? $legacy : 0;
}

function deprat_home_slide_image_html( $post ) {
    $id = deprat_home_slide_image_id( $post->ID );
    $url = $id ? wp_get_attachment_image_url( $id, 'medium_large' ) : '';
    echo '<div class="deprat-home-image-picker" data-deprat-home-image-picker>';
    echo '<input type="hidden" name="deprat_home_slide_image_id" data-deprat-image-id value="' . esc_attr( $id ) . '">';
    echo '<div data-deprat-image-preview style="margin-bottom:12px">';
    if ( $url ) {
        echo '<img src="' . esc_url( $url ) . '" alt="Aperçu de l’image du slide" style="width:100%;height:auto;max-height:220px;object-fit:cover;border:1px solid #ccd0d4;border-radius:5px">';
    } else {
        echo '<p class="description">Aucune image sélectionnée. Choisissez une photo pour l’arrière-plan du slide.</p>';
    }
    echo '</div>';
    echo '<p><button type="button" class="button button-primary" data-deprat-image-select>' . ( $id ? 'Remplacer l’image' : 'Ajouter une image' ) . '</button> ';
    echo '<button type="button" class="button" data-deprat-image-remove' . ( $id ? '' : ' hidden' ) . '>Retirer</button></p>';
    echo '<p class="description">Format recommandé : <strong>1920 × 650 px</strong>. L’image sera recadrée automatiquement selon l’écran.</p>';
    echo '</div>';
}

function deprat_home_slide_image_assets( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
    $screen = get_current_screen();
    if ( ! $screen || 'deprat_slide' !== $screen->post_type ) { return; }
    wp_enqueue_media();
    wp_enqueue_script( 'deprat-slide-image-admin', plugins_url( 'assets/js/home-slide-admin.js', dirname( __DIR__ ) . '/deprat-core.php' ), array( 'jquery', 'media-views' ), '0.1.46', true );
}
add_action( 'admin_enqueue_scripts', 'deprat_home_slide_image_assets' );

function deprat_home_slide_photo_guide() {
    echo '<p><strong>Format recommandé : 1920 × 650 pixels</strong> (horizontal, environ 3:1).</p>';
    echo '<p>JPG ou WebP, idéalement <strong>moins de 500 Ko</strong> après optimisation.</p>';
    echo '<p>Privilégiez une photo avec le produit ou la porte à <strong>droite</strong> et une zone dégagée à gauche : le texte sera ajouté par le site.</p>';
    echo '<p>Ajoutez votre photo dans le bloc <strong>Image du slide</strong>, puis cliquez sur <strong>Mettre à jour</strong> ou <strong>Publier</strong>.</p>';
    echo '<p>Pour définir l’ordre des slides, utilisez le champ <strong>Ordre</strong> (0 pour le premier) dans les attributs de l’élément.</p>';
}

function deprat_home_slide_fields_html( $post ) {
    wp_nonce_field( 'deprat_home_slide_save', 'deprat_home_slide_nonce' );
    $description = (string) get_post_meta( $post->ID, '_deprat_home_slide_description', true );
    $button_text = (string) get_post_meta( $post->ID, '_deprat_home_slide_button_text', true );
    $button_url = (string) get_post_meta( $post->ID, '_deprat_home_slide_button_url', true );
    echo '<p>Le <strong>titre du slide</strong> se renseigne dans le titre principal en haut de cette page. Les slides publiés et non masqués s’affichent automatiquement sur l’accueil.</p>';
    echo '<p><label for="deprat-home-slide-description"><strong>Texte de présentation</strong></label></p>';
    echo '<p><textarea id="deprat-home-slide-description" name="deprat_home_slide_description" rows="3" class="widefat" placeholder="Une gamme complète de pièces et accessoires réservée aux professionnels.">' . esc_textarea( $description ) . '</textarea></p>';
    echo '<p><label for="deprat-home-slide-button-text"><strong>Texte du bouton</strong></label></p>';
    echo '<p><input id="deprat-home-slide-button-text" type="text" name="deprat_home_slide_button_text" class="widefat" maxlength="90" value="' . esc_attr( $button_text ) . '" placeholder="Découvrir nos composants"></p>';
    echo '<p><label for="deprat-home-slide-button-url"><strong>Lien du bouton</strong></label></p>';
    echo '<p><input id="deprat-home-slide-button-url" type="text" name="deprat_home_slide_button_url" class="widefat" value="' . esc_attr( $button_url ) . '" placeholder="/boutique/"></p>';
    echo '<p class="description">Laissez le texte ou le lien du bouton vide pour ne pas afficher de bouton. Privilégiez un lien relatif comme /boutique/ pour qu’il fonctionne aussi après la migration de deprat.eu à deprat.fr. Les liens https:// sont également acceptés.</p>';
}

function deprat_home_save_slide( $post_id ) {
    if ( ! isset( $_POST['deprat_home_slide_nonce'] ) || ! is_string( $_POST['deprat_home_slide_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['deprat_home_slide_nonce'] ) ), 'deprat_home_slide_save' ) ) {
        return;
    }
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $fields = array(
        'deprat_home_slide_description' => '_deprat_home_slide_description',
        'deprat_home_slide_button_text' => '_deprat_home_slide_button_text',
        'deprat_home_slide_button_url' => '_deprat_home_slide_button_url',
    );
    update_post_meta( $post_id, '_deprat_home_slide_hidden', isset( $_POST['deprat_home_slide_hidden'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['deprat_home_slide_hidden'] ) ) ? '1' : '0' );
    // Toujours vérifier le type de média côté serveur, même si l’interface limite aux images.
    if ( isset( $_POST['deprat_home_slide_image_id'] ) && is_scalar( $_POST['deprat_home_slide_image_id'] ) ) {
        $image_id = absint( wp_unslash( $_POST['deprat_home_slide_image_id'] ) );
        if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
            $image_id = 0;
        }
        update_post_meta( $post_id, '_deprat_home_slide_image_id', $image_id );
        // Éviter qu'une ancienne image mise en avant réapparaisse après « Retirer ».
        if ( ! $image_id && get_post_thumbnail_id( $post_id ) ) {
            delete_post_thumbnail( $post_id );
        }
    }

    foreach ( $fields as $field => $meta_key ) {
        $raw = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
        if ( 'deprat_home_slide_button_url' === $field ) {
            $clean = esc_url_raw( $raw, array( 'http', 'https' ) );
        } elseif ( 'deprat_home_slide_description' === $field ) {
            $clean = sanitize_textarea_field( $raw );
        } else {
            $clean = sanitize_text_field( $raw );
        }
        update_post_meta( $post_id, $meta_key, $clean );
    }
}
add_action( 'save_post_deprat_slide', 'deprat_home_save_slide' );

/** Statut et actions rapides dans la liste des slides WordPress. */
function deprat_home_slide_admin_columns( $columns ) {
    $columns['deprat_slide_image'] = 'Image';
    $columns['deprat_slide_visibility'] = 'Affichage';
    $columns['deprat_slide_order'] = 'Ordre';
    return $columns;
}
add_filter( 'manage_deprat_slide_posts_columns', 'deprat_home_slide_admin_columns' );

function deprat_home_slide_admin_column( $column, $post_id ) {
    if ( 'deprat_slide_image' === $column ) {
        $image_id = deprat_home_slide_image_id( $post_id );
        echo $image_id ? wp_get_attachment_image( $image_id, array( 110, 60 ) ) : '—';
    } elseif ( 'deprat_slide_visibility' === $column ) {
        if ( 'publish' !== get_post_status( $post_id ) ) {
            echo 'Brouillon / non publié';
        } else {
            echo deprat_home_slide_is_hidden( $post_id ) ? '<strong>Masqué</strong>' : '<strong style="color:#176e41">Visible</strong>';
        }
    } elseif ( 'deprat_slide_order' === $column ) {
        echo (int) get_post_field( 'menu_order', $post_id );
    }
}
add_action( 'manage_deprat_slide_posts_custom_column', 'deprat_home_slide_admin_column', 10, 2 );

function deprat_home_slide_row_actions( $actions, $post ) {
    if ( 'deprat_slide' !== $post->post_type || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }
    $hidden = deprat_home_slide_is_hidden( $post->ID );
    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=deprat_home_toggle_slide&slide_id=' . (int) $post->ID ),
        'deprat_home_toggle_' . $post->ID
    );
    $actions['deprat_slide_visibility'] = '<a href="' . esc_url( $url ) . '">' . ( $hidden ? 'Afficher sur l’accueil' : 'Masquer de l’accueil' ) . '</a>';
    return $actions;
}
add_filter( 'post_row_actions', 'deprat_home_slide_row_actions', 10, 2 );

function deprat_home_toggle_slide() {
    $id = isset( $_GET['slide_id'] ) ? absint( $_GET['slide_id'] ) : 0;
    if ( ! $id || 'deprat_slide' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ! current_user_can( 'edit_post', $id ) ) {
        wp_die( 'Action non autorisée.', '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'deprat_home_toggle_' . $id );
    update_post_meta( $id, '_deprat_home_slide_hidden', deprat_home_slide_is_hidden( $id ) ? '0' : '1' );
    wp_safe_redirect( admin_url( 'edit.php?post_type=deprat_slide&deprat_slide_updated=1' ) );
    exit;
}
add_action( 'admin_post_deprat_home_toggle_slide', 'deprat_home_toggle_slide' );

function deprat_home_slide_updated_notice() {
    if ( ! isset( $_GET['deprat_slide_updated'] ) || ! current_user_can( 'edit_posts' ) ) { return; }
    echo '<div class="notice notice-success is-dismissible"><p>Affichage du slide mis à jour.</p></div>';
}
add_action( 'admin_notices', 'deprat_home_slide_updated_notice' );

/** Récupère les slides publiés ; une diapositive de démonstration s'affiche avant le premier ajout. */
function deprat_home_get_slides() {
    $query = new WP_Query( array(
        'post_type'              => 'deprat_slide',
        'post_status'            => 'publish',
        'posts_per_page'         => 16,
        'orderby'                => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_term_cache' => false,
    ) );
    $slides = array();
    foreach ( $query->posts as $item ) {
        if ( deprat_home_slide_is_hidden( $item->ID ) || '' === trim( get_the_title( $item->ID ) ) ) { continue; }
        $slides[] = array(
            'id'          => $item->ID,
            'title'       => get_the_title( $item->ID ),
            'description' => (string) get_post_meta( $item->ID, '_deprat_home_slide_description', true ),
            'button_text' => (string) get_post_meta( $item->ID, '_deprat_home_slide_button_text', true ),
            'button_url'  => (string) get_post_meta( $item->ID, '_deprat_home_slide_button_url', true ),
            'image_id'    => deprat_home_slide_image_id( $item->ID ),
        );
    }
    // Afficher la démonstration seulement si aucun slide n'a encore été créé.
    // Si l'équipe a masqué tous les slides, ne réafficher aucun bandeau par surprise.
    $counts = wp_count_posts( 'deprat_slide' );
    $created = $counts && ( (int) $counts->publish + (int) $counts->draft + (int) $counts->future + (int) $counts->pending + (int) $counts->private ) > 0;
    if ( ! $slides && ! $created ) {
        $slides[] = array(
            'id'          => 0,
            'title'       => 'Vos composants techniques pour volets roulants et portes de garage',
            'description' => 'Une gamme complète de pièces, accessoires et automatismes, réservée aux professionnels.',
            'button_text' => 'Découvrir nos composants',
            'button_url'  => home_url( '/boutique/' ),
            'image_id'    => 0,
        );
    }
    return $slides;
}

function deprat_home_svg( $key ) {
    $paths = array(
        'gear'    => '<path d="M10.5 2h3l.6 2.1a8 8 0 0 1 1.7.7l2-1 2.2 2.2-1 2a8 8 0 0 1 .7 1.7L22 10.5v3l-2.3.6a8 8 0 0 1-.7 1.7l1 2-2.2 2.2-2-1a8 8 0 0 1-1.7.7L13.5 22h-3l-.6-2.3a8 8 0 0 1-1.7-.7l-2 1L4 17.8l1-2a8 8 0 0 1-.7-1.7L2 13.5v-3l2.3-.6A8 8 0 0 1 5 8.2l-1-2L6.2 4l2 1a8 8 0 0 1 1.7-.7z"/><circle cx="12" cy="12" r="3.4"/>',
        'people'  => '<circle cx="9" cy="8" r="3.3"/><path d="M2.5 21v-2.4a6.5 6.5 0 0 1 13 0V21M17 5.2a3.2 3.2 0 0 1 0 6.2M17.7 14a6.2 6.2 0 0 1 3.8 5.7V21"/>',
        'file'    => '<path d="M5 2h9l5 5v15H5zM14 2v5h5M8 11h8M8 15h8M8 19h6"/>',
        'quote'   => '<rect x="4" y="2" width="16" height="20" rx="2"/><rect x="7" y="5" width="10" height="4" rx=".5"/><path d="M8 13h1M12 13h1M16 13h1M8 17h1M12 17h1M16 17h1"/>',
    );
    $path = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['file'];
    return '<svg aria-hidden="true" width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

function deprat_home_hero_html() {
    $slides = deprat_home_get_slides();
    $count = count( $slides );
    ob_start();
    ?>
    <div class="deprat-home-block">
        <?php if ( $count ) : ?><section class="deprat-home-slider" aria-label="Actualités et gammes DEPRAT" aria-roledescription="carrousel" data-deprat-home-slider>
            <div class="deprat-home-slides">
                <?php foreach ( $slides as $index => $slide ) : ?>
                    <article class="deprat-home-slide" data-deprat-home-slide aria-roledescription="diapositive" aria-label="<?php echo esc_attr( ( $index + 1 ) . ' sur ' . $count ); ?>" <?php echo 0 !== $index ? 'hidden' : ''; ?>>
                        <?php if ( $slide['image_id'] ) : ?>
                            <?php echo wp_get_attachment_image( $slide['image_id'], 'full', false, array(
                                'class'         => 'deprat-home-slide-image',
                                'alt'           => '',
                                'loading'       => 0 === $index ? 'eager' : 'lazy',
                                'fetchpriority' => 0 === $index ? 'high' : 'auto',
                                'decoding'      => 'async',
                            ) ); ?>
                        <?php endif; ?>
                        <div class="deprat-home-overlay" aria-hidden="true"></div>
                        <div class="deprat-home-slide-inner">
                            <div class="deprat-home-slide-copy">
                                <h1><?php echo esc_html( $slide['title'] ); ?></h1>
                                <span class="deprat-home-gold-rule" aria-hidden="true"></span>
                                <?php if ( $slide['description'] ) : ?>
                                    <p><?php echo nl2br( esc_html( $slide['description'] ) ); ?></p>
                                <?php endif; ?>
                                <?php if ( $slide['button_text'] && $slide['button_url'] ) : ?>
                                    <a class="deprat-home-cta" href="<?php echo esc_url( $slide['button_url'] ); ?>">
                                        <?php echo esc_html( $slide['button_text'] ); ?>
                                        <span aria-hidden="true">→</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ( $count > 1 ) : ?>
                <button type="button" class="deprat-home-arrow deprat-home-arrow-prev" data-deprat-home-prev aria-label="Slide précédent"><span aria-hidden="true">‹</span></button>
                <button type="button" class="deprat-home-arrow deprat-home-arrow-next" data-deprat-home-next aria-label="Slide suivant"><span aria-hidden="true">›</span></button>
                <div class="deprat-home-dots" aria-label="Choisir une diapositive">
                    <?php foreach ( $slides as $index => $slide ) : ?>
                        <button type="button" class="deprat-home-dot" data-deprat-home-dot="<?php echo esc_attr( $index ); ?>" aria-label="<?php echo esc_attr( 'Afficher le slide ' . ( $index + 1 ) ); ?>" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section><?php endif; ?>
        <div class="deprat-home-benefits" aria-label="Les avantages DEPRAT">
            <div class="deprat-home-benefits-inner">
                <div class="deprat-home-benefit"><?php echo deprat_home_svg( 'gear' ); ?><div><strong>Spécialiste des composants</strong><span>Volets roulants, portes de garage et automatismes</span></div></div>
                <div class="deprat-home-benefit"><?php echo deprat_home_svg( 'people' ); ?><div><strong>Réservé aux professionnels</strong><span>Artisans, installateurs, revendeurs, industriels</span></div></div>
                <div class="deprat-home-benefit"><?php echo deprat_home_svg( 'file' ); ?><div><strong>Fiche produit détaillée</strong><span>Caractéristiques, compatibilités, schémas</span></div></div>
                <div class="deprat-home-benefit"><?php echo deprat_home_svg( 'quote' ); ?><div><strong>Demande de devis rapide</strong><span>Sélectionnez vos produits et envoyez votre demande</span></div></div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/** Ajout automatique en premier contenu, donc sous le menu Astra existant. */
function deprat_home_hero_prepend( $content ) {
    if ( is_admin() || ! is_front_page() || ! is_main_query() || ! in_the_loop()
        || (int) get_the_ID() !== (int) get_queried_object_id() || has_shortcode( $content, 'deprat_accueil_hero' ) ) {
        return $content;
    }
    static $rendered = false;
    if ( $rendered ) { return $content; }
    $rendered = true;
    return deprat_home_hero_html() . $content;
}
add_filter( 'the_content', 'deprat_home_hero_prepend', 95 );

/** Option de secours pour les pages conçues avec un template Elementor spécial. */
add_shortcode( 'deprat_accueil_hero', static function () {
    return deprat_home_hero_html();
} );

function deprat_home_hero_assets() {
    if ( ! is_front_page() ) { return; }
    wp_enqueue_style( 'deprat-home-hero', plugins_url( 'assets/css/home-hero.css', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.45' );
    wp_enqueue_script( 'deprat-home-hero', plugins_url( 'assets/js/home-hero.js', dirname( __DIR__ ) . '/deprat-core.php' ), array(), '0.1.45', true );
}
add_action( 'wp_enqueue_scripts', 'deprat_home_hero_assets', 25 );
