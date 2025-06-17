<?php

namespace WPGraphQL\Utils;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Server\OperationParams;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use WPGraphQL;
use WPGraphQL\Request;
use WPGraphQL\WPSchema;

/**
 * This class is used to identify "keys" relevant to the GraphQL Request.
 *
 * These keys can be used to identify common patterns across documents.
 *
 * A common use case would be for caching a GraphQL request and tagging the cached
 * object with these keys, then later using these keys to evict the cached
 * document.
 *
 * These keys can also be used by loggers to identify patterns, etc.
 *
 * @phpstan-type AnalyzedGraphQLKeys array{
 *  keys: string,
 *  keysLength: int,
 *  keysCount: int,
 *  skippedKeys: string,
 *  skippedKeysSize: int,
 *  skippedKeysCount: int,
 *  skippedTypes: string[]
 * }
 */
class QueryAnalyzer {

	/**
	 * @var \WPGraphQL\WPSchema|null
	 */
	protected $schema;

	/**
	 * Types that are referenced in the query
	 *
	 * @var string[]
	 */
	protected $queried_types = [];

	/**
	 * @var string
	 */
	protected $root_operation = '';

	/**
	 * Models that are referenced in the query
	 *
	 * @var string[]
	 */
	protected $models = [];

	/**
	 * Types in the query that are lists
	 *
	 * @var string[]
	 */
	protected $list_types = [];

	/**
	 * @var string[]|int[]
	 */
	protected $runtime_nodes = [];

	/**
	 * @var array<string,int[]|string[]>
	 */
	protected $runtime_nodes_by_type = [];

	/**
	 * @var string
	 */
	protected $query_id;

	/**
	 * @var \WPGraphQL\Request
	 */
	protected $request;

	/**
	 * The character length limit for headers
	 *
	 * @var int
	 */
	protected $header_length_limit;

	/**
	 * The keys that were skipped from being returned in the X-GraphQL-Keys header.
	 *
	 * @var string
	 */
	protected $skipped_keys = '';

	/**
	 * The GraphQL keys to return in the X-GraphQL-Keys header.
	 *
	 * @var AnalyzedGraphQLKeys|array{}
	 */
	protected $graphql_keys = [];

	/**
	 * Track all Types that were queried as a list.
	 *
	 * @var mixed[]
	 */
	protected $queried_list_types = [];

	/**
	 * @var ?bool Whether the Query Analyzer is enabled for the specific or not.
	 */
	protected $is_enabled_for_query;

	/**
	 * @param \WPGraphQL\Request $request The GraphQL request being executed
	 */
	public function __construct( Request $request ) {
		$this->request       = $request;
		$this->runtime_nodes = [];
		$this->models        = [];
		$this->list_types    = [];
		$this->queried_types = [];
	}

	/**
	 * Checks whether the Query Analyzer is enabled on the site.
	 *
	 * @uses `graphql_query_analyzer_enabled` filter.
	 */
	public static function is_enabled(): bool {
		$is_debug_enabled = WPGraphQL::debug();

		// The query analyzer is enabled if WPGraphQL Debugging is enabled
		$query_analyzer_enabled = $is_debug_enabled;

		// If WPGraphQL Debugging is not enabled, check the setting
		if ( ! $is_debug_enabled ) {
			$query_analyzer_enabled = get_graphql_setting( 'query_analyzer_enabled', 'off' );
			$query_analyzer_enabled = 'on' === $query_analyzer_enabled;
		}

		/**
		 * Filters whether to analyze queries for all GraphQL requests.
		 *
		 * @param bool $should_track_types Whether to analyze queries or not. Defaults to `true` if GraphQL Debugging is enabled, otherwise `false`.
		 */
		return apply_filters( 'graphql_should_analyze_queries', $query_analyzer_enabled );
	}

