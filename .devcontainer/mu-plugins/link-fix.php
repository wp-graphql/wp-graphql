<?php
/*
Plugin Name: Local WP Live Link Helper
Plugin URI: https://localwp.com
Description: Makes WordPress URL functions relative to play nicely with Live Links.
Version: 2.0
Author: Flywheel
Author URI: http://getflywheel.com
License: GPLv2 or later
*/

class LocalWP_Live_Link_Helper {
	/**
	 * host/domain parsed from 'home' option
	 *
	 * @var string|null
	 */
	// public $home_domain;

	public function __construct() {
		// $this->home_domain = str_replace( '/^www./', '', parse_url( get_option( 'home' ), PHP_URL_HOST ) );

		$this->add_filters();
	}

	/**
	 * @return string Host from server along with port re-added if using localhost routing mode.
	 */
	// public function get_local_host() {
	// 	$original = str_replace( '/^www./', '', $_SERVER['HTTP_HOST'] );

	// 	/**
	// 	 * If using localhost and the port isn't in HTTP_HOST then it needs to be re-added.
	// 	 */
	// 	if ( $original === 'localhost' ) {
	// 		$original .= ':' . $_SERVER['SERVER_PORT'];
	// 	}

	// 	return $original;
	// }

	/**
	 * Convenience method to get tunnel domain from headers.
	 *
	 * @return string
	 */
	// public function get_tunnel_host() {
	// 	return $_SERVER['HTTP_X_ORIGINAL_HOST'];
	// }

	/**
	 * @return void
	 */
	public function add_filters() {
		/**
		 * Do not add any of these filters if the X-Original-Host header is missing.
		 */
		// if ( empty( $_SERVER['HTTP_X_ORIGINAL_HOST'] ) ) {
		// 	return;
		// }

		add_action( 'send_headers', [ $this, 'send_private_cache_control_header' ], 9999 );
		// add_action( 'send_headers', [ $this, 'send_local_host_header' ], 9999 );

		remove_action( 'template_redirect', 'redirect_canonical' );

		$local_to_tunnel_filters = [
			'get_rest_url',
			'wp_redirect',
			'bloginfo_url',
			'the_permalink',
			'wp_list_pages',
			'wp_list_categories',
			'the_content_more_link',
			'the_content',
			'the_tags',
			'the_author_posts_link',
			'post_link',
			'post_type_link',
			'page_link',
			'attachment_link',
			'get_shortlink',
			'post_type_archive_link',
			'get_pagenum_link',
			'get_comments_pagenum_link',
			'term_link',
			'search_link',
			'day_link',
			'month_link',
			'year_link',
			'option_siteurl',
			'blog_option_siteurl',
			'option_home',
			'admin_url',
			'get_admin_url',
			'get_site_url',
			'network_admin_url',
			'home_url',
			'includes_url',
			'site_url',
			'site_option_siteurl',
			'network_home_url',
			'network_site_url',
			'get_the_author_url',
			'get_comment_link',
			'wp_get_attachment_image_src',
			'wp_get_attachment_thumb_url',
			'wp_get_attachment_url',
			'wp_login_url',
			'wp_logout_url',
			'wp_lostpassword_url',
			'get_stylesheet_uri',
			'get_locale_stylesheet_uri',
			'script_loader_src',
			'style_loader_src',
			'get_theme_root_uri',
			'theme_root_uri',
			'plugins_url',
			'stylesheet_directory_uri',
			'template_directory_uri',
			'wp_admin_css',
			// 'wp_admin_css_uri',
			// 'wp_admin_scripts_uri',
		];

		// foreach ( $local_to_tunnel_filters as $local_to_tunnel_filter ) {
			// add_filter( $local_to_tunnel_filter, 'wp_make_link_relative', 9999 );
		// }

		// add_filter( 'pre_update_option', [ $this, 'make_link_local' ] );
		// add_filter( 'wp_insert_post_data', [ $this, 'make_link_local_in_posts' ], 9999 );
	}

	/**
	 * Prevent possible cache poisoning by sending Cache-Control private header whenever the domain is replaced.
	 *
	 * @return void
	 */
	public function send_private_cache_control_header() {
		header( 'Cache-Control: private' );
	}

	/**
	 * Send original domain so the tunnel server can perform a replacement with it.
	 */
	// public function send_local_host_header() {
	// 	header( 'X-Local-Host: ' . $this->get_local_host() );
	// }

	/**
	 * Convenience method for replacing old host with new host.
	 *
	 * @param string $old Host to be replaced
	 * @param string $new Host to use as replacement
	 * @param string $subject Replacement subject
	 */
	// public function replace_host( $old, $new, $subject ) {
	// 	$subject = str_replace( 'www.' . $old, $new, $subject );
	// 	$subject = str_replace( $old, $new, $subject );

	// 	return $subject;
	// }

	/**
	 * Generic replacement of the site's local hostname to the tunnel hostname
	 *
	 * @param string $str String provided by the filter
	 *
	 * @return string String with local hostname replaced with the tunnel hostname
	 */
	// public function make_link_tunnel( $str ) {
	// 	$local_host  = $this->get_local_host();
	// 	$tunnel_host = $this->get_tunnel_host();

	// 	$str = $this->replace_host( $local_host, $tunnel_host, $str );
	// 	$str = $this->replace_host( $this->home_domain, $tunnel_host, $str );

	// 	/**
	// 	 * Force HTTPS, but not for local dev testing setup ($tunnel_host ends with tunnel.testing:<port> )
	// 	 */
	// 	if ( ! preg_match( '/^.*tunnel\.testing:\d+$/i', $tunnel_host ) ) {
	// 		$str = str_replace( 'http://' . $tunnel_host, 'https://' . $tunnel_host, $str );
	// 	}

	// 	return $str;
	// }

	/**
	 * Generic replacement of the site's tunnel hostname to the local hostname,
	 * used when saving options to the database via the pre_update_option filter hook
	 *
	 * @param mixed $option Option being saved, provided by the filter. Will
	 *                      usually be a serialized string, but may be an
	 *                      unserialized type in some cases. See:
	 *                      https://developer.wordpress.org/reference/functions/get_option/
	 */
	public function make_link_local( $option ) {
		if ( gettype( $option ) !== 'string' ) {
			$old_str = serialize( $option );
			$new_str = wp_make_link_relative( $old_str );
			return unserialize( $new_str );
		}

		return wp_make_link_relative( $option );
	}

	/**
	 * Go through post properties and replace the tunnel host with the local host
	 *
	 * @param \WP_Post $post
	 */
	public function make_link_local_in_posts( $post ) {
		$post['post_content'] = $this->make_link_local( $post['post_content'] );

		return $post;
	}
}

new LocalWP_Live_Link_Helper();
