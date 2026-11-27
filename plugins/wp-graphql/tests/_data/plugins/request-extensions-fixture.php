<?php
/**
 * Plugin Name: WPGraphQL Request Extensions Test Fixture
 * Description: Registers request extension namespaces and a field that echoes them, so the functional suite can exercise the request extension transports over real HTTP. Activated and deactivated by RequestExtensionsTransportCept.
 *
 * @package WPGraphQL\Tests
 */

add_action(
	'graphql_register_request_extensions',
	static function ( \WPGraphQL\Registry\RequestExtensionRegistry $registry ) {
		$identity = static function ( $value ) {
			return $value;
		};

		$registry->register_extension(
			'postPassword',
			[
				'description' => 'Post passwords supplied by the client.',
				'header'      => 'X-GraphQL-Post-Password',
				'cache'       => 'no-store',
				'members'     => [
					'postId'   => [
						'type'     => 'integer',
						'sanitize' => $identity,
					],
					'password' => [
						'type'     => 'string',
						'sanitize' => $identity,
					],
				],
			]
		);

		$registry->register_extension(
			'locale',
			[
				'description' => 'The locale to resolve the request in.',
				'header'      => 'X-GraphQL-Locale',
				'cache'       => 'vary',
				'members'     => [
					'code' => [
						'type'     => 'string',
						'sanitize' => 'sanitize_text_field',
					],
				],
			]
		);
	}
);

add_action(
	'init',
	static function () {
		register_graphql_preview_context_member(
			'translationLocale',
			[
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			]
		);
	}
);

add_action(
	'graphql_register_types',
	static function () {
		register_graphql_field(
			'RootQuery',
			'requestExtensionFixture',
			[
				'type'        => 'String',
				'description' => 'Test fixture: the parsed request extension namespace, as JSON.',
				'args'        => [
					'name' => [ 'type' => [ 'non_null' => 'String' ] ],
				],
				'resolve'     => static function ( $root, array $args, \WPGraphQL\AppContext $context ) {
					return wp_json_encode( $context->get_request_extension( $args['name'] ) );
				},
			]
		);
	}
);
