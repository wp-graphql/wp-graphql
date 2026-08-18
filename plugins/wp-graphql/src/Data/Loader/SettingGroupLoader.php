<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\SettingGroup;

/**
 * Class SettingGroupLoader
 *
 * Loads settings groups (keyed by their normalized group key, e.g. "general",
 * "permalink") from the normalized settings map and returns them as
 * SettingGroup models.
 *
 * @package WPGraphQL\Data\Loader
 */
class SettingGroupLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string,array<string,mixed>> $entry The group's entries from the normalized settings map.
	 *
	 * @return \WPGraphQL\Model\SettingGroup
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new SettingGroup( (string) $key, $entry );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys Normalized settings group keys.
	 * @return array<string,array<string,array<string,mixed>>|null>
	 */
	public function loadKeys( array $keys ) {
		$groups = DataSource::get_allowed_settings_by_group( \WPGraphQL::get_type_registry() );

		$loaded = [];

		foreach ( $keys as $key ) {
			$loaded[ $key ] = ! empty( $groups[ $key ] ) ? $groups[ $key ] : null;
		}

		return $loaded;
	}
}
