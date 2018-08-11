<?php

namespace WPGraphQL\Type\ThemeMods;

use WPGraphQL\Types;
use WPGraphQL\Data\DataSource;

/**
 * Class ThemeModQuery
 *
 * @since 0.0.32
 * @package WPGraphQL\Type\ThemeMods
 */
class ThemeModsQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 * @since 0.0.32
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition
	 * for ThemeMod
	 *
	 * @access public
	 *
	 * @return array $root_query
	 */
	public static function root_query() {
		if ( null === self::$root_query ) {
			self::$root_query = [
				'type'        => Types::theme_mods(),
				'resolve'     => function () {
					return DataSource::get_theme_mods_data();
				},
			];
		}

		return self::$root_query;
	}
}
