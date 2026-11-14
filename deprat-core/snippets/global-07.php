<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT - Menu client dynamique et tableau de bord professionnel
 * Version V3 : liens, icones, coordonnees pro et mot de passe.
 * Compatible WooCommerce + Astra Free.
 */

/* =========================================================
   1. Nom affiché : société en priorité, sinon nom du client
========================================================= */
function deprat_pro_nom($user_id) {
    $societe = trim((string) get_user_meta($user_id, 'billing_company', true));

    if ($societe !== '') {
        return $societe;
    }

    $prenom = trim((string) get_user_meta($user_id, 'first_name', true));
    $nom    = trim((string) get_user_meta($user_id, 'last_name', true));
    $complet = trim($prenom . ' ' . $nom);

    if ($complet !== '') {
        return $complet;
    }

    $user = get_userdata($user_id);
    return $user ? $user->display_name : 'Mon compte';
}

/* =========================================================
   2. Ma demande en cours -> panier WooCommerce
   La page Panier doit contenir [woocommerce_cart].
========================================================= */
function deprat_pro_url_devis() {
    if (function_exists('wc_get_cart_url')) {
        return wc_get_cart_url();
    }

    return home_url('/');
}

/* =========================================================
   3. Mes demandes de devis -> vraie page WordPress
   Slug : mes-demandes-devis
   Contenu de la page : [deprat_mes_demandes]
   Le shortcode est fourni par le snippet de gestion des devis.
========================================================= */
function deprat_pro_url_mes_demandes() {
    $page = get_page_by_path('mes-demandes-devis', OBJECT, 'page');

    if ($page && $page->post_status === 'publish') {
        return get_permalink($page->ID);
    }

    return home_url('/mes-demandes-devis/');
}

/* =========================================================
   4. Destinations des informations du compte
========================================================= */
function deprat_pro_url_coordonnees() {
    return wc_get_account_endpoint_url('coordonnees-pro');
}

function deprat_pro_url_securite() {
    return wc_get_account_endpoint_url('securite-pro');
}

/* =========================================================
   5. Shortcode du menu en haut
========================================================= */
add_shortcode('deprat_menu_pro', function () {

    if (!function_exists('wc_get_page_permalink')) {
        return '';
    }

    $compte = wc_get_page_permalink('myaccount');

    if (!is_user_logged_in()) {
        return '<a class="deprat-pro-connexion" href="' . esc_url($compte) . '">Connexion / Inscription</a>';
    }

    $user_id = get_current_user_id();
    $nom     = deprat_pro_nom($user_id);
    $detail  = deprat_pro_url_securite();
    $logout  = wc_get_account_endpoint_url('customer-logout');

    $html  = '<div class="deprat-pro-header">';
    $html .= '<details class="deprat-pro-details">';
    $html .= '<summary>👤 <span class="deprat-pro-name">' . esc_html($nom) . '</span> <span aria-hidden="true">⌄</span></summary>';
    $html .= '<nav class="deprat-pro-menu" aria-label="Menu de mon compte">';
    $html .= '<a href="' . esc_url($compte) . '"><span class="deprat-pro-menu-icone" aria-hidden="true">🏠</span>Mon espace professionnel</a>';
    $html .= '<a href="' . esc_url(deprat_pro_url_devis()) . '"><span class="deprat-pro-menu-icone" aria-hidden="true">📋</span>Ma demande en cours</a>';
    $html .= '<a href="' . esc_url(deprat_pro_url_mes_demandes()) . '"><span class="deprat-pro-menu-icone" aria-hidden="true">🗂️</span>Mes demandes de devis</a>';
    $html .= '<a href="' . esc_url(deprat_pro_url_coordonnees()) . '"><span class="deprat-pro-menu-icone" aria-hidden="true">🏢</span>Mes coordonnées</a>';
    $html .= '<a href="' . esc_url($detail) . '"><span class="deprat-pro-menu-icone" aria-hidden="true">⚙️</span>Détails du compte</a>';
    $html .= '</nav>';
    $html .= '</details>';
    $html .= '<a class="deprat-pro-sortie" href="' . esc_url($logout) . '">Se déconnecter</a>';
    $html .= '</div>';

    return $html;
});

