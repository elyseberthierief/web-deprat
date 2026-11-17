<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT — Gestion des demandes et factures V7.5.
 * Conserve V6 : documents chiffrés et acceptation. Ajoute facture séparée,
 * statuts ADV / client, consultation ADV, dates et recherche dans l’historique.
 * V7.1 : aperçu 4 références, détails en fenêtre modale, bouton refaire en icône.
 * V7.2 : demander un ajustement, nouvelle version de devis archivée, recherche instantanée.
 * V7.3 : liens de documents discrets, actions client mieux structurées.
 * V7.4 : envoi de devis modifié distinct, nouvel accord obligatoire et notifications.
 * V7.5 : enregistrer un PDF de devis déclenche son envoi et la notification au client.
 * Tableau de bord ADV et cloche de notifications : snippets distincts.
 * Le design du tableau de bord ADV est dans un AUTRE snippet indépendant.
 * Correction : consultation des devis et factures sur une URL publique (sans /wp-admin/),
 * avec droits client / ADV, session et jeton de sécurité vérifiés.
 * Code Snippets / PHP / Exécuter partout : coller SANS balise <?php.
 * Remplacer uniquement l'ancien snippet « DEPRAT – Gestion des demandes de devis ».
 * Sauvegarder le code précédent avant modification et faire les tests sur le site de test.
 * Aucune pièce jointe PDF dans les e-mails : accès sécurisé dans l'espace pro.
 */

// 1. Sept états de traitement. Le libellé interne indique l'action de l'ADV.
// Le libellé client est volontairement différent (voir deprat_statut_client_v7()).
function deprat_statuts_devis() {
    return array(
        'wc-dpr-recu'    => 'Nouvelle demande',
        'wc-dpr-encours' => 'En cours de traitement',
        'wc-dpr-envoye'  => 'Envoyer le devis',
        'wc-dpr-modifie' => 'Envoyer le devis modifié',
        'wc-dpr-accepte' => 'Devis accepté',
        'wc-dpr-facture' => 'Envoyer la facture',
        'wc-dpr-termine' => 'Commande terminée',
    );
}
function deprat_statut_client_v7( $etat ) {
    $noms = array(
        'dpr-recu'    => 'Demande envoyée',
        'dpr-encours' => 'Devis en cours de traitement',
        'dpr-envoye'  => 'Devis à valider',
        'dpr-modifie' => 'Devis modifié à valider',
        'dpr-accepte' => 'Devis accepté',
        'dpr-facture' => 'Facture disponible',
        'dpr-termine' => 'Commande terminée',
    );
    return $noms[ $etat ] ?? wc_get_order_status_name( $etat );
}
function deprat_mention_paiement_v7() {
    // Mention informative uniquement : les conditions de la facture font foi.
    // Modifiable ultérieurement sans toucher au stockage des documents.
    return 'Paiement possible par virement bancaire sous 30 jours, selon les modalités indiquées sur votre facture.';
}
function deprat_date_utc_v7( $date_utc ) {
    if ( ! $date_utc ) { return ''; }
    $horodatage = strtotime( $date_utc . ' UTC' );
    return $horodatage ? wp_date( 'd/m/Y à H:i', $horodatage, wp_timezone() ) : '';
}
add_action( 'init', function () {
    foreach ( deprat_statuts_devis() as $slug => $label ) {
        register_post_status( $slug, array(
            'label' => $label,
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop( $label . ' (%s)', $label . ' (%s)' ),
        ) );
    }
    add_rewrite_endpoint( 'mes-demandes', EP_ROOT | EP_PAGES );
}, 10 );
// Le menu d'une fiche de demande DEPRAT ne présente que nos sept étapes.
// Ailleurs, les statuts WooCommerce natifs restent disponibles pour ne pas
// perturber une éventuelle autre utilisation de WooCommerce.
add_filter( 'wc_order_statuses', function ( $statuses ) {
    static $controle = false;
    $statuts = deprat_statuts_devis();
    $fusion = array_merge( $statuses, $statuts );
    if ( ! is_admin() || $controle || ! function_exists( 'wc_get_order' ) ) { return $fusion; }
    $id = 0;
    // HPOS : /wp-admin/admin.php?page=wc-orders&action=edit&id=...
    if ( isset( $_GET['page'], $_GET['action'], $_GET['id'] ) &&
         'wc-orders' === sanitize_key( wp_unslash( $_GET['page'] ) ) &&
         'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
        $id = absint( $_GET['id'] );
    }
    // Stockage classique : /wp-admin/post.php?post=...&action=edit
    if ( ! $id && isset( $_GET['post'], $_GET['action'] ) &&
         'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
        $id = absint( $_GET['post'] );
    }
    if ( ! $id ) { return $fusion; }
    $controle = true;
    try { $commande = wc_get_order( $id ); } finally { $controle = false; }
    if ( ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) {
        return $fusion;
    }
    // Sur une demande DEPRAT, retirer les statuts natifs de la liste déroulante.
    return $statuts;
}, 20 );

// 2. URL de l'historique. La page autonome évite de dépendre du thème ou d'un tableau de bord personnalisé.
function deprat_lien_historique() {
    $page = get_page_by_path( 'mes-demandes-devis', OBJECT, 'page' );
    if ( $page && 'publish' === $page->post_status ) {
        return get_permalink( $page );
    }
    return function_exists( 'wc_get_account_endpoint_url' )
        ? wc_get_account_endpoint_url( 'mes-demandes' )
        : home_url( '/' );
}

// Adresse de notification du service ADV. Laisser vide pour utiliser l'adresse administrateur WordPress.
// Plus tard, indiquer ici l'adresse de la boîte partagée ADV.
function deprat_email_adv() {
    $email_adv = ''; // Vide : adresse administrateur WP. À terme : boîte partagée ADV.
    return is_email( $email_adv ) ? $email_adv : get_option( 'admin_email' );
}

// 3. Formulaire commentaire + envoi sous le panier classique [woocommerce_cart].
add_action( 'woocommerce_after_cart', function () {
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        return;
    }
    echo '<section class="deprat-envoi" style="margin:28px 0;padding:24px;border:1px solid #d8e0ea;border-radius:8px">';
    echo '<h3>Envoyer ma demande de devis</h3>';
    if ( ! is_user_logged_in() ) {
        echo '<p>Connectez-vous à votre compte professionnel pour envoyer votre demande.</p>';
        echo '<p><a class="button" href="' . esc_url( wp_login_url( wc_get_cart_url() ) ) . '">Me connecter</a></p>';
    } else {
        echo '<form method="post" action="' . esc_url( wc_get_cart_url() ) . '">';
        echo '<p><label for="deprat_commentaire">Commentaire (facultatif)</label></p>';
        echo '<textarea id="deprat_commentaire" name="deprat_commentaire" rows="4" maxlength="2000" placeholder="Livraison souhaitée, précisions, informations utiles..." style="width:100%;max-width:720px"></textarea>';
        wp_nonce_field( 'deprat_envoyer_demande', 'deprat_nonce' );
        echo '<p><button type="submit" name="deprat_envoyer" value="1" class="button alt" style="margin-top:14px">Envoyer ma demande de devis</button></p>';
        echo '</form>';
    }
    echo '</section>';
}, 20 );

// 4. E-mails HTML DEPRAT : notification vers l'administrateur WP pour le moment.

// 4 bis. Modèle d'e-mail DEPRAT (client et administration, même style que le compte pro).
// Utilise prioritairement le modèle HTML du snippet « Inscription professionnelle ».
// Le modèle autonome de secours permet aussi l'envoi si celui-ci est désactivé.
function deprat_devis_email_html_v7( $titre, $contenu ) {
    if ( function_exists( 'deprat_pro_email_html' ) ) {
        return str_replace( 'https://deprat.eu/wp-content/uploads/2026/11/cropped-Logo-Deprat.png', home_url( '/wp-content/uploads/2026/11/cropped-Logo-Deprat.png' ), deprat_pro_email_html( $titre, $contenu ) );
    }

    $logo = home_url( '/wp-content/uploads/2026/11/cropped-Logo-Deprat.png' );
    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>';
    $html .= '<body style="margin:0;padding:0;background:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#1c2b42;">';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fa;"><tr><td align="center" style="padding:28px 12px;">';
    $html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e3e8ef;">';
    $html .= '<tr><td style="padding:26px 34px;border-top:5px solid #0a3c8a;border-bottom:2px solid #ad963f;">';
    $html .= '<img src="' . esc_url( $logo ) . '" width="145" alt="DEPRAT" style="display:block;width:145px;max-width:100%;height:auto;border:0;"></td></tr>';
    $html .= '<tr><td style="padding:34px;color:#33435b;font-size:15px;line-height:1.65;">';
    $html .= '<h1 style="margin:0 0 22px;font-size:25px;line-height:1.25;color:#0b2e64;font-weight:700;">' . esc_html( $titre ) . '</h1>';
    $html .= $contenu;
    $html .= '<p style="margin:26px 0 0;">Cordialement,<br><strong style="color:#0a3c8a;">L’équipe DEPRAT</strong></p>';
    $html .= '</td></tr><tr><td style="padding:22px 34px;background:#f7f9fc;border-top:1px solid #e4e9f2;color:#66758a;font-size:12px;line-height:1.8;">';
    $html .= '<strong style="color:#0b2e64;">DEPRAT</strong><br>4 rue de la Papinerie – 59115 Leers, France<br>';
    $html .= 'Tél. : <a href="tel:0320896800" style="color:#0a3c8a;">03 20 89 68 00</a> &nbsp;|&nbsp; ';
    $html .= '<a href="mailto:contact@deprat.com" style="color:#0a3c8a;">contact@deprat.com</a><br>';
    $html .= '<a href="https://www.deprat.fr/" style="color:#0a3c8a;">www.deprat.fr</a>';
    $html .= '</td></tr></table></td></tr></table></body></html>';
    return $html;
}

function deprat_devis_email_bouton_v7( $texte, $url ) {
    return '<p style="margin:24px 0 18px;">'
        . '<a href="' . esc_url( $url ) . '" style="display:inline-block;background:#0a3c8a;border-radius:5px;padding:13px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">'
        . esc_html( $texte ) . '</a></p>';
}

