<?php
namespace WPGraphQL\Types\TermObject;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class TermObjectsType
 *
 * Defines the TermObjectsType
 * @package WPGraphQL\Types\TermObject
 * @since 0.0.2
 */
class TermObjectsType extends AbstractObjectType {

	/**
	 * getName
	 *
	 * Returns the name of the type
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {

		/**
		 * Get the query_name
		 */
		$taxonomy_name = $this->getConfig()->get( 'query_name' );

		/**
		 * Return the name with "Results" appended
		 *
		 * @since 0.0.2
		 */
		return $taxonomy_name . 'Results';
	}

	/**
	 * getDescription
	 *
	 * Returns the description for the TermsType
	 *
	 * @return mixed
	 * @since 0.0.01
	 */
	public function getDescription() {
		return __( 'The base TermsType with info about the query and a list of queried items', 'wp-graphql' );
	}

	/**
	 * build
	 *
	 * Defines the object type
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @return void
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$termObjectConfig = [
			'taxonomy' => $this->getConfig()->get( 'taxonomy' ),
			'taxonomy_name' => $this->getConfig()->get( 'taxonomy_name' ),
		];

		$fields = [
			'items' => [
				'type' => new ListType( new TermObjectType( $termObjectConfig ) ),
				'description' => __( 'List of terms matching the criteria' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->terms ) ) ? $value->terms : [];
				}
			],
			'page' => [
				'type' => new IntType(),
				'description' => __( 'The current page of the paginated request', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->page ) ? absint( $value->page ) : 0;
				}
			],
			'per_page' => [
				'type' => new IntType(),
				'description' => __( 'The number of items displayed in the current paginated request', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->per_page ) ? absint( $value->per_page ) : 0;
				}
			],
			'taxonomy' => [
				'type' => new StringType(),
				'description' => __( 'The taxonomy type', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->taxonomy ) ? esc_html( $value->taxonomy ) : 'category';
				}
			],
			'total' => [
				'type' => new IntType(),
				'description' => __( 'The total number of terms that match the current query', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->total ) ? absint( $value->total ) : 0;
				}
			],
			'total_pages' => [
				'type' => new IntType(),
				'description' => __( 'The total number of pages', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->total_pages ) ? absint( $value->total_pages ) : 0;
				}
			],
		];

		/**
		 * Filter the fields that are part of the TermObjectsType
		 * @since 0.0.2
		 */
		$fields = apply_filters( 'graphql_term_objects_type_fields_' . $this->getConfig()->get( 'taxonomy' ), $fields );

		/**
		 * If there are fields, add them to the config
		 */
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			$config->addFields( $fields );
		}
		
	}
}