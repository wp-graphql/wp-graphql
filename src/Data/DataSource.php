<?php

namespace WPGraphQL\Data;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PluginConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Data\Connection\CommentConnectionResolver;
use WPGraphQL\Data\Connection\ThemeConnectionResolver;
use WPGraphQL\Data\Connection\UserConnectionResolver;
use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Model\Avatar;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\CommentAuthor;
use WPGraphQL\Model\Plugin;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;
use WPGraphQL\Model\Taxonomy;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\Theme;
use WPGraphQL\Model\User;
use WPGraphQL\Model\UserRole;

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
	 * @param int        $id      ID of the comment we want to get the object for
	 * @param AppContext $context The context of the request
	 *
	 * @return Deferred object
	 * @throws UserError
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve_comment( $id, $context ) {

		if ( empty( $id ) || ! absint( $id ) ) {
			return null;
		}

		$comment_id = absint( $id );
		$context->getLoader( 'comment' )->buffer( [ $comment_id ] );

		return new Deferred(
			function() use ( $comment_id, $context ) {
				return $context->getLoader( 'comment' )->load( $comment_id );
			}
		);

	}

	/**
	 * Retrieves a WP_Comment object for the ID that gets passed
	 *
	 * @param int $comment_id The ID of the comment the comment author is associated with.
	 *
	 * @return CommentAuthor
	 * @throws
	 */
	public static function resolve_comment_author( $comment_id ) {
		global $wpdb;
		$comment_author = $wpdb->get_row( $wpdb->prepare( "SELECT comment_id, comment_author_email, comment_author, comment_author_url, comment_author_email from $wpdb->comments WHERE comment_id = %s LIMIT 1", esc_sql( $comment_id ) ) );
		$comment_author = ! empty( $comment_author ) ? (array) $comment_author : [];

		return new CommentAuthor( $comment_author );
	}

	/**
	 * Wrapper for the CommentsConnectionResolver class
	 *
	 * @param mixed  object $source
	 * @param array         $args    Query args to pass to the connection resolver
	 * @param AppContext    $context The context of the query to pass along
	 * @param ResolveInfo   $info    The ResolveInfo object
	 *
	 * @return mixed
	 * @since 0.0.5
	 * @throws \Exception
	 */
	public static function resolve_comments_connection( $source, array $args, $context, ResolveInfo $info ) {
		$resolver   = new CommentConnectionResolver( $source, $args, $context, $info );
		$connection = $resolver->get_connection();

		return $connection;
	}

	/**
	 * Returns the Plugin model for the plugin you are requesting
	 *
	 * @param string|array $info Name of the plugin you want info for, or the array of data for the
	 *                           plugin
	 *
	 * @return Plugin
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_plugin( $info ) {

		if ( ! is_array( $info ) ) {
			// Puts input into a url friendly slug format.
			$slug   = sanitize_title( $info );
			$plugin = null;

			// The file may have not been loaded yet.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			/**
			 * NOTE: This is missing must use and drop in plugins.
			 */
			$plugins = apply_filters( 'all_plugins', get_plugins() );

			/**
			 * Loop through the plugins and find the matching one
			 *
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
		} else {
			$plugin = $info;
		}

		/**
		 * Return the plugin, or throw an exception
		 */
		if ( ! empty( $plugin ) ) {
			return new Plugin( $plugin );
		} else {
			throw new UserError( sprintf( __( 'No plugin was found with the name %s', 'wp-graphql' ), $info ) );
		}
	}

	/**
	 * Wrapper for PluginsConnectionResolver::resolve
	 *
	 * @param \WP_Post    $source  WP_Post object
	 * @param array       $args    Array of arguments to pass to reolve method
	 * @param AppContext  $context AppContext object passed down
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 *
	 * @throws \Exception
	 */
	public static function resolve_plugins_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		return PluginConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * Returns the post object for the ID and post type passed
	 *
	 * @param int        $id      ID of the post you are trying to retrieve
	 * @param AppContext $context The context of the GraphQL Request
	 *
	 * @throws UserError
	 * @since  0.0.5
	 * @return Deferred
	 * @access public
	 *
	 * @throws \Exception
	 */
	public static function resolve_post_object( $id, AppContext $context ) {

		if ( empty( $id ) || ! absint( $id ) ) {
			return null;
		}
		$post_id = absint( $id );
		$context->getLoader( 'post_object' )->buffer( [ $post_id ] );

		return new Deferred(
			function() use ( $post_id, $context ) {
				return $context->getLoader( 'post_object' )->load( $post_id );
			}
		);

	}

	/**
	 * @param int        $id      The ID of the menu item to load
	 * @param AppContext $context The context of the GraphQL request
	 *
	 * @return Deferred|null
	 * @throws \Exception
	 */
	public static function resolve_menu_item( $id, AppContext $context ) {
		if ( empty( $id ) || ! absint( $id ) ) {
			return null;
		}
		$menu_item_id = absint( $id );
		$context->getLoader( 'menu_item' )->buffer( [ $menu_item_id ] );

		return new Deferred(
			function() use ( $menu_item_id, $context ) {
				return $context->getLoader( 'menu_item' )->load( $menu_item_id );
			}
		);
	}

	/**
	 * Wrapper for PostObjectsConnectionResolver
	 *
	 * @param             $source
	 * @param array              $args    Arguments to pass to the resolve method
	 * @param AppContext         $context AppContext object to pass down
	 * @param ResolveInfo        $info    The ResolveInfo object
	 * @param mixed string|array $post_type Post type of the post we are trying to resolve
	 *
	 * @return mixed
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve_post_objects_connection( $source, array $args, AppContext $context, ResolveInfo $info, $post_type ) {
		$resolver   = new PostObjectConnectionResolver( $source, $args, $context, $info, $post_type );
		$connection = $resolver->get_connection();

		return $connection;

	}

	/**
	 * Gets the post type object from the post type name
	 *
	 * @param string $post_type Name of the post type you want to retrieve the object for
	 *
	 * @return PostType object
	 * @throws UserError
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve_post_type( $post_type ) {

		/**
		 * Get the allowed_post_types
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		/**
		 * If the $post_type is one of the allowed_post_types
		 */
		if ( in_array( $post_type, $allowed_post_types, true ) ) {
			return new PostType( get_post_type_object( $post_type ) );
		} else {
			throw new UserError( sprintf( __( 'No post_type was found with the name %s', 'wp-graphql' ), $post_type ) );
		}

	}

	/**
	 * Retrieves the taxonomy object for the name of the taxonomy passed
	 *
	 * @param string $taxonomy Name of the taxonomy you want to retrieve the taxonomy object for
	 *
	 * @return Taxonomy object
	 * @throws UserError | \Exception
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
			return new Taxonomy( get_taxonomy( $taxonomy ) );
		} else {
			throw new UserError( sprintf( __( 'No taxonomy was found with the name %s', 'wp-graphql' ), $taxonomy ) );
		}

	}

	/**
	 * Get the term object for a term
	 *
	 * @param int        $id      ID of the term you are trying to retrieve the object for
	 * @param AppContext $context The context of the GraphQL Request
	 *
	 * @return mixed
	 * @throws \Exception
	 * @since  0.0.5
	 * @access public
	 */
	public static function resolve_term_object( $id, AppContext $context ) {

		if ( empty( $id ) || ! absint( $id ) ) {
			return null;
		}

		$term_id = absint( $id );
		$context->getLoader( 'term_object' )->buffer( [ $id ] );

		return new Deferred(
			function() use ( $term_id, $context ) {
				return $context->getLoader( 'term_object' )->load( $term_id );
			}
		);

	}

	/**
	 * Wrapper for TermObjectConnectionResolver::resolve
	 *
	 * @param              $source
	 * @param array        $args     Array of args to be passed to the resolve method
	 * @param AppContext   $context  The AppContext object to be passed down
	 * @param ResolveInfo  $info     The ResolveInfo object
	 * @param \WP_Taxonomy $taxonomy The WP_Taxonomy object of the taxonomy the term is connected to
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve_term_objects_connection( $source, array $args, $context, ResolveInfo $info, $taxonomy ) {
		$resolver   = new TermObjectConnectionResolver( $source, $args, $context, $info, $taxonomy );
		$connection = $resolver->get_connection();

		return $connection;
	}

	/**
	 * Retrieves the theme object for the theme you are looking for
	 *
	 * @param string $stylesheet Directory name for the theme.
	 *
	 * @return Theme object
	 * @throws UserError
	 * @since  0.0.5
	 * @access public
	 *
	 * @throws \Exception
	 */
	public static function resolve_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );
		if ( $theme->exists() ) {
			return new Theme( $theme );
		} else {
			throw new UserError( sprintf( __( 'No theme was found with the stylesheet: %s', 'wp-graphql' ), $stylesheet ) );
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
	 * @throws \Exception
	 */
	public static function resolve_themes_connection( $source, array $args, $context, ResolveInfo $info ) {
		return ThemeConnectionResolver::resolve( $source, $args, $context, $info );
	}

	/**
	 * Gets the user object for the user ID specified
	 *
	 * @param int        $id      ID of the user you want the object for
	 * @param AppContext $context The AppContext
	 *
	 * @return Deferred
	 * @since  0.0.5
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve_user( $id, AppContext $context ) {

		if ( empty( $id ) ) {
			return null;
		}
		$user_id = absint( $id );
		$context->getLoader( 'user' )->buffer( [ $user_id ] );

		return new Deferred(
			function() use ( $user_id, $context ) {
				return $context->getLoader( 'user' )->load( $user_id );
			}
		);
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
	 * @throws \Exception
	 */
	public static function resolve_users_connection( $source, array $args, $context, ResolveInfo $info ) {
		$resolver = new UserConnectionResolver( $source, $args, $context, $info );

		return $resolver->get_connection();

	}

	/**
	 * Returns an array of data about the user role you are requesting
	 *
	 * @param string $name Name of the user role you want info for
	 *
	 * @return UserRole
	 * @throws \Exception
	 * @since  0.0.30
	 * @access public
	 */
	public static function resolve_user_role( $name ) {

		$role = isset( wp_roles()->roles[ $name ] ) ? wp_roles()->roles[ $name ] : null;

		if ( null === $role ) {
			throw new UserError( sprintf( __( 'No user role was found with the name %s', 'wp-graphql' ), $name ) );
		} else {
			$role                = (array) $role;
			$role['id']          = $name;
			$role['displayName'] = $role['name'];
			$role['name']        = $name;

			return new UserRole( $role );
		}

	}

	/**
	 * Resolve the avatar for a user
	 *
	 * @param int   $user_id ID of the user to get the avatar data for
	 * @param array $args    The args to pass to the get_avatar_data function
	 *
	 * @return array|null|Avatar
	 * @throws \Exception
	 */
	public static function resolve_avatar( $user_id, $args ) {

		$avatar = get_avatar_data( absint( $user_id ), $args );

		if ( ! empty( $avatar ) ) {
			$avatar = new Avatar( $avatar );
		} else {
			$avatar = null;
		}

		return $avatar;

	}

	/**
	 * Resolve the connection data for user roles
	 *
	 * @param array       $source  The Query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext passed down to the query
	 * @param ResolveInfo $info    The ResloveInfo object
	 *
	 * @throws \Exception
	 * @return array
	 */
	public static function resolve_user_role_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new UserRoleConnectionResolver( $source, $args, $context, $info );

		return $resolver->get_connection();

	}

	/**
	 * Get all of the allowed settings by group and return the
	 * settings group that matches the group param
	 *
	 * @access public
	 *
	 * @param string $group
	 *
	 * @return array $settings_groups[ $group ]
	 */
	public static function get_setting_group_fields( $group ) {

		/**
		 * Get all of the settings, sorted by group
		 */
		$settings_groups = self::get_allowed_settings_by_group();

		return ! empty( $settings_groups[ $group ] ) ? $settings_groups[ $group ] : [];

	}

	/**
	 * Get all of the allowed settings by group
	 *
	 * @access public
	 * @return array $allowed_settings_by_group
	 */
	public static function get_allowed_settings_by_group() {

		/**
		 * Get all registered settings
		 */
		$registered_settings = get_registered_settings();

		/**
		 * Loop through the $registered_settings array and build an array of
		 * settings for each group ( general, reading, discussion, writing, reading, etc. )
		 * if the setting is allowed in REST or GraphQL
		 */
		foreach ( $registered_settings as $key => $setting ) {
			if ( ! isset( $setting['show_in_graphql'] ) ) {
				if ( isset( $setting['show_in_rest'] ) && false !== $setting['show_in_rest'] ) {
					$setting['key'] = $key;
					$allowed_settings_by_group[ $setting['group'] ][ $key ] = $setting;
				}
			} elseif ( true === $setting['show_in_graphql'] ) {
				$setting['key'] = $key;
				$allowed_settings_by_group[ $setting['group'] ][ $key ] = $setting;
			}
		};

		/**
		 * Set the setting groups that are allowed
		 */
		$allowed_settings_by_group = ! empty( $allowed_settings_by_group ) && is_array( $allowed_settings_by_group ) ? $allowed_settings_by_group : [];

		/**
		 * Filter the $allowed_settings_by_group to allow enabling or disabling groups in the GraphQL Schema.
		 *
		 * @param array $allowed_settings_by_group
		 */
		$allowed_settings_by_group = apply_filters( 'graphql_allowed_settings_by_group', $allowed_settings_by_group );

		return $allowed_settings_by_group;

	}

	/**
	 * Get all of the $allowed_settings
	 *
	 * @access public
	 * @return array $allowed_settings
	 */
	public static function get_allowed_settings() {

		/**
		 * Get all registered settings
		 */
		$registered_settings = get_registered_settings();

		/**
		 * Loop through the $registered_settings and if the setting is allowed in REST or GraphQL
		 * add it to the $allowed_settings array
		 */
		foreach ( $registered_settings as $key => $setting ) {
			if ( ! isset( $setting['show_in_graphql'] ) ) {
				if ( isset( $setting['show_in_rest'] ) && false !== $setting['show_in_rest'] ) {
					$setting['key']           = $key;
					$allowed_settings[ $key ] = $setting;
				}
			} elseif ( true === $setting['show_in_graphql'] ) {
				$setting['key']           = $key;
				$allowed_settings[ $key ] = $setting;
			}
		};

		/**
		 * Verify that we have the allowed settings
		 */
		$allowed_settings = ! empty( $allowed_settings ) && is_array( $allowed_settings ) ? $allowed_settings : [];

		/**
		 * Filter the $allowed_settings to allow some to be enabled or disabled from showing in
		 * the GraphQL Schema.
		 *
		 * @param array $allowed_settings
		 *
		 * @return array
		 */
		$allowed_settings = apply_filters( 'graphql_allowed_setting_groups', $allowed_settings );

		return $allowed_settings;

	}

	/**
	 * We get the node interface and field from the relay library.
	 *
	 * The first method is the way we resolve an ID to its object. The second is the way we resolve
	 * an object that implements node to its type.
	 *
	 * @return array
	 * @throws UserError
	 * @access public
	 */
	public static function get_node_definition() {

		if ( null === self::$node_definition ) {

			$node_definition = Relay::nodeDefinitions(

				// The ID fetcher definition
				function( $global_id, AppContext $context, ResolveInfo $info ) {
					self::resolve_node( $global_id, $context, $info );
				},
				// Type resolver
				function( $node ) {
					self::resolve_node_type( $node );
				}
			);

			self::$node_definition = $node_definition;

		}

		return self::$node_definition;
	}

	public static function resolve_node_type( $node ) {
		$type = null;

		if ( true === is_object( $node ) ) {

			switch ( true ) {
				case $node instanceof Post:
					$type = get_post_type_object( $node->post_type )->graphql_single_name;
					break;
				case $node instanceof Term:
					$type = get_taxonomy( $node->taxonomyName )->graphql_single_name;
					break;
				case $node instanceof Comment:
					$type = 'Comment';
					break;
				case $node instanceof PostType:
					$type = 'PostType';
					break;
				case $node instanceof Taxonomy:
					$type = 'Taxonomy';
					break;
				case $node instanceof Theme:
					$type = 'Theme';
					break;
				case $node instanceof User:
					$type = 'User';
					break;
				case $node instanceof Plugin:
					$type = 'Plugin';
					break;
				case $node instanceof CommentAuthor:
					$type = 'CommentAuthor';
					break;
				default:
					$type = null;
			}
		}

		/**
		 * Add a filter to allow externally registered node types to return the proper type
		 * based on the node_object that's returned
		 *
		 * @param mixed|object|array $type The type definition the node should resolve to.
		 * @param mixed|object|array $node The $node that is being resolved
		 *
		 * @since 0.0.6
		 */
		$type = apply_filters( 'graphql_resolve_node_type', $type, $node );
		$type = ucfirst( $type );

		/**
		 * If the $type is not properly resolved, throw an exception
		 *
		 * @since 0.0.6
		 */
		if ( null === $type ) {
			throw new UserError( __( 'No type was found matching the node', 'wp-graphql' ) );
		}

		/**
		 * Return the resolved $type for the $node
		 *
		 * @since 0.0.5
		 */
		return $type;
	}

	/**
	 * Given the ID of a node, this resolves the data
	 *
	 * @param string      $global_id The Global ID of the node
	 * @param AppContext  $context   The Context of the GraphQL Request
	 * @param ResolveInfo $info      The ResolveInfo for the GraphQL Request
	 *
	 * @return null|string
	 * @throws \Exception
	 */
	public static function resolve_node( $global_id, AppContext $context, ResolveInfo $info ) {
		if ( empty( $global_id ) ) {
			throw new UserError( __( 'An ID needs to be provided to resolve a node.', 'wp-graphql' ) );
		}

		/**
		 * Convert the encoded ID into an array we can work with
		 *
		 * @since 0.0.4
		 */
		$id_components = Relay::fromGlobalId( $global_id );

		/**
		 * If the $id_components is a proper array with a type and id
		 *
		 * @since 0.0.5
		 */
		if ( is_array( $id_components ) && ! empty( $id_components['id'] ) && ! empty( $id_components['type'] ) ) {

			/**
			 * Get the allowed_post_types and allowed_taxonomies
			 *
			 * @since 0.0.5
			 */
			$allowed_post_types = \WPGraphQL::get_allowed_post_types();
			$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

			switch ( $id_components['type'] ) {
				case in_array( $id_components['type'], $allowed_post_types, true ):
					$node = self::resolve_post_object( $id_components['id'], $context );
					break;
				case in_array( $id_components['type'], $allowed_taxonomies, true ):
					$node = self::resolve_term_object( $id_components['id'], $context );
					break;
				case 'comment':
					$node = self::resolve_comment( $id_components['id'], $context );
					break;
				case 'commentAuthor':
					$node = self::resolve_comment_author( $id_components['id'] );
					break;
				case 'plugin':
					$node = self::resolve_plugin( $id_components['id'] );
					break;
				case 'postType':
					$node = self::resolve_post_type( $id_components['id'] );
					break;
				case 'taxonomy':
					$node = self::resolve_taxonomy( $id_components['id'] );
					break;
				case 'theme':
					$node = self::resolve_theme( $id_components['id'] );
					break;
				case 'user':
					$user_id = absint( $id_components['id'] );

					if ( empty( $user_id ) || ! absint( $user_id ) ) {
						return null;
					}
					$context->getLoader( 'user' )->buffer( [ $user_id ] );

					return new Deferred(
						function() use ( $user_id, $context ) {
							return $context->getLoader( 'user' )->load( $user_id );
						}
					);
					break;
				default:
					/**
					 * Add a filter to allow externally registered node types to resolve based on
					 * the id_components
					 *
					 * @param int    $id   The id of the node, from the global ID
					 * @param string $type The type of node to resolve, from the global ID
					 *
					 * @since 0.0.6
					 */
					$node = apply_filters( 'graphql_resolve_node', null, $id_components['id'], $id_components['type'], $context );
					break;

			}

			/**
			 * If the $node is not properly resolved, throw an exception
			 *
			 * @since 0.0.6
			 */
			if ( ! $node ) {
				throw new UserError( sprintf( __( 'No node could be found with global ID: %s', 'wp-graphql' ), $global_id ) );
			}

			/**
			 * Return the resolved $node
			 *
			 * @since 0.0.5
			 */
			return $node;

		} else {
			throw new UserError( sprintf( __( 'The global ID isn\'t recognized ID: %s', 'wp-graphql' ), $global_id ) );
		}
	}

	/**
	 * Cached version of get_page_by_path so that we're not making unnecessary SQL all the time
	 *
	 * This is a modified version of the cached function from WordPress.com VIP MU Plugins here.
	 *
	 * @param string $uri
	 * @param string $output    Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
	 * @param string $post_type Optional. Post type; default is 'post'.
	 *
	 * @access public
	 * @return \WP_Post|null WP_Post on success or null on failure
	 * @see    https://github.com/Automattic/vip-go-mu-plugins/blob/52549ae9a392fc1343b7ac9dba4ebcdca46e7d55/vip-helpers/vip-caching.php#L186
	 * @link   http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
	 */
	public static function get_post_object_by_uri( $uri, $output = OBJECT, $post_type = 'post' ) {

		if ( is_array( $post_type ) ) {
			$cache_key = sanitize_key( $uri ) . '_' . md5( serialize( $post_type ) );
		} else {
			$cache_key = $post_type . '_' . sanitize_key( $uri );
		}
		$post_id = wp_cache_get( $cache_key, 'get_post_object_by_path' );

		if ( false === $post_id ) {
			$post    = get_page_by_path( $uri, $output, $post_type );
			$post_id = $post ? $post->ID : 0;
			if ( 0 === $post_id ) {
				wp_cache_set( $cache_key, $post_id, 'get_post_object_by_path', ( 1 * HOUR_IN_SECONDS + mt_rand( 0, HOUR_IN_SECONDS ) ) ); // We only store the ID to keep our footprint small
			} else {
				wp_cache_set( $cache_key, $post_id, 'get_post_object_by_path', 0 ); // We only store the ID to keep our footprint small
			}
		}
		if ( $post_id ) {
			return get_post( absint( $post_id ) );
		}

		return null;

	}

	/**
	 * Returns array of nav menu location names
	 *
	 * @access public
	 * @return array
	 */
	public static function get_registered_nav_menu_locations() {
		global $_wp_registered_nav_menus;

		return ! empty( $_wp_registered_nav_menus ) && is_array( $_wp_registered_nav_menus ) ? array_keys( $_wp_registered_nav_menus ) : [];
	}

	/**
	 * This resolves a resource, given a URI (the path / permalink to a resource)
	 *
	 * Based largely on the core parse_request function in wp-includes/class-wp.php
	 *
	 * @param string      $uri     The URI to fetch a resource from
	 * @param AppContext  $context The AppContext passed through the GraphQL Resolve Tree
	 * @param ResolveInfo $info    The ResolveInfo passed through the GraphQL Resolve tree
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function resolve_resource_by_uri( $uri, $context, $info ) {

		$node_resolver = new NodeResolver();
		return $node_resolver->resolve_uri( $uri );

	}

}
