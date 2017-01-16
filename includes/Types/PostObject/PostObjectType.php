<?php
namespace WPGraphQL\Types\PostObject;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostObjectType
 *
 * This defines the base PostObjectType which is by default re-used by all
 * post_types that are set to "show_in_graphql". This sets up the core
 * fields that WordPress provides across post types.
 *
 * @package WPGraphQL\Entities\PostObject
 */
class PostObjectType extends AbstractObjectType {

	/**
	 * getName
	 *
	 * This sets the name of the ObjectType based on the "query_name" that was passed down
	 * through the instantiation of the class
	 *
	 * @return callable|mixed|null|string
	 * @since 0.0.1
	 */
	public function getName() {
		$query_name = $this->getConfig()->get( 'query_name' );

		return ! empty( $query_name ) ? $query_name : 'Post';
	}

	/**
	 * getDescription
	 *
	 * This sets the description of the PostObjectType
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return sprint_f( __( 'The $s post type', 'wp-graphql' ), $this->getConfig()->get( 'post_type' ) );
	}

	/**
	 * build
	 *
	 * This builds out the fields for the PostObjectType
	 *
	 * @since 0.0.1
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 *
	 * @return void
	 */
	public function build( $config ) {

		$fields = [
			'comment_count'    => [
				'name'        => 'comment_count',
				'type'        => new IntType(),
				'description' => __( 'The number of comments on the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->comment_count ) ? absint( $value->comment_count ) : null;
				},
			],
			'comment_status'   => [
				'name'        => 'comment_status',
				'type'        => new StringType(),
				'description' => __( 'Whether or not comments are open on the object', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->comment_status ) ? $value->comment_status : null;
				},
			],
			'content_filtered' => [
				'name'        => 'content_filtered',
				'type'        => new StringType(),
				'description' => __( 'A utility DB field for post content (post_content_filtered)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_content_filtered ) ? $value->post_content_filtered : null;
				},
			],
			'date'             => [
				'name'        => 'date',
				'type'        => new StringType(),
				'description' => __( 'The date the object was published, in the site\'s timezone. (post_date)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_date ) ? $value->post_date : null;
				},
			],
			'date_gmt'         => [
				'name'        => 'date_gmt',
				'type'        => new StringType(),
				'description' => __( 'The date the object was published, in GMT (post_date_gmt)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_date_gmt ) ? $value->post_date_gmt : null;
				},
			],
			'desired_slug'     => [
				'name'        => 'desired_slug',
				'type'        => new StringType(),
				'description' => __( 'Desired slug, stored if it is already taken by another object', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$desired_slug = get_post_meta( $value->ID, '_wp_desired_post_slug', true );

					return ! empty( $desired_slug ) ? $desired_slug : null;
				},
			],
			'edit_last'        => [
				'name'        => 'edit_last',
				'type'        => new IntType(),
				'description' => __( 'The ID of the user that most recently edited the object', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$edit_last = get_post_meta( $value->ID, '_edit_last', true );

					return ! empty( $edit_last ) ? absint( $edit_last ) : null;
				},
			],
			'edit_lock'        => [
				'name'        => 'edit_lock',
				'type'        => new StringType(),
				'description' => __( 'String indicating the timestamp and ID of the user that most 
				recently edited an object. Can be used to determine if it can safely be 
				edited by another user.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$edit_lock = get_post_meta( $value->ID, '_edit_lock', true );

					return ! empty( $edit_lock ) ? absint( $edit_lock ) : null;
				},
			],
			'enclosure'        => [
				'name'        => 'enclosure',
				'type'        => new StringType(),
				'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$enclosure = get_post_meta( $value->ID, 'enclosure', true );

					return ! empty( $enclosure ) ? $enclosure : null;
				},
			],
			'guid'             => [
				'name'        => 'guid',
				'type'        => new StringType(),
				'description' => __( 'The globally unique identifier for the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->guid ) ? $value->guid : null;
				},
			],
			'id'               => [
				'name'        => 'id',
				'type'        => new NonNullType( new IntType() ),
				'description' => __( 'Unique identifier for the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return $value->ID;
				},
			],
			'link'             => [
				'name'        => 'link',
				'type'        => new StringType(),
				'description' => __( 'The web-friendly url of the article (get_permalink)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$permalink = get_permalink( $value->ID );

					return ! empty( $permalink ) ? $permalink : null;
				},
			],
			'modified'         => [
				'name'        => 'modified',
				'type'        => new StringType(),
				'description' => __( 'The date the object was last modified, in the site\'s timezone. (post_modified)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_modified ) ? $value->post_modified : null;
				},
			],
			'modified_gmt'     => [
				'name'        => 'modified_gmt',
				'type'        => new StringType(),
				'description' => __( 'The date the object was last modified, as GMT. (post_modified_gmt)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_modified_gmt ) ? $value->post_modified_gmt : null;
				},
			],
			'old_slug'         => [
				'name'        => 'old_slug',
				'type'        => new StringType(),
				'description' => __( 'The old slug of the object. Can be used to find object where the slug changed or can be used to redirect old slugs to new slugs', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$old_slug = get_post_meta( $value->ID, '_wp_old_slug', true );

					return ! empty( $old_slug ) ? $old_slug : null;
				},
			],
			'parent'           => [
				'name'        => 'parent',
				'type'        => new PostParentUnion(),
				'description' => __( 'The parent object', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$post_parent = ! empty( $value->post_parent ) ? $value->post_parent : null;

					return ! empty( $post_parent ) ? get_post( $post_parent ) : null;
				},
			],
			'parent_id'        => [
				'name'        => 'parent_id',
				'type'        => new IntType(),
				'description' => __( 'The id for the author of the object. (post_parent)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_parent ) ? absint( $value->post_parent ) : null;
				},
			],
			'pinged'           => [
				'name'        => 'pinged',
				'type'        => new BooleanType(),
				'description' => __( 'Whether or not the object has been pinged', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->pinged ) ? true : false;
				},
			],
			'ping_status'      => [
				'name'        => 'ping_status',
				'type'        => new BooleanType(), // @todo: convert to Enum? Returns "open" or "closed"
				'description' => __( 'Whether or not the object can be pinged.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->ping_status ) ? $value->ping_status : null;
				},
			],

			/**
			 * @todo: discuss naming of post_content, post_excerpt and post_type fields.
			 * @see: https://github.com/wp-graphql/wp-graphql/issues/2
			 */
			'post_content'     => [
				'name'        => 'post_content',
				'type'        => new StringType(),
				'description' => __( 'The content for the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_content ) ? apply_filters( 'the_content', $value->post_content ) : null;
				},
			],
			/**
			 * @todo: discuss naming of post_content, post_excerpt and post_type fields.
			 * @see: https://github.com/wp-graphql/wp-graphql/issues/2
			 */
			'post_excerpt'     => [
				'name'        => 'post_excerpt',
				'type'        => new StringType(),
				'description' => __( 'The excerpt for the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_excerpt ) ? apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $value->post_excerpt, $value ) ) : null;
				},
			],
			'password'         => [
				'name'        => 'password',
				'type'        => new StringType(),
				'description' => __( 'The password of the object. (post_password)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_password ) ? $value->post_password : null;
				},
			],
			/**
			 * @todo: discuss naming of post_content, post_excerpt and post_type fields.
			 * @see: https://github.com/wp-graphql/wp-graphql/issues/2
			 */
			'post_type'        => [
				'name'        => 'post_type',
				'type'        => new StringType(),
				'description' => __( 'Post type for the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_type ) ? $value->post_type : null;
				},
			],
			'slug'             => [
				'name'        => 'slug',
				'type'        => new StringType(),
				'description' => __( 'The object\'s slug. (post_name)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_name ) ? $value->post_name : null;
				},
			],
			'status'           => [
				'name'        => 'status',
				'type'        => new StringType(),
				'description' => __( 'A named status for the object. (post_status)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_status ) ? $value->post_status : null;
				},
			],
			'title'            => [
				'name'        => 'title',
				'type'        => new StringType(),
				'description' => __( 'The title for the object. (post_title)', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->post_title ) ? $value->post_title : null;
				},
			],
			'to_ping'          => [
				'name'        => 'to_ping',
				'type'        => new BooleanType(),
				'description' => __( 'The "to_ping" flag of the object.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->to_ping ) ? true : false;
				},
			],
			'trashed_status'   => [
				'name'        => 'trashed_status',
				'type'        => new StringType(),
				'description' => __( 'The status of the post when it was marked for trash.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$trashed_status = get_post_meta( $value->ID, '_wp_trash_meta_status', true );

					return ! empty( $trashed_status ) ? $trashed_status : null;
				},
			],
			'trashed_time'     => [
				'name'        => 'trashed_time',
				'type'        => new StringType(),
				'description' => __( 'The UNIX timestamp of when the post was marked for trash.', 'wp-graphql' ),
				'resolve'     => function( $value, array $args, ResolveInfo $info ) {
					$trashed_time = get_post_meta( $value->ID, '_wp_trash_meta_time', true );

					return ! empty( $trashed_time ) ? $trashed_time : null;
				},
			],

		];

		/**
		 * Pass fields through a filter to allow modifications from outside the core plugin
		 *
		 * Allows fields to be filtered per post_type and passes the $config data to the filter
		 *
		 * @since 0.0.2
		 * @params $fields
		 * @params $config
		 */
		$fields = apply_filters( 'graphql_post_object_type_fields_' . $config->get( 'post_type' ), $fields, $config );

		/**
		 * This sorts the fields to be returned in alphabetical order.
		 * For my own sanity I like this, but I'd be open to discussing
		 * alternatives. We could move this out into a filter in a custom plugin
		 * instead of leaving here if alphabetical order doesn't seem to be
		 * everyone's preference?
		 *
		 * @since 0.0.2
		 *
		 * @note: the <=> operator is only supported in PHP 7,
		 * so this will need to be re-thought if we want to support older versions
		 * of PHP.
		 * @see: http://php.net/manual/en/migration70.new-features.php#migration70.new-features.spaceship-op
		 */
		usort( $fields, function( $a, $b ) {
			return $a['name'] <=> $b['name'];
		} );

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}
