<?php
namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

use WPGraphQL\AppContext;
use WPGraphQL\Type\TermObject\Connection\TermObjectConnectionResolver;
use WPGraphQL\Type\Comment\Connection\CommentConnectionResolver;
use WPGraphQL\Type\Plugin\Connection\PluginConnectionResolver;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionResolver;
use WPGraphQL\Type\Theme\Connection\ThemeConnectionResolver;
use WPGraphQL\Type\User\Connection\UserConnectionResolver;
use WPGraphQL\Types;

/**
 * Class DataSource
 *
 * This class serves as a factory for all the resolvers for queries and mutations. This layer of
 * abstraction over the actual resolve functions allows easier, granular control over versioning as
 * we can change big things behind the scenes if/when needed, and we just need to ensure the
 * call to the DataSource method returns the expected data later on. This should make it easy
 * down the road to version resolvers if/when changes to the WordPress API are rolled out.
 *
 * @package WPGraphQL\Data
 * @since   0.0.4
 */
class DataSource {

	/**
	 * Stores an array of node definitions
	 *
	 * @var array $node_definition
	 * @since  0.0.4
	 * @access protected
	 */
	protected static $node_definition;

	/**
	 * Retrieves a WP_Comment object for the id that gets passed
	 *
	 * @param int $id ID of the comment we want to get the object for
	 *
	 * @return \WP_Comment object
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_comment( $id ) {

		$comment = \WP_Comment::get_instance( $id );
		if ( empty( $comment ) ) {
			throw new \Exception( sprintf( __( 'No comment was found with ID %s', 'wp-graphql' ), absint( $id ) ) );
		}

		return $comment;

	}

	/**
	 * Wrapper for the CommentsConnectionResolver::resolve method
	 *
	 * @param             WP_Post  object $source
	 * @param array       $args    Query args to pass to the connection resolver
	 * @param AppContext  $context The context of the query to pass along
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since 0.0.5
	 */
	public static function resolve_comments_connection( $source, array $args, $context, ResolveInfo $info ) {
		return CommentConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * Returns an array of data about the plugin you are requesting
	 *
	 * @param string $name Name of the plugin you want info for
	 *
	 * @return null|array
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_plugin( $name ) {

		// Puts input into a url friendly slug format.
		$slug   = sanitize_title( $name );
		$plugin = null;

		// The file may have not been loaded yet.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		/**
		 * NOTE: This is missing must use and drop in plugins.
		 */
		$plugins = apply_filters( 'all_plugins', get_plugins() );

		/**
		 * Loop through the plugins and find the matching one
		 * @since 0.0.5
		 */
		foreach ( $plugins as $path => $plugin_data ) {
			if ( sanitize_title( $plugin_data['Name'] ) === $slug ) {
				$plugin         = $plugin_data;
				$plugin['path'] = $path;
				// Exit early when plugin is found.
				break;
			}
		}

		/**
		 * Return the plugin, or throw an exception
		 */
		if ( ! empty( $plugin ) ) {
			return $plugin;
		} else {
			throw new \Exception( sprintf( __( 'No plugin was found with the name %s', 'wp-graphql' ), $name ) );
		}
	}

	/**
	 * Wrapper for PluginsConnectionResolver::resolve
	 *
	 * @param \WP_Post    $source  WP_Post object
	 * @param array       $args    Array of arguments to pass to reolve method
	 * @param object      $context AppContext object passed down
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_plugins_connection( $source, array $args, $context, ResolveInfo $info ) {
		return PluginConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * Returns the post object for the ID and post type passed
	 *
	 * @param int    $id        ID of the post you are trying to retrieve
	 * @param string $post_type Post type the post is attached to
	 *
	 * @throws \Exception
	 * @since  0.0.5
	 * @return \WP_Post
	 * @access public
	 */
	public static function resolve_post_object( $id, $post_type ) {

		$post_object = \WP_Post::get_instance( $id );
		if ( empty( $post_object ) ) {
			throw new \Exception( sprintf( __( 'No %1$s was found with the ID: %2$s', 'wp-graphql' ), $id, $post_type ) );
		}

		return $post_object;

	}

	/**
	 * Wrapper for PostObjectsConnectionResolver::resolve
	 *
	 * @param string      $post_type Post type of the post we are trying to resolve
	 * @param             $source
	 * @param array       $args      Arguments to pass to the resolve method
	 * @param AppContext  $context   AppContext object to pass down
	 * @param ResolveInfo $info      The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_post_objects_connection( $post_type, $source, array $args, $context, ResolveInfo $info ) {
		return PostObjectConnectionResolver::resolve( $post_type, $source, $args, $context, $info );
	}

	/**
	 * Gets the post type object from the post type name
	 *
	 * @param string $post_type Name of the post type you want to retrieve the object for
	 *
	 * @return \WP_Post_Type object
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_post_type( $post_type ) {

		/**
		 * Get the allowed_post_types
		 */
		$allowed_post_types = \WPGraphQL::$allowed_post_types;

		/**
		 * If the $post_type is one of the allowed_post_types
		 */
		if ( in_array( $post_type, $allowed_post_types, true ) ) {
			return get_post_type_object( $post_type );
		} else {
			throw new \Exception( sprintf( __( 'No post_type was found with the name %s', 'wp-graphql' ), $post_type ) );
		}

	}

