<?php
/**
 * WPGraphQL Abilities Prototype — POC: generate an ability from a persisted query.
 *
 * PROTOTYPE ONLY. This is the constructive flip-side of the experiments: instead
 * of pushing an ability *under* WPGraphQL's resolver (which loses batching or
 * laziness — see FINDINGS), we generate an ability that sits *above* it and runs
 * a persisted GraphQL query.
 *
 * The point it makes: an ability is, in essence, a registry entry for a typed
 * operation — a name, typed input, typed output, and a permission gate. A
 * persisted GraphQL query is already exactly that, and its types come from the
 * schema. So rather than hand-authoring an ability's JSON Schemas (which must be
 * kept in sync with behavior and are validated on every execute()), we DERIVE
 * both the ability's `input_schema` (from the operation's variable definitions)
 * AND its `output_schema` (from the operation's selection set + the GraphQL
 * schema), and let WPGraphQL's resolvers do the real (batched, lazy,
 * per-field-authz) work.
 *
 * Deriving the output schema is what tells a consumer that *this* persisted query
 * returns `{ post: { databaseId, title, date } }`, while another might return
 * `content` or something else entirely — without anyone hand-writing it.
 *
 * Here the persisted query is defined inline for a self-contained demo. In
 * practice it would come from a persisted-query store (e.g. WPGraphQL Smart
 * Cache's `graphql_document`s, or code/file-defined persisted queries), and the
 * derived schemas would be computed once and cached against the query hash rather
 * than rebuilt per request — wiring that up is left for later.
 *
 * @package WPGraphQL\Prototype\Abilities
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Input schema — derived from the operation's variable definitions (AST only,
 * no schema needed).
 * ---------------------------------------------------------------------- */

/**
 * Map a GraphQL named (scalar) type to a JSON Schema base type string.
 */
function wpgraphql_proto_scalar_to_json_type( string $type_name ): string {
	switch ( $type_name ) {
		case 'Int':
			return 'integer';
		case 'Float':
			return 'number';
		case 'Boolean':
			return 'boolean';
		case 'ID':
		case 'String':
		default:
			return 'string';
	}
}

/**
 * Convert a GraphQL variable type AST node to a JSON Schema fragment.
 *
 * @param \GraphQL\Language\AST\TypeNode|object $type_node The type AST node.
 * @return array{0:array<string,mixed>,1:bool} [ json_schema_fragment, is_required ]
 */
function wpgraphql_proto_input_type_to_json_schema( $type_node ): array {
	$required = false;

	if ( 'NonNullType' === $type_node->kind ) {
		$required  = true;
		$type_node = $type_node->type;
	}

	if ( 'ListType' === $type_node->kind ) {
		[ $inner ] = wpgraphql_proto_input_type_to_json_schema( $type_node->type );
		return [
			[
				'type'  => 'array',
				'items' => $inner,
			],
			$required,
		];
	}

	return [ [ 'type' => wpgraphql_proto_scalar_to_json_type( $type_node->name->value ) ], $required ];
}

/* -------------------------------------------------------------------------
 * Output schema — derived from the operation's selection set + the GraphQL
 * schema (to know each field's type). Kept nullable-lenient so output
 * validation doesn't reject legitimately-null fields (e.g. a forbidden node).
 * ---------------------------------------------------------------------- */

/**
 * Convert a (possibly wrapped) GraphQL output type + selection set to JSON Schema.
 *
 * @param \GraphQL\Type\Definition\Type            $type     The GraphQL output type.
 * @param \GraphQL\Language\AST\SelectionSetNode|null $sub_set The sub-selection, if any.
 * @param bool                                     $nullable Whether the value may be null.
 * @return array<string,mixed>
 */
function wpgraphql_proto_output_type_to_json_schema( $type, $sub_set, bool $nullable = true ): array {
	if ( $type instanceof \GraphQL\Type\Definition\NonNull ) {
		return wpgraphql_proto_output_type_to_json_schema( $type->getWrappedType(), $sub_set, false );
	}

	if ( $type instanceof \GraphQL\Type\Definition\ListOfType ) {
		$items = wpgraphql_proto_output_type_to_json_schema( $type->getWrappedType(), $sub_set, true );
		$schema = [
			'type'  => $nullable ? [ 'array', 'null' ] : 'array',
			'items' => $items,
		];
		return $schema;
	}

	$named = \GraphQL\Type\Definition\Type::getNamedType( $type );

	// Object / interface types: recurse into the selection set.
	if ( null !== $sub_set && method_exists( $named, 'getFields' ) && ! ( $named instanceof \GraphQL\Type\Definition\EnumType ) ) {
		$schema         = wpgraphql_proto_selection_set_to_json_schema( $sub_set, $named );
		$schema['type'] = $nullable ? [ 'object', 'null' ] : 'object';
		return $schema;
	}

	if ( $named instanceof \GraphQL\Type\Definition\EnumType ) {
		return [ 'type' => $nullable ? [ 'string', 'null' ] : 'string' ];
	}

	$base = $named instanceof \GraphQL\Type\Definition\ScalarType ? wpgraphql_proto_scalar_to_json_type( $named->name ) : 'string';
	return [ 'type' => $nullable ? [ $base, 'null' ] : $base ];
}

