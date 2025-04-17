<?php

namespace WPGraphQL\Type\Enum;

class PostObjectsConnectionOrderbyEnum {

	/**
	 * Register the PostObjectsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'PostObjectsConnectionOrderbyEnum',
			[
				'description' => __( 'Content sorting attributes for post-type objects. Identifies which content property should be used to determine result order.', 'wp-graphql' ),
				'values'      => [
					'AUTHOR'        => [
						'value'       => 'post_author',
						'description' => __( 'Ordering by content author (typically by author name).', 'wp-graphql' ),
					],
					'COMMENT_COUNT' => [
						'value'       => 'comment_count',
						'description' => __( 'Ordering by popularity based on number of comments.', 'wp-graphql' ),
					],
					'DATE'          => [
						'value'       => 'post_date',
						'description' => __( 'Chronological ordering by publication date.', 'wp-graphql' ),
					],
					'IN'            => [
						'value'       => 'post__in',
						'description' => __( 'Maintain custom order of IDs exactly as specified in the query with the IN field.', 'wp-graphql' ),
					],
					'MENU_ORDER'    => [
						'value'       => 'menu_order',
						'description' => __( 'Ordering by manually defined sort position.', 'wp-graphql' ),
					],
					'MODIFIED'      => [
						'value'       => 'post_modified',
						'description' => __( 'Chronological ordering by modified date.', 'wp-graphql' ),
					],
					'NAME_IN'       => [
						'value'       => 'post_name__in',
						'description' => __( 'Maintain custom order of IDs exactly as specified in the query with the NAME_IN field.', 'wp-graphql' ),
					],
					'PARENT'        => [
						'value'       => 'post_parent',
						'description' => __( 'Ordering by parent-child relationship in hierarchical content.', 'wp-graphql' ),
					],
					'SLUG'          => [
						'value'       => 'post_name',
						'description' => __( 'Alphabetical ordering by URL-friendly name.', 'wp-graphql' ),
					],
					'TITLE'         => [
						'value'       => 'post_title',
						'description' => __( 'Alphabetical ordering by content title', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
