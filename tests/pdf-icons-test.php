<?php
/** Validation des 11 pictogrammes du PDF original, sans WordPress. */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/deprat-core/includes/pdf-icons.php';

$sources = deprat_pdf_icon_sources();
$expected = array(
    'tubes', 'embouts', 'flasque_guidage_bague', 'supports_arbres',
    'liaison', 'sangle', 'coulisses', 'tirage', 'jalousie', 'bras', 'treuil',
);
foreach ( $expected as $key ) {
    if ( ! isset( $sources[ $key ] ) ) {
        fwrite( STDERR, "Icône manquante : $key\n" );
        exit( 1 );
    }
    $compressed = base64_decode( $sources[ $key ], true );
    $bits = false === $compressed ? false : @gzuncompress( $compressed );
    if ( false === $bits || 800 !== strlen( $bits ) ) {
        fwrite( STDERR, "Tracé d'icône invalide : $key\n" );
        exit( 1 );
    }
}
if ( count( $sources ) !== 11 ) { exit( 1 ); }
echo "11 icônes PDF DEPRAT validées (80 × 80, compression zlib).\n";
