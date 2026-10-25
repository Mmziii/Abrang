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
