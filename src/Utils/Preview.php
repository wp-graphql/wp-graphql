<?php

namespace WPGraphQL\Utils;

class Preview {

	/**
	 * This filters the post meta for previews. Since WordPress core does not save meta for revisions
	 * this resolves calls to get_post_meta() using the meta of the revisions parent (the published version of the post).
	 *
	 * For plugins (such as ACF) that do store meta on revisions, the filter "graphql_resolve_revision_meta_from_parent"
	 * can be used to opt-out of this default behavior and instead return meta from the revision
	 * object instead of the parent.
	 *
	 * @param mixed $default_value The default value of the meta
	 * @param int $object_id The ID of the object the meta is for
	 * @param string $meta_key The meta key
	 * @param bool $single Whether the meta is a single value
	 *
	 * @return mixed
	 */
	public static function filter_post_meta_for_previews( $default_value, int $object_id, string $meta_key, bool $single ) {

		if ( ! is_graphql_request() ) {
			return $default_value;
		}

		/**
		 * Filters whether to resolve revision metadata from the parent node
		 * by default.
		 *
		 * @param bool   $should    Whether to resolve using the parent object. Default true.
		 * @param int    $object_id The ID of the object to resolve meta for
		 * @param string $meta_key  The key for the meta to resolve
		 * @param bool   $single    Whether a single value should be returned
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
			$parent = get_post( $post->post_parent );
			return get_post_meta( $parent->ID, $meta_key, $single );
		}

		return $default_value;

	}

}
