<?php
namespace WPGraphQL;

use WPGraphQL\RootQuery;
use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Schema\AbstractSchema;

/**
 * Class Schema
 *
 * This sets up the base GraphQL Schema
 *
 * @package WPGraphQL
 * @since 0.0.1
 */
class Schema extends AbstractSchema {

	/**
	 * build
	 *
	 * Build the Schema by applying the RootQueryType and RootMutationType
	 *
	 * @param SchemaConfig $config
	 *
	 * @since 0.0.1
	 */
	public function build( SchemaConfig $config ) {

		/**
		 * Add the QueryTypes and MutationTypes
		 * @since 0.0.1
		 */
		$config->setQuery( new RootQuery() );

		// @todo: Figure out a good pattern for mutations

	}

}