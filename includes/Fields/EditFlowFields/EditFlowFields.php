<?php
namespace DFM\WPGraphQL\Fields\EditFlowFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class EditFlowFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class EditFlowFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'edit_flow', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to Edit Flow', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		/**
		 *
		 * _ef_editorial_meta_checkbox_needs-photo
		 * _ef_editorial_meta_checkbox_photos-media-library
		 * _ef_editorial_meta_checkbox_video-requested
		 * _ef_editorial_meta_date_first-draft-date
		 * _ef_editorial_meta_paragraph_assignment
		 *
		 * @todo: Look into making these fields dynamic based on what's configured in the Editorial Metadata of EditFlow
		 *        as users can configure different fields on the fly
		 */
		$fields = [

			/**
			 * photo_requested
			 * @since 0.0.2
			 */
			'photo_requested' => [
				'type' => new BooleanType(),
				'description' => __( 'Whether or not a photo was requested for the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( get_post_meta( $value->ID, '_ef_editorial_meta_checkbox_needs', true ) ) ? true : false;
				}
			],

			/**
			 * photos_uploaded
			 * @since 0.0.2
			 */
			'photos_uploaded' => [
				'type' => new BooleanType(),
				'description' => __( 'Whether or not requested photos have been uploaded to the media library', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( get_post_meta( $value->ID, '_ef_editorial_meta_checkbox_photos-media-library', true ) ) ? true : false;
				}
			],

			/**
			 * video_requested
			 * @since 0.0.2
			 */
			'video_requested' => [
				'type' => new BooleanType(),
				'description' => __( 'Whether or not a video was requested for the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( get_post_meta( $value->ID, '_ef_editorial_meta_checkbox_video-requested', true ) ) ? true : false;
				}
			],

			/**
			 * date_of_first_draft
			 * @since 0.0.2
			 */
			'date_of_first_draft' => [
				'type' => new StringType(),
				'description' => __( 'Whether or not the object needs a photo', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return esc_html( get_post_meta( $value->ID, '_ef_editorial_meta_date_first-draft-date', true ) );
				}
			],

			/**
			 * assignment
			 * @since 0.0.2
			 */
			'assignment' => [
				'type' => new StringType(),
				'description' => __( 'Whether or not the object needs a photo', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return esc_html( get_post_meta( $value->ID, '_ef_editorial_meta_paragraph_assignment', true ) );
				}
			],
		];

		/**
		 * addFields
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

	}

}