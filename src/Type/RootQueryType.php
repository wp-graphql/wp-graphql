<?php
namespace WPGraphQL\Type;

use WPGraphQL\Type\PostObject\PostObjectQuery;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\TermObject\Connection\TermObjectConnectionDefinition;
use WPGraphQL\Type\TermObject\TermObjectQuery;

/**
 * Class RootQueryType
 * The RootQueryType is the primary entry for Queries in the GraphQL Schema.
 * @package WPGraphQL\Type
 * @since 0.0.4
 */
class RootQueryType extends WPObjectType {

	protected static $type_name;
	protected static $fields;

	/**
	 * RootQueryType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {self::$type_name = 'RootQuery';

		parent::__construct( [
			'name' => self::$type_name,
			'fields' => self::fields(),
		]);

	}

	public static function fields() {

		if ( null === self::$fields ) {
			self::$fields = function() {
				/**
				 * Setup data
				 * @since 0.0.5
				 */
				$allowed_post_types = \WPGraphQL::$allowed_post_types;
				$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

				/**
				 * Creates the root fields for post objects (of any post_type)
				 * This registers root fields (single and plural) for any post_type that has been registered as an
				 * allowed post_type.
				 * @see \WPGraphQL::$allowed_post_types
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
					foreach ( $allowed_post_types as $post_type ) {
						/**
						 * Get the post_type object to pass down to the schema
						 * @since 0.0.5
						 */
						$post_type_object = get_post_type_object( $post_type );

						/**
						 * Root query for single posts (of the specified post_type)
						 * @since 0.0.5
						 */
						$fields[ $post_type_object->graphql_single_name ] = PostObjectQuery::root_query( $post_type_object );
						$fields[ $post_type_object->graphql_single_name . 'By' ] = PostObjectQuery::post_object_by( $post_type_object );

						/**
						 * Root query for collections of posts (of the specified post_type)
						 * @since 0.0.5
						 */
						// $fields[ $post_type_object->graphql_plural_name ] = PostObjectConnectionDefinition::connection( $post_type_object );
					}
				}

				/**
				 * Creates the root fields for terms of each taxonomy
				 * This registers root fields (single and plural) for terms of any taxonomy that has been registered as an
				 * allowed taxonomy.
				 * @see \WPGraphQL::$allowed_taxonomies
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {

						/**
						 * Get the taxonomy object
						 * @since 0.0.5
						 */
						$taxonomy_object = get_taxonomy( $taxonomy );

						/**
						 * Root query for single terms (of the specified taxonomy)
						 * @since 0.0.5
						 */
						// $fields[ $taxonomy_object->graphql_single_name ] = TermObjectQuery::root_query( $taxonomy_object );

						/**
						 * Root query for collections of terms (of the specified taxonomy)
						 * @since 0.0.5
						 */
						// $fields[ $taxonomy_object->graphql_plural_name ] = TermObjectConnectionDefinition::connection( $taxonomy_object );
					}
				}

				/**
				 * Pass the root queries through a filter.
				 * This allows fields to be added or removed.
				 * NOTE: Use this filter with care. Before removing existing fields seriously consider deprecating the field, as
				 * that will allow the field to still be used and not break systems that rely on it, but just not be present
				 * in Schema documentation, etc.
				 * If the behavior of a field needs to be changed, depending on the change, it might be better to consider adding
				 * a new field with the new behavior instead of overriding an existing field. This will allow existing fields
				 * to behave as expected, but will allow introduction of new fields with different behavior at any point.
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_root_queries', $fields );
				$fields = self::prepare_fields( $fields, self::$type_name );
				return $fields;
			};
		}

		return self::$fields;

	}
}
