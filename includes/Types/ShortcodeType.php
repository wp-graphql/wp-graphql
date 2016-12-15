<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Fields\ShortcodeFields\ShortcodeFields;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class ShortcodeType extends AbstractObjectType {

	/**
	 * getDescription
	 *
	 * Returns the description for the ShortcodesType
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'The base PostsType with info about the query and a list of queried items', 'wp-graphqhl' );
	}

	/**
	 * build
	 *
	 * Defines the Object Type
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$fields = [
			'name' => [
				'type' => new StringType(),
				'description' => __( 'The name of the shortcode', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( $value['name'] ) ? $value['name'] : '';
				}
			],
			'fields' => [
				'type' => new ListType( new ShortcodeFields() ),
				'description' => __( 'The name of the shortcode', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return $value['args'];
				}
			]
		];

		$fields = apply_filters( 'DFM\WPGraphQL\Types\Shortcodes', $fields );

		$config->addFields( $fields );

	}

}