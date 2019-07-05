<?php

namespace WPGraphQL\Type;

register_graphql_enum_type( 'PluginStatusEnum', [
	'description'  => __( 'The status of the plugin object', 'wp-graphql' ),
	'values'       => [
		'ACTIVE' => [
			'value' => 'active',
		],
		'DROP_IN'  => [
			'value' => 'drop_in',
		],
		'INACTIVE' => [
			'value' => 'inactive',
		],
		'MUST_USE' => [
			'value' => 'must_use',
		],
		'RECENTLY_ACTIVE' => [
			'value' => 'recently_active',
		],
		'UPGRADE' => [
			'value' => 'upgrade',
		],
	],
] );
