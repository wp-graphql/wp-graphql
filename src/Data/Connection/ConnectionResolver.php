<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class ConnectionResolver {

	/**
	 * @var mixed
	 */
	protected $source;

	/**
	 * @var array
	 */
	protected $args;

	/**
	 * @var AppContext
	 */
	protected $context;

	/**
	 * @var ResolveInfo
	 */
	protected $info;

	/**
	 * @var array array
	 */
	protected $query_args;

	/**
	 * ConnectionResolver constructor.
	 *
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 */
	public function __construct( $source, $args, $context, $info ) {

		/**
		 * Set the source (the root object) for the resolver
		 */
		$this->source = $source;

		/**
		 * Set the args for the resolver
		 */
		$this->args = $args;

		/**
		 * Set the context of the resolver
		 */
		$this->context = $context;

		/**
		 * Set the resolveInfo for the resolver
		 */
		$this->info = $info;

	}

}
