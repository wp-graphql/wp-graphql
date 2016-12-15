<?php
namespace DFM\WPGraphQL\Fields\AppleNewsFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class AppleNewsFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class AppleNewsFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'apple_news', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to Apple News', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$fields = [
			'is_preview' => [
				'type' => new BooleanType(),
				'description' => __( 'Whether or not the object is an Apple News preview', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( get_post_meta( $value->ID, 'apple_news_is_preview', true ) ) ? true : false;
				}
			],
			'pullquote' => [
				'type' => new StringType(),
				'description' => __( 'The pullquote used for Apple News', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return esc_html( get_post_meta( $value->ID, 'apple_news_pullquote', true ) );
				}
			],
			'pullquote_position' => [
				'type' => new StringType(),
				'description' => __( 'The position of the pullquote used for Apple News', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return esc_html( get_post_meta( $value->ID, 'apple_news_pullquote_position', true ) );
				}
			],
			'sections' => [
				'type' => new ListType( new StringType() ),
				'description' => __( 'The sections of the pullquote used for Apple News', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return get_post_meta( $value->ID, 'apple_news_sections', true );
				}
			]

		];

		/**
		 * addFields
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

	}

}