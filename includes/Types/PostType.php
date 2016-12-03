<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\Interfaces\PostObjectInterface;
use DFM\WPGraphQL\Types\AttachmentType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostType
 *
 * Sets up the basic structure for the PostType
 *
 * includes all fields from the PostObjectInterface as well as:
 *
 * attachments
 * terms
 *
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class PostType extends AbstractObjectType  {

	/**
	 * getDescription
	 *
	 * This returns the description of the PostType
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The base WordPress Post Type', 'wp-graphqhl' );
	}

	/**
	 * build
	 *
	 * This configures the fields for the PostType
	 *
	 * @param |Youshido|GraphQL|Config|Object|ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * Applies the PostObjectInterface which defines the core PostObject fields
		 *
		 * @since 0.0.1
		 */
		$config->applyInterface( new PostObjectInterface() );

		/**
		 * Adds additional Fields to the Post type
		 *
		 * @since 0.0.2
		 */
		$config->addFields([

		]);

		/**
		 * attached_images
		 *
		 * This queries for attached images and uses the AttachmentType schema for each item returned
		 *
		 * @return array
		 * @since 0.0.1
		 */
		$config->addField(
			'attached_images',
			[
				'type' => new ListType( new AttachmentType() ),
				'description' => __( 'Images that are attached to this post object (via the image post_parent field)', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					// Query for attached media of the "image" type
					$attached_media = get_attached_media( 'image', $value->ID );

					// Return the array of media objects
					return $attached_media;

				}
			]
		);

		/**
		 * terms
		 *
		 * This queries for all terms for the object based on the taxonomy passed to the field
		 * and returns full term objects using the TermType schema
		 *
		 * @return array
		 * @since 0.0.1
		 */
		$config->addField(
			'terms',
			[
				'type' => new ListType( new TermType() ),
				'description' => __( 'Terms associated with the post. Must define a taxonomy, otherwise will defualy to "category"', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					// Get the $taxonomy from the field $args, or default to $category
					$taxonomy = ( ! empty( $args['taxonomy'] ) ) ? $args['taxonomy'] : 'category';

					// Get the object terms for the current post in the defined $taxonomy
					$terms = wp_get_object_terms( $value->ID, $taxonomy );

					// Return the $terms or the error message
					// @todo: look into returning the error message if the
					return ( ! is_wp_error( $terms ) ) ? $terms : array();

				},
				'args' => [
					'taxonomy' => new StringType(),
					'order' => new StringType(), // @todo: convert to enum type (ASC, DESC)
					'orderby' => new StringType(), // @todo: convert to enum type (name, count, slug, term_group, term_order, term_id, none)
					// @todo: look at implementing the fields argument?
				]
			]
		);

	}

}