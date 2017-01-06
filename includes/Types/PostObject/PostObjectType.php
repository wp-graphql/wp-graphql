<?php
namespace DFM\WPGraphQL\Types\PostObject;

use DFM\WPGraphQL\Fields\AuthorIdField;
use DFM\WPGraphQL\Fields\CommentCountField;
use DFM\WPGraphQL\Fields\CommentStatusField;
use DFM\WPGraphQL\Fields\ContentFilteredField;
use DFM\WPGraphQL\Fields\DateField;
use DFM\WPGraphQL\Fields\DateGmtField;
use DFM\WPGraphQL\Fields\DesiredSlugField;
use DFM\WPGraphQL\Fields\EditLastField;
use DFM\WPGraphQL\Fields\EditLockField;
use DFM\WPGraphQL\Fields\EnclosureField;
use DFM\WPGraphQL\Fields\GuidField;
use DFM\WPGraphQL\Fields\IdField;
use DFM\WPGraphQL\Fields\LinkField;
use DFM\WPGraphQL\Fields\MenuOrderField;
use DFM\WPGraphQL\Fields\ModifiedField;
use DFM\WPGraphQL\Fields\ModifiedGmtField;
use DFM\WPGraphQL\Fields\OldSlugField;
use DFM\WPGraphQL\Fields\ParentIdField;
use DFM\WPGraphQL\Fields\PingedField;
use DFM\WPGraphQL\Fields\PingStatusField;
use DFM\WPGraphQL\Fields\PostContentField;
use DFM\WPGraphQL\Fields\PostExcerptField;
use DFM\WPGraphQL\Fields\PostPasswordField;
use DFM\WPGraphQL\Fields\TitleField;
use DFM\WPGraphQL\Fields\SlugField;
use DFM\WPGraphQL\Fields\StatusField;
use DFM\WPGraphQL\Fields\ToPingField;
use DFM\WPGraphQL\Fields\TrashStatusField;
use DFM\WPGraphQL\Fields\TrashTimeField;
use DFM\WPGraphQL\Fields\PostTypeField;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class PostObjectType
 *
 * This defines the base PostObjectType which is by default re-used by all
 * post_types that are set to "show_in_graphql". This sets up the core
 * fields that WordPress provides across post types.
 *
 * @package DFM\WPGraphQL\Entities\PostObject
 */
class PostObjectType extends AbstractObjectType {

	/**
	 * getPostType
	 *
	 * Returns the post type
	 *
	 * @return callable|mixed|null|string
	 * @since 0.0.2
	 */
	public function getPostType() {

		/**
		 * Check if the post_type was passed down in the config
		 */
		$post_type = $this->getConfig()->get( 'post_type' );

		/**
		 * Check if the post_type is a populated string, otherwise fallback to the
		 * default "post" type
		 */
		$post_type = ( ! empty( $config_post_type ) && is_string( $post_type ) ) ? $post_type: 'post';

		/**
		 * Ensure the Query only contains letters and numbers
		 */
		$post_type = preg_replace( '/[^A-Za-z0-9]/i', '', $post_type );

		/**
		 * Return the post_type
		 */
		return $post_type;

	}

	public function getName() {

		/**
		 * Get the post_type
		 */
		$query_name = $this->getConfig()->get( 'query_name' );
		$name = ! empty( $query_name ) ? $query_name : 'Post';
		return $name;

	}

	public function getDescription() {
		return __( 'The ' . $this->getConfig()->get( 'post_type' ) . ' post type', 'wp-graphql' );
	}

