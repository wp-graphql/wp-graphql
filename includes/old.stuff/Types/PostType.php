<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Interfaces\PostObjectInterface;

class PostType extends PostObjectType {

	public function getDescription() {
		return __( 'The Post type', 'wp-graphql' );
	}

	public function build( $config ) {

		/**
		 * Get the fields defined by the parent class
		 *
		 * @since 0.0.2
		 */
		$fieldsList = parent::getFields();

		/**
		 * Pass fields through a filter to allow modifications from outside the core plugin
		 *
		 * Filtering this will filter all types that use or extend the PostObjectInterface
		 *
		 * @since 0.0.2
		 * @return array
		 */
		$fields = apply_filters( 'wpgraphql_post_type_fields', $fieldsList, $config );

		/**
		 * Add the fields to the config
		 *
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

		/**
		 * Build the class
		 */
		parent::build( $config );

	}

}