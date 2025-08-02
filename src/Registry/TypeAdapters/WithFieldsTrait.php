<?php
/**
 * Trait for GraphQL types that can have fields.
 *
 * @package WPGraphQL\Registry\TypeAdapters;
 * @since next-version
 */

namespace WPGraphQL\Registry\TypeAdapters;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use WPGraphQL;

/**
 * Class - WithFieldsTrait
 *
 * @phpstan-import-type FieldDefinitionConfig from \GraphQL\Type\Definition\FieldDefinition
 *
 * @phpstan-type TypeDef \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
 *
 * @phpstan-require-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface
 */
trait WithFieldsTrait {
	/**
	 * Wrapper for prepare_field to prepare multiple fields for registration at once.
	 *
	 * @todo can be made not public once we no longer need back-compat in TypeRegistry.
	 *
	 * @param array<string,array<string,mixed>> $fields Array of fields and their settings to register on a Type
	 * @param array<string,mixed>               $config The configuration array for the type.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function prepare_fields( array $fields, array $config ): array {
		/**
		 * Filter all fields, passing the $typename as a param.
		 *
		 * This is useful when several different types need to be easily filtered at once.
		 * For example, if ALL types with a field of a certain name needed to be adjusted.
		 *
		 * @param array<string,array<string,mixed>> $fields    The array of fields for the object config
		 * @param string                            $type_name The name of the object type
		 * @param array<string,mixed>               $config    The configuration array for the type.
		 */
		$fields = apply_filters( 'graphql_' . static::get_kind() . '_fields', $fields, $config['name'], $config );

		/**
		 * Filter the fields with the typename explicitly in the filter name.
		 *
		 * Useful for more targeted filtering, and is applied after the general filter to allow for more specific overrides.
		 *
		 * @param array<string,array<string,mixed>> $fields The array of fields for the object config
		 * @param array<string,mixed>               $config The configuration array for the type.
		 */
		$fields = apply_filters( 'graphql_' . lcfirst( $config['name'] ) . '_fields', $fields, $config );

		/**
		 * @param array<string,array<string,mixed>> $fields The array of fields for the object config
		 * @param array<string,mixed>               $config The configuration array for the type.
		 */
		$fields = apply_filters( 'graphql_' . ucfirst( $config['name'] ) . '_fields', $fields, $config );

		// Inherit field from interfaces.
		$fields = ! empty( $config['interfaces'] ) ? static::inherit_interface_fields( $fields, $config['interfaces'] ) : $fields;

		// Prepare each field for registration.
		$prepared_fields = [];

		foreach ( $fields as $field_name => $field_config ) {
			$prepared_field = self::prepare_field( $field_config, $field_name, $config['name'] );

			if ( ! $prepared_field ) {
				continue;
			}

			$prepared_fields[ $field_name ] = $prepared_field;
		}

