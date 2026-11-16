( function ( wp, labels ) {
    'use strict';

    if ( ! wp || ! wp.hooks || ! labels ) {
        return;
    }

    function translateReference( translation, original ) {
        return Object.prototype.hasOwnProperty.call( labels, original )
            ? labels[ original ]
            : translation;
    }

    wp.hooks.addFilter(
        'i18n.gettext_woocommerce',
        'deprat-core/product-reference',
        translateReference
    );
    wp.hooks.addFilter(
        'i18n.gettext_with_context_woocommerce',
        'deprat-core/product-reference',
        translateReference
    );
} )( window.wp, window.depratProductReferenceLabels );
