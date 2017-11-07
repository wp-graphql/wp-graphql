<?php

namespace WPGraphQL\Type\Setting\Mutation;

use WPGraphQL\Types;


/**
 * Class SettingUpdate
 *
 * @package WPGraphQL\Type\Setting\Mutation
 */
class SettingUpdate {

	/**
	 * Stores the setting mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Define the update mutation for various setting types
	 *
	 * @param $setting_type
	 *
	 * @return array|mixed
	 */
	public static function mutate( $setting_type ) {

		if ( empty( self::$mutation ) ) {

			/**
			 * Set the formatted name of the Setting type
			 */
			$setting_type_name = lcfirst( ucwords( $setting_type ) );

			/**
			 * Set the name of the mutation being performed
			 */
			$mutation_name = 'update' . ucwords( $setting_type );

			self::$mutation[$setting_type_name] = [
				'name' => esc_html( $mutation_name ),
				'description' => "Updates the {$setting_type_name} setting.",
				'inputFields' => \SettingMutation::input_fields( $setting_type ),
				'outputFields' => [
					$setting_type_name => [
						'type' => Types::setting( $setting_type ),
						'resolve' => function( $payload ) {
							return $payload;
						}
					],
				],
				'mutateAndGetPayload' => function( $input ) use ( $setting_type, $mutation_name ) {

					$setting_args = \SettingMutation::prepare_settings( $input );

					return "hughie";
				},
			];

			return ! empty( self::$mutation[ $setting_type_name ] ) ? self::$mutation[ $setting_type_name ] : null;

		}

	}


}
