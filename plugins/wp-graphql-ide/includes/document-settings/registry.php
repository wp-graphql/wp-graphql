<?php
/**
 * Document Settings registry.
 *
 * Holds field descriptors registered via {@see register_graphql_document_setting_field()}
 * (see access-functions.php). Other modules in this directory read from the
 * registry to drive REST exposure, localization to JS, and storage dispatch.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

class Registry {

	/**
	 * @var Registry|null
	 */
	private static $instance = null;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	private $fields = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset the registry. Used by tests; not part of the public API.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Register a document settings field. Last-write-wins on duplicate names.
	 *
	 * @param string              $field_name
	 * @param array<string,mixed> $config
	 */
	public function register_field( string $field_name, array $config ): void {
		if ( '' === $field_name ) {
			return;
		}

		$config['name'] = $field_name;

		$this->fields[ $field_name ] = $this->normalize_config( $config );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_field( string $field_name ): ?array {
		return $this->fields[ $field_name ] ?? null;
	}

	/**
	 * Apply defaults so callers can omit non-essential keys.
	 *
	 * @param array<string,mixed> $config
	 *
	 * @return array<string,mixed>
	 */
	private function normalize_config( array $config ): array {
		$defaults = [
			'label'             => '',
			'desc'              => '',
			'type'              => 'text',
			'default'           => '',
			'options'           => [],
			'capability'        => 'edit_posts',
			'sanitize_callback' => null,
			'storage'           => [],
		];

		$config = array_merge( $defaults, $config );

		$config['storage'] = array_merge(
			[
				'kind'   => 'post_meta',
				'key'    => '',
				'multi'  => false,
				'unique' => false,
			],
			is_array( $config['storage'] ) ? $config['storage'] : []
		);

		return $config;
	}
}
