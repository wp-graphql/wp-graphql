<?php
/**
 * Class for handling deprecated functionality.
 *
 * Entirely deprecated classes can be relocated to the `deprecated/` directory, but things still need to be hooked into WordPress.
 *
 * @package  WPGraphQL
 */

namespace WPGraphQL;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Type\Union\MenuItemObjectUnion;
use WPGraphQL\Type\Union\PostObjectUnion;
use WPGraphQL\Type\Union\TermObjectUnion;
use WPGraphQL\Type\WPObjectType;

/**
 * Class - Deprecated
 */
final class Deprecated {
	/**
	 * The class constructor.
	 */
	public function __construct() {}

	/**
	 * Register the deprecated functionality.
	 */
	public function register(): void {
		$this->filters();
		// We want to defer the action until after the schema is registered.
		add_action( 'graphql_register_types', [ $this, 'register_deprecated_types' ] );
	}

	/**
	 * Handles deprecated filters.
	 */
	private function filters(): void {
		/**
		 * The `graphql_object_type_interfaces` filter
		 *
		 * @deprecated 1.4.1
		 * @todo Remove in 3.0.0
		 */
		add_filter(
			'graphql_type_interfaces',
			static function ( $interfaces, $config, $type ) {
				if ( ! $type instanceof WPObjectType || ! has_filter( 'graphql_object_type_interfaces' ) ) {
					return $interfaces;
				}

				/**
				 * @deprecated
				 *
				 * @param string[]                                                     $interfaces List of interfaces applied to the Object Type
				 * @param array<string,mixed>                                          $config     The config for the Object Type
				 * @param \WPGraphQL\Type\WPInterfaceType|\WPGraphQL\Type\WPObjectType $type       The Type instance
				 */
				return apply_filters_deprecated( 'graphql_object_type_interfaces', [ $interfaces, $config, $type ], '1.4.1', 'graphql_type_interfaces', __( 'This will be removed in the next major release of WPGraphQL.', 'wp-graphql' ) );
			},
			10,
			3
		);

		/**
		 * The `graphql_return_modeled_data` filter.
		 *
		 * @deprecated 1.7.0
		 * @todo Remove in 3.0.0
		 */
		add_filter(
			'graphql_model_prepare_fields',
			static function ( $fields, $model_name, $data, $visibility, $owner, $current_user ) {
				if ( ! has_filter( 'graphql_return_modeled_data' ) ) {
					return $fields;
				}

				/**
				 * @param array<string,mixed>    $fields       The array of fields for the model
				 * @param string                 $model_name   Name of the model the filter is currently being executed in
				 * @param string                 $visibility   The visibility setting for this piece of data
				 * @param ?int                   $owner        The user ID for the owner of this piece of data
				 * @param \WP_User               $current_user The current user for the session
				 *
				 * @deprecated 1.7.0 use "graphql_model_prepare_fields" filter instead, which passes additional context to the filter
				 */
				return apply_filters_deprecated(
					'graphql_return_modeled_data',
					[ $fields, $model_name, $visibility, $owner, $current_user ],
					'1.7.0',
					'graphql_model_prepare_fields',
					__( 'This will be removed in the next major release of WPGraphQL.', 'wp-graphql' )
				);
			},
			10,
			6
		);
	}

	/**
	 * Registers deprecated graphql types.
	 */
	public function register_deprecated_types(): void {
		MenuItemObjectUnion::register_type(); /* @phpstan-ignore staticMethod.deprecatedClass */
		PostObjectUnion::register_type(); /* @phpstan-ignore staticMethod.deprecatedClass */
		TermObjectUnion::register_type(); /* @phpstan-ignore staticMethod.deprecatedClass */

		$this->graphql_post_types();
		$this->menu_item_connected_object();
		$this->send_password_reset_email_user();
	}

