<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\TermType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TermsType extends AbstractObjectType  {

	public function getDescription() {
		return __( 'The base get_terms query with info about the query and a list of queried items', 'wp-graphql' );
	}

	public function build( $config ) {

		$config->addField(
			'items',
			[
				'type' => new ListType( new TermType() ),
				'description' => __( 'The current page of the paginated request', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->items ) ) ? $value->items : [];
				}
			]
		);

		$config->addField(
			'page',
			[
				'type' => new IntType(),
				'description' => __( 'The current page of the paginated request', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->page ) ? absint( $value->page ) : 0;
				}
			]
		);

		$config->addField(
			'per_page',
			[
				'type' => new IntType(),
				'description' => __( 'The number of items displayed in the current paginated request', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->per_page ) ? absint( $value->per_page ) : 0;
				}
			]
		);

		$config->addField(
			'taxonomy',
			[
				'type' => new StringType(),
				'description' => __( 'The taxonomy type', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->taxonomy ) ? esc_html( $value->taxonomy ) : 'category';
				}
			]
		);

		$config->addField(
			'total',
			[
				'type' => new IntType(),
				'description' => __( 'The total number of terms that match the current query', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->total ) ? absint( $value->total ) : 0;
				}
			]
		);

		$config->addField(
			'total_pages',
			[
				'type' => new IntType(),
				'description' => __( 'The total number of pages', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->total_pages ) ? absint( $value->total_pages ) : 0;
				}
			]
		);


	}

}