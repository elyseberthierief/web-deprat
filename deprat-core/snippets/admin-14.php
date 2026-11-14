<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT — Interface EDITE COMMANDE en 3 colonnes v1.4 (date et heure dans le bandeau), DANS « Modifier commande » WooCommerce.
 * Nouveau snippet indépendant, administration uniquement, SANS balise <?php.
 * Nécessite le snippet DEPRAT — Gestion des demandes V7.5 ACTIF.
 * IMPORTANT : désactiver l'ancien snippet « DEPRAT – Tableau de bord ADV »
 * (conserver le code en sauvegarde), pour éviter deux interfaces superposées.
 * Fonctionne sur une demande DEPRAT existante, HPOS ou stockage classique.
 * Les PDF et statuts sont gérés par la V7.5 : ce code ne les réécrit pas.
 */

if ( ! function_exists( 'deprat_opui_order' ) ) {
    function deprat_opui_order() {
        if ( ! is_admin() || ! function_exists( 'wc_get_order' ) || ! class_exists( 'WC_Order' ) ) {
            return false;
        }
        $id = 0;
        if ( isset( $_GET['page'], $_GET['action'], $_GET['id'] ) &&
             'wc-orders' === sanitize_key( wp_unslash( $_GET['page'] ) ) &&
             'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
            $id = absint( $_GET['id'] );
        } elseif ( isset( $_GET['post'], $_GET['action'] ) &&
                   'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
            $id = absint( $_GET['post'] );
        }
        if ( ! $id || ( ! current_user_can( 'manage_woocommerce' ) &&
                        ! current_user_can( 'edit_shop_orders' ) &&
                        ! current_user_can( 'edit_shop_order', $id ) ) ) {
            return false;
        }
        $order = wc_get_order( $id );
        return $order instanceof WC_Order && 'oui' === $order->get_meta( '_deprat_demande', true ) ? $order : false;
    }
}

if ( ! function_exists( 'deprat_opui_icon' ) ) {
    function deprat_opui_icon( $name ) {
        $paths = array(
            'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
            'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'box'      => '<path d="m12 3 9 5-9 5-9-5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
            'message'  => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/>',
            'settings' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-1.8 1.8-.06-.06A1.65 1.65 0 0 0 16.1 18a1.65 1.65 0 0 0-1 .5l-.06.07h-2.5l-.03-.08A1.65 1.65 0 0 0 11 18a1.65 1.65 0 0 0-1.65.3l-.08.07-1.8-1.8.07-.08A1.65 1.65 0 0 0 7.3 15a1.65 1.65 0 0 0-.5-1l-.07-.06v-2.5l.07-.06a1.65 1.65 0 0 0 .5-1 1.65 1.65 0 0 0-.3-1.65l-.07-.08 1.8-1.8.08.07A1.65 1.65 0 0 0 10.5 7a1.65 1.65 0 0 0 1-.5l.06-.07h2.5l.06.07a1.65 1.65 0 0 0 1 .5 1.65 1.65 0 0 0 1.65-.3l.08-.07 1.8 1.8-.07.08A1.65 1.65 0 0 0 18.3 10a1.65 1.65 0 0 0 .5 1l.07.06v2.5l-.07.06a1.65 1.65 0 0 0-.5 1z"/>',
            'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l4 2"/>',
            'check'    => '<path d="M20 6 9 17l-5-5"/>',
            'upload'   => '<path d="M12 16V3m0 0-5 5m5-5 5 5M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
            'send'     => '<path d="m22 2-7 20-4-9-9-4zM22 2 11 13"/>',
            'save'     => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2zM17 21v-8H7v8M7 3v5h8"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'user'     => '<path d="M20 21v-2a6 6 0 0 0-6-6H10a6 6 0 0 0-6 6v2"/><circle cx="12" cy="7" r="4"/>',
            'arrow'    => '<path d="m15 18-6-6 6-6"/>',
            'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.7L7 10a16 16 0 0 0 7 7l1.6-2a2 2 0 0 1 1.7-.6l3 .5a2 2 0 0 1 1.7 2z"/>',
        );
        $path = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['file'];
        return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }
}

/** AJAX : ajouter une note privée à la demande. Le bouton de la vue est facultatif. */
add_action( 'wp_ajax_deprat_opui_note', function () {
    if ( ! function_exists( 'wc_get_order' ) ) {
        wp_send_json_error( array( 'message' => 'WooCommerce indisponible.' ), 503 );
    }
    $id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    check_ajax_referer( 'deprat_opui_note_' . $id, 'security' );
    if ( ! $id || ( ! current_user_can( 'manage_woocommerce' ) &&
                    ! current_user_can( 'edit_shop_orders' ) &&
                    ! current_user_can( 'edit_shop_order', $id ) ) ) {
        wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }
    $order = wc_get_order( $id );
    if ( ! $order instanceof WC_Order || 'oui' !== $order->get_meta( '_deprat_demande', true ) ) {
        wp_send_json_error( array( 'message' => 'Demande introuvable.' ), 404 );
    }
    $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
    if ( ! $note || strlen( $note ) > 3000 ) {
        wp_send_json_error( array( 'message' => 'Renseignez une note de moins de 3 000 caractères.' ), 400 );
    }
    $order->add_order_note( $note, 0, true );
    wp_send_json_success( array( 'message' => 'Note enregistrée dans l’historique.' ) );
} );

