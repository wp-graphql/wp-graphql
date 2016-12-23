<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Queries\PostsQuery;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TermType extends AbstractObjectType {

	public function getDescription() {
		return __( 'The base WordPress Term Type', 'wp-graphql' );
	}

	public function build( $config ) {

		$config->addField( new PostsQuery() );

		$config->addField(
			'id',
			[
				'type' => new NonNullType( new IntType() ),
				'description' => __( 'The ID of the term', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->term_id ) ? absint( $value->term_id ) : 0;
				}
			]
		);

		$config->addField(
			'name',
			[
				'type' => new StringType(),
				'description' => __( 'The name of the term', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->name ) ? esc_html( $value->name ) : 'n/a';
				}
			]
		);

		$config->addField(
			'slug',
			[
				'type' => new StringType(),
				'description' => __( 'The slug of the term', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->slug ) ? esc_html( $value->slug ) : '';
				}
			]
		);

		$config->addField(
			'term_group_id',
			[
				'type' => new IntType(),
				'description' => __( 'The term_group the term belongs to', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->term_group ) ? esc_html( $value->term_group ) : 0;
				}
			]
		);

		$config->addField(
			'term_taxonomy_id',
			[
				'type' => new IntType(),
				'description' => __( 'The ID of the term taxonomy', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->term_taxonomy_id ) ? absint( $value->term_taxonomy_id ) : 0;
				}
			]
		);

		$config->addField(
			'taxonomy_name',
			[
				'type' => new StringType(),
				'description' => __( 'The taxonomy the term belongs to', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->taxonomy ) ? esc_html( $value->taxonomy ) : '';
				}
			]
		);

		$config->addField(
			'description',
			[
				'type' => new StringType(),
				'description' => __( 'The description of the term', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->description ) ? esc_html( $value->description ) : '';
				}
			]
		);

		$config->addField(
			'parent_id',
			[
				'type' => new IntType(),
				'description' => __( 'The taxonomy the term belongs to', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->parent ) ? absint( $value->parent ) : 0;
				}
			]
		);

		$config->addField(
			'count',
			[
				'type' => new IntType(),
				'description' => __( 'The number of items associated with the term', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->count ) ? absint( $value->count ) : 0;
				}
			]
		);

	}

}