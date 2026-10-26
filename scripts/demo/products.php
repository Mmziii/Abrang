<?php
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

// Ensure attribute taxonomies are registered for this request (setup.sh
// calls attributes.php before this file, but direct demo.sh runs also need it).
if ( function_exists( 'wc_register_attribute_taxonomies' ) ) {
    wc_register_attribute_taxonomies();
}

/**
 * Ensure a term exists in the given taxonomy and return its slug.
 * Terms created here for the demo are marked _abrang_demo=1 so
 * demo/remove.php can delete them only when unused.
 */
$abrang_ensure_demo_term = static function ( $taxonomy, $slug, $name ) {
    $term = get_term_by( 'slug', $slug, $taxonomy );
    if ( $term && ! is_wp_error( $term ) ) {
        return $term->slug;
    }
    $result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
    if ( is_wp_error( $result ) ) {
        if ( 'term_exists' === $result->get_error_code() ) {
            return $slug;
        }
        WP_CLI::error( $result );
    }
    update_term_meta( $result['term_id'], '_abrang_demo', 1 );
    return $slug;
};

// Brand term used by every demo product.
$abrang_brand_slug = $abrang_ensure_demo_term( 'pa_brand', 'abrang-demo', 'برند آبرنگ' );
// Size / material / pack-size demo terms.
$abrang_ensure_demo_term( 'pa_size', 'medium', 'متوسط' );
$abrang_ensure_demo_term( 'pa_material', 'paper', 'کاغذ' );
$abrang_ensure_demo_term( 'pa_pack-size', '1', '۱ عدد' );

$names = array( 'خودکار آبی نرم', 'مداد نوکی ساده', 'دفتر خط‌دار', 'برنامه‌ریز هفتگی', 'کاغذ طراحی', 'آبرنگ دوازده رنگ', 'قلم‌مو گرد', 'جامدادی پارچه‌ای', 'ست هندسه', 'منگنه رومیزی', 'پوشه دکمه‌دار', 'چسب کاغذی' );
$cats  = array( 'pens','pencils','notebooks','planners','paper-sheets','watercolor-gouache','brushes-canvas','bags-cases','geometry','staplers','folders','glue-scissors' );
$images = array( 'pen','pencil','notebook','planner','paper-sheets','watercolor','brush','pencil-case','geometry','stapler','folder','glue-stick' );
$color_slugs = array( 'blue','black','red','green' );

