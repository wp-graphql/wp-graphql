<?php
namespace DFM\WPGraphQL\Mutations;
use DFM\WPGraphQL\Types\PostType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class RootMutationType
 *
 * This sets up the RootMutationType
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class RootMutationType extends AbstractObjectType {

	/**
	 * Add the RootMutationType fields
	 *
	 * @param ObjectTypeConfig $config
	 * @return mixed
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * Base mutation fields
		 * @since 0.0.1
		 */
		$fields = [
			'update_post' => [
				'name' => 'updatePost',
				'type' => new PostType(),
				'args' => [
					'id' => [
						'type' => new NonNullType( new IntType() ),
						'description' => __( 'The unique Identifier of the object', 'wpgraphql' ),
						'resolve' => function( $value, array $args, ResolveInfo $info ) {
							return $value;
						}
					],
					'title' => new StringType(),
					'author_id' => new IntType(),
					'date' => new StringType(),
					'date_gmt' => new StringType()
				],
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					$args['ID'] = $args['id'];
					$args['post_title'] = $args['title'];

					unset( $args['title'] );
					unset( $args['id'] );

					$post_id = wp_update_post( $args );

					return get_post( $post_id );
				},
			],
		];

		/**
		 * Pass the fields through a filter
		 * @since 0.0.1
		 */
		$fields =  apply_filters( 'DFM\WPGraphQL\Schema\RootMutationType\Fields', $fields );

		/**
		 * addFields
		 * @since 0.0.1
		 */
		$config->addFields( $fields );

	}

}