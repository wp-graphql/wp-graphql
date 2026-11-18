<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\Admin\Settings\SettingsRegistry;
use WPGraphQL\SmartCache\Document\Grant;

/**
 * The labels on the Saved Queries settings tab can be translated.
 */
class SettingsTranslationTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Registers the settings the way the settings screens do, with every Smart Cache string
	 * translated, and returns the fields of the Saved Queries tab.
	 *
	 * @return array<int,array<string,mixed>> The fields, keyed in registration order.
	 */
	private function persisted_queries_fields(): array {
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return 'wp-graphql-smart-cache' === $domain ? '[translated] ' . $text : $translation;
			},
			10,
			3
		);

		// Smart Cache builds its field configs, including the translated strings, when the
		// settings are registered, so register them again with the filter in place.
		do_action( 'graphql_register_settings' );

		$registry = new SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		return $registry->get_settings_fields()['graphql_persisted_queries_section'] ?? [];
	}

	/**
	 * The last registered field with a name, which is the one built with the filter in place.
	 *
	 * @param array<int,array<string,mixed>> $fields The fields.
	 * @param string                         $name   The field name.
	 *
	 * @return array<string,mixed>|null The field.
	 */
	private function find_field( array $fields, string $name ): ?array {
		$found = null;
		foreach ( $fields as $field ) {
			if ( $name === $field['name'] ) {
				$found = $field;
			}
		}
		return $found;
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function radioFieldsProvider(): array {
		return [
			'Allow/Deny Mode'                        => [ Grant::GLOBAL_SETTING_NAME ],
			'Who can save new queries automatically' => [ 'apq_auto_persist_mode' ],
		];
	}

	/**
	 * @dataProvider radioFieldsProvider
	 */
	public function testRadioOptionLabelsAreTranslatable( string $name ): void {
		$field = $this->find_field( $this->persisted_queries_fields(), $name );

		$this->assertNotNull( $field, "The $name field is not registered" );
		$this->assertNotEmpty( $field['options'] );
		foreach ( $field['options'] as $value => $label ) {
			$this->assertStringStartsWith( '[translated] ', $label, "The \"$value\" option of $name is not translatable" );
		}
	}
}
