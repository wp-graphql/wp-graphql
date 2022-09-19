<?php

namespace WPGraphQL\Utils;

use Exception;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Server\OperationParams;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use WPGraphQL\Model\Model;
use WPGraphQL\Request;

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
 */
class QueryAnalyzer {

	/**
	 * @var Schema
	 */
	protected $schema;

	/**
	 * Types that are referenced in the query
	 *
	 * @var array
	 */
	protected $queried_types = [];

	/**
	 * @var string
	 */
	protected $root_operation = 'Query';

	/**
	 * Models that are referenced in the query
	 *
	 * @var array
	 */
	protected $models = [];

	/**
	 * Types in the query that are lists
	 *
	 * @var array
	 */
	protected $list_types = [];

	/**
	 * @var array
	 */
	protected $runtime_nodes = [];

	/**
	 * @var string
	 */
	protected $query_id;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @param Request $request The GraphQL request being executed
	 */
	public function __construct( Request $request ) {
		$this->request       = $request;
		$this->schema        = $request->schema;
		$this->runtime_nodes = [];
		$this->models        = [];
		$this->list_types    = [];
		$this->queried_types = [];
	}

	/**
	 * @return Request
	 */
	public function get_request(): Request {
		return $this->request;
	}

	/**
	 * @return void
	 */
	public function init(): void {

		// allow query analyzer functionality to be disabled
		$should_analyze_queries = apply_filters( 'graphql_should_analyze_queries', true, $this );

		// If query analyzer is disabled, bail
		if ( true !== $should_analyze_queries ) {
			return;
		}

		// track keys related to the query
		add_action( 'do_graphql_request', [ $this, 'determine_graphql_keys' ], 10, 4 );

		// Track models loaded during execution
		add_filter( 'graphql_dataloader_get_model', [ $this, 'track_nodes' ], 10, 1 );

	}

	/**
	 * Determine the keys associated with the GraphQL document being executed
	 *
	 * @param ?string         $query     The GraphQL query
	 * @param ?string         $operation The name of the operation
	 * @param ?array          $variables Variables to be passed to your GraphQL request
	 * @param OperationParams $params    The Operation Params. This includes any extra params, such as extenions or any other modifications to the request body
	 *
	 * @return void
	 * @throws Exception
	 */
	public function determine_graphql_keys( ?string $query, ?string $operation, ?array $variables, OperationParams $params ): void {

		// @todo: support for QueryID?

		// if the query is empty, get it from the request params
		if ( empty( $query ) ) {
			$query = $this->request->params->query ?: null;
		}

		if ( empty( $query ) ) {
			return;
		}

		$query_id       = Utils::get_query_id( $query );
		$this->query_id = $query_id ?: uniqid( 'gql:', true );

		// if there's a query (either saved or part of the request params)
		// get the GraphQL Types being asked for by the query
		$this->list_types    = $this->set_list_types( $this->schema, $query );
		$this->queried_types = $this->set_query_types( $this->schema, $query );
		$this->models        = $this->set_query_models( $this->schema, $query );

		/**
		 * @param QueryAnalyzer $query_analyzer The instance of the query analyzer
		 * @param string $query The query string being executed
		 */
		do_action( 'graphql_determine_graphql_keys', $this, $query );

	}

	/**
	 * @return array
	 */
	public function get_list_types(): array {
		return array_unique( $this->list_types );
	}

	/**
	 * @return array
	 */
	public function get_query_types(): array {
		return array_unique( $this->queried_types );
	}

	/**
	 * @return array
	 */
	public function get_query_models(): array {
		return array_unique( $this->models );
	}

	/**
	 * @return array
	 */
	public function get_runtime_nodes(): array {
		return array_unique( $this->runtime_nodes );
	}

	/**
	 * @return string
	 */
	public function get_root_operation(): string {
		return $this->root_operation;
	}

