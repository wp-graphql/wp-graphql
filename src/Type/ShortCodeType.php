<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

class ShortcodeType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'shortcode',
			'description' => __( 'A shortcode object', 'wp-graphql' ),
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