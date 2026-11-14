<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DEPRAT - Liste WooCommerce des demandes professionnelles - V2.
 * À placer dans Code Snippets > PHP > Administration uniquement.
 * Remplace le snippet "DEPRAT – LISTE COMMANDES – ÉTAT ET ACTIONS ADV".
 * Aucun changement des commandes, statuts, e-mails, fichiers ou preuves en base.
 * Compatible affichages HPOS et commandes WordPress classiques.
 */

if ( ! function_exists( 'deprat_liste2_columns' ) ) {
    function deprat_liste2_columns( $columns ) {
        if ( ! is_array( $columns ) ) {
            return $columns;
        }
        $new = array();

        // Conserver les cases natives de sélection en masse.
        if ( isset( $columns['cb'] ) ) {
            $new['cb'] = $columns['cb'];
        }

        $new['deprat_l2_number']   = 'N° demande';
        $new['deprat_l2_company']  = 'Entreprise';
        $new['deprat_l2_contact']  = 'Nom / prénom';
        $new['deprat_l2_products'] = 'Produits';
        $new['deprat_l2_date']     = 'Date';
        $new['deprat_l2_status']   = 'État';
        $new['deprat_l2_action']   = 'Action requise';
        $new['deprat_l2_open']     = 'Dossier';

        return $new;
    }
}

add_filter(
    'manage_woocommerce_page_wc-orders_columns',
    'deprat_liste2_columns',
    99999
);

add_filter(
    'manage_edit-shop_order_columns',
    'deprat_liste2_columns',
    99999
);


/* ==========================================
   1. STATUTS ET PROCHAINES ACTIONS
   ========================================== */

if ( ! function_exists( 'deprat_liste2_status' ) ) {
    function deprat_liste2_status( $order ) {

        $status = (string) $order->get_status();

        $revision = 'oui' === $order->get_meta(
            '_deprat_revision_en_attente',
            true
        );

        $map = array(
            'dpr-recu' => array(
                'Nouvelle demande',
                'amber',
                'Préparer le devis'
            ),

            'dpr-encours' => array(
                'En cours de traitement',
                'amber',
                $revision
                    ? 'Corriger le devis'
                    : 'Finaliser le devis'
            ),

            'dpr-envoye' => array(
                'Devis à valider',
                'blue',
                'En attente du client'
            ),

            'dpr-modifie' => array(
                'Devis modifié à valider',
                'blue',
                'En attente du client'
            ),

            'dpr-accepte' => array(
                'Devis accepté',
                'green',
                'Préparer la facture'
            ),

            'dpr-facture' => array(
                'Facture disponible',
                'purple',
                'Facture transmise'
            ),

            'dpr-termine' => array(
                'Commande terminée',
                'grey',
                '—'
            ),
        );

        if ( isset( $map[ $status ] ) ) {
            return $map[ $status ];
        }

        return array(
            function_exists( 'wc_get_order_status_name' )
                ? wc_get_order_status_name( 'wc-' . $status )
                : $status,
            'grey',
            '—'
        );
    }
}


/* ==========================================
   2. CONTENU DES COLONNES
   ========================================== */

