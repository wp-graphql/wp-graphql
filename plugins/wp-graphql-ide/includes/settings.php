<?php
/**
 * Settings workspace tab support for WPGraphQL IDE.
 *
 * Captures the WPGraphQL settings registry on admin_init, exposes it through
 * the IDE's localized bootstrap data, and registers a GraphQL mutation
 * (updateGraphqlSetting) so the IDE can persist changes through its own
 * GraphQL API instead of a REST round-trip.
 *
 * Lives entirely in wp-graphql-ide — no changes to core wp-graphql.
 *
 * @package WPGraphQLIDE\Settings
 */

namespace WPGraphQLIDE\Settings;

/**
 * Filterable capability for managing WPGraphQL settings from the IDE.
 *
 * Defaults to `manage_options` to mirror the existing WPGraphQL admin
 * settings page. Extensions can override via the `graphql_manage_settings_cap`
 * filter once core wp-graphql adopts the same filter name.
 */
function get_capability(): string {
	$capability = apply_filters( 'graphql_manage_settings_cap', 'manage_options' );

	return is_string( $capability ) && ! empty( $capability ) ? $capability : 'manage_options';
}

/**
 * Returns whether the current user can manage WPGraphQL settings.
 */
function current_user_can_manage(): bool {
	return current_user_can( get_capability() );
}

/**
 * Snapshot of the WPGraphQL settings registry, captured during admin_init.
 *
 * Stored in a static so it can be reused for both localize and the GraphQL
 * mutation handler within the same request.
 *
 * @param \WPGraphQL\Admin\Settings\SettingsRegistry|null $registry
 *
 * @return array{sections: array<string,array<string,mixed>>, fields: array<string,array<int,array<string,mixed>>>}
 */
function snapshot( $registry = null ): array {
	static $cache = [
		'sections' => [],
		'fields'   => [],
	];

	if ( null !== $registry ) {
		$cache = [
			'sections' => $registry->get_settings_sections(),
			'fields'   => $registry->get_settings_fields(),
		];
	}

	return $cache;
}

/**
 * Capture the settings registry once it has been fully populated.
 *
 * `graphql_init_settings` fires from SettingsRegistry::admin_init() with the
 * registry as its argument — at that point all built-in and third-party
 * sections/fields are registered.
 *
 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry
 */
add_action(
	'graphql_init_settings',
	static function ( $registry ): void {
		snapshot( $registry );
	},
	9999
);

/**
 * Find a field config inside the captured snapshot.
 *
 * @param string $section_slug
 * @param string $field_name
 *
 * @return array<string,mixed>|null
 */
function find_field( string $section_slug, string $field_name ) {
	$fields = snapshot()['fields'][ $section_slug ] ?? [];
	foreach ( $fields as $field ) {
		if ( isset( $field['name'] ) && $field['name'] === $field_name ) {
			return $field;
		}
	}

	return null;
}

/**
 * Mapping of WPGraphQL setting field types → OneOf input variant names.
 *
 * The GraphQL variant names are camelCase (per spec convention) while
 * WPGraphQL settings field types use snake_case in PHP. The "html" type is
 * read-only and intentionally has no input variant.
 *
 * @return array<string,string>
 */
function field_type_variants(): array {
	return [
		'text'             => 'text',
		'url'              => 'url',
		'textarea'         => 'textarea',
		'number'           => 'number',
		'checkbox'         => 'checkbox',
		'select'           => 'select',
		'radio'            => 'radio',
		'multicheck'       => 'multicheck',
		'color'            => 'color',
		'user_role_select' => 'userRoleSelect',
	];
}

/**
 * Resolve the OneOf variant the caller actually provided.
 *
 * The GraphQL @oneOf directive guarantees exactly one variant is non-null,
 * so we just need to find which key it is.
 *
 * @param array<string,mixed> $value_input
 *
 * @return string|null The variant name, or null if none provided.
 */
function provided_variant( array $value_input ) {
	foreach ( $value_input as $key => $val ) {
		if ( null !== $val ) {
			return (string) $key;
		}
	}
	return null;
}

