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
	 * The default loaders for the AppContext.
	 */
	private const DEFAULT_LOADERS = [
		'comment_author'      => CommentAuthorLoader::class,
		'comment'             => CommentLoader::class,
		'enqueued_script'     => EnqueuedScriptLoader::class,
		'enqueued_stylesheet' => EnqueuedStylesheetLoader::class,
		'plugin'              => PluginLoader::class,
		'nav_menu_item'       => PostObjectLoader::class,
		'post'                => PostObjectLoader::class,
		'post_type'           => PostTypeLoader::class,
		'taxonomy'            => TaxonomyLoader::class,
		'term'                => TermObjectLoader::class,
		'theme'               => ThemeLoader::class,
		'user'                => UserLoader::class,
		'user_role'           => UserRoleLoader::class,
	];

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
	 * @todo These properties and methods are unused. We should consider deprecating/removing them.
	 *
	 * @var mixed|string|null
	 */
	public $currentConnection = null;

	/**
	 * Passes context about the current connection
	 *
	 * @todo These properties and methods are unused. We should consider deprecating/removing them.
	 *
	 * @var array<string,mixed>
	 */
	public $connectionArgs = [];

	/**
	 * Stores the loaders for the class
	 *
	 * @var array<string,\WPGraphQL\Data\Loader\AbstractDataLoader>
	 *
	 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation, -- For phpstan type hinting
	 *
	 * @template T of key-of<self::DEFAULT_LOADERS>
	 *
	 * @phpstan-var array<T, new<self::DEFAULT_LOADERS[T]>>|array<string,\WPGraphQL\Data\Loader\AbstractDataLoader>
	 *
	 * phpcs:enable
	 */
	public $loaders = [];

	/**
	 * Instance of the NodeResolver class to resolve nodes by URI
	 *
	 * @var \WPGraphQL\Data\NodeResolver
	 */
	public $node_resolver;

	/**
	 * The loader classes, before they are instantiated.
	 *
	 * @var array<string,class-string<\WPGraphQL\Data\Loader\AbstractDataLoader>>
	 */
	private $loader_classes = self::DEFAULT_LOADERS;

	/**
	 * Stores custom data with namespace isolation.
	 *
	 * This is a key-value store where data is organized by namespace to prevent collisions
	 * between different plugins/extensions.
	 *
	 * INTENDED USE: Store temporary, request-scoped state that needs to be passed between
	 * different phases of GraphQL execution (e.g., directive hooks, middleware, resolver chains).
	 *
	 * NOT INTENDED: Storing permanent configuration or replacing existing AppContext properties.
	 * For configuration, use the 'graphql_app_context_config' filter instead.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $store = [];

	/**
	 * AppContext constructor.
	 */
	public function __construct() {

		// Prime the loader classes (and their instances) for the AppContext.
		$this->prepare_data_loaders();

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
	 * Prepares the data loaders for the AppContext.
	 *
	 * This method instantiates the loader classes and prepares them for use in the AppContext.
	 * It also applies filters to allow customization of the loader classes.
	 *
	 * @uses graphql_data_loader_classes filter.
	 * @uses graphql_data_loaders filter (deprecated).
	 */
	private function prepare_data_loaders(): void {
		/**
		 * Filter to change the data loader classes.
		 *
		 * This allows for additional loaders to be added to the AppContext or replaced as needed.
		 *
		 * @param array<string,class-string<\WPGraphQL\Data\Loader\AbstractDataLoader>> $loader_classes The loader classes accessible in the AppContext
		 * @param \WPGraphQL\AppContext                                                $context        The AppContext
		 */
		$this->loader_classes = apply_filters( 'graphql_data_loader_classes', $this->loader_classes, $this );

		/**
		 * Prime the loaders if needed
		 *
		 * @todo Remove this when the loaders are instantiated on demand.
		 */
		if ( has_filter( 'graphql_data_loaders' ) ) {
			$loaders = array_map(
				function ( $loader_class ) {
					return new $loader_class( $this );
				},
				$this->loader_classes
			);

			/**
			 * @deprecated 2.3.2 in favor of graphql_data_loader_classes.
			 * @todo Remove in a future version.
			 *
			 * @param array<string,\WPGraphQL\Data\Loader\AbstractDataLoader> $loaders The loaders accessible in the AppContext
			 * @param \WPGraphQL\AppContext                                   $context The AppContext
			 */
			$this->loaders = apply_filters_deprecated(
				'graphql_data_loaders',
				[ $loaders, $this ],
				'2.3.2',
				'graphql_data_loader_classes',
				esc_html__( 'The graphql_data_loaders filter is deprecated and will be removed in a future version. Instead, use the graphql_data_loader_classes filter to add/change data loader classes before they are instantiated.', 'wp-graphql' ),
			);
		}
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
	 * @template T of key-of<self::DEFAULT_LOADERS>
	 *
	 * @param T|string $key The name of the loader to get.
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 * @throws \GraphQL\Error\UserError If the loader is not found.
	 *
	 * @phpstan-return ( $key is T ? new<self::DEFAULT_LOADERS[T]> : \WPGraphQL\Data\Loader\AbstractDataLoader )
	 */
	public function get_loader( $key ) {
		// @todo: Remove the isset() when `graphql_data_loaders` is removed.
		if ( ! array_key_exists( $key, $this->loader_classes ) && ! isset( $this->loaders[ $key ] ) ) {
			// translators: %s is the key of the loader that was not found.
			throw new UserError( esc_html( sprintf( __( 'No loader assigned to the key %s', 'wp-graphql' ), $key ) ) );
		}

		// If the loader is not instantiated, instantiate it.
		if ( ! isset( $this->loaders[ $key ] ) ) {
			try {
				$this->loaders[ $key ] = new $this->loader_classes[ $key ]( $this );
			} catch ( \Throwable $e ) {
				// translators: %s is the key of the loader that failed to instantiate.
				throw new UserError( esc_html( sprintf( __( 'Failed to instantiate %1$s: %2$s', 'wp-graphql' ), $this->loader_classes[ $key ], $e->getMessage() ) ) );
			}
		}

		/** @var \WPGraphQL\Data\Loader\AbstractDataLoader $loader */
		$loader = $this->loaders[ $key ];
		return $loader;
	}

	/**
	 * Magic getter used to warn about accessing the loaders property directly.
	 *
	 * @todo Remove this when we change the property visibility.
	 *
	 * @param string $key The name of the property to get.
	 * @return mixed
	 */
	public function __get( $key ) {
		// Use default handling if the key is not a loader.
		if ( 'loaders' !== $key ) {
			return $this->$key;
		}

		// Warn about accessing the loaders property directly.
		_doing_it_wrong(
			__METHOD__,
			esc_html__( 'Accessing the AppContext::$loaders property from outside the AppContext class is deprecated and will throw an error in a future version. Use AppContext::get_loader() instead.', 'wp-graphql' ), //phpcs:ignore PHPCS.Functions.VersionParameter.InvalidVersion -- @todo Fix this smell.
			'2.3.2' // phpcs:ignore PHPCS.Functions.VersionParameter.OldVersionPlaceholder -- @todo Fix this smell.
		);

		// Return the actual loaders array.
		return $this->loaders;
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
	 * @todo These properties and methods are unused. We should consider deprecating/removing them.
	 *
	 * @return mixed[]|mixed
	 */
	public function get_connection_args() {
		return isset( $this->currentConnection ) && isset( $this->connectionArgs[ $this->currentConnection ] ) ? $this->connectionArgs[ $this->currentConnection ] : [];
	}

	/**
	 * Returns the current connection
	 *
	 * @todo These properties and methods are unused. We should consider deprecating/removing them.
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

	/**
	 * Magic setter to warn about setting dynamic properties on AppContext.
	 *
	 * This maintains backward compatibility while warning developers to use the new set() method.
	 *
	 * @param string $name  The name of the property being set.
	 * @param mixed  $value The value being assigned to the property.
	 * @return void
	 */
	public function __set( $name, $value ) {
		// Only warn for truly dynamic properties, not existing defined properties
		if ( ! property_exists( $this, $name ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					// translators: %s is the name of the property being set.
					esc_html__( 'Setting dynamic properties on AppContext is deprecated. Use AppContext::set() instead. Attempted to set property: %s', 'wp-graphql' ),
					esc_html( $name )
				),
				'@since 2.3.8'
			);
		}

		// Still set the property for backward compatibility
		$this->$name = $value;
	}

	/**
	 * Sets a value in the context store with namespace isolation.
	 *
	 * It's strongly recommended to use a unique namespace to avoid collisions with other plugins.
	 * A good practice is to use your plugin's text domain or a similar unique identifier.
	 *
	 * Example:
	 * ```php
	 * $context->set( 'my-plugin', 'user-language', 'fr' );
	 * $context->set( 'my-plugin', 'original-locale', get_locale() );
	 * ```
	 *
	 * @param string $namespace The namespace to store the value under (e.g., 'my-plugin').
	 * @param string $key       The key to store the value under within the namespace.
	 * @param mixed  $value     The value to store.
	 * @since 2.3.8
	 */
	public function set( string $namespace, string $key, $value ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- Namespace is semantically appropriate.
		if ( ! isset( $this->store[ $namespace ] ) ) {
			$this->store[ $namespace ] = [];
		}

		$this->store[ $namespace ][ $key ] = $value;
	}

	/**
	 * Gets a value from the context store.
	 *
	 * Example:
	 * ```php
	 * $language = $context->get( 'my-plugin', 'user-language', 'en' );
	 * $locale = $context->get( 'my-plugin', 'original-locale' );
	 * ```
	 *
	 * @param string $namespace The namespace to retrieve the value from.
	 * @param string $key       The key to retrieve within the namespace.
	 * @param mixed  $default   Optional. The default value to return if the key doesn't exist. Default null.
	 * @return mixed The value if it exists, otherwise the default value.
	 * @since 2.3.8
	 */
	public function get( string $namespace, string $key, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Semantically appropriate.
		return $this->store[ $namespace ][ $key ] ?? $default;
	}

	/**
	 * Checks if a key exists in the context store.
	 *
	 * Example:
	 * ```php
	 * if ( $context->has( 'my-plugin', 'user-language' ) ) {
	 *     $language = $context->get( 'my-plugin', 'user-language' );
	 * }
	 * ```
	 *
	 * @param string $namespace The namespace to check.
	 * @param string $key       The key to check within the namespace.
	 * @return bool True if the key exists, false otherwise.
	 * @since 2.3.8
	 */
	public function has( string $namespace, string $key ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- Namespace is semantically appropriate.
		return isset( $this->store[ $namespace ] ) && array_key_exists( $key, $this->store[ $namespace ] );
	}

	/**
	 * Removes a specific key from the context store.
	 *
	 * Example:
	 * ```php
	 * $context->remove( 'my-plugin', 'temporary-data' );
	 * ```
	 *
	 * @param string $namespace The namespace containing the key.
	 * @param string $key       The key to remove.
	 * @since 2.3.8
	 */
	public function remove( string $namespace, string $key ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- Namespace is semantically appropriate.
		if ( isset( $this->store[ $namespace ] ) ) {
			unset( $this->store[ $namespace ][ $key ] );
		}
	}

	/**
	 * Clears all data in a specific namespace.
	 *
	 * This removes all keys associated with the given namespace.
	 *
	 * Example:
	 * ```php
	 * // Clear all data for 'my-plugin' namespace
	 * $context->clear( 'my-plugin' );
	 * ```
	 *
	 * @param string $namespace The namespace to clear.
	 * @since 2.3.8
	 */
	public function clear( string $namespace ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- Namespace is semantically appropriate.
		unset( $this->store[ $namespace ] );
	}

	/**
	 * Gets all data stored in a specific namespace.
	 *
	 * Returns an associative array of all key-value pairs in the namespace.
	 *
	 * Example:
	 * ```php
	 * $all_data = $context->all( 'my-plugin' );
	 * foreach ( $all_data as $key => $value ) {
	 *     // Process each key-value pair
	 * }
	 * ```
	 *
	 * @param string $namespace The namespace to retrieve data from.
	 * @return array<string,mixed> An array of all key-value pairs in the namespace, or empty array if namespace doesn't exist.
	 * @since 2.3.8
	 */
	public function all( string $namespace ): array { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound -- Namespace is semantically appropriate.
		return $this->store[ $namespace ] ?? [];
	}
}
