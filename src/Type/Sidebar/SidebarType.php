<?php

namespace WPGraphQL\Type\Sidebar;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\Widget\Connection\WidgetConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class SidebarType
 *
 * @package WPGraphQL\Type\Sidebar
 * @since   0.0.31
 */
class SidebarType extends WPObjectType {

	/**
	 * Type name
	 *
	 * @var string $type_name
	 */
	private static $type_name = 'Sidebar';

	/**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * SidebarType constructor.
	 */
	public function __construct() {

		$config = [
			'name' => self::$type_name,
			'description' => __( 'A sidebar object', 'wp-graphql' ),
			'fields' => self::fields(),
			'interfaces' => [ self::node_interface() ],
		];

		parent::__construct( $config );
	}

	/**
	 * This defines the fields that make up the SidebarType.
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {

				$fields = [
					'id'          => [
						'type'    => Types::non_null( Types::id() ),
						'resolve' => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $sidebar ) && ! empty( $sidebar['id'] ) ) ? Relay::toGlobalId( 'sidebar', $sidebar['id'] ) : null;
						},
					],
					'sidebarId'        => [
						'type'        => Types::string(),
						'description' => __( 'WP ID of the sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['id'] ) ? $sidebar['id'] : '';
						},
					],
					'name'        => [
						'type'        => Types::string(),
						'description' => __( 'Display name of the sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['name'] ) ? $sidebar['name'] : '';
						},
					],
					'description'        => [
						'type'        => Types::string(),
						'description' => __( 'Description of the sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['description'] ) ? $sidebar['description'] : '';
						},
					],
					'class'        => [
						'type'        => Types::string(),
						'description' => __( 'HTML class attribute of the sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['class'] ) ? $sidebar['class'] : '';
						},
					],
					'beforeWidget'        => [
						'type'        => Types::string(),
						'description' => __( 'HTML Tags used before each widget in sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['before_widget'] ) ? $sidebar['before_widget'] : '';
						},
					],
					'afterWidget'        => [
						'type'        => Types::string(),
						'description' => __( 'HTML Tags used after each widget in sidebar.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['after_widget'] ) ? $sidebar['after_widget'] : '';
						},
					],
					'beforeTitle'        => [
						'type'        => Types::string(),
						'description' => __( 'HTML Tags used before sidebar title display.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['before_title'] ) ? $sidebar['before_title'] : '';
						},
					],
					'afterTitle'        => [
						'type'        => Types::string(),
						'description' => __( 'HTML Tags used after sidebar title display.', 'wp-graphql' ),
						'resolve'     => function( array $sidebar, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $sidebar['after_title'] ) ? $sidebar['after_title'] : '';
						},
					],
					'widgets' => WidgetConnectionDefinition::connection( self::$type_name )
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}