if ( ! function_exists( 'deprat_liste2_cell' ) ) {
    function deprat_liste2_cell( $column, $order ) {

        if (
            ! $order instanceof WC_Order ||
            0 !== strpos( (string) $column, 'deprat_l2_' )
        ) {
            return;
        }

        $url = $order->get_edit_order_url();
        $uid = $order->get_customer_id();
        $date = $order->get_date_created();

        $status_info = deprat_liste2_status( $order );

        switch ( $column ) {

            /* NUMÉRO */
            case 'deprat_l2_number':

                echo '<a class="deprat-l2-id" href="' .
                    esc_url( $url ) . '">#' .
                    esc_html( $order->get_order_number() ) .
                    '</a>';

                break;


            /* ENTREPRISE */
            case 'deprat_l2_company':

                $company = trim(
                    (string) $order->get_billing_company()
                );

                if ( '' === $company && $uid ) {
                    $company = trim(
                        (string) get_user_meta(
                            $uid,
                            'billing_company',
                            true
                        )
                    );
                }

                echo '<span class="deprat-l2-company">' .
                    esc_html(
                        $company ?: 'Non renseignée'
                    ) .
                    '</span>';

                break;


            /* CONTACT */
            case 'deprat_l2_contact':

                $first = trim(
                    (string) $order->get_billing_first_name()
                );

                $last = trim(
                    (string) $order->get_billing_last_name()
                );

                if ( '' === $first && $uid ) {
                    $first = (string) get_user_meta(
                        $uid,
                        'first_name',
                        true
                    );
                }

                if ( '' === $last && $uid ) {
                    $last = (string) get_user_meta(
                        $uid,
                        'last_name',
                        true
                    );
                }

                echo esc_html(
                    trim( $first . ' ' . $last )
                    ?: 'Non renseigné'
                );

                break;


            /* NOMBRE DE RÉFÉRENCES */
            case 'deprat_l2_products':

                $count = count(
                    $order->get_items( 'line_item' )
                );

                echo '<strong>' .
                    esc_html( (string) $count ) .
                    '</strong> ';

                echo '<span class="deprat-l2-muted">' .
                    esc_html(
                        $count > 1
                            ? 'références'
                            : 'référence'
                    ) .
                    '</span>';

                break;


            /* DATE ET HEURE */
            case 'deprat_l2_date':

                if ( $date ) {
                    echo esc_html(
                        wp_date(
                            'd/m/Y',
                            $date->getTimestamp()
                        )
                    );

                    echo '<small class="deprat-l2-time">' .
                        esc_html(
                            wp_date(
                                'H:i',
                                $date->getTimestamp()
                            )
                        ) .
                        '</small>';
                } else {
                    echo '—';
                }

                break;


            /* ÉTAT */
            case 'deprat_l2_status':

                echo '<span class="deprat-l2-pill deprat-l2-pill--' .
                    esc_attr( $status_info[1] ) .
                    '">' .
                    esc_html( $status_info[0] ) .
                    '</span>';

                break;


            /* ACTION REQUISE */
            case 'deprat_l2_action':

                $action = $status_info[2];

                if ( '—' === $action ) {

                    echo '<span class="deprat-l2-muted">—</span>';

                } elseif (
                    in_array(
                        $order->get_status(),
                        array(
                            'dpr-envoye',
                            'dpr-modifie',
                            'dpr-facture'
                        ),
                        true
                    )
                ) {

                    echo '<span class="deprat-l2-wait">' .
                        esc_html( $action ) .
                        '</span>';

                } else {

                    echo '<span class="deprat-l2-required">' .
                        esc_html( $action ) .
                        '</span>';

                }

                break;


            /* BOUTON OUVRIR */
            case 'deprat_l2_open':

                echo '<a class="deprat-l2-open" href="' .
                    esc_url( $url ) .
                    '" aria-label="Ouvrir la demande ' .
                    esc_attr(
                        $order->get_order_number()
                    ) .
                    '">Ouvrir <span aria-hidden="true">↗</span></a>';

                break;
        }
    }
}


/* ==========================================
   3. COMPATIBILITÉ HPOS / CLASSIQUE
   ========================================== */

// HPOS : l'argument transmis est un objet WC_Order.
add_action(
    'manage_woocommerce_page_wc-orders_custom_column',
    function( $column, $order ) {
        deprat_liste2_cell( $column, $order );
    },
    10,
    2
);

// Stockage historique : l'argument est l'identifiant du post.
add_action(
    'manage_shop_order_posts_custom_column',
    function( $column, $post_id ) {

        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        deprat_liste2_cell(
            $column,
            wc_get_order( absint( $post_id ) )
        );
    },
    10,
    2
);


/* ==========================================
   4. DESIGN DE LA LISTE
   ========================================== */

