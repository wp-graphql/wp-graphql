<?php
// Ensure WordPress functions are loaded before running this.
tests_add_filter( 'init', function () {
    update_option( 'thumbnail_size_w', 150 );
    update_option( 'thumbnail_size_h', 150 );
    update_option( 'medium_size_w', 300 );
    update_option( 'medium_size_h', 300 );
    update_option( 'large_size_w', 1024 );
    update_option( 'large_size_h', 1024 );
});
