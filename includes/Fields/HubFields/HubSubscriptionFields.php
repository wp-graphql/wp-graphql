<?php
namespace DFM\WPGraphQL\Fields\HubFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class HubSubscriptionFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class HubSubscriptionFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'subscriptions', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Fields related to Hub subscriptions', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		/**
		 * dfm_hub_post_id
		 * dfm_hub_domain
		 */
		$fields = [

			'id' => [
				'type' => new IntType(),
				'description' => __( 'The ID of the synced object in the hub' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['dfm_hub_post_id'] ) ) ? absint( $value['dfm_hub_post_id'] ) : 0;
				}
			],

			'domain' => [
				'type' => new StringType(),
				'description' => __( 'The domain of the subscriber', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['dfm_hub_domain'] ) ) ? esc_html( $value['dfm_hub_domain'] ) : '';
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