add_action( 'admin_head', function() {

    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();

    if (
        ! $screen ||
        ! in_array(
            $screen->id,
            array(
                'woocommerce_page_wc-orders',
                'edit-shop_order'
            ),
            true
        )
    ) {
        return;
    }
    ?>

    <style id="deprat-liste2-style">

        /* Titre de page */
        body.woocommerce_page_wc-orders
        #wpbody-content .wrap h1.wp-heading-inline,
        body.post-type-shop_order
        #wpbody-content .wrap h1.wp-heading-inline {
            font-family: 'Agenda', Arial, sans-serif;
            color: #11467e;
            font-size: 32px;
            font-weight: 750;
            letter-spacing: -.5px;
            margin: 10px 0 20px;
        }

        /* Largeur */
        body.woocommerce_page_wc-orders
        #wpbody-content .wrap,
        body.post-type-shop_order
        #wpbody-content .wrap {
            max-width: none;
        }

        /* Onglets de filtrage */
        body.woocommerce_page_wc-orders .subsubsub,
        body.post-type-shop_order .subsubsub {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            float: none !important;
            width: 100%;
            margin: 12px 0 20px !important;
        }

        body.woocommerce_page_wc-orders .subsubsub li,
        body.post-type-shop_order .subsubsub li {
            margin: 0 !important;
            padding: 0 !important;
            font-size: 0 !important;
        }

        body.woocommerce_page_wc-orders .subsubsub li a,
        body.post-type-shop_order .subsubsub li a {
            display: inline-flex;
            align-items: center;
            padding: 9px 13px;
            background: #fff;
            border: 1px solid #dfe8f2;
            border-radius: 9px;
            font-size: 13px !important;
            font-weight: 700;
            color: #214d78 !important;
            text-decoration: none;
            line-height: 1.35;
        }

        body.woocommerce_page_wc-orders .subsubsub li a.current,
        body.post-type-shop_order .subsubsub li a.current {
            background: #0a4d8c;
            border-color: #0a4d8c;
            color: #fff !important;
        }

        body.woocommerce_page_wc-orders .subsubsub li a .count,
        body.post-type-shop_order .subsubsub li a .count {
            font-size: 12px !important;
            margin-left: 4px;
            color: inherit;
        }

        /* Tableau */
        body.woocommerce_page_wc-orders .wp-list-table,
        body.post-type-shop_order .wp-list-table {
            border: 1px solid #dce5ef;
            border-radius: 12px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(17,67,113,.04);
            font-family: 'Agenda', Arial, sans-serif;
            table-layout: auto;
        }

        /* En-têtes de colonnes */
        body.woocommerce_page_wc-orders .wp-list-table thead th,
        body.post-type-shop_order .wp-list-table thead th {
            background: #f0f5fa !important;
            color: #173e68 !important;
            padding-top: 16px;
            padding-bottom: 16px;
            font-size: 12px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* Lignes */
        body.woocommerce_page_wc-orders .wp-list-table tbody td,
        body.post-type-shop_order .wp-list-table tbody td {
            padding-top: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #eaf0f5;
            vertical-align: middle;
            color: #263f5e;
            font-size: 13px;
        }

        body.woocommerce_page_wc-orders .wp-list-table tbody tr:hover td,
        body.post-type-shop_order .wp-list-table tbody tr:hover td {
            background: #f8fbff;
        }

        /* Numéro de demande */
        .deprat-l2-id {
            font-size: 16px;
            font-weight: 800;
            color: #07529a;
            text-decoration: none;
        }

        .deprat-l2-id:hover {
            text-decoration: underline;
        }

        /* Entreprise */
        .deprat-l2-company {
            font-weight: 750;
            color: #153b62;
        }

        /* Textes secondaires */
        .deprat-l2-muted,
        .deprat-l2-time {
            font-size: 12px;
            color: #788ba1;
        }

        .deprat-l2-time {
            display: block;
            margin-top: 4px;
        }

        /* Pastilles des statuts */
        .deprat-l2-pill {
            display: inline-flex;
            max-width: 180px;
            padding: 8px 11px;
            align-items: center;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.25;
            white-space: normal;
        }

        .deprat-l2-pill--amber {
            background: #fff0d9;
            color: #965a0a;
        }

        .deprat-l2-pill--blue {
            background: #e5f1ff;
            color: #155b9f;
        }

        .deprat-l2-pill--green {
            background: #e1f5e7;
            color: #167342;
        }

        .deprat-l2-pill--purple {
            background: #f1eafa;
            color: #683d9c;
        }

        .deprat-l2-pill--grey {
            background: #edf1f5;
            color: #566a7f;
        }

        /* Action ADV en texte rouge */
        .deprat-l2-required {
            color: #be3030 !important;
            font-weight: 750;
            line-height: 1.4;
        }

        /* Attente client */
        .deprat-l2-wait {
            color: #6c7e90;
        }

        /* Bouton ouvrir */
        .deprat-l2-open {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            background: #0b5fb0;
            color: white !important;
            border-radius: 8px;
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 750;
            text-decoration: none !important;
            white-space: nowrap;
            transition: background .15s;
        }

        .deprat-l2-open:hover {
            background: #083e79;
        }

        /* Largeurs des colonnes */
        .wp-list-table .column-deprat_l2_number {
            width: 8%;
        }

        .wp-list-table .column-deprat_l2_company {
            width: 18%;
        }

        .wp-list-table .column-deprat_l2_contact {
            width: 15%;
        }

        .wp-list-table .column-deprat_l2_products {
            width: 10%;
        }

        .wp-list-table .column-deprat_l2_date {
            width: 12%;
        }

        .wp-list-table .column-deprat_l2_status {
            width: 15%;
        }

        .wp-list-table .column-deprat_l2_action {
            width: 14%;
        }

        .wp-list-table .column-deprat_l2_open {
            width: 8%;
        }

        @media (max-width: 1300px) {
            .wp-list-table .column-deprat_l2_company {
                width: 15%;
            }

            .wp-list-table .column-deprat_l2_action {
                width: 16%;
            }
        }

    </style>

    <?php
} );


/* ==========================================
   5. TITRE DE LA PAGE
   ========================================== */

add_action( 'admin_footer', function() {

    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();

    if (
        ! $screen ||
        ! in_array(
            $screen->id,
            array(
                'woocommerce_page_wc-orders',
                'edit-shop_order'
            ),
            true
        )
    ) {
        return;
    }
    ?>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

                var h = document.querySelector(
                    '#wpbody-content .wrap h1.wp-heading-inline'
                );

                if ( h ) {
                    h.textContent =
                        'DEPRAT — Demandes professionnelles';
                }
            }
        );
    </script>

    <?php
} );
