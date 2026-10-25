<?php
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$names = array( 'خودکار آبی نرم', 'مداد نوکی ساده', 'دفتر خط‌دار', 'برنامه‌ریز هفتگی', 'کاغذ طراحی', 'آبرنگ دوازده رنگ', 'قلم‌مو گرد', 'جامدادی پارچه‌ای', 'ست هندسه', 'منگنه رومیزی', 'پوشه دکمه‌دار', 'چسب کاغذی' );
$cats = array( 'pens','pencils','notebooks','planners','paper-sheets','watercolor-gouache','brushes-canvas','bags-cases','geometry','staplers','folders','glue-scissors' );
foreach ( $names as $i => $name ) {
    $sku = 'DEMO-' . ( $i + 1 );
    if ( wc_get_product_id_by_sku( $sku ) ) { WP_CLI::log( "[already done] $sku" ); continue; }
    $product = new WC_Product_Simple();
    $product->set_name( $name . ' — نمونه آزمایشی' );
    $product->set_sku( $sku );
    $product->set_status( 'publish' );
    $product->set_description( 'محصول ساختگی برای طراحی؛ قابل خرید واقعی نیست.' );
    $price = array(45000,85000,120000,180000,65000,380000)[$i % 6];
    $product->set_regular_price( $price );
    if ( 0 === $i % 4 ) { $product->set_sale_price( $price * 0.8 ); }
    if ( 0 === $i % 5 ) { $product->set_stock_status( 'outofstock' ); }
    $term = get_term_by( 'slug', $cats[$i], 'product_cat' );
    $product->set_category_ids( array( $term->term_id, $term->parent ) );
    $product->update_meta_data( '_abrang_demo', 1 );
    $id = $product->save();
    $file = wp_tempnam( 'demo.png' );
    copy( __DIR__ . '/images/' . array(0,0,1,1,1,2,2,3,3,4,4,4)[$i] . '.png', $file );
    $attachment = media_handle_sideload( array( 'name'=>"$sku.png", 'tmp_name'=>$file ), $id, 'تصویر نمونه آزمایشی' );
    if ( is_wp_error( $attachment ) ) { WP_CLI::error( $attachment ); }
    update_post_meta( $attachment, '_abrang_demo', 1 );
    update_post_meta( $attachment, '_wp_attachment_image_alt', 'تصویر آزمایشی ' . $name );
    $product->set_image_id( $attachment );
    $product->save();
}
wc_delete_product_transients();
