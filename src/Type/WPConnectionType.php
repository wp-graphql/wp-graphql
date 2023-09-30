<?php
namespace WPGraphQL\Type;

use GraphQL\Exception\InvalidArgument;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\InterfaceType\PageInfo;
use WPGraphQL\Utils\Utils;

/**
 * Class WPConnectionType
 *
 * @package WPGraphQL\Type
 */
class WPConnectionType {

	/**
	 * Configuration for how auth should be handled on the connection field
	 *
	 * @var array
	 */
	protected $auth;

	/**
	 * The config for the connection
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The args configured for the connection
	 *
	 * @var array
	 */
	protected $connection_args;

	/**
	 * The fields to show on the connection
	 *
	 * @var array
	 */
	protected $connection_fields;

	/**
	 * @var array|null
	 */
	protected $connection_interfaces;

	/**
	 * The name of the connection
	 *
	 * @var mixed|string
	 */
	protected $connection_name;

	/**
	 * The fields to expose on the edge of the connection
	 *
	 * @var array
	 */
	protected $edge_fields;

	/**
	 * The name of the field the connection will be exposed as
	 *
	 * @var string
	 */
	protected $from_field_name;

	/**
	 * The name of the GraphQL Type the connection stems from
	 *
	 * @var string
	 */
	protected $from_type;

	/**
	 * Whether the connection is a one-to-one connection (default is false)
	 *
	 * @var bool
	 */
	protected $one_to_one;

	/**
	 * The Query Class that is used to resolve the connection.
	 *
	 * @var string
	 */
	protected $query_class;

	/**
	 * The resolver function to resolve the connection
	 *
	 * @var callable|\Closure
	 */
	protected $resolve_connection;

	/**
	 * @var mixed|null
	 */
	protected $resolve_cursor;

	/**
	 * Whether to  include and generate the default GraphQL interfaces on the connection Object types.
	 *
	 * @var bool
	 */
	protected $include_default_interfaces;

	/**
	 * The name of the GraphQL Type the connection connects to
	 *
	 * @var string
	 */
	protected $to_type;

	/**
	 * The WPGraphQL TypeRegistry
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	protected $type_registry;

	/**
	 * The where args for the connection
	 *
	 * @var array
	 */
	protected $where_args;

	/**
	 * WPConnectionType constructor.
	 *
	 * @param array                            $config        The config array for the connection
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry Instance of the WPGraphQL Type
	 *                                                        Registry
	 *
	 * @throws \Exception
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;

		/**
		 * Filter the config of WPConnectionType
		 *
		 * @param array        $config         Array of configuration options passed to the WPConnectionType when instantiating a new type
		 * @param \WPGraphQL\Type\WPConnectionType $wp_connection_type The instance of the WPConnectionType class
		 */
		$config = apply_filters( 'graphql_wp_connection_type_config', $config, $this );

		$this->validate_config( $config );

		$this->config    = $config;
		$this->from_type = $config['fromType'];
		$this->to_type   = $config['toType'];

		/**
		 * Filter the connection field name.
		 *
		 * @internal This filter is internal and used by rename_graphql_field(). It is not intended for use by external code.
		 *
		 * @param string $from_field_name The name of the field the connection will be exposed as.
		 */
		$this->from_field_name = apply_filters( "graphql_wp_connection_{$this->from_type}_from_field_name", $config['fromFieldName'] );

		$this->connection_name = ! empty( $config['connectionTypeName'] ) ? $config['connectionTypeName'] : $this->get_connection_name( $this->from_type, $this->to_type, $this->from_field_name );

		/**
		 * Bail if the connection has been de-registered or excluded.
		 */
		if ( ! $this->should_register() ) {
			return;
		}

		$this->auth                       = array_key_exists( 'auth', $config ) && is_array( $config['auth'] ) ? $config['auth'] : [];
		$this->connection_fields          = array_key_exists( 'connectionFields', $config ) && is_array( $config['connectionFields'] ) ? $config['connectionFields'] : [];
		$this->connection_args            = array_key_exists( 'connectionArgs', $config ) && is_array( $config['connectionArgs'] ) ? $config['connectionArgs'] : [];
		$this->edge_fields                = array_key_exists( 'edgeFields', $config ) && is_array( $config['edgeFields'] ) ? $config['edgeFields'] : [];
		$this->resolve_cursor             = array_key_exists( 'resolveCursor', $config ) && is_callable( $config['resolve'] ) ? $config['resolveCursor'] : null;
		$this->resolve_connection         = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : static function () {
			return null;
		};
		$this->where_args                 = [];
		$this->one_to_one                 = isset( $config['oneToOne'] ) && true === $config['oneToOne'];
		$this->connection_interfaces      = isset( $config['connectionInterfaces'] ) && is_array( $config['connectionInterfaces'] ) ? $config['connectionInterfaces'] : [];
		$this->include_default_interfaces = isset( $config['includeDefaultInterfaces'] ) ? (bool) $config['includeDefaultInterfaces'] : true;
		$this->query_class                = array_key_exists( 'queryClass', $config ) && ! empty( $config['queryClass'] ) ? $config['queryClass'] : null;

