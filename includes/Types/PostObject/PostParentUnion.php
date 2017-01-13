<?php
namespace WPGraphQL\Types\PostObject;

use WPGraphQL\Setup\PostEntities;
use Youshido\GraphQL\Type\Union\AbstractUnionType;

class PostParentUnion extends AbstractUnionType {

	public function getDescription(){
		return __( 'Because post objects can have a parent of any other post_type, this field allows for any 
		post_type to be returned and valid, and fields of that object can be retrieved via a 
		fragment query.', 'wp-graphql' );
	}

	public function getName() {
		return 'ParentUnion';
	}

	/**
	 * getTypes
	 *
	 * Define an array of the possible types for this union field
	 *
	 * Since there's no API in WordPress that restricts what "post_type" can be the parent
	 * of another "post_type", this registers ALL $allowed_post_types as a type in the parent union,
	 * but certain plugins/themes, will want to filter this to match their specific needs.
	 *
	 * @return array
	 * @since 0.0.2
	 */
	public function getTypes() {

		$types = [];
		$post_entities = new PostEntities();
		$allowed_post_types = $post_entities->get_allowed_post_types();

		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {

			foreach ( $allowed_post_types as $allowed_post_type ) {

				$query_name = ! empty( get_post_type_object( $allowed_post_type )->graphql_name ) ? get_post_type_object( $allowed_post_type )->graphql_name : null;

				if ( ! empty( $query_name ) ) {

					$types[] = new PostObjectType( [
						'post_type'  => $allowed_post_type,
						'query_name' => $query_name,
					]);

				}

			}

		}

		/**
		 * Pass the parent union types through a filter to allow more granular control
		 * over the types that each post_type can consider to be a "parent"
		 *
		 * @since 0.0.2
		 */
		$types = apply_filters( 'graphql_post_type_parent_union_types', $types, $allowed_post_types );

		/**
		 * Return the Types
		 */
		return $types;

	}

	/**
	 * resolveType
	 *
	 * This resolves the query based on the "post_type" of the queried post and determines which
	 * PostObjectType to return.
	 *
	 * @since 0.0.2
	 * @param object $post
	 * @return PostObjectType|null
	 */
	public function resolveType( $post ) {

		$post_type = ! empty( $post->post_type ) ? $post->post_type : 'post';
		$post_type_object = get_post_type_object( $post->post_type );
		$query_name = ! empty( $post_type_object->graphql_name ) ? $post_type_object->graphql_name : 'Post';

		$type = new PostObjectType( [
			'post_type'  => $post_type,
			'query_name' => $query_name,
		] );

		return ! empty( $type ) ? $type : null;

	}

}