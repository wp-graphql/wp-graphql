<?php

namespace WPGraphQL\Utils;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;

class Preview {

	/**
	 * The preview context keys core defines. Third-party members may not use these
	 * names (or their header keys): the overlay is keyed on `databaseId` and
	 * `revisionDatabaseId`, so letting a member shadow them is a hard refusal.
	 */
	private const RESERVED_CONTEXT_MEMBERS = [
		'databaseId'              => 'database_id',
		'revisionDatabaseId'      => 'revision_database_id',
		'featuredImageDatabaseId' => 'featured_image_database_id',
		'nonce'                   => 'nonce',
	];

	/**
	 * The member types a third-party preview context member may declare.
	 */
	private const CONTEXT_MEMBER_TYPES = [ 'integer', 'string', 'boolean', 'inner-list' ];

	/**
	 * The item types an `inner-list` member may declare.
	 */
	private const CONTEXT_MEMBER_ITEM_TYPES = [ 'integer', 'string', 'boolean' ];

	/**
	 * The preview context members registered by extensions, keyed by camelCase name.
	 *
	 * @var array<string,array{type:string,items:?string,header_key:string,description:string,sanitize:callable}>
	 */
	private static $context_members = [];

	/**
	 * Registers a third-party member of the request preview context.
	 *
	 * See register_graphql_preview_context_member() for the config contract.
	 *
	 * @param string              $name   The camelCase member name.
	 * @param array<string,mixed> $config The member config.
	 */
	public static function register_context_member( string $name, array $config ): void {
		if ( isset( self::RESERVED_CONTEXT_MEMBERS[ $name ] ) ) {
			self::debug_context_member_not_registered( $name, __( 'it is reserved by WPGraphQL core', 'wp-graphql' ) );
			return;
		}

		if ( ! preg_match( '/^[a-z][a-zA-Z0-9]*$/', $name ) ) {
			self::debug_context_member_not_registered( $name, __( 'the name must be camelCase (a lowercase letter followed by letters and digits)', 'wp-graphql' ) );
			return;
		}

		$type  = $config['type'] ?? null;
		$items = 'inner-list' === $type ? ( $config['items'] ?? null ) : null;

		if ( ! in_array( $type, self::CONTEXT_MEMBER_TYPES, true ) ) {
			self::debug_context_member_not_registered( $name, __( 'the type must be one of `integer`, `string`, `boolean` or `inner-list`', 'wp-graphql' ) );
			return;
		}

		if ( 'inner-list' === $type && ! in_array( $items, self::CONTEXT_MEMBER_ITEM_TYPES, true ) ) {
			self::debug_context_member_not_registered( $name, __( 'an `inner-list` member must declare `items` as one of `integer`, `string` or `boolean`', 'wp-graphql' ) );
			return;
		}

		if ( ! isset( $config['sanitize'] ) || ! is_callable( $config['sanitize'] ) ) {
			self::debug_context_member_not_registered( $name, __( 'a `sanitize` callback is required', 'wp-graphql' ) );
			return;
		}

		// The header key is derived from the name so the two transports cannot drift.
		$header_key = $config['header_key'] ?? strtolower( (string) preg_replace( '/[A-Z]/', '_$0', $name ) );

		// RFC 8941 dictionary keys: a lowercase letter or "*", then lowercase letters, digits, "_", "-", "." or "*".
		if ( ! is_string( $header_key ) || ! preg_match( '/^[a-z*][a-z0-9_\-.*]*$/', $header_key ) ) {
			self::debug_context_member_not_registered( $name, __( 'the `header_key` must be a valid lowercase RFC 8941 dictionary key', 'wp-graphql' ) );
			return;
		}

		if ( in_array( $header_key, self::RESERVED_CONTEXT_MEMBERS, true ) ) {
			self::debug_context_member_not_registered( $name, __( 'its header key is reserved by WPGraphQL core', 'wp-graphql' ) );
			return;
		}

		// First wins, so behavior never depends on plugin load order.
		if ( isset( self::$context_members[ $name ] ) || in_array( $header_key, wp_list_pluck( self::$context_members, 'header_key' ), true ) ) {
			self::debug_context_member_not_registered( $name, __( 'a member with the same name or header key is already registered', 'wp-graphql' ) );
			return;
		}

		self::$context_members[ $name ] = [
			'type'        => $type,
			'items'       => $items,
			'header_key'  => $header_key,
			'description' => isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : '',
			'sanitize'    => $config['sanitize'],
		];
	}

