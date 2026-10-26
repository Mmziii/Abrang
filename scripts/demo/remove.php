<?php
// Only explicit markers authorize deletion, never a SKU or filename alone.
// Variations (product_variation) are also marked _abrang_demo and must be
// removed, along with their images. Attribute terms that were created by
// the demo (meta _abrang_demo) are removed only when they are unused.
$ids = get_posts( array(
    'post_type'      => array( 'product', 'product_variation', 'attachment' ),
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_key'       => '_abrang_demo',
    'meta_value'     => '1',
) );
foreach ( $ids as $id ) {
    $type = get_post_type( $id );
    if ( 'attachment' === $type ) {
        wp_delete_attachment( $id, true );
    } else {
        // product or product_variation — variations are deleted explicitly;
        // deleting a variable product also deletes its variations, but we
        // handle the variation case directly so orphaned variations do not
        // remain if the parent was already removed.
        wp_delete_post( $id, true );
    }
}

// Demo attribute terms: delete only those that were created by the demo
// (term meta _abrang_demo) and are currently unused (count = 0). The
// attribute definitions themselves (pa_brand etc) are global product
// infrastructure and are never deleted here.
$taxonomies = array( 'pa_brand', 'pa_color', 'pa_size', 'pa_material', 'pa_pack-size', 'product_tag' );
foreach ( $taxonomies as $taxonomy ) {
    if ( ! taxonomy_exists( $taxonomy ) ) {
        continue;
    }
    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'meta_query' => array(
            array( 'key' => '_abrang_demo', 'value' => '1' ),
        ),
    ) );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        continue;
    }
    foreach ( $terms as $term ) {
        // Never delete a term that is still assigned to a product.
        if ( (int) $term->count !== 0 ) {
            continue;
        }
        $deleted = wp_delete_term( $term->term_id, $taxonomy );
        if ( ! is_wp_error( $deleted ) && $deleted ) {
            WP_CLI::log( "Removed demo term {$taxonomy}:{$term->slug}" );
        }
    }
}

wc_delete_product_transients();
WP_CLI::success( 'Demo content removed; unmarked content preserved.' );
