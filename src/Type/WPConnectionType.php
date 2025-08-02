<?php
namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Type\InterfaceType\PageInfo;
use WPGraphQL\Utils\Utils;

/**
 * Class WPConnectionType
 *
 * @package WPGraphQL\Type
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-type WPConnectionTypeConfig array{
 *  auth: array<string,mixed>,
 *  deprecationReason?: string|callable(): ?string,
 *  description?: string|callable(): string,
 *  inputFields?: array<string,array<string,mixed>>,
 *  isPrivate: bool,
 *  mutateAndGetPayload: callable,
 *  name: string,
 *  outputFields?: array<string,array<string,mixed>>,
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<WPConnectionTypeConfig>
 *
 * phpcs:enable
 */
class WPConnectionType implements TypeAdapterInterface {

	/**
	 * Configuration for how auth should be handled on the connection field
	 *
	 * @var array<string,mixed>
	 */
	protected $auth;

	/**
	 * The config for the connection
	 *
	 * @var array<string,mixed>
	 */
	protected $config;

	/**
	 * The args configured for the connection
	 *
	 * @var array<string,array<string,mixed>>
	 */
	protected $connection_args;

	/**
	 * The fields to show on the connection
	 *
	 * @var array<string,mixed>
	 */
	protected $connection_fields;