	public function build( $config ) {

		$fields = [
			/**
			 * AuthorIdField
			 * post_author
			 * @since 0.0.1
			 */
			new AuthorIdField(),

			// @todo: add Author field that returns a full Author object

			/**
			 * CommentCountField
			 * comment_count
			 * @since 0.0.2
			 */
			new CommentCountField(),

			/**
			 * CommentStatusField
			 * comment_status
			 * @since 0.0.1
			 */
			new CommentStatusField(),

			/**
			 * ContentFiltered
			 * post_content_filtered
			 * @since 0.0.2
			 */
			new ContentFilteredField(),

			/**
			 * DateField
			 * post_date
			 * @since 0.0.1
			 */
			new DateField(),

			/**
			 * DateGmtField
			 * post_date_gmt
			 * @since 0.0.1
			 */
			new DateGmtField(),

			/**
			 * DesiredSlug
			 * @since 0.0.2
			 * @see: https://github.com/WordPress/WordPress/blob/f3a9d2bd9a9be240785855564225ffe933d49482/wp-includes/post.php#L6185
			 */
			new DesiredSlugField(),

			/**
			 * EditLastField
			 * _edit_last
			 * @since 0.0.2
			 */
			new EditLastField(),

			/**
			 * EditLockField
			 * _edit_lock
			 * @since 0.0.2
			 */
			new EditLockField(),

			/**
			 * EnclusureField
			 * enclosure
			 * @since 0.0.2
			 * @see: https://github.com/WordPress/WordPress/blob/dca7d8d0ea4ca90a715c1cbc46d5fb3cd1bcbdb2/wp-includes/class-wp-xmlrpc-server.php#L5143
			 */
			new EnclosureField(),

			/**
			 * GuidField
			 * guid
			 * @since 0.0.2
			 */
			new GuidField(),

			/**
			 * IdField
			 * @since 0.0.1
			 */
			new IdField(),

			/**
			 * LinkField
			 * @since 0.0.1
			 */
			new LinkField(),

			/**
			 * MenuOrder
			 * menu_order
			 * @since 0.0.2
			 */
			new MenuOrderField(),

			/**
			 * ModifiedField
			 * post_modified
			 * @since 0.0.1
			 */
			new ModifiedField(),

			/**
			 * ModifiedGmtField
			 * post_modified_gmt
			 * @since 0.0.1
			 */
			new ModifiedGmtField(),

			/**
			 * OldSlugField
			 * _wp_old_slug
			 * @since 0.0.2
			 * @see: https://github.com/WordPress/WordPress/blob/f3a9d2bd9a9be240785855564225ffe933d49482/wp-includes/post.php#L5402
			 */
			new OldSlugField(),

			/**
			 * ParentIdField
			 * post_parent
			 * @since 0.0.1
			 */
			new ParentIdField(),

			/**
			 * Pinged
			 * pinged
			 * @since 0.0.2
			 */
			new PingedField(),

			/**
			 * PingStatusField
			 * ping_status
			 * @since 0.0.1
			 */
			new PingStatusField(),

			/**
			 * PostContentField
			 * post_content
			 * @since 0.0.2
			 */
			new PostContentField(),

			/**
			 * PostExcerptField
			 * post_excerpt
			 * @since 0.0.1
			 */
			new PostExcerptField(),

			/**
			 * PostPassword
			 * post_password
			 * @since 0.0.2
			 */
			new PostPasswordField(),

			/**
			 * PostTypeField
			 * post_type
			 * @since 0.0.1
			 */
			new PostTypeField(),

			/**
			 * SlugField
			 * @since 0.0.1
			 */
			new SlugField(),

			/**
			 * StatusField
			 * @since 0.0.1
			 */
			new StatusField(),

			/**
			 * TrashStatusField
			 * _wp_trash_meta_status
			 * @since 0.0.2
			 * @see: https://github.com/WordPress/WordPress/blob/f3a9d2bd9a9be240785855564225ffe933d49482/wp-includes/post.php#L2607
			 */
			new TrashStatusField(),

			/**
			 * TrashTimeField
			 * _wp_trash_meta_time
			 * @since 0.0.2
			 * @see: https://github.com/WordPress/WordPress/blob/f3a9d2bd9a9be240785855564225ffe933d49482/wp-includes/post.php#L2608
			 */
			new TrashTimeField(),


			/**
			 * ToPing
			 * @since 0.0.2
			 */
			new ToPingField(),

			/**
			 * TitleField
			 * @since 0.0.1
			 */
			new TitleField(),

		];

		/**
		 * Pass fields through a filter to allow modifications from outside the core plugin
		 *
		 * Filtering this will filter all types that use or extend the PostObjectInterface
		 */
		$fields = apply_filters( 'wpgraphql_post_object_type_fields_' . $config->get( 'post_type' ) , $fields, $config );

		/**
		 * Sort the fields in alphabetical order
		 * apply a filter to allow the alphabetical sorting to be disabled
		 */
		if ( apply_filters( 'wpgraphql_post_object_type_fields_alphabetical_order', 'return__true', $fields, $config ) ) {

			// Sort the fields
			usort( $fields, function( $a, $b ) {

				// Determine the name to compare
				$a_name = ( is_array( $a ) && ! empty( $a['name'] ) ) ? $a['name'] : $a->getName();
				$b_name = ( is_array( $b ) && ! empty( $b['name'] ) ) ? $b['name'] : $b->getName();

				// Return based on alphabetical order
				return strcmp( $a_name, $b_name );

			} );

		}

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}