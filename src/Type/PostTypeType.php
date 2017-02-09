<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

class PostTypeType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'post_type',
			'description' => __( 'An Post Type object', 'wp-graphql' ),
			'fields' => function() {
				return [
					'id' => Relay::globalIdField(),
				];
			},
			'interfaces' => [
				$node_definition['nodeInterface'],
			],
		];

		parent::__construct( $config );

	}

}