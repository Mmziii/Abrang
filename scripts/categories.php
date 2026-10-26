<?php
// Slugs are identities: preserve existing names and relationships.
$ensure = static function ( $slug, $name, $parent = 0 ) {
    $term = get_term_by( 'slug', $slug, 'product_cat' );
    if ( $term ) {
        WP_CLI::log( "[already done] category $slug" );
        return $term->term_id;
    }
    $result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug, 'parent' => $parent ) );
    if ( is_wp_error( $result ) ) { WP_CLI::error( $result ); }
    WP_CLI::log( "Created category $slug" );
    return $result['term_id'];
};
foreach ( json_decode( file_get_contents( __DIR__ . '/categories.json' ), true ) as $row ) {
    $parent = $ensure( $row[0], $row[1] );
    foreach ( $row[2] as $child ) { $ensure( $child[0], $child[1], $parent ); }
}
$default = get_term( (int) get_option( 'default_product_cat' ), 'product_cat' );
if ( $default && ! is_wp_error( $default ) && in_array( $default->name, array( 'Uncategorized', 'بدون دسته‌بندی' ), true ) ) {
    $result = wp_update_term( $default->term_id, 'product_cat', array( 'name' => 'سایر', 'slug' => 'other' ) );
    if ( is_wp_error( $result ) ) { WP_CLI::error( $result ); }
}
