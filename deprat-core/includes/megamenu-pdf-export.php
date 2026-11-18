<?php
/**
 * DEPRAT — export PDF autonome du méga-menu réellement enregistré.
 *
 * Pas de CDN, pas de police distante, pas de dépendance PDF à installer.
 * Les données proviennent des rubriques éditoriales et des product_cat WooCommerce.
 */
defined( 'ABSPATH' ) || exit;

/** Récolter les catégories réelles dans l'ordre de WooCommerce. */
function deprat_megamenu_pdf_tree() {
    $groups = deprat_catalog_grouped_roots();
    $definitions = deprat_catalog_section_definitions();
    $result = array();
    $visited = array();
    $total = 0;
    foreach ( $definitions as $section => $title ) {
        if ( empty( $groups[ $section ] ) ) { continue; }
        $rows = array();
        $walk = static function ( $term, $depth ) use ( &$walk, &$rows, &$visited, &$total ) {
            if ( $depth > 30 || $total >= 2500 || isset( $visited[ (int) $term->term_id ] ) ) {
                return;
            }
            $visited[ (int) $term->term_id ] = true;
            $rows[] = array(
                'name' => wp_strip_all_tags( (string) $term->name ),
                'depth' => $depth,
            );
            ++$total;
            foreach ( deprat_category_tree_front_children( (int) $term->term_id ) as $child ) {
                $walk( $child, $depth + 1 );
            }
        };
        foreach ( $groups[ $section ] as $root ) { $walk( $root, 0 ); }
        if ( $rows ) {
            $result[] = array( 'key' => $section, 'title' => $title, 'rows' => $rows );
        }
    }
    return $result;
}

/** PDF WinAnsi : les accents français sont lisibles, tous les caractères PDF spéciaux échappés. */
function deprat_megamenu_pdf_cp1252( $value ) {
    $value = wp_strip_all_tags( (string) $value );
    if ( function_exists( 'iconv' ) ) {
        $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value );
        if ( false !== $converted ) { $value = $converted; }
    } else {
        $value = remove_accents( $value );
        $value = preg_replace( '/[^\x20-\x7E]/', '?', $value );
    }
    return preg_replace( '/[\x00-\x1F\x7F]/', ' ', $value );
}
function deprat_megamenu_pdf_literal( $value ) {
    return str_replace(
        array( '\\', '(', ')' ),
        array( '\\\\', '\\(', '\\)' ),
        deprat_megamenu_pdf_cp1252( $value )
    );
}
function deprat_megamenu_pdf_wrap( $value, $width ) {
    $text = deprat_megamenu_pdf_cp1252( $value );
    $lines = explode( "\n", wordwrap( $text, max( 8, (int) $width ), "\n", true ) );
    // Les noms de catégories contiennent des accents : revenir en UTF-8
    // avant de réutiliser la fonction de dessin, qui encode une seule fois.
    if ( function_exists( 'iconv' ) ) {
        foreach ( $lines as &$line ) {
            $utf8 = @iconv( 'Windows-1252', 'UTF-8//IGNORE', $line );
            if ( false !== $utf8 ) { $line = $utf8; }
        }
        unset( $line );
    }
    return $lines;
}
function deprat_megamenu_pdf_rgb( $hex ) {
    $hex = ltrim( $hex, '#' );
    $parts = array_map( 'hexdec', str_split( $hex, 2 ) );
    return sprintf( '%.3f %.3f %.3f', $parts[0] / 255, $parts[1] / 255, $parts[2] / 255 );
}
function deprat_megamenu_pdf_rect( $x, $y, $width, $height, $color, $stroke = false ) {
    $rgb = deprat_megamenu_pdf_rgb( $color );
    return sprintf( "q %s %s %.2f %.2f %.2f %.2f re %s Q\n",
        $rgb, $stroke ? 'RG' : 'rg', $x, $y, $width, $height, $stroke ? 'S' : 'f' );
}
function deprat_megamenu_pdf_line( $x1, $y1, $x2, $y2, $color, $width = 1 ) {
    return sprintf( "q %s RG %.2f w %.2f %.2f m %.2f %.2f l S Q\n",
        deprat_megamenu_pdf_rgb( $color ), $width, $x1, $y1, $x2, $y2 );
}
function deprat_megamenu_pdf_text( $text, $x, $y, $size, $color, $bold = false ) {
    return sprintf( "q %s rg BT /%s %.1f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET Q\n",
        deprat_megamenu_pdf_rgb( $color ), $bold ? 'F2' : 'F1',
        $size, $x, $y, deprat_megamenu_pdf_literal( $text ) );
}
function deprat_megamenu_pdf_row_height( $row ) {
    $maxchars = max( 20, 51 - min( 6, (int) $row['depth'] ) * 3 );
    $lines = deprat_megamenu_pdf_wrap( $row['name'], $maxchars );
    return max( 22, 7 + 14 * count( $lines ) );
}
/** Découper les très longues sections, sans tronquer ni masquer une sous-catégorie. */
function deprat_megamenu_pdf_tiles( $sections ) {
    $tiles = array();
    foreach ( $sections as $section ) {
        $chunk = array();
        $height = 76;
        $continuation = false;
        foreach ( $section['rows'] as $row ) {
            $row_height = deprat_megamenu_pdf_row_height( $row );
            if ( $chunk && $height + $row_height + 17 > 585 ) {
                $tiles[] = array(
                    'title' => $section['title'] . ( $continuation ? ' (suite)' : '' ),
                    'key' => $section['key'], 'rows' => $chunk, 'height' => $height + 15,
                );
                $chunk = array();
                $height = 76;
                $continuation = true;
            }
            $chunk[] = $row;
            $height += $row_height;
        }
        if ( $chunk ) {
            $tiles[] = array(
                'title' => $section['title'] . ( $continuation ? ' (suite)' : '' ),
                'key' => $section['key'], 'rows' => $chunk, 'height' => $height + 15,
            );
        }
    }
    return $tiles;
}

