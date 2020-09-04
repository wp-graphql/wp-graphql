<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class TimezoneEnum {
	public static function register_type() {
		/**
		 * Logic for this taken from the `wp_timezone_choice` here:
		 * https://github.com/WordPress/WordPress/blob/c204ac4bc7972c9ca1e6b354ec8fb0851e255bc5/wp-includes/functions.php#L5191
		 */

		$enum_values = [];

		$locale           = get_locale();
		static $mo_loaded = false, $locale_loaded = null;
		$continents       = [
			'Africa',
			'America',
			'Antarctica',
			'Arctic',
			'Asia',
			'Atlantic',
			'Australia',
			'Europe',
			'Indian',
			'Pacific',
		];
		// Load translations for continents and cities.
		if ( ! $mo_loaded || $locale !== $locale_loaded ) {
			$locale_loaded = $locale ? $locale : get_locale();
			$mofile        = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
			unload_textdomain( 'continents-cities' );
			load_textdomain( 'continents-cities', $mofile );
			$mo_loaded = true;
		}
		$zonen = [];
		foreach ( timezone_identifiers_list() as $zone ) {
			$zone = explode( '/', $zone );
			if ( ! in_array( $zone[0], $continents, true ) ) {
				continue;
			}
			// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
			$exists    = [
				0 => ( isset( $zone[0] ) && $zone[0] ),
				1 => ( isset( $zone[1] ) && $zone[1] ),
				2 => ( isset( $zone[2] ) && $zone[2] ),
			];
			$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
			$exists[4] = ( $exists[1] && $exists[3] );
			$exists[5] = ( $exists[2] && $exists[3] );
			// phpcs:disable WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText
			$zonen[] = [
				'continent'   => ( $exists[0] ? $zone[0] : '' ),
				'city'        => ( $exists[1] ? $zone[1] : '' ),
				'subcity'     => ( $exists[2] ? $zone[2] : '' ),
				't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
				't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
				't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' ),
			];
			// phpcs:enable
		}
		usort( $zonen, '_wp_timezone_choice_usort_callback' );

		foreach ( $zonen as $key => $zone ) {
			// Build value in an array to join later
			$value = [ $zone['continent'] ];
			if ( empty( $zone['city'] ) ) {
				// It's at the continent level (generally won't happen)
				$display = $zone['t_continent'];
			} else {
				// It's inside a continent group
				// Continent optgroup
				if ( ! isset( $zonen[ $key - 1 ] ) || $zonen[ $key - 1 ]['continent'] !== $zone['continent'] ) {
					$label = $zone['t_continent'];
				}
				// Add the city to the value
				$value[] = $zone['city'];
				$display = $zone['t_city'];
				if ( ! empty( $zone['subcity'] ) ) {
					// Add the subcity to the value
					$value[]  = $zone['subcity'];
					$display .= ' - ' . $zone['t_subcity'];
				}
			}
			// Build the value
			$value = join( '/', $value );

			$enum_values[ WPEnumType::get_safe_name( $value ) ] = [
				'value'       => $value,
				'description' => $display,
			];

		}
		$offset_range = [
			- 12,
			- 11.5,
			- 11,
			- 10.5,
			- 10,
			- 9.5,
			- 9,
			- 8.5,
			- 8,
			- 7.5,
			- 7,
			- 6.5,
			- 6,
			- 5.5,
			- 5,
			- 4.5,
			- 4,
			- 3.5,
			- 3,
			- 2.5,
			- 2,
			- 1.5,
			- 1,
			- 0.5,
			0,
			0.5,
			1,
			1.5,
			2,
			2.5,
			3,
			3.5,
			4,
			4.5,
			5,
			5.5,
			5.75,
			6,
			6.5,
			7,
			7.5,
			8,
			8.5,
			8.75,
			9,
			9.5,
			10,
			10.5,
			11,
			11.5,
			12,
			12.75,
			13,
			13.75,
			14,
		];
		foreach ( $offset_range as $offset ) {

			if ( 0 <= $offset ) {
				$offset_name = '+' . $offset;
			} else {
				$offset_name = (string) $offset;
			}
			$offset_value = $offset_name;
			$offset_name  = str_replace(
				[ '.25', '.5', '.75' ],
				[
					':15',
					':30',
					':45',
				],
				$offset_name
			);
			$offset_name  = 'UTC' . $offset_name;
			$offset_value = 'UTC' . $offset_value;

			// Intentionally avoid WPEnumType::get_safe_name here for specific timezone formatting
			$enum_values[ WPEnumType::get_safe_name( $offset_name ) ] = [
				'value'       => $offset_value,
				'description' => sprintf( __( 'UTC offset: %s', 'wp-graphql' ), $offset_name ),
			];

		}

		register_graphql_enum_type(
			'TimezoneEnum',
			[
				'description' => __( 'Available timezones', 'wp-graphql' ),
				'values'      => $enum_values,
			]
		);
	}
}
