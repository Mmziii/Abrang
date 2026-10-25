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