/* =========================================================
   5. Simplifier les intitulés WooCommerce
========================================================= */
add_filter('woocommerce_account_menu_items', function ($items) {
    // Eviter de proposer des commandes classiques et des liens en doublon.
    unset($items['downloads'], $items['orders'], $items['edit-address'], $items['edit-account']);

    if (isset($items['dashboard'])) {
        $items['dashboard'] = 'Mon espace professionnel';
    }

    // Liens supplémentaires du compte WooCommerce si sa navigation est visible.
    $items['coordonnees-pro'] = 'Mes coordonnées';
    $items['securite-pro']    = 'Détails du compte';
    return $items;
}, 20);

/* =========================================================
   6. Identifier seulement l’accueil du compte
========================================================= */
add_filter('body_class', function ($classes) {

    if (
        function_exists('is_account_page') &&
        is_account_page() &&
        is_user_logged_in() &&
        function_exists('is_wc_endpoint_url') &&
        !is_wc_endpoint_url()
    ) {
        $classes[] = 'deprat-pro-dashboard';
    }

    return $classes;
});

/* =========================================================
   7. Nouveau tableau de bord
========================================================= */
add_action('woocommerce_account_dashboard', function () {

    if (!is_user_logged_in()) {
        return;
    }

    $nom = deprat_pro_nom(get_current_user_id());

    $liens = array(
        array(
            'icone' => '📋',
            'titre' => 'Ma demande en cours',
            'desc'  => 'Retrouver ma sélection en cours',
            'url'   => deprat_pro_url_devis()
        ),
        array(
            'icone' => '🗂️',
            'titre' => 'Mes demandes de devis',
            'desc'  => 'Retrouver mes demandes déjà envoyées',
            'url'   => deprat_pro_url_mes_demandes()
        ),
        array(
            'icone' => '🏢',
            'titre' => 'Mes coordonnées',
            'desc'  => 'Adresses et informations professionnelles',
            'url'   => deprat_pro_url_coordonnees()
        ),
        array(
            'icone' => '⚙️',
            'titre' => 'Détails du compte',
            'desc'  => 'Modifier mon mot de passe',
            'url'   => deprat_pro_url_securite()
        )
    );

    echo '<section class="deprat-pro-accueil">';
    echo '<h2>Bienvenue, ' . esc_html($nom) . '</h2>';
    echo '<p>Retrouvez vos demandes et gérez votre compte DEPRAT.</p>';
    echo '<div class="deprat-pro-cartes">';

    foreach ($liens as $lien) {
        echo '<a class="deprat-pro-carte" href="' . esc_url($lien['url']) . '">';
        echo '<span class="deprat-pro-icone">' . esc_html($lien['icone']) . '</span>';
        echo '<strong>' . esc_html($lien['titre']) . '</strong>';
        echo '<small>' . esc_html($lien['desc']) . '</small>';
        echo '</a>';
    }

    echo '</div>';
    echo '</section>';
}, 10);

/* =========================================================
   8. Historique des demandes
   La page réelle /mes-demandes-devis/ avec le shortcode
   [deprat_mes_demandes] remplace l'ancienne page virtuelle.
   Nous ne créons plus de règle de réécriture /mes-demandes/.
========================================================= */

