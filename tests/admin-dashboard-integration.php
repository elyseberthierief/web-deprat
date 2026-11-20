<?php
/**
 * Tests d'intégration du tableau de bord DEPRAT sur une installation WordPress.
 * Le test restaure le prénom et supprime la commande temporaire.
 */
defined( 'ABSPATH' ) || exit( 1 );
// WP-CLI exécute les tests hors wp-admin : charger explicitement le snippet.
if ( ! function_exists( 'deprat_accueil_afficher_portail' ) ) {
    require_once WP_PLUGIN_DIR . '/deprat-core/snippets/admin-12.php';
}

if ( ! function_exists( 'deprat_accueil_afficher_portail' )
    || ! function_exists( 'deprat_admin_adv_action_count' )
    || ! function_exists( 'wc_create_order' ) ) {
    throw new RuntimeException( 'Modules DEPRAT Admin ou WooCommerce manquants.' );
}

$admin = get_user_by( 'login', 'deprat-test' );
if ( ! $admin instanceof WP_User ) {
    throw new RuntimeException( 'Compte de test admin introuvable.' );
}
wp_set_current_user( $admin->ID );
$original_first_name = get_user_meta( $admin->ID, 'first_name', true );
$order = null;

try {
    update_user_meta( $admin->ID, 'first_name', 'TestAccueil' );
    ob_start();
    deprat_accueil_afficher_portail();
    $html = ob_get_clean();

    foreach ( array(
        'Bienvenue, TestAccueil',
        'deprat-dash-quick--orders',
        'deprat-dash-quick--products',
        'deprat-dash-quick--pending',
        'deprat-dash-two-columns',
        'Dernières commandes',
        'Comptes à valider',
        'users.php?deprat_view=clients',
        'role=deprat_pending',
    ) as $expected ) {
        if ( false === strpos( $html, $expected ) ) {
            throw new RuntimeException( 'Élément de tableau de bord absent : ' . $expected );
        }
    }

    delete_transient( 'deprat_admin_adv_action_count_v1' );
    $baseline = deprat_admin_adv_action_count();
    $order = wc_create_order( array( 'created_via' => 'deprat-dashboard-ci' ) );
    if ( ! $order instanceof WC_Order ) {
        throw new RuntimeException( 'Impossible de créer une commande de test.' );
    }
    $order->update_meta_data( '_deprat_demande', 'oui' );
    $order->set_status( 'dpr-recu' );
    $order->save();

    delete_transient( 'deprat_admin_adv_action_count_v1' );
    $after = deprat_admin_adv_action_count();
    if ( $after < $baseline + 1 ) {
        throw new RuntimeException( 'La pastille ADV doit compter la nouvelle demande reçue.' );
    }
    echo "OK: tableau de bord et pastille ADV testés.\n";
} finally {
    if ( $order instanceof WC_Order ) { $order->delete( true ); }
    delete_transient( 'deprat_admin_adv_action_count_v1' );
    update_user_meta( $admin->ID, 'first_name', $original_first_name );
}
