<?php
$pages = array( 'about'=>'درباره ما', 'contact'=>'تماس با ما', 'terms'=>'قوانین و مقررات', 'privacy-policy'=>'حریم خصوصی', 'shipping'=>'شیوه‌های ارسال', 'returns'=>'رویه بازگرداندن کالا', 'faq'=>'سوالات متداول' );
foreach ( $pages as $slug => $title ) {
    $existing = get_page_by_path( $slug );
    if ( $existing ) {
        if ( 'privacy-policy' === $slug && 'draft' === $existing->post_status ) {
            wp_update_post( array( 'ID'=>$existing->ID, 'post_status'=>'publish', 'post_title'=>$title, 'post_content'=>'این صفحه به‌زودی تکمیل می‌شود.' ) );
        }
        WP_CLI::log( "[already done] page $slug" ); continue;
    }
    $args = array( 'post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$slug, 'post_title'=>$title, 'post_content'=>'این صفحه به‌زودی تکمیل می‌شود.' );
    if ( 'privacy-policy' === $slug && get_post( (int) get_option( 'wp_page_for_privacy_policy' ) ) ) {
        $args['ID'] = (int) get_option( 'wp_page_for_privacy_policy' );
    }
    $id = wp_insert_post( $args, true );
    if ( is_wp_error( $id ) ) { WP_CLI::error( $id ); }
    if ( 'privacy-policy' === $slug ) { update_option( 'wp_page_for_privacy_policy', $id ); }
}
