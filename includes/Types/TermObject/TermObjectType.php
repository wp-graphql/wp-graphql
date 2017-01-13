<?php
namespace WPGraphQL\Types\TermObject;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TermObjectType extends AbstractObjectType {

	/**
	 * getName
	 *
	 * This sets the name of the ObjectType based on the "query_name" that was passed
	 * down through the instantiation of the class
	 *
	 * @return callable|mixed|null
	 * @since 0.0.2
	 */
	public function getName() {
		$query_name = $this->getConfig()->get( 'query_name' );
		return ! empty( $query_name ) ? $query_name : 'Category';
	}

	/**
	 * getDescription
	 *
	 * This sets the description for the TermObjectType
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Terms of the ' . $this->getConfig()->get( 'taxonomy' ) . ' Taxonomy', 'wp-graphql' );
	}

	/**
	 * build
	 *
	 * This builds out the fields for the TermObjectType
	 *
	 * @since 0.0.2
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @return void
	 */
	public function build( $config ) {
		
		$fields = [
			'count' => [
				'name' => 'count',
				'type' => new IntType(),
				'description' => __( '', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->count ) ? absint( $value->count ) : null;
				}
			],
			'description' => [
				'name' => 'description',
				'type' => new StringType(),
				'description' => __( 'The description of the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->description ) ? $value->description : null;
				}
			],
			'id' => [
				'name' => 'id',
				'type' => new IntType(),
				'description' => __( 'Unique Identifier for the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->term_id ) ? absint( $value->term_id ) : null;
				},
			],
			'name' => [
				'name' => 'name',
				'type' => new StringType(),
				'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->name ) ? $value->name : null;
				}
			],
			'slug' => [
				'name' => 'slug',
				'type' => new StringType(),
				'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->slug ) ? $value->slug : null;
				}
			],
			'term_group_id' => [
				'name' => 'term_group_id',
				'type' => new IntType(),
				'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->term_group_id ) ? absint( $value->term_group_id ) : false;
				}
			],
			'term_taxonomy_id' => [
				'name' => 'term_taxonomy_id',
				'type' => new IntType(),
				'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->term_taxonomy_id ) ? absint( $value->term_taxonomy_id ) : null;
				}
			],
			'taxonomy_name' => [
				'name' => 'taxonomy_name',
				'type' => new StringType(),
				'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ! empty( $value->term_taxonomy ) ? $value->term_taxonomy : null;
				}
			],
		 ];

		$fields = apply_filters( 'graphql_term_object_type_fields_' . $config->get( 'taxonomy' ), $fields, $config );

		/**
		 * This sorts the fields to be returned in alphabetical order.
		 * For my own sanity I like this, but I'd be open to discussing
		 * alternatives. We could move this out into a filter in a custom plugin
		 * instead of leaving here if alphabetical order doesn't seem to be
		 * everyone's preference?
		 *
		 * @since 0.0.2
		 *
		 * @note: the <=> operator is only supported in PHP 7,
		 * so this will need to be re-thought if we want to support older versions
		 * of PHP.
		 * @see: http://php.net/manual/en/migration70.new-features.php#migration70.new-features.spaceship-op
		 */
		usort( $fields, function( $a, $b ) {
			return $a['name'] <=> $b['name'];
		});

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}