/**
 * Coerce a value coming out of the OneOf input variant into the storage shape
 * expected by WPGraphQL's settings — checkboxes store "on"/"off" strings, not
 * booleans, so the mutation surface is friendlier than the storage format.
 *
 * @param array<string,mixed>      $value_input  The OneOf value — exactly one variant set.
 * @param array<string,mixed>|null $field_config The captured field config, when available.
 */
function coerce_value( array $value_input, $field_config ) {
	$type = is_array( $field_config ) && isset( $field_config['type'] ) ? (string) $field_config['type'] : '';

	if ( array_key_exists( 'checkbox', $value_input ) && null !== $value_input['checkbox'] ) {
		// Checkboxes are stored as "on"/"off" strings; preserve that.
		return $value_input['checkbox'] ? 'on' : 'off';
	}

	if ( array_key_exists( 'number', $value_input ) && null !== $value_input['number'] ) {
		$num = $value_input['number'];

		// Integer-y values store as ints to match historical WPGraphQL behavior.
		if ( 'number' === $type && (float) $num === floor( (float) $num ) ) {
			return (int) $num;
		}

		return $num + 0;
	}

	if ( array_key_exists( 'multicheck', $value_input ) && is_array( $value_input['multicheck'] ) ) {
		return array_values(
			array_map(
				static function ( $v ) {
					return is_string( $v ) ? $v : (string) $v;
				},
				$value_input['multicheck']
			)
		);
	}

	// All remaining variants are string-shaped: text, url, textarea, select,
	// radio, color, userRoleSelect.
	foreach ( [ 'text', 'url', 'textarea', 'select', 'radio', 'color', 'userRoleSelect' ] as $key ) {
		if ( array_key_exists( $key, $value_input ) && null !== $value_input[ $key ] ) {
			return (string) $value_input[ $key ];
		}
	}

	return null;
}

/**
 * Build the structured output `value` payload for a setting.
 *
 * Returns an associative array with exactly one populated key matching the
 * field type's variant. Mirrors the OneOf input shape so callers can read the
 * value without parsing JSON.
 *
 * @param mixed                    $stored_value The value as it lives in wp_options.
 * @param array<string,mixed>|null $field_config
 *
 * @return array<string,mixed>
 */
function build_value_output( $stored_value, $field_config ): array {
	$type = is_array( $field_config ) && isset( $field_config['type'] ) ? (string) $field_config['type'] : 'text';
	$map  = field_type_variants();
	if ( ! isset( $map[ $type ] ) ) {
		return [];
	}
	$variant = $map[ $type ];

	if ( 'checkbox' === $type ) {
		return [ 'checkbox' => 'on' === $stored_value || true === $stored_value ];
	}

	if ( 'number' === $type ) {
		return [ 'number' => is_numeric( $stored_value ) ? (float) $stored_value : null ];
	}

	if ( 'multicheck' === $type ) {
		return [
			'multicheck' => is_array( $stored_value )
				? array_values( array_map( 'strval', $stored_value ) )
				: [],
		];
	}

	return [ $variant => null !== $stored_value ? (string) $stored_value : null ];
}

/**
 * Apply a registered field's sanitize_callback when present, otherwise fall
 * back to a sensible default based on the field type.
 *
 * @param mixed                    $value
 * @param array<string,mixed>|null $field_config
 */
function sanitize_value( $value, $field_config ) {
	if ( is_array( $field_config ) && isset( $field_config['sanitize_callback'] ) && is_callable( $field_config['sanitize_callback'] ) ) {
		return call_user_func( $field_config['sanitize_callback'], $value );
	}

	$type = is_array( $field_config ) && isset( $field_config['type'] ) ? (string) $field_config['type'] : '';

	switch ( $type ) {
		case 'textarea':
			return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
		case 'url':
			return is_string( $value ) ? esc_url_raw( $value ) : '';
		case 'multicheck':
			if ( ! is_array( $value ) ) {
				return [];
			}
			return array_values(
				array_map( 'sanitize_text_field', $value )
			);
		case 'number':
			return is_numeric( $value ) ? $value + 0 : 0;
		case 'checkbox':
			// coerce_value() already converted booleans → 'on'/'off' strings
			// for checkbox fields, so a re-cast here would treat 'off' (truthy
			// PHP string) as true and flip the value back. Pass through as-is.
			return $value;
		default:
			if ( is_array( $value ) ) {
				return array_values( array_map( 'sanitize_text_field', $value ) );
			}
			return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}
}