/**
 * Convert a selection set against a parent object/interface type to a JSON Schema
 * object describing the returned shape.
 *
 * Fragments are not expanded here (out of scope for the POC); only plain fields
 * are walked.
 *
 * @param \GraphQL\Language\AST\SelectionSetNode      $selection_set The selection set.
 * @param \GraphQL\Type\Definition\Type&object        $parent_type   The parent type (has getFields()).
 * @return array<string,mixed>
 */
function wpgraphql_proto_selection_set_to_json_schema( $selection_set, $parent_type ): array {
	$fields     = method_exists( $parent_type, 'getFields' ) ? $parent_type->getFields() : [];
	$properties = [];

	foreach ( $selection_set->selections as $selection ) {
		// Only plain field nodes; skip fragment spreads / inline fragments.
		if ( 'Field' !== $selection->kind ) {
			continue;
		}
		$name  = $selection->name->value;
		$alias = isset( $selection->alias ) && $selection->alias ? $selection->alias->value : $name;

		if ( ! isset( $fields[ $name ] ) ) {
			// e.g. __typename or an interface meta field — describe loosely.
			$properties[ $alias ] = [ 'type' => [ 'string', 'null' ] ];
			continue;
		}

		$field_type           = $fields[ $name ]->getType();
		$properties[ $alias ] = wpgraphql_proto_output_type_to_json_schema( $field_type, $selection->selectionSet ?? null );
	}

	return [
		'type'                 => 'object',
		'properties'           => $properties,
		'additionalProperties' => true,
	];
}

/* -------------------------------------------------------------------------
 * Registration.
 * ---------------------------------------------------------------------- */

/**
 * Register an ability that is generated from a persisted GraphQL query.
 *
 * Both `input_schema` (from variables) and `output_schema` (from the selection
 * set + schema) are DERIVED — no hand-written schemas. `execute_callback` runs
 * the operation through WPGraphQL.
 *
 * @param array{name:string,label:string,description:string,query:string} $pq The persisted query.
 */
function wpgraphql_proto_register_ability_from_persisted_query( array $pq ): void {
	if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( '\GraphQL\Language\Parser' ) ) {
		return;
	}

	$properties    = [];
	$required      = [];
	$output_schema = null;

	try {
		$ast       = \GraphQL\Language\Parser::parse( $pq['query'] );
		$operation = null;
		foreach ( $ast->definitions as $definition ) {
			if ( 'OperationDefinition' === $definition->kind ) {
				$operation = $definition;
				break;
			}
		}

		if ( null === $operation ) {
			return;
		}

		// Input schema from the operation variables (AST only).
		if ( null !== $operation->variableDefinitions ) {
			foreach ( $operation->variableDefinitions as $variable_definition ) {
				$variable_name                = $variable_definition->variable->name->value;
				[ $schema, $is_required ]     = wpgraphql_proto_input_type_to_json_schema( $variable_definition->type );
				$properties[ $variable_name ] = $schema;
				if ( $is_required ) {
					$required[] = $variable_name;
				}
			}
		}

		// Output schema from the operation's selection set + the GraphQL schema.
		// (In production this would be computed once and cached against the query
		// hash, not rebuilt per request.)
		$graphql_schema = \WPGraphQL::get_schema();
		$query_type     = $graphql_schema->getQueryType();
		if ( null !== $query_type ) {
			$output_schema = wpgraphql_proto_selection_set_to_json_schema( $operation->selectionSet, $query_type );
		}
	} catch ( \Throwable $e ) {
		// On any parse/schema failure, register without the derived schemas rather
		// than failing registration outright.
		$output_schema = null;
	}

	$input_schema = [
		'type'                 => 'object',
		'properties'           => $properties,
		'additionalProperties' => false,
	];
	if ( ! empty( $required ) ) {
		$input_schema['required'] = $required;
	}

	$args = [
		'label'               => $pq['label'],
		'description'         => $pq['description'],
		'category'            => 'data',
		// DERIVED from the persisted operation's variables — not hand-written.
		'input_schema'        => $input_schema,
		'permission_callback' => static function ( $input = null ) {
			// Authorization is enforced by WPGraphQL's resolvers / Model during
			// execution (per-field, per-row). A single per-call gate here would be
			// strictly coarser, so we defer.
			return true;
		},
		'execute_callback'    => static function ( $input = null ) use ( $pq ) {
			$result = graphql(
				[
					'query'     => $pq['query'],
					'variables' => is_array( $input ) ? $input : [],
				]
			);
			return $result['data'] ?? null;
		},
	];

	// DERIVED from the selection set — tells consumers exactly which fields this
	// persisted query returns.
	if ( null !== $output_schema ) {
		$args['output_schema'] = $output_schema;
	}

	wp_register_ability( $pq['name'], $args );
}

/**
 * Register a sample persisted query as an ability.
 *
 * In a real integration this list would be pulled from the persisted-query store
 * rather than hard-coded.
 */
add_action(
	'wp_abilities_api_init',
	static function (): void {
		wpgraphql_proto_register_ability_from_persisted_query(
			[
				'name'        => 'wpgraphql/post-by-database-id',
				'label'       => __( 'Get Post by Database ID (from persisted query)', 'wpgraphql-proto' ),
				'description' => __( 'Auto-generated from a persisted GraphQL query. Input and output schemas derived from the operation; execution and authorization defer entirely to WPGraphQL.', 'wpgraphql-proto' ),
				'query'       => 'query PostByDatabaseId($id: ID!) { post(id: $id, idType: DATABASE_ID) { databaseId title date } }',
			]
		);
	},
	20
);