function deprat_megamenu_pdf_card( $tile, $x, $top, $width ) {
    $height = $tile['height'];
    $bottom = $top - $height;
    $out = deprat_megamenu_pdf_rect( $x, $bottom, $width, $height, 'FFFFFF' );
    $out .= deprat_megamenu_pdf_rect( $x, $bottom, $width, $height, 'DCE7F3', true );
    // Pictogramme vectoriel discret sur fond bleu pâle.
    $out .= deprat_megamenu_pdf_rect( $x + 14, $top - 49, 32, 32, 'EDF4FE' );
    $out .= deprat_megamenu_pdf_rect( $x + 20, $top - 41, 20, 15, '17529C', true );
    $out .= deprat_megamenu_pdf_line( $x + 22, $top - 38, $x + 37, $top - 28, '17529C', 0.8 );
    $out .= deprat_megamenu_pdf_line( $x + 22, $top - 32, $x + 37, $top - 39, '17529C', 0.8 );
    $head_lines = deprat_megamenu_pdf_wrap( $tile['title'], 42 );
    $header_y = $top - 29;
    foreach ( array_slice( $head_lines, 0, 2 ) as $head_line ) {
        $out .= deprat_megamenu_pdf_text( $head_line, $x + 55, $header_y, 12.5, '143F7C', true );
        $header_y -= 15;
    }
    $out .= deprat_megamenu_pdf_rect( $x + 55, $top - 56, 73, 3, 'C9911C' );
    $y = $top - 75;
    foreach ( $tile['rows'] as $row ) {
        $depth = min( 7, max( 0, (int) $row['depth'] ) );
        $indent = $depth * 16;
        $text_x = $x + 26 + $indent;
        $lines = deprat_megamenu_pdf_wrap( $row['name'], max( 20, 51 - $depth * 3 ) );
        if ( $depth ) {
            $out .= deprat_megamenu_pdf_line( $x + 15 + $indent, $y + 2,
                $x + 15 + $indent, $y - ( count( $lines ) - 1 ) * 14 - 11,
                'D4E0ED', 0.7 );
            $out .= deprat_megamenu_pdf_rect( $x + 19 + $indent, $y - 1, 3, 3, '91AFCF' );
        } else {
            $out .= deprat_megamenu_pdf_rect( $x + 14, $y - 2, 4, 4, '1E579A' );
        }
        foreach ( $lines as $line ) {
            $out .= deprat_megamenu_pdf_text( $line, $text_x, $y, $depth ? 10 : 10.5,
                $depth ? '486481' : '214B80', ! $depth );
            $y -= 14;
        }
        $y -= max( 8, deprat_megamenu_pdf_row_height( $row ) - 14 * count( $lines ) );
    }
    return $out;
}

