<?php
namespace DFM\WPGraphQL\Interfaces;

use DFM\WPGraphQL\Fields\AuthorIdField;
use DFM\WPGraphQL\Fields\CommentCountField;
use DFM\WPGraphQL\Fields\CommentStatusField;
use DFM\WPGraphQL\Fields\ContentField;
use DFM\WPGraphQL\Fields\ContentFilteredField;
use DFM\WPGraphQL\Fields\DateField;
use DFM\WPGraphQL\Fields\DateGmtField;
use DFM\WPGraphQL\Fields\ExcerptField;
use DFM\WPGraphQL\Fields\GuidField;
use DFM\WPGraphQL\Fields\IdField;
use DFM\WPGraphQL\Fields\LinkField;
use DFM\WPGraphQL\Fields\MenuOrderField;
use DFM\WPGraphQL\Fields\MimeTypeField;
use DFM\WPGraphQL\Fields\ModifiedField;
use DFM\WPGraphQL\Fields\ModifiedGmtField;
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
use DFM\WPGraphQL\Types\PostType;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;

/**
 * Class PostTypeInterface
 *
 * author_id
 * comment_status
 * date
 * date_gmt
 * excerpt
 * id
 * link
 * modified
 * modified_gmt
 * parent_id
 * ping_status
 * slug
 * status
 * title
 * type
 *
 * @todo:
 * author (object/type)
 * featured_media (object/type)
 * format
 * sticky
 * password
 * parent (object/type)
 *
 * @package DFM\WPGraphQL\Types\Interfaces
 * @since 0.0.1
 */
class PostObjectInterface extends AbstractInterfaceType  {

	/**
	 * build
	 *
	 * @param \Youshido\GraphQL\Config\Object\InterfaceTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ){

		/**
		 * Unique identifier for the object.
		 * @since 0.0.1
		 */
		$config->addFields(
			[
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
			]
		);

	}

	/**
	 * resolveType
	 *
	 * Determine the ObjectType to resolve
	 *
	 * @param $object
	 * @return PostType
	 * @since 0.0.1
	 */
	public function resolveType( $object ) {

		/**
		 * @todo: return different types?
		 */
		return new PostType();

	}

}