<?php

namespace WPGraphQL;

use GraphQL\Error\UserError;
use WPGraphQL\Data\Loader\CommentLoader;
use WPGraphQL\Data\Loader\MenuItemLoader;
use WPGraphQL\Data\Loader\PostObjectLoader;
use WPGraphQL\Data\Loader\TermObjectLoader;
use WPGraphQL\Data\Loader\UserLoader;

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
	 * @access public
	 */
	public $root_url;

	/**
	 * Stores the WP_User object of the current user
	 *
	 * @var \WP_User $viewer
	 * @access public
	 */
	public $viewer;

	/**
	 * Stores everything from the $_REQUEST global
	 *
	 * @var \mixed $request
	 * @access public
	 */
	public $request;

	/**
	 * Stores additional $config properties
	 *
	 * @var \mixed $config
	 * @access public
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
	 * @var CommentLoader
	 */
	public $CommentLoader;

	/**
	 * @var MenuItemLoader
	 */
	public $MenuItemLoader;

	/**
	 * @var PostObjectLoader
	 */
	public $PostObjectLoader;

	/**
	 * @var TermObjectLoader
	 */
	public $TermObjectLoader;

	/**
	 * @var UserLoader
	 */
	public $UserLoader;

	/**
	 * @var array
	 */
	private $loaders;

	/**
	 * AppContext constructor.
	 */
	public function __construct() {

		$this->CommentLoader    = new CommentLoader( $this );
		$this->MenuItemLoader   = new MenuItemLoader( $this );
		$this->PostObjectLoader = new PostObjectLoader( $this );
		$this->TermObjectLoader = new TermObjectLoader( $this );
		$this->UserLoader       = new UserLoader( $this );

		$this->loaders = [
			'comment'   => &$this->CommentLoader,
			'menu_item' => &$this->MenuItemLoader,
			'user'      => &$this->UserLoader,
		];

		$allowed_post_types = \WPGraphQL::$allowed_post_types;
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$this->loaders[ $post_type ] = &$this->PostObjectLoader;
			}
		}

		$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
		if ( ! empty( $allowed_taxonomies && is_array( $allowed_taxonomies ) ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$this->loaders[ $taxonomy ] = &$this->TermObjectLoader;
			}
		}

		/**
		 * This filters the data loaders, allowing for additional loaders to be
		 * added to the AppContext or for existing loaders to be replaced if
		 * needed.
		 *
		 * @params array $loaders The loaders accessible in the AppContext
		 * @params AppContext $this The AppContext
		 */
		$this->loaders = apply_filters( 'graphql_data_loaders', $this->loaders, $this );

		/**
		 * This filters the config for the AppContext.
		 *
		 * This can be used to store additional context config, which is available to resolvers
		 * throughout the resolution of a GraphQL request.
		 *
		 * @params array $config The config array of the AppContext object
		 * @params AppContext $this The AppContext
		 */
		$this->config = apply_filters( 'graphql_app_context_config', $this->config, $this );
	}

	/**
	 * Retrieves loader assigned to $key
	 *
	 * @return mixed
	 */
	public function getLoader( $key ) {
		if ( ! array_key_exists( $key, $this->loaders ) ) {
			throw new UserError( sprintf( __( 'No loader assigned to the key %s', 'wp-graphql' ), $key ) );
		}

		return $this->loaders[ $key ];
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 *
	 * @return array|mixed
	 */
	public function getConnectionArgs() {
		return isset( $this->currentConnection ) && isset( $this->connectionArgs[ $this->currentConnection ] ) ? $this->connectionArgs[ $this->currentConnection ] : [];
	}

	/**
	 * Returns the current connection
	 *
	 * @return mixed|null|String
	 */
	public function getCurrentConnection() {
		return isset( $this->currentConnection ) ? $this->currentConnection : null;
	}

}
