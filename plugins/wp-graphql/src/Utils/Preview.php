<?php

namespace WPGraphQL\Utils;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;

class Preview {

	/**
	 * Cache of revision Post models keyed by revision database ID, to avoid rebuilding
	 * the model for every overlaid field of a previewed node.
	 *
	 * @var array<int,\WPGraphQL\Model\Post|null>
	 */
	private static $revision_models = [];

	/**
	 * Overlays previewable fields from a post's revision when the request carries a
	 * `preview` envelope targeting that post, while preserving the node's published
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
		if ( ! $source instanceof Post || (int) $source->databaseId !== (int) $context->preview['id'] ) {
			return $nil;
		}

		$preview = $context->preview;

		// Only authenticated users who can edit (preview) the post may see previewed data.
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', (int) $preview['id'] ) ) {
			return $nil;
		}

		$config = $field->config ?? [];

		// A custom preview resolver supplies request-derived values (e.g. featuredImage).
		if ( isset( $config['previewResolve'] ) && is_callable( $config['previewResolve'] ) ) {
			return call_user_func( $config['previewResolve'], $source, $args, $context, $info, $preview );
		}

		// Opted-in fields resolve their normal resolver against the revision model.
		if ( ! empty( $config['isPreviewable'] ) ) {
			$revision = self::get_revision_model( (int) ( $preview['revisionId'] ?? 0 ) );

			if ( $revision instanceof Post ) {
				return is_callable( $field_resolver )
					? $field_resolver( $revision, $args, $context, $info )
					: Executor::defaultFieldResolver( $revision, $args, $context, $info );
			}
		}

		return $nil;
	}

	/**
	 * Builds (and caches per request) the Post model for a revision id.
	 *
	 * @param int $revision_id The revision's database ID.
	 */
	private static function get_revision_model( int $revision_id ): ?Post {
		if ( empty( $revision_id ) ) {
			return null;
		}

		if ( ! array_key_exists( $revision_id, self::$revision_models ) ) {
			$revision_post                         = get_post( $revision_id );
			self::$revision_models[ $revision_id ] = $revision_post instanceof \WP_Post ? new Post( $revision_post ) : null;
		}

		return self::$revision_models[ $revision_id ];
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

		/**
		 * Filters whether to resolve revision metadata from the parent node
		 * by default.
		 *
		 * @param bool    $should    Whether to resolve using the parent object. Default true.
		 * @param int     $object_id The ID of the object to resolve meta for
		 * @param ?string $meta_key  The key for the meta to resolve
		 * @param ?bool   $single    Whether a single value should be returned
		 */
		$resolve_revision_meta_from_parent = apply_filters( 'graphql_resolve_revision_meta_from_parent', true, $object_id, $meta_key, $single );

		if ( false === $resolve_revision_meta_from_parent ) {
			return $default_value;
		}

		$post = get_post( $object_id );

		if ( ! $post instanceof \WP_Post ) {
			return $default_value;
		}

		if ( 'revision' === $post->post_type ) {
			$parent   = get_post( $post->post_parent );
			$meta_key = ! empty( $meta_key ) ? $meta_key : '';

			$parent_meta = isset( $parent->ID ) && absint( $parent->ID ) ? get_post_meta( $parent->ID, $meta_key, (bool) $single ) : $default_value;

			// Wrap in array in case of single as get_post_metadata filter returns first value from array when single.
			// Ref: https://github.com/WordPress/wordpress-develop/blob/2fe26ceb7a1f3fb57ec8726fc5f425d00a12ace9/src/wp-includes/meta.php#L666
			return ( $single && is_array( $parent_meta ) ) ? [ $parent_meta ] : $parent_meta;
		}

		return $default_value;
	}
}
