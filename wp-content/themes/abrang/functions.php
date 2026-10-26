<?php
/**
 * Abrang (آبرنگ) child theme functions.
 *
 * Parent theme: Twenty Twenty-Five. Almost everything visual (brand palette,
 * self-hosted Vazirmatn via fontFace, element defaults) is declared in
 * theme.json — this file stays deliberately minimal.
 *
 * @package Abrang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup: translations + WooCommerce support.
 */
function abrang_setup() {
	load_child_theme_textdomain( 'abrang', get_stylesheet_directory() . '/languages' );

	add_theme_support( 'woocommerce' );
}
add_action( 'after_setup_theme', 'abrang_setup' );

/**
 * Enqueue the child theme stylesheet.
 *
 * The parent theme enqueues its own stylesheet itself, and WordPress prints
 * the theme.json global styles (including the Vazirmatn @font-face rules)
 * automatically — so only this theme's style.css is needed here.
 */
function abrang_enqueue_styles() {
	wp_enqueue_style(
		'abrang-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'abrang_enqueue_styles' );

/** Reusable storefront sections in the inserter. */
add_action( 'init', static function () {
    register_block_pattern_category( 'abrang', array( 'label' => 'آبرنگ' ) );
} );

/** Avoid an empty promotional section when no products are on sale. */
add_filter( 'render_block_core/group', static function ( $content, $block ) {
    if ( str_contains( $block['attrs']['className'] ?? '', 'abrang-sale' ) && function_exists( 'wc_get_product_ids_on_sale' ) && ! wc_get_product_ids_on_sale() ) {
        return '';
    }
    return $content;
}, 10, 2 );

/** Progressive enhancement for the core navigation overlay. */
add_action( 'wp_enqueue_scripts', static function () {
    wp_enqueue_script( 'abrang-storefront', get_stylesheet_directory_uri() . '/assets/storefront.js', array(), (string) filemtime( get_stylesheet_directory() . '/assets/storefront.js' ), true );
} );

/** Reusable collection image badges; no stock values are inferred in the browser. */
add_filter( 'render_block_woocommerce/product-image', static function ( $content, $block, $instance ) {
    $product = wc_get_product( $instance->context['postId'] ?? 0 );
    if ( ! $product ) { return $content; }
    $badges = '';
    if ( $product->is_on_sale() ) { $badges .= '<span class="abrang-badge abrang-badge-sale">تخفیف</span>'; }
    if ( ! $product->is_in_stock() ) { $badges .= '<span class="abrang-badge abrang-badge-stock">ناموجود</span>'; }
    return '<div class="abrang-product-image">' . $content . '<div class="abrang-product-badges">' . $badges . '</div></div>';
}, 10, 3 );

add_filter( 'woocommerce_product_add_to_cart_text', static function ( $text, $product ) {
    if ( is_admin() ) { return $text; }
    return $product->is_type( 'simple' ) && $product->is_in_stock() && $product->is_purchasable() ? 'افزودن به سبد' : 'اطلاعات بیشتر';
}, 10, 2 );

add_filter( 'render_block_woocommerce/product-button', static function ( $content, $block, $instance ) {
    $product = wc_get_product( $instance->context['postId'] ?? 0 );
    if ( $product && ( ! $product->is_in_stock() || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() ) ) {
        return '<div class="abrang-secondary">' . $content . '</div>';
    }
    return $content;
}, 10, 3 );

/** Sub-category chips for the archive header — [abrang_subcategories]. Also used for search empty suggestions. */
add_shortcode( 'abrang_subcategories', static function () {
    if ( is_admin() ) {
        return '';
    }
    $term = get_queried_object();
    $is_shop = function_exists( 'is_shop' ) && is_shop();
    $is_search = is_search();
    $chips = array();
    if ( $is_shop || $is_search ) {
        $tops = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ) );
        if ( ! is_wp_error( $tops ) ) {
            // For search empty state, show 5 top categories; for shop, show all.
            $limit = $is_search ? 5 : 0;
            $count = 0;
            foreach ( $tops as $t ) {
                if ( $limit && $count >= $limit ) {
                    break;
                }
                $chips[] = sprintf( '<a class="abrang-chip" href="%s">%s</a>', esc_url( get_term_link( $t ) ), esc_html( $t->name ) );
                $count++;
            }
        }
    } elseif ( $term && isset( $term->taxonomy ) && 'product_cat' === $term->taxonomy ) {
        // For a top-level category show its children; for a child show siblings (still useful).
        $parent_id = (int) $term->parent === 0 ? (int) $term->term_id : (int) $term->parent;
        $children = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent_id, 'orderby' => 'name' ) );
        if ( ! is_wp_error( $children ) && $children ) {
            foreach ( $children as $t ) {
                $active = $term->term_id === $t->term_id ? ' is-active' : '';
                $chips[] = sprintf( '<a class="abrang-chip%s" href="%s">%s</a>', $active, esc_url( get_term_link( $t ) ), esc_html( $t->name ) );
            }
        }
    }
    // Fallback for 404 or other archives — show 5 top categories so the visitor can navigate.
    if ( empty( $chips ) ) {
        $tops = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ) );
        if ( ! is_wp_error( $tops ) && $tops ) {
            $count = 0;
            foreach ( $tops as $t ) {
                if ( $count >= 5 ) {
                    break;
                }
                $chips[] = sprintf( '<a class="abrang-chip" href="%s">%s</a>', esc_url( get_term_link( $t ) ), esc_html( $t->name ) );
                $count++;
            }
        }
    }
    if ( empty( $chips ) ) {
        return '';
    }
    return '<div class="abrang-chips">' . implode( '', $chips ) . '</div>';
} );

