<?php

namespace WPGraphQL\Type\Sidebar;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class SidebarQuery
 *
 * @package WPGraphQL\Type\Sidebar
 * @since   0.0.31
 */
class SidebarQuery {
  /**
	 * Holds the root_query field definition
	 *
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Holds the sidebar_by field definition
	 *
	 * @var array $sidebar_by
	 */
	private static $sidebar_by;

	/**
	 * Method that returns the root query field definition
	 *
	 * @return array
	 * @since  0.0.31
	 */
	public static function root_query() {
    if ( null === self::$root_query ) {

			self::$root_query = [
				'type' => Types::sidebar(),
				'description' => __( 'A WordPress sidebar', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_sidebar( $id_components['id'] );
				},
			];

		}

		return self::$root_query;
	}
	
	/**
	 * Method that returns the "sidebar_by" field definition to get a sidebar by id or name.
	 *
	 * @return array
	 */
	public static function sidebar_by() {
    if ( null === self::$sidebar_by ) {

			self::$sidebar_by = [
				'type' => Types::sidebar(),
				'description' => __( 'A WordPress sidebar', 'wp-graphql' ),
				'args' => [
					'id' 		=> Types::string(),
					'name' 	=> Types::string(),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {

					$sidebar = null;

					if( ! empty( $args[ 'id' ] ) ) {
						$sidebar = DataSource::resolve_sidebar( $args[ 'id' ] );
					}
					if ( ! empty( $args[ 'name' ] ) ) {
						$sidebar = DataSource::resolve_sidebar( $args[ 'name' ], 'name' );
					}

					return $sidebar;

				},
			];

		}

		return self::$sidebar_by;
  }
}