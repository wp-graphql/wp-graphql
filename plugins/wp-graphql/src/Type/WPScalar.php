<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\CustomScalarType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPScalar
 *
 * @package WPGraphQL\Type
 *
 * phpcs:disable -- For phpstan type hinting.
 * @phpstan-import-type CustomScalarConfig from \GraphQL\Type\Definition\CustomScalarType
 *
 * @phpstan-type InputWPScalarConfig array{
 *   name: string,
 *   description?: string|null,
 *   serialize?: callable(mixed): mixed,
 *   parseValue: callable(mixed): mixed,
 *   parseLiteral: callable(\GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node, array<string, mixed>|null): mixed,
 *   astNode?: \GraphQL\Language\AST\ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\ScalarTypeDefinitionNode>|null,
 *   kind?:'scalar'|null,
 * }
 * @phpstan-type OutputWPScalarConfig array{
 *   name: string,
 *   description?: string|null,
 *   serialize: callable(mixed): mixed,
 *   parseValue?: callable(mixed): mixed,
 *   parseLiteral?: callable(\GraphQL\Language\AST\ValueNode&\GraphQL\Language\AST\Node, array<string, mixed>|null): mixed,
 *   astNode?: \GraphQL\Language\AST\ScalarTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\ScalarTypeDefinitionNode>|null,
 *   kind?:'scalar'|null,
 * }
 * @phpstan-type WPScalarConfig InputWPScalarConfig|OutputWPScalarConfig
 * phpcs:enable
 */
class WPScalar extends CustomScalarType {

	/**
	 * WPScalar constructor.
	 *
	 * @param array<string,mixed>              $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @phpstan-param WPScalarConfig $config
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$name           = $config['name'];
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config         = apply_filters( 'graphql_custom_scalar_config', $config, $type_registry );

		parent::__construct( $config );
	}
}
