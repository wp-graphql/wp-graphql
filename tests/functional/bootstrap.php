<?php
define( 'WP_AUTO_UPDATE_CORE', false );
add_filter( 'enable_maintenance_mode', function() {
	return false;
} );
