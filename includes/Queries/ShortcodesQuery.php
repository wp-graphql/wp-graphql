<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Types\ShortcodeType;
use DFM\WPGraphQL\Utils\Shortcodes;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class ShortcodesQuery
 *
 * Define the ShortcodesQuery
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.1
 */
class ShortcodesQuery extends AbstractField  {

	/**
	 * getName
	 *
	 * This returns the name of the query
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getName() {
		return __( 'shortcodes', 'wp-graphql' );
	}

	/**
	 * getType
	 *
	 * This defines the type that returns for the ArticleQuery
	 *
	 * @return ListType
	 * @since 0.0.1
	 */
	public function getType() {
		return new ListType( new ShortcodeType() );
	}

	/**
	 * getDescription
	 *
	 * This returns the description of the ArticleQuery
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'Retrieve a list of shortcodes', 'dfm-graphql-endpoints' );
	}

	/**
	 * resolve
	 *
	 * This defines the
	 *
	 * @since 0.0.1
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return array
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {

		/**
		 * Return an array of registered shortcodes to be available to the Schema
		 */
		return [
			[
				'name' => 'caption',
				'args' => [
					[
						'name' => 'id',
						'type' => 'string',
						'description' => 'A unique HTML ID that you can change to use within your CSS',
					],
					[
						'name' => 'class',
						'type' => 'string',
						'description' => 'Custom class that you can use within your CSS',
					],
					[
						'name' => 'align',
						'type' => 'enum',
						'options' => [ 'alignnone', 'aligncenter', 'alignright', 'alignleft' ],
						'description' => 'Custom class that you can use within your CSS',
					],
					[
						'name' => 'width',
						'type' => 'string',
						'required' => true,
						'description' => 'How wide the caption should be in pixels. This is a required and must have a value greater than or equal to 1. If not provided, caption processing will not be done and caption content will be passed-through',
					],
				],
			],
		];

	}

}