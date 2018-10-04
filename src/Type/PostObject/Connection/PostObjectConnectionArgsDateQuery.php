<?php
namespace WPGraphQL\Type\PostObject\Connection;

use GraphQL\Type\Definition\EnumType;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

/**
 * Class PostObjectConnectionArgsDateQuery
 *
 * This defines the input fields for date queries
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class PostObjectConnectionArgsDateQuery extends WPInputObjectType {

	/**
	 * This holds the field definitions
	 * @var array $fields
	 * @since 0.0.5
	 */
	public static $fields;

	/**
	 * DateQueryType constructor.
	 * @since 0.0.5
	 */
	public function __construct( $config = [] ) {
		$config['name'] = 'DateQuery';
		$config['fields'] = self::fields();
		parent::__construct( $config );
	}

	/**
	 * fields
	 *
	 * This defines the fields that make up the DateQueryType
	 *
	 * @return array|null
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) {
			self::$fields = [
				'year'      => [
					'type'        => Types::int(),
					'description' => __( '4 digit year (e.g. 2017)', 'wp-graphql' ),
				],
				'month'     => [
					'type'        => Types::int(),
					'description' => __( 'Month number (from 1 to 12)', 'wp-graphql' ),
				],
				'week'      => [
					'type'        => Types::int(),
					'description' => __( 'Week of the year (from 0 to 53)', 'wp-graphql' ),
				],
				'day'       => [
					'type'        => Types::int(),
					'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
				],
				'hour'      => [
					'type'        => Types::int(),
					'description' => __( 'Hour (from 0 to 23)', 'wp-graphql' ),
				],
				'minute'    => [
					'type'        => Types::int(),
					'description' => __( 'Minute (from 0 to 59)', 'wp-graphql' ),
				],
				'second'    => [
					'type'        => Types::int(),
					'description' => __( 'Second (0 to 59)', 'wp-graphql' ),
				],
				'after'     => [
					'type' => TypeRegistry::get_type( 'DateInput' ),
				],
				'before'    => [
					'type' => TypeRegistry::get_type( 'DateInput' ),
				],
				'inclusive' => [
					'type'        => Types::boolean(),
					'description' => __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' ),
				],
				'compare'   => [
					'type'        => Types::string(),
					'description' => __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' ),
				],
				'column'    => [
					'type'        => TypeRegistry::get_type( 'PostObjectsConnectionDateColumnEnum' ),
					'description' => __( 'Column to query against', 'wp-graphql' ),
				],
				'relation'  => [
					'type'        => Types::relation_enum(),
					'description' => __( 'OR or AND, how the sub-arrays should be compared', 'wp-graphql' ),
				],
			];
		}
		return self::prepare_fields( self::$fields, 'DateQuery' );
	}

}
