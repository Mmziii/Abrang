<?php
// Native Persian WooCommerce price display option; preserve all unrelated settings.
$options = get_option( 'PW_Options', array() );
if ( 'yes' === ( $options['persian_price'] ?? '' ) ) {
    WP_CLI::log( '[already done] Persian WooCommerce price digits' );
} else {
    $options['persian_price'] = 'yes';
    update_option( 'PW_Options', $options );
    WP_CLI::success( 'Persian WooCommerce price digits enabled.' );
}
