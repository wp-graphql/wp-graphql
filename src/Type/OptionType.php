<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

class OptionType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'option',
			'description' => __( 'An Option object', 'wp-graphql' ),
			'fields' => function() {
				return [
					'id' => Relay::globalIdField(),
				];
			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
			'resolveField' => function( $value, $args, $context, ResolveInfo $info ) {
				if ( method_exists( $this, $info->fieldName ) ) {
					return $this->{ $info->fieldName }( $value, $args, $context, $info );
				} else {
					return $value->{ $info->fieldName };
				}
			},
		];

		parent::__construct( $config );

	}

}