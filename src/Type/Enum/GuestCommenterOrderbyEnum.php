<?php
namespace WPGraphQL\Type\Enum;

class GuestCommenterOrderbyEnum {

	/**
	 * Register the GuestCommenterOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'GuestCommenterOrderbyEnum',
			[
				'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
				'values'      => [
					'NAME'  => [
						'description' => __( 'Order by name of the guest commenter.', 'wp-graphql' ),
						'value'       => 'comment_author',
					],
					'EMAIL' => [
						'description' => __( 'Order by e-mail of the guest commenter.', 'wp-graphql' ),
						'value'       => 'comment_author_email',
					],
					'URL'   => [
						'description' => __( 'Order by URL address of the guest commenter.', 'wp-graphql' ),
						'value'       => 'comment_author_url',
					],
				],
			]
		);
	}
}
