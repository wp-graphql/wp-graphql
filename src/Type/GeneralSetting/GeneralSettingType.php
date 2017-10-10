<?php
namespace WPGraphQL\Type\GeneralSetting;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class GeneralSettingType
 *
 * This sets up the base GeneralSettingType.
 *
 * @package WPGraphQL\Type
 */
class GeneralSettingType extends WPObjectType {

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 * @access private
	 */
	private static $type_name;

	/**
	 * Holds the $fields definition for the GeneralSettingType
	 *
	 * @var $fields
	 * @access private
	 */
	private static $fields;

	/**
	 * GeneralSettingType constructor
	 *
	 * @access public
	 */
	public function __construct() {
		/**
		 * Set the type_name
		 */
		self::$type_name = 'generalSetting';

		/**
		 * Set up the configuration of the specific option_type
		 *
		 * @access public
		 */
		$config = [
			'name'        => self::$type_name,
			'description' => __( 'A general setting object', 'wp-graphql' ),
			'fields'      => self::fields(),
			'interfaces'  => [ self::node_interface() ],
		];

		parent::__construct( $config );

	}

	/**
	 * Fields
	 *
	 * This defines the fields for the GeneralSettingType. The fields are passed through a filter so the shape of the schema
	 * can be modified
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 */
	private static function fields() {

		if ( null === self::$fields ) :
			self::$fields = function() {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( $general_setting, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $general_setting ) && ! empty( $general_setting['name'] ) ) ? Relay::toGlobalId( 'generalSetting', $general_setting['name'] ) : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => __( 'Display name of the general setting.', 'wp-graphql' ),
						'resolve' => function( array $general_setting, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $general_setting['name'] ) ? $general_setting['name'] : null;
						},
					],
					'intValue' => [
						'type' => Types::int(),
						'description' => __( 'The integer value of the setting.', 'wp-graphql' ),
						'resolve' => function( array $general_setting, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $general_setting['intValue'] ) ? $general_setting['intValue'] : null;
						},
					],
					'stringValue' => [
						'type' => Types::string(),
						'description' => __( 'The string value of the setting.', 'wp-graphql' ),
						'resolve' => function( array $general_setting, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $general_setting['stringValue'] ) ? $general_setting['stringValue'] : null;
						},
					],
					'boolValue' => [
						'type' => Types::boolean(),
						'description' => __( 'The boolean value of the setting.', 'wp-graphql' ),
						'resolve' => function( array $general_setting, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $general_setting['boolValue'] ) ? $general_setting['boolValue'] : null;
						},
					]
				];

				/**
				 * This will prepare the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields method needs to be applied
				 * to the fields directly instead of being applied to all objects extending the WPObjectType class.
				 */
				return self::prepare_fields( $fields, self::$type_name );
			};
		endif;
		return self::$fields;
	}

}
