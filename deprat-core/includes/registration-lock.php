<?php
/**
 * DEPRAT – Protection des inscriptions pendant la préparation du site.
 *
 * Par défaut, les visiteurs ne peuvent créer aucun nouveau compte via
 * WordPress ou WooCommerce. Les comptes existants, la connexion et la
 * création manuelle par un administrateur ne sont pas affectés.
 *
 * Réactivation volontaire : Réglages > Général > Inscriptions professionnelles.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function deprat_registration_is_open() {
    return 'yes' === get_option( 'deprat_registration_enabled', 'no' );
}

function deprat_registration_paused_message() {
    return 'Les inscriptions professionnelles DEPRAT sont temporairement fermées pendant la préparation du site.';
}

// Couper les formulaires natifs sans désactiver l’accès des comptes existants.
add_filter( 'pre_option_users_can_register', static function( $pre ) {
    return deprat_registration_is_open() ? $pre : 0;
}, 20 );
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', static function( $pre ) {
    return deprat_registration_is_open() ? $pre : 'no';
}, 20 );
add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', static function( $pre ) {
    return deprat_registration_is_open() ? $pre : 'no';
}, 20 );

// Une soumission directe au formulaire doit aussi être refusée sur le serveur.
// Ceci protège les routes classiques, y compris wc_create_new_customer().
function deprat_registration_reject_public_signup( $errors ) {
    if ( deprat_registration_is_open() || ( function_exists( 'current_user_can' ) && current_user_can( 'create_users' ) ) ) {
        return $errors;
    }
    if ( is_wp_error( $errors ) ) {
        $errors->add( 'deprat_registration_paused', deprat_registration_paused_message() );
    }
    return $errors;
}
add_filter( 'registration_errors', 'deprat_registration_reject_public_signup', 5, 3 );
add_filter( 'woocommerce_process_registration_errors', 'deprat_registration_reject_public_signup', 5, 4 );
add_filter( 'woocommerce_registration_errors', 'deprat_registration_reject_public_signup', 5, 3 );

// WooCommerce propose également un point de validation avant wc_create_new_customer().
add_action( 'woocommerce_register_post', static function( $username, $email, $errors ) {
    deprat_registration_reject_public_signup( $errors );
}, 5, 3 );

// Réglage explicite : les inscriptions ne rouvrent jamais automatiquement.
add_action( 'admin_init', static function() {
    register_setting( 'general', 'deprat_registration_enabled', array(
        'type' => 'string',
        'sanitize_callback' => static function( $value ) { return 'yes' === $value ? 'yes' : 'no'; },
        'default' => 'no',
    ) );
    add_settings_field(
        'deprat_registration_enabled',
        'Inscriptions professionnelles DEPRAT',
        'deprat_registration_settings_field',
        'general'
    );
} );

function deprat_registration_settings_field() {
    $open = deprat_registration_is_open();
    ?>
    <input type="hidden" name="deprat_registration_enabled" value="no">
    <label>
        <input type="checkbox" name="deprat_registration_enabled" value="yes" <?php checked( $open ); ?>>
        Autoriser à nouveau les demandes d’ouverture de compte professionnel
    </label>
    <p class="description">
        <?php if ( $open ) : ?>
            <strong>Inscriptions DEPRAT autorisées.</strong>
            Vérifiez également les réglages WooCommerce et WordPress avant ouverture au public.
        <?php else : ?>
            <strong>Inscriptions suspendues.</strong> Les visiteurs ne peuvent pas créer de compte.
            Les comptes déjà créés restent inchangés ; un administrateur peut toujours ajouter un utilisateur.
        <?php endif; ?>
    </p>
    <?php
}

// Informer les visiteurs qui ouvrent directement la page Mon compte.
// Ne pas afficher cet avertissement aux personnes déjà connectées.
add_action( 'woocommerce_before_customer_login_form', static function() {
    if ( deprat_registration_is_open() || is_user_logged_in() ) { return; }
    echo '<p class="woocommerce-info deprat-registration-paused" role="status">'
        . esc_html( deprat_registration_paused_message() ) . '</p>';
}, 4 );
