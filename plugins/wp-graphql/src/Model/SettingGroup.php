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
	 * {@inheritDoc}
	 *
	 * Uses the group's registered GraphQL type name (e.g. `GeneralSettings`)
	 * so model-layer filters and debug messages identify the specific group
	 * rather than a generic model class name.
	 */
	protected function get_model_name() {
		if ( empty( $this->model_name ) ) {
			$this->model_name = \WPGraphQL\Type\ObjectType\SettingGroup::get_type_name( $this->group_key );
		}

		return $this->model_name;
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

			foreach ( $this->data as $setting_field ) {
				$field_key = isset( $setting_field['graphql_field_name'] ) ? (string) $setting_field['graphql_field_name'] : '';

				// `id` is reserved for the node identifier.
				if ( empty( $field_key ) || isset( $this->fields[ $field_key ] ) ) {
					continue;
				}

				$callback = function () use ( $setting_field ) {
					return $this->resolve_setting_value( $setting_field );
				};

				// A setting carrying `graphql_capability` is declared as a
				// capability-gated field; the Model nulls it (with a debug
				// message) for users lacking the capability.
				if ( ! empty( $setting_field['graphql_capability'] ) ) {
					$this->fields[ $field_key ] = [
						'callback'   => $callback,
						'capability' => (string) $setting_field['graphql_capability'],
					];
					continue;
				}

				$this->fields[ $field_key ] = $callback;
			}
		}
	}

	/**
	 * Resolves the value of a single setting entry: reads the option, casts it
	 * by the entry's declared type, gives the entry's own `graphql_resolve`
	 * callback the first pass, then applies the `graphql_setting_field_value`
	 * filter.
	 *
	 * This is the single value-resolution path for settings; the grouped and
	 * flat read surfaces both resolve through the model.
	 *
	 * @param array<string,mixed> $setting_field The setting entry from the normalized settings map.
	 *
	 * @return mixed
	 */
	private function resolve_setting_value( array $setting_field ) {
		$option = ! empty( $setting_field['key'] ) ? get_option( (string) $setting_field['key'] ) : null;

		switch ( $setting_field['type'] ?? '' ) {
			case 'integer':
			case 'int':
				$value = absint( $option );
				break;
			case 'string':
				$value = (string) $option;
				break;
			case 'boolean':
			case 'bool':
				$value = (bool) $option;
				break;
			case 'number':
			case 'float':
				$value = (float) $option;
				break;
			default:
				$value = ! empty( $option ) ? $option : null;
				break;
		}

		/**
		 * Give the setting's own resolver, declared as `graphql_resolve`
		 * in the normalized settings map, the first pass at the value.
		 */
		if ( isset( $setting_field['graphql_resolve'] ) && is_callable( $setting_field['graphql_resolve'] ) ) {
			$value = call_user_func( $setting_field['graphql_resolve'], $value, $setting_field, $this->group_key );
		}

		/**
		 * Filters the resolved value of a single settings field before it is returned in the Schema.
		 *
		 * This gives extensions a seam to normalize or override a setting's resolved value
		 * without adding one-off special cases to the core resolver.
		 *
		 * @param mixed               $value         The resolved (and type-cast) value of the setting field.
		 * @param array<string,mixed> $setting_field The setting field config, including its `key` and `type`.
		 * @param string              $group_name    The name of the settings group the field belongs to.
		 *
		 * @hookGroup settings
		 * @since 2.18.0
		 */
		return apply_filters( 'graphql_setting_field_value', $value, $setting_field, $this->group_key );
	}
}
