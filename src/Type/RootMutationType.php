<?php

namespace WPGraphQL\Type;

use WPGraphQL\Type\PostObject\Mutation\PostObjectCreate;
use WPGraphQL\Type\PostObject\Mutation\PostObjectDelete;
use WPGraphQL\Type\PostObject\Mutation\PostObjectUpdate;
use WPGraphQL\Type\PostObject\Mutation\TermObjectDelete;
use WPGraphQL\Type\Settings\Mutation\SettingsUpdate;
use WPGraphQL\Type\TermObject\Mutation\TermObjectCreate;
use WPGraphQL\Type\TermObject\Mutation\TermObjectUpdate;
use WPGraphQL\Type\User\Mutation\UserCreate;
use WPGraphQL\Type\User\Mutation\UserDelete;
use WPGraphQL\Type\User\Mutation\UserUpdate;
use WPGraphQL\Type\User\Mutation\UserRegister;

/**
 * Class RootMutationType
 * The RootMutationType is the primary entry point for Mutations in the GraphQL Schema
 *
 * @package WPGraphQL\Type
 * @since   0.0.8
 */
class RootMutationType extends WPObjectType {

	/**
	 * Holds the $fields definition for the PluginType
	 *
	 * @var $fields
	 */
	private static $fields;

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * RootMutationType constructor.
	 */
	public function __construct() {

		self::$type_name = 'rootMutation';

		/**
		 * Configure the rootMutation
		 */
		$config = [
			'name'        => self::$type_name,
			'description' => __( 'The root mutation', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		/**
		 * Pass the config to the parent construct
		 */
		parent::__construct( $config );

	}

	/**
	 * This defines the fields for the RootMutationType. The fields are passed through a filter so the shape of the
	 * schema can be modified, for example to add entry points to Types that are unique to certain plugins.
	 *
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {
				$fields             = [];

				/**
				 * Root mutation field for updating settings
				 */
				$fields['updateSettings'] = SettingsUpdate::mutate();

				$fields = self::prepare_fields( $fields, self::$type_name );
				return $fields;

			};

		} // End if().

		/**
		 * Pass the fields through a filter to allow for hooking in and adjusting the shape
		 * of the type's schema
		 */
		return self::$fields;

	}

}
