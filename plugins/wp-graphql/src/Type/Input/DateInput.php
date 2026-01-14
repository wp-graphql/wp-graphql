<?php
namespace WPGraphQL\Type\Input;

class DateInput {

	/**
	 * Register the DateInput Input
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_input_type(
			'DateInput',
			[
				'description' => static function () {
					return __( 'Date values', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'year'  => [
							'type'        => 'Int',
							'description' => static function () {
								return __( '4 digit year (e.g. 2017)', 'wp-graphql' );
							},
						],
						'month' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Month number (from 1 to 12)', 'wp-graphql' );
							},
						],
						'day'   => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Day of the month (from 1 to 31)', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