function deprat_envoyer_email_client_v7( $email, $sujet, $titre, $contenu_html, $contexte ) {
    if ( ! is_email( $email ) ) {
        wc_get_logger()->warning( 'DEPRAT : adresse e-mail client invalide (' . $contexte . ').', array( 'source' => 'deprat-devis' ) );
        return false;
    }
    $html = deprat_devis_email_html_v7( $titre, $contenu_html );
    $ok = wp_mail( $email, $sujet, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
    if ( ! $ok ) {
        wc_get_logger()->error( 'DEPRAT : échec e-mail HTML client (' . $contexte . ').', array( 'source' => 'deprat-devis' ) );
    }
    return $ok;
}

// Envois internes HTML : mêmes couleurs/logo que les notifications clients.
function deprat_envoyer_email_admin_v7( $email, $sujet, $titre, $contenu_html, $contexte ) {
    if ( ! is_email( $email ) ) {
        wc_get_logger()->warning( 'DEPRAT : adresse du service ADV/administration invalide (' . $contexte . ').', array( 'source' => 'deprat-devis' ) );
        return false;
    }
    $ok = wp_mail(
        $email,
        $sujet,
        deprat_devis_email_html_v7( $titre, $contenu_html ),
        array( 'Content-Type: text/html; charset=UTF-8' )
    );
    if ( ! $ok ) {
        wc_get_logger()->error( 'DEPRAT : échec e-mail HTML administrateur (' . $contexte . ').', array( 'source' => 'deprat-devis' ) );
    }
    return $ok;
}

// Tableau réutilisable pour l'accusé de réception CLIENT et la notification SERVICE ADV.
// Le SKU est enregistré dans la ligne lors de la demande pour ne pas dépendre
// d'une future modification de la fiche produit.
function deprat_tableau_produits_email_v7( $commande ) {
    $out  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;font-size:13px;line-height:1.5;">';
    $out .= '<thead><tr style="background:#eaf0f8;color:#0b2e64;">';
    $out .= '<th align="left" style="padding:10px 8px;border:1px solid #dce4ef;">Référence</th>';
    $out .= '<th align="left" style="padding:10px 8px;border:1px solid #dce4ef;">Désignation</th>';
    $out .= '<th align="center" style="padding:10px 8px;border:1px solid #dce4ef;white-space:nowrap;">Qté</th>';
    $out .= '</tr></thead><tbody>';
    foreach ( $commande->get_items( 'line_item' ) as $article ) {
        $reference = (string) $article->get_meta( '_deprat_reference', true );
        if ( '' === $reference ) {
            $produit = $article->get_product();
            $reference = $produit && $produit->get_sku() ? $produit->get_sku() : 'Sans référence';
        }
        $out .= '<tr>';
        $out .= '<td style="padding:10px 8px;border:1px solid #dce4ef;vertical-align:top;">' . esc_html( $reference ) . '</td>';
        $out .= '<td style="padding:10px 8px;border:1px solid #dce4ef;vertical-align:top;">' . esc_html( $article->get_name() ) . '</td>';
        $out .= '<td align="center" style="padding:10px 8px;border:1px solid #dce4ef;vertical-align:top;">' . esc_html( $article->get_quantity() ) . '</td>';
        $out .= '</tr>';
    }
    $out .= '</tbody></table>';
    return $out;
}

function deprat_lignes_demande( $commande ) {
    $lignes = array();
    foreach ( $commande->get_items( 'line_item' ) as $article ) {
        $reference = $article->get_meta( '_deprat_reference', true );
        if ( ! $reference ) {
            $produit = $article->get_product();
            $reference = $produit && $produit->get_sku() ? $produit->get_sku() : 'Sans référence';
        }
        $lignes[] = $reference . ' — ' . $article->get_name() . ' : ' . $article->get_quantity();
    }
    return $lignes;
}

// 5. Enregistrer une demande WooCommerce et notifier l'administrateur ET le client.
add_action( 'template_redirect', function () {
    if ( ! is_cart() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['deprat_envoyer'] ) ) {
        return;
    }
    $nonce = isset( $_POST['deprat_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'deprat_envoyer_demande' ) || ! is_user_logged_in() ) {
        wc_add_notice( 'Impossible de valider la demande. Reconnectez-vous puis réessayez.', 'error' );
        return;
    }
    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        wc_add_notice( 'Votre demande est vide.', 'error' );
        return;
    }
    $commentaire = isset( $_POST['deprat_commentaire'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['deprat_commentaire'] ) ) : '';
    $commentaire = wp_html_excerpt( $commentaire, 2000, '' );
    $client_id   = get_current_user_id();
    $client      = wp_get_current_user();

    try {
        $commande = wc_create_order( array( 'customer_id' => $client_id, 'created_via' => 'deprat-devis' ) );
        if ( is_wp_error( $commande ) || ! $commande ) {
            throw new Exception( 'Échec de création de la demande.' );
        }
        $nb_lignes = 0;
        foreach ( WC()->cart->get_cart() as $element ) {
            $produit  = isset( $element['data'] ) ? $element['data'] : false;
            $quantite = isset( $element['quantity'] ) ? wc_stock_amount( $element['quantity'] ) : 0;
            if ( ! $produit || $quantite <= 0 ) {
                continue;
            }
            // Les lignes restent à 0 dans l'administration : ce n'est PAS un devis chiffré.
            $item_id = $commande->add_product( $produit, $quantite, array( 'subtotal' => 0, 'total' => 0 ) );
            if ( ! $item_id ) {
                continue;
            }
            $article = $commande->get_item( $item_id );
            if ( $article ) {
                $article->add_meta_data( '_deprat_reference', $produit->get_sku(), true );
                $article->save();
            }
            $nb_lignes++;
        }
        if ( 0 === $nb_lignes ) {
            $commande->delete( true );
            wc_add_notice( 'Aucun produit valide à envoyer.', 'error' );
            return;
        }
        $commande->set_billing_first_name( $client->first_name );
        $commande->set_billing_last_name( $client->last_name );
        $commande->set_billing_email( $client->user_email );
        $commande->set_billing_company( get_user_meta( $client_id, 'billing_company', true ) );
        $commande->update_meta_data( '_deprat_demande', 'oui' );
        $commande->update_meta_data( '_deprat_commentaire', $commentaire );
        $commande->calculate_totals( false );
        $commande->set_status( 'dpr-recu' );
        $commande->save();

        $numero   = $commande->get_order_number();
        $societe  = $commande->get_billing_company();

        // Notification interne au service ADV : HTML, tableau détaillé et commentaire.
        $destinataire = deprat_email_adv();
        $siret = (string) get_user_meta( $client_id, 'deprat_siret', true );
        $telephone = (string) get_user_meta( $client_id, 'billing_phone', true );
        $nom_contact = trim( (string) $commande->get_billing_first_name() . ' ' . (string) $commande->get_billing_last_name() );
        if ( '' === $nom_contact ) {
            $nom_contact = $client->display_name;
        }
        $message_admin  = '<p style="margin:0 0 16px;">Bonjour,</p>';
        $message_admin .= '<p>Une <strong>nouvelle demande de prix n° ' . esc_html( $numero ) . '</strong> a été envoyée depuis le site DEPRAT.</p>';
        $message_admin .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f9fc;border-left:3px solid #ad963f;margin:18px 0 24px;">';
        $message_admin .= '<tr><td style="padding:16px 18px;line-height:1.75;">';
        $message_admin .= '<strong style="color:#0b2e64;">Client professionnel</strong><br>';
        $message_admin .= '<strong>Société :</strong> ' . esc_html( $societe ?: 'Non renseignée' ) . '<br>';
        if ( $siret !== '' ) {
            $message_admin .= '<strong>SIRET :</strong> ' . esc_html( $siret ) . '<br>';
        }
        $message_admin .= '<strong>Contact :</strong> ' . esc_html( $nom_contact ) . '<br>';
        $message_admin .= '<strong>E-mail :</strong> <a style="color:#0a3c8a;" href="mailto:' . esc_attr( $client->user_email ) . '">' . esc_html( $client->user_email ) . '</a>';
        if ( $telephone !== '' ) {
            $message_admin .= '<br><strong>Téléphone :</strong> ' . esc_html( $telephone );
        }
        $message_admin .= '</td></tr></table>';
        $message_admin .= '<p style="margin:22px 0 10px;"><strong style="color:#0b2e64;">Produits demandés</strong></p>';
        $message_admin .= deprat_tableau_produits_email_v7( $commande );
        $message_admin .= '<p style="margin:22px 0 8px;"><strong style="color:#0b2e64;">Commentaire du client</strong></p>';
        $message_admin .= '<div style="padding:14px 16px;background:#f7f9fc;border-left:3px solid #ad963f;">'
            . ( $commentaire !== '' ? nl2br( esc_html( $commentaire ) ) : '<em>Aucun commentaire</em>' ) . '</div>';
        $message_admin .= deprat_devis_email_bouton_v7( 'Ouvrir la demande dans WooCommerce', $commande->get_edit_order_url() );
        deprat_envoyer_email_admin_v7(
            $destinataire,
            'DEPRAT - Nouvelle demande #' . $numero,
            'Nouvelle demande de prix #' . $numero,
            $message_admin,
            'administration demande #' . $numero
        );

        // Accusé de réception client en HTML, sans prix et sans pièce jointe.
        $prenom = trim( (string) $commande->get_billing_first_name() );
        $bonjour = $prenom ? 'Bonjour ' . esc_html( $prenom ) . ',' : 'Bonjour,';
        $corps_client  = '<p style="margin:0 0 16px;">' . $bonjour . '</p>';
        $corps_client .= '<p>Nous vous confirmons la bonne réception de votre <strong>demande de devis n° ' . esc_html( $numero ) . '</strong>.</p>';
        $corps_client .= '<p>Notre équipe va étudier votre sélection et reviendra vers vous dès que votre devis sera disponible.</p>';
        $corps_client .= '<p style="margin:24px 0 10px;"><strong style="color:#0b2e64;">Récapitulatif de votre demande</strong></p>';
        $corps_client .= deprat_tableau_produits_email_v7( $commande );
        if ( $commentaire !== '' ) {
            $corps_client .= '<p style="margin:22px 0 8px;"><strong style="color:#0b2e64;">Votre commentaire</strong></p>';
            $corps_client .= '<div style="padding:14px 16px;background:#f7f9fc;border-left:3px solid #ad963f;">' . nl2br( esc_html( $commentaire ) ) . '</div>';
        }
        $corps_client .= '<p>Vous retrouverez votre demande et son évolution dans votre espace professionnel.</p>';
        $corps_client .= deprat_devis_email_bouton_v7( 'Consulter mes demandes', deprat_lien_historique() );
        deprat_envoyer_email_client_v7(
            $client->user_email,
            'DEPRAT - Confirmation de votre demande #' . $numero,
            'Votre demande de devis est bien reçue',
            $corps_client,
            'client demande #' . $numero
        );

        WC()->cart->empty_cart();
        wp_safe_redirect( add_query_arg( 'demande_envoyee', $commande->get_id(), deprat_lien_historique() ) );
        exit;
    } catch ( Throwable $erreur ) {
        wc_get_logger()->error( 'DEPRAT : ' . $erreur->getMessage(), array( 'source' => 'deprat-devis' ) );
        wc_add_notice( 'La demande n’a pas pu être envoyée. Contactez-nous si le problème persiste.', 'error' );
    }
}, 10 );

// 6. PDF confidentiel du devis : téléversement dans la fiche commande du service ADV.
// Le PDF est chiffre sur le serveur (AES-256-GCM) et n'apparait PAS dans la mediathèque publique.
function deprat_pdf_dossier() {
    $repertoires = wp_upload_dir();
    if ( ! empty( $repertoires['error'] ) ) {
        return false;
    }
    $dossier = trailingslashit( $repertoires['basedir'] ) . 'deprat-devis-prives';
    if ( ! is_dir( $dossier ) && ! wp_mkdir_p( $dossier ) ) {
        return false;
    }
    // Protections complementaires pour Apache / IIS ; le chiffrement protege aussi sur Nginx.
    if ( ! file_exists( $dossier . '/.htaccess' ) ) {
        @file_put_contents( $dossier . '/.htaccess', "Require all denied\nDeny from all\n" );
    }
    if ( ! file_exists( $dossier . '/index.php' ) ) {
        @file_put_contents( $dossier . '/index.php', "<?php // Acces interdit.\n" );
    }
    return $dossier;
}
function deprat_pdf_cle_privee() {
    if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
        return false;
    }
    $valeur = get_option( 'deprat_devis_pdf_cle', '' );
    if ( ! $valeur ) {
        try {
            $nouvelle_cle = base64_encode( random_bytes( 32 ) );
        } catch ( Throwable $e ) {
            return false;
        }
        if ( add_option( 'deprat_devis_pdf_cle', $nouvelle_cle, '', false ) ) {
            $valeur = $nouvelle_cle;
        } else {
            $valeur = get_option( 'deprat_devis_pdf_cle', '' );
        }
    }
    $cle = base64_decode( $valeur, true );
    return ( false !== $cle && 32 === strlen( $cle ) ) ? $cle : false;
}

