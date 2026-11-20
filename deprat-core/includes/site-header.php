<?php
/**
 * DEPRAT — en-tête Astra en trois niveaux (desktop).
 *
 * On conserve le vrai menu Astra et ses sous-menus (méga-menu DEPRAT),
 * les shortcodes WooCommerce pour la connexion et les notifications, ainsi
 * que la destination d'origine du panier de devis.
 */
defined( 'ABSPATH' ) || exit;

/** URL du logo historique, uniquement si le fichier est bien sur ce site. */
function deprat_site_header_logo_url() {
    $uploads = wp_upload_dir();
    if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
        $relative = '/2026/11/cropped-Logo-Deprat.png';
        if ( is_file( $uploads['basedir'] . $relative ) ) {
            return $uploads['baseurl'] . $relative;
        }
    }
    // Respecter le logo réellement paramétré dans Astra en cas de migration.
    $custom_logo_id = (int) get_theme_mod( 'custom_logo', 0 );
    return $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
}

/** Lorsque le logo officiel est présent, rester cohérent avec Astra mobile. */
function deprat_site_header_official_logo( $html, $blog_id = 0 ) {
    if ( is_admin() || ! is_string( $html ) ) { return $html; }
    $logo_url = deprat_site_header_logo_url();
    if ( ! $logo_url ) { return $html; }
    return '<a class="custom-logo-link deprat-official-logo" href="' . esc_url( home_url( '/' ) ) . '" rel="home">'
        . '<img class="custom-logo" src="' . esc_url( $logo_url ) . '" alt="DEPRAT"'
        . ' loading="eager" decoding="async"></a>';
}
add_filter( 'get_custom_logo', 'deprat_site_header_official_logo', 20, 2 );

/**
 * Astra place cette action dans <header id="masthead"> avant ses rangées.
 * On conserve donc en dessous la vraie navigation Astra (3e niveau).
 */
