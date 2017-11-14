#!/usr/bin/env bash

WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

serve_site() {
    cd $WP_CORE_DIR
    wp server --port=9999
}

serve_site
exit 1