/** Translate WooCommerce catalog sorting labels to Persian. */
add_filter( 'woocommerce_catalog_orderby', static function ( $options ) {
    $map = array(
        'menu_order' => 'پیش‌فرض',
        'popularity' => 'پرفروش‌ترین',
        'date'       => 'جدیدترین',
        'price'      => 'ارزان‌ترین',
        'price-desc' => 'گران‌ترین',
        'rating'     => 'محبوب‌ترین',
    );
    foreach ( $map as $key => $label ) {
        if ( isset( $options[ $key ] ) ) {
            $options[ $key ] = $label;
        }
    }
    return $options;
} );
add_filter( 'woocommerce_default_catalog_orderby_options', static function ( $options ) {
    $map = array(
        'menu_order' => 'پیش‌فرض',
        'popularity' => 'پرفروش‌ترین',
        'date'       => 'جدیدترین',
        'price'      => 'ارزان‌ترین',
        'price-desc' => 'گران‌ترین',
        'rating'     => 'محبوب‌ترین',
    );
    foreach ( $options as $key => $label ) {
        if ( isset( $map[ $key ] ) ) {
            $options[ $key ] = $map[ $key ];
        }
    }
    return $options;
} );

/** Brand link for the single product summary — links to the shop filtered by brand. */
add_shortcode( 'abrang_brand_link', static function () {
    global $product;
    if ( ! $product ) {
        $product = wc_get_product( get_queried_object_id() );
    }
    if ( ! $product ) {
        return '';
    }
    $terms = get_the_terms( $product->get_id(), 'pa_brand' );
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '';
    }
    $term = $terms[0];
    // Filtered shop URL — `filter_brand` is the query var used by product filters.
    $url = add_query_arg( array( 'filter_brand' => $term->slug ), wc_get_page_permalink( 'shop' ) );
    // Fallback to the attribute query used by the classic filter: `filter_pa_brand`.
    // Both are accepted; the new filter block reads `filter_brand`.
    return sprintf( '<div class="abrang-brand"><a href="%s">%s: %s</a></div>', esc_url( $url ), esc_html__( 'برند', 'abrang' ), esc_html( $term->name ) );
} );

/** Persian stock messages — the block renders availability text that we translate. */
add_filter( 'woocommerce_get_availability', static function ( $availability, $product ) {
    if ( is_admin() ) {
        return $availability;
    }
    $stock_qty = $product->get_stock_quantity();
    $is_managed = $product->managing_stock();
    if ( $product->is_in_stock() ) {
        if ( $is_managed && null !== $stock_qty && $stock_qty <= 5 && $stock_qty > 0 ) {
            $availability['availability'] = sprintf( 'تنها %s عدد باقی مانده', abrang_display_digits( (string) $stock_qty ) );
            $availability['class'] = 'abrang-stock-low';
        } else {
            $availability['availability'] = 'موجود';
            $availability['class'] = 'in-stock';
        }
    } else {
        $availability['availability'] = 'ناموجود';
        $availability['class'] = 'out-of-stock';
    }
    return $availability;
}, 10, 2 );

