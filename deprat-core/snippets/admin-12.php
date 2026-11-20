<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT — Accueil WordPress : demandes récentes + comptes pro à valider.
 * Nouveau snippet PHP indépendant : ne remplace ni la V7.3 ni l'inscription pro V2.
 * Code Snippets > PHP > exécution uniquement dans l'administration.
 * Coller SANS la balise <?php.
 *
 * IMPORTANT : le bouton Valider déclenche le hook set_user_role du snippet
 * DEPRAT – Demande d'ouverture de compte professionnel V2, responsable
 * de l'e-mail d'activation. Garder ce snippet V2 actif.
 */

if ( ! function_exists( 'deprat_accueil_adv_statut' ) ) {
    function deprat_accueil_adv_statut( $status ) {
        $labels = array(
            'dpr-recu'    => 'Nouvelle demande',
            'dpr-encours' => 'Devis en traitement',
            'dpr-envoye'  => 'Devis à valider',
            'dpr-modifie' => 'Devis modifié à valider',
            'dpr-accepte' => 'Devis accepté',
            'dpr-facture' => 'Facture disponible',
            'dpr-termine' => 'Terminée',
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : wc_get_order_status_name( $status );
    }
}

/** Tableau de bord professionnel DEPRAT : accueil, raccourcis et 2 colonnes. */
function deprat_accueil_afficher_portail() {
    $user = wp_get_current_user();
    $first_name = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
    $welcome_name = $first_name ?: trim( (string) $user->display_name );
    $pending_count = (int) ( count_users()['avail_roles']['deprat_pending'] ?? 0 );
    $adv_count = function_exists( 'deprat_admin_adv_action_count' ) ? deprat_admin_adv_action_count() : 0;
    ?>
    <div class="deprat-dash-shell">
        <header class="deprat-dash-welcome">
            <div>
                <p class="deprat-dash-eyebrow">Tableau de bord professionnel</p>
                <h1>Bienvenue<?php echo $welcome_name ? ', ' . esc_html( $welcome_name ) : ''; ?> !</h1>
                <p>Retrouvez vos commandes, vos produits et les comptes professionnels à traiter.</p>
            </div>
            <span class="deprat-dash-brand">DEPRAT</span>
        </header>
        <nav class="deprat-dash-quick-links" aria-label="Accès rapides aux espaces DEPRAT">
            <?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
                <a class="deprat-dash-quick deprat-dash-quick--orders" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">
                    <span class="deprat-dash-quick-symbol" aria-hidden="true">☷</span>
                    <span class="deprat-dash-quick-content"><strong>Commandes</strong><small>Consulter et traiter les demandes</small></span>
                    <?php if ( $adv_count > 0 ) : ?><span class="deprat-dash-quick-count"><?php echo esc_html( (string) $adv_count ); ?> à traiter</span><?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if ( current_user_can( 'edit_products' ) ) : ?>
                <a class="deprat-dash-quick deprat-dash-quick--products" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">
                    <span class="deprat-dash-quick-symbol" aria-hidden="true">◇</span>
                    <span class="deprat-dash-quick-content"><strong>Produits</strong><small>Gérer les fiches du catalogue</small></span>
                    <span class="deprat-dash-quick-arrow" aria-hidden="true">↗</span>
                </a>
            <?php endif; ?>
            <?php if ( current_user_can( 'list_users' ) ) : ?>
                <a class="deprat-dash-quick deprat-dash-quick--pending" href="<?php echo esc_url( admin_url( 'users.php?deprat_view=clients&role=deprat_pending' ) ); ?>">
                    <span class="deprat-dash-quick-symbol" aria-hidden="true">♙</span>
                    <span class="deprat-dash-quick-content"><strong>Comptes pro en attente</strong><small>Examiner les nouvelles inscriptions</small></span>
                    <span class="deprat-dash-quick-count"><?php echo esc_html( (string) $pending_count ); ?></span>
                </a>
            <?php endif; ?>
        </nav>
        <div class="deprat-dash-two-columns">
            <?php if ( current_user_can( 'manage_woocommerce' ) && function_exists( 'wc_get_orders' ) ) : ?>
            <section class="deprat-dash-panel" aria-labelledby="deprat-dash-orders-heading">
                <header class="deprat-dash-panel-header">
                    <div><h2 id="deprat-dash-orders-heading">Dernières commandes</h2><p>Les 5 dossiers les plus récents</p></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">Tout voir</a>
                </header>
                <?php deprat_accueil_afficher_demandes(); ?>
            </section>
            <?php endif; ?>
            <?php if ( current_user_can( 'promote_users' ) && current_user_can( 'list_users' ) ) : ?>
            <section class="deprat-dash-panel" aria-labelledby="deprat-dash-pending-heading">
                <header class="deprat-dash-panel-header">
                    <div><h2 id="deprat-dash-pending-heading">Comptes à valider</h2><p>Demandes d'ouverture de compte professionnel</p></div>
                    <a href="<?php echo esc_url( admin_url( 'users.php?deprat_view=clients&role=deprat_pending' ) ); ?>">Tout voir</a>
                </header>
                <?php deprat_accueil_afficher_comptes(); ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** 1. Les deux widgets sur Tableau de bord > Accueil. */
add_action( 'wp_dashboard_setup', function () {
    // Un seul panneau large, organisé en interne en deux colonnes.
    // En mode classique, les widgets WordPress d'origine sont conservés.
    if ( function_exists( 'deprat_admin_design_enabled' ) && deprat_admin_design_enabled() ) {
        if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'promote_users' ) ) {
            wp_add_dashboard_widget(
                'deprat_accueil_portail',
                'Accueil DEPRAT',
                'deprat_accueil_afficher_portail'
            );
        }
        return;
    }
    if ( current_user_can( 'manage_woocommerce' ) && function_exists( 'wc_get_orders' ) ) {
        wp_add_dashboard_widget(
            'deprat_accueil_demandes',
            'DEPRAT · Dernières demandes et commandes',
            'deprat_accueil_afficher_demandes'
        );
    }

    // Seuls les administrateurs autorisés à promouvoir des utilisateurs
    // voient le bouton de validation des comptes professionnels.
    if ( current_user_can( 'promote_users' ) && current_user_can( 'list_users' ) ) {
        wp_add_dashboard_widget(
            'deprat_accueil_comptes',
            'DEPRAT · Comptes professionnels à valider',
            'deprat_accueil_afficher_comptes'
        );
    }
}, 30 );