// V7.5 : enregistrer le devis = le transmettre au client immédiatement (et envoyer une notification).
// Le PDF de facture reste en dépôt séparé et n'est jamais envoyé automatiquement.
// Interface ADV : deux pièces jointes DISTINCTES + preuve de validation.
// Les devis V6 continuent d'utiliser _deprat_pdf_fichier / _deprat_pdf_nom.
add_action( 'woocommerce_admin_order_data_after_order_details', function ( $commande ) {
    if ( ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) { return; }
    $id = $commande->get_id();
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'edit_shop_order', $id ) ) { return; }

    $date_accord = (string) $commande->get_meta( '_deprat_devis_accepte_le', true );
    $date_facture = (string) $commande->get_meta( '_deprat_facture_envoyee_le', true );
    echo '<div class="deprat-adv-v7" style="clear:both;margin:20px 0;padding:18px;border:1px solid #ccd6e4;border-radius:8px;background:#f8fafc">';
    echo '<h3 style="margin:0 0 8px;color:#153d79">DEPRAT — Documents et suivi ADV</h3>';
    echo '<p style="margin-bottom:18px">Le devis accepté est conservé. La facture se dépose séparément : elle ne remplace jamais le devis.</p>';
    if ( 'oui' === $commande->get_meta( '_deprat_revision_en_attente', true ) ) {
        echo '<div class="deprat-revision-alert" style="border-left:5px solid #e3a008;background:#fff8e5;padding:15px;margin-bottom:15px">';
        echo '<strong>Modification demandée par le client — nouveau devis nécessaire</strong><p>';
        echo nl2br( esc_html( (string) $commande->get_meta( '_deprat_dernier_ajustement_texte', true ) ) );
        echo '</p><p>Déposez la version corrigée, puis sélectionnez « Envoyer le devis modifié ». L’ancienne version restera archivée.</p></div>';
    }

    foreach ( array( 'devis', 'facture' ) as $type ) {
        $est_devis = 'devis' === $type;
        $meta_nom = $est_devis ? '_deprat_pdf_nom' : '_deprat_facture_pdf_nom';
        $meta_fichier = $est_devis ? '_deprat_pdf_fichier' : '_deprat_facture_pdf_fichier';
        $nom = (string) $commande->get_meta( $meta_nom, true );
        $fichier = (string) $commande->get_meta( $meta_fichier, true );
        $verrouille = $est_devis ? ( (bool) $date_accord || in_array( $commande->get_status(), array( 'dpr-envoye', 'dpr-modifie' ), true ) ) : (bool) $date_facture;
        $bloque = ! $est_devis && ! $date_accord;
        $titre = $est_devis ? '1. Devis PDF' : '2. Facture PDF';
        $zone_id = 'deprat-document-' . $type . '-' . $id;
        $nonce = wp_create_nonce( 'deprat_upload_pdf_' . $id );
        echo '<section id="' . esc_attr( $zone_id ) . '" style="margin:14px 0;padding:16px;background:#fff;border:1px solid #e2e8f0;border-radius:7px">';
        echo '<h4 style="margin:0 0 10px">' . esc_html( $titre ) . '</h4>';
        echo '<p><strong>Fichier :</strong> <span class="deprat-pdf-nom">' . ( $nom ? esc_html( $nom ) : 'Aucun document' ) . '</span></p>';
        echo '<p class="deprat-pdf-lien">';
        if ( $fichier ) {
            echo '<a target="_blank" rel="noopener noreferrer" class="button" href="' . esc_url( deprat_lien_document_v7( $commande, $type ) ) . '">Consulter ' . ( $est_devis ? 'le devis' : 'la facture' ) . '</a>';
        }
        echo '</p>';
        if ( $verrouille ) {
            echo '<p style="color:#237447"><strong>Document verrouillé.</strong> ' . ( $est_devis ? ( $date_accord ? 'Devis accepté par le client.' : 'Devis déjà envoyé : repassez en « En cours de traitement » pour le réviser et le renvoyer.' ) : 'Facture déjà mise à disposition.' ) . '</p>';
        } elseif ( $bloque ) {
            echo '<p>La facture peut être ajoutée après acceptation du devis par le client.</p>';
        } else {
            echo '<input type="file" accept="application/pdf,.pdf" class="deprat-pdf-fichier" style="max-width:100%"> ';
            echo '<button class="button deprat-pdf-bouton" type="button">' . ( $est_devis ? ( 'oui' === $commande->get_meta( '_deprat_revision_en_attente', true ) ? 'Envoyer le devis modifié' : 'Envoyer le devis' ) : 'Enregistrer la facture PDF' ) . '</button>';
            if ( ! $est_devis ) {
                echo '<p style="color:#64748b;font-size:12px">Après téléversement, choisir « Envoyer la facture » puis mettre à jour la commande. La facture n’est pas envoyée par ce bouton.</p>';
            }
            echo '<p class="deprat-pdf-resultat" role="status" aria-live="polite"></p>';
        }
        echo '</section>';
        if ( ! $verrouille && ! $bloque ) {
            $script = <<<'JS'
(function(){
  const bloc = document.getElementById(__BLOCK_ID__);
  if (!bloc) return;
  const bouton = bloc.querySelector('.deprat-pdf-bouton');
  const fichier = bloc.querySelector('.deprat-pdf-fichier');
  const resultat = bloc.querySelector('.deprat-pdf-resultat');
  bouton.addEventListener('click', async function(){
    if (!fichier.files.length) { resultat.textContent = 'Sélectionnez un PDF.'; return; }
    if (__TYPE__ === 'devis' && !window.confirm('Enregistrer ce devis et le transmettre maintenant au client ? Une notification e-mail sera déclenchée.')) return;
    bouton.disabled = true;
    bloc.dispatchEvent(new CustomEvent('deprat-upload-start'));
    resultat.textContent = 'Enregistrement du document…';
    const donnees = new FormData();
    donnees.append('action','deprat_pdf_upload');
    donnees.append('order_id',__ORDER_ID__);
    donnees.append('security',__NONCE__);
    donnees.append('type',__TYPE__);
    donnees.append('pdf',fichier.files[0]);
    try {
      const reponse = await fetch(__AJAX_URL__,{method:'POST',credentials:'same-origin',body:donnees});
      const retour = await reponse.json();
      if (!reponse.ok || !retour.success) throw new Error(retour.data?.message || 'Échec du dépôt.');
      bloc.querySelector('.deprat-pdf-nom').textContent = retour.data.name;
      bloc.querySelector('.deprat-pdf-lien').innerHTML = '';
      const a = document.createElement('a');
      a.className = 'button'; a.target='_blank'; a.rel='noopener noreferrer';
      a.href = retour.data.view_url;
      a.textContent = __TYPE__ === 'devis' ? 'Consulter le devis' : 'Consulter la facture';
      bloc.querySelector('.deprat-pdf-lien').appendChild(a);
      resultat.textContent = retour.data.auto_sent
        ? '✓ Devis publié dans l’espace client. Notification soumise au système d’envoi. Actualisation…'
        : '✓ Document enregistré. Pour la facture, choisissez « Envoyer la facture » et enregistrez.';
      fichier.value = '';
      if (retour.data.auto_sent) { window.setTimeout(function(){ window.location.reload(); }, 800); }
    } catch (e) { resultat.textContent='Erreur : '+e.message; }
    finally {
      bouton.disabled = false;
      bloc.dispatchEvent(new CustomEvent('deprat-upload-end'));
    }
  });
})();
JS;
            echo '<script>' . str_replace(
                array('__BLOCK_ID__','__ORDER_ID__','__NONCE__','__TYPE__','__AJAX_URL__'),
                array(wp_json_encode($zone_id),(string)$id,wp_json_encode($nonce),wp_json_encode($type),wp_json_encode(admin_url('admin-ajax.php'))),
                $script
            ) . '</script>';
        }
    }
    // Historique des PDF remplacés à la demande du client : lecture réservée au service ADV.
    $anciens_devis = $commande->get_meta( '_deprat_anciens_devis', true );
    if ( is_array( $anciens_devis ) && $anciens_devis ) {
        echo '<section class="deprat-versions-devis" style="margin:14px 0;padding:16px;background:#fff;border:1px solid #e2e8f0;border-radius:7px">';
        echo '<h4>Versions précédentes du devis — archives ADV</h4><ul>';
        foreach ( $anciens_devis as $numero_version => $version_pdf ) {
            $nom_archive = isset( $version_pdf['nom'] ) ? (string) $version_pdf['nom'] : 'Ancien devis PDF';
            $url_archive = deprat_lien_document_archive_v72( $commande, $numero_version );
            if ( $url_archive ) {
                echo '<li><a href="' . esc_url( $url_archive ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $nom_archive ) . '</a>';
                if ( ! empty( $version_pdf['date'] ) ) { echo ' — ' . esc_html( deprat_date_utc_v7( $version_pdf['date'] ) ); }
                echo '</li>';
            }
        }
        echo '</ul><p style="font-size:12px;color:#64748b">Ces versions ne sont pas accessibles au client : seul le nouveau devis envoyé peut être accepté.</p></section>';
    }
    echo '<section style="margin:14px 0 0;padding:16px;background:#fff;border:1px solid #e2e8f0;border-radius:7px">';
    echo '<h4 style="margin:0 0 8px">3. Confirmation et traçabilité</h4>';
    if ( $date_accord ) {
        $client_id = absint( $commande->get_meta( '_deprat_devis_accepte_par', true ) );
        $client = $client_id ? get_user_by( 'id', $client_id ) : false;
        echo '<p><strong>Devis accepté :</strong> ' . esc_html( deprat_date_utc_v7( $date_accord ) ) . '<br>';
        echo '<strong>Compte ayant accepté :</strong> ' . esc_html( $client ? $client->display_name . ' (#' . $client_id . ')' : '#' . $client_id ) . '<br>';
        echo '<strong>Empreinte SHA-256 :</strong> <code style="word-break:break-all">' . esc_html( $commande->get_meta( '_deprat_pdf_sha256_accepte', true ) ) . '</code></p>';
        // Vérifier l'intégrité du document conservé.
        $pdf = deprat_pdf_contenu_prive( $commande );
        $empreinte = (string) $commande->get_meta( '_deprat_pdf_sha256_accepte', true );
        if ( $empreinte && ( false === $pdf || ! hash_equals( $empreinte, hash( 'sha256', $pdf ) ) ) ) {
            echo '<p style="color:#a61e1e"><strong>Alerte : le PDF du devis accepté est introuvable ou sa vérification a échoué.</strong></p>';
        }
    } else {
        echo '<p>En attente de l’acceptation du client.</p>';
    }
    echo '<p><strong>Facture mise à disposition :</strong> ' . ( $date_facture ? esc_html( deprat_date_utc_v7( $date_facture ) ) : 'Pas encore envoyée' ) . '</p>';
    echo '</section></div>';
}, 30 );

