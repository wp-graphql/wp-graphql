<?php
namespace DFM\WPGraphQL\Queries;
use DFM\WPGraphQL\Queries\AttachmentQuery;
use DFM\WPGraphQL\Queries\PostQuery;
use DFM\WPGraphQL\Queries\TermQuery;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class RootQueryType
 *
 * This sets up the RootQueryType
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class RootQueryType extends AbstractObjectType {

	/**
	 * Add the root Query Types
	 *
	 * @param ObjectTypeConfig $config
	 * @return mixed
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * Define the default Query fields
		 * @since 0.0.1
		 */
		$fields = [
			new AttachmentQuery(),
			new PostQuery(),
			new TermQuery(),
		];

		/**
		 * Pass the fields through a filter
		 * @since 0.0.1
		 */
		$fields =  apply_filters( 'DFM\WPGraphQL\Schema\RootQueryType\Fields', $fields );

		/**
		 * addFields
		 * @since 0.0.1
		 */
		$config->addFields( $fields );

	}

}