	/**
	 * Get the GraphQL Schema.
	 * If the schema is not set, it will be set.
	 *
	 * @throws \Exception
	 */
	public function get_schema(): ?WPSchema {
		if ( ! $this->schema ) {
			$this->schema = WPGraphQL::get_schema();
		}

		return $this->schema;
	}

	/**
	 * Gets the request object.
	 */
	public function get_request(): Request {
		return $this->request;
	}

	/**
	 * Checks if the Query Analyzer is enabled.
	 *
	 * @uses `graphql_should_analyze_queries` filter.
	 */
	public function is_enabled_for_query(): bool {
		if ( ! isset( $this->is_enabled_for_query ) ) {
			$is_enabled = self::is_enabled();

			/**
			 * Filters whether to analyze queries or for a specific GraphQL request.
			 *
			 * @param bool          $should_analyze_queries Whether to analyze queries for the current request. Defaults to the value of `graphql_query_analyzer_enabled` filter.
			 * @param \WPGraphQL\Request $request               The GraphQL request being executed
			 */
			$should_analyze_queries = apply_filters( 'graphql_should_analyze_query', $is_enabled, $this->get_request() );

			$this->is_enabled_for_query = true === $should_analyze_queries;
		}

		return $this->is_enabled_for_query;
	}

	/**
	 * Initialize the QueryAnalyzer.
	 */
	public function init(): void {
		$should_analyze_queries = $this->is_enabled_for_query();

		// If query analyzer is disabled, bail
		if ( true !== $should_analyze_queries ) {
			return;
		}

		$this->graphql_keys = [];
		$this->skipped_keys = '';

		/**
		 * Many clients have an 8k (8192 characters) header length cap.
		 *
		 * This is the total for ALL headers, not just individual headers.
		 *
		 * SEE: https://nodejs.org/en/blog/vulnerability/november-2018-security-releases/#denial-of-service-with-large-http-headers-cve-2018-12121
		 *
		 * In order to respect this, we have a default limit of 4000 characters for the X-GraphQL-Keys header
		 * to allow for other headers to total up to 8k.
		 *
		 * This value can be filtered to be increased or decreased.
		 *
		 * If you see "Parse Error: Header overflow" errors in your client, you might want to decrease this value.
		 *
		 * On the other hand, if you've increased your allowed header length in your client
		 * (i.e. https://github.com/wp-graphql/wp-graphql/issues/2535#issuecomment-1262499064) then you might want to increase this so that keys are not truncated.
		 *
		 * @param int $header_length_limit The max limit in (binary) bytes headers should be. Anything longer will be truncated.
		 */
		$this->header_length_limit = apply_filters( 'graphql_query_analyzer_header_length_limit', 4000 );

		// track keys related to the query
		add_action( 'do_graphql_request', [ $this, 'determine_graphql_keys' ], 10, 4 );

		// Track models loaded during execution
		add_filter( 'graphql_dataloader_get_model', [ $this, 'track_nodes' ], 10, 1 );

		// Expose query analyzer data in extensions
		add_filter(
			'graphql_request_results',
			[
				$this,
				'show_query_analyzer_in_extensions',
			],
			10,
			5
		);
	}

	/**
	 * Determine the keys associated with the GraphQL document being executed
	 *
	 * @param ?string                         $query     The GraphQL query
	 * @param ?string                         $operation The name of the operation
	 * @param ?array<string,mixed>            $variables Variables to be passed to your GraphQL request
	 * @param \GraphQL\Server\OperationParams $params The Operation Params. This includes any extra params,
	 * such as extensions or any other modifications to the request body
	 *
	 * @throws \Exception
	 */
	public function determine_graphql_keys( ?string $query, ?string $operation, ?array $variables, OperationParams $params ): void {

		// @todo: support for QueryID?

		// if the query is empty, get it from the (non-batch) request params
		if ( empty( $query ) && isset( $this->request->params->query ) ) {
			$query = $this->request->params->query ?: null;
		}

		if ( empty( $query ) ) {
			return;
		}

		$query_id       = Utils::get_query_id( $query );
		$this->query_id = $query_id ?: uniqid( 'gql:', true );

		// if there's a query (either saved or part of the request params)
		// get the GraphQL Types being asked for by the query
		$this->list_types    = $this->set_list_types( $this->get_schema(), $query );
		$this->queried_types = $this->set_query_types( $this->get_schema(), $query );
		$this->models        = $this->set_query_models( $this->get_schema(), $query );

		/**
		 * @param \WPGraphQL\Utils\QueryAnalyzer $query_analyzer The instance of the query analyzer
		 * @param string                         $query          The query string being executed
		 */
		do_action( 'graphql_determine_graphql_keys', $this, $query );
	}