/**
 * Inject settings registry data + current values into the IDE's localized
 * bootstrap so the Settings workspace tab can render synchronously.
 *
 * @param array<string,mixed> $data
 *
 * @return array<string,mixed>
 */
function localize_settings_data( array $data ): array {
	if ( ! current_user_can_manage() ) {
		// Still tell the IDE the user can't manage settings, so the topbar
		// button can hide itself; don't ship section/field metadata.
		$data['canManageSettings'] = false;

		return $data;
	}

	$snapshot = snapshot();
	$sections = [];

	foreach ( $snapshot['sections'] as $slug => $section ) {
		$option_values = get_option( $slug, [] );
		if ( ! is_array( $option_values ) ) {
			$option_values = [];
		}

		$fields = [];
		foreach ( $snapshot['fields'][ $slug ] ?? [] as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$name             = (string) $field['name'];
			$type             = isset( $field['type'] ) ? (string) $field['type'] : 'text';
			$default          = $field['default'] ?? '';
			$current_override = $field['value'] ?? null;

			// Mirror the precedence used by get_graphql_setting().
			if ( null !== $current_override ) {
				$current = $current_override;
			} elseif ( array_key_exists( $name, $option_values ) ) {
				$current = $option_values[ $name ];
			} else {
				$current = $default;
			}

			$fields[] = [
				'name'        => $name,
				'type'        => $type,
				'label'       => isset( $field['label'] ) ? (string) $field['label'] : '',
				'desc'        => isset( $field['desc'] ) ? (string) $field['desc'] : '',
				'default'     => $default,
				'value'       => $current,
				'options'     => isset( $field['options'] ) ? $field['options'] : null,
				'placeholder' => isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '',
				'min'         => isset( $field['min'] ) ? $field['min'] : null,
				'max'         => isset( $field['max'] ) ? $field['max'] : null,
				'step'        => isset( $field['step'] ) ? $field['step'] : null,
				'disabled'    => isset( $field['disabled'] ) ? (bool) $field['disabled'] : false,
			];
		}

		$sections[] = [
			'slug'   => (string) $slug,
			'title'  => isset( $section['title'] ) ? (string) $section['title'] : (string) $slug,
			'desc'   => isset( $section['desc'] ) ? (string) $section['desc'] : '',
			'fields' => $fields,
		];
	}

	$data['canManageSettings'] = true;
	$data['settings']          = [
		'sections'   => $sections,
		'capability' => get_capability(),
	];

	return $data;
}

add_filter( 'wpgraphql_ide_localized_data', __NAMESPACE__ . '\\localize_settings_data' );

/**
 * Register the GraphQL types and mutation that drive Settings persistence.
 *
 * Schema:
 *   input UpdateGraphqlSettingValueInput @oneOf {
 *     string: String
 *     number: Float
 *     boolean: Boolean
 *     stringList: [String!]
 *   }
 *
 *   input UpdateGraphqlSettingInput {
 *     section: String!
 *     field: String!
 *     value: UpdateGraphqlSettingValueInput!
 *     clientMutationId: String
 *   }
 *
 *   type UpdateGraphqlSettingPayload {
 *     success: Boolean!
 *     section: String!
 *     field: String!
 *     valueJSON: String   # The saved value, JSON-encoded, for the client to apply optimistically.
 *     message: String
 *     clientMutationId: String
 *   }
 */
