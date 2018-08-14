<?php

namespace WPGraphQL\Type\ThemeMods\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\ThemeMods\ThemeModsFields;
use WPGraphQL\Types;

/**
 * Class ThemeModsMutation
 *
 * @package WPGraphQL\Type\ThemeMods
 */
class ThemeModsMutation {

	/**
	 * Holds the input_fields configuration
	 *
	 * @var array
	 */
  private static $input_fields = [];

  /**
   * Stores the background input object
   *
   * @var WPInputObjectType
   */
  private static $background_input;

  /**
   * Stores the header image input object
   *
   * @var WPInputObjectType
   */
  private static $header_image_input;

  /**
   * Stores the nav menu locations input object
   *
   * @var WPInputObjectType
   */
  private static $nav_menu_locations_input;

  /**
	 * @return mixed|array|null $input_fields
	 */
	public static function input_fields() {
    if ( empty( self::$input_fields ) ) {
			$input_fields = [
        'background'      => [
					'type'        => self::background_input(),
        ],
        'backgroundColor'      => [
					'type'        => Types::string(),
					'description' => __( 'The theme mod "background-color" hex color code', 'wp-graphql' ),
        ],
        'customCssPostId'      => [
					'type'        => Types::int(),
					'description' => __( 'The theme mod "custom-css-post-id" post id', 'wp-graphql' ),
				],
				'customLogo'      => [
					'type'        => Types::int(),
					'description' => __( 'The theme mod "custom-logo" attachment id', 'wp-graphql' ),
        ],
        'headerImage'      => [
					'type'        => self::header_image_input(),
        ],
				'navMenuLocations'      => [
					'type'        => self::nav_menu_locations_input(),
				],
			];

			/**
			 * Filters the mutation input fields for the object type
			 *
			 * @param array $input_fields The array of input fields
			 */
			self::$input_fields = apply_filters( 'graphql_theme_mods_input_fields', $input_fields );
		}

		return ( ! empty( self::$input_fields ) ) ? self::$input_fields : null;
  }

  /**
   * Returns the BackgroundInput definition
   *
   * @return WPInputObjectType
   */
  private static function background_input() {

    return self::$background_input ?: self::$background_input = new WPInputObjectType( [
      'name' => 'BackgroundInput',
      'fields' => [
        'imageId' => [
          'type'        => Types::id(),
          'description' => __( 'The theme mod "background"\'s image attachment id', 'wp-graphql' ),
        ],
        'preset' => [
          'type'        => Types::string(),
          'description' => __( 'The theme mod "background"\'s preset property', 'wp-graphql' ),
        ],
        'size' => [
          'type'        => Types::string(),
          'description' => __( 'The theme mod "background"\'s css background-size property', 'wp-graphql' ),
        ],
        'repeat' => [
          'type'        => Types::string(),
          'description' => __( 'The theme mod "background"\'s css background-repeat property', 'wp-graphql' ),
        ],
        'attachment' => [
          'type'        => Types::string(),
          'description' => __( 'The theme mod "background"\'s css background-attachement property', 'wp-graphql' ),
        ],
      ],
    ] );

  }

  /**
   * Returns the HeaderImageInput definition
   *
   * @return WPInputObjectType
   */
  private static function header_image_input() {

    return self::$header_image_input ?: self::$header_image_input = new WPInputObjectType( [
      'name' => 'HeaderImageInput',
      'fields' => [
        'imageId' => [
          'type'        => Types::id(),
          'description' => __( 'The theme mod "header-image"\'s image attachment id', 'wp-graphql' ),
        ],
        'thumbnailUrl' => [
          'type'        => Types::string(),
          'description' => __( 'The theme mod "header-image"\'s thumbnail url', 'wp-graphql' ),
        ],
        'height' => [
          'type'        => Types::int(),
          'description' => __( 'The theme mod "header-image"\'s display width in pixels', 'wp-graphql' ),
        ],
        'width' => [
          'type'        => Types::int(),
          'description' => __( 'The theme mod "header-image"\'s display height in pixels', 'wp-graphql' ),
        ],
      ]
    ] );
    
  }

