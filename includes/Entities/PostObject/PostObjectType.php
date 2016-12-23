<?php
namespace DFM\WPGraphQL\Entities\PostObject;

use DFM\WPGraphQL\Fields\AuthorIdField;
use DFM\WPGraphQL\Fields\CommentCountField;
use DFM\WPGraphQL\Fields\CommentStatusField;
use DFM\WPGraphQL\Fields\ContentField;
use DFM\WPGraphQL\Fields\ContentFilteredField;
use DFM\WPGraphQL\Fields\DateField;
use DFM\WPGraphQL\Fields\DateGmtField;
use DFM\WPGraphQL\Fields\EditLastField;
use DFM\WPGraphQL\Fields\EditLockField;
use DFM\WPGraphQL\Fields\ExcerptField;
use DFM\WPGraphQL\Fields\GuidField;
use DFM\WPGraphQL\Fields\IdField;
use DFM\WPGraphQL\Fields\LinkField;
use DFM\WPGraphQL\Fields\MenuOrderField;
use DFM\WPGraphQL\Fields\MimeTypeField;
use DFM\WPGraphQL\Fields\ModifiedField;
use DFM\WPGraphQL\Fields\ModifiedGmtField;
use DFM\WPGraphQL\Fields\OldSlugField;
use DFM\WPGraphQL\Fields\ParentIdField;
use DFM\WPGraphQL\Fields\PingedField;
use DFM\WPGraphQL\Fields\PingStatusField;
use DFM\WPGraphQL\Fields\PostPasswordField;
use DFM\WPGraphQL\Fields\SlugField;
use DFM\WPGraphQL\Fields\StatusField;
use DFM\WPGraphQL\Fields\ThumbnailIdField;
use DFM\WPGraphQL\Fields\TitleField;
use DFM\WPGraphQL\Fields\ToPingField;
use DFM\WPGraphQL\Fields\TypeField;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

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
		$post_type_name = $this->getConfig()->get( 'post_type_name' );
		$name = ! empty( $post_type_name ) ? $post_type_name : 'Post';
		return $name;

	}

	public function getDescription() {
		return __( 'The ' . $this->getConfig()->get( 'post_type' ) . ' post type', 'wp-graphql' );
	}

	public function build( $config ) {

		$fields = [
			/**
			 * AuthorIdField
			 * @since 0.0.1
			 */
			new AuthorIdField(),

			/**
			 * CommentCountField
			 * @since 0.0.2
			 */
			new CommentCountField(),

			/**
			 * AuthorIdField
			 * @since 0.0.1
			 */
			new CommentStatusField(),

			/**
			 * AuthorIdField
			 * @since 0.0.1
			 */
			new ContentField(),

			/**
			 * ContentFiltered
			 * @since 0.0.2
			 */
			new ContentFilteredField(),

			/**
			 * DateField
			 * @since 0.0.1
			 */
			new DateField(),

			/**
			 * DateGmtField
			 * @since 0.0.1
			 */
			new DateGmtField(),

			/**
			 * EditLastField
			 * @since 0.0.2
			 */
			new EditLastField(),

			/**
			 * EditLockField
			 * @since 0.0.2
			 */
			new EditLockField(),

			/**
			 * ExcerptField
			 * @since 0.0.1
			 */
			new ExcerptField(),

			/**
			 * GuidField
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
			 * @since 0.0.2
			 */
			new MenuOrderField(),

			/**
			 * MimeTypeField
			 * @since 0.0.2
			 */
			new MimeTypeField(),

			/**
			 * ModifiedField
			 * @since 0.0.1
			 */
			new ModifiedField(),

			/**
			 * ModifiedGmtField
			 * @since 0.0.1
			 */
			new ModifiedGmtField(),

			/**
			 * OldSlugField
			 * @since 0.0.2
			 */
			new OldSlugField(),

			/**
			 * ParentIdField
			 * @since 0.0.1
			 */
			new ParentIdField(),

			/**
			 * Pinged
			 * @since 0.0.2
			 */
			new PingedField(),

			/**
			 * PingStatusField
			 * @since 0.0.1
			 */
			new PingStatusField(),

			/**
			 * PostPassword
			 * @since 0.0.2
			 */
			new PostPasswordField(),

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
			 * ToPing
			 * @since 0.0.2
			 */
			new ToPingField(),

			/**
			 * TitleField
			 * @since 0.0.1
			 */
			new TitleField(),

			/**
			 * TypeField
			 * @since 0.0.1
			 */
			new TypeField(),

		];

		/**
		 * Pass fields through a filter to allow modifications from outside the core plugin
		 *
		 * Filtering this will filter all types that use or extend the PostObjectInterface
		 */
		$fields = apply_filters( 'wpgraphql_post_object_type_fields_' . $config->get( 'post_type' ) , $fields, $config );

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}