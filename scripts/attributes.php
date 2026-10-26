<?php
/**
 * Abrang global product attributes — idempotent, by slug.
 *
 * Called from setup.sh on every run. Attribute archives remain disabled.
 * Only the slugs are identities: existing names are preserved.
 *
 * Slugs: brand (برند), color (رنگ), size (سایز/ابعاد), material (جنس),
 * pack-size (تعداد در بسته). Colour terms for the demo fixtures are also
 * created here and marked _abrang_demo so that demo-remove.sh can clean
 * them up when they are no longer used.
 */

// Global attributes — type select, archives disabled, ordered by menu_order.
$abrang_attributes = array(
    'brand'     => 'برند',
    'color'     => 'رنگ',
    'size'      => 'سایز/ابعاد',
    'material'  => 'جنس',
    'pack-size' => 'تعداد در بسته',
);

// Helper to ensure pa_* taxonomy is registered in this PHP request.
// Woo's wc_register_attribute_taxonomies() reads from DB via wc_get_attribute_taxonomies()
// which is cached in transient + object cache. After wc_create_attribute() the transient
// is deleted, but the object cache may still hold stale data until we invalidate it.
// We also register directly if Woo's function did not pick it up yet (same args Woo uses).
$abrang_ensure_taxonomy = static function ( $slug, $label ) {
    $taxonomy = 'pa_' . $slug;
    if ( taxonomy_exists( $taxonomy ) ) {
        return;
    }
    // Force cache refresh before Woo's registration.
    delete_transient( 'wc_attribute_taxonomies' );
    if ( class_exists( 'WC_Cache_Helper' ) ) {
        WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
    }
    if ( function_exists( 'wc_register_attribute_taxonomies' ) ) {
        wc_register_attribute_taxonomies();
    }
    if ( ! taxonomy_exists( $taxonomy ) ) {
        // Direct registration fallback — same args WooCommerce uses for attribute taxonomies
        // (public = has_archives, hierarchical false, query_var true, rewrite false).
        register_taxonomy(
            $taxonomy,
            array( 'product' ),
            array(
                'labels'       => array( 'name' => $label ),
                'public'       => false,
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            )
        );
    }
};

foreach ( $abrang_attributes as $slug => $label ) {
    $existing_id = wc_attribute_taxonomy_id_by_name( $slug );
    if ( $existing_id ) {
        $attr = wc_get_attribute( $existing_id );
        // Ensure archives stay disabled (attribute_public = 0).
        if ( $attr && $attr->has_archives ) {
            $updated = wc_update_attribute( $existing_id, array( 'has_archives' => false ) );
            if ( is_wp_error( $updated ) ) {
                WP_CLI::error( $updated );
            }
            WP_CLI::log( "Updated attribute $slug (disabled archives)" );
        } else {
            WP_CLI::log( "[already done] attribute $slug" );
        }
        // Ensure taxonomy is registered in this request even for existing attributes.
        $abrang_ensure_taxonomy( $slug, $label );
        continue;
    }
    $result = wc_create_attribute( array(
        'name'         => $label,
        'slug'         => $slug,
        'type'         => 'select',
        'order_by'     => 'menu_order',
        'has_archives' => false,
    ) );
    if ( is_wp_error( $result ) ) {
        // Genuine already-exists race — treat as done.
        if ( in_array( $result->get_error_code(), array( 'invalid_product_attribute_slug_already_exists', 'term_exists' ), true ) ) {
            WP_CLI::log( "[already done] attribute $slug" );
            $abrang_ensure_taxonomy( $slug, $label );
            continue;
        }
        WP_CLI::error( $result );
    }
    WP_CLI::log( "Created attribute $slug" );
    // Immediately register the new taxonomy in this same PHP request so
    // subsequent wp_insert_term / wp_set_object_terms / variations do not
    // fail with "invalid taxonomy".
    $abrang_ensure_taxonomy( $slug, $label );
}

// Ensure all attribute taxonomies are registered for term creation below
// (and for the next file setup.sh will run in the same request).
delete_transient( 'wc_attribute_taxonomies' );
if ( class_exists( 'WC_Cache_Helper' ) ) {
    WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
}
if ( function_exists( 'wc_register_attribute_taxonomies' ) ) {
    wc_register_attribute_taxonomies();
}

// Demo colour terms — needed for the variable demo product and for the
// simple demo fixtures. They are marked with _abrang_demo so demo-remove.sh
// can delete them only when they are unused and only if this file created them.
$abrang_color_terms = array(
    'blue'  => 'آبی',
    'black' => 'مشکی',
    'red'   => 'قرمز',
    'green' => 'سبز',
);

foreach ( $abrang_color_terms as $slug => $name ) {
    // Ensure taxonomy exists before touching terms.
    if ( ! taxonomy_exists( 'pa_color' ) ) {
        $abrang_ensure_taxonomy( 'color', 'رنگ' );
    }
    $term = get_term_by( 'slug', $slug, 'pa_color' );
    if ( $term && ! is_wp_error( $term ) ) {
        // Preserve existing terms — only mark with demo meta if it was created
        // by this script. Terms that pre-date the demo flag keep no marker.
        WP_CLI::log( "[already done] color term $slug" );
        continue;
    }
    $result = wp_insert_term( $name, 'pa_color', array( 'slug' => $slug ) );
    if ( is_wp_error( $result ) ) {
        // Race: term was created between the get and the insert.
        if ( 'term_exists' === $result->get_error_code() ) {
            WP_CLI::log( "[already done] color term $slug" );
            continue;
        }
        WP_CLI::error( $result );
    }
    update_term_meta( $result['term_id'], '_abrang_demo', 1 );
    WP_CLI::log( "Created color term $slug" );
}
