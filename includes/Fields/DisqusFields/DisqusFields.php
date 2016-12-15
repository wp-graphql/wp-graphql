<?php
namespace DFM\WPGraphQL\Fields\DisqusFields;

use DFM\WPGraphQL\Fields\DisqusFields\DisqusNeedsSyncField;
use DFM\WPGraphQL\Fields\DisqusFields\DisqusThreadIdField;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class DisqusFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class DisqusFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'disqus', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to Disqus', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		$fields = [
			/**
			 * DisqusNeedsSyncField
			 * @since 0.0.2
			 */
			new DisqusNeedsSyncField(),

			/**
			 * DisqusThreadIdField
			 * @since 0.0.2
			 */
			new DisqusThreadIdField(),
		];

		/**
		 * addFields
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

	}

}