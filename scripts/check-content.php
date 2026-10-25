<?php
foreach ( json_decode( file_get_contents( __DIR__ . '/categories.json' ), true ) as $row ) {
    $parent = get_term_by( 'slug', $row[0], 'product_cat' );
    if ( ! $parent || $parent->parent ) { WP_CLI::error( 'Invalid top category' ); }
    foreach ( $row[2] as $child ) {
        $term = get_term_by( 'slug', $child[0], 'product_cat' );
        if ( ! $term || (int) $term->parent !== (int) $parent->term_id ) { WP_CLI::error( 'Invalid category child' ); }
    }
}
WP_CLI::success( 'Category tree verified.' );
