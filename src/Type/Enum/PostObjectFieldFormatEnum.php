<?php

namespace WPGraphQL\Type;

register_graphql_enum_type( 'PostObjectFieldFormatEnum', [
	'description' => __( 'The format of post field data.', 'wp-graphql' ),
	'values'      => [
		'RAW'      => [
			'name'        => 'RAW',
			'description' => __( 'Provide the field value directly from database', 'wp-graphql' ),
			'value'       => 'raw',
		],
		'RENDERED' => [
			'name'        => 'RENDERED',
			'description' => __( 'Apply the default WordPress rendering', 'wp-graphql' ),
			'value'       => 'rendered',
		],
	]
] );
