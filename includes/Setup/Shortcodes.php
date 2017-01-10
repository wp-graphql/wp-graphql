<?php
namespace DFM\WPGraphQL\Setup;

class Shortcodes {

	public function init() {

		add_action( 'wpgraphql_root_queries', [ $this, 'setup_queries' ], 10, 1 );

	}

	public function setup_queries( $fields ) {

		global $shortcode_tags;

		if ( ! empty( $shortcode_tags ) && is_array( $shortcode_tags ) ) {

			foreach( $shortcode_tags as $shortcode_tag => $callback ) {

				$query_class = '\DFM\WPGraphQL\Types\Shortcodes\ShortcodeQueryType';
				$shortcode_query_class = apply_filters( 'wpgraphql_shortcode_query_class', $query_class, $shortcode_tag );
				$query_class = class_exists( $shortcode_query_class ) ? $shortcode_query_class : $query_class;

				$query_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $shortcode_tag );
				$query_name = preg_replace( '/[^A-Za-z0-9]/i', '',  ucwords( $query_name ) );


				$fields[] = new $query_class([
					'query_name' => $query_name
				]);

			}

		}

		return $fields;

	}

}