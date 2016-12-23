<?php
namespace DFM\WPGraphQL\Setup;

/**
 * Class Init
 *
 * Hook into GraphQL to make it aware of various Entities
 *
 * @package DFM\WPGraphQL\Setup
 * @since 0.0.2
 */
class Init {

	/**
	 * Init constructor.
	 *
	 * This sets up the Root Queries by filtering the "wpgraphql_root_queries" filter
	 * to add query entry points for core WP data types
	 *
	 * @since 0.0.2
	 */
	public function init() {

		/**
		 * Sets up queries related to Post objects
		 *
		 * @since 0.0.2
		 */
		add_filter( 'wpgraphql_root_queries', array( new PostEntities(), 'init' ), 10, 1 );

		/**
		 * Sets up queries related to Taxonomies & Terms
		 *
		 * @since 0.0.2
		 */
		// add_filter( 'wpgraphql_root_queries', array( $this, 'taxonomy_queries' ), 10, 1 );

		/**
		 * Sets up queries related to Comments
		 *
		 * @since 0.0.2
		 */
		// add_filter( 'wpgraphql_root_queries', array( $this, 'comment_queries' ), 10, 1 );

		/**
		 * Sets up queries related to Options
		 *
		 * @since 0.0.2
		 */
		// add_filter( 'wpgraphql_root_queries', array( $this, 'option_queries' ), 10, 1 );

		/**
		 * Sets up queries related to Shortcodes
		 *
		 * @since 0.0.2
		 */
		// add_filter( 'wpgraphql_root_queries', array( $this, 'shortcode_queries' ), 10, 1 );

		/**
		 * Sets up queries related to Widgets
		 *
		 * @since 0.0.2
		 */
		// add_filter( 'wpgraphql_root_queries', array( $this, 'widget_queries' ), 10, 1 );

	}

}