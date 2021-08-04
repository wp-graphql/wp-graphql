<?php
namespace WPGraphQL\Type;

use Closure;
use Exception;
use GraphQL\Exception\InvalidArgument;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Registry\TypeRegistry;

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
	 * @var callable|Closure
	 */
	protected $resolve_connection;

	/**
	 * @var mixed|null
	 */
	protected $resolve_cursor;

	/**
	 * The name of the GraphQL Type the connection connects to
	 *
	 * @var string
	 */
	protected $to_type;

	/**
	 * The WPGraphQL TypeRegistry
	 *
	 * @var TypeRegistry
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
	 * @param array        $config The config array for the connection
	 * @param TypeRegistry $type_registry Instance of the WPGraphQL Type Registry
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		$this->validate_config( $config );

		$this->config                = $config;
		$this->type_registry         = $type_registry;
		$this->from_type             = $config['fromType'];
		$this->to_type               = $config['toType'];
		$this->from_field_name       = $config['fromFieldName'];
		$this->auth                  = array_key_exists( 'auth', $config ) && is_array( $config['auth'] ) ? $config['auth'] : [];
		$this->connection_fields     = array_key_exists( 'connectionFields', $config ) && is_array( $config['connectionFields'] ) ? $config['connectionFields'] : [];
		$this->connection_args       = array_key_exists( 'connectionArgs', $config ) && is_array( $config['connectionArgs'] ) ? $config['connectionArgs'] : [];
		$this->edge_fields           = array_key_exists( 'edgeFields', $config ) && is_array( $config['edgeFields'] ) ? $config['edgeFields'] : [];
		$this->resolve_cursor        = array_key_exists( 'resolveCursor', $config ) && is_callable( $config['resolve'] ) ? $config['resolveCursor'] : null;
		$this->resolve_connection    = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : function () {
			return null;
		};
		$this->connection_name       = ! empty( $config['connectionTypeName'] ) ? $config['connectionTypeName'] : $this->get_connection_name( $this->from_type, $this->to_type, $this->from_field_name );
		$this->where_args            = [];
		$this->one_to_one            = isset( $config['oneToOne'] ) && true === $config['oneToOne'];
		$this->connection_interfaces = isset( $config['connectionInterfaces'] ) && is_array( $config['connectionInterfaces'] ) ? $config['connectionInterfaces'] : [];
		$this->query_class           = array_key_exists( 'queryClass', $config ) && ! empty( $config['queryClass'] ) ? $config['queryClass'] : null;

	}

	/**
	 * Validates that essential key/value pairs are passed to the connection config.
	 *
	 * @param array $config
	 *
	 * @return void
	 */
	protected function validate_config( array $config ) {

		if ( ! array_key_exists( 'fromType', $config ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have at least a fromType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'toType', $config ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have a "toType" defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'fromFieldName', $config ) || ! is_string( $config['fromFieldName'] ) ) {
			throw new InvalidArgument( __( 'Connection config needs to have "fromFieldName" defined as a string value', 'wp-graphql' ) );
		}

	}

	/**
	 * Get edge interfaces
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function get_edge_interfaces( array $interfaces ) {
		if ( ! empty( $this->connection_interfaces ) ) {
			foreach ( $this->connection_interfaces as $connection_interface ) {
				$interfaces[] = $connection_interface . 'Edge';
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
	public function get_connection_name( string $from_type, string $to_type, string $from_field_name ) {

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
	 * @throws Exception
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
				// Translators: Placeholder is the name of the connection
				'description' => sprintf( __( 'Arguments for filtering the %s connection', 'wp-graphql' ), $this->connection_name ),
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
	 * @throws Exception
	 */
	protected function register_one_to_one_connection_edge_type() {

		$interfaces = [ 'SingleNodeConnectionEdge', 'Edge' ];
		$interfaces = $this->get_edge_interfaces( $interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'interfaces'  => $interfaces,
				// Translators: Placeholders are for the name of the Type the connection is coming from and the name of the Type the connection is going to
				'description' => sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
				'fields'      => array_merge(
					[
						'node' => [
							'type'        => $this->to_type,
							'description' => __( 'The node of the connection, without the edges', 'wp-graphql' ),
						],
					],
					$this->edge_fields
				),
			]
		);
	}

	/**
	 * Registers the Connection Edge type to the Schema
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function register_connection_edge_type() {

		$interfaces = [ 'Edge' ];
		$this->get_edge_interfaces( $interfaces );

		$this->type_registry->register_object_type(
			$this->connection_name . 'Edge',
			[
				'description' => __( 'An edge in a connection', 'wp-graphql' ),
				'interfaces'  => $interfaces,
				'fields'      => array_merge(
					[
						'cursor' => [
							'type'        => 'String',
							'description' => __( 'A cursor for use in pagination', 'wp-graphql' ),
							'resolve'     => $this->resolve_cursor,
						],
						'node'   => [
							'type'        => $this->to_type,
							'description' => __( 'The item at the end of the edge', 'wp-graphql' ),
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
	 * @throws Exception
	 */
	protected function register_connection_type() {

		$interfaces   = ! empty( $this->connection_interfaces ) ? $this->connection_interfaces : [];
		$interfaces[] = [ 'Connection' ];

		$this->type_registry->register_object_type(
			$this->connection_name,
			[
				// Translators: the placeholders are the name of the Types the connection is between.
				'description'       => sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
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
	protected function get_connection_fields() {

		return array_merge(
			[
				'pageInfo' => [
					// @todo: change to PageInfo when/if the Relay lib is deprecated
					'type'        => 'WPPageInfo',
					'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
				],
				'edges'    => [
					'type'        => [ 'list_of' => $this->connection_name . 'Edge' ],
					// Translators: Placeholder is the name of the connection
					'description' => sprintf( __( 'Edges for the %s connection', 'wp-graphql' ), $this->connection_name ),
				],
				'nodes'    => [
					'type'        => [ 'list_of' => $this->to_type ],
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
	protected function get_pagination_args() {

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
	 */
	public function register_connection_field() {

		$this->type_registry->register_field(
			$this->from_type,
			$this->from_field_name,
			[
				'type'        => true === $this->one_to_one ? $this->connection_name . 'Edge' : $this->connection_name,
				'args'        => array_merge( $this->get_pagination_args(), $this->where_args ),
				'auth'        => $this->auth,
				'description' => ! empty( $this->config['description'] ) ? $this->config['description'] : sprintf( __( 'Connection between the %1$s type and the %2$s type', 'wp-graphql' ), $this->from_type, $this->to_type ),
				'resolve'     => function ( $root, $args, $context, $info ) {

					if ( ! isset( $this->resolve_connection ) || ! is_callable( $this->resolve_connection ) ) {
						return null;
					}

					$context->connection_query_class = $this->query_class;
					$resolve_connection              = $this->resolve_connection;

					/**
					 * Return the results of the connection resolver
					 */
					return $resolve_connection( $root, $args, $context, $info );
				},
			]
		);

	}

	/**
	 * Registers the connection Types and field to the Schema
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function register_connection() {

		$this->register_connection_input();

		if ( true === $this->one_to_one ) {
			$this->register_one_to_one_connection_edge_type();
		} else {
			$this->register_connection_edge_type();
			$this->register_connection_type();
		}

		$this->register_connection_field();

	}

}
