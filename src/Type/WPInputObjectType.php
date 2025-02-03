<?php
namespace WPGraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Utils\Utils;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPInputObjectType
 *
 * Input types should extend this class to take advantage of the helper methods for formatting
 * and adding consistent filters.
 *
 * @phpstan-import-type InputObjectConfig from \GraphQL\Type\Definition\InputObjectType
 * @phpstan-import-type FieldConfig from \GraphQL\Type\Definition\InputObjectType
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class WPInputObjectType extends InputObjectType {

	/**
	 * WPInputObjectType constructor.
	 *
	 * @param array<string,mixed>              $config The Config to set up an Input Type
	 *
	 * @phpstan-param InputObjectConfig $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry instance
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$name           = $config['name'] ?? $this->inferName();
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		if ( array_key_exists( 'fields', $config ) && is_array( $config['fields'] ) ) {
			$config['fields'] = function () use ( $config, $type_registry ) {
				$fields = $this->prepare_fields( $config['fields'], $config['name'], $config, $type_registry );
				$fields = $type_registry->prepare_fields( $fields, $config['name'] );

				return $fields;
			};
		}

		parent::__construct( $config );
	}

	/**
	 * Prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array<string,array<string,mixed>> $fields
	 * @param string                            $type_name
	 * @param array<string,mixed>               $config
	 * @param \WPGraphQL\Registry\TypeRegistry  $type_registry
	 * @return array<string,array<string,mixed>>
	 *
	 * @phpstan-param array<string,FieldConfig> $fields
	 * @phpstan-return array<string,FieldConfig>
	 * @since 0.0.5
	 */
	public function prepare_fields( array $fields, string $type_name, array $config, TypeRegistry $type_registry ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array<string,FieldConfig>         $fields        The array of fields for the object config
		 * @param string                            $type_name     The name of the object type
		 * @param array<string,mixed>               $config        The type config
		 * @param \WPGraphQL\Registry\TypeRegistry  $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( 'graphql_input_fields', $fields, $type_name, $config, $type_registry );

		/**
		 * Filter once with lowercase, once with uppercase for Back Compat.
		 */
		$lc_type_name = lcfirst( $type_name );
		$uc_type_name = ucfirst( $type_name );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array<string,FieldConfig> $fields        The array of fields for the object config
		 * @param \WPGraphQL\Registry\TypeRegistry  $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields, $type_registry );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array<string,FieldConfig>            $fields        The array of fields for the object config
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( "graphql_{$uc_type_name}_fields", $fields, $type_registry );

		/**
		 * Sort the fields alphabetically by key. This makes reading through docs much easier
		 *
		 * @since 0.0.2
		 */
		ksort( $fields );

		return $fields;
	}

	/**
	 * Validates type config and throws if one of type options is invalid.
	 * Note: this method is shallow, it won't validate object fields and their arguments.
	 *
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public function assertValid(): void {
		Utils::assertValidName( $this->name );

		$fields = $this->config['fields'] ?? null;
		if ( is_callable( $fields ) ) {
			$fields = $fields();
		}

		/**
		 * @todo: This is a temporary fix to prevent the InvariantViolation from being thrown
		 *    when the fields are not iterable. This is a temporary fix until the issue is resolved.
		 */
		if ( ! is_iterable( $fields ) && ! $fields instanceof InputObjectField ) {
			$invalidFields = Utils::printSafe( $fields );

			// translators: %1$s is the name of the type and %2$s is the invalid fields
			throw new InvariantViolation( sprintf( esc_html__( '%1$s fields must be an iterable or a callable which returns an iterable, got: %2$s.', 'wp-graphql' ), esc_html( $this->name ), esc_html( $invalidFields ) ) );
		}

		$resolvedFields = $this->getFields();

		foreach ( $resolvedFields as $field ) {
			$field->assertValid( $this );
		}
	}
}
