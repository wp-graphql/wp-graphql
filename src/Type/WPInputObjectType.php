<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;

/**
 * Class WPInputObjectType
 *
 * Input types should extend this class to take advantage of the helper methods for formatting
 * and adding consistent filters.
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class WPInputObjectType extends InputObjectType {

	/**
	 * WPInputObjectType constructor.
	 *
	 * @param array $config The configuration for the InputObjectType
	 */
	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	/**
	 * format_enum_name
	 *
	 * This formats enum_names to be all caps with underscores for spaces/word-breaks
	 *
	 * @param $name
	 * @return string
	 * @since 0.0.5
	 */
	public static function format_enum_name( $name ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $name ) );
	}

}