	/**
	 * Retrieves the taxonomy object for the name of the taxonomy passed
	 *
	 * @param string $taxonomy Name of the taxonomy you want to retrieve the taxonomy object for
	 *
	 * @return \WP_Taxonomy object
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_taxonomy( $taxonomy ) {

		/**
		 * Get the allowed_taxonomies
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		/**
		 * If the $post_type is one of the allowed_post_types
		 */
		if ( in_array( $taxonomy, $allowed_taxonomies, true ) ) {
			return get_taxonomy( $taxonomy );
		} else {
			throw new \Exception( sprintf( __( 'No taxonomy was found with the name %s', 'wp-graphql' ), $taxonomy ) );
		}

	}

	/**
	 * Get the term object for a term
	 *
	 * @param int    $id       ID of the term you are trying to retrieve the object for
	 * @param string $taxonomy Name of the taxonomy the term is in
	 *
	 * @return mixed
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_term_object( $id, $taxonomy ) {

		$term_object = \WP_Term::get_instance( $id, $taxonomy );
		if ( empty( $term_object ) ) {
			throw new \Exception( sprintf( __( 'No %1$s was found with the ID: %2$s', 'wp-graphql' ), $id, $taxonomy ) );
		}

		return $term_object;

	}

	/**
	 * Wrapper for TermObjectConnectionResolver::resolve
	 *
	 * @param \WP_Taxonomy $taxonomy The WP_Taxonomy object of the taxonomy the term is connected to
	 * @param              $source
	 * @param array        $args     Array of args to be passed to the resolve method
	 * @param AppContext   $context  The AppContext object to be passed down
	 * @param ResolveInfo  $info     The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_term_objects_connection( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {
		return TermObjectConnectionResolver::resolve( $taxonomy, $source, $args, $context, $info );
	}

	/**
	 * Retrieves the theme object for the theme you are looking for
	 *
	 * @param string $stylesheet Directory name for the theme.
	 *
	 * @return \WP_Theme object
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );
		if ( $theme->exists() ) {
			return $theme;
		} else {
			throw new \Exception( sprintf( __( 'No theme was found with the stylesheet: %s', 'wp-graphql' ), $stylesheet ) );
		}
	}

	/**
	 * Wrapper for the ThemesConnectionResolver::resolve method
	 *
	 * @param             $source
	 * @param array       $args    Passes an array of arguments to the resolve method
	 * @param AppContext  $context The AppContext object to be passed down
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_themes_connection( $source, array $args, $context, ResolveInfo $info ) {
		return ThemeConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * Gets the user object for the user ID specified
	 *
	 * @param int $id ID of the user you want the object for
	 *
	 * @return bool|\WP_User
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_user( $id ) {
		$user = new \WP_User( $id );
		if ( ! $user->exists() ) {
			throw new \Exception( sprintf( __( 'No user was found with ID %s', 'wp-graphql' ), $id ) );
		}

		return $user;
	}

	/**
	 * Wrapper for the UsersConnectionResolver::resolve method
	 *
	 * @param             $source
	 * @param array       $args    Array of args to be passed down to the resolve method
	 * @param AppContext  $context The AppContext object to be passed down
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_users_connection( $source, array $args, $context, ResolveInfo $info ) {
		return UserConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * We get the node interface and field from the relay library.
	 *
	 * The first method is the way we resolve an ID to its object. The second is the way we resolve
	 * an object that implements node to its type.
	 *
	 * @return array
	 * @throws \Exception
	 * @access public
	 */
	public static function get_node_definition() {

		if ( null === self::$node_definition ) {

			$node_definition = Relay::nodeDefinitions(

				// The ID fetcher definition
				function( $global_id ) {

					if ( empty( $global_id ) ) {
						throw new \Exception( __( 'An ID needs to be provided to resolve a node.', 'wp-graphql' ) );
					}

					/**
					 * Convert the encoded ID into an array we can work with
					 * @since 0.0.4
					 */
					$id_components = Relay::fromGlobalId( $global_id );

					/**
					 * Get the allowed_post_types and allowed_taxonomies
					 * @since 0.0.5
					 */
					$allowed_post_types = \WPGraphQL::$allowed_post_types;
					$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

					if ( is_array( $id_components ) && ! empty( $id_components['id'] ) && ! empty( $id_components['type'] ) ) {
						switch ( $id_components['type'] ) {

							// postObjects
							case in_array( $id_components['type'], $allowed_post_types, true ):
								return self::resolve_post_object( $id_components['id'], $id_components['type'] );
							// termObjects
							case in_array( $id_components['type'], $allowed_taxonomies, true ):
								return self::resolve_term_object( $id_components['id'], $id_components['type'] );
							case 'comment':
								$comment = self::resolve_comment( $id_components['id'] );

								return $comment;
							case 'plugin':
								return self::resolve_plugin( $id_components['id'] );
							case 'post_type':
								return self::resolve_post_type( $id_components['id'] );
							case 'taxonomy':
								return self::resolve_taxonomy( $id_components['id'] );
							case 'theme':
								return self::resolve_theme( $id_components['id'] );
							case 'user':
								return self::resolve_user( $id_components['id'] );
							default:
								throw new \Exception( sprintf( __( 'No node could be found with global ID: %s', 'wp-graphql' ), $global_id ) );

						}
					} else {
						throw new \Exception( sprintf( __( 'The global ID isn\'t recognized ID: %s', 'wp-graphql' ), $global_id ) );
					}
				},

				// Type resolver
				function( $node ) {

					if ( is_object( $node ) ) {
						if ( $node instanceof \WP_Post ) {
							return Types::post_object( $node->post_type );
						} elseif ( $node instanceof \WP_Term ) {
							return Types::term_object( $node->taxonomy );
						} elseif ( $node instanceof \WP_Comment ) {
							return Types::comment();
						} elseif ( $node instanceof \WP_Post_Type ) {
							return Types::post_type();
						} elseif ( $node instanceof \WP_Taxonomy ) {
							return Types::taxonomy();
						} elseif ( $node instanceof \WP_Theme ) {
							return Types::theme();
						} elseif ( $node instanceof \WP_User ) {
							return Types::user();
						}
						// Some nodes might return an array instead of an object
					} elseif ( is_array( $node ) && array_key_exists( 'PluginURI', $node ) ) {
						return Types::plugin();
					}

					throw new \Exception( __( 'No type was found matching the node', 'wp-graphql' ) );
				}
			);

			self::$node_definition = $node_definition;

		}

		return self::$node_definition;
	}

}
