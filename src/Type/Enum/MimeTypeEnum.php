<?php

namespace WPGraphQL\Type;

$values = [
	'IMAGE_JPEG' => [
		'value' => 'image/jpeg',
	],
];

$allowed_mime_types = get_allowed_mime_types();

if ( ! empty( $allowed_mime_types ) ) {
	$values = [];
	foreach ( $allowed_mime_types as $mime_type ) {
		$values[ WPEnumType::get_safe_name( $mime_type ) ] = [
			'value' => $mime_type,
		];
	}
}

register_graphql_enum_type(
	'MimeTypeEnum',
	[
		'description' => __( 'The MimeType of the object', 'wp-graphql' ),
		'values'      => $values,
	]
);
