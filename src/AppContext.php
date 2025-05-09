<?php

namespace WPGraphQL;

use GraphQL\Error\UserError;
use WPGraphQL\Data\Loader\CommentAuthorLoader;
use WPGraphQL\Data\Loader\CommentLoader;
use WPGraphQL\Data\Loader\EnqueuedScriptLoader;
use WPGraphQL\Data\Loader\EnqueuedStylesheetLoader;
use WPGraphQL\Data\Loader\PluginLoader;
use WPGraphQL\Data\Loader\PostObjectLoader;
use WPGraphQL\Data\Loader\PostTypeLoader;
use WPGraphQL\Data\Loader\TaxonomyLoader;
use WPGraphQL\Data\Loader\TermObjectLoader;
use WPGraphQL\Data\Loader\ThemeLoader;
use WPGraphQL\Data\Loader\UserLoader;
use WPGraphQL\Data\Loader\UserRoleLoader;
use WPGraphQL\Data\NodeResolver;

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
#[\AllowDynamicProperties]
class AppContext {

	/**
	 * Stores the class to use for the connection query.
	 *
	 * @var \WP_Query|null
	 */
	public $connection_query_class = null;

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
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * Stores everything from the $_REQUEST global
	 *
	 * @var mixed $request
	 */
	public $request;

	/**
	 * Stores additional $config properties
	 *
	 * @var mixed $config
	 */
	public $config;

	/**
	 * Passes context about the current connection being resolved
	 *
	 * @var mixed|string|null
	 */
	public $currentConnection = null;

	/**
	 * Passes context about the current connection
	 *
	 * @var array<string,mixed>
	 */
	public $connectionArgs = [];

	/**
	 * Stores the loaders for the class
	 *
	 * @var array<string,\WPGraphQL\Data\Loader\AbstractDataLoader>
	 */
	public $loaders = [];

	/**
	 * Instance of the NodeResolver class to resolve nodes by URI
	 *
	 * @var \WPGraphQL\Data\NodeResolver
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
			'nav_menu_item'       => new PostObjectLoader( $this ),
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
		 * @param array<string,\WPGraphQL\Data\Loader\AbstractDataLoader> $loaders The loaders accessible in the AppContext
		 * @param \WPGraphQL\AppContext                                   $context The AppContext
		 */
		$this->loaders = apply_filters( 'graphql_data_loaders', $loaders, $this );

		/**
		 * This sets up the NodeResolver to allow nodes to be resolved by URI
		 */
		$this->node_resolver = new NodeResolver( $this );

		/**
		 * This filters the config for the AppContext.
		 *
		 * This can be used to store additional context config, which is available to resolvers
		 * throughout the resolution of a GraphQL request.
		 *
		 * @param mixed[] $config The config array of the AppContext object
		 */
		$this->config = apply_filters( 'graphql_app_context_config', $this->config );
	}

	/**
	 * Retrieves loader assigned to $key
	 *
	 * @param string $key The name of the loader to get
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 *
	 * @deprecated Use get_loader instead.
	 */
	public function getLoader( $key ) {
		_deprecated_function( __METHOD__, '0.8.4', self::class . '::get_loader()' );
		return $this->get_loader( $key );
	}

	/**
	 * Retrieves loader assigned to $key
	 *
	 * @param string $key The name of the loader to get
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 * @throws \GraphQL\Error\UserError If the loader is not found.
	 */
	public function get_loader( $key ) {
		if ( ! array_key_exists( $key, $this->loaders ) ) {
			// translators: %s is the key of the loader that was not found.
			throw new UserError( esc_html( sprintf( __( 'No loader assigned to the key %s', 'wp-graphql' ), $key ) ) );
		}

		return $this->loaders[ $key ];
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 *
	 * @deprecated use get_connection_args() instead
	 * @return mixed[]|mixed
	 */
	public function getConnectionArgs() {
		_deprecated_function( __METHOD__, '0.8.4', self::class . '::get_connection_args()' );
		return $this->get_connection_args();
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 *
	 * @return mixed[]|mixed
	 */
	public function get_connection_args() {
		return isset( $this->currentConnection ) && isset( $this->connectionArgs[ $this->currentConnection ] ) ? $this->connectionArgs[ $this->currentConnection ] : [];
	}

	/**
	 * Returns the current connection
	 *
	 * @return mixed|string|null
	 */
	public function get_current_connection() {
		return isset( $this->currentConnection ) ? $this->currentConnection : null;
	}

	/**
	 * @return mixed|string|null
	 * @deprecated use get_current_connection instead.
	 */
	public function getCurrentConnection() {
		return $this->get_current_connection();
	}
}
