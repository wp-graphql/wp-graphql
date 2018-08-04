<?php

namespace WPGraphQL\Type\Widget;

use GraphQLRelay\Relay;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class WidgetType
 *
 * @package WPGraphQL\Type\Widget
 * @since   0.0.31
 */
class WidgetType extends WPObjectType {

	/**
	 * Type name
	 *
	 * @var string $type_name
	 */
	private static $type_name = 'Menu';

	/**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * WidgetType constructor.
	 */
	public function __construct() {
		$config = [
			
		];

		parent::__construct( $config );
	}

	/**
	 * This defines the fields that make up the MenuType.
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {

				$fields = [
					
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}