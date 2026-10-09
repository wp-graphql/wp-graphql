<?php

namespace WPGraphQL\Admin\SettingsReview;

use WPGraphQL\Admin\Settings\SettingsRegistry;

/**
 * Class FieldFactory
 *
 * Turns registered settings fields into the shape the settings review renders.
 *
 * A setting opts in with the `settings_review` key of the config passed to
 * register_graphql_settings_field(). Everything else here is derived from the setting's own
 * registration, so a setting has one label, one description and one set of tradeoffs wherever it
 * is shown.
 *
 * @package WPGraphQL\Admin\SettingsReview
 *
 * @phpstan-import-type SettingsReviewField from \WPGraphQL\Admin\SettingsReview\SettingsReview
 * @phpstan-import-type SettingsReviewOption from \WPGraphQL\Admin\SettingsReview\SettingsReview
 */
final class FieldFactory {

	/**
	 * The settings registry the review reads settings from.
	 */
	private SettingsRegistry $registry;

	/**
	 * The steps a setting can be assigned to.
	 */
	private StepRegistry $steps;

	/**
	 * The prepared settings, once the settings registry is fully populated.
	 *
	 * @var array<string,SettingsReviewField>|null
	 */
	private ?array $fields = null;

	/**
	 * FieldFactory constructor.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry   $registry The settings registry.
	 * @param \WPGraphQL\Admin\SettingsReview\StepRegistry $steps    The step registry.
	 */
	public function __construct( SettingsRegistry $registry, StepRegistry $steps ) {
		$this->registry = $registry;
		$this->steps    = $steps;
	}

	/**
	 * Returns the settings shown in the review, keyed by "{section}.{name}".
	 *
	 * @return array<string,SettingsReviewField>
	 */
	public function all(): array {
		if ( null !== $this->fields ) {
			return $this->fields;
		}

		$fields = [];
		$index  = 0;

		foreach ( $this->registry->get_settings_fields() as $section => $section_fields ) {
			foreach ( $section_fields as $field ) {
				++$index;

				$field = $this->prepare( (string) $section, $field, $index );

				if ( null !== $field ) {
					$fields[ $field['key'] ] = $field;
				}
			}
		}

		// Drop dependencies on settings that aren't in the review.
		foreach ( $fields as $key => $field ) {
			if ( null !== $field['dependsOn'] && ! isset( $fields[ $field['dependsOn'] ] ) ) {
				$fields[ $key ]['dependsOn'] = null;
			}
		}

		// Tie-broken on key: `order` defaults to registration position, so ties only happen when two
		// settings declare the same order, and sorting is only stable as of PHP 8.0 while this plugin
		// supports 7.4.
		uasort(
			$fields,
			static function ( $a, $b ) {
				return [ $a['order'], $a['key'] ] <=> [ $b['order'], $b['key'] ];
			}
		);

		// Settings are registered on `init`. Only keep the result once registration is done.
		if ( did_action( 'graphql_init_settings' ) ) {
			$this->fields = $fields;
		}

		return $fields;
	}

	/**
	 * Returns the current value of each setting in the review, keyed by "{section}.{name}".
	 *
	 * @return array<string,mixed>
	 */
	public function values(): array {
		$registered = [];

		foreach ( $this->registry->get_settings_fields() as $section => $section_fields ) {
			foreach ( $section_fields as $field ) {
				if ( isset( $field['name'] ) && is_string( $field['name'] ) ) {
					$registered[ $section . '.' . $field['name'] ] = $field;
				}
			}
		}

		$values = [];

		foreach ( $this->all() as $key => $field ) {
			$stored = get_option( $field['section'], [] );
			$stored = is_array( $stored ) ? $stored : [];

			$value = $stored[ $field['name'] ] ?? $field['default'];

			// A locked field can declare the value that applies, for example when a constant decides it.
			// Only locked fields use it: for other fields it was computed when settings were registered and
			// can be out of date after the review saves.
			if ( $field['locked'] && array_key_exists( 'value', $registered[ $key ] ?? [] ) ) {
				$value = $registered[ $key ]['value'];
			}

			if ( 'checkbox' === $field['type'] ) {
				$value = ( 'on' === $value || true === $value ) ? 'on' : 'off';
			}

			$values[ $key ] = $value;
		}

		return $values;
	}