function deprat_site_header_tiers() {
    if ( is_admin() ) { return; }
    $logo_url = deprat_site_header_logo_url();
    $account_url = function_exists( 'wc_get_page_permalink' )
        ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
    $account_url = $account_url ? $account_url : wp_login_url();
    ?>
    <div class="deprat-header-desktop">
        <div class="deprat-header-contact-row">
            <div class="deprat-header-shell deprat-header-contact-inner">
                <div class="deprat-header-contact-links">
                    <span class="deprat-header-contact-address">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.8"/></svg>
                        4 rue de la Papinerie, 59115 Leers
                    </span>
                    <a href="tel:+33320896800">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 3h4l2 5-3 2c1.4 2.7 3.3 4.6 6 6l2-3 5 2v4c0 1-.8 2-2 2C10 21 3 14 3 5c0-1.2 1-2 2-2Z"/></svg>
                        03 20 89 68 00
                    </a>
                    <a href="mailto:contact@deprat.com">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                        contact@deprat.com
                    </a>
                </div>
                <div class="deprat-header-pro-banner">Site réservé aux professionnels</div>
            </div>
        </div>
        <div class="deprat-header-identity-row">
            <div class="deprat-header-shell deprat-header-identity-inner <?php echo is_user_logged_in() ? 'is-connected' : 'is-visitor'; ?>">
                <a class="deprat-header-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="DEPRAT — Accueil">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" width="90" height="90" alt="" loading="eager" decoding="async">
                    <?php endif; ?>
                    <span class="deprat-header-brand-copy">
                        <strong>DEPRAT</strong>
                        <small>Équipementier de la fermeture<br>au service des professionnels</small>
                    </span>
                </a>
                <form class="deprat-header-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <label class="screen-reader-text" for="deprat-header-search-input">Rechercher un produit ou une référence</label>
                    <input id="deprat-header-search-input" type="search" name="s"
                        value="<?php echo esc_attr( get_search_query() ); ?>"
                        placeholder="Rechercher une référence, un composant, un moteur…" autocomplete="off">
                    <input type="hidden" name="post_type" value="product">
                    <button type="submit" aria-label="Lancer la recherche">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5.5 5.5"/></svg>
                    </button>
                </form>
                <div class="deprat-header-pro-panel <?php echo is_user_logged_in() ? 'is-connected' : 'is-visitor'; ?>">
                    <div class="deprat-header-pro-actions">
                        <?php if ( is_user_logged_in() ) : ?>
                            <?php
                            // Reprendre le menu client et sa cloche existants : aucun doublon.
                            $menu_pro = do_shortcode( '[deprat_menu_pro]' );
                            $logout = function_exists( 'wc_get_account_endpoint_url' )
                                ? wc_get_account_endpoint_url( 'customer-logout' ) : wp_logout_url( home_url( '/' ) );
                            $logout_link = '<a class="deprat-header-logout" href="' . esc_url( $logout ) . '">Se déconnecter</a>';
                            if ( false !== strpos( $menu_pro, '</nav></details>' ) ) {
                                $menu_pro = str_replace( '</nav></details>', $logout_link . '</nav></details>', $menu_pro );
                            }
                            echo $menu_pro; // HTML échappé par les shortcodes DEPRAT existants.
                            ?>
                        <?php else : ?>
                            <?php echo do_shortcode( '[deprat_menu_pro]' ); ?>
                            <a class="deprat-header-account-button" href="<?php echo esc_url( $account_url ); ?>">Créer un compte pro</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( is_user_logged_in() ) : ?>
                    <?php echo deprat_site_header_quote_button(); // Lien panier, icône et compteur, un seul bouton. ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'astra_masthead_top', 'deprat_site_header_tiers', 5 );

/** L'élément ajouté dans le vrai menu reçoit les mêmes classes Astra et son lien panier. */
function deprat_site_header_quote_item( $items, $args ) {
    if ( is_admin() || ! is_object( $args ) || ! isset( $args->theme_location )
        || 'primary' !== $args->theme_location ) {
        return $items;
    }

    $url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
    if ( ! $url ) { return $items; }
    $quantity = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
    $badge = $quantity > 0
        ? '<span class="deprat-header-quote-count" aria-label="' . esc_attr( $quantity . ' articles dans la demande' ) . '">' . esc_html( $quantity > 99 ? '99+' : (string) $quantity ) . '</span>'
        : '';
    $icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>';
    $items .= '<li class="menu-item deprat-header-quote-menu-item">'
        . '<a class="menu-link" href="' . esc_url( $url ) . '" aria-label="Ma demande de devis">'
        . '<span class="deprat-header-quote-icon">' . $icon . $badge . '</span>'
        . '<span>Ma demande de devis</span></a></li>';
    return $items;
}
// La demande de devis figure désormais dans l'en-tête central : pas de doublon dans le menu.

/** Un bouton entier clicable, lié au panier WooCommerce et à son nombre d'articles. */
function deprat_site_header_quote_button() {
    $url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
    if ( ! $url ) { return ''; }
    $count = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
    $badge = $count > 0 ? '<span class="deprat-header-quote-count" aria-label="' . esc_attr( $count . ' articles dans la demande' ) . '">' . esc_html( $count > 99 ? '99+' : (string) $count ) . '</span>' : '';
    return '<a id="deprat-header-quote-link" class="deprat-header-quote-button" href="' . esc_url( $url ) . '">'
        . '<span class="deprat-header-quote-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></span>'
        . '<span>Ma demande de devis</span>' . $badge . '</a>';
}
/** Recalculer le compteur immédiatement après l'ajout AJAX au panier. */
add_filter( 'woocommerce_add_to_cart_fragments', static function ( $fragments ) {
    if ( is_user_logged_in() && function_exists( 'WC' ) && WC()->cart ) {
        $fragments['#deprat-header-quote-link'] = deprat_site_header_quote_button();
    }
    return $fragments;
} );
