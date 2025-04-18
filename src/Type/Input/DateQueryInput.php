<?php

namespace WPGraphQL\Type\Input;

class DateQueryInput {

	/**
	 * Register the DateQueryInput Input
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_input_type(
			'DateQueryInput',
			[
				'description' => static function () {
					return __( 'Filter the connection based on input', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'year'      => [
							'type'        => 'Int',
							'description' => static function () {
									return __( '4 digit year (e.g. 2017)', 'wp-graphql' );
							},
						],
						'month'     => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Month number (from 1 to 12)', 'wp-graphql' );
							},
						],
						'week'      => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Week of the year (from 0 to 53)', 'wp-graphql' );
							},
						],
						'day'       => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Day of the month (from 1 to 31)', 'wp-graphql' );
							},
						],
						'hour'      => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Hour (from 0 to 23)', 'wp-graphql' );
							},
						],
						'minute'    => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Minute (from 0 to 59)', 'wp-graphql' );
							},
						],
						'second'    => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Second (0 to 59)', 'wp-graphql' );
							},
						],
						'after'     => [
							'type'        => 'DateInput',
							'description' => static function () {
								return __( 'Nodes should be returned after this date', 'wp-graphql' );
							},
						],
						'before'    => [
							'type'        => 'DateInput',
							'description' => static function () {
								return __( 'Nodes should be returned before this date', 'wp-graphql' );
							},
						],
						'inclusive' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' );
							},
						],
						'compare'   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' );
							},
						],
						'column'    => [
							'type'        => 'PostObjectsConnectionDateColumnEnum',
							'description' => static function () {
								return __( 'Column to query against', 'wp-graphql' );
							},
						],
						'relation'  => [
							'type'        => 'RelationEnum',
							'description' => static function () {
								return __( 'OR or AND, how the sub-arrays should be compared', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