	/**
	 * Returns the preview context members registered by extensions.
	 *
	 * @return array<string,array{type:string,items:?string,header_key:string,description:string,sanitize:callable}>
	 */
	public static function get_context_members(): array {
		return self::$context_members;
	}

	/**
	 * Normalizes a client-supplied value for a registered preview context member.
	 *
	 * The value must match the declared type exactly (no coercion); the member's
	 * sanitizer then runs on the type-checked value, and its return is used as is.
	 * A value that fails the type check surfaces a debug notice and is treated as absent.
	 *
	 * @param string                                                                                  $name   The camelCase member name.
	 * @param array{type:string,items:?string,header_key:string,description:string,sanitize:callable} $member The registered member.
	 * @param mixed                                                                                   $value  The raw client-supplied value.
	 *
	 * @return mixed The sanitized value, or null when absent or invalid.
	 */
	public static function normalize_context_member( string $name, array $member, $value ) {
		if ( null === $value ) {
			return null;
		}

		$is_valid = 'inner-list' === $member['type']
			? is_array( $value ) && array_values( $value ) === $value && count( array_filter( $value, static fn ( $item ) => self::is_context_value_of_type( $item, (string) $member['items'] ) ) ) === count( $value )
			: self::is_context_value_of_type( $value, $member['type'] );

		if ( ! $is_valid ) {
			graphql_debug(
				sprintf(
					/* translators: 1: the preview context member name, 2: the header key, 3: the expected type */
					__( 'The preview context member `%1$s` (header key `%2$s`) was ignored because its value is not of the expected type `%3$s`.', 'wp-graphql' ),
					$name,
					$member['header_key'],
					'inner-list' === $member['type'] ? 'inner-list of ' . $member['items'] : $member['type']
				),
				[ 'type' => 'PREVIEW_CONTEXT_MEMBER_INVALID' ]
			);
			return null;
		}

		return call_user_func( $member['sanitize'], $value );
	}