/* =========================================================
   8 bis. Pages reelles dans Mon compte (WooCommerce)
   - /mon-compte/coordonnees-pro/
   - /mon-compte/securite-pro/
   Les URL suivent automatiquement le slug de la page Mon compte.
========================================================= */
add_action('init', function () {
    add_rewrite_endpoint('coordonnees-pro', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('securite-pro', EP_ROOT | EP_PAGES);
}, 20);

add_filter('woocommerce_get_query_vars', function ($vars) {
    $vars['coordonnees-pro'] = 'coordonnees-pro';
    $vars['securite-pro']    = 'securite-pro';
    return $vars;
});

// Recupere la valeur du profil, uniquement pour l'affichage.
function deprat_pro_meta($user_id, $key) {
    return (string) get_user_meta($user_id, $key, true);
}

/* =========================================================
   8 ter. Enregistrer les formulaires prives, avec nonce
========================================================= */
add_action('template_redirect', function () {
    if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['deprat_pro_action'])) {
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['deprat_pro_action']));
    $is_coords = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('coordonnees-pro');
    $is_pass = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('securite-pro');
    if (($action === 'enregistrer_coordonnees' && !$is_coords) || ($action === 'changer_mot_de_passe' && !$is_pass)) {
        return;
    }

    $user_id = get_current_user_id();
    $nonce = isset($_POST['deprat_pro_nonce']) ? sanitize_text_field(wp_unslash($_POST['deprat_pro_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'deprat_pro_action_' . $user_id)) {
        wc_add_notice('La session du formulaire a expiré. Rechargez la page et réessayez.', 'error');
        return;
    }

    if ($action === 'enregistrer_coordonnees') {
        $fields = array(
            'billing_first_name', 'billing_last_name', 'billing_phone',
            'billing_address_1', 'billing_address_2', 'billing_postcode',
            'billing_city', 'deprat_activity', 'deprat_note'
        );
        $values = array();
        foreach ($fields as $key) {
            $values[$key] = isset($_POST[$key]) ? ('deprat_note' === $key ? sanitize_textarea_field(wp_unslash($_POST[$key])) : sanitize_text_field(wp_unslash($_POST[$key]))) : '';
        }
        $email = isset($_POST['deprat_email']) ? sanitize_email(wp_unslash($_POST['deprat_email'])) : '';

        if (!$values['billing_first_name'] || !$values['billing_last_name'] || !$values['billing_address_1'] ||
            !$values['billing_postcode'] || !$values['billing_city'] || !$values['billing_phone'] || !$values['deprat_activity']) {
            wc_add_notice('Merci de compléter tous les champs obligatoires.', 'error');
            return;
        }
        if (!is_email($email)) {
            wc_add_notice('Votre adresse e-mail n’est pas valide.', 'error');
            return;
        }
        $existing = email_exists($email);
        if ($existing && (int) $existing !== $user_id) {
            wc_add_notice('Cette adresse e-mail est déjà utilisée par un autre compte.', 'error');
            return;
        }
        $activity_values = array('fabricant', 'installateur', 'reparateur', 'revendeur', 'industriel', 'autre');
        if ($values['deprat_activity'] && !in_array($values['deprat_activity'], $activity_values, true)) {
            wc_add_notice('Veuillez choisir une activité dans la liste.', 'error');
            return;
        }

        $update = wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'first_name' => $values['billing_first_name'],
            'last_name' => $values['billing_last_name'],
        ));
        if (is_wp_error($update)) {
            wc_add_notice('Enregistrement impossible : ' . $update->get_error_message(), 'error');
            return;
        }

        foreach ($values as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        update_user_meta($user_id, 'billing_email', $email);
        // La raison sociale et le SIRET valides restent inchanges.
        wc_add_notice('Vos coordonnées ont bien été enregistrées.', 'success');
        wp_safe_redirect(deprat_pro_url_coordonnees());
        exit;
    }

    if ($action === 'changer_mot_de_passe') {
        $current = isset($_POST['deprat_old_password']) ? (string) wp_unslash($_POST['deprat_old_password']) : '';
        $new = isset($_POST['deprat_new_password']) ? (string) wp_unslash($_POST['deprat_new_password']) : '';
        $confirm = isset($_POST['deprat_confirm_password']) ? (string) wp_unslash($_POST['deprat_confirm_password']) : '';
        $user = get_userdata($user_id);

        if (!$user || !wp_check_password($current, $user->user_pass, $user_id)) {
            wc_add_notice('Le mot de passe actuel est incorrect.', 'error');
            return;
        }
        if (strlen($new) < 12) {
            wc_add_notice('Choisissez un nouveau mot de passe d’au moins 12 caractères.', 'error');
            return;
        }
        if ($new !== $confirm) {
            wc_add_notice('La confirmation du nouveau mot de passe ne correspond pas.', 'error');
            return;
        }
        if ($current === $new) {
            wc_add_notice('Choisissez un mot de passe différent du précédent.', 'error');
            return;
        }
        $result = wp_update_user(array('ID' => $user_id, 'user_pass' => $new));
        if (is_wp_error($result)) {
            wc_add_notice('Le mot de passe n’a pas pu être modifié.', 'error');
            return;
        }
        wc_add_notice('Votre mot de passe a été modifié.', 'success');
        wp_safe_redirect(deprat_pro_url_securite());
        exit;
    }
}, 15);

