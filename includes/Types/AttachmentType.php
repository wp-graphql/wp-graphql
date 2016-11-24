<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\Interfaces\PostObjectInterface;
use DFM\WPGraphQL\Types\MediaDetailsType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class AttachmentType
 *
 * This defines the Attachment Object Type applying the PostObjectInterface and defining additional
 * fields specific to the AttachmentType:
 *
 * caption
 * alt_text
 * description
 * media_type
 * mime_type
 * associated_post_id
 * source_url
 * file
 * width
 * height
 *
 *
 * @package DFM\WPGraphQL\Types
 * @since 0.0.1
 */
class AttachmentType extends AbstractObjectType  {

	/**
	 * getDescription
	 *
	 * This returns the description of the Attachment Type
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The base WordPress Attachment Type', 'wp-graphql' );
	}

	/**
	 * build
	 *
	 * This defines the fields for the AttachmentType. It uses the PostTypeInterface to include
	 * the standard PostObject fields
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ){

		/**
		 * Applies the PostObjectInterface which defines the core PostObject fields
		 *
		 * @since 0.0.1
		 */
		$config->applyInterface( new PostObjectInterface() );

		$config->addField(
			'caption',
			[
				'type' => new StringType(),
				'description' => __( 'The caption for the resource', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return esc_html( $value->post_excerpt );
				}
			]
		);

		$config->addField(
			'alt_text',
			[
				'type' => new StringType(),
				'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return esc_html( get_post_meta( $value->ID, '_wp_attachment_image_alt', true ) );
				}
			]
		);

		$config->addField(
			'description',
			[
				'type' => new StringType(),
				'description' => __( 'The description for the resource', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return esc_html( $value->post_excerpt );
				}
			]
		);

		$config->addField(
			'media_type',
			[
				'type' => new StringType(),
				'description' => __( 'Type of resource', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return wp_attachment_is_image( $value->ID ) ? 'image' : 'file';
				}
			]
		);

		$config->addField(
			'mime_type',
			[
				'type' => new StringType(),
				'description' => __( 'Mime type of resource', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return esc_html( $value->post_mime_type );
				}
			]
		);

		// @todo: add support for media details

		$config->addField(
			'associated_post_id',
			[
				'type' => new IntType(),
				'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_parent ) ? (int) $value->post_parent : null;
				}
			]
		);

		$config->addField(
			'source_url',
			[
				'type' => new IntType(),
				'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return wp_get_attachment_url( $value->ID );
				}
			]
		);

		$config->addField( 'media_details', new MediaDetailsType() );

	}

}