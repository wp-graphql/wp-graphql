<?php
/**
 * This registers custom Post Type and Taxomomy for use in query tests to make sure
 * APIs for Custom Taxonomies and Custom Post Types work as expected.
 */
add_action( 'init', function() {
	register_post_type(
		'bootstrap_cpt',
		[
			'show_in_graphql'     => true,
			'graphql_single_name' => 'bootstrapPost',
			'graphql_plural_name' => 'bootstrapPosts',
			'hierarchical'        => true,
			'taxonomies'          => [ 'bootstrap_tax' ],
		]
	);
	register_taxonomy(
		'bootstrap_tax',
		[ 'bootstrap_cpt' ],
		[
			'show_in_graphql'     => true,
			'graphql_single_name' => 'bootstrapTerm',
			'graphql_plural_name' => 'bootstrapTerms',
			'hierarchical'        => true,
		]
	);
});

/**
 * The most secure encryption function ever.
 *
 * @param string $msg  The message to be known be never communicated🥷.
 * @return void
 */
function encode_for_the_organizations_eyes_only( $msg ) {
	return ! empty( $msg ) ? "¯\_(ツ)_/¯{$msg}" : null;
}

/**
 * There's no fear in this dojo
 *
 * @return WP_Post The Master.
 */
$master_post_id = 0;
function call_the_master() {
	global $master_post_id;

	$the_master = get_post( $master_post_id );
	if ( ! $the_master ) {
		$master_post_id = wp_insert_post( [
			'post_title'   => 'test master post',
			'post_content' => 'Master of All',
			'post_status'  => 'publish',
			'post_type'    => 'bootstrap_ctm_query',
		] );
		$the_master     = get_post( $master_post_id );
	}

	return $the_master;
}

/**
 * This registers custom Post types that bypass parts of the schema tree
 * using WPGraphQL settings.
 */
add_action( 'init', function() {

	/**
	 * Register custom post-type that doesn't get a schema type definition.
	 */
	register_post_type(
		'bootstrap_ctm_type',
		[
			'show_in_graphql'      => true,
			'graphql_single_name'  => 'CustomSchemaType',
			'graphql_plural_name'  => 'CustomSchemaTypes',
			'supports'             => [ 'title', 'editor' ],
			'taxonomies'           => [ 'post_category' ],
			'register_schema_type' => false,
		]
	);

	/**
	 * Register a custom schema Object type with the field definitions/decorators on the type you wish
	 * to expose on the schema.
	 *
	 * This may seem kinda trivial with this simple example, but to see what the potential
	 * replacement type could be see the `Product` type in WooGraphQL, it's actually an Interface.
	 * https://github.com/wp-graphql/wp-graphql-woocommerce/blob/develop/includes/type/interface/class-product.php#L31
	 *
	 * Please note that if the "register_root_query", "register_root_connection" flags are set to true on
	 * post-type, which they are by default, the core PostObject query and connection definitions will
	 * still be fully-functional as long as a capable Model is still be used (Preferably, one that extends the \WPGraphQL\Model\Post class).
	 */
	register_graphql_type( 'CustomSchemaType', [
		'fields' => [
			'id'         => [
				'type' => [ 'non_null' => 'ID' ],
			],
			'databaseId' => [
				'type' => [ 'non_null' => 'ID' ],
			],
			'title'      => [
				'type'    => 'String',
				'resolve' => function( $source ) {
					return encode_for_the_organizations_eyes_only(
						get_post_field( 'post_title', $source->ID, 'raw' )
					);
				},
			],
			'content'    => [
				'type'    => 'String',
				'resolve' => function( $source ) {
					return encode_for_the_organizations_eyes_only(
						get_post_field( 'post_content', $source->ID, 'raw' )
					);
				},
			],
		],
	] );

	/**
	 * Register post-type that doesn't get a root query definition.
	 *
	 * There are a number of reasons to do this from the post-type not wanting post-type publicly queryable
	 * but still be somewhat viewable as a child of an post-type to simply adding some extra logic to act
	 * as extra layer of identification like in the "register_graphql_field()" call below.
	 */
	register_post_type(
		'bootstrap_ctm_query',
		[
			'show_in_graphql'     => true,
			'graphql_single_name' => 'CustomRootQuery',
			'graphql_plural_name' => 'CustomRootQueries',
			'taxonomies'          => [ 'category' ],
			'hierarchical'        => true,
			'register_root_query' => false,
		]
	);

	register_graphql_field( 'RootQuery', 'customRootQuery', [
		'type'    => 'CustomRootQuery',
		'args'    => [
			'id'     => [
				'type' => [ 'non_null' => 'ID' ],
			],
			'idType' => [
				'type' => 'CustomRootQueryIdType',
			],
		],
		'resolve' => function( $source, array $args ) {
			$id      = isset( $args['id'] ) ? $args['id'] : null;
			$id_type = isset( $args['idType'] ) ? $args['idType'] : 'global_id';

			// Just something unique.
			if ( 'thesecretpassword' === $id ) {
				return new \WPGraphQL\Model\Post( call_the_master() );
			}

			$post_id = null;
			switch ( $id_type ) {
				case 'database_id':
					$post_id = absint( $id );
					break;
				case 'global_id':
				default:
					$id_components = \GraphQLRelay\Relay::fromGlobalId( $id );
					if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
						throw new \GraphQL\Error\UserError( __( 'The "id" is invalid', 'wp-graphql-woocommerce' ) );
					}
					$post_id = absint( $id_components['id'] );
					break;
			}

			if ( empty( $post_id ) ) {
				throw new \GraphQL\Error\UserError(
					sprintf(
						/* translators: %1$s: ID type, %2$s: ID value */
						__( 'No CustomRootQuery ID was found corresponding to the %1$s: %2$s', 'wp-graphql' ),
						$id_type,
						$post_id
					)
				);
			}

			return new \WPGraphQL\Model\Post( get_post( $post_id ) );
		},
	] );

	register_post_type(
		'bootstrap_ctm_nodes',
		[
			'show_in_graphql'          => true,
			'graphql_single_name'      => 'CustomRootConnection',
			'graphql_plural_name'      => 'CustomRootConnections',
			'taxonomies'               => [ 'post_category' ],
			'register_root_connection' => false,
		]
	);
} );
