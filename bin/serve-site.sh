#!/usr/bin/env bash

apt-get install nginx
apt-get install php5-fpm
cp .travis_nginx.conf /etc/nginx/nginx.conf
/etc/init.d/nginx restart

serve_site() {
    cd $WP_CORE_DIR
    wp server --port=9999
}

serve_site
exit 1