	/**
	 * Whether a value is of the given preview context member (or item) type.
	 *
	 * @param mixed  $value The value.
	 * @param string $type  One of `integer`, `string` or `boolean`.
	 */
	private static function is_context_value_of_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'integer':
				return is_int( $value );
			case 'string':
				return is_string( $value );
			case 'boolean':
				return is_bool( $value );
			default:
				return false;
		}
	}

	/**
	 * Surfaces a debug notice for a preview context member registration that was refused.
	 *
	 * @param string $name   The member name.
	 * @param string $reason Why the member was not registered.
	 */
	private static function debug_context_member_not_registered( string $name, string $reason ): void {
		graphql_debug(
			sprintf(
				/* translators: 1: the preview context member name, 2: the reason it was not registered */
				__( 'The preview context member `%1$s` was not registered: %2$s.', 'wp-graphql' ),
				$name,
				$reason
			),
			[ 'type' => 'PREVIEW_CONTEXT_MEMBER_NOT_REGISTERED' ]
		);
	}

	/**
	 * Returns the schema deprecation reason for the `asPreview` argument.
	 *
	 * Single-sourced so the schema's guidance cannot drift from the runtime behavior:
	 * when a request carries preview context, the context is applied and `asPreview`
	 * is ignored (see resolve_preview_field()).
	 */
	public static function get_as_preview_deprecation_reason(): string {
		return __( 'Use the request-level preview context instead: send the `X-GraphQL-Preview` request header (or a `preview` object in the request `extensions`). When a request carries preview context, the context is applied and `asPreview` is ignored. This argument is planned for removal in a future major version.', 'wp-graphql' );
	}

	/**
	 * Adds the debug notice for an `asPreview` argument that was ignored because the
	 * request carries preview context. Shared by every resolver that accepts the
	 * deprecated argument so the guidance stays identical everywhere.
	 */
	public static function debug_as_preview_ignored(): void {
		graphql_debug(
			__( 'The deprecated `asPreview` argument was ignored because the request carries preview context (the `X-GraphQL-Preview` header or `extensions.preview`), which takes precedence. Remove `asPreview` from the query.', 'wp-graphql' ),
			[ 'type' => 'PREVIEW_ARG_IGNORED' ]
		);
	}

	/**
	 * Whether the current user is allowed to preview the given post.
	 *
	 * This is the single authorization rule for the preview overlay, mirroring how
	 * WordPress core gates previews: the viewer must be authenticated and able to
	 * edit the post being previewed. Every consumer of preview context routes
	 * through this helper so the rule cannot drift between call sites.
	 *
	 * @param int $post_id The database ID of the post being previewed.
	 */
	public static function viewer_can_preview( int $post_id ): bool {
		return is_user_logged_in() && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Overlays previewable fields from a post's revision when the request carries
	 * preview context targeting that post, while preserving the node's published
	 * identity (id/databaseId and any field not opted in stay published).
	 *
	 * Opt-in is per field via field config:
	 * - `previewResolve` (callable): supplies a request-derived value (e.g. the previewed
	 *    featured image). Receives ( $source, $args, $context, $info, $preview ).
	 * - `isPreviewable` (bool true): runs the field's normal resolver against the revision.
	 *
	 * Unmarked fields resolve from the published node. Invalid or unauthorized preview
	 * context is treated as if it were never provided (returns the $nil sentinel), so it
	 * cannot be used to read or probe for unpublished content.
	 *
	 * @param mixed                                    $nil            The unique "no override" sentinel from graphql_pre_resolve_field.
	 * @param mixed                                    $source         The source being resolved.
	 * @param array<string,mixed>                      $args           The field args.
	 * @param \WPGraphQL\AppContext                    $context        The AppContext for the request.
	 * @param \GraphQL\Type\Definition\ResolveInfo     $info           The ResolveInfo for the field.
	 * @param string                                   $type_name      The name of the type the field belongs to.
	 * @param string                                   $field_key      The name of the field.
	 * @param \GraphQL\Type\Definition\FieldDefinition $field          The field definition.
	 * @param ?callable                                $field_resolver The default field resolver.
	 *
	 * @return mixed
	 */
	public static function resolve_preview_field( $nil, $source, array $args, AppContext $context, ResolveInfo $info, string $type_name, string $field_key, FieldDefinition $field, $field_resolver ) {
		// No preview context, nothing to overlay.
		if ( ! is_array( $context->preview ) ) {
			return $nil;
		}

		// The overlay only applies to the post the preview context targets.
		if ( ! $source instanceof Post || (int) $source->databaseId !== (int) $context->preview['databaseId'] ) {
			return $nil;
		}

		$preview = $context->preview;

		// Only authenticated users who can edit (preview) the post may see previewed data.
		if ( ! self::viewer_can_preview( (int) $preview['databaseId'] ) ) {
			return $nil;
		}

		$config = $field->config ?? [];

		// A custom preview resolver supplies request-derived values (e.g. featuredImage).
		if ( isset( $config['previewResolve'] ) && is_callable( $config['previewResolve'] ) ) {
			return call_user_func( $config['previewResolve'], $source, $args, $context, $info, $preview );
		}

		// Opted-in fields resolve their normal resolver against the revision model.
		if ( ! empty( $config['isPreviewable'] ) ) {
			$revision = self::get_revision_model( (int) ( $preview['revisionDatabaseId'] ?? 0 ) );

			if ( $revision instanceof Post ) {
				return is_callable( $field_resolver )
					? $field_resolver( $revision, $args, $context, $info )
					: Executor::defaultFieldResolver( $revision, $args, $context, $info );
			}
		}

		return $nil;
	}

	/**
	 * Builds the Post model for a revision id.
	 *
	 * A fresh model is built per call rather than cached, because the Model captures the
	 * current user and its visibility at construction. A persisted (e.g. static) cache
	 * would let a model built for one user/request be reused for another, leaking the
	 * constructing user's visibility/owner context. `get_post()` is object-cached, so
	 * the cost is negligible.
	 *
	 * @param int $revision_id The revision's database ID.
	 */
	private static function get_revision_model( int $revision_id ): ?Post {
		if ( empty( $revision_id ) ) {
			return null;
		}

		$revision_post = get_post( $revision_id );

		return $revision_post instanceof \WP_Post ? new Post( $revision_post ) : null;
	}

	/**
	 * This filters the post meta for previews. Since WordPress core does not save meta for
	 * revisions this resolves calls to get_post_meta() using the meta of the revisions parent (the
	 * published version of the post).
	 *
	 * For plugins (such as ACF) that do store meta on revisions, the filter
	 * "graphql_resolve_revision_meta_from_parent" can be used to opt-out of this default behavior
	 * and instead return meta from the revision object instead of the parent.
	 *
	 * @param mixed       $default_value The default value of the meta
	 * @param int         $object_id     The ID of the object the meta is for
	 * @param string|null $meta_key      The meta key
	 * @param bool|null   $single        Whether the meta is a single value
	 *
	 * @return mixed
	 */
	public static function filter_post_meta_for_previews( $default_value, int $object_id, ?string $meta_key = null, ?bool $single = false ) {
		if ( ! is_graphql_request() ) {
			return $default_value;
		}

		$post = get_post( $object_id );

		if ( ! $post instanceof \WP_Post ) {
			return $default_value;
		}

		$parent   = 'revision' === $post->post_type ? get_post( $post->post_parent ) : null;
		$meta_key = ! empty( $meta_key ) ? $meta_key : '';

		// Meta keys that WordPress revisions (registered with `revisions_enabled`, or
		// added via the `wp_post_revision_meta_keys` filter) are stored on the revision
		// itself, so those default to resolving from the revision rather than the parent,
		// mirroring core's `_wp_preview_meta_filter`. The computed default is passed
		// through the filter below, which can override it in either direction.
		// (`wp_post_revision_meta_keys()` was added in WordPress 6.4.)
		$resolve_from_parent_default = true;
		if ( '' !== $meta_key && $parent instanceof \WP_Post && function_exists( 'wp_post_revision_meta_keys' ) ) {
			$revisioned_meta_keys = wp_post_revision_meta_keys( $parent->post_type );

			if ( is_array( $revisioned_meta_keys ) && in_array( $meta_key, $revisioned_meta_keys, true ) ) {
				$resolve_from_parent_default = false;
			}
		}

		/**
		 * Filters whether to resolve revision metadata from the parent node.
		 *
		 * @param bool    $should    Whether to resolve using the parent object. Defaults to true, except for meta keys WordPress stores on revisions (registered with `revisions_enabled`, or added via the `wp_post_revision_meta_keys` filter), which default to false so they resolve from the revision's own value. Return false to resolve a key from the revision, or true to force resolution from the parent.
		 * @param int     $object_id The ID of the object to resolve meta for
		 * @param ?string $meta_key  The key for the meta to resolve
		 * @param ?bool   $single    Whether a single value should be returned
		 *
		 * @hookGroup models
		 * @since 0.0.5
		 */
		$resolve_revision_meta_from_parent = apply_filters( 'graphql_resolve_revision_meta_from_parent', $resolve_from_parent_default, $object_id, $meta_key, $single );

		if ( false === $resolve_revision_meta_from_parent ) {
			return $default_value;
		}

		if ( $parent instanceof \WP_Post ) {
			$parent_meta = absint( $parent->ID ) ? get_post_meta( $parent->ID, $meta_key, (bool) $single ) : $default_value;

			// Wrap in array in case of single as get_post_metadata filter returns first value from array when single.
			// Ref: https://github.com/WordPress/wordpress-develop/blob/2fe26ceb7a1f3fb57ec8726fc5f425d00a12ace9/src/wp-includes/meta.php#L666
			return ( $single && is_array( $parent_meta ) ) ? [ $parent_meta ] : $parent_meta;
		}

		return $default_value;
	}
}
