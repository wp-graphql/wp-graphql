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
				'description' => __( 'Date values', 'wp-graphql' ),
				'fields'      => [
					'year'  => [
						'type'        => 'Int',
						'description' => __( '4 digit year (e.g. 2017)', 'wp-graphql' ),
					],
					'month' => [
						'type'        => 'Int',
						'description' => __( 'Month number (from 1 to 12)', 'wp-graphql' ),
					],
					'day'   => [
						'type'        => 'Int',
						'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
					],
				],
			]
		);
	}
}

