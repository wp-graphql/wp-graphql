<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;

/**
 * Class WPUnionType
 *
 * Union Types should extend this class to take advantage of the helper methods
 * and consistent filters.
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-import-type ResolveType from \GraphQL\Type\Definition\AbstractType
 * @phpstan-import-type UnionConfig from \GraphQL\Type\Definition\UnionType
 * @phpstan-import-type ObjectTypeReference from \GraphQL\Type\Definition\UnionType
 *
 * @phpstan-type WPUnionConfig array{
 *   description?: string|callable():string|null,
 *   typeNames: string[],
 *   resolveType?: ResolveType|null,
 *   astNode?: \GraphQL\Language\AST\UnionTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\UnionTypeExtensionNode>|null,
 *   kind?: 'union'
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<UnionConfig>
 *
 * phpcs:enable
 *
 * @package WPGraphQL\Type\Union
 * @since   0.0.30
 */
class WPUnionType extends UnionType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<UnionConfig> */
	use TypeAdapterTrait;

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'union';
	}

	/**
	 * Prepares the configuration before passing it to the graphql-php parent constructor.
	 *
	 * @param array<string,mixed> $config
	 *
	 * @since 0.0.30
	 */
	public function __construct( array $config ) {
		$config = $this->prepare_config( $config );

		$this->validate_config( $config );

		do_action( 'graphql_wp_union_type', $config, $this );
		parent::__construct( $config );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Called by ::prepare_config()
	 */
	public function prepare( array $config ): array {
		$config['types'] = static function () use ( $config ): array {
			// Bail if no types are provided.
			if ( empty( $config['typeNames'] ) || ! is_array( $config['typeNames'] ) ) {
				return [];
			}

			return self::prepare_types( $config['typeNames'] );
		};

		// Wrap the resolveType in a callable and apply filters.
		$config['resolveType'] = static function ( $source, $context, $info ) use ( $config ) {
			return self::prepare_type_resolver( $source, $context, $info, $config );
		};

		// Refilter the types... because(?).
		$types           = apply_filters( 'graphql_union_possible_types', $config['types'], $config, $this );
		$config['types'] = $types;

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
		if ( empty( $config['types'] ) || ( ! is_array( $config['types'] ) && ! is_callable( $config['types'] ) ) ) {
			throw new \GraphQL\Error\UserError(
				sprintf(
					// translators: %s is the type name.
					esc_html__( 'Union type "%s" must have a "types" array or callable.', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}

	/**
	 * Prepares the types for the config.
	 *
	 * @param string[] $type_names
	 *
	 * @return \GraphQL\Type\Definition\ObjectType[]
	 */
	protected static function prepare_types( array $type_names ): array {
		$prepared_types = [];
		$type_registry  = \WPGraphQL::get_type_registry();

		foreach ( $type_names as $type_name ) {
			// Skip excluded types.
			if ( in_array( strtolower( $type_name ), $type_registry->get_excluded_types(), true ) ) {
				continue;
			}

			$type = $type_registry->get_type( $type_name );
			if ( ! $type instanceof ObjectType ) {
				graphql_debug(
					sprintf(
					// translators: %s is the type name.
						esc_html__( 'Type "%s" is not an ObjectType and cannot be used in a Union.', 'wp-graphql' ),
						esc_html( $type_name )
					)
				);

				continue;
			}

			$prepared_types[] = $type;
		}

		return $prepared_types;
	}
}
