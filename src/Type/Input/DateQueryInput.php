<?php

namespace WPGraphQL\Type;

register_graphql_input_type( 'DateQueryInput', [
	'description' => __( 'Filter the connection based on input', 'wp-graphql' ),
	'fields'      => [
		'year'      => [
			'type'        => 'Int',
			'description' => __( '4 digit year (e.g. 2017)', 'wp-graphql' ),
		],
		'month'     => [
			'type'        => 'Int',
			'description' => __( 'Month number (from 1 to 12)', 'wp-graphql' ),
		],
		'week'      => [
			'type'        => 'Int',
			'description' => __( 'Week of the year (from 0 to 53)', 'wp-graphql' ),
		],
		'day'       => [
			'type'        => 'Int',
			'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
		],
		'hour'      => [
			'type'        => 'Int',
			'description' => __( 'Hour (from 0 to 23)', 'wp-graphql' ),
		],
		'minute'    => [
			'type'        => 'Int',
			'description' => __( 'Minute (from 0 to 59)', 'wp-graphql' ),
		],
		'second'    => [
			'type'        => 'Int',
			'description' => __( 'Second (0 to 59)', 'wp-graphql' ),
		],
		'after'     => [
			'type' => 'DateInput',
		],
		'before'    => [
			'type' => 'DateInput',
		],
		'inclusive' => [
			'type'        => 'Boolean',
			'description' => __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' ),
		],
		'compare'   => [
			'type'        => 'String',
			'description' => __( 'For after/before, whether exact value should be matched or not', 'wp-graphql' ),
		],
		'column'    => [
			'type'        => 'PostObjectsConnectionDateColumnEnum',
			'description' => __( 'Column to query against', 'wp-graphql' ),
		],
		'relation'  => [
			'type'        => 'RelationEnum',
			'description' => __( 'OR or AND, how the sub-arrays should be compared', 'wp-graphql' ),
		],
	]
] );