/** 2. Dernières demandes : WooCommerce CRUD, compatible HPOS et stockage classique. */
function deprat_accueil_afficher_demandes() {
    if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
        echo '<p>Accès non autorisé.</p>';
        return;
    }

    // On limite le volume demandé à WooCommerce pour préserver la rapidité.
    // Dans une boutique DEPRAT dédiée, cela couvre largement les derniers dossiers.
    $commandes = wc_get_orders( array(
        'type'    => 'shop_order',
        'limit'   => 60,
        'orderby' => 'date',
        'order'   => 'DESC',
        'status'  => array( 'dpr-recu', 'dpr-encours', 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' ),
    ) );

    $trouvees = array();
    foreach ( $commandes as $commande ) {
        if ( ! $commande instanceof WC_Order ) { continue; }
        if ( 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) { continue; }
        $trouvees[] = $commande;
        if ( count( $trouvees ) >= 5 ) { break; }
    }

    echo '<div class="deprat-home-help">Accès rapide aux dossiers du service ADV. Les états sont actualisés lors du chargement de la page.</div>';
    if ( empty( $trouvees ) ) {
        echo '<div class="deprat-home-empty">Aucune demande DEPRAT récente.</div>';
        return;
    }

    echo '<div class="deprat-home-scroll"><table class="deprat-home-table"><thead><tr><th>Demande</th><th>Client / Société</th><th>Date</th><th>État</th><th></th></tr></thead><tbody>';
    foreach ( $trouvees as $commande ) {
        $etat = $commande->get_status();
        $societe = trim( (string) $commande->get_billing_company() );
        $contact = trim( (string) $commande->get_formatted_billing_full_name() );
        if ( '' === $societe ) { $societe = $contact ?: $commande->get_billing_email(); }
        $date = $commande->get_date_created();
        $date_affichee = $date ? wp_date( 'd/m/Y', $date->getTimestamp() ) : '—';
        $url = $commande->get_edit_order_url();

        echo '<tr>';
        echo '<td class="deprat-home-reference"><a href="' . esc_url( $url ) . '">#' . esc_html( $commande->get_order_number() ) . '</a></td>';
        echo '<td><strong>' . esc_html( $societe ) . '</strong>';
        if ( $contact && $contact !== $societe ) {
            echo '<span class="deprat-home-muted">' . esc_html( $contact ) . '</span>';
        }
        echo '</td>';
        echo '<td class="deprat-home-date">' . esc_html( $date_affichee ) . '</td>';
        echo '<td><span class="deprat-home-etat deprat-home-etat-' . esc_attr( sanitize_html_class( $etat ) ) . '">' . esc_html( deprat_accueil_adv_statut( $etat ) ) . '</span></td>';
        echo '<td><a class="deprat-home-action" href="' . esc_url( $url ) . '" aria-label="Ouvrir la demande ' . esc_attr( $commande->get_order_number() ) . '">Ouvrir</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '<p class="deprat-home-footer"><a href="' . esc_url( admin_url( 'admin.php?page=wc-orders' ) ) . '">Voir toutes les commandes WooCommerce →</a></p>';
}

/** 3. Comptes pro en attente. */
function deprat_accueil_afficher_comptes() {
    if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'list_users' ) ) {
        echo '<p>Accès non autorisé.</p>';
        return;
    }

    $recherche = new WP_User_Query( array(
        'role'        => 'deprat_pending',
        'meta_key'    => 'deprat_account_status',
        'meta_value'  => 'pending',
        'number'      => 5,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'count_total' => true,
    ) );
    $utilisateurs = $recherche->get_results();
    $total = (int) $recherche->get_total();

    if ( isset( $_GET['deprat_compte_valide'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['deprat_compte_valide'] ) ) ) {
        echo '<div class="notice notice-success inline"><p><strong>Compte professionnel activé.</strong> L’e-mail d’activation a été demandé au système d’envoi.</p></div>';
    }

    echo '<p class="deprat-home-help">' . esc_html( $total ) . ' compte(s) en attente. Vérifie les informations de l’entreprise et le SIRET avant de valider.</p>';
    if ( ! $utilisateurs ) {
        echo '<div class="deprat-home-empty">✓ Aucun compte professionnel en attente.</div>';
        return;
    }

    echo '<div class="deprat-home-scroll"><table class="deprat-home-table deprat-home-table-users"><thead><tr><th>Société / Contact</th><th>SIRET</th><th>Demande</th><th>Action</th></tr></thead><tbody>';
    foreach ( $utilisateurs as $utilisateur ) {
        if ( ! $utilisateur instanceof WP_User ) { continue; }
        $id = (int) $utilisateur->ID;
        $societe = (string) get_user_meta( $id, 'billing_company', true );
        $siret   = (string) get_user_meta( $id, 'deprat_siret', true );
        $contact = trim( (string) get_user_meta( $id, 'billing_first_name', true ) . ' ' . (string) get_user_meta( $id, 'billing_last_name', true ) );
        $date    = strtotime( (string) $utilisateur->user_registered );
        $lien    = get_edit_user_link( $id );
        $peut_valider = current_user_can( 'edit_user', $id );

        echo '<tr>';
        echo '<td><strong>' . esc_html( $societe ?: 'Société non renseignée' ) . '</strong>';
        if ( $contact ) { echo '<span class="deprat-home-muted">' . esc_html( $contact ) . '</span>'; }
        echo '<span class="deprat-home-muted">' . esc_html( $utilisateur->user_email ) . '</span></td>';
        echo '<td><span class="deprat-home-siret">' . esc_html( $siret ?: '—' ) . '</span></td>';
        echo '<td class="deprat-home-date">' . esc_html( $date ? wp_date( 'd/m/Y', $date ) : '—' ) . '</td>';
        echo '<td class="deprat-home-validate-cell">';
        if ( $lien ) {
            echo '<a class="deprat-home-details" href="' . esc_url( $lien ) . '">Examiner</a>';
        }
        if ( $peut_valider ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="deprat-home-validate-form" onsubmit="return confirm(\'Confirmer la validation de ce compte professionnel ? Un e-mail d’activation sera envoyé.\');">';
            echo '<input type="hidden" name="action" value="deprat_accueil_valider_compte">';
            echo '<input type="hidden" name="deprat_user_id" value="' . esc_attr( (string) $id ) . '">';
            wp_nonce_field( 'deprat_accueil_valider_' . $id, 'deprat_accueil_nonce' );
            echo '<button type="submit" class="button button-primary deprat-home-validate-btn">Valider</button>';
            echo '</form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<p class="deprat-home-footer"><a href="' . esc_url( admin_url( 'users.php?deprat_view=clients&role=deprat_pending' ) ) . '">Afficher les comptes en attente →</a></p>';
}

/** 4. Validation sécurisée d'un compte depuis l'accueil WordPress. */
add_action( 'admin_post_deprat_accueil_valider_compte', function () {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        wp_die( 'Méthode non autorisée.', 'DEPRAT', array( 'response' => 405 ) );
    }
    if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'list_users' ) ) {
        wp_die( 'Permissions insuffisantes.', 'DEPRAT', array( 'response' => 403 ) );
    }

    $id = isset( $_POST['deprat_user_id'] ) ? absint( $_POST['deprat_user_id'] ) : 0;
    if ( ! $id ) {
        wp_die( 'Compte introuvable.', 'DEPRAT', array( 'response' => 400 ) );
    }
    check_admin_referer( 'deprat_accueil_valider_' . $id, 'deprat_accueil_nonce' );

    if ( ! current_user_can( 'edit_user', $id ) ) {
        wp_die( 'Vous ne pouvez pas modifier ce compte.', 'DEPRAT', array( 'response' => 403 ) );
    }
    $utilisateur = get_userdata( $id );
    if ( ! $utilisateur instanceof WP_User
        || ! in_array( 'deprat_pending', (array) $utilisateur->roles, true )
        || 'pending' !== get_user_meta( $id, 'deprat_account_status', true ) ) {
        wp_die( 'Ce compte n’est plus en attente de validation.', 'DEPRAT', array( 'response' => 409 ) );
    }

    // On refuse la validation si le snippet d'inscription pro n'est pas actif :
    // son hook set_user_role gère le statut et l'e-mail d'activation.
    if ( ! function_exists( 'deprat_pro_send_client_email' ) ) {
        wp_die( 'Le snippet « Inscription professionnelle DEPRAT » doit être actif pour valider et notifier ce compte.', 'DEPRAT', array( 'response' => 409 ) );
    }

    // IMPORTANT : déclenche set_user_role du snippet V2, qui passe le méta
    // deprat_account_status en approved et envoie le mail HTML d'activation.
    $utilisateur->set_role( 'customer' );
    update_user_meta( $id, 'deprat_dashboard_approved_by', get_current_user_id() );
    update_user_meta( $id, 'deprat_dashboard_approved_at', gmdate( 'Y-m-d H:i:s' ) );

    wp_safe_redirect( add_query_arg( 'deprat_compte_valide', '1', admin_url( 'index.php' ) ) );
    exit;
} );

