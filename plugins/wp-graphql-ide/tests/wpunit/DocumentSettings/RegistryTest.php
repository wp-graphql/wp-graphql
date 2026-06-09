<?php
/**
 * Tests for the Document Settings registry and its public access function.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace Tests\WPGraphQLIDE\DocumentSettings;

use WPGraphQLIDE\DocumentSettings\Registry;

class RegistryTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		Registry::reset();
		// Re-register the four built-in fields so each test starts with the
		// same baseline.
		\WPGraphQLIDE\DocumentSettings\register_built_in_fields();
	}

	public function test_built_in_fields_are_registered() {
		$fields = Registry::instance()->get_fields();

		$this->assertArrayHasKey( 'description', $fields );
		$this->assertArrayHasKey( 'aliases', $fields );
		$this->assertArrayHasKey( 'maxAgeHeader', $fields );
		$this->assertArrayHasKey( 'grant', $fields );
	}

	public function test_register_field_through_public_api() {
		register_graphql_document_setting_field(
			'customField',
			[
				'label'   => 'Custom',
				'type'    => 'text',
				'storage' => [
					'kind' => 'post_meta',
					'key'  => '_custom_field',
				],
			]
		);

		$field = Registry::instance()->get_field( 'customField' );

		$this->assertNotNull( $field );
		$this->assertSame( 'Custom', $field['label'] );
		$this->assertSame( 'text', $field['type'] );
		$this->assertSame( 'post_meta', $field['storage']['kind'] );
	}

	public function test_register_field_normalizes_missing_keys() {
		register_graphql_document_setting_field(
			'sparseField',
			[
				'storage' => [ 'kind' => 'post_meta', 'key' => '_sparse' ],
			]
		);

		$field = Registry::instance()->get_field( 'sparseField' );

		$this->assertSame( 'text', $field['type'] );
		$this->assertSame( 'edit_posts', $field['capability'] );
		$this->assertSame( '', $field['default'] );
		$this->assertSame( [], $field['options'] );
	}

	public function test_duplicate_registration_overwrites() {
		register_graphql_document_setting_field( 'overwriteMe', [
			'label' => 'First',
			'type'  => 'text',
		] );
		register_graphql_document_setting_field( 'overwriteMe', [
			'label' => 'Second',
			'type'  => 'textarea',
		] );

		$field = Registry::instance()->get_field( 'overwriteMe' );

		$this->assertSame( 'Second', $field['label'] );
		$this->assertSame( 'textarea', $field['type'] );
	}

	public function test_empty_field_name_is_ignored() {
		register_graphql_document_setting_field( '', [ 'label' => 'Nope' ] );

		$this->assertNull( Registry::instance()->get_field( '' ) );
	}
}
