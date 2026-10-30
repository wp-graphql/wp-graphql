<?php

/**
 * Regression tests for wp-graphql/wp-graphql#4525: a field type registered with
 * register_graphql_acf_field_type() can ask for ACF's formatted value with the `format_value`
 * config option.
 *
 * Top-level fields resolve from the stored value, and only a fixed list of built-in field types
 * was formatted. A third-party field type that stores one shape and formats it into another (the
 * Font Awesome add-on stores the icon as JSON) resolved the stored JSON string at the top level,
 * so every field of its object type came back null, while the same field inside a repeater row,
 * which is formatted, resolved correctly.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4525
 */
class CustomFieldTypeFormatValueTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * The stored value of the test icon field: a JSON string, like the Font Awesome add-on stores.
	 *
	 * @var string
	 */
	private $stored_icon = '{"id":"medal","class":"fa-solid fa-medal"}';

	public function setUp(): void {
		parent::setUp();

		// A field type that stores its value as a JSON string and decodes it when ACF formats the
		// value, like the Font Awesome add-on. Added for each test, because hooks are restored
		// after every test.
		add_filter(
			'acf/format_value/type=wpgraphql_test_icon',
			static function ( $value ) {
				return is_string( $value ) && '' !== $value ? json_decode( $value, true ) : $value;
			}
		);

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_object_type(
					'WpgraphqlAcfTestIcon',
					[
						'description' => 'An icon resolved from a formatted field value',
						'fields'      => [
							'id'    => [ 'type' => 'String' ],
							'class' => [ 'type' => 'String' ],
						],
					]
				);
			}
		);
	}

	/**
	 * Queries the given selection on the published post's AcfTestGroup.
	 *
	 * @param string $selection The selection on the AcfTestGroup type.
	 */
	private function query_test_group( string $selection ): array {
		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $selection . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		return $actual['data']['post']['acfTestGroup'];
	}

	/**
	 * Registers the test icon field type with WPGraphQL for ACF.
	 *
	 * @param array $config Config to merge into the field type's config.
	 */
	private function register_icon_field_type( array $config = [] ): void {
		register_graphql_acf_field_type(
			'wpgraphql_test_icon',
			array_merge(
				[ 'graphql_type' => 'WpgraphqlAcfTestIcon' ],
				$config
			)
		);
	}

	/**
	 * The test icon field's config.
	 *
	 * @param array $config Config to merge into the field's config.
	 */
	private function icon_field( array $config = [] ): array {
		return array_merge(
			[
				'key'                => 'field_test_icon',
				'name'               => 'icon',
				'type'               => 'wpgraphql_test_icon',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'icon',
			],
			$config
		);
	}

	public function testTopLevelFieldResolvesFormattedValueWhenFieldTypeOptsIn(): void {
		$this->register_icon_field_type( [ 'format_value' => true ] );
		$this->register_acf_field( $this->icon_field() );
		update_field( 'field_test_icon', $this->stored_icon, $this->published_post->ID );

		$group = $this->query_test_group( 'icon { id class }' );

		$this->assertSame(
			[
				'id'    => 'medal',
				'class' => 'fa-solid fa-medal',
			],
			$group['icon']
		);
	}

	public function testTopLevelFieldResolvesStoredValueWhenFieldTypeDoesNotOptIn(): void {
		// Without the option, the field still resolves the stored value, as before.
		$this->register_icon_field_type( [ 'graphql_type' => 'String' ] );
		$this->register_acf_field( $this->icon_field() );
		update_field( 'field_test_icon', $this->stored_icon, $this->published_post->ID );

		$group = $this->query_test_group( 'icon' );

		$this->assertSame( $this->stored_icon, $group['icon'] );
	}

	public function testFieldInGroupResolvesFormattedValueWhenFieldTypeOptsIn(): void {
		$this->register_icon_field_type( [ 'format_value' => true ] );
		$this->register_acf_field(
			[
				'key'                => 'field_test_icon_group',
				'name'               => 'icon_group',
				'type'               => 'group',
				'graphql_field_name' => 'iconGroup',
				'sub_fields'         => [ $this->icon_field( [ 'key' => 'field_test_icon_in_group' ] ) ],
			]
		);
		update_field( 'field_test_icon_group', [ 'field_test_icon_in_group' => $this->stored_icon ], $this->published_post->ID );

		$group = $this->query_test_group( 'iconGroup { icon { id class } }' );

		$this->assertSame(
			[
				'id'    => 'medal',
				'class' => 'fa-solid fa-medal',
			],
			$group['iconGroup']['icon']
		);
	}

	public function testFieldInRepeaterResolvesFormattedValueWhenFieldTypeOptsIn(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$this->register_icon_field_type( [ 'format_value' => true ] );
		$this->register_acf_field(
			[
				'key'                => 'field_test_icon_repeater',
				'name'               => 'icon_repeater',
				'type'               => 'repeater',
				'graphql_field_name' => 'iconRepeater',
				'sub_fields'         => [ $this->icon_field( [ 'key' => 'field_test_icon_in_repeater' ] ) ],
			]
		);
		update_field( 'field_test_icon_repeater', [ [ 'field_test_icon_in_repeater' => $this->stored_icon ] ], $this->published_post->ID );

		$group = $this->query_test_group( 'iconRepeater { icon { id class } }' );

		$this->assertSame(
			[
				'id'    => 'medal',
				'class' => 'fa-solid fa-medal',
			],
			$group['iconRepeater'][0]['icon']
		);
	}

	public function testBuiltInFieldTypeCanOptOutOfFormatting(): void {
		// A select with the "label" return format resolves the label, because select values are
		// formatted. A field type config with `format_value` set to false resolves the stored value.
		add_filter(
			'wpgraphql/acf/field_type_config',
			static function ( $config, $field_type ) {
				if ( 'select' === $field_type->get_acf_field_type() ) {
					$config['format_value'] = false;
				}
				return $config;
			},
			10,
			2
		);

		$this->register_acf_field(
			[
				'key'                => 'field_test_select',
				'name'               => 'color',
				'type'               => 'select',
				'graphql_field_name' => 'color',
				'choices'            => [ 'red' => 'Red' ],
				'return_format'      => 'label',
			]
		);
		update_field( 'field_test_select', 'red', $this->published_post->ID );

		$group = $this->query_test_group( 'color' );

		$this->assertSame( [ 'red' ], $group['color'] );
	}
}