// Le même formulaire AJAX que V6, avec type de document devis OU facture.
// Le format binaire DPR1 et l'ancienne clé de chiffrement sont conservés.
add_action( 'wp_ajax_deprat_pdf_upload', function () {
    $id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
    if ( ! $id || ! wp_verify_nonce( $nonce, 'deprat_upload_pdf_' . $id ) ) {
        wp_send_json_error( array( 'message' => 'Vérification de sécurité échouée.' ), 403 );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'edit_shop_order', $id ) ) {
        wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }
    $commande = wc_get_order( $id );
    if ( ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) {
        wp_send_json_error( array( 'message' => 'Demande introuvable.' ), 404 );
    }
    $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'devis';
    if ( ! in_array( $type, array( 'devis', 'facture' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Type de document inconnu.' ), 400 );
    }
    $accord = $commande->get_meta( '_deprat_devis_accepte_le', true );
    if ( 'devis' === $type && ! in_array( $commande->get_status(), array( 'dpr-recu', 'dpr-encours' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Pour transmettre un nouveau devis, passez d’abord la demande à « En cours de traitement » et enregistrez la commande.' ), 409 );
    }
    if ( 'devis' === $type && ( $accord || in_array( $commande->get_status(), array( 'dpr-envoye', 'dpr-modifie' ), true ) ) ) {
        wp_send_json_error( array( 'message' => $accord ? 'Devis accepté : document verrouillé.' : 'Revenez au statut « En cours de traitement » avant de remplacer le devis envoyé.' ), 409 );
    }
    if ( 'facture' === $type && ! $accord ) {
        wp_send_json_error( array( 'message' => 'Attendez la validation du devis avant d’ajouter la facture.' ), 409 );
    }
    if ( 'facture' === $type && $commande->get_meta( '_deprat_facture_envoyee_le', true ) ) {
        wp_send_json_error( array( 'message' => 'Facture déjà envoyée : document verrouillé.' ), 409 );
    }
    if ( ! isset( $_FILES['pdf'] ) || ! is_array( $_FILES['pdf'] ) ) {
        wp_send_json_error( array( 'message' => 'Aucun fichier transmis.' ), 400 );
    }
    $pdf = $_FILES['pdf'];
    if ( UPLOAD_ERR_OK !== (int) ( $pdf['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $pdf['tmp_name'] ) ) {
        wp_send_json_error( array( 'message' => 'Téléversement non valide.' ), 400 );
    }
    if ( (int) $pdf['size'] < 20 || (int) $pdf['size'] > 10 * 1024 * 1024 ) {
        wp_send_json_error( array( 'message' => 'PDF vide ou supérieur à 10 Mo.' ), 400 );
    }
    $nom = sanitize_file_name( wp_basename( $pdf['name'] ) );
    if ( 'pdf' !== strtolower( pathinfo( $nom, PATHINFO_EXTENSION ) ) ||
         '%PDF-' !== @file_get_contents( $pdf['tmp_name'], false, null, 0, 5 ) ) {
        wp_send_json_error( array( 'message' => 'Le document doit être un PDF valide.' ), 400 );
    }
    if ( class_exists( 'finfo' ) ) {
        $info = new finfo( FILEINFO_MIME_TYPE );
        if ( ! in_array( $info->file( $pdf['tmp_name'] ), array( 'application/pdf', 'application/x-pdf' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Type de document non reconnu.' ), 400 );
        }
    }
    $dossier = deprat_pdf_dossier();
    $cle = deprat_pdf_cle_privee();
    if ( ! $dossier || ! $cle ) {
        wp_send_json_error( array( 'message' => 'Stockage sécurisé indisponible.' ), 500 );
    }
    $contenu = @file_get_contents( $pdf['tmp_name'] );
    if ( false === $contenu ) {
        wp_send_json_error( array( 'message' => 'Lecture du PDF impossible.' ), 500 );
    }
    try {
        $iv = random_bytes( 12 );
        $tag = '';
        $crypte = openssl_encrypt( $contenu, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag );
        $fichier = 'commande-' . $id . '-' . $type . '-' . bin2hex( random_bytes( 8 ) ) . '.bin';
    } catch ( Throwable $e ) {
        wp_send_json_error( array( 'message' => 'Erreur de chiffrement.' ), 500 );
    }
    // Défense en profondeur : n'écrire que dans le dossier privé et avec le
    // nom construit localement (ID entier, type autorisé et suffixe aléatoire).
    // Aucune donnée fournie par le navigateur ne sert de chemin arbitraire.
    if ( ! preg_match( '/\\Acommande-[0-9]+-(?:devis|facture)-[a-f0-9]{16}\\.bin\\z/D', $fichier ) ) {
        wp_send_json_error( array( 'message' => 'Nom de fichier non valide.' ), 400 );
    }
    $chemin_prive = trailingslashit( $dossier ) . $fichier;
    // Fausse alerte Semgrep « tainted-filename » : le nom est validé par une
    // liste blanche stricte, et le dossier est créé par deprat_pdf_dossier().
    if ( false === $crypte || 16 !== strlen( $tag ) || false === @file_put_contents( $chemin_prive, 'DPR1' . $iv . $tag . $crypte, LOCK_EX ) ) { // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
        wp_send_json_error( array( 'message' => 'Enregistrement du document impossible.' ), 500 );
    }
    @chmod( $chemin_prive, 0600 ); // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
    $cle_meta = 'devis' === $type ? '_deprat_pdf_fichier' : '_deprat_facture_pdf_fichier';
    $nom_meta = 'devis' === $type ? '_deprat_pdf_nom' : '_deprat_facture_pdf_nom';
    $ancien = (string) $commande->get_meta( $cle_meta, true );
    $ancien_nom = (string) $commande->get_meta( $nom_meta, true );
    $commande->update_meta_data( $cle_meta, $fichier );
    $commande->update_meta_data( $nom_meta, $nom );
    $commande->save();
    // Ne JAMAIS supprimer une version envoyée lors d'un ajustement :
    // l'ADV doit pouvoir consulter l'ancien PDF pour le suivi commercial.
    if ( 'devis' === $type && $ancien && $ancien !== $fichier && basename( $ancien ) === $ancien ) {
        $archives = $commande->get_meta( '_deprat_anciens_devis', true );
        if ( ! is_array( $archives ) ) { $archives = array(); }
        $archives[] = array(
            'fichier' => $ancien,
            'nom' => $ancien_nom ?: 'Ancien devis',
            'date' => gmdate( 'Y-m-d H:i:s' ),
        );
        $commande->update_meta_data( '_deprat_anciens_devis', $archives );
        $commande->save();
    } elseif ( 'facture' === $type && $ancien && $ancien !== $fichier && basename( $ancien ) === $ancien ) {
        // Une facture non encore publiée peut être corrigée : conserver également l'ancienne version côté serveur.
        $versions = $commande->get_meta( '_deprat_anciennes_factures', true );
        if ( ! is_array( $versions ) ) { $versions = array(); }
        $versions[] = array( 'fichier' => $ancien, 'date' => gmdate( 'Y-m-d H:i:s' ) );
        $commande->update_meta_data( '_deprat_anciennes_factures', $versions );
        $commande->save();
    }
    $commande->add_order_note( 'Service ADV : PDF ' . $type . ' ajouté par ' . wp_get_current_user()->display_name . ' (' . $nom . ').', 0 );

    // Un seul clic ADV : dépôt du devis + changement réel de statut WooCommerce.
    // Le hook woocommerce_order_status_changed existant notifie ensuite le client.
    // La facture ne suit PAS ce chemin : publication seulement après accord du client.
    $auto_sent = false;
    if ( 'devis' === $type ) {
        $revision = 'oui' === $commande->get_meta( '_deprat_revision_en_attente', true );
        $nouveau_statut = $revision ? 'dpr-modifie' : 'dpr-envoye';
        $change = $commande->update_status(
            $nouveau_statut,
            'Service ADV : devis ' . ( $revision ? 'modifié' : 'initial' ) . ' transmis automatiquement après enregistrement du PDF.',
            true
        );
        $apres = wc_get_order( $id );
        if ( ! $change || ! $apres instanceof WC_Order || $nouveau_statut !== $apres->get_status() ) {
            // Le PDF est conservé pour permettre à l'ADV de réessayer en utilisant
            // « Envoyer le devis (secours) » sans recommencer le téléversement.
            wp_send_json_error( array(
                'message' => 'Le PDF a été enregistré, mais son envoi automatique a échoué. Actualisez la fiche et utilisez « Envoyer le devis (secours) ». Aucun nouvel envoi automatique ne doit être présumé.',
            ), 500 );
        }
        $commande = $apres;
        $auto_sent = true;
    }
    wp_send_json_success( array(
        'name' => $nom,
        'view_url' => deprat_lien_document_v7( $commande, $type ),
        'auto_sent' => $auto_sent,
    ) );
} );

// 6 bis. Lire le PDF chiffre en reutilisant LE MEME format et LA MEME cle que dans V3.
// Important : ne pas modifier ces deux valeurs sans migrer les documents existants.
function deprat_pdf_contenu_prive( $commande ) {
    if ( ! $commande instanceof WC_Order ) {
        return false;
    }
    $fichier = (string) $commande->get_meta( '_deprat_pdf_fichier', true );
    $dossier = deprat_pdf_dossier();
    $cle     = deprat_pdf_cle_privee();
    if ( ! $fichier || basename( $fichier ) !== $fichier || ! $dossier || ! $cle ) {
        return false;
    }
    $chemin = trailingslashit( $dossier ) . $fichier;
    if ( ! is_file( $chemin ) || ! is_readable( $chemin ) ) {
        return false;
    }
    $paquet = @file_get_contents( $chemin );
    if ( false === $paquet || strlen( $paquet ) < 40 || 'DPR1' !== substr( $paquet, 0, 4 ) ) {
        return false;
    }
    $pdf = openssl_decrypt(
        substr( $paquet, 32 ), 'aes-256-gcm', $cle, OPENSSL_RAW_DATA,
        substr( $paquet, 4, 12 ), substr( $paquet, 16, 16 )
    );
    return ( false !== $pdf && '%PDF-' === substr( $pdf, 0, 5 ) ) ? $pdf : false;
}

// Facture : même cryptage que les devis, mais clé de métadonnées distincte.
function deprat_facture_contenu_prive_v7( $commande ) {
    if ( ! $commande instanceof WC_Order ) { return false; }
    $fichier = (string) $commande->get_meta( '_deprat_facture_pdf_fichier', true );
    $dossier = deprat_pdf_dossier();
    $cle = deprat_pdf_cle_privee();
    if ( ! $fichier || basename( $fichier ) !== $fichier || ! $dossier || ! $cle ) { return false; }
    $chemin = trailingslashit( $dossier ) . $fichier;
    if ( ! is_file( $chemin ) || ! is_readable( $chemin ) ) { return false; }
    $paquet = @file_get_contents( $chemin );
    if ( false === $paquet || strlen( $paquet ) < 40 || 'DPR1' !== substr( $paquet, 0, 4 ) ) { return false; }
    $pdf = openssl_decrypt( substr( $paquet, 32 ), 'aes-256-gcm', $cle, OPENSSL_RAW_DATA,
        substr( $paquet, 4, 12 ), substr( $paquet, 16, 16 ) );
    return ( false !== $pdf && '%PDF-' === substr( $pdf, 0, 5 ) ) ? $pdf : false;
}

function deprat_devis_est_au_client( $commande ) {
    return is_user_logged_in()
        && $commande instanceof WC_Order
        && (int) $commande->get_customer_id() === get_current_user_id()
        && 'oui' === $commande->get_meta( '_deprat_demande', true );
}

// Lien sécurisé pour devis ET facture, valable pour ADV et client selon droits.
function deprat_lien_document_v7( $commande, $type ) {
    if ( ! $commande instanceof WC_Order || ! in_array( $type, array( 'devis', 'facture' ), true ) ) { return ''; }
    $id = $commande->get_id();
    $version = (string) $commande->get_meta(
        'devis' === $type ? '_deprat_pdf_fichier' : '_deprat_facture_pdf_fichier', true );
    if ( ! $version ) { return ''; }
    return wp_nonce_url(
        add_query_arg( array(
            'deprat_document' => '1',
            'order_id' => $id,
            'type' => $type,
        ), home_url( '/' ) ),
        'deprat_document_v7_' . $id . '_' . $type . '_' . $version
    );
}
// Ancienne fonction gardée pour compatibilité avec d'éventuelles références externes.
function deprat_lien_pdf_client( $commande ) { return deprat_lien_document_v7( $commande, 'devis' ); }

// Anciennes versions : URL à usage strictement ADV, liée à la version physique du PDF.
function deprat_lien_document_archive_v72( $commande, $index ) {
    if ( ! $commande instanceof WC_Order ) { return ''; }
    $archives = $commande->get_meta( '_deprat_anciens_devis', true );
    $index = absint( $index );
    if ( ! is_array( $archives ) || empty( $archives[ $index ]['fichier'] ) ) { return ''; }
    $fichier = (string) $archives[ $index ]['fichier'];
    return wp_nonce_url( add_query_arg( array(
        'deprat_document' => '1', 'order_id' => $commande->get_id(),
        'type' => 'archive', 'version' => $index,
    ), home_url( '/' ) ), 'deprat_archive_v72_' . $commande->get_id() . '_' . $index . '_' . $fichier );
}
function deprat_pdf_archive_v72( $commande, $index ) {
    if ( ! $commande instanceof WC_Order ) { return false; }
    $archives = $commande->get_meta( '_deprat_anciens_devis', true );
    if ( ! is_array( $archives ) || empty( $archives[ $index ]['fichier'] ) ) { return false; }
    $fichier = (string) $archives[ $index ]['fichier'];
    if ( basename( $fichier ) !== $fichier ) { return false; }
    $dossier = deprat_pdf_dossier(); $cle = deprat_pdf_cle_privee();
    if ( ! $dossier || ! $cle || ! is_readable( $dossier . '/' . $fichier ) ) { return false; }
    $paquet = @file_get_contents( $dossier . '/' . $fichier );
    if ( false === $paquet || strlen( $paquet ) < 40 || 'DPR1' !== substr( $paquet, 0, 4 ) ) { return false; }
    $pdf = openssl_decrypt( substr( $paquet, 32 ), 'aes-256-gcm', $cle, OPENSSL_RAW_DATA,
        substr( $paquet, 4, 12 ), substr( $paquet, 16, 16 ) );
    return ( false !== $pdf && '%PDF-' === substr( $pdf, 0, 5 ) ) ? $pdf : false;
}

// Point d'accès côté site, sans /wp-admin/ visible dans le navigateur du client.
// L'ancien accès admin-post est conservé pour les liens existants, mais les
// nouveaux liens PDF passent par /?deprat_document=1&order_id=...
function deprat_servir_document_v71() {
    $id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
    $commande = $id ? wc_get_order( $id ) : false;
    if ( ! $commande instanceof WC_Order ||
         'oui' !== $commande->get_meta( '_deprat_demande', true ) ||
         ! in_array( $type, array( 'devis', 'facture', 'archive' ), true ) ) {
        wp_die( 'Document introuvable.', 'DEPRAT', array( 'response' => 404 ) );
    }
    $est_adv = current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) || current_user_can( 'edit_shop_order', $id );
    $est_client = deprat_devis_est_au_client( $commande );
    if ( ! $est_adv && ! $est_client ) { wp_die( 'Accès refusé.', 'DEPRAT', array( 'response' => 403 ) ); }
    if ( 'archive' === $type && ! $est_adv ) { wp_die( 'Archives ADV uniquement.', 'DEPRAT', array( 'response' => 403 ) ); }
    if ( $est_client && ! $est_adv ) {
        if ( 'devis' === $type && ! in_array( $commande->get_status(), array( 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' ), true ) ) {
            wp_die( 'Le devis n’est pas encore disponible.', 'DEPRAT', array( 'response' => 403 ) );
        }
        if ( 'facture' === $type && ! in_array( $commande->get_status(), array( 'dpr-facture', 'dpr-termine' ), true ) ) {
            wp_die( 'La facture n’est pas encore disponible.', 'DEPRAT', array( 'response' => 403 ) );
        }
    }
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( 'archive' === $type ) {
        $index = isset( $_GET['version'] ) ? absint( $_GET['version'] ) : 0;
        $archives = $commande->get_meta( '_deprat_anciens_devis', true );
        $version = is_array( $archives ) && isset( $archives[ $index ]['fichier'] ) ? (string) $archives[ $index ]['fichier'] : '';
        if ( ! $version || ! wp_verify_nonce( $nonce, 'deprat_archive_v72_' . $id . '_' . $index . '_' . $version ) ) {
            wp_die( 'Archive indisponible ou lien expiré.', 'DEPRAT', array( 'response' => 403 ) );
        }
        $pdf = deprat_pdf_archive_v72( $commande, $index );
    } else {
        $version = (string) $commande->get_meta(
            'devis' === $type ? '_deprat_pdf_fichier' : '_deprat_facture_pdf_fichier', true );
        if ( ! $version || ! wp_verify_nonce( $nonce, 'deprat_document_v7_' . $id . '_' . $type . '_' . $version ) ) {
            wp_die( 'Lien expiré : retournez dans votre espace professionnel.', 'DEPRAT', array( 'response' => 403 ) );
        }
        $pdf = 'devis' === $type ? deprat_pdf_contenu_prive( $commande ) : deprat_facture_contenu_prive_v7( $commande );
    }
    if ( false === $pdf || ! is_string( $pdf ) || 0 !== strpos( $pdf, '%PDF-' ) ) {
        wp_die( 'Le document est indisponible.', 'DEPRAT', array( 'response' => 404 ) );
    }
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
    header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
    header( 'Referrer-Policy: no-referrer' );
    header( 'Content-Type: application/pdf' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Content-Disposition: inline; filename="' . ( 'facture' === $type ? 'Facture' : 'Devis' ) . '-DEPRAT-' . $id . '.pdf"' );
    header( 'Content-Length: ' . strlen( $pdf ) );
    // Exception documentée : réponse binaire PDF, accessible uniquement après
    // contrôle du compte, de la commande et d'un nonce de version ; MIME PDF
    // avec « nosniff ». Escaper le flux corromprait le devis/facture.
    echo $pdf; // nosemgrep: php.lang.security.injection.echoed-request.echoed-request
    exit;
}
add_action( 'template_redirect', function () {
    if ( ! isset( $_GET['deprat_document'] ) || '1' !== (string) wp_unslash( $_GET['deprat_document'] ) ) { return; }
    // Tout lien sans compte connecté doit être refusé avant lecture du PDF.
    if ( ! is_user_logged_in() ) {
        wp_die( 'Veuillez vous connecter à votre espace professionnel pour consulter ce document.', 'DEPRAT', array( 'response' => 403 ) );
    }
    deprat_servir_document_v71();
}, 0 );
// Anciennes adresses /wp-admin/admin-post.php : toujours protégées et compatibles.
add_action( 'admin_post_deprat_consulter_document_v7', 'deprat_servir_document_v71' );
// Compatibilité avec les anciens liens encore ouverts (V6).
add_action( 'admin_post_deprat_consulter_devis', function () {
    $id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    $commande = $id ? wc_get_order( $id ) : false;
    if ( ! deprat_devis_est_au_client( $commande ) ) {
        wp_die( 'Accès refusé.', 'DEPRAT', array( 'response' => 403 ) );
    }
    $version = (string) $commande->get_meta( '_deprat_pdf_fichier', true );
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'deprat_consulter_devis_' . $id . '_' . $version ) ) {
        wp_die( 'Lien expiré : ouvrez le devis depuis votre historique.', 'DEPRAT', array( 'response' => 403 ) );
    }
    wp_safe_redirect( deprat_lien_document_v7( $commande, 'devis' ) );
    exit;
} );

// Envoi du devis initial ou modifié. Le statut distinct conserve le sens de la version envoyée.
add_action( 'woocommerce_order_status_changed', function ( $id, $ancien, $nouveau, $commande ) {
    if ( ! empty( $GLOBALS['deprat_status_correction'] ) ||
         ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ||
         ! in_array( $nouveau, array( 'dpr-envoye', 'dpr-modifie' ), true ) || $commande->get_status() !== $nouveau || $commande->get_meta( '_deprat_devis_accepte_le', true ) ) { return; }
    $est_modifie = 'dpr-modifie' === $nouveau;
    if ( $est_modifie && 'oui' === $commande->get_meta( '_deprat_revision_en_attente', true ) ) {
        $commande->delete_meta_data( '_deprat_revision_en_attente' );
        $commande->delete_meta_data( '_deprat_revision_ancien_fichier' );
        $commande->save_meta_data();
        $commande->add_order_note( 'Service ADV : une nouvelle version du devis a été envoyée après demande de modification.', 0 );
    }
    $numero = $commande->get_order_number();
    $prenom = trim( (string) $commande->get_billing_first_name() );
    $corps  = '<p>' . ( $prenom ? 'Bonjour ' . esc_html( $prenom ) . ',' : 'Bonjour,' ) . '</p>';
    $corps .= $est_modifie
        ? '<p>Votre <strong>devis DEPRAT modifié n° ' . esc_html( $numero ) . '</strong> est disponible dans votre espace professionnel.</p>'
        : '<p>Votre <strong>devis DEPRAT n° ' . esc_html( $numero ) . '</strong> est disponible dans votre espace professionnel.</p>';
    $corps .= '<p>Vous pouvez le consulter et <strong>confirmer votre acceptation en ligne</strong>.</p>';
    $corps .= deprat_devis_email_bouton_v7( 'Consulter et valider mon devis', deprat_lien_historique() );
    $corps .= '<p style="font-size:13px;color:#64748b">Le PDF est accessible uniquement après connexion. Aucun document n’est joint à cet e-mail.</p>';
    $ok = deprat_envoyer_email_client_v7( $commande->get_billing_email(),
        ( $est_modifie ? 'DEPRAT - Votre devis modifié #' : 'DEPRAT - Votre devis #' ) . $numero . ' est à valider',
        ( $est_modifie ? 'Votre devis modifié est disponible' : 'Votre devis est disponible' ), $corps, 'devis disponible #' . $id );
    $commande->add_order_note( $ok ? ( $est_modifie ? 'Notification DEVIS MODIFIÉ mise en file d’envoi pour le client (sans PDF joint).' : 'Notification devis mise en file d’envoi pour le client (sans PDF joint).' )
        : 'ERREUR : notification du devis non remise au système mail.', 0 );
}, 10, 4 );

// Envoi de la facture : date/heure UTC figées, publication client et e-mail HTML.
add_action( 'woocommerce_order_status_changed', function ( $id, $ancien, $nouveau, $commande ) {
    if ( ! empty( $GLOBALS['deprat_status_correction'] ) ||
         ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ||
         'dpr-facture' !== $nouveau || $commande->get_status() !== $nouveau ) { return; }
    if ( ! $commande->get_meta( '_deprat_facture_envoyee_le', true ) ) {
        $commande->update_meta_data( '_deprat_facture_envoyee_le', gmdate( 'Y-m-d H:i:s' ) );
        $commande->save_meta_data();
    }
    $numero = $commande->get_order_number();
    $prenom = trim( (string) $commande->get_billing_first_name() );
    $corps = '<p>' . ( $prenom ? 'Bonjour ' . esc_html( $prenom ) . ',' : 'Bonjour,' ) . '</p>';
    $corps .= '<p>Votre <strong>facture relative à la demande n° ' . esc_html( $numero ) . '</strong> est disponible dans votre espace professionnel.</p>';
    $corps .= '<p><strong>Date de mise à disposition :</strong> ' . esc_html( deprat_date_utc_v7( $commande->get_meta( '_deprat_facture_envoyee_le', true ) ) ) . '</p>';
    $corps .= '<p>' . esc_html( deprat_mention_paiement_v7() ) . '</p>';
    $corps .= deprat_devis_email_bouton_v7( 'Consulter ma facture', deprat_lien_historique() );
    $corps .= '<p style="font-size:13px;color:#64748b">Le PDF est accessible après connexion. Aucun document n’est joint à cet e-mail.</p>';
    $ok = deprat_envoyer_email_client_v7( $commande->get_billing_email(),
        'DEPRAT - Facture disponible pour la demande #' . $numero,
        'Votre facture est disponible', $corps, 'facture disponible #' . $id );
    $commande->add_order_note( ( $ok ? 'Notification facture mise en file d’envoi pour le client.' : 'ERREUR : notification facture non remise au système mail.' )
        . ' Mise à disposition le ' . deprat_date_utc_v7( $commande->get_meta( '_deprat_facture_envoyee_le', true ) ) . '.', 0 );
}, 10, 4 );

// 6 ter. Demande de modification par le client (avant acceptation seulement).
// Retour à « En cours de traitement » ; l'ancien devis est masqué au client,
// mais conservé et archivé lorsque le service ADV déposera sa nouvelle version.
add_action( 'template_redirect', function () {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
         ! isset( $_POST['deprat_demander_modification'] ) ) { return; }
    $id = absint( $_POST['deprat_demander_modification'] );
    $commande = $id ? wc_get_order( $id ) : false;
    if ( ! deprat_devis_est_au_client( $commande ) ||
         ! in_array( $commande->get_status(), array( 'dpr-envoye', 'dpr-modifie' ), true ) ||
         $commande->get_meta( '_deprat_devis_accepte_le', true ) ) {
        wp_die( 'Cette demande ne peut plus être modifiée depuis votre espace.', 'DEPRAT', array( 'response' => 403 ) );
    }
    $fichier = (string) $commande->get_meta( '_deprat_pdf_fichier', true );
    $version = isset( $_POST['deprat_version'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_version'] ) ) : '';
    $nonce = isset( $_POST['deprat_revision_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_revision_nonce'] ) ) : '';
    if ( ! $fichier || ! hash_equals( $fichier, $version ) ||
         ! wp_verify_nonce( $nonce, 'deprat_demander_modification_' . $id . '_' . $fichier ) ) {
        wp_die( 'Le devis a changé ou votre session a expiré. Rechargez la page.', 'DEPRAT', array( 'response' => 403 ) );
    }
    $message = isset( $_POST['deprat_ajustements'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['deprat_ajustements'] ) ) ) : '';
    $message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 2000 ) : substr( $message, 0, 2000 );
    if ( strlen( $message ) < 5 ) {
        wp_safe_redirect( add_query_arg( 'deprat_revision_erreur', $id, deprat_lien_historique() ) );
        exit;
    }
    $id_client = get_current_user_id();
    $commande->update_meta_data( '_deprat_revision_en_attente', 'oui' );
    $commande->update_meta_data( '_deprat_revision_ancien_fichier', $fichier );
    $commande->update_meta_data( '_deprat_dernier_ajustement_texte', $message );
    $commande->update_meta_data( '_deprat_dernier_ajustement_date', gmdate( 'Y-m-d H:i:s' ) );
    $commande->update_meta_data( '_deprat_dernier_ajustement_client', $id_client );
    $commande->set_status( 'dpr-encours' );
    $commande->save();
    $commande->add_order_note( 'Client #' . $id_client . ' : demande de modification du devis le ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC : ' . $message, 0 );
    $contenu = '<p>Un client a demandé <strong>une modification du devis n° ' . esc_html( $commande->get_order_number() ) . '</strong>.</p>';
    $contenu .= '<p><strong>Société :</strong> ' . esc_html( $commande->get_billing_company() ) . '</p>';
    $contenu .= '<div style="background:#f7f9fc;border-left:3px solid #ad963f;padding:15px 18px;white-space:pre-wrap">' . nl2br( esc_html( $message ) ) . '</div>';
    $contenu .= '<p>La demande est repassée en cours de traitement. Déposez une nouvelle version du PDF puis choisissez « Envoyer le devis modifié ».</p>';
    $contenu .= deprat_devis_email_bouton_v7( 'Ouvrir la demande dans WooCommerce', $commande->get_edit_order_url() );
    deprat_envoyer_email_admin_v7( deprat_email_adv(),
        'DEPRAT - Modifications demandées sur le devis #' . $commande->get_order_number(),
        'Ajustements demandés sur le devis', $contenu, 'modification devis #' . $id );
    $client_contenu = '<p>Nous avons bien reçu vos demandes d’ajustement pour le devis n° ' . esc_html( $commande->get_order_number() ) . '.</p>';
    $client_contenu .= '<p>Le service ADV prépare une nouvelle version. Votre ancien devis n’est plus validable.</p>';
    $client_contenu .= deprat_devis_email_bouton_v7( 'Suivre ma demande', deprat_lien_historique() );
    deprat_envoyer_email_client_v7( $commande->get_billing_email(),
        'DEPRAT - Ajustements enregistrés pour le devis #' . $commande->get_order_number(),
        'Votre demande de modification a été reçue', $client_contenu, 'revision #' . $id );
    wp_safe_redirect( add_query_arg( 'deprat_revision_ok', $id, deprat_lien_historique() ) );
    exit;
}, 9 );

// 6 quater. Le client valide le PDF exact (version, identite et date tracees).
add_action( 'admin_post_deprat_accepter_devis', function () {
    $id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $commande = $id ? wc_get_order( $id ) : false;
    if ( ! deprat_devis_est_au_client( $commande ) ) {
        wp_die( 'Acces refuse.', 'DEPRAT', array( 'response' => 403 ) );
    }
    if ( ! in_array( $commande->get_status(), array( 'dpr-envoye', 'dpr-modifie' ), true ) || $commande->get_meta( '_deprat_devis_accepte_le', true ) ) {
        wp_safe_redirect( deprat_lien_historique() );
        exit;
    }
    $version = (string) $commande->get_meta( '_deprat_pdf_fichier', true );
    $version_post = isset( $_POST['deprat_version'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_version'] ) ) : '';
    $nonce = isset( $_POST['deprat_nonce_accord'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_nonce_accord'] ) ) : '';
    $accord = isset( $_POST['deprat_accord'] ) && 'oui' === $_POST['deprat_accord'];
    if ( ! $version || ! hash_equals( $version, $version_post ) ||
         ! wp_verify_nonce( $nonce, 'deprat_accepter_devis_' . $id . '_' . $version ) || ! $accord ) {
        wp_die( 'Validation invalide ou devis modifie : retournez dans Mes demandes.', 'DEPRAT', array( 'response' => 403 ) );
    }
    $pdf = deprat_pdf_contenu_prive( $commande );
    if ( false === $pdf ) {
        wp_die( 'Le PDF est indisponible : le devis ne peut pas etre accepte.', 'DEPRAT', array( 'response' => 409 ) );
    }
    $client = wp_get_current_user();
    $date_utc = gmdate( 'Y-m-d H:i:s' );
    $empreinte = hash( 'sha256', $pdf );
    $commande->update_meta_data( '_deprat_devis_accepte_le', $date_utc );
    $commande->update_meta_data( '_deprat_devis_accepte_par', $client->ID );
    $commande->update_meta_data( '_deprat_pdf_fichier_accepte', $version );
    $commande->update_meta_data( '_deprat_pdf_sha256_accepte', $empreinte );
    $commande->set_status( 'dpr-accepte' );
    $commande->save();
    $commande->add_order_note(
        'Devis accepte dans l espace pro par le compte #' . $client->ID
        . ' (' . $client->user_email . '), le ' . $date_utc . ' UTC. Empreinte du PDF : ' . $empreinte . '.', 0
    );
    $numero = $commande->get_order_number();
    $message_adv  = '<p>Le client a <strong>accepté le devis n° ' . esc_html( $numero ) . '</strong> dans son espace professionnel.</p>';
    $message_adv .= '<div style="padding:16px 18px;background:#f7f9fc;border-left:3px solid #ad963f;line-height:1.75;">';
    $message_adv .= '<strong>Société :</strong> ' . esc_html( $commande->get_billing_company() ) . '<br>';
    $message_adv .= '<strong>Client :</strong> ' . esc_html( $client->display_name ) . '<br>';
    $message_adv .= '<strong>E-mail :</strong> ' . esc_html( $client->user_email ) . '<br>';
    $message_adv .= '<strong>Date de validation (UTC) :</strong> ' . esc_html( $date_utc ) . '</div>';
    $message_adv .= '<p>La validation est enregistrée dans WooCommerce. Vous pouvez poursuivre le traitement dans votre logiciel habituel.</p>';
    $message_adv .= deprat_devis_email_bouton_v7( 'Ouvrir le devis accepté', $commande->get_edit_order_url() );
    $ok_admin = deprat_envoyer_email_admin_v7(
        deprat_email_adv(),
        'DEPRAT - Devis #' . $numero . ' accepté par le client',
        'Devis accepté #' . $numero,
        $message_adv,
        'acceptation #' . $id
    );
    if ( ! $ok_admin ) {
        $commande->add_order_note( 'ERREUR : notification de devis accepte non transmise a l administration.', 0 );
    }
    $prenom = trim( (string) get_user_meta( $client->ID, 'billing_first_name', true ) );
    $bonjour = $prenom ? 'Bonjour ' . esc_html( $prenom ) . ',' : 'Bonjour,';
    $corps_accord  = '<p style="margin:0 0 16px;">' . $bonjour . '</p>';
    $corps_accord .= '<p>Nous confirmons que votre <strong>accord sur le devis de la demande n° ' . esc_html( $numero ) . '</strong> a bien été enregistré.</p>';
    $corps_accord .= '<p>Notre équipe va poursuivre le traitement de votre commande. Vous pourrez retrouver le devis validé dans votre historique.</p>';
    $corps_accord .= deprat_devis_email_bouton_v7( 'Consulter mon historique', deprat_lien_historique() );
    deprat_envoyer_email_client_v7(
        $client->user_email,
        'DEPRAT - Confirmation de votre accord #' . $numero,
        'Votre devis a été accepté',
        $corps_accord,
        'confirmation acceptation #' . $id
    );
    wp_safe_redirect( add_query_arg( 'deprat_accepte', $id, deprat_lien_historique() ) );
    exit;
} );

// Protéger la preuve d'acceptation et refuser les changements de statut sans PDF.
// La V6 bloquait tout après dpr-accepte : la V7 autorise désormais facture puis terminé.
add_action( 'woocommerce_order_status_changed', function ( $id, $ancien, $nouveau, $commande ) {
    if ( ! empty( $GLOBALS['deprat_status_correction'] ) ||
         ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) { return; }
    $accord = (bool) $commande->get_meta( '_deprat_devis_accepte_le', true );
    $facture_envoyee = (bool) $commande->get_meta( '_deprat_facture_envoyee_le', true );
    $invalide = false;
    $raison = '';
    if ( 'dpr-termine' === $ancien && 'dpr-termine' !== $nouveau ) {
        $invalide = true; $raison = 'Une commande terminée ne peut pas être réouverte depuis ce menu.';
    } elseif ( $accord && ! in_array( $nouveau, array( 'dpr-accepte', 'dpr-facture', 'dpr-termine' ), true ) ) {
        $invalide = true; $raison = 'Le devis accepté ne peut pas être ramené à une étape antérieure.';
    } elseif ( 'dpr-accepte' === $nouveau && ! $accord ) {
        $invalide = true; $raison = 'Seul le client peut accepter le devis depuis son espace.';
    } elseif ( in_array( $nouveau, array( 'dpr-facture', 'dpr-termine' ), true ) && ! $accord ) {
        $invalide = true; $raison = 'Acceptation du devis requise avant la facture.';
    } elseif ( 'dpr-envoye' === $nouveau && 'oui' === $commande->get_meta( '_deprat_revision_en_attente', true ) ) {
        $invalide = true; $raison = 'Ajustement demandé : utilisez « Envoyer le devis modifié » après avoir déposé un NOUVEAU PDF.';
    } elseif ( 'dpr-modifie' === $nouveau && 'oui' !== $commande->get_meta( '_deprat_revision_en_attente', true ) ) {
        $invalide = true; $raison = 'Le statut « Devis modifié » est réservé aux ajustements demandés par le client.';
    } elseif ( 'dpr-modifie' === $nouveau &&
               $commande->get_meta( '_deprat_pdf_fichier', true ) === $commande->get_meta( '_deprat_revision_ancien_fichier', true ) ) {
        $invalide = true; $raison = 'Déposez une NOUVELLE version du PDF avant d’envoyer le devis modifié.';
    } elseif ( in_array( $nouveau, array( 'dpr-envoye', 'dpr-modifie' ), true ) && false === deprat_pdf_contenu_prive( $commande ) ) {
        $invalide = true; $raison = 'Déposez un PDF de devis lisible avant l’envoi au client.';
    } elseif ( 'dpr-facture' === $nouveau && false === deprat_facture_contenu_prive_v7( $commande ) ) {
        $invalide = true; $raison = 'Déposez le PDF de facture avant de choisir « Envoyer la facture ».';
    } elseif ( 'dpr-termine' === $nouveau && ! $facture_envoyee ) {
        $invalide = true; $raison = 'La facture doit d’abord avoir été mise à disposition.';
    } elseif ( $facture_envoyee && ! in_array( $nouveau, array( 'dpr-facture', 'dpr-termine' ), true ) ) {
        $invalide = true; $raison = 'Une facture déjà publiée ne peut pas être retirée silencieusement.';
    }
    if ( ! $invalide ) { return; }
    $GLOBALS['deprat_status_correction'] = true;
    try { $commande->set_status( $ancien ); $commande->save(); }
    finally { unset( $GLOBALS['deprat_status_correction'] ); }
    $commande->add_order_note( 'DEPRAT : statut restauré. ' . $raison, 0 );
}, 5, 4 );

// 7. Neutraliser la caisse et les anciennes vues « commandes » côté client.
add_action( 'template_redirect', function () {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
    if ( is_account_page() && ( is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'view-order' ) ) ) {
        wp_safe_redirect( deprat_lien_historique() );
        exit;
    }
    // Si un ancien menu pointe vers /mon-compte/mes-demandes/, utiliser la page autonome.
    $page = get_page_by_path( 'mes-demandes-devis', OBJECT, 'page' );
    if ( $page && 'publish' === $page->post_status && is_account_page() && is_wc_endpoint_url( 'mes-demandes' ) ) {
        wp_safe_redirect( get_permalink( $page ) );
        exit;
    }
}, 20 );

// 8. Historique client : recherche par date, numéro, SKU et désignation.
function deprat_recherche_normaliser_v7( $valeur ) {
    return strtolower( remove_accents( (string) $valeur ) );
}
function deprat_historique_correspond_v7( $commande, $recherche ) {
    if ( '' === $recherche ) { return true; }
    $elements = array( $commande->get_order_number(), '#' . $commande->get_order_number(), $commande->get_id() );
    $date = $commande->get_date_created();
    if ( $date ) {
        $elements[] = wc_format_datetime( $date, 'd/m/Y' );
        $elements[] = wc_format_datetime( $date, 'd-m-Y' );
        $elements[] = wc_format_datetime( $date, 'Y-m-d' );
        $elements[] = wc_format_datetime( $date, 'd/m/Y H:i' );
    }
    $elements[] = (string) $commande->get_meta( '_deprat_commentaire', true );
    foreach ( $commande->get_items( 'line_item' ) as $article ) {
        $elements[] = $article->get_name();
        $elements[] = (string) $article->get_meta( '_deprat_reference', true );
        $produit = $article->get_product();
        if ( $produit ) { $elements[] = (string) $produit->get_sku(); }
    }
    $ensemble = deprat_recherche_normaliser_v7( implode( ' ', $elements ) );
    return false !== strpos( $ensemble, deprat_recherche_normaliser_v7( $recherche ) );
}

// Refaire une demande n'altère JAMAIS l'ancienne : remet les articles dans le panier.
add_action( 'template_redirect', function () {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['deprat_refaire_demande'] ) ) { return; }
    $id = absint( $_POST['deprat_refaire_demande'] );
    $nonce = isset( $_POST['deprat_refaire_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['deprat_refaire_nonce'] ) ) : '';
    $commande = $id ? wc_get_order( $id ) : false;
    if ( ! deprat_devis_est_au_client( $commande ) || ! wp_verify_nonce( $nonce, 'deprat_refaire_' . $id ) ) {
        wp_die( 'Action non autorisée.', 'DEPRAT', array( 'response' => 403 ) );
    }
    if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) { wc_load_cart(); }
    if ( ! WC()->cart ) { wp_die( 'Panier momentanément indisponible.', 'DEPRAT' ); }
    $ajoutes = 0;
    $ignores = 0;
    foreach ( $commande->get_items( 'line_item' ) as $article ) {
        $produit = $article->get_product();
        $qte = max( 1, (int) $article->get_quantity() );
        if ( ! $produit || ! $produit->is_purchasable() ) { $ignores++; continue; }
        $pid = $article->get_product_id();
        $variation = $article->get_variation_id();
        $attributs = $variation && $produit instanceof WC_Product_Variation ? $produit->get_variation_attributes() : array();
        if ( WC()->cart->add_to_cart( $pid, $qte, $variation, $attributs ) ) { $ajoutes++; }
        else { $ignores++; }
    }
    if ( $ajoutes ) {
        wc_add_notice( $ajoutes . ' référence(s) ajoutée(s) à votre panier. Vérifiez les quantités avant l’envoi.', 'success' );
    } else {
        wc_add_notice( 'Aucun produit de cette ancienne demande ne peut actuellement être ajouté.', 'notice' );
    }
    if ( $ignores ) { wc_add_notice( $ignores . ' référence(s) non disponibles ont été ignorées.', 'notice' ); }
    wp_safe_redirect( wc_get_cart_url() );
    exit;
}, 8 );

function deprat_html_historique() {
    if ( ! function_exists( 'wc_get_orders' ) ) { return '<p>WooCommerce doit être activé.</p>'; }
    if ( ! is_user_logged_in() ) {
        return '<p>Connectez-vous pour consulter vos documents. <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">Me connecter</a></p>';
    }
    $GLOBALS['deprat_historique_modal_v71_visible'] = true;
    ob_start();
    $confirmation = isset( $_GET['demande_envoyee'] ) ? wc_get_order( absint( $_GET['demande_envoyee'] ) ) : false;
    if ( deprat_devis_est_au_client( $confirmation ) ) {
        echo '<div class="woocommerce-message">Votre demande est bien enregistrée. Une confirmation a été mise en file d’envoi par e-mail.</div>';
    }
    $accord_recent = isset( $_GET['deprat_accepte'] ) ? wc_get_order( absint( $_GET['deprat_accepte'] ) ) : false;
    if ( deprat_devis_est_au_client( $accord_recent ) && $accord_recent->get_meta( '_deprat_devis_accepte_le', true ) ) {
        echo '<div class="woocommerce-message">Votre accord a bien été enregistré. Le service ADV a été informé.</div>';
    }
    $revision_ok = isset( $_GET['deprat_revision_ok'] ) ? wc_get_order( absint( $_GET['deprat_revision_ok'] ) ) : false;
    if ( deprat_devis_est_au_client( $revision_ok ) ) {
        echo '<div class="woocommerce-message">Vos ajustements sont enregistrés. Le service ADV vous transmettra un nouveau devis.</div>';
    }
    $revision_erreur = isset( $_GET['deprat_revision_erreur'] ) ? wc_get_order( absint( $_GET['deprat_revision_erreur'] ) ) : false;
    if ( deprat_devis_est_au_client( $revision_erreur ) ) {
        echo '<div class="woocommerce-error">Précisez vos modifications dans le champ prévu (au moins 5 caractères).</div>';
    }

    $page = isset( $_GET['deprat_page'] ) ? max( 1, absint( $_GET['deprat_page'] ) ) : 1;
    $recherche = isset( $_GET['deprat_q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['deprat_q'] ) ) ) : '';
    $recherche = substr( $recherche, 0, 120 );
    $statuts = array( 'dpr-recu', 'dpr-encours', 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' );
    $statut = isset( $_GET['deprat_statut'] ) ? sanitize_key( wp_unslash( $_GET['deprat_statut'] ) ) : '';
    if ( ! in_array( $statut, $statuts, true ) ) { $statut = ''; }
    $periodes = array(
        '30j' => '30 derniers jours',
        '3mois' => '3 derniers mois',
        'annee' => 'Cette année',
    );
    $periode = isset( $_GET['deprat_periode'] ) ? sanitize_key( wp_unslash( $_GET['deprat_periode'] ) ) : '';
    if ( ! isset( $periodes[ $periode ] ) ) { $periode = ''; }
    $par_page = 15;
    $args = array(
        'customer_id' => get_current_user_id(),
        'status' => $statut ? array( $statut ) : $statuts,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    if ( $periode ) {
        $maintenant = current_datetime();
        if ( '30j' === $periode ) {
            $debut = $maintenant->modify( '-30 days' );
        } elseif ( '3mois' === $periode ) {
            $debut = $maintenant->modify( '-3 months' );
        } else {
            $debut = $maintenant->setDate( (int) $maintenant->format( 'Y' ), 1, 1 );
        }
        $args['date_created'] = '>=' . $debut->format( 'Y-m-d' );
    }

    // Les demandes actives précèdent les demandes facturées ou terminées,
    // y compris lorsque les résultats occupent plusieurs pages.
    $groupes_statuts = array(
        array_values( array_intersect( $args['status'], array( 'dpr-recu', 'dpr-encours', 'dpr-envoye', 'dpr-modifie', 'dpr-accepte' ) ) ),
        array_values( array_intersect( $args['status'], array( 'dpr-facture', 'dpr-termine' ) ) ),
    );
    $commandes = array();
    $total = 0;
    if ( '' === $recherche ) {
        $a_sauter = ( $page - 1 ) * $par_page;
        $places = $par_page;
        foreach ( $groupes_statuts as $groupe ) {
            if ( ! $groupe ) { continue; }
            $args_groupe = array_merge( $args, array( 'status' => $groupe ) );
            $resultats = wc_get_orders( array_merge( $args_groupe, array( 'limit' => 1, 'page' => 1, 'paginate' => true ) ) );
            $nombre_groupe = (int) $resultats->total;
            $total += $nombre_groupe;
            if ( $a_sauter >= $nombre_groupe ) {
                $a_sauter -= $nombre_groupe;
                continue;
            }
            if ( $places > 0 ) {
                $nombre_a_charger = min( $places, $nombre_groupe - $a_sauter );
                $lot = wc_get_orders( array_merge( $args_groupe, array( 'limit' => $nombre_a_charger, 'offset' => $a_sauter ) ) );
                $commandes = array_merge( $commandes, $lot );
                $places -= count( $lot );
            }
            $a_sauter = 0;
        }
    } else {
        // Les recherches parcourent les produits dans chaque groupe.
        $correspondances = array();
        foreach ( $groupes_statuts as $groupe ) {
            if ( ! $groupe ) { continue; }
            $args_groupe = array_merge( $args, array( 'status' => $groupe ) );
            for ( $numero_lot = 1; ; $numero_lot++ ) {
                $lot = wc_get_orders( array_merge( $args_groupe, array( 'limit' => 100, 'page' => $numero_lot ) ) );
                foreach ( $lot as $item ) {
                    if ( deprat_devis_est_au_client( $item ) && deprat_historique_correspond_v7( $item, $recherche ) ) {
                        $correspondances[] = $item;
                    }
                }
                if ( count( $lot ) < 100 ) { break; }
            }
        }
        $total = count( $correspondances );
        $commandes = array_slice( $correspondances, ( $page - 1 ) * $par_page, $par_page );
    }
    $pages = (int) ceil( $total / $par_page );

    echo '<section class="deprat-historique deprat-historique-v7">';
    echo '<div class="deprat-historique-header"><h2>Mes demandes de devis</h2><p>Retrouvez vos devis, factures et anciennes demandes.</p></div>';
    echo '<form method="get" action="' . esc_url( deprat_lien_historique() ) . '" class="deprat-recherche-form" role="search">';
    $url_query = (string) wp_parse_url( deprat_lien_historique(), PHP_URL_QUERY );
    if ( $url_query ) {
        wp_parse_str( $url_query, $parametres_page );
        foreach ( $parametres_page as $nom_param => $valeur_param ) {
            if ( is_scalar( $valeur_param ) && ! in_array( $nom_param, array( 'deprat_q', 'deprat_page', 'deprat_statut', 'deprat_periode' ), true ) ) {
                echo '<input type="hidden" name="' . esc_attr( $nom_param ) . '" value="' . esc_attr( $valeur_param ) . '">';
            }
        }
    }
    echo '<div class="deprat-recherche-champs"><label class="screen-reader-text" for="deprat-q">Rechercher dans mes demandes</label>';
    echo '<input id="deprat-q" type="search" name="deprat_q" value="' . esc_attr( $recherche ) . '" placeholder="N° de demande, date, référence, désignation…">';
    echo '<button type="submit" class="deprat-rechercher">Rechercher</button></div>';
    echo '<div class="deprat-filtres"><div class="deprat-filtre"><label for="deprat-statut">Statut :</label><select id="deprat-statut" name="deprat_statut"><option value="">Tous les statuts</option>';
    foreach ( $statuts as $option ) {
        // L'attribut sélectionné est une constante, jamais du texte de requête.
        $selected_attr = $statut === $option ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr( $option ) . '"' . $selected_attr . '>' . esc_html( deprat_statut_client_v7( $option ) ) . '</option>';
    }
    echo '</select></div><div class="deprat-filtre deprat-filtre-periode"><label for="deprat-periode">Période :</label><span class="deprat-periode-champ"><span class="deprat-periode-icone" aria-hidden="true"></span><select id="deprat-periode" name="deprat_periode"><option value="">Toutes les dates</option>';
    foreach ( $periodes as $valeur => $libelle ) {
        $selected_attr = $periode === $valeur ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr( $valeur ) . '"' . $selected_attr . '>' . esc_html( $libelle ) . '</option>';
    }
    echo '</select></span></div></div></form>';

    echo '<div class="deprat-historique-resultats-v72" aria-live="polite">';
    echo '<p class="deprat-resultats-count">' . esc_html( $total ) . ' demande' . ( 1 === $total ? '' : 's' ) . '</p>';
    if ( ! $commandes ) {
        echo '<p class="deprat-aucun-resultat">Aucune demande ne correspond à votre recherche.</p></div></section>';
        return ob_get_clean();
    }
    echo '<div class="deprat-demandes-liste">';
    $fenetres_details = array();
    $titre_terminees_affiche = false;
    foreach ( $commandes as $commande ) {
        if ( ! deprat_devis_est_au_client( $commande ) ) { continue; }
        $id = $commande->get_id();
        $etat = $commande->get_status();
        if ( ! $titre_terminees_affiche && in_array( $etat, array( 'dpr-facture', 'dpr-termine' ), true ) ) {
            echo '<h3 class="deprat-groupe-titre">Demandes terminées</h3>';
            $titre_terminees_affiche = true;
        }
        $date = $commande->get_date_created();
        $date_affichee = $date ? wc_format_datetime( $date, 'd/m/Y' ) : '—';
        $commentaire = (string) $commande->get_meta( '_deprat_commentaire', true );
        $lignes_produits = array();
        $nombre_pieces = 0;
        foreach ( $commande->get_items( 'line_item' ) as $article ) {
            $reference = (string) $article->get_meta( '_deprat_reference', true );
            if ( ! $reference ) {
                $produit = $article->get_product();
                $reference = $produit && $produit->get_sku() ? (string) $produit->get_sku() : '';
            }
            $details = array();
            $conditionnement = '';
            foreach ( $article->get_formatted_meta_data() as $meta ) {
                $libelle_meta = wp_strip_all_tags( $meta->display_key );
                $valeur_meta = wp_strip_all_tags( $meta->display_value );
                if ( false !== strpos( deprat_recherche_normaliser_v7( $libelle_meta ), 'conditionnement' ) ) {
                    $conditionnement = $valeur_meta;
                } else {
                    $details[] = $libelle_meta . ' : ' . $valeur_meta;
                }
            }
            $quantite = $article->get_quantity();
            $nombre_pieces += $quantite;
            $lignes_produits[] = array(
                'reference' => $reference,
                'designation' => (string) $article->get_name(),
                'quantite' => $quantite,
                'conditionnement' => $conditionnement,
                'details' => implode( ' · ', $details ),
            );
        }
        $nb_lignes = count( $lignes_produits );
        $nb_cachees = max( 0, $nb_lignes - 3 );
        $dialogue_id = 'deprat-details-' . $id;
        $titre_id = 'deprat-details-titre-' . $id;
        $pdf_devis = $commande->get_meta( '_deprat_pdf_fichier', true );
        $pdf_facture = $commande->get_meta( '_deprat_facture_pdf_fichier', true );
        $accord = (string) $commande->get_meta( '_deprat_devis_accepte_le', true );
        $facture_date = (string) $commande->get_meta( '_deprat_facture_envoyee_le', true );
        $facture_disponible = $pdf_facture && in_array( $etat, array( 'dpr-facture', 'dpr-termine' ), true );

        if ( $facture_disponible ) {
            echo '<details class="deprat-demande-repliee"><summary class="deprat-demande-resume">';
            echo '<span class="deprat-resume-numero">Demande #' . esc_html( $commande->get_order_number() ) . '</span>';
            echo '<span class="deprat-resume-date">' . esc_html( $date_affichee ) . '</span>';
            echo '<span class="deprat-etat deprat-etat-dpr-facture">✓ Facture disponible</span>';
            echo '<span class="deprat-resume-action"><span class="deprat-resume-ouvrir">Voir la demande</span><span class="deprat-resume-reduire">Réduire</span> <span class="deprat-resume-chevron" aria-hidden="true">⌄</span></span>';
            echo '</summary>';
        }
        echo '<article class="deprat-demande-card" aria-label="Demande numéro ' . esc_attr( $commande->get_order_number() ) . '">';
        echo '<div class="deprat-carte-colonne deprat-carte-demande"><h3>Demande</h3><div class="deprat-carte-identite">';
        echo '<strong class="deprat-numero">#' . esc_html( $commande->get_order_number() ) . '</strong>';
        echo '<span>' . esc_html( $date_affichee ) . '</span><span><strong>' . esc_html( $nb_lignes ) . ' référence' . ( 1 === $nb_lignes ? '' : 's' ) . '</strong><br>' . 'Quantité totale : ' . esc_html( $nombre_pieces ) . '</span></div></div>';

        echo '<div class="deprat-carte-colonne deprat-carte-produits"><h3>Produits</h3>';
        if ( $nb_lignes ) {
            echo '<div class="deprat-mini-table-wrap"><table class="deprat-mini-produits"><thead><tr><th>Réf.</th><th>Désignation</th><th>Qté</th></tr></thead><tbody>';
            foreach ( array_slice( $lignes_produits, 0, 3 ) as $ligne ) {
                echo '<tr><td>' . esc_html( $ligne['reference'] ?: '—' ) . '</td><td>' . esc_html( $ligne['designation'] ) . '</td><td>' . esc_html( $ligne['quantite'] ) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            if ( $nb_cachees ) {
                echo '<p class="deprat-produits-suite">+ ' . esc_html( $nb_cachees ) . ' autre' . ( 1 === $nb_cachees ? '' : 's' ) . ' référence' . ( 1 === $nb_cachees ? '' : 's' ) . '</p>';
            }
        } else {
            echo '<p class="deprat-texte-secondaire">Aucun produit enregistré</p>';
        }
        echo '<button type="button" class="deprat-ouvrir-details" data-deprat-open="' . esc_attr( $dialogue_id ) . '" aria-haspopup="dialog" aria-controls="' . esc_attr( $dialogue_id ) . '">Plus de détails <span aria-hidden="true">›</span></button></div>';

        echo '<div class="deprat-carte-colonne deprat-carte-commentaire"><h3>Commentaire</h3>';
        echo '<div class="deprat-commentaire-carte">' . ( '' !== $commentaire ? nl2br( esc_html( $commentaire ) ) : '<span class="deprat-texte-secondaire">Aucun commentaire</span>' ) . '</div></div>';

        echo '<div class="deprat-carte-colonne deprat-carte-devis"><h3>Devis</h3>';
        echo '<span class="deprat-etat deprat-etat-' . esc_attr( $etat ) . '">' . esc_html( deprat_statut_client_v7( $etat ) ) . '</span>';
        if ( $pdf_devis && in_array( $etat, array( 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' ), true ) ) {
            // Défense en profondeur : seul du HTML autorisé sort de ce lien.
            echo wp_kses_post( '<p><a class="deprat-lien-document" href="' . esc_url( deprat_lien_document_v7( $commande, 'devis' ) ) . '" target="_blank" rel="noopener noreferrer">Consulter le devis</a></p>' );
        }
        if ( in_array( $etat, array( 'dpr-envoye', 'dpr-modifie' ), true ) && $pdf_devis && ! $accord ) {
            $version = (string) $pdf_devis;
            echo '<div class="deprat-choix-devis-v73">';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="deprat-form-acceptation">';
            echo '<input type="hidden" name="action" value="deprat_accepter_devis">';
            echo '<input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
            echo '<input type="hidden" name="deprat_version" value="' . esc_attr( $version ) . '">';
            wp_nonce_field( 'deprat_accepter_devis_' . $id . '_' . $version, 'deprat_nonce_accord' );
            echo '<label class="deprat-accord-label"><input type="checkbox" name="deprat_accord" value="oui" required> J’ai consulté le devis PDF et je confirme mon accord.</label>';
            echo '<button type="submit" class="deprat-btn-valider-v73" onclick="return confirm(\'Confirmer définitivement votre acceptation du devis ?\');">✓ Accepter le devis</button></form>';
            echo '<details class="deprat-revision-client"><summary>Demander un ajustement</summary>';
            echo '<form method="post" action="' . esc_url( deprat_lien_historique() ) . '" class="deprat-revision-form">';
            echo '<input type="hidden" name="deprat_demander_modification" value="' . esc_attr( $id ) . '">';
            echo '<input type="hidden" name="deprat_version" value="' . esc_attr( $version ) . '">';
            wp_nonce_field( 'deprat_demander_modification_' . $id . '_' . $version, 'deprat_revision_nonce' );
            echo '<label for="deprat-revision-texte-' . esc_attr( $id ) . '">Quels ajustements souhaitez-vous ?</label>';
            echo '<textarea id="deprat-revision-texte-' . esc_attr( $id ) . '" rows="3" maxlength="2000" minlength="5" name="deprat_ajustements" required placeholder="Précisez les références ou les quantités à modifier…"></textarea>';
            echo '<button type="submit" class="deprat-revision-envoyer" onclick="return confirm(\'Envoyer votre demande de modification au service ADV ? Le devis actuel ne pourra plus être accepté.\');">Envoyer mes ajustements</button></form></details></div>';
        }
        if ( $accord ) {
            echo '<p class="deprat-accord-ok">✓ Devis accepté<br><small>Le ' . esc_html( deprat_date_utc_v7( $accord ) ) . '</small></p>';
        } elseif ( ! $pdf_devis ) {
            echo '<p class="deprat-texte-secondaire">En attente du devis</p>';
        }
        echo '</div>';

        echo '<div class="deprat-carte-colonne deprat-carte-facture"><h3>Facture</h3>';
        if ( $facture_disponible ) {
            echo '<span class="deprat-etat deprat-etat-dpr-facture">✓ Facture disponible</span>';
            echo wp_kses_post( '<p><a class="deprat-lien-document" href="' . esc_url( deprat_lien_document_v7( $commande, 'facture' ) ) . '" target="_blank" rel="noopener noreferrer">Consulter la facture</a></p>' );
            if ( $facture_date ) { echo '<p class="deprat-texte-secondaire">Envoyée le ' . esc_html( deprat_date_utc_v7( $facture_date ) ) . '</p>'; }
            echo '<p class="deprat-paiement-v7">' . esc_html( deprat_mention_paiement_v7() ) . '</p>';
        } else {
            echo '<div class="deprat-facture-attente"><span class="deprat-facture-info" aria-hidden="true">i</span><span>' . ( $accord ? 'Facture en préparation' : 'Disponible après validation du devis' ) . '</span></div>';
        }
        echo '</div>';

        echo '<div class="deprat-carte-colonne deprat-carte-refaire"><h3>Refaire</h3>';
        echo '<form method="post" action="' . esc_url( wc_get_cart_url() ) . '"><input type="hidden" name="deprat_refaire_demande" value="' . esc_attr( $id ) . '">';
        wp_nonce_field( 'deprat_refaire_' . $id, 'deprat_refaire_nonce' );
        echo '<button type="submit" class="deprat-refaire-icone" title="Refaire cette demande" aria-label="Refaire la demande numéro ' . esc_attr( $commande->get_order_number() ) . '">↻</button></form></div></article>';
        if ( $facture_disponible ) {
            echo '</details>';
        }

        $detail = '<dialog id="' . esc_attr( $dialogue_id ) . '" class="deprat-dialogue-produits" aria-labelledby="' . esc_attr( $titre_id ) . '">';
        $detail .= '<div class="deprat-dialogue-entete"><h3 id="' . esc_attr( $titre_id ) . '">Détail de la demande #' . esc_html( $commande->get_order_number() ) . '</h3><button type="button" class="deprat-dialogue-fermer" data-deprat-close aria-label="Fermer la fenêtre">×</button></div>';
        $detail .= '<div class="deprat-dialogue-interieur"><div class="deprat-dialogue-meta"><span>' . esc_html( $date_affichee ) . '</span><span>' . esc_html( $nb_lignes ) . ' référence' . ( 1 === $nb_lignes ? '' : 's' ) . ' · ' . 'Quantité totale : ' . esc_html( $nombre_pieces ) . '</span></div>';
        if ( '' !== $commentaire ) {
            $detail .= '<p class="deprat-dialogue-commentaire"><strong>Commentaire</strong> ' . nl2br( esc_html( $commentaire ) ) . '</p>';
        }
        $detail .= '<div class="deprat-dialogue-table-wrap"><table class="deprat-dialogue-table"><thead><tr><th>Réf.</th><th>Désignation</th><th>Qté</th><th>Conditionnement</th><th>Détails</th></tr></thead><tbody>';
        foreach ( $lignes_produits as $ligne ) {
            $detail .= '<tr><td>' . esc_html( $ligne['reference'] ?: '—' ) . '</td><td>' . esc_html( $ligne['designation'] ) . '</td><td>' . esc_html( $ligne['quantite'] ) . '</td><td>' . esc_html( $ligne['conditionnement'] ?: '—' ) . '</td><td>' . esc_html( $ligne['details'] ?: '—' ) . '</td></tr>';
        }
        $detail .= '</tbody></table></div></div><div class="deprat-dialogue-pied"><button type="button" class="deprat-dialogue-bouton-fermer" data-deprat-close>Fermer</button></div></dialog>';
        $fenetres_details[] = $detail;
    }
    echo '</div>';
    foreach ( $fenetres_details as $fenetre ) { echo $fenetre; }
    if ( $pages > 1 ) {
        echo '<nav aria-label="Pages des demandes" class="deprat-pages">';
        for ( $i = 1; $i <= $pages; $i++ ) {
            $url = add_query_arg( array( 'deprat_page' => $i, 'deprat_q' => $recherche, 'deprat_statut' => $statut, 'deprat_periode' => $periode ), deprat_lien_historique() );
            // Les seules portions variables sont échappées (esc_url, esc_html)
            // ou issues de constantes. KSES revalide le balisage final.
            echo wp_kses_post( '<a class="' . ( $i === $page ? 'deprat-page-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $i === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( $i ) . '</a>' );
        }
        echo '</nav>';
    }
    echo '</div></section>';
    return ob_get_clean();
}

// Recherche AJAX sans chargement de page (fallback formulaire GET conservé).
add_action( 'wp_ajax_deprat_recherche_historique_v72', function () {
    if ( ! is_user_logged_in() || ! check_ajax_referer( 'deprat_recherche_historique_v72', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Session expirée.' ), 403 );
    }
    $_GET['deprat_q'] = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $_GET['deprat_page'] = 1;
    $_GET['deprat_statut'] = isset( $_POST['statut'] ) ? sanitize_key( wp_unslash( $_POST['statut'] ) ) : '';
    $_GET['deprat_periode'] = isset( $_POST['periode'] ) ? sanitize_key( wp_unslash( $_POST['periode'] ) ) : '';
    $html = deprat_html_historique();
    wp_send_json_success( array( 'html' => $html ) );
} );

// Fenetres modales natives (clavier/Echap et verrouillage du focus pris en charge par <dialog>).
// Le JS n'est charge que sur les pages affichant vraiment l'historique.
add_action( 'wp_footer', function () {
    if ( empty( $GLOBALS['deprat_historique_modal_v71_visible'] ) ) { return; }
    $script_ajax_url = wp_json_encode( admin_url( 'admin-ajax.php' ) );
    $script_nonce = wp_json_encode( wp_create_nonce( 'deprat_recherche_historique_v72' ) );
    $script = <<<'DEPRAT_V71_JS'
<script id="deprat-historique-v71-modal-js">
(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    var cible = event.target;
    if (!(cible instanceof Element)) return;
    var ouvrir = cible.closest('.deprat-historique-v7 [data-deprat-open]');
    if (ouvrir) {
      event.preventDefault();
      var id = ouvrir.getAttribute('data-deprat-open');
      var dialogue = id ? document.getElementById(id) : null;
      if (!dialogue) return;
      if (typeof dialogue.showModal === 'function') {
        if (!dialogue.open) dialogue.showModal();
      } else {
        dialogue.setAttribute('open', '');
      }
      return;
    }
    var fermer = cible.closest('.deprat-historique-v7 [data-deprat-close]');
    if (fermer) {
      event.preventDefault();
      var modal = fermer.closest('dialog');
      if (!modal) return;
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
    }
  });
  // Recherche instantanée complète par numéro, date, référence et désignation.
  var rechercheTimer = null;
  var rechercheSequence = 0;
  document.addEventListener('input', function(event) {
    var champ = event.target;
    if (!(champ instanceof Element) || !champ.matches('.deprat-historique-v7 #deprat-q')) return;
    clearTimeout(rechercheTimer);
    var valeur = champ.value;
    var seq = ++rechercheSequence;
    rechercheTimer = setTimeout(function() {
      var racine = champ.closest('.deprat-historique-v7');
      if (!racine) return;
      var resultat = racine.querySelector('.deprat-historique-resultats-v72');
      if (!resultat) return;
      racine.classList.add('deprat-recherche-en-cours');
      var params = new URLSearchParams();
      params.append('action', 'deprat_recherche_historique_v72');
      params.append('nonce', __DEPRAT_SEARCH_NONCE__);
      params.append('q', valeur);
      var statut = racine.querySelector('#deprat-statut');
      var periode = racine.querySelector('#deprat-periode');
      params.append('statut', statut ? statut.value : '');
      params.append('periode', periode ? periode.value : '');
      fetch(__DEPRAT_AJAX_URL__, {method:'POST', credentials:'same-origin', body:params})
        .then(function(rep){ if(!rep.ok) throw new Error('Erreur de connexion.'); return rep.json(); })
        .then(function(data){
          if (seq !== rechercheSequence || !data.success) return;
          var html = new DOMParser().parseFromString(data.data.html, 'text/html');
          var nouveau = html.querySelector('.deprat-historique-resultats-v72');
          if (!nouveau || !resultat.isConnected) return;
          resultat.replaceWith(nouveau);
          var paramsUrl = new URL(window.location.href);
          if (valeur) paramsUrl.searchParams.set('deprat_q', valeur);
          else paramsUrl.searchParams.delete('deprat_q');
          if (statut && statut.value) paramsUrl.searchParams.set('deprat_statut', statut.value);
          else paramsUrl.searchParams.delete('deprat_statut');
          if (periode && periode.value) paramsUrl.searchParams.set('deprat_periode', periode.value);
          else paramsUrl.searchParams.delete('deprat_periode');
          paramsUrl.searchParams.delete('deprat_page');
          history.replaceState(null, '', paramsUrl.toString());
        })
        .catch(function(){ /* Le bouton Rechercher fournit le fonctionnement de secours. */ })
        .finally(function(){ if (seq === rechercheSequence) racine.classList.remove('deprat-recherche-en-cours'); });
    }, 320);
  });
  document.addEventListener('change', function (event) {
    if (!(event.target instanceof Element) || !event.target.matches('.deprat-historique-v7 select')) return;
    var form = event.target.closest('form');
    if (form && typeof form.requestSubmit === 'function') form.requestSubmit();
  });
  // Clic sur le fond sombre : ferme le dialogue (Echap est gere par le navigateur).
  document.addEventListener('click', function (event) {
    var cible = event.target;
    if (!(cible instanceof Element) || cible.tagName !== 'DIALOG' || !cible.classList.contains('deprat-dialogue-produits')) return;
    var rect = cible.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
      if (typeof cible.close === 'function') cible.close();
      else cible.removeAttribute('open');
    }
  });
}());
</script>
DEPRAT_V71_JS;
    echo str_replace( array( '__DEPRAT_SEARCH_NONCE__', '__DEPRAT_AJAX_URL__' ),
        array( $script_nonce, $script_ajax_url ), $script );
}, 25 );

// Retirer les balises de code autour du shortcode avant son exécution.
add_filter( 'the_content', function ( $contenu ) {
    if ( false === strpos( $contenu, '[deprat_mes_demandes]' ) ) {
        return $contenu;
    }
    $motifs = array(
        '~<p(?:\s[^>]*)?>\s*<code(?:\s[^>]*)?>\s*(\[deprat_mes_demandes\])\s*</code>\s*</p>~i',
        '~<code(?:\s[^>]*)?>\s*(\[deprat_mes_demandes\])\s*</code>~i',
    );
    return preg_replace( $motifs, '$1', $contenu );
}, 10 );

// Utilisation dans une page WordPress autonome : [deprat_mes_demandes].
add_shortcode( 'deprat_mes_demandes', 'deprat_html_historique' );

// Si la page « Mon compte » WooCommerce utilise vraiment un endpoint natif, il fonctionne aussi.
add_action( 'woocommerce_account_mes-demandes_endpoint', function () {
    echo deprat_html_historique(); // La fonction échappe chaque donnée utilisateur.
} );
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
    unset( $items['orders'] );
    $nouveau = array();
    foreach ( $items as $cle => $titre ) {
        if ( 'customer-logout' === $cle ) {
            $nouveau['mes-demandes'] = 'Mes demandes de devis';
        }
        $nouveau[ $cle ] = $titre;
    }
    return $nouveau;
} );

// 9. Afficher le commentaire dans WooCommerce > Commandes pour le service ADV.
add_action( 'woocommerce_admin_order_data_after_order_details', function ( $commande ) {
    if ( ! $commande instanceof WC_Order || 'oui' !== $commande->get_meta( '_deprat_demande', true ) ) {
        return;
    }
    echo '<p><strong>DEPRAT - Demande de devis</strong></p>';
    echo '<p><strong>Commentaire du client :</strong><br>';
    $commentaire = $commande->get_meta( '_deprat_commentaire', true );
    echo $commentaire ? nl2br( esc_html( $commentaire ) ) : 'Aucun commentaire';
    echo '</p>';
} );


// Actualiser les quantités du panier classique dès leur modification.
add_action( 'wp_enqueue_scripts', static function () {
    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return;
    }
    wp_enqueue_script( 'wc-cart' );
    wp_add_inline_script( 'wc-cart', <<<'JS'
jQuery(function ($) {
    const updateSelector = '.woocommerce-cart-form button[name="update_cart"]';
    function hideUpdateButton() {
        $(updateSelector).hide();
    }
    hideUpdateButton();
    function unlockRequest() {
        $('.deprat-cart-saving').prop('disabled', false).removeClass('deprat-cart-saving');
    }
    $(document.body).on('updated_wc_div', function () {
        hideUpdateButton();
        unlockRequest();
    });
    $(document).on('change', '.woocommerce-cart-form input.qty', function () {
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }
        $('[name="deprat_envoyer"]').closest('form').find(':submit:enabled')
            .addClass('deprat-cart-saving').prop('disabled', true);
        $(this).closest('form').find('button[name="update_cart"]')
            .prop('disabled', false).trigger('click');
    });
    // Laisser la possibilité de réessayer si la requête échoue.
    $(document).ajaxError(function () {
        unlockRequest();
        $(updateSelector).show().prop('disabled', false);
    });
});
JS
    );
}, 20 );