	/**
	 * Prepares a registered settings field for the review.
	 *
	 * @param string              $section The settings section the field is registered to.
	 * @param array<string,mixed> $field   The registered field config.
	 * @param int                 $index   The position the field was registered in.
	 *
	 * @return SettingsReviewField|null The prepared field, or null if the field isn't shown in the review.
	 */
	private function prepare( string $section, array $field, int $index ): ?array {
		$config = $field['settings_review'] ?? false;

		if ( true === $config ) {
			$config = [];
		}

		if ( ! is_array( $config ) || empty( $field['name'] ) || ! is_string( $field['name'] ) ) {
			return null;
		}

		$type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : 'text';

		if ( ! in_array( $type, SettingsReview::SUPPORTED_FIELD_TYPES, true ) ) {
			_doing_it_wrong(
				'register_graphql_settings_field',
				sprintf(
					/* translators: 1: settings field name, 2: settings field type */
					esc_html__( 'The "%1$s" setting can\'t be shown in the settings review because the settings review doesn\'t support the "%2$s" field type.', 'wp-graphql' ),
					esc_html( $field['name'] ),
					esc_html( $type )
				),
				'x-release-please-version'
			);
			return null;
		}

		$name = $field['name'];
		$step = isset( $config['step'] ) && is_string( $config['step'] ) ? sanitize_key( $config['step'] ) : '';

		if ( '' === $step || ! $this->steps->has( $step ) ) {
			$step = sanitize_key( $section );
		}

		// Tradeoffs belong to the setting, not the review, so other settings screens can show them too.
		$tradeoffs = isset( $field['tradeoffs'] ) && is_array( $field['tradeoffs'] ) ? $field['tradeoffs'] : [];

		// The review always uses the setting's own label, so each setting has one name everywhere.
		$label       = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $name;
		$description = isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : ( isset( $field['desc'] ) && is_string( $field['desc'] ) ? wp_strip_all_tags( $field['desc'] ) : '' );

		return [
			'key'         => $section . '.' . $name,
			'section'     => $section,
			'name'        => $name,
			'type'        => $type,
			'step'        => $step,
			'order'       => isset( $config['order'] ) ? (int) $config['order'] : $index,
			'label'       => $label,
			'description' => $description,
			'options'     => $this->options( $type, $field ),
			'min'         => isset( $field['min'] ) && is_numeric( $field['min'] ) ? $field['min'] + 0 : null,
			'max'         => isset( $field['max'] ) && is_numeric( $field['max'] ) ? $field['max'] + 0 : null,
			'inputStep'   => isset( $field['step'] ) && is_numeric( $field['step'] ) ? $field['step'] + 0 : null,
			'default'     => $field['default'] ?? '',
			'benefits'    => self::string_list( $tradeoffs['benefits'] ?? [] ),
			'costs'       => self::string_list( $tradeoffs['costs'] ?? [] ),
			'dependsOn'   => isset( $field['depends_on'] ) && is_string( $field['depends_on'] ) && '' !== $field['depends_on'] ? $section . '.' . $field['depends_on'] : null,
			'locked'      => ! empty( $field['disabled'] ),
		];
	}

	/**
	 * Returns the choices for a select, radio or user role field.
	 *
	 * @param string              $type  The field type.
	 * @param array<string,mixed> $field The registered field config.
	 *
	 * @return SettingsReviewOption[]
	 */
	private function options( string $type, array $field ): array {
		if ( 'user_role_select' === $type ) {
			$options = [
				[
					'value' => 'any',
					'label' => __( 'Any user, including logged-out visitors', 'wp-graphql' ),
				],
			];

			foreach ( wp_roles()->get_names() as $role => $role_name ) {
				$options[] = [
					'value' => (string) $role,
					'label' => translate_user_role( $role_name ),
				];
			}

			return $options;
		}

		if ( ! in_array( $type, [ 'select', 'radio' ], true ) || empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
			return [];
		}

		$options = [];

		foreach ( $field['options'] as $value => $label ) {
			$options[] = [
				'value' => (string) $value,
				'label' => is_string( $label ) ? wp_strip_all_tags( $label ) : (string) $value,
			];
		}

		return $options;
	}

	/**
	 * Returns only the non-empty strings in a list.
	 *
	 * @param mixed $items The list.
	 *
	 * @return string[]
	 */
	private static function string_list( $items ): array {
		if ( ! is_array( $items ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return is_string( $item ) && '' !== $item;
				}
			)
		);
	}
}
