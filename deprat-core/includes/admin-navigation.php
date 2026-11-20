<?php
/**
 * Interface d'administration DEPRAT, exclusivement pour les administrateurs.
 * Les pages réelles du wp-admin ne sont ni déplacées ni désenregistrées :
 * la navigation alternative est fabriquée à partir de leurs liens existants.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_admin_design_enabled() {
    return is_admin() && current_user_can( 'manage_options' )
        && 'classic' !== get_user_meta( get_current_user_id(), 'deprat_admin_menu_mode', true );
}

function deprat_admin_menu_mode_url( $mode ) {
    return wp_nonce_url(
        add_query_arg( array(
            'action' => 'deprat_admin_menu_mode',
            'mode' => $mode,
        ), admin_url( 'admin-post.php' ) ),
        'deprat_admin_menu_mode_' . $mode
    );
}

add_action( 'admin_post_deprat_admin_menu_mode', static function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès réservé à l’administration.' ); }
    $mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
    if ( ! in_array( $mode, array( 'classic', 'deprat' ), true ) ) { wp_die( 'Mode invalide.' ); }
    check_admin_referer( 'deprat_admin_menu_mode_' . $mode );
    update_user_meta( get_current_user_id(), 'deprat_admin_menu_mode', $mode );
    $referer = wp_get_referer();
    $destination = $referer && false === strpos( $referer, 'admin-post.php' )
        ? wp_validate_redirect( $referer, admin_url( 'index.php' ) ) : admin_url( 'index.php' );
    wp_safe_redirect( $destination );
    exit;
} );

add_action( 'admin_bar_menu', static function ( $bar ) {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $is_enabled = deprat_admin_design_enabled();
    $bar->add_node( array(
        'id' => 'deprat-admin-mode',
        'parent' => 'top-secondary',
        'title' => $is_enabled ? '↩ Admin classique' : '◆ Admin DEPRAT',
        'href' => deprat_admin_menu_mode_url( $is_enabled ? 'classic' : 'deprat' ),
        'meta' => array( 'title' => $is_enabled ? 'Retrouver le menu WordPress d’origine' : 'Activer le menu DEPRAT' ),
    ) );
}, 999 );

add_action( 'admin_enqueue_scripts', static function () {
    if ( ! deprat_admin_design_enabled() ) { return; }
    wp_enqueue_style(
        'deprat-admin-navigation',
        plugins_url( 'assets/css/admin-navigation.css', dirname( __DIR__ ) . '/deprat-core.php' ),
        array(),
        '0.1.66'
    );
    wp_enqueue_script(
        'deprat-admin-navigation',
        plugins_url( 'assets/js/admin-navigation.js', dirname( __DIR__ ) . '/deprat-core.php' ),
        array(),
        '0.1.66',
        true
    );
    wp_add_inline_script(
        'deprat-admin-navigation',
        'window.depratAdminNavigation=' . wp_json_encode( array(
            'classicUrl' => deprat_admin_menu_mode_url( 'classic' ),
            'logoUrl' => (int) get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' ) : '',
            'dashboardUrl' => admin_url( 'index.php' ),
            'updatesUrl' => admin_url( 'update-core.php' ),
            'productUrl' => admin_url( 'edit.php?post_type=product' ),
            'ordersUrl' => admin_url( 'admin.php?page=wc-orders' ),
            'settingsUrl' => admin_url( 'admin.php?page=deprat-shop-settings' ),
            'clientUrl' => admin_url( 'users.php' ),
            'pendingUrl' => admin_url( 'users.php?role=deprat_pending' ),
            'pendingCount' => (int) ( count_users()['avail_roles']['deprat_pending'] ?? 0 ),
            'marketingUrl' => admin_url( 'admin.php?page=wc-admin&path=/marketing' ),
            'homeSlideUrl' => admin_url( 'edit.php?post_type=deprat_slide' ),
            'blogUrl' => admin_url( 'edit.php' ),
            'mediaUrl' => admin_url( 'upload.php' ),
        ) ) . ';',
        'before'
    );
} );
