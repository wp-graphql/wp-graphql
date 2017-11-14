#!/usr/bin/env bash

function tnw-tpl {
	sed \
	-e "s|{WP_CORE_DIR}|$WP_CORE_DIR|g" \
	-e "s|{WP_PORT}|$WP_PORT|g" \
	-e "s|{NGINX_DIR}|$NGINX_DIR|g" \
	-e "s|{USER}|$USER|g" \
	-e "s|{PHP_VERSION}|$PHP_VERSION|g" \
	-e "s|{PORT}|$PORT|g" \
	-e "s|{SERVER}|$SERVER|g" \
	< $1 > $2
}

function tnw-install-nginx {
	TPL_DIR=${TPL_DIR-$HOME/.composer/vendor/typisttech/travis-nginx-wordpress/tpl}
	NGINX_DIR=${NGINX_DIR-$HOME/nginx}
	WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}
	WP_PORT=${WP_PORT-8080}
	USER=$(whoami)
	PHP_VERSION=$(phpenv version-name)
	PORT=9000
	SERVER="/tmp/php.sock"

	# Make some working directories.
	mkdir -p "$NGINX_DIR"
	mkdir -p "$NGINX_DIR/sites-enabled"
	mkdir -p "$NGINX_DIR/var"

	PHP_FPM_BIN="$HOME/.phpenv/versions/$PHP_VERSION/sbin/php-fpm"
	PHP_FPM_CONF="$NGINX_DIR/php-fpm.conf"

	# Build the php-fpm.conf.
	tnw-tpl "$TPL_DIR/php-fpm.tpl.conf" "$PHP_FPM_CONF"

	# Start php-fpm
	"$PHP_FPM_BIN" --fpm-config "$PHP_FPM_CONF"

	# Build the default nginx config files.
	tnw-tpl "$TPL_DIR/nginx.tpl.conf" "$NGINX_DIR/nginx.conf"
	tnw-tpl "$TPL_DIR/fastcgi.tpl.conf" "$NGINX_DIR/fastcgi.conf"
	tnw-tpl "$TPL_DIR/default-site.tpl.conf" "$NGINX_DIR/sites-enabled/default-site.conf"

	# Start nginx.
	nginx -c "$NGINX_DIR/nginx.conf"
}

tnw-install-nginx