	/**
	 * @return string[]
	 */
	public function get_list_types(): array {
		return array_unique( $this->list_types );
	}

	/**
	 * @return string[]
	 */
	public function get_query_types(): array {
		return array_unique( $this->queried_types );
	}

	/**
	 * @return string[]
	 */
	public function get_query_models(): array {
		return array_unique( $this->models );
	}

	/**
	 * @return string[]|int[]
	 */
	public function get_runtime_nodes(): array {
		/**
		 * @param string[]|int[] $runtime_nodes Nodes that were resolved during execution
		 */
		$runtime_nodes = apply_filters( 'graphql_query_analyzer_get_runtime_nodes', $this->runtime_nodes );

		return array_unique( $runtime_nodes );
	}

	/**
	 * Get the root operation of the query.
	 */
	public function get_root_operation(): string {
		return $this->root_operation;
	}

	/**
	 * Returns the operation name of the query, if there is one
	 */
	public function get_operation_name(): ?string {
		$operation_name = ! empty( $this->request->params->operation ) ? $this->request->params->operation : null;

		if ( empty( $operation_name ) ) {

			// If the query is not set on the params, return null
			if ( ! isset( $this->request->params->query ) ) {
				return null;
			}

			try {
				$ast            = Parser::parse( $this->request->params->query );
				$operation_name = ! empty( $ast->definitions[0]->name->value ) ? $ast->definitions[0]->name->value : null;
			} catch ( SyntaxError $error ) {
				return null;
			}
		}

		return ! empty( $operation_name ) ? 'operation:' . $operation_name : null;
	}

	/**
	 * Get the query id.
	 */
	public function get_query_id(): ?string {
		return $this->query_id;
	}

