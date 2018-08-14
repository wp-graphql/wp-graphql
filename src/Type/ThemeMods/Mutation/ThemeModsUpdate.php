<?php

namespace WPGraphQL\Type\ThemeMods\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class ThemeModsUpdate
 *
 * @package WPGraphQL\Type\ThemeMods\Mutation
 */
class ThemeModsUpdate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the update mutation for ThemeMods
	 *
	 * @return array|mixed
	 */
	public static function mutate() {
		if ( empty( self::$mutation ) ) {
			$mutation_name  = 'UpdateThemeMods';
			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Update theme mods', 'wp-graphql' ),
				'inputFields'         => WPInputObjectType::prepare_fields( ThemeModsMutation::input_fields(), $mutation_name ),
				'outputFields'        => [
					'themeMods' => [
						'type'    => Types::theme_mods(),
						'resolve' => function () {
              return DataSource::get_theme_mods_data();
						},
					],
				],
				'mutateAndGetPayload' => function ( $input, AppContext $context, ResolveInfo $info ) use ( $mutation_name ) {

          $prepared_values = ThemeModsMutation::prepare_theme_mods_values( $input, $mutation_name );

          /**
					 * Check if user has required capabilities
					 */
					if (
						! current_user_can( 'edit_theme_options' )
					) {
						throw new UserError( __( 'You do not have the appropriate capabilities to update theme mods.', 'wp-graphql' ) );
					}

					/**
					 * Update theme mods
					 */
					$success = self::update_theme_mods( $prepared_values );

					/**
					 * Throw an exception if the comment failed to be created
					 */
					if ( ! $success ) {
						throw new UserError( __( 'The theme mods failed to update', 'wp-graphql' ) );
          }
          
        },
        ] );
      }
  
      return ( ! empty( self::$mutation ) ) ? self::$mutation : null;
    }

    /**
     * Updates theme modifications with $theme_mods_args array
     *
     * @param array $theme_mods_args - new values for theme mods
     * @return boolean - true on success and false on fail
     */
    private static function update_theme_mods( $theme_mods_args ) {

      if( is_array( $theme_mods_args ) && ! empty( $theme_mods_args ) ) {
        foreach( $theme_mods_args as $name => $value ) {
          set_theme_mod( $name, $value );
        }
        return true;
      }

      return false;

    }
  }