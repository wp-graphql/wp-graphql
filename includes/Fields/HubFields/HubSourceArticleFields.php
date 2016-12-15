<?php
namespace DFM\WPGraphQL\Fields\HubFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class HubSourceArticleFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class HubSourceArticleFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'source_article', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Fields related to Hub such as source ids, hub ids, canonicals, etc. this only exists on an imported or subscribed post', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		/**
		 * source_id
		 * source_url
		 * version
		 * source_name
		 *
		 * @since 0.0.2
		 */
		$fields = [

			'id' => [
				'type' => new IntType(),
				'description' => __( 'The ID of the synced object in the hub' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['source_id'] ) ) ? absint( $value['dfm_hub_post_id'] ) : 0;
				}
			],
			'url' => [
				'type' => new StringType(),
				'description' => __( 'The url of the synced object in the hub', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['source_url'] ) ) ? esc_html( $value['source_url'] ) : '';
				}
			],
			'version' => [
				'type' => new IntType(),
				'description' => __( 'The syndication version', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return absint( $value['version'] );
				}
			],
			'source_name' => [
				'type' => new StringType(),
				'description' => __( 'The name of the source site for the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['source_name'] ) ) ? esc_html( $value['source_name'] ) : '';
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