add_action(
	'graphql_register_types',
	static function (): void {

		register_graphql_input_type(
			'UpdateGraphqlSettingValueInput',
			[
				'description' => __( 'Value to set for a WPGraphQL setting. Exactly one variant must be provided.', 'wp-graphql-ide' ),
				'isOneOf'     => true,
				'fields'      => [
					'string'     => [
						'type'        => 'String',
						'description' => __( 'String value (text, url, textarea, select, radio, color).', 'wp-graphql-ide' ),
					],
					'number'     => [
						'type'        => 'Float',
						'description' => __( 'Numeric value (number-typed settings).', 'wp-graphql-ide' ),
					],
					'boolean'    => [
						'type'        => 'Boolean',
						'description' => __( 'Boolean value (checkbox-typed settings).', 'wp-graphql-ide' ),
					],
					'stringList' => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'Multi-string value (multicheck-typed settings).', 'wp-graphql-ide' ),
					],
				],
			]
		);

		register_graphql_mutation(
			'updateGraphqlSetting',
			[
				'description'         => __( 'Updates a single WPGraphQL setting field. Requires the manage-settings capability.', 'wp-graphql-ide' ),
				'inputFields'         => [
					'section' => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The settings section slug (e.g. "graphql_general_settings").', 'wp-graphql-ide' ),
					],
					'field'   => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The field name within the section.', 'wp-graphql-ide' ),
					],
					'value'   => [
						'type'        => [ 'non_null' => 'UpdateGraphqlSettingValueInput' ],
						'description' => __( 'The new value, expressed via the matching OneOf variant.', 'wp-graphql-ide' ),
					],
				],
				'outputFields'        => [
					'success'   => [
						'type'        => [ 'non_null' => 'Boolean' ],
						'description' => __( 'Whether the setting was persisted.', 'wp-graphql-ide' ),
					],
					'section'   => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The section slug that was updated.', 'wp-graphql-ide' ),
					],
					'field'     => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The field name that was updated.', 'wp-graphql-ide' ),
					],
					'valueJSON' => [
						'type'        => 'String',
						'description' => __( 'The persisted value, JSON-encoded, so the client can echo it back into local state.', 'wp-graphql-ide' ),
					],
					'message'   => [
						'type'        => 'String',
						'description' => __( 'Optional human-readable status message.', 'wp-graphql-ide' ),
					],
				],
				'mutateAndGetPayload' => static function ( $input ) {
					if ( ! current_user_can_manage() ) {
						throw new \GraphQL\Error\UserError(
							esc_html__( 'You do not have permission to manage WPGraphQL settings.', 'wp-graphql-ide' )
						);
					}

					$section_slug = isset( $input['section'] ) ? (string) $input['section'] : '';
					$field_name   = isset( $input['field'] ) ? (string) $input['field'] : '';
					$value_input  = isset( $input['value'] ) && is_array( $input['value'] ) ? $input['value'] : [];

					if ( '' === $section_slug || '' === $field_name ) {
						throw new \GraphQL\Error\UserError(
							esc_html__( 'Both `section` and `field` are required.', 'wp-graphql-ide' )
						);
					}

					$snapshot = snapshot();
					if ( ! isset( $snapshot['sections'][ $section_slug ] ) ) {
						throw new \GraphQL\Error\UserError(
							sprintf(
								/* translators: %s: settings section slug */
								esc_html__( 'Unknown WPGraphQL settings section: %s', 'wp-graphql-ide' ),
								esc_html( $section_slug )
							)
						);
					}

					$field_config = find_field( $section_slug, $field_name );
					if ( null === $field_config ) {
						throw new \GraphQL\Error\UserError(
							sprintf(
								/* translators: 1: field name, 2: section slug */
								esc_html__( 'Unknown field "%1$s" in section "%2$s".', 'wp-graphql-ide' ),
								esc_html( $field_name ),
								esc_html( $section_slug )
							)
						);
					}

					if ( ! empty( $field_config['disabled'] ) ) {
						throw new \GraphQL\Error\UserError(
							sprintf(
								/* translators: %s: field name */
								esc_html__( 'The "%s" field is disabled and cannot be updated.', 'wp-graphql-ide' ),
								esc_html( $field_name )
							)
						);
					}

					$coerced   = coerce_value( $value_input, $field_config );
					$sanitized = sanitize_value( $coerced, $field_config );

					$option = get_option( $section_slug, [] );
					if ( ! is_array( $option ) ) {
						$option = [];
					}
					$option[ $field_name ] = $sanitized;

					$updated = update_option( $section_slug, $option );

					// update_option returns false if the value is unchanged;
					// treat that as success rather than a failure.
					$saved_value = get_option( $section_slug )[ $field_name ] ?? null;
					$success     = $updated || $saved_value === $sanitized;

					return [
						'success'   => $success,
						'section'   => $section_slug,
						'field'     => $field_name,
						'valueJSON' => wp_json_encode( $sanitized ),
						'message'   => null,
					];
				},
			]
		);
	}
);
