<?php
namespace DFM\WPGraphQL\Queries;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class RootQueryType
 *
 * This sets up the RootQueryType
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class RootQueryType extends AbstractObjectType {

	/**
	 * Add the root Query Types
	 *
	 * @param ObjectTypeConfig $config
	 * @return mixed
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * addFields
		 *
		 * Pass the fields through a filter to allow additional fields to
		 * be easily added
		 *
		 * @since 0.0.1
		 */
		$config->addFields( apply_filters( 'wpgraphql_root_queries', [] ) );

	}

	/**
	 * setup
	 *
	 * This sets up the Root Queries by filtering the "wpgraphql_root_queries" filter
	 * to add query entry points for core WP data types
	 *
	 * @since 0.0.2
	 */
	public function setup() {

		/**
		 * Sets up queries related to Post objects
		 *
		 * @since 0.0.2
		 */
		add_filter( 'wpgraphql_root_queries', array( new \DFM\WPGraphQL\Queries\Posts\PostsQueries, 'setup_post_queries' ), 10, 1 );

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