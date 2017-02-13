<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class PostObjectOrderbyEnumType extends EnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name' => 'orderby',
			'values' => self::values(),
		];

		parent::__construct( $config );

	}

	private static function values() {

		if ( null === self::$values ) {

			self::$values = [
				[
					'name'        => 'NONE',
					'value'       => 'none',
					'description' => __( 'No order', 'wp-graphql' ),
				],
				[
					'name'        => 'ID',
					'value'       => 'ID',
					'description' => __( 'Order by the object\'s id. Note the capitalization', 'wp-graphql' ),
				],
				[
					'name'        => 'AUTHOR',
					'value'       => 'author',
					'description' => __( 'Order by author', 'wp-graphql' ),
				],
				[
					'name'        => 'TITLE',
					'value'       => 'title',
					'description' => __( 'Order by title', 'wp-graphql' ),
				],
				[
					'name'        => 'SLUG',
					'value'       => 'name',
					'description' => __( 'Order by slug', 'wp-graphql' ),
				],
				[
					'name'        => 'DATE',
					'value'       => 'date',
					'description' => __( 'Order by date', 'wp-graphql' ),
				],
				[
					'name'        => 'MODIFIED',
					'value'       => 'modified',
					'description' => __( 'Order by last modified date', 'wp-graphql' ),
				],
				[
					'name'        => 'PARENT',
					'value'       => 'parent',
					'description' => __( 'Order by parent ID', 'wp-graphql' ),
				],
				[
					'name'        => 'COMMENT_COUNT',
					'value'       => 'comment_count',
					'description' => __( 'Order by number of comments', 'wp-graphql' ),
				],
				[
					'name'        => 'RELEVANCE',
					'value'       => 'relevance',
					'description' => __( 'Order by search terms in the following order: First, whether the entire 
					sentence is matched. Second, if all the search terms are within the titles. Third, if any of the 
					search terms appear in the titles. And, fourth, if the full sentence appears in the 
					contents.', 'wp-graphql' ),
				],
				[
					'name'        => 'IN',
					'value'       => 'post__in',
					'description' => __( 'Preserve the ID order given in the IN array', 'wp-graphql' ),
				],
				[
					'name'        => 'NAME_IN',
					'value'       => 'post_name__in',
					'description' => __( 'Preserve slug order given in the NAME_IN array', 'wp-graphql' ),
				],
			];

		}

		return ! empty( self::$values ) ? self::$values : null;

	}

}
