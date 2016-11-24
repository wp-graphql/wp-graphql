<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\AttachmentType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;

class AttachmentsType extends PostsType {

	public function getDescription() {
		return __( 'The base Attachments Type with info about the query and a list of queried items', 'wp-graphqhl' );
	}

	public function build( $config ) {
		$config->addField(
			'items',
			[
				'type' => new ListType( new AttachmentType() ),
				'description' => __( 'List of items matching the query', 'dfm-graphql-endpoints' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->posts ) && is_array( $value->posts ) ) ? $value->posts : [];
				}
			]
		);
	}
}