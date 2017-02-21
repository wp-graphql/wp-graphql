<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Types;

class DateQueryType extends InputObjectType {

	private static $date_after;
	private static $date_before;
	private static $column;

	/**
	 * DateQueryType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		$config = [
			'name'   => 'DateQuery',
			'fields' => function() {

				$fields = [
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
						'type' => self::date_after(),
					],
					'before'    => [
						'type' => self::date_before(),
					],
					'inclusive' => [
						'type'        => Types::boolean(),
						'description' => __( 'For after/before, whether exact value should be 
												matched or not', 'wp-graphql' ),
					],
					'compare'   => [
						'type'        => Types::string(),
						'description' => __( 'For after/before, whether exact value should be 
												matched or not', 'wp-graphql' ),
					],
					'column'    => [
						'type'        => self::column_enum(),
						'description' => __( 'Column to query against', 'wp-graphql' ),
					],
					'relation'  => [
						'type'        => Types::relation_enum(),
						'description' => __( 'OR or AND, how the sub-arrays should be compared', 'wp-graphql' ),
					],
				];

				/**
				 * Pass the fields through a filter
				 *
				 * @param array $fields
				 *
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_date_query_type_fields', $fields );

				/**
				 * Sort the fields alphabetically by key. This makes reading through docs much easier
				 * @since 0.0.2
				 */
				ksort( $fields );

				return $fields;
			},
		];

		parent::__construct( $config );

	}

	/**
	 * column_enum
	 *
	 * Creates an Enum type with the columns that can be queried against for the DateQuery
	 *
	 * @return EnumType|null
	 * @since 0.0.5
	 */
	private static function column_enum() {

		if ( null === self::$column ) {

			self::$column = new EnumType( [
				'name'   => 'dateColumn',
				'values' => [
					[
						'name'  => 'DATE',
						'value' => 'post_date',
					],
					[
						'name'  => 'MODIFIED',
						'value' => 'post_modified',
					],
				],
			] );

		}

		return ! empty( self::$column ) ? self::$column : null;

	}

	/**
	 * date_after
	 *
	 * Creates the date_after input field that allows "after" paramaters
	 * to be entered
	 *
	 * @return InputObjectType|null
	 * @since 0.0.5
	 */
	private static function date_after() {

		if ( null === self::$date_after ) {

			self::$date_after = new InputObjectType( [
				'name'   => 'dateAfter',
				'fields' => [
					'year'  => [
						'type'        => Types::int(),
						'description' => __( '4 digit year (e.g. 2017)', 'wp-graphql' ),
					],
					'month' => [
						'type'        => Types::int(),
						'description' => __( 'Month number (from 1 to 12)', 'wp-graphql' ),
					],
					'day'   => [
						'type'        => Types::int(),
						'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
					],
				],
			] );

		}

		return ! empty( self::$date_after ) ? self::$date_after : null;

	}

	/**
	 * date_before
	 *
	 * Creates the date_before input field that allows "before" paramaters
	 * to be entered
	 *
	 * @return InputObjectType|null
	 * @since 0.0.5
	 */
	private static function date_before() {

		if ( null === self::$date_before ) {

			self::$date_before = new InputObjectType( [
				'name'   => 'dateBefore',
				'fields' => [
					'year'  => [
						'type'        => Types::int(),
						'description' => __( '4 digit year (e.g. 2017)', 'wp-graphql' ),
					],
					'month' => [
						'type'        => Types::int(),
						'description' => __( 'Month number (from 1 to 12)', 'wp-graphql' ),
					],
					'day'   => [
						'type'        => Types::int(),
						'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
					],
				],
			] );

		}

		return ! empty( self::$date_before ) ? self::$date_before : null;

	}

}