foreach ( $names as $i => $name ) {
    $sku = 'DEMO-' . ( $i + 1 );
    $existing_id = wc_get_product_id_by_sku( $sku );
    $is_new = ! $existing_id;
    if ( $existing_id ) {
        $product = wc_get_product( $existing_id );
        // Preserve existing type — simple products only here.
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            WP_CLI::log( "[already done] $sku (type mismatch, kept)" );
            continue;
        }
    } else {
        $product = new WC_Product_Simple();
        $product->set_name( $name . ' — نمونه آزمایشی' );
        $product->set_sku( $sku );
        $product->set_status( 'publish' );
        $product->set_description( 'محصول ساختگی برای طراحی؛ قابل خرید واقعی نیست.' );
    }

    $price = array(45000,85000,120000,180000,65000,380000)[ $i % 6 ];
    $product->set_regular_price( $price );
    if ( 0 === $i % 4 ) {
        $product->set_sale_price( $price * 0.8 );
    } else {
        $product->set_sale_price( '' );
    }
    if ( 0 === $i % 5 ) {
        $product->set_stock_status( 'outofstock' );
        $product->set_manage_stock( false );
        $product->set_stock_quantity( null );
    } else {
        $product->set_stock_status( 'instock' );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( 12 + $i );
    }
    $product->set_weight( 100 + $i * 10 );

    $term = get_term_by( 'slug', $cats[ $i ], 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        $product->set_category_ids( array( (int) $term->term_id, (int) $term->parent ) );
    }

    // Demo attributes — visible on the product page, not used for variations.
    $color_slug = $color_slugs[ $i % 4 ];
    $attrs = array();
    // Brand (taxonomy)
    $a = new WC_Product_Attribute();
    $a->set_id( wc_attribute_taxonomy_id_by_name( 'brand' ) );
    $a->set_name( 'pa_brand' );
    $a->set_options( array( $abrang_brand_slug ) );
    $a->set_position( 0 );
    $a->set_visible( true );
    $a->set_variation( false );
    $attrs[] = $a;
    // Color (taxonomy)
    $a = new WC_Product_Attribute();
    $a->set_id( wc_attribute_taxonomy_id_by_name( 'color' ) );
    $a->set_name( 'pa_color' );
    $a->set_options( array( $color_slug ) );
    $a->set_position( 1 );
    $a->set_visible( true );
    $a->set_variation( false );
    $attrs[] = $a;
    // Size (taxonomy)
    $a = new WC_Product_Attribute();
    $a->set_id( wc_attribute_taxonomy_id_by_name( 'size' ) );
    $a->set_name( 'pa_size' );
    $a->set_options( array( 'medium' ) );
    $a->set_position( 2 );
    $a->set_visible( true );
    $a->set_variation( false );
    $attrs[] = $a;
    // Material (taxonomy)
    $a = new WC_Product_Attribute();
    $a->set_id( wc_attribute_taxonomy_id_by_name( 'material' ) );
    $a->set_name( 'pa_material' );
    $a->set_options( array( 'paper' ) );
    $a->set_position( 3 );
    $a->set_visible( true );
    $a->set_variation( false );
    $attrs[] = $a;
    // Pack-size (taxonomy)
    $a = new WC_Product_Attribute();
    $a->set_id( wc_attribute_taxonomy_id_by_name( 'pack-size' ) );
    $a->set_name( 'pa_pack-size' );
    $a->set_options( array( '1' ) );
    $a->set_position( 4 );
    $a->set_visible( true );
    $a->set_variation( false );
    $attrs[] = $a;

    $product->set_attributes( $attrs );
    $product->update_meta_data( '_abrang_demo', 1 );

    // Tags for the demo — a single shared tag so tag archives can be verified.
    $tag = get_term_by( 'slug', 'demo-tag', 'product_tag' );
    if ( ! $tag ) {
        $created = wp_insert_term( 'نمونه', 'product_tag', array( 'slug' => 'demo-tag' ) );
        if ( ! is_wp_error( $created ) ) {
            update_term_meta( $created['term_id'], '_abrang_demo', 1 );
            $tag = get_term( $created['term_id'], 'product_tag' );
        }
    }
    if ( $tag && ! is_wp_error( $tag ) ) {
        $product->set_tag_ids( array( (int) $tag->term_id ) );
    }

    $id = $product->save();

    if ( $is_new ) {
        $file = wp_tempnam( 'demo.png' );
        copy( __DIR__ . '/images/' . $images[ $i ] . '.png', $file );
        $attachment = media_handle_sideload( array( 'name'=>"$sku.png", 'tmp_name'=>$file ), $id, 'تصویر نمونه آزمایشی' );
        if ( is_wp_error( $attachment ) ) { WP_CLI::error( $attachment ); }
        update_post_meta( $attachment, '_abrang_demo', 1 );
        update_post_meta( $attachment, '_wp_attachment_image_alt', 'تصویر آزمایشی ' . $name );
        $product->set_image_id( $attachment );
        $product->save();
        WP_CLI::log( "Created $sku" );
    } else {
        WP_CLI::log( "[already done] $sku" );
    }
}