	/**
	 * @var string[]|null
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
	 * @var array<string,array<string,mixed>>
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
	 * @var callable(mixed $root,array<string,mixed> $args,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):mixed
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
	 * @var array<string,array<string,mixed>>
	 */
	protected $where_args = [];

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'wp_mutation';
	}

	/**
	 * WPConnectionType constructor.
	 *
	 * @param array<string,mixed> $config The config array for the connection.
	 *
	 * @throws \Exception
	 */
	public function __construct( array $config ) {
		$config = $this->prepare( $config );
		$this->validate( $config );

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
		$this->type_registry   = \WPGraphQL::get_type_registry();

		// Bail if the mutation should be excluded from the schema.
		if ( ! $this->should_register() ) {
			return;
		}

		/**
		 * Run an action when the WPConnectionType is instantiating.
		 *
		 * @param array<string,mixed>              $config             Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPConnectionType $wp_connection_type The instance of the WPConnectionType class
		 *
		 * @since 1.13.0
		 */
		do_action( 'graphql_wp_connection_type', $config, $this );

		$this->register_connection();
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare( array $config ): array {
		/**
		 * Filter the config of WPConnectionType
		 *
		 * @param array<string,mixed>              $config             Array of configuration options passed to the WPConnectionType when instantiating a new type
		 */
		$config = apply_filters( 'graphql_wp_connection_type_config', $config, $this );

		$config['auth']             = array_key_exists( 'auth', $config ) && is_array( $config['auth'] ) ? $config['auth'] : [];
		$config['connectionFields'] = array_key_exists( 'connectionFields', $config ) && is_array( $config['connectionFields'] ) ? $config['connectionFields'] : [];
		$config['connectionArgs']   = array_key_exists( 'connectionArgs', $config ) && is_array( $config['connectionArgs'] ) ? $config['connectionArgs'] : [];
		$config['edgeFields']       = array_key_exists( 'edgeFields', $config ) && is_array( $config['edgeFields'] ) ? $config['edgeFields'] : [];
		$config['resolveCursor']    = array_key_exists( 'resolveCursor', $config ) && is_callable( $config['resolve'] ) ? $config['resolveCursor'] : null;

		$config['resolve']                  = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : static function () {
			return null;
		};
		$config['oneToOne']                 = isset( $config['oneToOne'] ) && true === $config['oneToOne'];
		$config['connectionInterfaces']     = isset( $config['connectionInterfaces'] ) && is_array( $config['connectionInterfaces'] ) ? $config['connectionInterfaces'] : [];
		$config['includeDefaultInterfaces'] = isset( $config['includeDefaultInterfaces'] ) ? (bool) $config['includeDefaultInterfaces'] : true;
		$config['queryClass']               = array_key_exists( 'queryClass', $config ) && ! empty( $config['queryClass'] ) ? $config['queryClass'] : null;

		return $config;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \GraphQL\Error\UserError If the configuration is invalid.
	 */
	public function validate( array $config ): void {
		if ( ! array_key_exists( 'fromType', $config ) ) {
			throw new UserError( esc_html__( 'Connection config needs to have at least a fromType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'toType', $config ) ) {
			throw new UserError( esc_html__( 'Connection config needs to have a "toType" defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'fromFieldName', $config ) || ! is_string( $config['fromFieldName'] ) ) {
			throw new UserError( esc_html__( 'Connection config needs to have "fromFieldName" defined as a string value', 'wp-graphql' ) );
		}
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
		if ( in_array( strtolower( $this->from_type ), $excluded_types, true ) || in_array( strtolower( $this->to_type ), $excluded_types, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get edge interfaces
	 *
	 * @param string[] $interfaces Array of interfaces to add to the edge.
	 *
	 * @return string[]
	 */
	protected function get_edge_interfaces( array $interfaces = [] ): array {

		// Only include the default interfaces if the user hasnt explicitly opted out.
		if ( false !== $this->config['includeDefaultInterfaces'] ) {
			$interfaces[] = Utils::format_type_name( $this->to_type . 'ConnectionEdge' );
		}

		foreach ( $this->config['connectionInterfaces'] as $connection_interface ) {
			$interfaces[] = str_ends_with( $connection_interface, 'Edge' ) ? $connection_interface : $connection_interface . 'Edge';
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
	 * @throws \Exception
	 */
	protected function register_connection_input(): void {
		// If there are no connection args, bail
		if ( empty( $this->config['connectionArgs'] ) ) {
			return;
		}

		$input_fields = $this->config['connectionArgs'];

		$input_name = $this->connection_name . 'WhereArgs';

		$this->where_args = [
			'where' => [
				'description' => static function () {
					return __( 'Arguments for filtering the connection', 'wp-graphql' );
				},
				'type'        => $input_name,
			],
		];

		// Only register the input type if it hasn't already been registered.
		if ( $this->type_registry->has_type( $input_name ) ) {
			return;
		}

		$this->type_registry->register_input_type(
			$input_name,
			[
				'description' => function () {
					return sprintf(
						// translators: %s is the name of the connection
						__( 'Arguments for filtering the %s connection', 'wp-graphql' ),
						$this->connection_name
					);
				},
				'fields'      => $input_fields,
				'queryClass'  => $this->query_class,
			]
		);
	}

	/**
	 * Registers the One to One Connection Edge type to the Schema
	 *
	 * @throws \Exception
	 */
	protected function register_one_to_one_connection_edge_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name . 'Edge' ) ) {
			return;
		}

		// Only include the default interfaces if the user hasnt explicitly opted out.
		$default_interfaces = false !== $this->config['includeDefaultInterfaces'] ? [
			'OneToOneConnection',
			'Edge',
		] : [];
		$interfaces         = $this->get_edge_interfaces( $default_interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'interfaces'  => $interfaces,
				'description' => function () {
					return sprintf(
						// translators: Placeholders are for the name of the Type the connection is coming from and the name of the Type the connection is going to
						__( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ),
						$this->from_type,
						$this->to_type
					);
				},
				'fields'      => array_merge(
					[
						'node' => [
							'type'              => [ 'non_null' => $this->to_type ],
							'description'       => static function () {
								return __( 'The node of the connection, without the edges', 'wp-graphql' );
							},
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
				'description' => function () {
					return sprintf(
						// translators: %s is the name of the connection.
						__( 'Pagination metadata specific to "%1$s" collections. Provides cursors and flags for navigating through sets of %2$s Nodes.', 'wp-graphql' ),
						$this->connection_name,
						$this->connection_name
					);
				},
				'fields'      => PageInfo::get_fields(),
			]
		);
	}

	/**
	 * Registers the Connection Edge type to the Schema
	 *
	 * @throws \Exception
	 */
	protected function register_connection_edge_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name . 'Edge' ) ) {
			return;
		}
		// Only include the default interfaces if the user hasnt explicitly opted out.
		$default_interfaces = false === $this->config['includeDefaultInterfaces'] ? [
			'Edge',
		] : [];
		$interfaces         = $this->get_edge_interfaces( $default_interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'description' => static function () {
					return __( 'An edge in a connection', 'wp-graphql' );
				},
				'interfaces'  => $interfaces,
				'fields'      => array_merge(
					[
						'cursor' => [
							'type'              => 'String',
							'description'       => static function () {
								return __( 'A cursor for use in pagination', 'wp-graphql' );
							},
							'resolve'           => $this->config['resolveCursor'],
							'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
						],
						'node'   => [
							'type'              => [ 'non_null' => $this->to_type ],
							'description'       => static function () {
								return __( 'The item at the end of the edge', 'wp-graphql' );
							},
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
	 * @throws \Exception
	 */
	protected function register_connection_type(): void {
		if ( $this->type_registry->has_type( $this->connection_name ) ) {
			return;
		}

		$interfaces   = ! empty( $this->connection_interfaces ) ? $this->connection_interfaces : [];
		$interfaces[] = Utils::format_type_name( $this->to_type . 'Connection' );

		// Only include the default interfaces if the user has not explicitly opted out.
		if ( false !== $this->config['includeDefaultInterfaces'] ) {
			$interfaces[] = 'Connection';
		}

		$this->type_registry->register_object_type(
			$this->connection_name,
			[
				'description'       => function () {
					return sprintf(
						// translators: the placeholders are the name of the Types the connection is between.
						__( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ),
						$this->from_type,
						$this->to_type
					);
				},
				'interfaces'        => $interfaces,
				'connection_config' => $this->config,
				'fields'            => $this->get_connection_fields(),
			]
		);
	}

	/**
	 * Returns fields to be used on the connection
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_connection_fields(): array {
		return array_merge(
			[
				'pageInfo' => [
					'type'        => [ 'non_null' => $this->connection_name . 'PageInfo' ],
					'description' => static function () {
						return __( 'Information about pagination in a connection.', 'wp-graphql' );
					},
				],
				'edges'    => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->connection_name . 'Edge' ] ] ],
					'description' => function () {
						// translators: %s is the name of the connection.
						return sprintf( __( 'Edges for the %s connection', 'wp-graphql' ), $this->connection_name );
					},
				],
				'nodes'    => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->to_type ] ] ],
					'description' => static function () {
						return __( 'The nodes of the connection, without the edges', 'wp-graphql' );
					},
				],
			],
			$this->connection_fields
		);
	}

	/**
	 * Get the args used for pagination on connections
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_pagination_args(): array {
		if ( true === $this->one_to_one ) {
			$pagination_args = [];
		} else {
			$pagination_args = [
				'first'  => [
					'type'        => 'Int',
					'description' => static function () {
						return __( 'The number of items to return after the referenced "after" cursor', 'wp-graphql' );
					},
				],
				'last'   => [
					'type'        => 'Int',
					'description' => static function () {
						return __( 'The number of items to return before the referenced "before" cursor', 'wp-graphql' );
					},
				],
				'after'  => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'Cursor used along with the "first" argument to reference where in the dataset to get data', 'wp-graphql' );
					},
				],
				'before' => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'Cursor used along with the "last" argument to reference where in the dataset to get data', 'wp-graphql' );
					},
				],
			];
		}

		return $pagination_args;
	}

	/**
	 * Registers the connection in the Graph
	 *
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
				'isConnectionField'     => true,
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
	 * @throws \Exception
	 */
	public function register_connection_interfaces(): void {
		$connection_edge_type = Utils::format_type_name( $this->to_type . 'ConnectionEdge' );

		if ( ! $this->type_registry->has_type( $this->to_type . 'ConnectionPageInfo' ) ) {
			$this->type_registry->register_interface_type(
				$this->to_type . 'ConnectionPageInfo',
				[
					'interfaces'  => [ 'WPPageInfo' ],
					'description' => static function () use ( $connection_edge_type ) {
						// translators: %s is the name of the connection edge.
						return sprintf( __( 'Pagination metadata specific to "%1$s" collections. Provides cursors and flags for navigating through sets of "%2$s" Nodes.', 'wp-graphql' ), $connection_edge_type, $connection_edge_type );
					},
					'fields'      => PageInfo::get_fields(),
				]
			);
		}

		if ( ! $this->type_registry->has_type( $connection_edge_type ) ) {
			$this->type_registry->register_interface_type(
				$connection_edge_type,
				[
					'interfaces'  => [ 'Edge' ],
					'description' => function () {
						// translators: %s is the name of the type the connection edge is to.
						return sprintf( __( 'Represents a connection to a %1$s. Contains both the %2$s Node and metadata about the relationship.', 'wp-graphql' ), $this->to_type, $this->to_type );
					},
					'fields'      => [
						'node' => [
							'type'        => [ 'non_null' => $this->to_type ],
							'description' => function () {
								// translators: %s is the name of the type the connection edge is to.
								return sprintf( __( 'The connected %s Node', 'wp-graphql' ), $this->to_type );
							},
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
					'description' => function () {
						// translators: %s is the name of the type the connection is to.
						return sprintf( __( 'A paginated collection of %1$s Nodes, Supports cursor-based pagination and filtering to efficiently retrieve sets of %2$s Nodes', 'wp-graphql' ), $this->to_type, $this->to_type );
					},
					'fields'      => [
						'edges'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $connection_edge_type ] ] ],
							'description' => function () {
								return sprintf(
									// translators: %1$s is the name of the type the connection is from, %2$s is the name of the type the connection is to.
									__( 'A list of edges (relational context) between %1$s and connected %2$s Nodes', 'wp-graphql' ),
									$this->from_type,
									$this->to_type
								);
							},
						],
						'pageInfo' => [
							'type' => [ 'non_null' => $this->to_type . 'ConnectionPageInfo' ],
						],
						'nodes'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => $this->to_type ] ] ],
							'description' => function () {
								// translators: %s is the name of the type the connection is to.
								return sprintf( __( 'A list of connected %s Nodes', 'wp-graphql' ), $this->to_type );
							},
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
}