/** Bloquer le retrait de notes DEPRAT dans l'interface standard. */
add_action( 'wp_ajax_woocommerce_delete_order_note', function () {
    $id_note = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
    $note = $id_note ? get_comment( $id_note ) : null;
    if ( ! $note || 'order_note' !== $note->comment_type ) { return; }
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $note->comment_post_ID ) : false;
    if ( $order instanceof WC_Order && 'oui' === $order->get_meta( '_deprat_demande', true ) ) {
        wp_die( 'Suppression des événements désactivée pour les demandes DEPRAT.', 'Historique conservé', array( 'response' => 403 ) );
    }
}, 0 );

add_action( 'admin_footer', function () {
    $order = deprat_opui_order();
    if ( ! $order ) { return; }

    $id = $order->get_id();
    $date = $order->get_date_created();
    $date_label = $date ? wp_date( 'd F Y à H:i', $date->getTimestamp(), wp_timezone() ) : 'Date inconnue';
    $status = $order->get_status();
    $status_name = function_exists( 'deprat_statut_client_v7' ) ? deprat_statut_client_v7( $status ) : wc_get_order_status_name( $status );
    $has_quote = (bool) $order->get_meta( '_deprat_pdf_fichier', true );
    $has_invoice = (bool) $order->get_meta( '_deprat_facture_pdf_fichier', true );
    $accepted = (bool) $order->get_meta( '_deprat_devis_accepte_le', true );
    $invoiced = (bool) $order->get_meta( '_deprat_facture_envoyee_le', true );
    $adjustment = 'oui' === $order->get_meta( '_deprat_revision_en_attente', true );
    $comment = (string) $order->get_meta( '_deprat_commentaire', true );
    $customer_id = $order->get_customer_id();
    $siret = $customer_id ? (string) get_user_meta( $customer_id, 'deprat_siret', true ) : '';
    $company = $order->get_billing_company() ?: ( $customer_id ? get_user_meta( $customer_id, 'billing_company', true ) : '' );
    $company = $company ?: 'Société non renseignée';
    $full_name = trim( $order->get_formatted_billing_full_name() );
    $full_name = $full_name ?: ( $customer_id && get_userdata( $customer_id ) ? get_userdata( $customer_id )->display_name : 'Contact non renseigné' );
    $phone = $order->get_billing_phone();
    $email = $order->get_billing_email();
    $address = implode( ', ', array_filter( array(
        $order->get_billing_address_1(), $order->get_billing_address_2(),
        trim( $order->get_billing_postcode() . ' ' . $order->get_billing_city() ),
        $order->get_billing_country() ? WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country() : ''
    ) ) );
    $notes = function_exists( 'wc_get_order_notes' ) ? wc_get_order_notes( array(
        'order_id' => $id, 'limit' => 40, 'orderby' => 'date_created', 'order' => 'DESC'
    ) ) : array();
    $back = admin_url( 'admin.php?page=wc-orders' );
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
         ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        $back = admin_url( 'edit.php?post_type=shop_order' );
    }
    $customer_link = $customer_id ? get_edit_user_link( $customer_id ) : '';
    $order_meta = array(
        'order_id' => $id,
        'quote' => $has_quote,
        'invoice' => $has_invoice,
        'accepted' => $accepted,
        'invoiced' => $invoiced,
        'revision_pending' => $adjustment,
        'status' => $status,
        'ajax' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'deprat_opui_note_' . $id ),
    );

    ?>
    <div id="deprat-opui-app" data-order-id="<?php echo esc_attr( $id ); ?>">
      <!-- En-tête métier : logo officiel, numéro et statut issus de la vraie commande -->
      <div class="deprat-opui-brand">
        <div class="deprat-opui-brand-identite">
          <img class="deprat-opui-official-logo"
               src="<?php echo esc_url( home_url( '/wp-content/uploads/2026/11/cropped-Logo-Deprat.png' ) ); ?>"
               alt="DEPRAT" loading="eager" />
          <div class="deprat-opui-brand-intitule">
            <span class="deprat-opui-brand-eyebrow">Créée le <?php echo esc_html( $date_label ); ?></span>
            <h1>Demande n° <?php echo esc_html( $order->get_order_number() ); ?></h1>
          </div>
        </div>
        <span class="deprat-opui-brand-status status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_name ); ?></span>
      </div>
      <div class="deprat-opui-toolbar">
        <div class="deprat-opui-toolbar-title">
          <a href="<?php echo esc_url( $back ); ?>" class="deprat-opui-back"><?php echo deprat_opui_icon( 'arrow' ); ?> Retour aux demandes</a>
        </div>
        <div class="deprat-opui-toolbar-actions">
          <button class="deprat-opui-btn deprat-opui-save" type="button"><?php echo deprat_opui_icon( 'save' ); ?> Enregistrer les modifications</button>
        </div>
      </div>
      <div id="deprat-opui-notice" role="alert" aria-live="polite" hidden></div>
      <div class="deprat-opui-columns">

        <!-- COLONNE 1 : données de la commande (lecture seule) -->
        <section class="deprat-opui-col deprat-opui-col-left">
          <div class="deprat-opui-head deprat-opui-head-primary">
            <div class="deprat-opui-head-row"><h2><?php echo deprat_opui_icon( 'file' ); ?> Récapitulatif de la demande</h2></div>
            
          </div>
          <div class="deprat-opui-card-inner">
            <div class="deprat-opui-subcard">
              <h3><?php echo deprat_opui_icon( 'users' ); ?> Client</h3>
              <strong class="deprat-opui-company"><?php echo esc_html( $company ); ?></strong>
              <span class="deprat-opui-muted"><?php echo esc_html( $full_name ); ?></span>
            </div>
            <div class="deprat-opui-subcard">
              <h3><?php echo deprat_opui_icon( 'box' ); ?> Produits demandés</h3>
              <div class="deprat-opui-table-scroll">
                <table class="deprat-opui-table">
                  <thead><tr><th scope="col">Référence</th><th scope="col">Désignation</th><th scope="col">Qté</th></tr></thead>
                  <tbody>
                    <?php foreach ( $order->get_items( 'line_item' ) as $item ) :
                        $ref = (string) $item->get_meta( '_deprat_reference', true );
                        if ( ! $ref && $item->get_product() ) { $ref = (string) $item->get_product()->get_sku(); }
                    ?>
                    <tr><td><span class="deprat-opui-ref"><?php echo esc_html( $ref ?: '—' ); ?></span></td>
                        <td><?php echo esc_html( $item->get_name() ); ?></td>
                        <td class="deprat-opui-qty"><?php echo esc_html( $item->get_quantity() ); ?></td></tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="deprat-opui-subcard">
              <h3><?php echo deprat_opui_icon( 'message' ); ?> Commentaire du client</h3>
              <div class="deprat-opui-comment"><?php echo $comment ? nl2br( esc_html( $comment ) ) : '<em>Aucun commentaire.</em>'; ?></div>
            </div>
          </div>
        </section>

        <!-- COLONNE 2 : ACTIONS ADV + vrais formulaires V7.3 -->
        <section class="deprat-opui-col deprat-opui-col-mid">
          <div class="deprat-opui-head"><h2><?php echo deprat_opui_icon( 'settings' ); ?> Traitement ADV</h2></div>
          <div class="deprat-opui-card-inner">
            <div class="deprat-opui-status-summary status-<?php echo esc_attr( $status ); ?>">
              <?php echo deprat_opui_icon( 'clock' ); ?>
              <div><strong><?php echo esc_html( $status_name ); ?></strong><span><?php
                $messages = array(
                    'dpr-recu' => 'Action requise : préparer le devis du client.',
                    'dpr-encours' => 'L’ADV prépare ou révise le devis.',
                    'dpr-envoye' => 'En attente de l’accord du client sur le devis.',
                    'dpr-modifie' => 'Devis corrigé envoyé : en attente d’un nouvel accord du client.',
                    'dpr-accepte' => 'Devis accepté : préparer la facture.',
                    'dpr-facture' => 'Facture disponible dans l’espace client.',
                    'dpr-termine' => 'Demande terminée et archivée.',
                );
                echo esc_html( $messages[ $status ] ?? 'Suivi de la demande.' );
              ?></span></div>
            </div>
            <?php if ( $adjustment ) : ?>
              <div class="deprat-opui-alert is-warning"><strong>Modification demandée par le client.</strong><p><?php echo nl2br( esc_html( (string) $order->get_meta( '_deprat_dernier_ajustement_texte', true ) ) ); ?></p></div>
            <?php endif; ?>
            <div class="deprat-opui-field"><label for="order_status">État de traitement de la demande</label><div id="deprat-opui-status-slot"></div></div>

            <div class="deprat-opui-step"><div class="deprat-opui-step-title"><span class="deprat-opui-num">1</span> Devis PDF</div><div id="deprat-opui-devis-slot"></div><p class="deprat-opui-hint">L’enregistrement du PDF transmet automatiquement le devis au client. Le bouton ci-dessous sert uniquement de secours.</p>
              <button type="button" id="deprat-opui-send-quote" class="deprat-opui-btn deprat-opui-btn-blue" <?php disabled( in_array( $status, array( 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' ), true ) ); ?>><?php echo deprat_opui_icon( 'send' ); ?> <?php echo in_array( $status, array( 'dpr-envoye', 'dpr-modifie', 'dpr-accepte', 'dpr-facture', 'dpr-termine' ), true ) ? 'Devis déjà transmis' : ( $adjustment ? 'Envoyer le devis modifié (secours)' : 'Envoyer le devis (secours)' ); ?></button>
            </div>
            <div class="deprat-opui-step"><div class="deprat-opui-step-title"><span class="deprat-opui-num<?php echo $accepted ? '' : ' deprat-opui-num-grey'; ?>">2</span> Facture PDF</div><div id="deprat-opui-facture-slot"></div>
              <button type="button" id="deprat-opui-send-invoice" class="deprat-opui-btn deprat-opui-btn-blue" <?php disabled( ! $accepted ); ?>><?php echo deprat_opui_icon( 'send' ); ?> Envoyer la facture au client</button>
            </div>
            <?php if ( ! $accepted ) : ?>
              <div class="deprat-opui-alert is-danger"><?php echo deprat_opui_icon( 'clock' ); ?> En attente de l’accord du client : la facture ne peut pas être envoyée.</div>
            <?php else : ?>
              <div class="deprat-opui-alert is-success"><?php echo deprat_opui_icon( 'check' ); ?> Devis accepté le <?php echo esc_html( function_exists( 'deprat_date_utc_v7' ) ? deprat_date_utc_v7( $order->get_meta( '_deprat_devis_accepte_le', true ) ) : $order->get_meta( '_deprat_devis_accepte_le', true ) ); ?>.</div>
            <?php endif; ?>
            <div id="deprat-opui-archives-slot"></div>
            <?php if ( $invoiced && 'dpr-termine' !== $status ) : ?><button type="button" class="deprat-opui-btn deprat-opui-btn-outline" id="deprat-opui-finish"><?php echo deprat_opui_icon( 'check' ); ?> Terminer la commande</button><?php endif; ?>
          </div>
        </section>

        <!-- COLONNE 3 : historique + informations client -->
        <div class="deprat-opui-right">
          <section class="deprat-opui-col">
            <div class="deprat-opui-head"><h2><?php echo deprat_opui_icon( 'history' ); ?> Historique de la demande</h2></div>
            <div class="deprat-opui-card-inner">
              <?php if ( $notes ) : ?><ol class="deprat-opui-timeline">
                <?php foreach ( $notes as $note ) :
                  $ndate = $note->date_created instanceof WC_DateTime ? wp_date( 'd/m/Y à H:i', $note->date_created->getTimestamp() ) : '';
                ?>
                  <li><time><?php echo esc_html( $ndate ); ?></time><div><?php echo wp_kses_post( wpautop( $note->content ) ); ?></div></li>
                <?php endforeach; ?>
              </ol><?php else : ?><p class="deprat-opui-muted">Aucun événement enregistré.</p><?php endif; ?>
              <button type="button" id="deprat-opui-add-note" class="deprat-opui-text-link">+ Ajouter une note privée</button>
              <div id="deprat-opui-note-form" hidden>
                <textarea id="deprat-opui-note-text" rows="3" maxlength="3000" placeholder="Information interne pour le service ADV..."></textarea>
                <button type="button" class="deprat-opui-btn deprat-opui-btn-blue" id="deprat-opui-note-save">Enregistrer la note</button>
                <p id="deprat-opui-note-message" role="status"></p>
              </div>
            </div>
          </section>
          <section class="deprat-opui-col">
            <div class="deprat-opui-head"><h2><?php echo deprat_opui_icon( 'user' ); ?> Informations client</h2></div>
            <div class="deprat-opui-card-inner deprat-opui-customer-details">
              <dl><dt>Société</dt><dd><strong><?php echo esc_html( $company ); ?></strong></dd></dl>
              <dl><dt>Contact</dt><dd><?php echo esc_html( $full_name ); ?></dd></dl>
              <dl><dt>Téléphone</dt><dd><?php echo $phone ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '—'; ?></dd></dl>
              <dl><dt>E-mail</dt><dd><?php echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—'; ?></dd></dl>
              <dl><dt>SIRET</dt><dd><?php echo esc_html( $siret ?: '—' ); ?></dd></dl>
              <dl><dt>Adresse</dt><dd><?php echo esc_html( $address ?: '—' ); ?></dd></dl>
              <?php if ( $customer_link ) : ?><a class="deprat-opui-text-link" href="<?php echo esc_url( $customer_link ); ?>">Voir la fiche client</a><?php endif; ?>
            </div>
          </section>
        </div>
      </div>
      <div id="deprat-opui-advanced-wrap" hidden>
        <p><strong>Interface WooCommerce native :</strong> affichage de secours et paramètres avancés uniquement. Les dates de création sont en lecture seule.</p>
        <div id="deprat-opui-native-slot"></div>
      </div>
    </div>
    <script id="deprat-opui-data">window.depratOpuiData = <?php echo wp_json_encode( $order_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;</script>
    <style id="deprat-opui-css">
    #deprat-opui-app{font-family:"Agenda","Agenda Std",Arial,sans-serif;color:#1d2a3d;max-width:none;margin:18px 0 30px;line-height:1.45;box-sizing:border-box}
    #deprat-opui-app *{box-sizing:border-box}#deprat-opui-app svg{height:21px;width:21px;flex:none;vertical-align:middle}
    #deprat-opui-app button,#deprat-opui-app input,#deprat-opui-app select,#deprat-opui-app textarea{font-family:inherit}
    .deprat-opui-brand{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:14px;padding:21px 25px;background:#f5f8fc;border:1px solid #dce5ef;border-radius:13px;color:#1e344b;flex-wrap:wrap}
    .deprat-opui-brand-identite{display:flex;align-items:center;gap:24px;min-width:0;flex:1}
    .deprat-opui-official-logo{width:140px;height:auto;max-height:70px;object-fit:contain;flex:none;display:block}
    .deprat-opui-brand-intitule{padding-left:22px;border-left:1px solid #d5e0ec;min-width:0}
    .deprat-opui-brand-eyebrow{display:block;font-size:15px;font-weight:600;letter-spacing:0;text-transform:none;color:#657a92;margin:0 0 6px}
    #deprat-opui-app .deprat-opui-brand h1{font-family:"Agenda","Agenda Std",Arial,sans-serif;font-size:clamp(26px,2.4vw,38px);font-weight:800;line-height:1.14;color:#104983;margin:0;padding:0;letter-spacing:0}
    .deprat-opui-brand-status{display:inline-flex;align-items:center;justify-content:center;max-width:300px;padding:12px 19px;border-radius:100px;background:#fff1d0;color:#85570a;font-size:16px;font-weight:800;line-height:1.28;text-align:center;box-shadow:inset 0 0 0 1px rgba(134,92,18,.1)}
    .deprat-opui-brand-status.status-dpr-recu{background:#e8f1fc;color:#174f8f}
    .deprat-opui-brand-status.status-dpr-encours{background:#fff1d0;color:#85570a}
    .deprat-opui-brand-status.status-dpr-envoye,.deprat-opui-brand-status.status-dpr-modifie{background:#e6f0ff;color:#125798}
    .deprat-opui-brand-status.status-dpr-accepte,.deprat-opui-brand-status.status-dpr-facture,.deprat-opui-brand-status.status-dpr-termine{background:#e2f5e8;color:#236d3c}
    .deprat-opui-toolbar{display:flex;justify-content:space-between;align-items:center;gap:18px;margin:0 0 22px}
    .deprat-opui-toolbar h1{font-size:26px;font-weight:750;margin:6px 0 0;line-height:1.2;color:#1d2a3d}
    .deprat-opui-back{display:inline-flex;align-items:center;gap:3px;text-decoration:none;color:#214d80;font-size:14px;font-weight:600}
    .deprat-opui-back svg{width:17px;height:17px}.deprat-opui-columns{display:grid;grid-template-columns:minmax(0,1.16fr) minmax(0,1fr) minmax(0,1fr);gap:20px;align-items:start}
    .deprat-opui-col{background:#fff;border:1px solid #dce5ef;border-radius:12px;overflow:hidden;min-width:0;box-shadow:0 3px 16px rgba(22,56,100,.045)}
    .deprat-opui-right{display:grid;gap:18px;min-width:0}.deprat-opui-head{background:linear-gradient(120deg,#084077,#0a5ca0);padding:17px 19px;color:#fff}
    .deprat-opui-head h2{display:flex;align-items:center;gap:9px;margin:0;color:#fff;font-size:21px;font-weight:750;line-height:1.28}
    .deprat-opui-head h2 svg{height:22px;width:22px}.deprat-opui-head-primary{padding:17px 19px 19px}
    .deprat-opui-head-row{display:flex;gap:9px;justify-content:space-between;align-items:center;flex-wrap:wrap}
    .deprat-opui-header-status{background:#eaf2fc;color:#184a88;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:700}
    .deprat-opui-created{display:flex;align-items:center;gap:8px;font-size:13px;color:#e5f1ff;margin-top:12px}
    .deprat-opui-created svg{height:17px;width:17px}.deprat-opui-card-inner{padding:16px;min-width:0}
    .deprat-opui-subcard{border:1px solid #dee7f0;border-radius:10px;padding:14px;margin-bottom:14px;background:#fff}
    .deprat-opui-subcard:last-child{margin-bottom:0}.deprat-opui-subcard h3{margin:0 0 12px;color:#163b68;font-size:16px;font-weight:750;display:flex;gap:8px;align-items:center}
    .deprat-opui-subcard h3 svg{color:#08569a}.deprat-opui-company{font-size:16px;display:block}.deprat-opui-muted{color:#697b8c;display:block;margin-top:4px;font-size:13px}
    .deprat-opui-table-scroll{overflow-x:auto}.deprat-opui-table{width:100%;border-collapse:collapse;margin:0;font-size:13px;border:0;background:#fff}
    .deprat-opui-table th{background:#e8f1fb;color:#193c64;text-transform:uppercase;font-size:11px;letter-spacing:.03em;text-align:left;padding:10px 9px}
    .deprat-opui-table td{padding:9px;border-bottom:1px solid #e8edf4;color:#26364b;vertical-align:top}
    .deprat-opui-table tbody tr:last-child td{border-bottom:0}.deprat-opui-ref{font-weight:700;color:#12599b;white-space:nowrap}.deprat-opui-qty{text-align:center;font-weight:750}
    .deprat-opui-comment{padding:12px 13px;background:#f1f5fa;border-left:4px solid #d7a12a;border-radius:6px;white-space:normal;font-size:13px;overflow-wrap:anywhere}
    .deprat-opui-status-summary{display:flex;gap:12px;align-items:flex-start;background:#fff1d0;border-radius:9px;color:#936211;padding:14px;margin-bottom:15px}
    .deprat-opui-status-summary strong{display:block;font-size:19px;margin-bottom:3px}.deprat-opui-status-summary span{display:block;font-size:12px;color:#755c30}
    .deprat-opui-status-summary.status-dpr-accepte,.deprat-opui-status-summary.status-dpr-facture,.deprat-opui-status-summary.status-dpr-termine{background:#e9f6ee;color:#207347}
    .deprat-opui-status-summary.status-dpr-accepte span,.deprat-opui-status-summary.status-dpr-facture span,.deprat-opui-status-summary.status-dpr-termine span{color:#367853}
    .deprat-opui-status-summary.status-dpr-modifie,.deprat-opui-status-summary.status-dpr-envoye{background:#e8f1fc;color:#18558b}.deprat-opui-status-summary.status-dpr-modifie span,.deprat-opui-status-summary.status-dpr-envoye span{color:#486985}
    .deprat-opui-field{margin-bottom:15px}.deprat-opui-field label{display:block;font-size:12px;font-weight:750;margin-bottom:7px;color:#34506c}
    .deprat-opui-field p{margin:0!important;padding:0!important}.deprat-opui-field p>label{display:none}
    #deprat-opui-status-slot select{width:100%!important;max-width:none!important;min-height:42px!important;padding:6px 12px;border:1px solid #cbd8e6;border-radius:7px;font-size:13px;background:#fff}
    .deprat-opui-step{border:1px solid #dce5ef;background:#fbfcfe;border-radius:10px;padding:12px;margin:13px 0}
    .deprat-opui-step-title{display:flex;align-items:center;gap:10px;font-weight:800;font-size:16px;color:#24374e;margin-bottom:10px}
    .deprat-opui-num{width:29px;height:29px;border-radius:50%;display:inline-flex;justify-content:center;align-items:center;color:#fff;background:#105992;font-size:13px}
    .deprat-opui-num-grey{background:#8a9aab}
    #deprat-opui-devis-slot section,#deprat-opui-facture-slot section{border:0!important;background:transparent!important;padding:3px 0!important;margin:0!important;box-shadow:none!important}
    #deprat-opui-devis-slot h4,#deprat-opui-facture-slot h4{display:none!important}
    #deprat-opui-devis-slot p,#deprat-opui-facture-slot p{font-size:12px;margin:7px 0;line-height:1.5}
    #deprat-opui-devis-slot .button,#deprat-opui-facture-slot .button{background:#fff;color:#1166aa;border:1px solid #c7d9ea;border-radius:6px;text-decoration:none;padding:6px 11px;line-height:1.4;font-size:12px;min-height:0}
    #deprat-opui-devis-slot .deprat-pdf-fichier,#deprat-opui-facture-slot .deprat-pdf-fichier{max-width:100%;font-size:12px}
    .deprat-opui-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 15px;border-radius:7px;border:1px solid transparent;cursor:pointer;font-size:13px;font-weight:750;line-height:1.3;white-space:normal;text-align:center;transition:background-color .15s ease}
    .deprat-opui-btn svg{width:18px!important;height:18px!important}.deprat-opui-btn-blue{background:#074b87;color:#fff;width:100%}.deprat-opui-btn-blue:hover{background:#063c70;color:#fff}
    .deprat-opui-btn[disabled]{background:#a6b3c0!important;color:#fff!important;cursor:not-allowed}
    .deprat-opui-save{background:#084c88;color:#fff;font-size:14px;padding:12px 20px;box-shadow:0 4px 12px rgba(8,76,136,.14)}
    .deprat-opui-save:hover{background:#063c71;color:#fff}.deprat-opui-btn-outline{width:100%;border-color:#c2d9e6;background:#fff;color:#0b5c93}
    .deprat-opui-alert{padding:12px;border-radius:8px;font-size:12px;margin:13px 0;display:flex;gap:8px;align-items:flex-start;line-height:1.5}
    .deprat-opui-alert p{margin:4px 0 0}.deprat-opui-alert.is-danger{background:#fff0f0;color:#ab262a}.deprat-opui-alert.is-warning{background:#fff7de;color:#89640d;display:block}
    .deprat-opui-alert.is-success{background:#e6f4ec;color:#1c7640}.deprat-opui-alert svg{width:18px;height:18px}
    .deprat-opui-timeline{list-style:none;margin:0;padding:0;max-height:400px;overflow:auto}
    .deprat-opui-timeline li{border-left:2px solid #cdddeb;padding:0 0 14px 17px;position:relative;margin-left:6px}
    .deprat-opui-timeline li:before{content:"";position:absolute;width:11px;height:11px;border-radius:50%;left:-7px;top:3px;background:#1974c5;border:2px solid white;box-shadow:0 0 0 1px #bdd7ee}
    .deprat-opui-timeline li:first-child:before{background:#34a15c}.deprat-opui-timeline time{display:block;font-size:11px;color:#71839b;margin-bottom:4px}
    .deprat-opui-timeline li div{font-size:12px;font-weight:600;color:#2d3b4c;overflow-wrap:anywhere}.deprat-opui-timeline li p{margin:0 0 5px}
    .deprat-opui-customer-details dl{display:grid;grid-template-columns:95px minmax(0,1fr);gap:12px;padding:10px 0;border-bottom:1px solid #e7edf4;margin:0;font-size:12px}
    .deprat-opui-customer-details dl:last-of-type{border:0}.deprat-opui-customer-details dt{color:#66788c}.deprat-opui-customer-details dd{margin:0;overflow-wrap:anywhere;color:#233448}
    .deprat-opui-customer-details a{color:#0c64aa}.deprat-opui-text-link{border:0;background:transparent;color:#0b5c9e;cursor:pointer;font-size:12px;text-decoration:underline;padding:8px 0}
    #deprat-opui-note-form{margin-top:8px}#deprat-opui-note-form textarea{width:100%;max-width:100%;padding:10px;border:1px solid #d0e0ef;border-radius:6px;font-size:12px}
    #deprat-opui-note-save{width:auto;margin-top:6px}#deprat-opui-note-message{font-size:12px}
    #deprat-opui-archives-slot .deprat-versions-devis{border:1px solid #e3eaf2!important;border-radius:8px!important;padding:13px!important;background:#fff!important}
    #deprat-opui-archives-slot .deprat-versions-devis h4{margin:0 0 8px}
    #deprat-opui-notice:not([hidden]){display:block;background:#fff0ed;color:#a72a2a;padding:12px 16px;border:1px solid #f1c1bb;border-radius:8px;margin:0 0 14px;font-weight:700}
    /* En-tête WooCommerce natif masqué seulement si le nouveau tableau ADV est prêt.
       Le bandeau avec le numéro et le statut DEPRAT reste affiché. */
    body.deprat-opui-active #wpbody-content > .wrap > h1.wp-heading-inline,
    body.deprat-opui-active #wpbody-content > .wrap > h1:first-child,
    body.deprat-opui-active #wpbody-content > .wrap > .page-title-action,
    body.deprat-opui-active #wpbody-content > .wrap > hr.wp-header-end,
    body.deprat-opui-active #wpbody-content .wrap > h1.wp-heading-inline,
    body.deprat-opui-active #wpbody-content .wrap > .page-title-action,
    body.deprat-opui-active #wpbody-content .wrap > hr.wp-header-end {display:none!important}
    body.deprat-opui-active #deprat-opui-app {margin-top:0}
    body.deprat-opui-active #post-body.columns-2{margin-right:0!important}
    body.deprat-opui-active #post-body #postbox-container-1{display:none!important}
    body.deprat-opui-active #woocommerce-order-data,body.deprat-opui-active #woocommerce-order-items,body.deprat-opui-active #woocommerce-order-notes,body.deprat-opui-active #woocommerce-order-actions,body.deprat-opui-active #woocommerce-order-downloads,body.deprat-opui-active #deprat-adv-barre-fixe{display:none!important}
    body.deprat-opui-active #deprat-opui-app{display:block}
    @media(max-width:750px){.deprat-opui-brand{padding:17px;align-items:flex-start}.deprat-opui-brand-identite{gap:14px}.deprat-opui-official-logo{width:100px;max-height:55px}.deprat-opui-brand-intitule{padding-left:14px}.deprat-opui-brand-status{font-size:14px;padding:10px 13px}}
    @media(max-width:1350px){.deprat-opui-columns{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.deprat-opui-right{grid-column:1/-1;grid-template-columns:1fr 1fr}}
    @media(max-width:850px){.deprat-opui-columns,.deprat-opui-right{grid-template-columns:1fr}.deprat-opui-right{grid-column:1}.deprat-opui-toolbar{align-items:flex-start;flex-direction:column}.deprat-opui-save{width:100%}}
    </style>
    <script id="deprat-opui-boot">
    (function(){
      'use strict';
      const data = window.depratOpuiData || {};
      const root = document.getElementById('deprat-opui-app');
      if (!root) return;
      function notify(message){
        const node = document.getElementById('deprat-opui-notice');
        node.textContent = message; node.hidden = false; node.scrollIntoView({behavior:'smooth',block:'nearest'});
      }
      function mount(){
        const orderBox = document.getElementById('woocommerce-order-data');
        const select = document.getElementById('order_status');
        const quote = document.getElementById('deprat-document-devis-'+data.order_id);
        const invoice = document.getElementById('deprat-document-facture-'+data.order_id);
        const nativeSave = document.querySelector('#woocommerce-order-actions button.save_order, #woocommerce-order-actions button[name="save"], #publish');
        if (!orderBox || !select || !quote || !invoice || !nativeSave) {
          root.remove();
          return; // Sécurité : laisser WooCommerce natif visible si un composant n'est pas chargé.
        }
        const form = select.closest('form');
        const poststuff = document.getElementById('poststuff');
        const postBody = document.getElementById('post-body');
        if (!form) { root.remove(); return; }
        if (poststuff && postBody && form.contains(poststuff) && poststuff.contains(postBody)) {
          poststuff.insertBefore(root, postBody);
        } else if (orderBox.parentElement && form.contains(orderBox)) {
          orderBox.parentElement.insertBefore(root,orderBox);
        } else { root.remove(); return; }
        // IMPORTANT : déplacer les vrais éléments WooCommerce/V7.3, ne pas les cloner.
        const statusParent = select.closest('p.form-field') || select.parentElement;
        document.getElementById('deprat-opui-status-slot').appendChild(statusParent);
        document.getElementById('deprat-opui-devis-slot').appendChild(quote);
        document.getElementById('deprat-opui-facture-slot').appendChild(invoice);
        const oldArchive = orderBox.querySelector('.deprat-versions-devis');
        if (oldArchive) document.getElementById('deprat-opui-archives-slot').appendChild(oldArchive);
        // Date et heure de création : non éditables dans notre interface.
        ['order_date','order_date_hour','order_date_minute'].forEach(name=>{
          const input = form.querySelector('[name="'+name+'"]'); if (input) {input.readOnly=true;input.setAttribute('aria-readonly','true');}
        });
        document.body.classList.add('deprat-opui-active');
        function valueToSet(shortName){
          const values = Array.from(select.options).map(o=>o.value);
          return values.includes('wc-'+shortName) ? 'wc-'+shortName : (values.includes(shortName) ? shortName : '');
        }
        function save(){ nativeSave.click(); }
        function hasDocument(which){
          const selector = which === 'devis' ? '#deprat-opui-devis-slot .deprat-pdf-lien a' : '#deprat-opui-facture-slot .deprat-pdf-lien a';
          return !!document.querySelector(selector);
        }
        function statusAction(next,documentType){
          const target = valueToSet(next);
          if(!target){ notify('Le statut demandé n’est pas disponible. Vérifiez que la gestion des demandes V7.5 est active.');return; }
          if(documentType && !hasDocument(documentType)){
            notify('Déposez et enregistrez le fichier PDF '+(documentType==='devis'?'du devis':'de la facture')+' avant l’envoi.');return;
          }
          if(next==='dpr-facture' && !data.accepted){notify('Le client doit d’abord accepter son devis.');return;}
          if(next==='dpr-termine' && !data.invoiced){notify('La facture doit être publiée avant de terminer la commande.');return;}
          if((select.value||'')===target){notify('Cette demande est déjà dans cet état. Aucun nouvel e-mail ne sera envoyé sans transition de statut.');return;}
          if(!window.confirm('Confirmer cette action pour la demande n° '+data.order_id+' ?'))return;
          select.value=target;
          select.dispatchEvent(new Event('change',{bubbles:true}));
          save();
        }
        root.querySelector('.deprat-opui-save').addEventListener('click',save);
        document.getElementById('deprat-opui-send-quote').addEventListener('click',()=>statusAction(data.revision_pending ? 'dpr-modifie' : 'dpr-envoye','devis'));
        document.getElementById('deprat-opui-send-invoice').addEventListener('click',()=>statusAction('dpr-facture','facture'));
        const finish = document.getElementById('deprat-opui-finish');
        if(finish)finish.addEventListener('click',()=>statusAction('dpr-termine',null));
        document.getElementById('deprat-opui-add-note').addEventListener('click',()=>{
          const box=document.getElementById('deprat-opui-note-form');box.hidden=!box.hidden;
        });
        document.getElementById('deprat-opui-note-save').addEventListener('click',async()=>{
          const txt = document.getElementById('deprat-opui-note-text').value.trim();
          if(!txt){notify('Renseignez une note avant de l’enregistrer.');return;}
          const button=document.getElementById('deprat-opui-note-save');button.disabled=true;
          try{
            const payload=new FormData();
            payload.append('action','deprat_opui_note');payload.append('order_id',data.order_id);
            payload.append('security',data.nonce);payload.append('note',txt);
            const response=await fetch(data.ajax,{method:'POST',credentials:'same-origin',body:payload});
            const json=await response.json();
            if(!response.ok || !json.success)throw new Error(json.data?.message||'Échec de l’enregistrement.');
            window.location.reload();
          }catch(e){notify('Erreur : '+e.message);button.disabled=false;}
        });
      }
      if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});
      else mount();
    })();
    </script>
    <?php
} );
