<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class SettingGroup - Models the data for a settings group
 *
 * This is the data-layer Model for a settings group (general, reading,
 * discussion, permalink, etc.), giving each group a globally unique
 * identifier so it can be resolved as a Node. Not to be confused with
 * \WPGraphQL\Type\ObjectType\SettingGroup, which registers the GraphQL
 * object types for setting groups.
 *
 * @property ?string $id
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<array<string,array<string,mixed>>>
 */
class SettingGroup extends Model {
	/**
	 * The normalized settings group key (e.g. "general", "permalink").
	 *
	 * @var string
	 */
	protected $group_key;

	/**
	 * SettingGroup constructor.
	 *
	 * @param string                            $group_key The normalized settings group key.
	 * @param array<string,array<string,mixed>> $settings  The group's entries from the normalized settings map.
	 *
	 * @throws \Exception
	 */
	public function __construct( string $group_key, array $settings ) {
		$this->group_key = $group_key;
		$this->data      = $settings;

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Setting groups are publicly readable by default; individual settings
	 * within a group can restrict reads at the field level.
	 */
	protected function is_private() {
		return false;
	}

	/**
	 * Returns the normalized settings group key the model was loaded for.
	 */
	public function get_group_key(): string {
		return $this->group_key;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id' => function () {
					return Relay::toGlobalId( 'setting_group', $this->group_key );
				},
			];
		}
	}
}