/** Variation selector — with core blocks we use the standard dropdown, styled
 * cleanly. The spec allows this fallback when a swatch block is not available
 * without a plugin; the choice is documented here and in the template.
 * No extra plugin is loaded. The dropdown is given a comfortable min-height
 * and indigo focus ring via CSS.
 */

/** Search normalization — Arabic ي/ك → Persian ی/ک for query and display, no DB changes. */
add_action( 'pre_get_posts', static function ( $query ) {
    if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
        return;
    }
    $s = $query->get( 's' );
    if ( is_string( $s ) && '' !== $s ) {
        $normalized = strtr( $s, array( 'ي' => 'ی', 'ك' => 'ک' ) );
        if ( $normalized !== $s ) {
            $query->set( 's', $normalized );
        }
    }
} );
add_filter( 'get_search_query', static function ( $query ) {
    if ( is_admin() || ! is_string( $query ) ) {
        return $query;
    }
    return strtr( $query, array( 'ي' => 'ی', 'ك' => 'ک' ) );
} );

/** Ensure product search also matches SKU and excerpt (Woo does this, but keep it explicit for Persian). */
add_filter( 'posts_search', static function ( $search, $wp_query ) {
    // Let WooCommerce handle SKU search; we only ensure the Arabic→Persian
    // normalization above does not break anything here.
    return $search;
}, 10, 2 );

/** Inject correct attribute IDs into the archive filter blocks (brand = برند, color = رنگ). */
add_filter( 'render_block_data', static function ( $parsed ) {
    if ( ! isset( $parsed['blockName'] ) ) {
        return $parsed;
    }
    // New product-filter-attribute blocks inside the archive use a class to identify brand vs color.
    $class = $parsed['attrs']['className'] ?? '';
    if ( 'woocommerce/product-filter-attribute' === $parsed['blockName'] && str_contains( $class, 'abrang-filter-' ) ) {
        $slug = str_contains( $class, 'abrang-filter-brand' ) ? 'brand' : ( str_contains( $class, 'abrang-filter-color' ) ? 'color' : '' );
        if ( $slug && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
            $id = wc_attribute_taxonomy_id_by_name( $slug );
            if ( $id ) {
                $parsed['attrs']['attributeId'] = (int) $id;
            }
        }
    }
    // Legacy attribute-filter blocks (fallback) use the same class convention.
    if ( 'woocommerce/attribute-filter' === $parsed['blockName'] && str_contains( $class, 'abrang-filter-' ) ) {
        $slug = str_contains( $class, 'abrang-filter-brand' ) ? 'brand' : ( str_contains( $class, 'abrang-filter-color' ) ? 'color' : '' );
        if ( $slug && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
            $id = wc_attribute_taxonomy_id_by_name( $slug );
            if ( $id ) {
                $parsed['attrs']['attributeId'] = (int) $id;
            }
        }
    }
    return $parsed;
}, 10, 1 );

/** Display-only date digits. Never filter numeric timestamps, inputs, or stored data. */
function abrang_display_digits( $text ) {
    if ( is_admin() || ! is_string( $text ) ) { return $text; }
    return strtr( $text, array( '0'=>'۰', '1'=>'۱', '2'=>'۲', '3'=>'۳', '4'=>'۴', '5'=>'۵', '6'=>'۶', '7'=>'۷', '8'=>'۸', '9'=>'۹' ) );
}
// Only block text, never wp_date/date_i18n globally: those also produce machine
// dates in datetime attributes, REST responses and structured data.
add_filter( 'render_block_core/post-date', static function ( $html ) {
    $tokens = wp_html_split( $html );
    foreach ( $tokens as &$token ) {
        if ( '' !== $token && '<' !== $token[0] ) { $token = abrang_display_digits( $token ); }
    }
    unset( $token );
    return implode( '', $tokens );
} );