function deprat_megamenu_pdf_page_header( $page, $total, $date ) {
    $w = 1191;
    $h = 842;
    $out = deprat_megamenu_pdf_rect( 0, 0, $w, $h, 'F7F9FD' );
    $out .= deprat_megamenu_pdf_rect( 0, $h - 8, $w, 8, 'CA951F' );
    $out .= deprat_megamenu_pdf_text( 'CATALOGUE DEPRAT', 38, 801, 10, 'B17B16', true );
    $out .= deprat_megamenu_pdf_text( 'Nos composants', 38, 761, 27, '123B77', true );
    $out .= deprat_megamenu_pdf_text( 'Arborescence du méga-menu', 39, 738, 11, '5A7291' );
    $out .= deprat_megamenu_pdf_text( 'Export WordPress - ' . $date, 936, 772, 10, '607B9B' );
    $out .= deprat_megamenu_pdf_line( 38, 721, 1153, 721, 'D9E4F1', 1 );
    $out .= deprat_megamenu_pdf_line( 38, 49, 1153, 49, 'D9E4F1', 1 );
    $out .= deprat_megamenu_pdf_text( 'Titres de section > Catégories > Sous-catégories. Regroupements non cliquables.', 38, 31, 9, '68829F' );
    $out .= deprat_megamenu_pdf_text( sprintf( 'Page %d / %d', $page, $total ), 1091, 31, 9, '68829F' );
    return $out;
}

/** Petit assembleur PDF 1.4 : A3 paysage, polices Helvetica intégrées au lecteur. */
function deprat_megamenu_pdf_bytes( $sections, $date = '' ) {
    $tiles = deprat_megamenu_pdf_tiles( $sections );
    $rows = array_chunk( $tiles, 3 );
    $pages = array();
    $content = '';
    $y = 705;
    $w = 362;
    $gap = 17;
    foreach ( $rows as $row ) {
        $row_height = max( array_column( $row, 'height' ) );
        if ( $content && $y - $row_height < 65 ) {
            $pages[] = $content;
            $content = '';
            $y = 705;
        }
        foreach ( $row as $i => $tile ) {
            $content .= deprat_megamenu_pdf_card( $tile, 36 + $i * ( $w + $gap ), $y, $w );
        }
        $y -= $row_height + 17;
    }
    $pages[] = $content;
    $total = count( $pages );
    $objects = array(
        1 => '', 2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    );
    $kids = array();
    foreach ( $pages as $i => $stream ) {
        $stream = deprat_megamenu_pdf_page_header( $i + 1, $total, $date ) . $stream;
        $content_id = count( $objects ) + 1;
        $page_id = $content_id + 1;
        $objects[ $content_id ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n"
            . $stream . "\nendstream";
        $objects[ $page_id ] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 1191 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
            $content_id
        );
        $kids[] = $page_id . ' 0 R';
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
    ksort( $objects );
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = array( 0 );
    foreach ( $objects as $id => $object ) {
        $offsets[ $id ] = strlen( $pdf );
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen( $pdf );
    $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ( $i = 1; $i <= count( $objects ); ++$i ) {
        $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
    }
    $pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

function deprat_megamenu_pdf_export() {
    if ( ! current_user_can( 'manage_product_terms' ) || ! taxonomy_exists( 'product_cat' ) ) {
        wp_die( 'Accès à l’arborescence interdit.', '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'deprat_export_megamenu_pdf' );
    $pdf = deprat_megamenu_pdf_bytes( deprat_megamenu_pdf_tree(), wp_date( 'd/m/Y' ) );
    if ( headers_sent() ) {
        wp_die( 'Impossible de transmettre le PDF après un affichage.' );
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="DEPRAT-arborescence-mega-menu-' . wp_date( 'Y-m-d' ) . '.pdf"' );
    header( 'Content-Length: ' . strlen( $pdf ) );
    header( 'X-Content-Type-Options: nosniff' );
    echo $pdf; // PDF généré intégralement à partir de libellés encodés et échappés.
    exit;
}
add_action( 'admin_post_deprat_export_megamenu_pdf', 'deprat_megamenu_pdf_export' );