  /**
   * Returns the NavMenuLocationsInput definition
   *
   * @return WPInputObjectType|null
   */
  private static function nav_menu_locations_input() {

    return self::$nav_menu_locations_input ?: self::$nav_menu_locations_input = new WPInputObjectType( [
      'name' => 'NavMenuLocationsInput',
      'fields' => function() {
        /**
         * Get nav menu locations
         */
        $locations = DataSource::get_registered_nav_menu_locations();

        $fields = [];
        if ( ! empty( $locations ) ) {
          foreach( $locations as $location ) {
            $fields[ $location ] = [
              'type' => Types::int(),
              'description' => __( 'The WP ID of the nav menu to be assigned to %s', 'wp-graphql', $location ),
            ];
          }
        }

        return $fields;
      },
    ] );

  }

  /**
	 * This handles inserting the comment and creating
	 *
	 * @param array  $input         The input for the mutation
	 * @param string $mutation_name The name of the mutation being performed
	 *
	 * @return array $output_args
	 * @throws \Exception
	 */
	public static function prepare_theme_mods_values( $input, $mutation_name ) {
		
    $prepared_values = [];

    /**
     * Scalar Inputs
     */
		if ( ! empty( $input['backgroundColor'] ) ) {
			$prepared_values['background_color'] = $input['backgroundColor'];
    }
    if ( ! empty( $input['customCssPostId'] ) ) {
			$prepared_values['custom_css_post_id'] = $input['customCssPostId'];
    }
    if ( ! empty( $input['customLogo'] ) ) {
			$prepared_values['custom_logo'] = $input['customLogo'];
    }

    /**
     * Complex Inputs
     */
    if ( ! empty( $input['background'] ) && is_array( $input['background'] ) ) {
      foreach( $input['background'] as $key => $value ) {
        switch( $key ) {
          case 'imageId':
            $value_key = 'background_image';
            $prepared_values[ $value_key ] = wp_get_attachment_url( $value );
            break;
          default: 
            $value_key = 'background_'. preg_replace( '/([A-Z])/', '_\\L$1', $key );
            $prepared_values[ $value_key ] = $value;
        }
      }
    }

    if ( ! empty( $input['headerImage'] ) && is_array( $input['headerImage'] ) ) {

      $data_key = 'header_image_data';
      foreach( $input['headerImage'] as $key => $value ) {
        switch( $key ) {
          case 'imageId':
            $url = wp_get_attachment_url( $value );
            $value_key = 'header_image';
            $prepared_values[ $value_key ] = $url;

            if( empty( $prepared_values[ $data_key ] ) ) {
              $prepared_values[ $data_key ] = get_theme_mod( $data_key, new \stdClass );
            }
            $prepared_values[ $data_key ]->url = $url;
            break;

          default:
            if( empty( $prepared_values[ $data_key ] ) ) {
              $prepared_values[ $data_key ] = get_theme_mod( $data_key, new \stdClass );
            }
            $value_name = 'background_'. preg_replace( '/([A-Z])/', '_\\L$1', $name );
            $prepared_values[ $data_key ]->$value_name = $value;

        }
      }
    }

    if ( ! empty( $input['navMenuLocations'] ) && is_array( $input['navMenuLocations'] ) ) {
      $prepared_values[ 'nav_menu_locations' ] = $input['navMenuLocations'];
    }

		/**
		 * Filter the $theme_mods_update_values
		 *
		 * @param array  $prepared_values   The array of theme mods values that will be passed to set_theme_mod
		 * @param array  $input             The data that was entered as input for the mutation
		 * @param string $mutation_type     The type of mutation being performed ( create, edit, etc )
		 */
		$prepared_values = apply_filters( 'graphql_theme_mods_update_values', $prepared_values, $input, $mutation_name );

		return $prepared_values;
	}
  
}