/* =========================================================
   8 quater. Afficher les pages securisees
========================================================= */
add_action('woocommerce_account_coordonnees-pro_endpoint', function () {
    if (!is_user_logged_in()) {
        return;
    }
    $uid = get_current_user_id();
    $user = wp_get_current_user();
    $prenom = deprat_pro_meta($uid, 'billing_first_name');
    $nom = deprat_pro_meta($uid, 'billing_last_name');
    if (!$prenom) { $prenom = $user->first_name; }
    if (!$nom) { $nom = $user->last_name; }

    echo '<div class="deprat-pro-form">';
    echo '<h2>Mes coordonnées professionnelles</h2>';
    echo '<p>Retrouvez et mettez à jour vos coordonnées. La raison sociale et le SIRET sont protégés et doivent être modifiés par DEPRAT.</p>';
    echo '<div class="deprat-pro-info"><div><strong>Raison sociale</strong><span>' . esc_html(deprat_pro_meta($uid, 'billing_company') ?: 'Non renseignée') . '</span></div>';
    echo '<div><strong>SIRET</strong><span>' . esc_html(deprat_pro_meta($uid, 'deprat_siret') ?: 'Non renseigné') . '</span></div>';
    $client_no = deprat_pro_meta($uid, 'deprat_customer_number');
    if ($client_no !== '') {
        echo '<div><strong>N° client DEPRAT</strong><span>' . esc_html($client_no) . '</span></div>';
    }
    echo '<div><strong>Pays</strong><span>France</span></div>';
    echo '</div>';

    echo '<form method="post">';
    woocommerce_form_field('billing_first_name', array('type'=>'text','label'=>'Prénom','required'=>true,'class'=>array('form-row-first')), $prenom);
    woocommerce_form_field('billing_last_name', array('type'=>'text','label'=>'Nom','required'=>true,'class'=>array('form-row-last')), $nom);
    woocommerce_form_field('deprat_email', array('type'=>'email','label'=>'Adresse e-mail','required'=>true,'class'=>array('form-row-wide')), $user->user_email);
    woocommerce_form_field('billing_phone', array('type'=>'tel','label'=>'Téléphone','required'=>true,'class'=>array('form-row-wide')), deprat_pro_meta($uid,'billing_phone'));
    woocommerce_form_field('billing_address_1', array('type'=>'text','label'=>'Adresse','required'=>true,'class'=>array('form-row-wide')), deprat_pro_meta($uid,'billing_address_1'));
    woocommerce_form_field('billing_address_2', array('type'=>'text','label'=>'Complément d’adresse','required'=>false,'class'=>array('form-row-wide')), deprat_pro_meta($uid,'billing_address_2'));
    woocommerce_form_field('billing_postcode', array('type'=>'text','label'=>'Code postal','required'=>true,'class'=>array('form-row-first')), deprat_pro_meta($uid,'billing_postcode'));
    woocommerce_form_field('billing_city', array('type'=>'text','label'=>'Ville','required'=>true,'class'=>array('form-row-last')), deprat_pro_meta($uid,'billing_city'));
    woocommerce_form_field('deprat_activity', array(
        'type'=>'select', 'label'=>'Activité professionnelle', 'required'=>true,
        'options'=>array(''=>'Choisir une activité','fabricant'=>'Fabricant','installateur'=>'Installateur / Poseur',
            'reparateur'=>'Réparateur / SAV','revendeur'=>'Revendeur / Négoce','industriel'=>'Industriel','autre'=>'Autre'),
        'class'=>array('form-row-wide')
    ), deprat_pro_meta($uid,'deprat_activity'));

    woocommerce_form_field('deprat_note', array('type'=>'textarea','label'=>'Informations complémentaires','required'=>false,'class'=>array('form-row-wide')), deprat_pro_meta($uid, 'deprat_note'));
    wp_nonce_field('deprat_pro_action_' . $uid, 'deprat_pro_nonce');
    echo '<input type="hidden" name="deprat_pro_action" value="enregistrer_coordonnees">';
    echo '<button type="submit" class="button alt">Enregistrer mes coordonnées</button>';
    echo '</form></div>';
});

add_action('woocommerce_account_securite-pro_endpoint', function () {
    if (!is_user_logged_in()) {
        return;
    }
    echo '<div class="deprat-pro-form">';
    echo '<h2>Détails du compte — Mot de passe</h2>';
    echo '<p>Modifiez votre mot de passe pour sécuriser l’accès à votre espace professionnel DEPRAT.</p>';
    echo '<form method="post">';
    echo '<p class="form-row form-row-wide"><label for="deprat_old_password">Mot de passe actuel <span class="required">*</span></label><input id="deprat_old_password" class="input-text" type="password" name="deprat_old_password" autocomplete="current-password" required></p>';
    echo '<p class="form-row form-row-wide"><label for="deprat_new_password">Nouveau mot de passe (12 caractères minimum) <span class="required">*</span></label><input id="deprat_new_password" class="input-text" type="password" name="deprat_new_password" autocomplete="new-password" minlength="12" required></p>';
    echo '<p class="form-row form-row-wide"><label for="deprat_confirm_password">Confirmer le nouveau mot de passe <span class="required">*</span></label><input id="deprat_confirm_password" class="input-text" type="password" name="deprat_confirm_password" autocomplete="new-password" minlength="12" required></p>';
    wp_nonce_field('deprat_pro_action_' . get_current_user_id(), 'deprat_pro_nonce');
    echo '<input type="hidden" name="deprat_pro_action" value="changer_mot_de_passe">';
    echo '<button type="submit" class="button alt">Modifier mon mot de passe</button>';
    echo '</form></div>';
});

