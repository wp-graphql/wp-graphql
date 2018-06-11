<?php

/**
 * Clear the uploads folder
 */
$_wp_content_upload_dir = WP_CONTENT_DIR . '/uploads';
system( 'rm -rf ' . escapeshellarg( $_wp_content_upload_dir ), $retval );
