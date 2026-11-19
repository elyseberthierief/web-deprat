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

/** Les neuf illustrations 3D doivent être livrées avec le plugin, intactes. */
foreach ( array( 'attache', 'treuil', 'sangle', 'support-arbres', 'flasque-guidage', 'porte-garage', 'persienne-jalousie', 'bras-projection', 'guide-sangle' ) as $name ) {
    $path = dirname( __DIR__ ) . '/deprat-core/assets/icons/' . $name . '-3d.webp';
    if ( ! is_file( $path ) || filesize( $path ) < 300
        || substr( (string) file_get_contents( $path ), 0, 4 ) !== 'RIFF'
        || substr( (string) file_get_contents( $path ), 8, 4 ) !== 'WEBP' ) {
        fwrite( STDERR, "Icône 3D manquante ou corrompue : $name\\n" );
        exit( 1 );
    }
}
echo "9 rendus 3D valides dans DEPRAT Core.\\n";
