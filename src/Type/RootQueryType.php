<?php
namespace WPGraphQL\Type;

use WPGraphQL\Types;

/**
 * Class RootQueryType
 * The RootQueryType is the primary entry for Queries in the GraphQL Schema.
 * @package WPGraphQL\Type
 * @since 0.0.4
 */
class RootQueryType extends WPObjectType {

	protected static $type_name;
	protected static $fields;

	/**
	 * RootQueryType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {self::$type_name = 'RootQuery';

		/**
		 * Configure the RootQuery
		 * @since 0.0.5
		 */
		$config = [
			'name' => self::$type_name,
			'fields' => self::fields(),
		];

		/**
		 * Pass the config to the parent construct
		 * @since 0.0.5
		 */
		parent::__construct( $config );

	}

	public static function fields() {

		if ( null === self::$fields ) {
			self::$fields = function() {

				$fields = [
					'hello' => [
						'type' => Types::string(),
						'resolve' => function() {
							return 'world';
						}
					]
				];

				/**
				 * Pass the root queries through a filter.
				 * This allows fields to be added or removed.
				 * NOTE: Use this filter with care. Before removing existing fields seriously consider deprecating the field, as
				 * that will allow the field to still be used and not break systems that rely on it, but just not be present
				 * in Schema documentation, etc.
				 * If the behavior of a field needs to be changed, depending on the change, it might be better to consider adding
				 * a new field with the new behavior instead of overriding an existing field. This will allow existing fields
				 * to behave as expected, but will allow introduction of new fields with different behavior at any point.
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_root_queries', $fields );
				$fields = self::prepare_fields( $fields, self::$type_name );
				return $fields;
			};
		}

		return self::$fields;

	}
}