	/**
	 * @return string|null
	 */
	public function get_query_id(): ?string {
		return $this->query_id;
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param ?Schema $schema The WPGraphQL Schema
	 * @param ?string $query  The query string
	 *
	 * @return array
	 * @throws SyntaxError|Exception
	 */
	public function set_list_types( ?Schema $schema, ?string $query ): array {

		/**
		 * @param array|null $null Default value for the filter
		 * @param ?Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string $query The query string being requested
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

		$visitor = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {

				$type_info->enter( $node );
				$type = $type_info->getType();

				if ( ! $type ) {
					return;
				}

				$named_type = Type::getNamedType( $type );

				// determine if the field is returning a list of types
				// or singular types
				// @todo: this might still be too fragile. We might need to adjust for cases where we can have list_of( nonNull( type ) ), etc
				$is_list_type = $named_type && ( Type::listOf( $named_type )->name === $type->name );

				if ( $named_type instanceof InterfaceType ) {
					$possible_types = $schema->getPossibleTypes( $named_type );
					foreach ( $possible_types as $possible_type ) {
						// if the type is a list, store it
						if ( $is_list_type && 0 !== strpos( $possible_type, '__' ) ) {
							$type_map[] = 'list:' . strtolower( $possible_type );
						}
					}
				} elseif ( $named_type instanceof ObjectType ) {
					// if the type is a list, store it
					if ( $is_list_type && 0 !== strpos( $named_type, '__' ) ) {
						$type_map[] = 'list:' . strtolower( $named_type );
					}
				}
			},
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_list_types', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL Types that are being asked for
	 * by the query.
	 *
	 * @param ?Schema $schema The WPGraphQL Schema
	 * @param ?string $query  The query string
	 *
	 * @return array
	 * @throws Exception
	 */
	public function set_query_types( ?Schema $schema, ?string $query ): array {

		/**
		 * @param array|null $null Default value for the filter
		 * @param ?Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string $query The query string being requested
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
		$visitor   = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {
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
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_query_types', $map, $schema, $query, $type_info );
	}

	/**
	 * Given the Schema and a query string, return a list of GraphQL model names that are being asked for
	 * by the query.
	 *
	 * @param ?Schema $schema The WPGraphQL Schema
	 * @param ?string $query  The query string
	 *
	 * @return array
	 * @throws SyntaxError|Exception
	 */
	public function set_query_models( ?Schema $schema, ?string $query ): array {

		/**
		 * @param array|null $null Default value for the filter
		 * @param ?Schema $schema The WPGraphQL Schema for the current request
		 * @param ?string $query The query string being requested
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
		$type_map  = [];
		$type_info = new TypeInfo( $schema );
		$visitor   = [
			'enter' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info, &$type_map, $schema ) {
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
			'leave' => function ( $node, $key, $parent, $path, $ancestors ) use ( $type_info ) {
				$type_info->leave( $node );
			},
		];

		Visitor::visit( $ast, Visitor::visitWithTypeInfo( $type_info, $visitor ) );
		$map = array_values( array_unique( array_filter( $type_map ) ) );

		// @phpcs:ignore
		return apply_filters( 'graphql_cache_collection_get_query_models', $map, $schema, $query, $type_info );
	}

	/**
	 * Track the nodes that were resolved by ensuring the Node's model
	 * matches one of the models asked for in the query
	 *
	 * @param mixed $model The Model to be returned by the loader
	 *
	 * @return mixed
	 */
	public function track_nodes( $model ) {

		if ( isset( $model->id ) && in_array( get_class( $model ), $this->get_query_models(), true ) ) {
			// Is this model type part of the requested/returned data in the asked for query?

			/**
			 * Filter the node ID before returning to the list of resolved nodes
			 *
			 * @param int    $model_id      The ID of the model (node) being returned
			 * @param object $model         The Model object being returned
			 * @param array  $runtime_nodes The runtimes nodes already collected
			 */
			$node_id = apply_filters( 'graphql_query_analyzer_runtime_node', $model->id, $model, $this->runtime_nodes );

			$this->runtime_nodes[] = $node_id;
		}

		return $model;
	}


	/**
	 * Return headers
	 *
	 * @param array $headers The array of headers being returned
	 *
	 * @return array
	 */
	public function get_headers( array $headers = [] ): array {

		$keys = [];

		if ( ! empty( $this->get_list_types() ) && is_array( $this->get_list_types() ) ) {
			$headers['X-GraphQL-List-Types'] = implode( ' ', array_unique( array_values( $this->get_list_types() ) ) );
			$keys                            = array_merge( $keys, $this->get_list_types() );

		}

		if ( ! empty( $this->get_runtime_nodes() ) && is_array( $this->get_runtime_nodes() ) ) {
			$headers['X-GraphQL-Nodes'] = implode( ' ', array_unique( array_values( $this->get_runtime_nodes() ) ) );
			$keys                       = array_merge( $keys, $this->get_runtime_nodes() );
		}

		if ( ! empty( $this->get_root_operation() ) ) {
			$headers['X-GraphQL-Operation-Type'] = $this->get_root_operation();
			$keys[]                              = 'graphql:' . $this->get_root_operation();
		}

		if ( ! empty( $keys ) ) {

			if ( $this->get_query_id() ) {
				$keys[] = $this->get_query_id();
			}

			$key_string = implode( ' ', array_unique( array_values( $keys ) ) );

			$headers['X-GraphQL-Query-ID']  = $this->query_id;
			$headers['X-GraphQL-Keys']      = $key_string;
			$headers['X-GraphQL-Keys-Size'] = strlen( $key_string ); // calculate the bytes of the keys

		}

		return $headers;
	}

}
