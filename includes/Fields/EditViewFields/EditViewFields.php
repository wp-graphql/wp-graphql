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
class EditViewFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'edit_view', 'wp-graphql' );
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
		 * edit_view_side
		 * edit_view_body
		 *
		 * @since 0.0.2
		 *
		 * @todo: I recommend Edit view / Print View and Web View are the same content object tree, with nodes that
		 * have context about web, print and editing, that way we can maintain a singular source of truth rather than
		 * maintaining copies after copies, which becomes very difficult for troubleshooting, etc
		 */
		$fields = [

			/**
			 * body_fields
			 * @since 0.0.2
			 */
			'body_fields' => [
				'type' => new EditViewBodyFields(),
				'description' => __( 'The body fields of Edit View', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return get_post_meta( $value->ID, 'edit_view_body', true );
				}
			],

			/**
			 * side_fields
			 * @note: This field is simply a hack for Fieldmanager and doesn't actually have
			 * any relevance in the database, so it's not included in the GraphQL Schema
			 */

		];

		/**
		 * addFields
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

	}

}