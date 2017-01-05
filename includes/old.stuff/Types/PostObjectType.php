<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Interfaces\PostObjectInterface;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class PostObjectType extends AbstractObjectType {

	public function getDescription() {
		return __( 'The standard Post Object type', 'wp-graphql' );
	}

	public function build( $config ) {

		/**
		 * Apply the PostObjectInterface
		 */
		$config->applyInterface( new PostObjectInterface() );

		/**
		 * Pass fields through a filter to allow modifications from outside the core plugin
		 *
		 * Filtering this will filter all types that use or extend the PostObjectInterface
		 */
		$fields = apply_filters( 'wpgraphql_post_object_type_fields', [], $config );

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}