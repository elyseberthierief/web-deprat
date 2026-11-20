<?php
/** Integration test: user filters of the DEPRAT admin (customer / admin split). */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! function_exists( 'deprat_admin_users_view' ) ) {
    throw new RuntimeException( 'Module des clients administrateurs non chargé.' );
}
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$admin = get_user_by( 'login', 'deprat-test' );
if ( ! $admin instanceof WP_User ) { throw new RuntimeException( 'Compte admin de test absent.' ); }
wp_set_current_user( $admin->ID );
set_current_screen( 'users' );
if ( ! deprat_admin_design_enabled() ) {
    throw new RuntimeException( 'Le mode admin DEPRAT doit être actif sur users.php.' );
}
$suffix = strtolower( wp_generate_password( 9, false, false ) );
$created = array();
try {
    foreach ( array( 'customer', 'deprat_pending' ) as $role ) {
        if ( ! get_role( $role ) ) {
            throw new RuntimeException( 'Rôle professionnel absent : ' . $role );
        }
        $id = wp_insert_user( array(
            'user_login' => 'deprat_ci_' . $role . '_' . $suffix,
            'user_email' => 'deprat_ci_' . $role . '_' . $suffix . '@example.test',
            'user_pass' => wp_generate_password( 25 ),
            'role' => $role,
        ) );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $created[$role] = (int) $id;
    }

    $_GET['deprat_view'] = 'clients';
    unset( $_GET['role'] );
    $clients = new WP_User_Query( array( 'number' => 200, 'fields' => 'ID' ) );
    $client_ids = array_map( 'intval', $clients->get_results() );
    if ( in_array( $admin->ID, $client_ids, true )
        || ! in_array( $created['customer'], $client_ids, true )
        || ! in_array( $created['deprat_pending'], $client_ids, true ) ) {
        throw new RuntimeException( 'La liste Clients doit montrer les clients et demandes, pas les administrateurs.' );
    }

    $_GET['deprat_view'] = 'admins';
    unset( $_GET['role'] );
    $admins = new WP_User_Query( array( 'number' => 200, 'fields' => 'ID' ) );
    $admin_ids = array_map( 'intval', $admins->get_results() );
    if ( ! in_array( $admin->ID, $admin_ids, true )
        || in_array( $created['customer'], $admin_ids, true )
        || in_array( $created['deprat_pending'], $admin_ids, true ) ) {
        throw new RuntimeException( 'La rubrique Administrateurs doit afficher uniquement les administrateurs.' );
    }

    $_GET['deprat_view'] = 'clients';
    $_GET['role'] = 'deprat_pending';
    $pending = new WP_User_Query( array( 'number' => 200, 'fields' => 'ID' ) );
    $pending_ids = array_map( 'intval', $pending->get_results() );
    if ( ! in_array( $created['deprat_pending'], $pending_ids, true )
        || in_array( $created['customer'], $pending_ids, true ) ) {
        throw new RuntimeException( 'Le bouton Comptes pro en attente ne filtre pas correctement.' );
    }
    echo "OK: clients professionnels, comptes en attente et administrateurs séparés.\n";
} finally {
    unset( $_GET['role'], $_GET['deprat_view'] );
    foreach ( $created as $user_id ) { wp_delete_user( $user_id ); }
    set_current_screen( '' );
}