	/**
	 * The `MenuItem` connectedObject field.
	 *
	 * @todo remove in 3.0.0
	 */
	private function menu_item_connected_object(): void {
		register_graphql_field(
			'MenuItem',
			'connectedObject',
			[
				'type'              => 'MenuItemObjectUnion',
				'deprecationReason' => static function () {
					return __( 'Deprecated in favor of the connectedNode field', 'wp-graphql' );
				},
				'description'       => static function () {
					return __( 'The object connected to this menu item.', 'wp-graphql' );
				},
				'resolve'           => static function ( $menu_item, array $args, AppContext $context, $info ) {
					$object_id   = intval( get_post_meta( $menu_item->menuItemId, '_menu_item_object_id', true ) );
					$object_type = get_post_meta( $menu_item->menuItemId, '_menu_item_type', true );

					switch ( $object_type ) {
						// Post object
						case 'post_type':
							$resolved_object = $context->get_loader( 'post' )->load_deferred( $object_id );
							break;

						// Taxonomy term
						case 'taxonomy':
							$resolved_object = $context->get_loader( 'term' )->load_deferred( $object_id );
							break;
						default:
							$resolved_object = null;
							break;
					}

					/**
					 * @todo Remove in 3.0.0.
					 *
					 * @param \WP_Post|\WP_Term                    $resolved_object Post or term connected to MenuItem
					 * @param array<string,mixed>                  $args            Array of arguments input in the field as part of the GraphQL query
					 * @param \WPGraphQL\AppContext                $context         Object containing app context that gets passed down the resolve tree
					 * @param \GraphQL\Type\Definition\ResolveInfo $info            Info about fields passed down the resolve tree
					 * @param int                                  $object_id       Post or term ID of connected object
					 * @param string                               $object_type     Type of connected object ("post_type" or "taxonomy")
					 *
					 * @since 0.0.30
					 */
					return apply_filters_deprecated(
						'graphql_resolve_menu_item',
						[
							$resolved_object,
							$args,
							$context,
							$info,
							$object_id,
							$object_type,
						],
						'1.22.0',
						'graphql_pre_resolve_menu_item_connected_node',
						__( 'This will be removed in the next version of WPGraphQL. Use the `graphql_pre_resolve_menu_item_connected_node` filter on `connectedNode` instead.', 'wp-graphql' )
					);
				},
			],
		);
	}

	/**
	 * Registers deprecated Post Type data to the schema
	 */
	private function graphql_post_types(): void {
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects', [ 'graphql_register_root_field' => true ] );

		foreach ( $allowed_post_types as $post_type_object ) {
			$this->post_type_by_field( $post_type_object );
			$this->register_deprecated_post_type_parents( $post_type_object );
			$this->register_deprecated_post_type_previews( $post_type_object );
		}
	}

