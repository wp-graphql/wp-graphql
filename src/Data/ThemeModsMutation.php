<?php

namespace WPGraphQL\Data;

/**
 * Class ThemeModsMutation
 *
 * @package WPGraphQL\Data\ThemeModsMutation
 */
class ThemeModsMutation {
	/**
	 * This handles updating theme modifications
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
		if ( ! empty( $input['customCssPost'] ) ) {
			$prepared_values['custom_css_post_id'] = $input['customCssPost'];
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
						$value_name = 'background_'. preg_replace( '/([A-Z])/', '_\\L$1', $key );
						$prepared_values[ $data_key ]->$value_name = $value;

				}
			}
		}

		if ( ! empty( $input['navMenuLocations'] ) && is_array( $input['navMenuLocations'] ) ) {
			$prepared_values[ 'nav_menu_locations' ] = array_merge(
				get_nav_menu_locations(),
				$input['navMenuLocations']
			);
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
	
	/**
	 * Updates theme modifications with $theme_mods_args array
	 *
	 * @param array $theme_mods_args - new values for theme mods
	 * @return boolean - true on success and false on fail
	 */
	public static function update_theme_mods( $theme_mods_args ) {
		
		if( is_array( $theme_mods_args ) && ! empty( $theme_mods_args ) ) {
			foreach( $theme_mods_args as $name => $value ) {
				set_theme_mod( $name, $value );
			}
			return true;
		}

		return false;

	}
	
}