/* =========================================================
   9. Styles
========================================================= */
add_action('wp_head', function () {
    echo <<<'CSS'
<style id="deprat-pro-styles">
.deprat-pro-header {
    display:flex;
    align-items:center;
    gap:16px;
    font-size:14px;
}
.deprat-pro-details {
    position:relative;
}
.deprat-pro-details summary {
    display:flex;
    align-items:center;
    gap:7px;
    list-style:none;
    cursor:pointer;
    color:#0c3f91;
    font-weight:600;
}
.deprat-pro-details summary::-webkit-details-marker {
    display:none;
}
.deprat-pro-name {
    display:inline-block;
    max-width:210px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.deprat-pro-menu {
    position:absolute;
    right:0;
    top:calc(100% + 12px);
    z-index:9999;
    min-width:225px;
    background:white;
    border:1px solid #e1e6ec;
    border-radius:10px;
    box-shadow:0 8px 22px #12213822;
    padding:7px;
}
.deprat-pro-menu a {
    display:block;
    color:#19283c;
    padding:11px 12px;
    border-radius:6px;
    text-decoration:none;
}
.deprat-pro-menu a:hover,
.deprat-pro-menu a:focus {
    background:#f0f4fa;
}
.deprat-pro-sortie {
    padding-left:15px;
    border-left:1px solid #d8dee8;
    white-space:nowrap;
}
.deprat-pro-connexion {
    font-weight:600;
}

.deprat-pro-dashboard .woocommerce-MyAccount-content > p {
    display:none;
}
.deprat-pro-accueil h2 {
    font-size:26px;
    margin:0 0 7px;
    color:#17273e;
}
.deprat-pro-accueil > p {
    margin:0 0 22px;
    color:#627082;
}
.deprat-pro-cartes {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.deprat-pro-carte {
    display:flex;
    flex-direction:column;
    gap:7px;
    border:1px solid #dfe5ee;
    padding:21px;
    border-radius:12px;
    background:#fff;
    text-decoration:none !important;
    color:#17273e;
    transition:border-color .15s, box-shadow .15s;
}
.deprat-pro-carte:hover,
.deprat-pro-carte:focus {
    border-color:#1d5cad;
    box-shadow:0 4px 16px #11254a14;
}
.deprat-pro-carte strong {
    color:#132844;
}
.deprat-pro-carte small {
    color:#657184;
    line-height:1.4;
}
.deprat-pro-icone {
    font-size:23px;
}


/* Icones identiques aux quatre cartes */
.deprat-pro-menu a { display:flex; align-items:center; gap:11px; }
.deprat-pro-menu-icone { font-size:19px; width:24px; flex:0 0 24px; line-height:1.1; text-align:center; }

/* Pages de coordonnees et de securite */
.deprat-pro-form { max-width:760px; }
.deprat-pro-form h2 { font-size:25px; color:#17273e; margin:0 0 12px; }
.deprat-pro-form > p { color:#627082; margin-bottom:20px; }
.deprat-pro-info { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
.deprat-pro-info > div { background:#f4f7fb; border:1px solid #dfe5ee; border-radius:9px; padding:14px; }
.deprat-pro-info strong { color:#17273e; display:block; font-size:13px; margin-bottom:6px; }
.deprat-pro-info span { color:#46566b; }
.deprat-pro-form form:after { content:''; display:table; clear:both; }
.deprat-pro-form .button { background:#0967c8; color:white; border-radius:6px; margin-top:12px; }
@media(max-width:640px) { .deprat-pro-info { grid-template-columns:1fr; } }

@media (max-width:640px) {
    .deprat-pro-cartes {
        grid-template-columns:1fr;
    }
    .deprat-pro-header {
        gap:9px;
        font-size:12px;
    }
    .deprat-pro-name {
        max-width:115px;
    }
}
</style>
CSS;
});
