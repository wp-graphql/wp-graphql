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
						'year'   => [
							'type'        => 'Int',
							'description' => static function () {
								return __( '4 digit year (e.g. 2017)', 'wp-graphql' );
							},
						],
						'month'  => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Month number (from 1 to 12)', 'wp-graphql' );
							},
						],
						'day'    => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Day of the month (from 1 to 31)', 'wp-graphql' );
							},
						],
						'hour'   => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Hour of the day (from 0 to 23)', 'wp-graphql' );
							},
						],
						'minute' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Minute of the hour (from 0 to 59)', 'wp-graphql' );
							},
						],
						'second' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Second of the minute (from 0 to 59)', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
