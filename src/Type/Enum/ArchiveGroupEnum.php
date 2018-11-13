<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'ArchiveGroupEnum', [
	'description' => __( 'Archive grouping types', 'wp-graphql' ),
	'values' => [
		'YEARLY' => [
			'value'	=> 'yearly',
		],
		'MONTHLY' => [
			'value'	=> 'monthly',
		],
		'DAILY' => [
			'value'	=> 'daily',
		],
		'WEEKLY' => [
			'value'	=> 'weekly',
		],
		'POSTBYPOST' => [
			'value'	=> 'postbypost',
		],
		'ALPHA' => [
			'value'	=> 'alpha',
		],
	]
] );