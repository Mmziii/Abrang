<?php
// Only explicit markers authorize deletion, never a SKU or filename alone.
$ids = get_posts( array( 'post_type'=>array('product','attachment'), 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids', 'meta_key'=>'_abrang_demo', 'meta_value'=>'1' ) );
foreach ( $ids as $id ) {
    if ( 'attachment' === get_post_type( $id ) ) { wp_delete_attachment( $id, true ); }
    else { wp_delete_post( $id, true ); }
}
wc_delete_product_transients();
WP_CLI::success( 'Demo content removed; unmarked content preserved.' );
