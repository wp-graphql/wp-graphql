<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class TaxonomyType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'taxonomy',
			'description' => __( 'A taxonomy object', 'wp-graphql' ),
			'fields' => function() {
				return [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( $taxonomy, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $info->parentType ) && ! empty( $taxonomy->name ) ) ? Relay::toGlobalId( $info->parentType, $taxonomy->name ) : null;
						},
					],
				];
			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
		];

		parent::__construct( $config );

	}
}
