<?php

namespace WPGraphQL\Data;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\CommentConnectionResolver;
use WPGraphQL\Data\Connection\PluginConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Data\Connection\ThemeConnectionResolver;
use WPGraphQL\Data\Connection\UserConnectionResolver;
use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Model\Avatar;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\CommentAuthor;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\Plugin;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;
use WPGraphQL\Model\SettingGroup as SettingGroupModel;
use WPGraphQL\Model\Taxonomy;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\Theme;
use WPGraphQL\Model\User;
use WPGraphQL\Model\UserRole;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\ObjectType\SettingGroup;
use WPGraphQL\Utils\Utils;

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
	 * @var mixed[] $node_definition
	 * @since  0.0.4
	 */
	protected static $node_definition;


	/**
	 * Retrieves a WP_Comment object for the ID that gets passed
	 *
	 * @param int $comment_id The ID of the comment the comment author is associated with.
	 *
	 * @return \WPGraphQL\Model\CommentAuthor|null
	 * @throws \Exception Throws Exception.
	 */
	public static function resolve_comment_author( int $comment_id ) {
		$comment_author = get_comment( $comment_id );

		return ! empty( $comment_author ) ? new CommentAuthor( $comment_author ) : null;
	}

	/**
	 * Wrapper for the CommentsConnectionResolver class
	 *
	 * @param mixed                                $source  The object the connection is coming from
	 * @param array<string,mixed>                  $args    Query args to pass to the connection resolver
	 * @param \WPGraphQL\AppContext                $context The context of the query to pass along
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since 0.0.5
	 */
	public static function resolve_comments_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new CommentConnectionResolver( $source, $args, $context, $info );

		return $resolver->get_connection();
	}

	/**
	 * Wrapper for PluginsConnectionResolver::resolve
	 *
	 * @param mixed                                $source  The object the connection is coming from
	 * @param array<string,mixed>                  $args    Array of arguments to pass to resolve method
	 * @param \WPGraphQL\AppContext                $context AppContext object passed down
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since  0.0.5
	 */
	public static function resolve_plugins_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new PluginConnectionResolver( $source, $args, $context, $info );
		return $resolver->get_connection();
	}

	/**
	 * Wrapper for PostObjectsConnectionResolver
	 *
	 * @param mixed                                $source    The object the connection is coming from
	 * @param array<string,mixed>                  $args      Arguments to pass to the resolve method
	 * @param \WPGraphQL\AppContext                $context AppContext object to pass down
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 * @param mixed|string|string[]                $post_type Post type of the post we are trying to resolve
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since  0.0.5
	 */
	public static function resolve_post_objects_connection( $source, array $args, AppContext $context, ResolveInfo $info, $post_type ) {
		$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info, $post_type );

		return $resolver->get_connection();
	}

	/**
	 * Retrieves the taxonomy object for the name of the taxonomy passed
	 *
	 * @param string $taxonomy Name of the taxonomy you want to retrieve the taxonomy object for
	 *
	 * @return \WPGraphQL\Model\Taxonomy object
	 * @throws \GraphQL\Error\UserError If no taxonomy is found with the name passed.
	 * @since  0.0.5
	 */
	public static function resolve_taxonomy( $taxonomy ) {

		/**
		 * Get the allowed_taxonomies.
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
			// translators: %s is the name of the taxonomy.
			throw new UserError( esc_html( sprintf( __( 'No taxonomy was found with the name %s', 'wp-graphql' ), $taxonomy ) ) );
		}

		$tax_object = get_taxonomy( $taxonomy );

		if ( ! $tax_object instanceof \WP_Taxonomy ) {
			// translators: %s is the name of the taxonomy.
			throw new UserError( esc_html( sprintf( __( 'No taxonomy was found with the name %s', 'wp-graphql' ), $taxonomy ) ) );
		}

		return new Taxonomy( $tax_object );
	}

	/**
	 * Wrapper for TermObjectConnectionResolver::resolve
	 *
	 * @param mixed                                $source   The object the connection is coming from
	 * @param array<string,mixed>                  $args     Array of args to be passed to the resolve method
	 * @param \WPGraphQL\AppContext                $context The AppContext object to be passed down
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 * @param string                               $taxonomy The name of the taxonomy the term belongs to
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since  0.0.5
	 */
	public static function resolve_term_objects_connection( $source, array $args, AppContext $context, ResolveInfo $info, string $taxonomy ) {
		$resolver = new TermObjectConnectionResolver( $source, $args, $context, $info, $taxonomy );

		return $resolver->get_connection();
	}

	/**
	 * Retrieves the theme object for the theme you are looking for
	 *
	 * @param string $stylesheet Directory name for the theme.
	 *
	 * @return \WPGraphQL\Model\Theme object
	 * @throws \GraphQL\Error\UserError
	 * @since  0.0.5
	 */
	public static function resolve_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );
		if ( $theme->exists() ) {
			return new Theme( $theme );
		} else {
			// translators: %s is the name of the theme stylesheet.
			throw new UserError( esc_html( sprintf( __( 'No theme was found with the stylesheet: %s', 'wp-graphql' ), $stylesheet ) ) );
		}
	}

	/**
	 * Wrapper for the ThemesConnectionResolver::resolve method
	 *
	 * @param mixed                                $source  The object the connection is coming from
	 * @param array<string,mixed>                  $args    Passes an array of arguments to the resolve method
	 * @param \WPGraphQL\AppContext                $context The AppContext object to be passed down
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since  0.0.5
	 */
	public static function resolve_themes_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new ThemeConnectionResolver( $source, $args, $context, $info );
		return $resolver->get_connection();
	}

	/**
	 * Wrapper for the UsersConnectionResolver::resolve method
	 *
	 * @param mixed                                $source  The object the connection is coming from
	 * @param array<string,mixed>                  $args    Array of args to be passed down to the resolve method
	 * @param \WPGraphQL\AppContext                $context The AppContext object to be passed down
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 * @since  0.0.5
	 */
	public static function resolve_users_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new UserConnectionResolver( $source, $args, $context, $info );

		return $resolver->get_connection();
	}

	/**
	 * Returns an array of data about the user role you are requesting
	 *
	 * @param string $name Name of the user role you want info for
	 *
	 * @return \WPGraphQL\Model\UserRole
	 * @throws \GraphQL\Error\UserError If no user role is found with the name passed.
	 * @since  0.0.30
	 */
	public static function resolve_user_role( $name ) {
		$role = isset( wp_roles()->roles[ $name ] ) ? wp_roles()->roles[ $name ] : null;

		if ( null === $role ) {
			// translators: %s is the name of the user role.
			throw new UserError( esc_html( sprintf( __( 'No user role was found with the name %s', 'wp-graphql' ), $name ) ) );
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
	 * @param int                 $user_id ID of the user to get the avatar data for
	 * @param array<string,mixed> $args    The args to pass to the get_avatar_data function
	 *
	 * @return \WPGraphQL\Model\Avatar|null
	 * @throws \Exception
	 */
	public static function resolve_avatar( int $user_id, array $args ) {
		$avatar = get_avatar_data( absint( $user_id ), $args );

		// if there's no url returned, return null
		if ( empty( $avatar['url'] ) ) {
			return null;
		}

		$avatar = new Avatar( $avatar );

		if ( 'private' === $avatar->get_visibility() ) {
			return null;
		}

		return $avatar;
	}

	/**
	 * Resolve the connection data for user roles
	 *
	 * @param mixed[]                              $source  The Query results
	 * @param array<string,mixed>                  $args    The query arguments
	 * @param \WPGraphQL\AppContext                $context The AppContext passed down to the query
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 */
	public static function resolve_user_role_connection( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$resolver = new UserRoleConnectionResolver( $source, $args, $context, $info );

		return $resolver->get_connection();
	}

	/**
	 * Format the setting group name to our standard.
	 *
	 * @param string $group
	 *
	 * @return string $group
	 */
	public static function format_group_name( string $group ) {
		$replaced_group = graphql_format_name( $group, ' ', '/[^a-zA-Z0-9 -]/' );

		if ( ! empty( $replaced_group ) ) {
			$group = $replaced_group;
		}

		$group = lcfirst( str_replace( '_', ' ', ucwords( $group, '_' ) ) );
		$group = lcfirst( str_replace( '-', ' ', ucwords( $group, '_' ) ) );
		$group = lcfirst( str_replace( ' ', '', ucwords( $group, ' ' ) ) );

		return $group;
	}

	/**
	 * Get all of the allowed settings by group and return the
	 * settings group that matches the group param
	 *
	 * @param string                           $group
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array<string,mixed>
	 */
	public static function get_setting_group_fields( string $group, TypeRegistry $type_registry ) {

		/**
		 * Get all of the settings, sorted by group
		 */
		$settings_groups = self::get_allowed_settings_by_group( $type_registry );

		return ! empty( $settings_groups[ $group ] ) ? $settings_groups[ $group ] : [];
	}

	/**
	 * Build the canonical, normalized map of settings WPGraphQL can expose.
	 *
	 * The map is keyed by option name and each entry is the setting's registered
	 * args plus its `key`. Both read surfaces (the flat settings map and the
	 * grouped settings map) are derived from this single map so a setting cannot
	 * appear on one surface and not the other.
	 *
	 * The map is resolvable without a built schema. When a `TypeRegistry` is
	 * provided (the schema-build path) settings whose declared type has no
	 * corresponding GraphQL type are excluded, since they can't become fields.
	 * When resolved without one (e.g. cache invalidation reading the map outside a
	 * GraphQL request) that gate is skipped: the map is used to identify settings,
	 * not to register fields, so over-inclusion is harmless and no schema build is
	 * forced.
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry|null $type_registry The WPGraphQL TypeRegistry, or null to resolve the map without a built schema.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since x-release-please-version
	 */
	protected static function get_normalized_settings( ?TypeRegistry $type_registry = null ): array {

		/**
		 * Get all registered settings
		 */
		$registered_settings = get_registered_settings();

		/**
		 * Loop through the $registered_settings and add the setting to the
		 * normalized map if it is allowed in GraphQL, or in REST when the
		 * setting doesn't declare `show_in_graphql`.
		 */
		$normalized_settings = [];
		foreach ( $registered_settings as $key => $setting ) {
			$setting_key = (string) $key;

			if ( ! isset( $setting['type'] ) ) {
				continue;
			}

			// Only exclude by unknown GraphQL type when building the schema; when
			// the map is resolved without a registry the field-type gate doesn't apply.
			if ( null !== $type_registry && ! $type_registry->get_type( $setting['type'] ) ) {
				continue;
			}

			if ( ! isset( $setting['show_in_graphql'] ) ) {
				if ( ! isset( $setting['show_in_rest'] ) || false === $setting['show_in_rest'] ) {
					continue;
				}
			} elseif ( true !== $setting['show_in_graphql'] ) {
				continue;
			}

			$setting['key']                      = $setting_key;
			$normalized_settings[ $setting_key ] = $setting;
		}

		/**
		 * Apply WPGraphQL-managed config to core settings that need behavior
		 * beyond what their registration args declare.
		 */
		foreach ( self::get_core_setting_config() as $setting_key => $config ) {
			if ( isset( $normalized_settings[ $setting_key ] ) ) {
				$normalized_settings[ $setting_key ] = array_merge( $normalized_settings[ $setting_key ], $config );
			}
		}

		/**
		 * Seed the in-memory shims for options WordPress doesn't register via
		 * register_setting() (e.g. `home`, the permalink options). Added after the
		 * registered-settings loop and before the filter so extensions can override
		 * them, and only when the option isn't already present in the map so a real
		 * registration always wins.
		 */
		foreach ( self::get_core_shim_settings() as $setting_key => $shim ) {
			if ( ! isset( $normalized_settings[ $setting_key ] ) ) {
				$shim['key']                         = (string) $setting_key;
				$normalized_settings[ $setting_key ] = $shim;
			}
		}

		/**
		 * Filter the normalized settings map before the read and mutation surfaces are derived from it.
		 *
		 * This is the seam for exposing options WordPress never registers via register_setting():
		 * seed an entry here, in memory, instead of mutating the global settings registry. Entries
		 * follow the register_setting() args shape (`group`, `type`, `description`, ...) plus a `key`
		 * holding the option name, and support WPGraphQL-specific config: `graphql_readonly` (bool)
		 * rejects updates to the setting through the updateSettings mutation, and `graphql_resolve`
		 * (callable) normalizes the setting's resolved value.
		 *
		 * @param array<string,array<string,mixed>>     $normalized_settings The normalized settings map, keyed by option name.
		 * @param \WPGraphQL\Registry\TypeRegistry|null $type_registry       The WPGraphQL TypeRegistry, or null when the map is resolved without a built schema.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		$normalized_settings = apply_filters( 'graphql_normalized_settings', $normalized_settings, $type_registry );

		/**
		 * Ensure every entry carries its option name as `key`, so filter-added
		 * entries don't need to duplicate it. Then precompute each entry's GraphQL
		 * field names once, so every read/write surface reads the same name instead
		 * of re-deriving it independently.
		 */
		foreach ( $normalized_settings as $setting_key => $setting ) {
			if ( ! isset( $setting['key'] ) ) {
				$setting['key']                             = (string) $setting_key;
				$normalized_settings[ $setting_key ]['key'] = (string) $setting_key;
			}

			// The base/grouped field name (e.g. `homeUrl`, used on GeneralSettings).
			$field_name = self::get_setting_field_name( $setting );

			$normalized_settings[ $setting_key ]['graphql_field_name'] = $field_name;

			// The flat field name (e.g. `generalSettingsHomeUrl`, used on the Settings type)
			// only exists for entries that belong to a group.
			if ( ! empty( $setting['group'] ) ) {
				$normalized_settings[ $setting_key ]['graphql_settings_field_name'] = lcfirst( self::format_group_name( (string) $setting['group'] ) . 'Settings' . ucfirst( $field_name ) );
			}
		}

		return $normalized_settings;
	}

	/**
	 * Derive the base (grouped) GraphQL field name for a normalized setting.
	 *
	 * `graphql_field_name`, when set, overrides the name otherwise derived from the
	 * REST name (`show_in_rest['name']`) or the option key. In every case the name
	 * is run through `Utils::format_field_name()`, the same canonical formatter the
	 * field registration applies, so the precomputed name matches the name that
	 * ends up in the Schema and stays consistent with field naming elsewhere.
	 *
	 * @param array<string,mixed> $setting A normalized settings map entry.
	 *
	 * @since x-release-please-version
	 */
	protected static function get_setting_field_name( array $setting ): string {
		if ( ! empty( $setting['graphql_field_name'] ) ) {
			$name = (string) $setting['graphql_field_name'];
		} elseif ( ! empty( $setting['show_in_rest']['name'] ) ) {
			$name = (string) $setting['show_in_rest']['name'];
		} else {
			$name = isset( $setting['key'] ) ? (string) $setting['key'] : '';
		}

		return Utils::format_field_name( $name );
	}

	/**
	 * WPGraphQL-managed config for core settings, applied on top of their
	 * registered args in the normalized settings map.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since x-release-please-version
	 */
	protected static function get_core_setting_config(): array {
		return [
			// The administrator's email address is only readable by users who can
			// manage the site's options.
			'admin_email'     => [
				'graphql_capability' => 'manage_options',
			],
			// The site URL must not be updatable through the API. The input field is
			// kept (deprecated) rather than removed so existing schemas don't break.
			'siteurl'         => [
				'graphql_readonly'         => true,
				'graphql_deprecated_input' => __( 'The site URL is read-only and cannot be changed through the API.', 'wp-graphql' ),
			],
			// Derive the timezone from `gmt_offset` when `timezone_string` is empty.
			'timezone_string' => [
				'graphql_resolve' => [ SettingGroup::class, 'resolve_timezone_setting_value' ],
			],
		];
	}

	/**
	 * WPGraphQL-maintained shim settings for options WordPress does not register
	 * via register_setting() (e.g. the Site Address and the permalink options).
	 *
	 * These are seeded into the normalized settings map in memory so they surface
	 * in the Schema without mutating WordPress's global settings registry. Each
	 * entry follows the register_setting() args shape plus WPGraphQL per-entry
	 * config. They are seeded only when the option isn't already registered, so a
	 * real registration always wins.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since x-release-please-version
	 */
	protected static function get_core_shim_settings(): array {
		return [
			// "siteurl" is registered by core on single-site but not on multisite.
			// Seeding it here (only applied when it isn't already registered, i.e. on
			// multisite) exposes `url` and the flat `generalSettingsUrl` through the
			// settings machinery instead of a one-off polyfill field. On single-site
			// the registered setting wins.
			'siteurl'             => [
				'group'              => 'general',
				'type'               => 'string',
				'description'        => __( 'The base URL where the site\'s application and content management backend are served. Can differ from the `homeUrl` field when the front end and backend are served from different addresses, such as on headless or decoupled installs.', 'wp-graphql' ),
				'graphql_field_name' => 'url',
				'graphql_readonly'   => true,
				// Multisite-aware: get_site_url() returns the current site's URL.
				'graphql_resolve'    => static function () {
					return get_site_url();
				},
			],
			// "Site Address" (home) is not registered by core on any install.
			'home'                => [
				'group'              => 'general',
				'type'               => 'string',
				'description'        => __( 'The address at which visitors reach the site\'s front end. Can differ from the `url` field when the front end and the content management backend are served from different addresses, such as on headless or decoupled installs.', 'wp-graphql' ),
				'graphql_field_name' => 'homeUrl',
				'graphql_readonly'   => true,
				// Multisite-aware: get_home_url() returns the current site's home URL,
				// not the raw option value.
				'graphql_resolve'    => static function () {
					return get_home_url();
				},
			],
			// The permalink options drive the `uri` field on every content node and
			// term, so a change to any of them has schema-wide impact rather than
			// affecting only its own settings group. `graphql_purge_all` marks that
			// breadth for cache-invalidation consumers (e.g. WPGraphQL Smart Cache).
			'permalink_structure' => [
				'group'              => 'permalink',
				'type'               => 'string',
				'description'        => __( 'The structure used to build the URLs for content on the site.', 'wp-graphql' ),
				'graphql_field_name' => 'structure',
				'graphql_readonly'   => true,
				'graphql_purge_all'  => true,
			],
			'category_base'       => [
				'group'              => 'permalink',
				'type'               => 'string',
				'description'        => __( 'The prefix used in the URLs of category archive pages.', 'wp-graphql' ),
				'graphql_field_name' => 'categoryBase',
				'graphql_readonly'   => true,
				'graphql_purge_all'  => true,
			],
			'tag_base'            => [
				'group'              => 'permalink',
				'type'               => 'string',
				'description'        => __( 'The prefix used in the URLs of tag archive pages.', 'wp-graphql' ),
				'graphql_field_name' => 'tagBase',
				'graphql_readonly'   => true,
				'graphql_purge_all'  => true,
			],
		];
	}

	/**
	 * Get all of the allowed settings by group
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry|null $type_registry The WPGraphQL TypeRegistry, or null to resolve the grouped map without a built schema.
	 *
	 * @return array<string,array<string,mixed>> $allowed_settings_by_group
	 */
	public static function get_allowed_settings_by_group( ?TypeRegistry $type_registry = null ) {

		/**
		 * Group the normalized settings ( general, reading, discussion, writing, etc. ),
		 * skipping settings that don't have a group.
		 */
		$allowed_settings_by_group = [];
		foreach ( self::get_normalized_settings( $type_registry ) as $setting_key => $setting ) {
			if ( ! isset( $setting['group'] ) || empty( $setting['group'] ) ) {
				continue;
			}

			/** @var string $setting_group */
			$setting_group = $setting['group'];
			$group         = self::format_group_name( $setting_group );

			$allowed_settings_by_group[ $group ][ $setting_key ] = $setting;
		}

		/**
		 * Filter the $allowed_settings_by_group to allow enabling or disabling groups in the GraphQL Schema.
		 *
		 * @param array<string,array<string,mixed>> $allowed_settings_by_group The settings grouped by normalized setting group key.
		 *
		 * @hookGroup settings
		 * @since 0.0.1
		 */
		return apply_filters( 'graphql_allowed_settings_by_group', $allowed_settings_by_group );
	}

	/**
	 * Get all of the $allowed_settings
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry|null $type_registry The WPGraphQL TypeRegistry, or null to resolve the flat map without a built schema.
	 *
	 * @return array<string,array<string,mixed>> $allowed_settings
	 */
	public static function get_allowed_settings( ?TypeRegistry $type_registry = null ) {

		/**
		 * The flat view is the normalized map itself.
		 */
		$allowed_settings = self::get_normalized_settings( $type_registry );

		/**
		 * Filter the $allowed_settings to allow some to be enabled or disabled from showing in
		 * the GraphQL Schema.
		 *
		 * @param array<string,array<string,mixed>> $allowed_settings The settings that can be exposed in the GraphQL schema.
		 *
		 * @hookGroup settings
		 * @since 0.0.1
		 */
		return apply_filters( 'graphql_allowed_setting_groups', $allowed_settings );
	}

	/**
	 * We get the node interface and field from the relay library.
	 *
	 * The first method is the way we resolve an ID to its object. The second is the way we resolve
	 * an object that implements node to its type.
	 *
	 * @return mixed[]
	 * @throws \GraphQL\Error\UserError
	 */
	public static function get_node_definition() {
		if ( null === self::$node_definition ) {
			$node_definition = Relay::nodeDefinitions(
			// The ID fetcher definition
				static function ( $global_id, AppContext $context, ResolveInfo $info ) {
					self::resolve_node( $global_id, $context, $info );
				},
				// Type resolver
				static function ( $node ) {
					self::resolve_node_type( $node );
				}
			);

			self::$node_definition = $node_definition;
		}

		return self::$node_definition;
	}

	/**
	 * Given a node, returns the GraphQL Type
	 *
	 * @param mixed $node The node to resolve the type of
	 *
	 * @return string
	 * @throws \GraphQL\Error\UserError If no type is found for the node.
	 */
	public static function resolve_node_type( $node ) {
		$type = null;

		if ( true === is_object( $node ) ) {
			switch ( true ) {
				case $node instanceof Post:
					if ( $node->isRevision ) {
						/** @var ?\WP_Post */
						$parent_post = get_post( $node->parentDatabaseId );

						if ( ! empty( $parent_post ) ) {
							/** @var \WP_Post_Type $post_type_object */
							$post_type_object = get_post_type_object( $parent_post->post_type );
							$type             = $post_type_object->graphql_single_name ?? null;

							break;
						}
					}

					/** @var \WP_Post_Type $post_type_object */
					$post_type_object = isset( $node->post_type ) ? get_post_type_object( $node->post_type ) : null;
					$type             = $post_type_object->graphql_single_name ?? null;
					break;
				case $node instanceof Term:
					/** @var \WP_Taxonomy $tax_object */
					$tax_object = isset( $node->taxonomyName ) ? get_taxonomy( $node->taxonomyName ) : null;
					$type       = $tax_object->graphql_single_name;
					break;
				case $node instanceof Comment:
					$type = 'Comment';
					break;
				case $node instanceof PostType:
					$type = 'ContentType';
					break;
				case $node instanceof SettingGroupModel:
					$type = SettingGroup::get_type_name( $node->get_group_key() );
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
				case $node instanceof Menu:
					$type = 'Menu';
					break;
				case $node instanceof \_WP_Dependency:
					$type = isset( $node->type ) ? $node->type : null;
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
		 * @hookGroup request-lifecycle
		 * @since 0.0.6
		 */
		$type = apply_filters( 'graphql_resolve_node_type', $type, $node );

		/**
		 * If the $type is not properly resolved, throw an exception
		 *
		 * @since 0.0.6
		 */
		if ( empty( $type ) ) {
			throw new UserError( esc_html__( 'No type was found matching the node', 'wp-graphql' ) );
		}

		/**
		 * Return the resolved $type for the $node
		 *
		 * @since 0.0.5
		 */
		return ucfirst( $type );
	}

	/**
	 * Given the ID of a node, this resolves the data
	 *
	 * @param string                               $global_id The Global ID of the node
	 * @param \WPGraphQL\AppContext                $context The Context of the GraphQL Request
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo for the GraphQL Request
	 *
	 * @return ?\GraphQL\Deferred
	 * @throws \GraphQL\Error\UserError If no ID is passed.
	 */
	public static function resolve_node( $global_id, AppContext $context, ResolveInfo $info ) {
		if ( empty( $global_id ) ) {
			throw new UserError( esc_html__( 'An ID needs to be provided to resolve a node.', 'wp-graphql' ) );
		}

		/**
		 * Convert the encoded ID into an array we can work with
		 *
		 * @since 0.0.4
		 */
		$id_components = Relay::fromGlobalId( $global_id );

		/**
		 * $id_components is an array with the id and type
		 *
		 * @since 0.0.5
		 */
		if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
			// translators: %s is the global ID.
			throw new UserError( esc_html( sprintf( __( 'The global ID isn\'t recognized ID: %s', 'wp-graphql' ), $global_id ) ) );
		}

		/**
		 * Get the allowed_post_types and allowed_taxonomies
		 *
		 * @since 0.0.5
		 */

		$loader = $context->get_loader( $id_components['type'] );

		if ( $loader ) {
			return $loader->load_deferred( $id_components['id'] );
		}

		return null;
	}

	/**
	 * Returns array of nav menu location names
	 *
	 * @return string[]
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
	 * @param string                               $uri     The URI to fetch a resource from
	 * @param \WPGraphQL\AppContext                $context The AppContext passed through the GraphQL Resolve Tree
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed through the GraphQL Resolve tree
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 */
	public static function resolve_resource_by_uri( $uri, $context, $info ) {
		$node_resolver = new NodeResolver( $context );

		return $node_resolver->resolve_uri( $uri );
	}

	/**
	 * @todo remove in 3.0.0
	 * @deprecated Use the Loader passed in $context instead
	 * @codeCoverageIgnore
	 *
	 * @param int                   $id      ID of the comment we want to get the object for.
	 * @param \WPGraphQL\AppContext $context The context of the request.
	 *
	 * @return \GraphQL\Deferred object
	 * @throws \GraphQL\Error\UserError Throws UserError.
	 * @throws \Exception Throws UserError.
	 */
	public static function resolve_comment( $id, $context ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the method name */
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				'$context->get_loader( \'comment\' )->load_deferred( $id )'
			),
			'0.8.4'
		);

		return $context->get_loader( 'comment' )->load_deferred( $id );
	}

	/**
	 * @todo remove in 3.0.0
	 * @deprecated Use the Loader passed in $context instead
	 * @codeCoverageIgnore
	 *
	 * @param int                   $id      ID of the post you are trying to retrieve
	 * @param \WPGraphQL\AppContext $context The context of the GraphQL Request
	 *
	 * @return \GraphQL\Deferred
	 *
	 * @throws \GraphQL\Error\UserError
	 * @throws \Exception
	 */
	public static function resolve_post_object( int $id, AppContext $context ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the method name */
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				'$context->get_loader( \'post\' )->load_deferred( $id )'
			),
			'0.8.4'
		);
		return $context->get_loader( 'post' )->load_deferred( $id );
	}

	/**
	 * @todo remove in 3.0.0
	 * @deprecated Use the Loader passed in $context instead
	 * @codeCoverageIgnore
	 *
	 * @param int                   $id      The ID of the menu item to load
	 * @param \WPGraphQL\AppContext $context The context of the GraphQL request
	 *
	 * @return \GraphQL\Deferred|null
	 * @throws \Exception
	 */
	public static function resolve_menu_item( int $id, AppContext $context ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the method name */
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				'$context->get_loader( \'menu_item\' )->load_deferred( $id )'
			),
			'0.8.4'
		);
		return $context->get_loader( 'post' )->load_deferred( $id );
	}

	/**
	 * @todo remove in 3.0.0
	 * @deprecated Use the Loader passed in $context instead
	 * @codeCoverageIgnore
	 *
	 * @param int                   $id      ID of the term you are trying to retrieve the object for
	 * @param \WPGraphQL\AppContext $context The context of the GraphQL Request
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 */
	public static function resolve_term_object( $id, AppContext $context ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the method name */
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				'$context->get_loader( \'term\' )->load_deferred( $id )'
			),
			'0.8.4'
		);
		return $context->get_loader( 'term' )->load_deferred( $id );
	}

	/**
	 * @todo remove in 3.0.0
	 * @deprecated Use the Loader passed in $context instead
	 * @codeCoverageIgnore
	 *
	 * @param int                   $id      ID of the user you want the object for
	 * @param \WPGraphQL\AppContext $context The AppContext
	 *
	 * @return \GraphQL\Deferred
	 * @throws \Exception
	 */
	public static function resolve_user( $id, AppContext $context ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the method name */
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				'$context->get_loader( \'user\' )->load_deferred( $id )'
			),
			'0.8.4'
		);
		return $context->get_loader( 'user' )->load_deferred( $id );
	}
}
