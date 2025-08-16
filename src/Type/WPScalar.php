<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\CustomScalarType;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;

/**
 * Class WPScalar
 *
 * @package WPGraphQL\Type
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-import-type CustomScalarConfig from \GraphQL\Type\Definition\CustomScalarType
 *
 * @phpstan-type WPScalarConfig array{
 *   description?: string|callable():string|null,
 *   serialize?: callable(mixed): mixed,
 *   parseValue?: callable(mixed): mixed,
 *   parseLiteral?: callable(
 * \GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node, array<string, mixed>|null): mixed,
 *   astNode?: \GraphQL\Language\AST\ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\ScalarTypeExtensionNode>|null,
 *   kind?: 'scalar'
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<CustomScalarConfig>
 *
 * phpcs:enable
 */
class WPScalar extends CustomScalarType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<CustomScalarConfig> */
	use TypeAdapterTrait;

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
		return 'custom_scalar';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Called by ::prepare_config()
	 */
	public function prepare( array $config ): array {
		// noop.
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
		if (
			// Output scalars need a serialize method.
			( ! isset( $config['serialize'] ) || ! is_callable( $config['serialize'] ) ) &&
			// Input scalars need a parseValue and parseLiteral method.
			( ! isset( $config['parseValue'] ) || ! is_callable( $config['parseValue'] ) ||
				! isset( $config['parseLiteral'] ) || ! is_callable( $config['parseLiteral'] ) )
		) {
			throw new \GraphQL\Error\UserError(
				sprintf(
					// Translators: %s is the name of the scalar type.
					esc_html__( 'Scalar type "%s" must either implement serialize() (if it is an output scalar) or both parseValue() and parseLiteral() (if it is an input scalar).', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}
}