/** 5. Design uniquement sur le Tableau de bord WordPress. */
add_action( 'admin_head-index.php', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'promote_users' ) ) { return; }
    ?>
    <style id="deprat-accueil-dashboard-css">
    #deprat_accueil_demandes,#deprat_accueil_comptes{border:1px solid #dbe5f0;box-shadow:0 3px 15px rgba(25,54,87,.045);border-radius:10px;overflow:hidden}
    #deprat_accueil_demandes .postbox-header,#deprat_accueil_comptes .postbox-header{background:#f5f8fd;border-bottom:1px solid #e2eaf2}
    #deprat_accueil_demandes .hndle,#deprat_accueil_comptes .hndle{color:#143d78;font-size:15px;font-weight:700}
    #deprat_accueil_demandes .inside,#deprat_accueil_comptes .inside{margin:0;padding:0}
    .deprat-home-help{padding:13px 16px;font-size:12px;color:#61748c;background:#fff}
    .deprat-home-empty{margin:0 15px 15px;padding:18px;border:1px dashed #d4e0ec;border-radius:9px;background:#fafcfe;color:#477355;font-weight:600}
    .deprat-home-scroll{max-width:100%;overflow-x:auto}
    .deprat-home-table{width:100%;border-collapse:collapse;min-width:530px;font-size:13px}
    .deprat-home-table thead{background:#f6f9fd}
    .deprat-home-table th{text-align:left;padding:11px 13px;font-weight:700;font-size:11px;color:#67809b;text-transform:uppercase;letter-spacing:.3px;border-top:1px solid #edf1f7;border-bottom:1px solid #edf1f7}
    .deprat-home-table td{padding:13px;border-bottom:1px solid #edf1f6;vertical-align:middle;color:#27405d}
    .deprat-home-table tbody tr:hover{background:#f9fbfd}
    .deprat-home-table tbody tr:last-child td{border-bottom:0}
    .deprat-home-table td strong{font-size:13px;line-height:1.3;color:#173d70}
    .deprat-home-reference a{font-weight:800;text-decoration:none;color:#165da9;font-size:14px}
    .deprat-home-muted{display:block;color:#71829a;font-size:11px;margin-top:3px;overflow-wrap:anywhere}
    .deprat-home-date{white-space:nowrap;font-size:12px;color:#6a7990}
    .deprat-home-etat{display:inline-flex;align-items:center;white-space:nowrap;padding:5px 9px;border-radius:30px;font-size:11px;font-weight:700;background:#eaf1fb;color:#21568b}
    .deprat-home-etat-dpr-recu{background:#eaf2fb;color:#225b9e}
    .deprat-home-etat-dpr-encours{background:#fff4dd;color:#94620a}
    .deprat-home-etat-dpr-envoye{background:#fef1d9;color:#92611a}
    .deprat-home-etat-dpr-accepte{background:#e9f7ec;color:#277742}
    .deprat-home-etat-dpr-facture{background:#f0eafa;color:#6c4c9d}
    .deprat-home-etat-dpr-termine{background:#edf1f4;color:#556b7a}
    .deprat-home-action,.deprat-home-details{text-decoration:none;font-size:12px;font-weight:700;color:#165da9}
    .deprat-home-siret{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px}
    .deprat-home-validate-cell{white-space:nowrap}
    .deprat-home-validate-form{display:inline-block;margin-left:8px}
    .deprat-home-validate-form .deprat-home-validate-btn{background:#147a4b;border-color:#147a4b;border-radius:6px;padding:2px 10px;min-height:28px;line-height:1.6;font-weight:700}
    .deprat-home-validate-form .deprat-home-validate-btn:hover{background:#0d603a;border-color:#0d603a}
    .deprat-home-footer{margin:0;padding:12px 16px;background:#fafbfd;border-top:1px solid #e8eef5}
    .deprat-home-footer a{font-size:12px;text-decoration:none;font-weight:700}
    @media(max-width:782px){.deprat-home-table{min-width:540px}.deprat-home-table td{padding:12px 10px}}

    /* 0.1.78 — Portail d'accueil DEPRAT : accueil personnalisé et deux colonnes.
       Ciblé uniquement sur l'admin DEPRAT, sans toucher au WordPress classique. */
    body.deprat-admin-mode.index-php #wpbody-content>.wrap>h1{display:none!important}
    body.deprat-admin-mode.index-php #dashboard-widgets #postbox-container-1{
        width:100%!important;max-width:100%!important;float:none!important;
    }
    body.deprat-admin-mode.index-php #dashboard-widgets #postbox-container-1 .meta-box-sortables{
        min-height:0!important;
    }
    body.deprat-admin-mode.index-php #deprat_accueil_portail{
        border:0!important;box-shadow:none!important;background:transparent!important;
        margin:0!important;overflow:visible!important;
    }
    body.deprat-admin-mode.index-php #deprat_accueil_portail>.postbox-header{
        display:none!important;
    }
    body.deprat-admin-mode.index-php #deprat_accueil_portail>.inside{
        display:block!important;margin:0!important;padding:0!important;
    }
    body.deprat-admin-mode.index-php .deprat-dash-shell{
        display:grid;gap:18px;
        max-width:100%;padding:4px 8px 12px 0;
        color:#183c6e;box-sizing:border-box;
    }
    body.deprat-admin-mode.index-php .deprat-dash-welcome{
        display:flex;align-items:center;justify-content:space-between;gap:24px;
        min-height:128px;padding:23px 27px;
        border:1px solid #d3e1f1;border-radius:14px;
        background:linear-gradient(112deg,#f7faff,#eaf3fe);
        box-shadow:0 4px 15px rgba(21,63,116,.035);
    }
    .deprat-dash-eyebrow{
        margin:0 0 7px!important;font-size:11px!important;
        letter-spacing:.075em;text-transform:uppercase;
        color:#52749b;font-weight:650;
    }
    body.deprat-admin-mode.index-php .deprat-dash-welcome h1{
        display:block!important;margin:0 0 7px!important;
        font-size:clamp(23px,2.2vw,31px)!important;
        letter-spacing:-.025em!important;font-weight:660!important;
        line-height:1.2;color:#143e7a!important;
    }
    .deprat-dash-welcome p:last-child{
        margin:0;font-size:13px;line-height:1.5;color:#617994;
    }
    .deprat-dash-brand{
        color:#173e7a;font-size:19px;font-weight:750;letter-spacing:.08em;
        white-space:nowrap;
    }
    .deprat-dash-quick-links{
        display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
        gap:14px;
    }
    .deprat-dash-quick{
        position:relative;display:flex;align-items:center;gap:14px;
        min-width:0;min-height:106px;padding:17px 18px;
        border-radius:13px;border:1px solid rgba(15,35,76,.08);
        color:#fff!important;text-decoration:none!important;
        box-shadow:0 5px 15px rgba(15,49,93,.095);
        transition:transform .15s,box-shadow .15s;
    }
    .deprat-dash-quick:hover{
        transform:translateY(-2px);
        box-shadow:0 10px 22px rgba(15,49,93,.14);
        color:#fff!important;
    }
    .deprat-dash-quick:focus-visible{outline:3px solid #84addd;outline-offset:3px}
    .deprat-dash-quick--orders{background:linear-gradient(115deg,#403b88,#282e66)}
    .deprat-dash-quick--products{background:linear-gradient(115deg,#544996,#35377d)}
    .deprat-dash-quick--pending{background:linear-gradient(115deg,#334e8c,#263e70)}
    .deprat-dash-quick-symbol{
        flex:0 0 43px;height:43px;display:grid;place-items:center;
        border:1px solid rgba(255,255,255,.21);border-radius:11px;
        background:rgba(255,255,255,.12);font-size:26px;line-height:1;
    }
    .deprat-dash-quick-content{min-width:0;display:grid;gap:5px}
    .deprat-dash-quick-content strong{
        display:block;color:#fff;font-size:17px;line-height:1.22;font-weight:650;
    }
    .deprat-dash-quick-content small{
        display:block;color:rgba(255,255,255,.83);
        font-size:11.5px;line-height:1.4;
    }
    .deprat-dash-quick-count,.deprat-dash-quick-arrow{
        margin-left:auto;flex:0 0 auto;
        display:inline-flex;align-items:center;justify-content:center;
        min-height:29px;padding:5px 9px;border-radius:9px;
        background:rgba(255,255,255,.2);
        color:#fff;font-size:11px;font-weight:650;white-space:nowrap;
    }
    .deprat-dash-quick-arrow{font-size:20px;padding:4px 9px}
    .deprat-dash-two-columns{
        display:grid;grid-template-columns:repeat(2,minmax(0,1fr));
        align-items:start;gap:18px;
    }
    .deprat-dash-panel{
        min-width:0;overflow:hidden;background:#fff;
        border:1px solid #dce6f1;border-radius:13px;
        box-shadow:0 3px 13px rgba(16,51,95,.045);
    }
    .deprat-dash-panel-header{
        display:flex;justify-content:space-between;align-items:center;gap:12px;
        padding:17px 19px;background:#f5f8fd;
        border-bottom:1px solid #e1eaf4;
    }
    .deprat-dash-panel-header h2{
        margin:0 0 4px;font-size:16px!important;font-weight:650;
        line-height:1.3;color:#143e7a;
    }
    .deprat-dash-panel-header p{
        margin:0;font-size:11.5px;line-height:1.4;color:#6c8099;
    }
    .deprat-dash-panel-header>a{
        flex:0 0 auto;color:#18538e;text-decoration:none;
        font-weight:650;font-size:12px;
    }
    .deprat-dash-panel-header>a:hover{text-decoration:underline}
    .deprat-dash-panel .deprat-home-help{
        padding:10px 17px;background:#fff;font-size:11.5px;
    }
    .deprat-dash-panel .deprat-home-scroll{overflow-x:auto;max-width:100%}
    .deprat-dash-panel .deprat-home-table{
        min-width:500px;font-size:12px;
    }
    .deprat-dash-panel .deprat-home-table th{
        padding:10px 10px;font-size:10.5px;letter-spacing:0;
    }
    .deprat-dash-panel .deprat-home-table td{
        padding:10px;line-height:1.35;font-size:11.5px;
    }
    .deprat-dash-panel .deprat-home-table td strong{font-size:12px}
    .deprat-dash-panel .deprat-home-table .deprat-home-reference a{font-size:12px}
    .deprat-dash-panel .deprat-home-footer{display:none!important}
    @media(max-width:1450px){
        .deprat-dash-quick-links{gap:11px}
        .deprat-dash-quick{padding:15px 13px;gap:10px}
        .deprat-dash-quick-symbol{flex-basis:36px;height:36px}
        .deprat-dash-quick-content strong{font-size:15px}
    }
    @media(max-width:1250px){
        .deprat-dash-two-columns{grid-template-columns:minmax(0,1fr)}
    }
    @media(max-width:900px){
        .deprat-dash-quick-links{grid-template-columns:minmax(0,1fr)}
        .deprat-dash-quick{min-height:83px}
        .deprat-dash-welcome{padding:18px!important}
        .deprat-dash-brand{display:none}
    }
    </style>
    <?php
} );
