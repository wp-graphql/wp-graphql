<?php
namespace WPGraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Utils\Utils;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;
use WPGraphQL\Registry\TypeAdapters\WithFieldsTrait;

/**
 * Class WPInputObjectType
 *
 * Input types should extend this class to take advantage of the helper methods for formatting
 * and adding consistent filters.
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-import-type InputObjectConfig from \GraphQL\Type\Definition\InputObjectType
 * @phpstan-import-type FieldConfig from \GraphQL\Type\Definition\InputObjectType
 *
 * @phpstan-type WPInputObjectTypeConfig array{
 *   description?: string|callable():string|null,
 *   fields: iterable<FieldConfig>|callable(): iterable<FieldConfig>,
 *   parseValue?: callable(array<string, mixed>): mixed,
 *   astNode?: \GraphQL\Language\AST\InputObjectTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\InputObjectTypeExtensionNode>|null,
 *   kind?: 'input'
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<InputObjectConfig>
 *
 * phpcs:enable
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class WPInputObjectType extends InputObjectType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<InputObjectConfig> */
	use TypeAdapterTrait;
	use WithFieldsTrait;

	/**
	 * Prepares the configuration before passing it to the graphql-php parent constructor.
	 *
	 * @param array<string,mixed> $config
	 */
	public function __construct( array $config ) {
		$config = $this->prepare_config( $config );

		$this->validate_config( $config );

		parent::__construct( $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'input';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Called by ::prepare_config()
	 */
	public function prepare( array $config ): array {
		if ( ! isset( $config['fields'] ) ) {
			return $config;
		}

		// Ensure the 'fields' key is set as a callable if it is not already.
		$config['fields'] = static function () use ( $config ) {
			$fields = is_callable( $config['fields'] ) ? $config['fields']() : $config['fields'];

			$fields = self::prepare_fields( $fields, $config );

			// Sort the fields alphabetically by key. This makes reading through docs much easier.
			ksort( $fields );
			return $fields;
		};

		return $config;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Called by ::validate_config()
	 *
	 * @throws \GraphQL\Error\UserError If the configuration is invalid.
	 */
	public function validate( array $config ): void {
		// Values must be array or callable.
		if ( ! isset( $config['fields'] ) || ( ! is_array( $config['fields'] ) && ! is_callable( $config['fields'] ) ) ) {
			throw new \GraphQL\Error\UserError(
				sprintf(
					// translators: %s is the type name.
					esc_html__( 'Input object type "%s" must have a "fields" array or callable.', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Validates type config and throws if one of type options is invalid.
	 *
	 * This method is overridden from the parent GraphQL\Type\Definition\InputObjectType class
	 * to support WPGraphQL's filter system. The parent implementation only accepts iterables
	 * for fields, but WPGraphQL's filters (like graphql_input_fields and graphql_{type}_fields)
	 * might return a single InputObjectField instance. This override allows for both iterables
	 * and single InputObjectField instances to be valid field values.
	 *
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public function assertValid(): void {
		Utils::assertValidName( $this->name );

		$fields = $this->config['fields'] ?? null;
		if ( is_callable( $fields ) ) {
			$fields = $fields();
		}

		// Validate that $fields is either an iterable or an InputObjectField
		if ( ! is_iterable( $fields ) && ! $fields instanceof InputObjectField ) {
			$invalidFields = Utils::printSafe( $fields );

			throw new InvariantViolation(
				sprintf(
					// translators: %1$s is the name of the type and %2$s is the invalid fields
					esc_html__( '%1$s fields must be an array, an iterable, or an InputObjectField instance, got: %2$s', 'wp-graphql' ),
					esc_html( $this->name ),
					esc_html( $invalidFields )
				)
			);
		}

		$resolvedFields = $this->getFields();

		foreach ( $resolvedFields as $field ) {
			$field->assertValid( $this );
		}
	}
}
