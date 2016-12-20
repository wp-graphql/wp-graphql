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

	}

}