<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;
use WPGraphQL\Registry\TypeAdapters\WithFieldsTrait;
use WPGraphQL\Registry\TypeAdapters\WithInterfacesTrait;

/**
 * Class WPObjectType
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-import-type ObjectConfig from \GraphQL\Type\Definition\ObjectType
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<ObjectConfig>

 * phpcs:enable
 *
 * @package WPGraphQL\Type
 * @since   0.0.5
 */
class WPObjectType extends ObjectType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<ObjectConfig> */
	use TypeAdapterTrait;
	use WithInterfacesTrait;
	use WithFieldsTrait;

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'object';
	}

	/**
	 * @deorecated @next-version
	 *
	 * @var array<string,mixed>|\WPGraphQL\Type\InterfaceType\Node $node_interface
	 * @since 0.0.5
	 */
	private static $node_interface;

	/**
	 * Prepares the configuration before passing it to the graphql-php parent constructor.
	 *
	 * @param array<string,mixed> $config
	 */
	public function __construct( array $config ) {
		$config['name'] = isset( $config['name'] ) ? ucfirst( $config['name'] ) : $this->inferName();

		$config = $this->prepare_config( $config );

		$this->validate_config( $config );

		/**
		 * Run an action when the WPObjectType is instantiating
		 *
		 * @param ObjectConfig                 $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The instance of the WPObjectType class
		 */
		do_action( 'graphql_wp_object_type', $config, $this );

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

		/**
		 * Set up the fields
		 *
		 * @return array<string, array<string, mixed>> $fields
		 */
		$config['fields'] = static function () use ( $config ) {
			$fields = is_callable( $config['fields'] ) ? $config['fields']() : $config['fields'];

			// If fields is still empty, set it to an empty array.
			$fields = is_array( $fields ) ? $fields : [];

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
					esc_html__( 'Object type "%s" must have a "fields" array or callable.', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}

	/**
	 * This returns the node_interface definition allowing
	 * WPObjectTypes to easily implement the node_interface
	 *
	 * @return array<string,mixed>|\WPGraphQL\Type\InterfaceType\Node
	 * @since 0.0.5
	 *
	 * @deprecated @next-version
	 */
	public static function node_interface() {
		_deprecated_function(
			__FUNCTION__,
			'@since next-version',
			esc_html__( 'Use DataSource::get_node_definition() instead.', 'wp-graphql' )
		);

		if ( null === self::$node_interface ) {
			$node_interface       = DataSource::get_node_definition();
			self::$node_interface = $node_interface['Node'];
		}

		return self::$node_interface;
	}
}