// -------------------------------------------------------------------------
// Variable demo product: خودکار با ۴ رنگ — one product, four variations by
// colour. Different stock per variation, one out of stock, images per
// variation. Used to design and test the variable-product UI.
// -------------------------------------------------------------------------
$var_sku = 'DEMO-VAR-1';
$var_colors = array(
    'blue'  => array( 'label' => 'آبی',  'stock' => 12, 'price' => 85000, 'image' => 'pen-blue' ),
    'black' => array( 'label' => 'مشکی', 'stock' => 5,  'price' => 85000, 'image' => 'pen-black' ),
    'red'   => array( 'label' => 'قرمز', 'stock' => 0,  'price' => 85000, 'image' => 'pen-red' ),
    'green' => array( 'label' => 'سبز',  'stock' => 3,  'price' => 90000, 'image' => 'pen-green' ),
);
$existing_var_id = wc_get_product_id_by_sku( $var_sku );
if ( $existing_var_id ) {
    WP_CLI::log( "[already done] $var_sku" );
} else {
    $variable = new WC_Product_Variable();
    $variable->set_name( 'خودکار چهار رنگ — نمونه آزمایشی' );
    $variable->set_sku( $var_sku );
    $variable->set_status( 'publish' );
    $variable->set_description( 'خودکار با چهار رنگ متفاوت برای نمایش انتخاب رنگ و موجودی متغیر.' );
    $variable->set_short_description( 'خودکار با ۴ رنگ — نمونه آزمایشی برای بررسی محصول متغیر.' );
    $variable->update_meta_data( '_abrang_demo', 1 );
    $term = get_term_by( 'slug', 'pens', 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        $variable->set_category_ids( array( (int) $term->term_id, (int) $term->parent ) );
    }
    // Tag
    $tag = get_term_by( 'slug', 'demo-tag', 'product_tag' );
    if ( $tag && ! is_wp_error( $tag ) ) {
        $variable->set_tag_ids( array( (int) $tag->term_id ) );
    }
    // Attribute: pa_color with all four options, used for variations.
    $attr = new WC_Product_Attribute();
    $attr->set_id( wc_attribute_taxonomy_id_by_name( 'color' ) );
    $attr->set_name( 'pa_color' );
    $attr->set_options( array_keys( $var_colors ) );
    $attr->set_position( 0 );
    $attr->set_visible( true );
    $attr->set_variation( true );
    $variable->set_attributes( array( $attr ) );

    // Brand attribute visible as well.
    $attr2 = new WC_Product_Attribute();
    $attr2->set_id( wc_attribute_taxonomy_id_by_name( 'brand' ) );
    $attr2->set_name( 'pa_brand' );
    $attr2->set_options( array( $abrang_brand_slug ) );
    $attr2->set_position( 1 );
    $attr2->set_visible( true );
    $attr2->set_variation( false );
    $variable->set_attributes( array( $attr, $attr2 ) );

    $variable_id = $variable->save();

    // Parent image.
    $file = wp_tempnam( 'demo.png' );
    copy( __DIR__ . '/images/pen.png', $file );
    $attachment = media_handle_sideload( array( 'name'=>"$var_sku.png", 'tmp_name'=>$file ), $variable_id, 'تصویر نمونه آزمایشی' );
    if ( ! is_wp_error( $attachment ) ) {
        update_post_meta( $attachment, '_abrang_demo', 1 );
        update_post_meta( $attachment, '_wp_attachment_image_alt', 'تصویر آزمایشی خودکار چهار رنگ' );
        $variable->set_image_id( $attachment );
        $variable->save();
    }

    foreach ( $var_colors as $slug => $info ) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id( $variable_id );
        $variation->set_attributes( array( 'pa_color' => $slug ) );
        $variation->set_sku( $var_sku . '-' . strtoupper( $slug ) );
        $variation->set_status( 'publish' );
        $variation->set_regular_price( $info['price'] );
        // No sale price for variations in the demo.
        $variation->set_manage_stock( true );
        $variation->set_stock_quantity( $info['stock'] );
        $variation->set_stock_status( $info['stock'] > 0 ? 'instock' : 'outofstock' );
        $variation->update_meta_data( '_abrang_demo', 1 );
        $vid = $variation->save();

        // Variation image.
        $file = wp_tempnam( 'demo.png' );
        copy( __DIR__ . '/images/' . $info['image'] . '.png', $file );
        $attachment = media_handle_sideload( array( 'name'=> $var_sku . '-' . $slug . '.png', 'tmp_name'=>$file ), $vid, 'تصویر نمونه آزمایشی ' . $info['label'] );
        if ( ! is_wp_error( $attachment ) ) {
            update_post_meta( $attachment, '_abrang_demo', 1 );
            update_post_meta( $attachment, '_wp_attachment_image_alt', 'تصویر آزمایشی ' . $info['label'] );
            $variation->set_image_id( $attachment );
            $variation->save();
        }
        WP_CLI::log( "Created variation $var_sku-" . strtoupper( $slug ) . " stock {$info['stock']}" );
    }
    WP_CLI::log( "Created $var_sku" );
}

wc_delete_product_transients();
