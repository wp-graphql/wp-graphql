<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'CommentsConnectionOrderbyEnum', [
	'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
	'values' => [
		'COMMENT_AGENT'        => [
			'value' => 'comment_agent',
		],
		'COMMENT_APPROVED'     => [
			'value' => 'comment_approved',
		],
		'COMMENT_AUTHOR'       => [
			'value' => 'comment_author',
		],
		'COMMENT_AUTHOR_EMAIL' => [
			'value' => 'comment_author_email',
		],
		'COMMENT_AUTHOR_IP'    => [
			'value' => 'comment_author_IP',
		],
		'COMMENT_AUTHOR_URL'   => [
			'value' => 'comment_author_url',
		],
		'COMMENT_CONTENT'      => [
			'value' => 'comment_content',
		],
		'COMMENT_DATE'         => [
			'value' => 'comment_date',
		],
		'COMMENT_DATE_GMT'     => [
			'value' => 'comment_date_gmt',
		],
		'COMMENT_ID'           => [
			'value' => 'comment_ID',
		],
		'COMMENT_KARMA'        => [
			'value' => 'comment_karma',
		],
		'COMMENT_PARENT'       => [
			'value' => 'comment_parent',
		],
		'COMMENT_POST_ID'      => [
			'value' => 'comment_post_ID',
		],
		'COMMENT_TYPE'         => [
			'value' => 'comment_type',
		],
		'USER_ID'              => [
			'value' => 'user_id',
		],
		'COMMENT_IN'           => [
			'value' => 'comment__in',
		],
	],
] );