		/**
		 * Run an action when the WPConnectionType is instantiating.
		 *
		 * @param array        $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPConnectionType $wp_connection_type The instance of the WPConnectionType class
		 *
		 * @since 1.13.0
		 */
		do_action( 'graphql_wp_connection_type', $config, $this );

		$this->register_connection();
	}

	/**
	 * Validates that essential key/value pairs are passed to the connection config.
	 *
	 * @param array $config
	 *
	 * @return void
	 */
	protected function validate_config( array $config ): void {
		if ( ! array_key_exists( 'fromType', $config ) ) {
			throw new InvalidArgument( esc_html__( 'Connection config needs to have at least a fromType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'toType', $config ) ) {
			throw new InvalidArgument( esc_html__( 'Connection config needs to have a "toType" defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'fromFieldName', $config ) || ! is_string( $config['fromFieldName'] ) ) {
			throw new InvalidArgument( esc_html__( 'Connection config needs to have "fromFieldName" defined as a string value', 'wp-graphql' ) );
		}
	}

	/**
	 * Get edge interfaces
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function get_edge_interfaces( array $interfaces = [] ): array {

		// Only include the default interfaces if the user hasnt explicitly opted out.
		if ( false !== $this->include_default_interfaces ) {
			$interfaces[] = Utils::format_type_name( $this->to_type . 'ConnectionEdge' );
		}

		if ( ! empty( $this->connection_interfaces ) ) {
			foreach ( $this->connection_interfaces as $connection_interface ) {
				$interfaces[] = str_ends_with( $connection_interface, 'Edge' ) ? $connection_interface : $connection_interface . 'Edge';
			}
		}
		return $interfaces;
	}

	/**
	 * Utility method that formats the connection name given the name of the from Type and the to
	 * Type
	 *
	 * @param string $from_type        Name of the Type the connection is coming from
	 * @param string $to_type          Name of the Type the connection is going to
	 * @param string $from_field_name  Acts as an alternative "toType" if connection type already defined using $to_type.
	 *
	 * @return string
	 */
	public function get_connection_name( string $from_type, string $to_type, string $from_field_name ): string {

		// Create connection name using $from_type + To + $to_type + Connection.
		$connection_name = ucfirst( $from_type ) . 'To' . ucfirst( $to_type ) . 'Connection';

		// If connection type already exists with that connection name. Set connection name using
		// $from_field_name + To + $to_type + Connection.
		if ( $this->type_registry->has_type( $connection_name ) ) {
			$connection_name = ucfirst( $from_type ) . 'To' . ucfirst( $from_field_name ) . 'Connection';
		}

		return $connection_name;
	}

	/**
	 * If the connection includes connection args in the config, this registers the input args
	 * for the connection
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function register_connection_input() {
		if ( empty( $this->connection_args ) ) {
			return;
		}

		$input_name = $this->connection_name . 'WhereArgs';

		if ( $this->type_registry->has_type( $input_name ) ) {
			return;
		}

		$this->type_registry->register_input_type(
			$input_name,
			[
				'description' => sprintf(
					// translators: %s is the name of the connection
					__( 'Arguments for filtering the %s connection', 'wp-graphql' ),
					$this->connection_name
				),
				'fields'      => $this->connection_args,
				'queryClass'  => $this->query_class,
			]
		);

		$this->where_args = [
			'where' => [
				'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
				'type'        => $this->connection_name . 'WhereArgs',
			],
		];
	}

	/**
	 * Registers the One to One Connection Edge type to the Schema
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function register_one_to_one_connection_edge_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name . 'Edge' ) ) {
			return;
		}

		// Only include the default interfaces if the user hasnt explicitly opted out.
		$default_interfaces = false !== $this->include_default_interfaces ? [
			'OneToOneConnection',
			'Edge',
		] : [];
		$interfaces         = $this->get_edge_interfaces( $default_interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'interfaces'  => $interfaces,
				'description' => sprintf(
					// translators: Placeholders are for the name of the Type the connection is coming from and the name of the Type the connection is going to
					__( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ),
					$this->from_type,
					$this->to_type
				),
				'fields'      => array_merge(
					[
						'node' => [
							'type'              => [ 'non_null' => $this->to_type ],
							'description'       => __( 'The node of the connection, without the edges', 'wp-graphql' ),
							'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
						],
					],
					$this->edge_fields
				),
			]
		);
	}

	/**
	 * Registers the PageInfo type for the connection
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function register_connection_page_info_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name . 'PageInfo' ) ) {
			return;
		}

		$this->type_registry->register_object_type(
			$this->connection_name . 'PageInfo',
			[
				'interfaces'  => [ $this->to_type . 'ConnectionPageInfo' ],
				'description' => sprintf(
					// translators: %s is the name of the connection.
					__( 'Page Info on the "%s"', 'wp-graphql' ),
					$this->connection_name
				),
				'fields'      => PageInfo::get_fields(),
			]
		);
	}

	/**
	 * Registers the Connection Edge type to the Schema
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function register_connection_edge_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name . 'Edge' ) ) {
			return;
		}
		// Only include the default interfaces if the user hasnt explicitly opted out.
		$default_interfaces = false === $this->include_default_interfaces ? [
			'Edge',
		] : [];
		$interfaces         = $this->get_edge_interfaces( $default_interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'description' => __( 'An edge in a connection', 'wp-graphql' ),
				'interfaces'  => $interfaces,
				'fields'      => array_merge(
					[
						'cursor' => [
							'type'              => 'String',
							'description'       => __( 'A cursor for use in pagination', 'wp-graphql' ),
							'resolve'           => $this->resolve_cursor,
							'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
						],
						'node'   => [
							'type'              => [ 'non_null' => $this->to_type ],
							'description'       => __( 'The item at the end of the edge', 'wp-graphql' ),
							'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
						],
					],
					$this->edge_fields
				),
			]
		);
	}

	/**
	 * Registers the Connection Type to the Schema
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function register_connection_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name ) ) {
			return;
		}

		$interfaces   = ! empty( $this->connection_interfaces ) ? $this->connection_interfaces : [];
		$interfaces[] = Utils::format_type_name( $this->to_type . 'Connection' );

		// Only include the default interfaces if the user hasnt explicitly opted out.
		if ( false !== $this->include_default_interfaces ) {
			$interfaces[] = 'Connection';
		}

		$this->type_registry->register_object_type(
			$this->connection_name,
			[
				'description'       => sprintf(
					// translators: the placeholders are the name of the Types the connection is between.
					__( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ),
					$this->from_type,
					$this->to_type
				),
				'interfaces'        => $interfaces,
				'connection_config' => $this->config,
				'fields'            => $this->get_connection_fields(),
			]
		);
	}

	/**
	 * Returns fields to be used on the connection
	 *
	 * @return array
	 */
	protected function get_connection_fields(): array {
		return array_merge(
			[
				'pageInfo' => [
					'type'        => [ 'non_null' => $this->connection_name . 'PageInfo' ],
					'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
				],
				'edges'    => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->connection_name . 'Edge' ] ] ],
					// translators: %s is the name of the connection.
					'description' => sprintf( __( 'Edges for the %s connection', 'wp-graphql' ), $this->connection_name ),
				],
				'nodes'    => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->to_type ] ] ],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
				],
			],
			$this->connection_fields
		);
	}

	/**
	 * Get the args used for pagination on connections
	 *
	 * @return array|array[]
	 */
	protected function get_pagination_args(): array {
		if ( true === $this->one_to_one ) {
			$pagination_args = [];
		} else {
			$pagination_args = [
				'first'  => [
					'type'        => 'Int',
					'description' => __( 'The number of items to return after the referenced "after" cursor', 'wp-graphql' ),
				],
				'last'   => [
					'type'        => 'Int',
					'description' => __( 'The number of items to return before the referenced "before" cursor', 'wp-graphql' ),
				],
				'after'  => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "first" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
				'before' => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "last" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
			];
		}

		return $pagination_args;
	}

	/**
	 * Registers the connection in the Graph
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function register_connection_field(): void {

		// merge the config so the raw data passed to the connection
		// is passed to the field and can be accessed via $info in resolvers
		$field_config = array_merge(
			$this->config,
			[
				'type'                  => true === $this->one_to_one ? $this->connection_name . 'Edge' : $this->connection_name,
				'args'                  => array_merge( $this->get_pagination_args(), $this->where_args ),
				'auth'                  => $this->auth,
				'deprecationReason'     => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
				'description'           => ! empty( $this->config['description'] )
					? $this->config['description']
					: sprintf(
						// translators: the placeholders are the name of the Types the connection is between.
						__( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ),
						$this->from_type,
						$this->to_type
					),
				'resolve'               => function ( $root, $args, $context, $info ) {
					$context->connection_query_class = $this->query_class;
					$resolve_connection              = $this->resolve_connection;

					/**
					 * Return the results of the connection resolver
					 */
					return $resolve_connection( $root, $args, $context, $info );
				},
				'allowFieldUnderscores' => isset( $this->config['allowFieldUnderscores'] ) && true === $this->config['allowFieldUnderscores'],
			]
		);

		$this->type_registry->register_field(
			$this->from_type,
			$this->from_field_name,
			$field_config
		);
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function register_connection_interfaces(): void {
		$connection_edge_type = Utils::format_type_name( $this->to_type . 'ConnectionEdge' );

		if ( ! $this->type_registry->has_type( $this->to_type . 'ConnectionPageInfo' ) ) {
			$this->type_registry->register_interface_type(
				$this->to_type . 'ConnectionPageInfo',
				[
					'interfaces'  => [ 'WPPageInfo' ],
					// translators: %s is the name of the connection edge.
					'description' => sprintf( __( 'Page Info on the connected %s', 'wp-graphql' ), $connection_edge_type ),
					'fields'      => PageInfo::get_fields(),
				]
			);
		}


		if ( ! $this->type_registry->has_type( $connection_edge_type ) ) {
			$this->type_registry->register_interface_type(
				$connection_edge_type,
				[
					'interfaces'  => [ 'Edge' ],
					// translators: %s is the name of the type the connection edge is to.
					'description' => sprintf( __( 'Edge between a Node and a connected %s', 'wp-graphql' ), $this->to_type ),
					'fields'      => [
						'node' => [
							'type'        => [ 'non_null' => $this->to_type ],
							// translators: %s is the name of the type the connection edge is to.
							'description' => sprintf( __( 'The connected %s Node', 'wp-graphql' ), $this->to_type ),
						],
					],
				]
			);
		}

		if ( ! $this->one_to_one && ! $this->type_registry->has_type( $this->to_type . 'Connection' ) ) {
			$this->type_registry->register_interface_type(
				$this->to_type . 'Connection',
				[
					'interfaces'  => [ 'Connection' ],
					// translators: %s is the name of the type the connection is to.
					'description' => sprintf( __( 'Connection to %s Nodes', 'wp-graphql' ), $this->to_type ),
					'fields'      => [
						'edges'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $connection_edge_type ] ] ],
							'description' => sprintf(
								// translators: %1$s is the name of the type the connection is from, %2$s is the name of the type the connection is to.
								__( 'A list of edges (relational context) between %1$s and connected %2$s Nodes', 'wp-graphql' ),
								$this->from_type,
								$this->to_type
							),
						],
						'pageInfo' => [
							'type' => [ 'non_null' => $this->to_type . 'ConnectionPageInfo' ],
						],
						'nodes'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->to_type ] ] ],
							// translators: %s is the name of the type the connection is to.
							'description' => sprintf( __( 'A list of connected %s Nodes', 'wp-graphql' ), $this->to_type ),
						],
					],
				]
			);
		}
	}

	/**
	 * Registers the connection Types and field to the Schema.
	 *
	 * @todo change to 'Protected'. This is public for now to allow for backwards compatibility.
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function register_connection(): void {
		$this->register_connection_input();

		if ( false !== $this->include_default_interfaces ) {
			$this->register_connection_interfaces();
		}

		if ( true === $this->one_to_one ) {
			$this->register_one_to_one_connection_edge_type();
		} else {
			$this->register_connection_page_info_type();
			$this->register_connection_edge_type();
			$this->register_connection_type();
		}

		$this->register_connection_field();
	}

	/**
	 * Checks whether the connection should be registered to the Schema.
	 */
	protected function should_register(): bool {

		// Don't register if the connection has been excluded from the schema.
		$excluded_connections = $this->type_registry->get_excluded_connections();
		if ( in_array( strtolower( $this->connection_name ), $excluded_connections, true ) ) {
			return false;
		}

		// Don't register if one of the connection types has been excluded from the schema.
		$excluded_types = $this->type_registry->get_excluded_types();
		if ( ( in_array( strtolower( $this->from_type ), $excluded_types, true ) || in_array( strtolower( $this->to_type ), $excluded_types, true ) ) ) {
			return false;
		}

		return true;
	}
}
