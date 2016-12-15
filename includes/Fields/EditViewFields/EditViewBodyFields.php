<?php
namespace DFM\WPGraphQL\Fields\EditViewFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class EditViewFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class EditViewBodyFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'body_fields', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to Edit View', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		/**
		 * edit_view_body
		 */
		$fields = [

			/**
			 * title
			 * @since 0.0.2
			 */
			'title' => [
				'type' => new StringType(),
				'description' => __( 'Title for Edit View', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( $value['edit_view_title'] ) ? esc_html( $value['edit_view_title'] ) : '';
				}
			],

			/**
			 * content
			 * @since 0.0.2
			 */
			'content' => [
				'type' => new StringType(),
				'description' => __( 'Content for Edit View', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return ( $value['edit_view_content'] ) ? esc_html( $value['edit_view_content'] ) : '';
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