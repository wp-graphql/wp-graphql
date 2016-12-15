<?php
namespace DFM\WPGraphQL\Fields\ShortcodeFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class ShortcodeFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class ShortcodeFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'args', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Shortcode args', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$fields = [
			'name' => [
				'type' => new StringType(),
				'description' => __( 'The name of the shortcode $arg', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['name'] ) ) ? esc_html( $value['name'] ) : '';
				}
			],
			'type' => [
				'type' => new StringType(),
				'description' => __( 'The type of data the $arg expects', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['type'] ) ) ? esc_html( $value['type'] ) : '';
				}
			],
			'options' => [
				'type' => new ListType( new StringType() ),
				'description' => __( 'The options allowed for the specific shortcode $arg', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['options'] ) ) ? $value['options'] : [];
				}
			],
			'description' => [
				'type' => new StringType(),
				'description' => __( 'The description of the shortcode $arg', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value['name'] ) ) ? esc_html( $value['name'] ) : '';
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