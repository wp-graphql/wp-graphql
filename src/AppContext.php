<?php
namespace WPGraphQL;

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
	 * @var \mixed $config
	 * @access public
	 */
	public $config;

	/**
	 * Passes context about the current connection being resolved
	 * @var mixed| String | null
	 */
	public $currentConnection = null;

	/**
	 * Passes context about the current connection
	 * @var array
	 */
	public $connectionArgs = [];

	/**
	 * AppContext constructor.
	 */
	public function __construct() {
		$this->config = apply_filters( 'graphql_app_context_config', $this->config );
	}

	/**
	 * Returns the $args for the connection the field is a part of
	 * @return array|mixed
	 */
	public function getConnectionArgs() {
		return isset( $this->currentConnection ) && isset( $this->connectionArgs[ $this->currentConnection ] ) ? $this->connectionArgs[ $this->currentConnection ] : [];
	}

	/**
	 * Returns the current connection
	 * @return mixed|null|String
	 */
	public function getCurrentConnection() {
		return isset( $this->currentConnection ) ? $this->currentConnection : null;
	}

}
