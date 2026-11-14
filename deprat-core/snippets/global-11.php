<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT — Cloche de notifications dans le menu professionnel V1.
 * NOUVEAU snippet PHP séparé. Code Snippets : exécuter partout, SANS <?php.
 * Se branche automatiquement sur [deprat_menu_pro] (snippet espace professionnel V3).
 * Notifications du client connecté uniquement. Aucune donnée transmise à un autre compte.
 */
function deprat_notifs_items_v1( $user_id ) {
    if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) { return array(); }
    $liste = wc_get_orders( array(
        'customer_id' => $user_id,
        'status' => array( 'dpr-envoye', 'dpr-facture', 'dpr-termine' ),
        'limit' => 60,
        'orderby' => 'date', 'order' => 'DESC',
    ) );
    $resultat = array();
    foreach ( $liste as $commande ) {
        if ( ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) || (int) $commande->get_customer_id() !== (int) $user_id ) { continue; }
        $id = $commande->get_id();
        $numero = $commande->get_order_number();
        if ( 'dpr-envoye' === $commande->get_status() && ! $commande->get_meta( '_deprat_devis_accepte_le', true ) ) {
            $version = (string) $commande->get_meta( '_deprat_pdf_fichier', true );
            if ( $version ) {
                $resultat[] = array( 'id' => $id, 'cle' => 'devis:' . $id . ':' . $version,
                    'texte' => 'Devis n° ' . $numero . ' à accepter', 'detail' => 'Consultez votre devis et confirmez votre accord.',
                    'type' => 'devis' );
            }
        }
        $date_facture = (string) $commande->get_meta( '_deprat_facture_envoyee_le', true );
        if ( $date_facture && $commande->get_meta( '_deprat_facture_pdf_fichier', true ) ) {
            $resultat[] = array( 'id' => $id, 'cle' => 'facture:' . $id . ':' . $date_facture,
                'texte' => 'Facture n° ' . $numero . ' disponible', 'detail' => 'Votre facture est consultable dans vos demandes.',
                'type' => 'facture' );
        }
    }
    return array_slice( $resultat, 0, 30 );
}
function deprat_notifs_marquees_v1( $user_id ) {
    $lues = get_user_meta( $user_id, '_deprat_notifications_lues_v1', true );
    return is_array( $lues ) ? $lues : array();
}
function deprat_notifs_html_v1() {
    if ( ! is_user_logged_in() ) { return ''; }
    $user_id = get_current_user_id();
    $notifications = deprat_notifs_items_v1( $user_id );
    $lues = deprat_notifs_marquees_v1( $user_id );
    $non_lues = 0;
    foreach ( $notifications as $notification ) {
        if ( ! isset( $lues[ $notification['cle'] ] ) ) { $non_lues++; }
    }
    $html = '<details class="deprat-cloche-v1"><summary aria-label="Notifications professionnelles, ' . esc_attr( $non_lues ) . ' non lues" title="Notifications professionnelles">';
    $html .= '<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>';
    if ( $non_lues ) { $html .= '<span class="deprat-cloche-compte">' . esc_html( $non_lues > 9 ? '9+' : $non_lues ) . '</span>'; }
    $html .= '</summary><div class="deprat-cloche-panneau"><strong class="deprat-cloche-titre">Notifications</strong>';
    if ( ! $notifications ) { $html .= '<p class="deprat-cloche-vide">Aucune notification pour le moment.</p>'; }
    else {
        $affichees = array_slice( $notifications, 0, 8 );
        foreach ( $affichees as $notif ) {
            $lue = isset( $lues[ $notif['cle'] ] );
            $html .= '<form method="post" class="deprat-cloche-item ' . ( $lue ? 'est-lue' : 'est-nouvelle' ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            $html .= '<input type="hidden" name="action" value="deprat_notification_lire_v1">';
            $html .= '<input type="hidden" name="id" value="' . esc_attr( $notif['id'] ) . '">';
            $html .= '<input type="hidden" name="cle" value="' . esc_attr( $notif['cle'] ) . '">';
            $html .= wp_nonce_field( 'deprat_notif_v1_' . $notif['id'], 'deprat_notif_nonce', true, false );
            $html .= '<button type="submit"><span class="deprat-cloche-icone" aria-hidden="true">' . ( 'facture' === $notif['type'] ? '▤' : '✓' ) . '</span><span><b>' . esc_html( $notif['texte'] ) . '</b><small>' . esc_html( $notif['detail'] ) . '</small></span>';
            if ( ! $lue ) { $html .= '<span class="deprat-cloche-point" aria-label="Non lue"></span>'; }
            $html .= '</button></form>';
        }
        if ( count( $notifications ) > 8 ) { $html .= '<p class="deprat-cloche-vide">Et d’autres notifications dans votre historique.</p>'; }
    }
    $lien = function_exists( 'deprat_lien_historique' ) ? deprat_lien_historique() : home_url( '/' );
    $html .= '<a class="deprat-cloche-tout" href="' . esc_url( $lien ) . '">Voir toutes mes demandes</a></div></details>';
    return $html;
}
add_shortcode( 'deprat_cloche_pro', 'deprat_notifs_html_v1' );
// Pas de modification de V3 : injection dans le HTML produit par son shortcode.
add_filter( 'do_shortcode_tag', function( $html, $tag ) {
    if ( 'deprat_menu_pro' !== $tag || ! is_user_logged_in() || false === strpos( $html, 'deprat-pro-details' ) ) { return $html; }
    return str_replace( '<details class="deprat-pro-details">', deprat_notifs_html_v1() . '<details class="deprat-pro-details">', $html );
}, 12, 2 );
// Marquer comme lue lors de l'ouverture ; seuls les événements appartenant au compte sont autorisés.
add_action( 'admin_post_deprat_notification_lire_v1', function() {
    if ( ! is_user_logged_in() ) { wp_die( 'Connexion requise.', 'DEPRAT', array('response'=>403) ); }
    $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
    $cle = isset( $_POST['cle'] ) ? sanitize_text_field( wp_unslash( $_POST['cle'] ) ) : '';
    $nonce = isset( $_POST['deprat_notif_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_notif_nonce'] ) ) : '';
    if ( ! $id || ! wp_verify_nonce( $nonce, 'deprat_notif_v1_' . $id ) ) { wp_die( 'Action expirée.', 'DEPRAT', array('response'=>403) ); }
    $notifications = deprat_notifs_items_v1( get_current_user_id() );
    $valide = false;
    foreach ( $notifications as $notification ) {
        if ( $notification['id'] === $id && hash_equals( $notification['cle'], $cle ) ) { $valide = true; break; }
    }
    if ( ! $valide ) { wp_die( 'Notification indisponible.', 'DEPRAT', array('response'=>403) ); }
    $lues = deprat_notifs_marquees_v1( get_current_user_id() );
    $lues[ $cle ] = gmdate( 'Y-m-d H:i:s' );
    if ( count( $lues ) > 250 ) { $lues = array_slice( $lues, -200, null, true ); }
    update_user_meta( get_current_user_id(), '_deprat_notifications_lues_v1', $lues );
    $lien = function_exists( 'deprat_lien_historique' ) ? deprat_lien_historique() : home_url( '/' );
    wp_safe_redirect( add_query_arg( 'deprat_q', (string) $id, $lien ) );
    exit;
} );
add_action( 'wp_head', function() {
    if ( ! is_user_logged_in() ) { return; }
    echo '<style id="deprat-notifications-v1">
    .deprat-cloche-v1{position:relative;z-index:1001;margin-right:10px;font-family:Agenda,Arial,sans-serif}
    .deprat-cloche-v1>summary{position:relative;cursor:pointer;list-style:none;color:#173e73;width:42px;height:42px;display:flex;justify-content:center;align-items:center;border:1px solid #e0e8f1;border-radius:50%;background:#f7f9fc}
    .deprat-cloche-v1>summary::-webkit-details-marker{display:none}.deprat-cloche-v1>summary:hover{background:#e9f2fc}
    .deprat-cloche-compte{position:absolute;right:-4px;top:-6px;min-width:20px;height:20px;border-radius:13px;padding:1px 5px;background:#d83943;color:white;font-size:11px;font-weight:700;text-align:center;line-height:18px}
    .deprat-cloche-panneau{position:absolute;z-index:9999;right:0;top:calc(100% + 9px);width:min(355px,90vw);max-height:450px;overflow:auto;background:white;box-shadow:0 14px 42px rgba(18,44,77,.18);border:1px solid #dbe5f0;border-radius:12px;color:#233e60}
    .deprat-cloche-titre{display:block;padding:16px 18px 11px;border-bottom:1px solid #edf1f5;font-size:17px}
    .deprat-cloche-item{margin:0!important;padding:0!important;border-bottom:1px solid #edf1f5}.deprat-cloche-item button{cursor:pointer;width:100%;display:flex;gap:10px;align-items:flex-start;border:0;border-radius:0;background:white;color:#203e64;padding:13px 16px;text-align:left;font:inherit;font-size:13px}
    .deprat-cloche-item.est-nouvelle button{background:#f5f9ff}.deprat-cloche-item button:hover{background:#e8f1fb}
    .deprat-cloche-item small{display:block;color:#6c7f93;font-size:12px;line-height:1.45;margin-top:4px}.deprat-cloche-icone{background:#e5effc;color:#125b9f;border-radius:6px;padding:5px 7px}
    .deprat-cloche-point{flex:0 0 7px;margin-top:9px;height:7px;background:#d83943;border-radius:50%}.deprat-cloche-tout{display:block;padding:14px 18px;text-align:center;text-decoration:none!important;color:#145da9!important;font-weight:700;font-size:13px}
    .deprat-cloche-vide{margin:0;padding:17px;color:#61778d;font-size:13px}
    </style>';
}, 40 );
