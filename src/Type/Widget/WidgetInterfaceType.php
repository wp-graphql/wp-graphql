<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\InterfaceType;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPInterfaceType;
use WPGraphQL\Type\Widget\WidgetTypes;
use WPGraphQL\Types;

/**
 * Class WidgetType
 *
 * @package WPGraphQL\Type\Widget
 * @since   0.0.31
 */
class WidgetInterfaceType extends WPInterfaceType {

	/**
	 * Type name
	 *
	 * @var string $type_name
	 */
  private static $type_name = 'Widget';
  
  /**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
  private static $fields;
  
  /**
   * This holds the type registry instance
   * 
   * @var WidgetTypes $registry
   */
  private static $registry;

	/**
	 * WidgetInterfaceType constructor.
	 */
	public function __construct() {
		$config = [
      'name'        => self::$type_name,
			'description' => __( 'A widget object', 'wp-graphql' ),
			'fields' => self::fields(),
      'resolveType' => function( $widget ) {
        if( ! empty( $widget[ 'type' ] ) ) {
          return WidgetTypes::{ $widget[ 'type' ] }();
        }
        return self;
			},
		];

    parent::__construct( $config );

    /**
     * Initialize Registry
     */
    self::$registry = new WidgetTypes( self::$type_name );
	}

	/**
	 * This defines the fields that make up the WidgetInterfaceType.
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {

        $fields = [
          'id'          => [
            'type'    => Types::non_null( Types::id() ),
            'resolve' => function( array $widget, $args, AppContext $context, ResolveInfo $info ) {
              return ( ! empty( $widget ) && ! empty( $widget[ 'id' ] ) ) ? Relay::toGlobalId( 'widget', $widget[ 'id' ] ) : null;
            },
          ],
          'widgetId'          => [
            'type'    => Types::string(),
            'resolve' => function( array $widget, $args, AppContext $context, ResolveInfo $info ) {
              return ( ! empty( $widget[ 'id' ] ) ) ? $widget[ 'id' ] : '';
            },
          ],
          'name'          => [
            'type'    => Types::string(),
            'resolve' => function( array $widget, $args, AppContext $context, ResolveInfo $info ) {
              return ( ! empty( $widget[ 'name' ] ) ) ? $widget[ 'name' ] : '';
            },
          ],
          'basename'          => [
            'type'    => Types::string(),
            'resolve' => function( array $widget, $args, AppContext $context, ResolveInfo $info ) {
              return ( ! empty( $widget[ 'type' ] ) ) ? $widget[ 'type' ] : '';
            },
          ],
        ];

        return self::prepare_fields( $fields, self::$type_name );
      };

    } // End if().

    return ! empty( self::$fields ) ? self::$fields : null;

  }

}