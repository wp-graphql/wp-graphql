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
					'name'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The display name of the taxonomy. This field is equivalent to WP_Taxonomy->label', 'wp-graphql' ),
					],
					'slug'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The url friendly name of the taxonomy. This field is equivalent to WP_Taxonomy->name', 'wp-graphql' ),
					],
					'description'     => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Description of the taxonomy. This field is equivalent to WP_Taxonomy->description', 'wp-graphql' ),
					],
					'show_cloud'      => [
						'type'        => Types::boolean(),
						'description' => esc_html__( 'Whether to show the taxonomy as part of a tag cloud widget. This field is equivalent to WP_Taxonomy->show_tagcloud', 'wp-graphql' ),
					],
					'hierarchical'    => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Whether the taxonomy is hierarchical. This field is equivalent to WP_Taxonomy->hierarchical', 'wp-graphql' ),
					],
				];
			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
		];

		parent::__construct( $config );

	}
}