	/**
	 * @param \GraphQL\Type\Definition\Type            $type The Type of field
	 * @param \GraphQL\Type\Definition\FieldDefinition $field_def The field definition the type is for
	 * @param ?\GraphQL\Type\Definition\CompositeType  $parent_type The Parent Type
	 * @param bool                                     $is_list_type Whether the field is a list type field
	 *
	 * @return \GraphQL\Type\Definition\Type|string|null
	 */
	public static function get_wrapped_field_type( Type $type, FieldDefinition $field_def, $parent_type, bool $is_list_type = false ) {
		if ( ! isset( $parent_type->name ) || 'RootQuery' !== $parent_type->name ) {
			return null;
		}

		if ( $type instanceof NonNull || $type instanceof ListOfType ) {
			if ( $type instanceof ListOfType && ! empty( $parent_type->name ) ) {
				$is_list_type = true;
			}

			return self::get_wrapped_field_type( $type->getWrappedType(), $field_def, $parent_type, $is_list_type );
		}

		// Determine if we're dealing with a connection
		if ( $type instanceof ObjectType || $type instanceof InterfaceType ) {
			$interfaces      = $type->getInterfaces();
			$interface_names = ! empty( $interfaces ) ? array_map(
				static function ( InterfaceType $interface_obj ) {
					return $interface_obj->name;
				},
				$interfaces
			) : [];

			if ( array_key_exists( 'Connection', $interface_names ) ) {
				if ( isset( $field_def->config['fromType'] ) && ( 'rootquery' !== strtolower( $field_def->config['fromType'] ) ) ) {
					return null;
				}

				$to_type = $field_def->config['toType'] ?? null;
				if ( empty( $to_type ) ) {
					return null;
				}

				return $to_type;
			}

			if ( ! $is_list_type ) {
				return null;
			}

			return $type;
		}

		return null;
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema
	 * @param ?string               $query  The query string
	 *
	 * @return string[]
	 * @throws \GraphQL\Error\SyntaxError|\Exception
	 */
	public function set_list_types( ?Schema $schema, ?string $query ): array {

		/**
		 * @param string[]|null         $null   Default value for the filter
		 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string               $query  The query string being requested
		 */
		$null               = null;
		$pre_get_list_types = apply_filters( 'graphql_pre_query_analyzer_get_list_types', $null, $schema, $query );

		if ( null !== $pre_get_list_types ) {
			return $pre_get_list_types;
		}

		if ( empty( $query ) || null === $schema ) {
			return [];
		}

		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}

		$type_map  = [];
		$type_info = new TypeInfo( $schema );

		Visitor::visit(
			$ast,
			Visitor::visitWithTypeInfo(
				$type_info,
				[
					'enter' => static function ( $node ) use ( $type_info, &$type_map, $schema ) {
						$parent_type = $type_info->getParentType();

						if ( 'Field' !== $node->kind ) {
							Visitor::skipNode();
						}

						$type_info->enter( $node );
						$field_def = $type_info->getFieldDef();

						if ( ! $field_def instanceof FieldDefinition ) {
							return;
						}

						// Determine the wrapped type, which also determines if it's a listOf
						$field_type = $field_def->getType();
						$field_type = self::get_wrapped_field_type( $field_type, $field_def, $parent_type );

						if ( null === $field_type ) {
							return;
						}

						if ( ! empty( $field_type ) && is_string( $field_type ) ) {
							$field_type = $schema->getType( ucfirst( $field_type ) );
						}

						if ( ! $field_type ) {
							return;
						}

						$field_type = $schema->getType( $field_type );

						if ( ! $field_type instanceof ObjectType && ! $field_type instanceof InterfaceType ) {
							return;
						}

						// If the type being queried is an interface (i.e. ContentNode) the publishing a new
						// item of any of the possible types (post, page, etc) should invalidate
						// this query, so we need to tag this query with `list:$possible_type` for each possible type
						if ( $field_type instanceof InterfaceType ) {
							$possible_types = $schema->getPossibleTypes( $field_type );
							if ( ! empty( $possible_types ) ) {
								foreach ( $possible_types as $possible_type ) {
									$type_map[] = 'list:' . strtolower( $possible_type );
								}
							}
						} else {
							$type_map[] = 'list:' . strtolower( $field_type );
						}
					},
					'leave' => static function ( $node ) use ( $type_info ): void {
						$type_info->leave( $node );
					},
				]
			)
		);

		$map = array_values( array_unique( $type_map ) );

		return apply_filters( 'graphql_cache_collection_get_list_types', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema
	 * @param ?string               $query  The query string
	 *
	 * @return string[]
	 * @throws \Exception
	 */
	public function set_query_types( ?Schema $schema, ?string $query ): array {

		/**
		 * @param string[]|null         $null   Default value for the filter
		 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string               $query  The query string being requested
		 */
		$null                = null;
		$pre_get_query_types = apply_filters( 'graphql_pre_query_analyzer_get_query_types', $null, $schema, $query );

		if ( null !== $pre_get_query_types ) {
			return $pre_get_query_types;
		}

		if ( empty( $query ) || null === $schema ) {
			return [];
		}
		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}
		$type_map  = [];
		$type_info = new TypeInfo( $schema );

		Visitor::visit(
			$ast,
			Visitor::visitWithTypeInfo(
				$type_info,
				[
					'enter' => function ( $node ) use ( $type_info, &$type_map, $schema ): void {
						$type_info->enter( $node );
						$type = $type_info->getType();
						if ( ! $type ) {
							return;
						}

						if ( empty( $this->root_operation ) ) {
							if ( $type === $schema->getQueryType() ) {
								$this->root_operation = 'Query';
							}

							if ( $type === $schema->getMutationType() ) {
								$this->root_operation = 'Mutation';
							}

							if ( $type === $schema->getSubscriptionType() ) {
								$this->root_operation = 'Subscription';
							}
						}

						$named_type = Type::getNamedType( $type );

						if ( $named_type instanceof InterfaceType ) {
							$possible_types = $schema->getPossibleTypes( $named_type );
							foreach ( $possible_types as $possible_type ) {
								$type_map[] = strtolower( $possible_type );
							}
						} elseif ( $named_type instanceof ObjectType ) {
							$type_map[] = strtolower( $named_type );
						}
					},
					'leave' => static function ( $node ) use ( $type_info ): void {
						$type_info->leave( $node );
					},
				]
			)
		);
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		return apply_filters( 'graphql_cache_collection_get_query_types', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL model names that are being
	 * asked for by the query.
	 *
	 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema
	 * @param ?string               $query  The query string
	 *
	 * @return string[]
	 * @throws \GraphQL\Error\SyntaxError|\Exception
	 */
	public function set_query_models( ?Schema $schema, ?string $query ): array {

		/**
		 * @param string[]|null         $null   Default value for the filter
		 * @param ?\GraphQL\Type\Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string               $query  The query string being requested
		 */
		$null           = null;
		$pre_get_models = apply_filters( 'graphql_pre_query_analyzer_get_models', $null, $schema, $query );

		if ( null !== $pre_get_models ) {
			return $pre_get_models;
		}

		if ( empty( $query ) || null === $schema ) {
			return [];
		}
		try {
			$ast = Parser::parse( $query );
		} catch ( SyntaxError $error ) {
			return [];
		}

		/**
		 * @var array<string> $type_map
		 */
		$type_map  = [];
		$type_info = new TypeInfo( $schema );
		Visitor::visit(
			$ast,
			Visitor::visitWithTypeInfo(
				$type_info,
				[
					'enter' => static function ( $node ) use ( $type_info, &$type_map, $schema ): void {
						$type_info->enter( $node );
						$type = $type_info->getType();
						if ( ! $type ) {
							return;
						}

						$named_type = Type::getNamedType( $type );

						if ( $named_type instanceof InterfaceType ) {
							$possible_types = $schema->getPossibleTypes( $named_type );
							foreach ( $possible_types as $possible_type ) {
								if ( ! isset( $possible_type->config['model'] ) ) {
									continue;
								}
								$type_map[] = $possible_type->config['model'];
							}
						} elseif ( $named_type instanceof ObjectType ) {
							if ( ! isset( $named_type->config['model'] ) ) {
								return;
							}
							$type_map[] = $named_type->config['model'];
						}
					},
					'leave' => static function ( $node ) use ( $type_info ): void {
						$type_info->leave( $node );
					},
				]
			)
		);

		/**
		 * @var string[] $filtered
		 */
		$filtered = array_filter( $type_map );

		/** @var string[] $unique */
		$unique = array_unique( $filtered );

		/** @var string[] $map */
		$map = array_values( $unique );

		return apply_filters( 'graphql_cache_collection_get_query_models', $map, $schema, $query, $type_info );
	}

	/**
	 * Track the nodes that were resolved by ensuring the Node's model
	 * matches one of the models asked for in the query
	 *
	 * @template T
	 *
	 * @param T $model The Model to be returned by the loader
	 *
	 * @return T
	 */
	public function track_nodes( $model ) {
		if ( isset( $model->id ) && in_array( get_class( $model ), $this->get_query_models(), true ) ) {

			// Is this model type part of the requested/returned data in the asked for query?

			/**
			 * Filter the node ID before returning to the list of resolved nodes
			 *
			 * @param int             $model_id      The ID of the model (node) being returned
			 * @param object          $model         The Model object being returned
			 * @param string[]|int[]  $runtime_nodes The runtimes nodes already collected
			 */
			$node_id = apply_filters( 'graphql_query_analyzer_runtime_node', $model->id, $model, $this->runtime_nodes );

			$node_type = Utils::get_node_type_from_id( $node_id );

			if ( empty( $this->runtime_nodes_by_type[ $node_type ] ) || ! in_array( $node_id, $this->runtime_nodes_by_type[ $node_type ], true ) ) {
				$this->runtime_nodes_by_type[ $node_type ][] = $node_id;
			}

			$this->runtime_nodes[] = $node_id;
		}

		return $model;
	}

	/**
	 * Returns graphql keys for use in debugging and headers.
	 *
	 * @return AnalyzedGraphQLKeys
	 */
	public function get_graphql_keys() {
		if ( ! empty( $this->graphql_keys ) ) {
			return $this->graphql_keys;
		}

		$keys        = [];
		$return_keys = '';

		if ( $this->get_query_id() ) {
			$keys[] = $this->get_query_id();
		}

		if ( ! empty( $this->get_root_operation() ) ) {
			$keys[] = 'graphql:' . $this->get_root_operation();
		}

		if ( ! empty( $this->get_operation_name() ) ) {
			$keys[] = $this->get_operation_name();
		}

		if ( ! empty( $this->get_list_types() ) ) {
			$keys = array_merge( $keys, $this->get_list_types() );
		}

		if ( ! empty( $this->get_runtime_nodes() ) ) {
			$keys = array_merge( $keys, $this->get_runtime_nodes() );
		}

		if ( ! empty( $keys ) ) {
			$all_keys = implode( ' ', array_unique( array_values( $keys ) ) );

			// Use the header_length_limit to wrap the words with a separator
			$wrapped = wordwrap( $all_keys, $this->header_length_limit, '\n' );

			// explode the string at the separator. This creates an array of chunks that
			// can be used to expose the keys in multiple headers, each under the header_length_limit
			$chunks = explode( '\n', $wrapped );

			// Iterate over the chunks
			foreach ( $chunks as $index => $chunk ) {
				if ( 0 === $index ) {
					$return_keys = $chunk;
				} else {
					$this->skipped_keys = trim( $this->skipped_keys . ' ' . $chunk );
				}
			}
		}

		$skipped_keys_array = ! empty( $this->skipped_keys ) ? explode( ' ', $this->skipped_keys ) : [];
		$return_keys_array  = ! empty( $return_keys ) ? explode( ' ', $return_keys ) : [];
		$skipped_types      = [];

		$runtime_node_types = array_keys( $this->runtime_nodes_by_type );

		if ( ! empty( $skipped_keys_array ) ) {
			foreach ( $skipped_keys_array as $skipped_key ) {
				foreach ( $runtime_node_types as $node_type ) {
					if ( in_array( 'skipped:' . $node_type, $skipped_types, true ) ) {
						continue;
					}
					if ( in_array( $skipped_key, $this->runtime_nodes_by_type[ $node_type ], true ) ) {
						$skipped_types[] = 'skipped:' . $node_type;
						break;
					}
				}
			}
		}

		// If there are any skipped types, append them to the GraphQL Keys
		if ( ! empty( $skipped_types ) ) {
			$skipped_types_string = implode( ' ', $skipped_types );
			$return_keys         .= ' ' . $skipped_types_string;
		}

		/**
		 * @param AnalyzedGraphQLKeys $graphql_keys       Information about the keys and skipped keys returned by the Query Analyzer
		 * @param string              $return_keys        The keys returned to the X-GraphQL-Keys header
		 * @param string              $skipped_keys       The Keys that were skipped (truncated due to size limit) from the X-GraphQL-Keys header
		 * @param string[]            $return_keys_array  The keys returned to the X-GraphQL-Keys header, in array instead of string
		 * @param string[]            $skipped_keys_array The keys skipped, in array instead of string
		 */
		$this->graphql_keys = apply_filters(
			'graphql_query_analyzer_graphql_keys',
			[
				'keys'             => $return_keys,
				'keysLength'       => strlen( $return_keys ),
				'keysCount'        => ! empty( $return_keys_array ) ? count( $return_keys_array ) : 0,
				'skippedKeys'      => $this->skipped_keys,
				'skippedKeysSize'  => strlen( $this->skipped_keys ),
				'skippedKeysCount' => ! empty( $skipped_keys_array ) ? count( $skipped_keys_array ) : 0,
				'skippedTypes'     => $skipped_types,
			],
			$return_keys,
			$this->skipped_keys,
			$return_keys_array,
			$skipped_keys_array
		);

		return $this->graphql_keys;
	}

	/**
	 * Return headers
	 *
	 * @param array<string,string> $headers The array of headers being returned
	 *
	 * @return array<string,string>
	 */
	public function get_headers( array $headers = [] ): array {
		$keys = $this->get_graphql_keys();

		if ( ! empty( $keys ) ) {
			$headers['X-GraphQL-Query-ID'] = $this->query_id ?: '';
			$headers['X-GraphQL-Keys']     = $keys['keys'] ?: '';
		}

		/**
		 * @param array<string,string>           $headers The array of headers being returned
		 * @param \WPGraphQL\Utils\QueryAnalyzer $query_analyzer The instance of the query analyzer
		 */
		return apply_filters( 'graphql_query_analyzer_get_headers', $headers, $this );
	}

	/**
	 * Outputs Query Analyzer data in the extensions response
	 *
	 * @param mixed|array<string,mixed>|object $response
	 * @param \WPGraphQL\WPSchema              $schema         The WPGraphQL Schema
	 * @param string|null                      $operation_name The operation name being executed
	 * @param string|null                      $request        The GraphQL Request being made
	 * @param array<string,mixed>|null         $variables      The variables sent with the request
	 *
	 * @return mixed|array<string,mixed>|object
	 */
	public function show_query_analyzer_in_extensions( $response, WPSchema $schema, ?string $operation_name, ?string $request, ?array $variables ) {
		$should = $this->is_enabled_for_query() && WPGraphQL::debug();

		/**
		 * @param bool                              $should         Whether the query analyzer output should be displayed in the Extensions output. Defaults to true if the query analyzer is enabled for the request and WPGraphQL Debugging is enabled.
		 * @param  mixed|array<string,mixed>|object $response       The response of the WPGraphQL Request being executed
		 * @param \WPGraphQL\WPSchema               $schema         The WPGraphQL Schema
		 * @param string|null                       $operation_name The operation name being executed
		 * @param string|null                       $request        The GraphQL Request being made
		 * @param array<string,mixed>|null          $variables      The variables sent with the request
		 */
		$should_show_query_analyzer_in_extensions = apply_filters( 'graphql_should_show_query_analyzer_in_extensions', $should, $response, $schema, $operation_name, $request, $variables );

		// If the query analyzer output is disabled,
		// don't show the output in the response
		if ( false === $should_show_query_analyzer_in_extensions ) {
			return $response;
		}

		$keys = $this->get_graphql_keys();

		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				$response['extensions']['queryAnalyzer'] = $keys ?: null;
			} elseif ( is_object( $response ) ) {
				// @phpstan-ignore-next-line
				$response->extensions['queryAnalyzer'] = $keys ?: null;
			}
		}

		return $response;
	}
}
