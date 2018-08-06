<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\InterfaceType;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Widget\WidgetTypes;
use WPGraphQL\Types;

/**
 * Class WidgetType
 *
 * @package WPGraphQL\Type\Widget
 * @since   0.0.31
 */
class WidgetType extends InterfaceType {

	/**
	 * Type name
	 *
	 * @var string $type_name
	 */
	private static $type_name = 'Widget';

	/**
	 * WidgetType constructor.
	 */
	public function __construct() {
		$config = [
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
    
    add_filter( 'graphql_executable_schema', function( $executable_schema ) {

      $executable_schema[ 'types' ] = WidgetTypes::get_types();
  
      return $executable_schema;
    } );
	}

	/**
   * This defines the fields that make up the WidgetType.
   *
   * @return array
   */
  private static function fields() {

    $fields = array(
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
		);

    $fields = apply_filters( "graphql_widget_fields", $fields );

    return $fields;
  }

}