	/**
	 * Register deprecated {PostType}By fields
	 *
	 * @todo remove in 3.0.0
	 *
	 * @param \WP_Post_Type $post_type_object The post type object to register the field for.
	 */
	private function post_type_by_field( $post_type_object ): void {
		$post_by_args = [
			'id'                                          => [
				'type'        => 'ID',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'Get the %s object by its global ID', 'wp-graphql' ),
						$post_type_object->graphql_single_name
					);
				},
			],
			$post_type_object->graphql_single_name . 'Id' => [
				'type'        => 'Int',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'Get the %s by its database ID', 'wp-graphql' ),
						$post_type_object->graphql_single_name
					);
				},
			],
			'uri'                                         => [
				'type'        => 'String',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'Get the %s by its uri', 'wp-graphql' ),
						$post_type_object->graphql_single_name
					);
				},
			],
		];

		if ( false === $post_type_object->hierarchical ) {
			$post_by_args['slug'] = [
				'type'        => 'String',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'Get the %s by its slug (only available for non-hierarchical types)', 'wp-graphql' ),
						$post_type_object->graphql_single_name
					);
				},
			];
		}

		/**
		 * @deprecated Deprecated in favor of single node entry points
		 */
		register_graphql_field(
			'RootQuery',
			$post_type_object->graphql_single_name . 'By',
			[
				'type'              => $post_type_object->graphql_single_name,
				'deprecationReason' => static function () {
					return __( 'Deprecated in favor of using the single entry point for this type with ID and IDType fields. For example, instead of postBy( id: "" ), use post(id: "" idType: "")', 'wp-graphql' );
				},
				'description'       => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'A %s object', 'wp-graphql' ),
						$post_type_object->graphql_single_name
					);
				},
				'args'              => $post_by_args,
				'resolve'           => static function ( $source, array $args, $context ) use ( $post_type_object ) {
					$post_id = 0;

					if ( ! empty( $args['id'] ) ) {
						$id_components = Relay::fromGlobalId( $args['id'] );
						if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
							throw new UserError( esc_html__( 'The "id" is invalid', 'wp-graphql' ) );
						}
						$post_id = absint( $id_components['id'] );
					} elseif ( ! empty( $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ] ) ) {
						$id      = $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ];
						$post_id = absint( $id );
					} elseif ( ! empty( $args['uri'] ) ) {
						return $context->node_resolver->resolve_uri(
							$args['uri'],
							[
								'post_type' => $post_type_object->name,
								'archive'   => false,
								'nodeType'  => 'ContentNode',
							]
						);
					} elseif ( ! empty( $args['slug'] ) ) {
						$slug = esc_html( $args['slug'] );

						return $context->node_resolver->resolve_uri(
							$slug,
							[
								'name'      => $slug,
								'post_type' => $post_type_object->name,
								'nodeType'  => 'ContentNode',
							]
						);
					}

					return $context->get_loader( 'post' )->load_deferred( $post_id )->then(
						static function ( $post ) use ( $post_type_object ) {

							// if the post type object isn't an instance of WP_Post_Type, return
							if ( ! $post_type_object instanceof \WP_Post_Type ) {
								return null;
							}

							// if the post isn't an instance of a Post model, return
							if ( ! $post instanceof Post ) {
								return null;
							}

							if ( ! isset( $post->post_type ) || ! in_array(
								$post->post_type,
								[
									'revision',
									$post_type_object->name,
								],
								true
							) ) {
								return null;
							}

							return $post;
						}
					);
				},
			]
		);
	}

	/**
	 * Register deprecated Post Type connections.
	 *
	 * @todo remove in 3.0.0
	 *
	 * @param \WP_Post_Type $post_type_object The post type object to register the connection for.
	 */
	private function register_deprecated_post_type_parents( \WP_Post_Type $post_type_object ): void {
		if ( $post_type_object->hierarchical || in_array( $post_type_object->name, [ 'attachment', 'revision' ], true ) ) {
			return;
		}

		// Ancestors
		register_graphql_connection(
			[
				'fromType'          => $post_type_object->graphql_single_name,
				'toType'            => $post_type_object->graphql_single_name,
				'fromFieldName'     => 'ancestors',
				'description'       => static function () {
						return __( 'The ancestors of the content node.', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'This content type is not hierarchical and typically will not have ancestors', 'wp-graphql' );
				},
				'resolve'           => static function () {
					return null;
				},
			]
		);

		// Parent
		register_graphql_connection(
			[
				'fromType'          => $post_type_object->graphql_single_name,
				'toType'            => $post_type_object->graphql_single_name,
				'fromFieldName'     => 'parent',
				'oneToOne'          => true,
				'description'       => static function () {
					return __( 'The parent of the content node.', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'This content type is not hierarchical and typically will not have a parent', 'wp-graphql' );
				},
				'resolve'           => static function () {
					return null;
				},
			]
		);
	}

	/**
	 * Register deprecated Post Type previews.
	 *
	 * @todo remove in 3.0.0
	 * @param \WP_Post_Type $post_type_object The post type object to register the preview for.
	 */
	private function register_deprecated_post_type_previews( \WP_Post_Type $post_type_object ): void {
		if ( in_array( $post_type_object->name, [ 'attachment', 'revision' ], true ) ) {
			return;
		}

		register_graphql_connection(
			[
				'fromType'           => $post_type_object->graphql_single_name,
				'toType'             => $post_type_object->graphql_single_name,
				'fromFieldName'      => 'preview',
				'connectionTypeName' => ucfirst( $post_type_object->graphql_single_name ) . 'ToPreviewConnection',
				'oneToOne'           => true,
				'deprecationReason'  => ( true === $post_type_object->publicly_queryable || true === $post_type_object->public ) ? null
					: sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'The "%s" Type is not publicly queryable and does not support previews. This field will be removed in the future.', 'wp-graphql' ),
						\WPGraphQL\Utils\Utils::format_type_name( $post_type_object->graphql_single_name )
					),
				'resolve'            => static function ( Post $post, $args, $context, $info ) {
					if ( $post->isRevision ) {
						return null;
					}

					if ( empty( $post->previewRevisionDatabaseId ) ) {
						return null;
					}

					$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'revision' );
					$resolver->set_query_arg( 'p', $post->previewRevisionDatabaseId );

					return $resolver->one_to_one()->get_connection();
				},
			]
		);
	}

	/**
	 * SendPasswordResetEmail.user output field\
	 *
	 * @todo remove in 3.0.0
	 */
	public function send_password_reset_email_user(): void {
		register_graphql_field(
			'SendPasswordResetEmailPayload',
			'user',
			[
				'type'              => 'User',
				'description'       => static function () {
					return __( 'The user that the password reset email was sent to', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'This field will be removed in a future version of WPGraphQL', 'wp-graphql' );
				},
				'resolve'           => static function ( $payload, $args, AppContext $context ) {
					return ! empty( $payload['id'] ) ? $context->get_loader( 'user' )->load_deferred( $payload['id'] ) : null;
				},
			],
		);
	}
}
