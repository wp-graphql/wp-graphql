<?php

namespace WPGraphQL\Type\MediaItem;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

/**
 * Class MediaItemType
 *
 * This class isn't a full definition of a new Type, instead it's used to customize
 * the shape of the mediaItemType (via filter), which is instantiated as a PostObjectType.
 *
 * @see     : wp-graphql.php - add_filter( 'graphql_mediaItem_fields', [
 *          '\WPGraphQL\Type\MediaItem\MediaItemType',
 *          'fields' ], 10, 1 );
 *
 * @package WPGraphQL\Type\MediaItem
 */
class MediaItemType {

	/**
	 * This customizes the fields for the mediaItem type ( attachment post_type) as the shape of
	 * the mediaItem Schema is different than a standard post
	 *
	 * @see: wp-graphql.php - add_filter( 'graphql_mediaItem_fields' );add_filter(
	 *       'graphql_mediaItem_fields', [
	 *       '\WPGraphQL\Type\MediaItem\MediaItemType', 'fields' ], 10, 1 );
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public static function register_fields() {


		add_filter( 'graphql_mediaItem_fields', function ( $fields ) {

			if ( isset( $fields['excerpt'] ) ) {
				$fields['excerpt']['isDeprecated']      = true;
				$fields['excerpt']['deprecationReason'] = __( 'Use the caption field instead of excerpt', 'wp-graphql' );
			}

			if ( isset( $fields['content'] ) ) {
				$fields['content']['isDeprecated']      = true;
				$fields['content']['deprecationReason'] = __( 'Use the description field instead of content', 'wp-graphql' );
			}

			return $fields;
		}, 10, 1 );

		register_graphql_fields( 'MediaItem', [
			'caption'      => [
				'type'        => 'String',
				'description' => __( 'The caption for the resource', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) );

					return ! empty( $caption ) ? $caption : null;
				},
			],
			'altText'      => [
				'type'        => 'String',
				'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
				},
			],
			'description'  => [
				'type'        => 'String',
				'description' => __( 'Description of the image (stored as post_content)', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return apply_filters( 'the_content', $post->post_content );
				},
			],
			'mediaType'    => [
				'type'        => 'String',
				'description' => __( 'Type of resource', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
				},
			],
			'sourceUrl'    => [
				'type'        => 'String',
				'description' => __( 'Url of the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return wp_get_attachment_url( $post->ID );
				},
			],
			'mimeType'     => [
				'type'        => 'String',
				'description' => __( 'The mime type of the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return ! empty( $post->post_mime_type ) ? $post->post_mime_type : null;
				},
			],
			'mediaDetails' => [
				'type'        => 'MediaDetails',
				'description' => __( 'Details about the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					$media_details       = wp_get_attachment_metadata( $post->ID );
					$media_details['ID'] = $post->ID;

					return $media_details;
				},
			],

		] );

	}

}