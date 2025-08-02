<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;
use WPGraphQL\Registry\TypeAdapters\WithFieldsTrait;
use WPGraphQL\Registry\TypeAdapters\WithInterfacesTrait;

/**
 * Class WPInterface
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-import-type ResolveType from \GraphQL\Type\Definition\AbstractType
 * @phpstan-import-type FieldsConfig from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type InterfaceConfig from \GraphQL\Type\Definition\InterfaceType
 * @phpstan-import-type InterfaceTypeReference from \GraphQL\Type\Definition\InterfaceType
 *
 * @phpstan-type WPInterfaceConfig array{
 *   description?: string|callable():string|null,
 *   fields: FieldsConfig,
 *   interfaces?: iterable<InterfaceTypeReference>|callable(): iterable<InterfaceTypeReference>,
 *   resolveType?: ResolveType|null,
 *   astNode?: \GraphQL\Language\AST\InterfaceTypeDefinitionNode|null,
 *   extensionASTNodes?: array<\GraphQL\Language\AST\InterfaceTypeExtensionNode>|null
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<InterfaceConfig>

 * phpcs:enable
 */
class WPInterfaceType extends InterfaceType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<InterfaceConfig> */
	use TypeAdapterTrait;
	use WithInterfacesTrait;
	use WithFieldsTrait;

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'interface';
	}

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
	 *
	 * Called by ::prepare_config()
	 */
	public function prepare( array $config ): array {
		// Do the interfaces first, so we can inherit their fields.
		$config['interfaces'] = $this->prepare_interfaces( $config );

		$config['fields'] = static function () use ( $config ) {
			$fields = is_callable( $config['fields'] ) ? $config['fields']() : $config['fields'];

			// If fields is still empty, set it to an empty array.
			$fields = is_array( $fields ) ? $fields : [];

			$fields = self::prepare_fields( $fields, $config );

			// Sort the fields alphabetically by key. This makes reading through docs much easier.
			ksort( $fields );

			return $fields;
		};

		// Wrap the resolveType in a callable and apply filters.
		$config['resolveType'] = static function ( $source, $context, $info ) use ( $config ) {
			return self::prepare_type_resolver( $source, $context, $info, $config );
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
					esc_html__( 'Interface type "%s" must have a "fields" array or callable.', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}
}
