<?php
namespace DFM\WPGraphQL\Fields\BitlyFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class BitlyFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class BitlyFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'bitly', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to Bitly', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$fields = [
			'url' => [
				'type' => new StringType(),
				'description' => __( 'The Bitly shortlink for the object', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return esc_html( get_post_meta( $value->ID, 'bitly_url', true ) );
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