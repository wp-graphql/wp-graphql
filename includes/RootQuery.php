<?php
namespace DFM\WPGraphQL;

use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class RootQuery
 *
 * This sets up the RootQuery
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class RootQuery extends AbstractObjectType {

	/**
	 * Add the root Query Types
	 *
	 * @param ObjectTypeConfig $config
	 * @return mixed
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * Filter the root query fields to allow
		 * root queries to be added from outside of the
		 * core plugin
		 *
		 * @since 0.0.2
		 */
		$fields = apply_filters( 'graphql_root_queries', [] );

		/**
		 * Ensure the $fields are a populated array
		 */
		if ( ! empty( $fields ) && is_array( $fields ) ) {

			/**
			 * addFields
			 *
			 * Pass the fields through a filter to allow additional fields to
			 * be easily added
			 *
			 * @since 0.0.1
			 */
			$config->addFields( $fields );

		}

	}

}