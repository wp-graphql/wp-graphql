<?php

namespace WPGraphQL;

use GraphQL\Error\UserError;
use WPGraphQL\Data\Loader\CommentAuthorLoader;
use WPGraphQL\Data\Loader\CommentLoader;
use WPGraphQL\Data\Loader\EnqueuedScriptLoader;
use WPGraphQL\Data\Loader\EnqueuedStylesheetLoader;
use WPGraphQL\Data\Loader\MenuItemLoader;
use WPGraphQL\Data\Loader\PluginLoader;
use WPGraphQL\Data\Loader\PostObjectLoader;
use WPGraphQL\Data\Loader\PostTypeLoader;
use WPGraphQL\Data\Loader\TaxonomyLoader;
use WPGraphQL\Data\Loader\TermObjectLoader;
use WPGraphQL\Data\Loader\ThemeLoader;
use WPGraphQL\Data\Loader\UserLoader;
use WPGraphQL\Data\Loader\UserRoleLoader;
use WPGraphQL\Data\NodeResolver;
use WPGraphQL\Model\Term;

/**
 * Class AppContext
 * Creates an object that contains all of the context for the GraphQL query
 * This class gets instantiated and populated in the main WPGraphQL class.
 *
 * The context is passed to each resolver during execution.
 *
 * Resolvers have the ability to read and write to context to pass info to nested resolvers.
 *
 * @package WPGraphQL
 */
class AppContext {

	/**
	 * Stores the url string for the current site
	 *
	 * @var string $root_url
	 */
	public $root_url;

	/**
	 * Stores the WP_User object of the current user
	 *
	 * @var \WP_User $viewer
	 */
	public $viewer;

	/**
	 * Stores everything from the $_REQUEST global
	 *
	 * @var \mixed $request
	 */
	public $request;

	/**
	 * Stores additional $config properties
	 *
	 * @var \mixed $config
	 */
	public $config;

	/**
	 * Passes context about the current connection being resolved
	 *
	 * @var mixed| String | null
	 */
	public $currentConnection = null;

	/**
	 * Passes context about the current connection
	 *
	 * @var array
	 */
	public $connectionArgs = [];

	/**
	 * Stores the loaders for the class
	 *
	 * @var array
	 */
	public $loaders = [];

	/**
	 * Instance of the NodeResolver class to resolve nodes by URI
	 *
	 * @var NodeResolver
	 */
	public $node_resolver;

	/**
	 * AppContext constructor.
	 */
	public function __construct() {

		/**
		 * Create a list of loaders to be available in AppContext
		 */
		$loaders = [
			'comment_author'      => new CommentAuthorLoader( $this ),
			'comment'             => new CommentLoader( $this ),
			'enqueued_script'     => new EnqueuedScriptLoader( $this ),
			'enqueued_stylesheet' => new EnqueuedStylesheetLoader( $this ),
			'plugin'              => new PluginLoader( $this ),
			'post'                => new PostObjectLoader( $this ),
			'post_type'           => new PostTypeLoader( $this ),
			'taxonomy'            => new TaxonomyLoader( $this ),
			'term'                => new TermObjectLoader( $this ),
			'theme'               => new ThemeLoader( $this ),
			'user'                => new UserLoader( $this ),
			'user_role'           => new UserRoleLoader( $this ),
		];

		/**
		 * This filters the data loaders, allowing for additional loaders to be
		 * added to the AppContext or for existing loaders to be replaced if
		 * needed.
		 *
		 * @params array $loaders The loaders accessible in the AppContext
		 * @params AppContext $this The AppContext
		 */
		$this->loaders = apply_filters( 'graphql_data_loaders', $loaders, $this );

		/**
		 * This sets up the NodeResolver to allow nodes to be resolved by URI
		 *
		 * @param AppContext $this The AppContext instance
		 */
		$this->node_resolver = new NodeResolver( $this );

		/**
		 * This filters the config for the AppContext.
		 *
		 * This can be used to store additional context config, which is available to resolvers
		 * throughout the resolution of a GraphQL request.
		 *
		 * @params array $config The config array of the AppContext object
		 */
		$this->config = apply_filters( 'graphql_app_context_config', $this->config );
	}

	/**
	 * Retrieves loader assigned to $key
	 *
	 * @param string $key The name of the loader to get
	 *
	 * @return mixed
	 *
	 * @deprecated Use get_loader instead.
	 */
	public function getLoader( $key ) {
		return $this->get_loader( $key );
	}

	/**
	 * Retrieves loader assigned to $key
	 *
	 * @param string $key The name of the loader to get
	 *
	 * @return mixed
	 */
	public function get_loader( $key ) {
		if ( ! array_key_exists( $key, $this->loaders ) ) {
			throw new UserError( sprintf( __( 'No loader assigned to the key %s', 'wp-graphql' ), $key ) );
		}

		return $this->loaders[ $key ];
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 *
	 * @return array|mixed
	 *
	 * @deprecated use get_connection_args() instead
	 */
	public function getConnectionArgs() {
		return $this->get_connection_args();
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 *
	 * @return array|mixed
	 */
	public function get_connection_args() {
		return isset( $this->currentConnection ) && isset( $this->connectionArgs[ $this->currentConnection ] ) ? $this->connectionArgs[ $this->currentConnection ] : [];
	}

	/**
	 * Returns the current connection
	 *
	 * @return mixed|null|String
	 */
	public function get_current_connection() {
		return isset( $this->currentConnection ) ? $this->currentConnection : null;

	}

	/**
	 * @return mixed|null|String
	 * @deprecated use get_current_connection instead.
	 */
	public function getCurrentConnection() {
		return $this->get_current_connection();
	}

}