		return $prepared_fields;
	}

	/**
	 * Prepares the field to be registered on a GraphQL Type.
	 *
	 * @param array<string,mixed> $field_config Configuration for the field.
	 * @param string              $field_name   Name of the field to prepare.
	 * @param string              $type_name    Name of the Type to register the field to.
	 *
	 * @return array<string,mixed>|null
	 * @throws \GraphQL\Error\Error If the field configuration is invalid.
	 */
	protected static function prepare_field( array $field_config, string $field_name, string $type_name ): ?array {
		// Ensure the field has a name.
		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = lcfirst( $field_name );
		}

		// Prepare the field 'type'.
		if ( isset( $field_config['type'] ) && ( is_string( $field_config['type'] ) || is_array( $field_config['type'] ) ) ) {
			// Check if the type is valid and not excluded.
			// If it's wrapped, we need to unwrap it first.
			$unmodified_type_name = self::get_unmodified_type_name( $field_config['type'] );

			if ( empty( $unmodified_type_name ) ) {
				// If the type is empty, we can error later.
				$field_config['type'] = null;
			} elseif ( in_array( strtolower( $unmodified_type_name ), WPGraphQL::get_type_registry()->get_excluded_types(), true ) ) {
				// If the type is excluded, we skip it.
				return null;
			} else {
				// Wrap the type in a callable to ensure it is resolved at runtime.
				$field_config['type'] = static function () use ( $field_config, $unmodified_type_name, $type_name ) {
					$type = WPGraphQL::get_type_registry()->get_type( $unmodified_type_name );

					if ( ! $type ) {
						throw new Error(
							sprintf(
								/* translators: %1$s is the Field name, %2$s is the type name the field belongs to. %3$s is the non-existent type name being referenced. */
								esc_html__( 'The field \"%1$s\" on Type \"%2$s\" is configured to return \"%3$s\" which is a non-existent Type in the Schema. Make sure to define a valid type for all fields. This might occur if there was a typo with \"%3$s\", or it needs to be registered to the Schema.', 'wp-graphql' ),
								esc_html( $field_config['name'] ),
								esc_html( $type_name ),
								esc_html( $unmodified_type_name )
							)
						);
					}

					// If we unwrapped the type, we need to re-apply any modifiers.
					return is_array( $field_config['type'] )
						? self::setup_type_modifiers( $field_config['type'] )
						: $type;
				};
			}
		}

		// If the type is not (or no longer) set, we can't prepare the field.
		if ( ! isset( $field_config['type'] ) ) {
			graphql_debug(
				sprintf(
					/* translators: %s is the Field name. */
					__( 'The registered field "%s" does not have a Type defined. Make sure to define a type for all fields.', 'wp-graphql' ),
					$field_name
				),
				[
					'type'       => 'INVALID_FIELD_TYPE',
					'type_name'  => $type_name,
					'field_name' => $field_name,
				]
			);
			return null;
		}

		// Prepare the field 'args'.
		$field_config = self::prepare_field_args( $field_config, $type_name );

		// Resolve/remove fields depending on introspection.
		return static::prepare_config_for_introspection( $field_config );
	}

	/**
	 * Prepare the field's 'args' config.
	 *
	 * @param array<string,mixed> $field_config The field configuration to prepare.
	 * @param string              $type_name    The name of the Type to register the field to.
	 *
	 * @return array<string,mixed>
	 */
	protected static function prepare_field_args( array $field_config, string $type_name ): array {
		if ( ! isset( $field_config['args'] ) || ! is_array( $field_config['args'] ) ) {
			return $field_config;
		}

		$prepared_args = [];

		foreach ( $field_config['args'] as $arg_name => $arg_config ) {
			$prepared_arg = self::prepare_field( $arg_config, $arg_name, $type_name );

			if ( empty( $prepared_arg ) ) {
				continue;
			}

			$prepared_args[ $arg_name ] = $prepared_arg;
		}

		$field_config['args'] = $prepared_args;

		return $field_config;
	}

	/**
	 * Processes type modifiers (e.g., "non-null"). Loads types immediately, so do
	 * not call before types are ready to be loaded.
	 *
	 * @template WrappedType of array{non_null:mixed}|array{list_of:mixed}
	 * @param WrappedType|array<string,mixed>|string|\GraphQL\Type\Definition\Type $type The type to process.
	 *
	 * @return ($type is WrappedType ? \GraphQL\Type\Definition\Type : (array<string,mixed>|string|\GraphQL\Type\Definition\Type))
	 * @throws \Exception
	 */
	private static function setup_type_modifiers( $type ) {
		if ( ! is_array( $type ) ) {
			return $type;
		}

		if ( isset( $type['non_null'] ) ) {
			/** @var TypeDef inner_type */
			$inner_type = self::setup_type_modifiers( $type['non_null'] );
			return self::non_null( $inner_type );
		}

		if ( isset( $type['list_of'] ) ) {
			/** @var TypeDef $inner_type */
			$inner_type = self::setup_type_modifiers( $type['list_of'] );
			return self::list_of( $inner_type );
		}

		return $type;
	}

	/**
	 * Gets the actual type name, stripped of possible NonNull and ListOf wrappers.
	 *
	 * Returns an empty string if the type modifiers are malformed.
	 *
	 * @param string|array<string|int,mixed> $type The (possibly-wrapped) type name.
	 */
	private static function get_unmodified_type_name( $type ): ?string {
		if ( ! is_array( $type ) ) {
			return $type;
		}

		$type = array_values( $type )[0] ?? '';

		return self::get_unmodified_type_name( $type );
	}

	/**
	 * Given a Type, this returns an instance of a NonNull of that type.
	 *
	 * @template T of \GraphQL\Type\Definition\NullableType&\GraphQL\Type\Definition\Type
	 * @param T|string $type The Type being wrapped.
	 */
	private static function non_null( $type ): \GraphQL\Type\Definition\NonNull {
		if ( is_string( $type ) ) {
			$type_def = WPGraphQL::get_type_registry()->get_type( $type );

			/** @phpstan-var T&TypeDef $type_def */
			return Type::nonNull( $type_def );
		}

		return Type::nonNull( $type );
	}

	/**
	 * Given a Type, this returns an instance of a listOf of that type.
	 *
	 * @template T of \GraphQL\Type\Definition\Type
	 * @param T|string $type The Type being wrapped.
	 *
	 * @return \GraphQL\Type\Definition\ListOfType<\GraphQL\Type\Definition\Type>
	 */
	private static function list_of( $type ): \GraphQL\Type\Definition\ListOfType {
		if ( is_string( $type ) ) {
			$resolved_type = WPGraphQL::get_type_registry()->get_type( $type );

			if ( is_null( $resolved_type ) ) {
				$resolved_type = Type::string();
			}

			$type = $resolved_type;
		}

		return Type::listOf( $type );
	}

	/**
	 * Gets the fields inherited from interfaces, if any.
	 *
	 * @param array<string,array<string,mixed>>        $fields     The fields to inherit from.
	 * @param \GraphQL\Type\Definition\InterfaceType[] $interfaces The interfaces to inherit fields from.
	 *
	 * @return array<string,array<string,mixed>> $fields The fields inherited from the interfaces.
	 */
	protected static function inherit_interface_fields( array $fields, array $interfaces ): array {
		foreach ( $interfaces as $interface_type ) {
			$interface_fields = $interface_type->getFields();
			foreach ( array_reverse( $interface_fields ) as $field_name => $field_definition ) {
				// Always start with the interface field configuration.
				$inherited_field = $field_definition->config;

				// If the field exists in the object type, merge it with the interface field.
				if ( isset( $fields[ $field_name ] ) ) {
					$inherited_field = self::merge_field_configs(
						$field_name,
						$inherited_field,
						$fields[ $field_name ]
					);
				}

				// Ensure the final field configuration is applied.
				$fields[ $field_name ] = $inherited_field;
			}
		}

		return $fields;
	}

	/**
	 * Merges field configurations from the interface into the type's field configuration.
	 *
	 * @param string              $field_name      The name of the field.
	 * @param array<string,mixed> $field           The field configuration from the type.
	 * @param array<string,mixed> $interface_field The field configuration from the interface.
	 *
	 * @return array<string,mixed> The merged field configuration.
	 */
	private static function merge_field_configs( string $field_name, array $field, array $interface_field ): array {
		foreach ( $interface_field as $key => $config ) {
			if ( empty( $config ) ) {
				continue;
			}

			if ( 'args' === $key ) {
				$field_args     = $field['args'] ?? [];
				$interface_args = $interface_field['args'] ?? [];
				$field[ $key ]  = self::merge_field_args( $field_name, $field_args, $interface_args );
				continue;
			}

			// Always prefer the latest type/config from the interface.
			$field[ $key ] = $config;
		}

		return $field;
	}

	/**
	 * Merge the field args from the field and the interface.
	 *
	 * @param string              $field_name     The field name.
	 * @param array<string,mixed> $field_args     The field args.
	 * @param array<string,mixed> $interface_args The interface args.
	 *
	 * @return array<string,mixed>
	 */
	private static function merge_field_args( string $field_name, array $field_args, array $interface_args ): array {
		foreach ( $interface_args as $arg_name => $arg_config ) {
			if ( ! isset( $field_args[ $arg_name ] ) ) {
				$field_args[ $arg_name ] = $arg_config;
				continue;
			}

			$field_arg_type     = self::normalize_type_name( $field_args[ $arg_name ]['type'] );
			$interface_arg_type = self::normalize_type_name( $arg_config['type'] );

			if ( ! empty( $field_arg_type ) && $field_arg_type !== $interface_arg_type ) {
				graphql_debug(
					sprintf(
						/* translators: 1: Field name, 2: Argument name, 3: Expected type, 4: Actual type */
						__( 'Field argument "%1$s.%2$s" expected to be of type "%3$s" but got "%4$s".', 'wp-graphql' ),
						$field_name,
						$arg_name,
						$field_arg_type,
						$interface_arg_type,
					)
				);
				continue;
			}

			$field_args[ $arg_name ] = array_merge( $arg_config, $field_args[ $arg_name ] );
		}

		// Sort args by key to ensure consistent ordering.
		ksort( $field_args );

		return $field_args;
	}

	/**
	 * Given a type it will return a string representation of the type.
	 *
	 * This is used for optimistic comparison of the arg types using strings.
	 *
	 * @param string|array<string,mixed>|callable|\GraphQL\Type\Definition\Type $type The type to normalize.
	 */
	private static function normalize_type_name( $type ): string {
		// Bail early if the type is empty.
		if ( empty( $type ) ) {
			return '';
		}

		// If the type is a callable, we need to resolve it.
		if ( is_callable( $type ) ) {
			$type = $type();
		}

		// If the type is an instance of a Type, we can get the name.
		if ( $type instanceof \GraphQL\Type\Definition\Type ) {
			$type = $type->name ?? $type->toString();
		}

		// If the type is *now* a string, we can return it.
		if ( is_string( $type ) ) {
			return $type;
		} elseif ( ! is_array( $type ) ) {
			// If the type is not an array, we can't do anything with it.
			return '';
		}

		// Arrays mean the type can be nested in modifiers.
		$output   = '';
		$modifier = array_keys( $type )[0];
		$type     = $type[ $modifier ];

		// Convert the type wrappers to a string, and recursively get the internals.
		switch ( $modifier ) {
			case 'list_of':
				$output = '[' . self::normalize_type_name( $type ) . ']';
				break;
			case 'non_null':
				$output = '!' . self::normalize_type_name( $type );
				break;
		}

		return $output;
	}
}
