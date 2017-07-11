<?php
namespace WPGraphQL\Type\MenuItem\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Menu\MenuQuery;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class MenuItemConnectionDefinition
 *
 * @package WPGraphQL\Type\Comment\Connection
 */
class MenuItemConnectionDefinition {

	/**
	 * Stores some date for the Relay connection for term objects
	 *
	 * @var array $connection
	 * @access private
	 */
	private static $connection;

	/**
	 * Stores the fields for the "where" args input type
	 * @var array $where_args
	 */
	private static $where_args;

	/**
	 * Method that sets up the relay connection for term objects
	 *
	 * @return mixed
	 */
	public static function connection() {

		if ( null === self::$connection ) :

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::menu_item(),
				'name' => 'menuItems',
			] );

			$args = [
				'where' => [
					'name' => 'where',
					'type' => self::where_args(),
				],
			];

			self::$connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of menu item objects', 'wp-graphql' ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, AppContext $context, ResolveInfo $info ) {
					return DataSource::resolve_menu_items_connection( $source, $args, $context, $info );
				},
			];
		endif;
		return ! empty( self::$connection ) ? self::$connection : null;
	}

	/**
	 * Defines the "where" args that can be used to query menuItems
	 * @return array|WPInputObjectType
	 */
	private static function where_args() {

		if ( null === self::$where_args ) {
			self::$where_args = new WPInputObjectType([
				'name' => 'menuQueryArgs',
				'fields' => function() {
					return [
						'menuSlug' => [
							'type' => Types::string(),
							'description' => __( 'The slug of the menu to query items for', 'wp-graphql' ),
						],
						'menuLocation' => [
							'type' => MenuQuery::menu_enum(),
							'description' => __( 'If populated, the active menu from this location will be used to resolve the items.', 'wp-graphql' ),
							'defaultValue' => null,
						],
						'parentMenuItemId' => [
							'type' => Types::id(),
							'description' => __( 'The global ID for the parent menu item', 'wp-graphql' ),
							'defaultValue' => null,
						],
					];
				},
			]);
		}

		return self::$where_args;

	}

}
