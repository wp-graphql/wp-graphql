<?php

class WysiwygFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'wysiwyg';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_data_to_store():string {
		return 'some text';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testWysiwyg
		}
		';
	}

	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_expected_block_fragment_response() {
		return wpautop( $this->get_data_to_store() );
	}

	public function get_expected_fragment_response() {
		return wpautop( $this->get_data_to_store() );
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

	/**
	 * @return mixed
	 */
	public function get_expected_clone_value() {
		return wpautop( $this->get_clone_value_to_save() );
	}

	/**
	 * Appended to the content by a callback on the_content, the way plugins add sharing buttons
	 * or related posts to it.
	 */
	private const THE_CONTENT_MARKER = '<div class="the-content-marker"></div>';

	/**
	 * Queries the given selection on the published post's AcfTestGroup while a callback on
	 * the_content appends a marker.
	 *
	 * @param string $selection The selection on the AcfTestGroup type.
	 */
	private function query_test_group_with_the_content_marker( string $selection ): array {
		$append_marker = static function ( $content ) {
			return $content . self::THE_CONTENT_MARKER;
		};

		add_filter( 'the_content', $append_marker );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $selection . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		remove_filter( 'the_content', $append_marker );

		return $actual;
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#4562: ACF formats a WYSIWYG value with its own
	 * acf_the_content filter, and the value was then run through the_content as well, so whatever
	 * other plugins hook onto the_content was added to it.
	 */
	public function testWysiwygValueIsNotRunThroughTheContent(): void {
		$value     = "First paragraph\n\nSecond paragraph";
		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_the_content',
				'graphql_field_name' => 'wysiwygTheContent',
			]
		);
		update_field( $field_key, $value, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygTheContent' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygTheContent', wpautop( $value ) ),
			]
		);
	}

	/**
	 * The sub fields of a group field are resolved from their stored values, so a WYSIWYG sub
	 * field gets ACF's formatting there instead of the_content.
	 */
	public function testWysiwygInGroupIsFormattedWithoutTheContent(): void {
		$value     = "First paragraph\n\nSecond paragraph";
		$group_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_group',
				'type'               => 'group',
				'name'               => 'wysiwyg_group',
				'graphql_field_name' => 'wysiwygGroup',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_group_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $group_key, [ 'field_wysiwyg_group_wysiwyg' => $value ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygGroup { wysiwyg }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygGroup.wysiwyg', wpautop( $value ) ),
			]
		);
	}

	public function testWysiwygInRepeaterIsNotRunThroughTheContent(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$value        = "First paragraph\n\nSecond paragraph";
		$repeater_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_repeater',
				'type'               => 'repeater',
				'name'               => 'wysiwyg_repeater',
				'graphql_field_name' => 'wysiwygRepeater',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_repeater_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $repeater_key, [ [ 'field_wysiwyg_repeater_wysiwyg' => $value ] ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygRepeater { wysiwyg }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygRepeater.0.wysiwyg', wpautop( $value ) ),
			]
		);
	}

//	public function queryForPostByDatabaseId() {
//		return '
//		query GetPost($id:ID!){
//		  post(id:$id idType:DATABASE_ID) {
//		    __typename
//		    ...OnWithAcfAcfTestGroup {
//		      acfTestGroup {
//		        ' . $this->get_formatted_field_name(). '
//		      }
//		    }
//		  }
//		}
//		';
//	}

//	public function testQueryFieldOnPostReturnsExpectedValue() {
//
//		$value = 'test content';
//
//		update_field( $this->published_post->ID, $this->get_field_name(), $value );
//
//		$actual = $this->graphql([
//			'query' => $this->queryForPostByDatabaseId(),
//			'variables' => [
//				'id' => $this->published_post->ID,
//			],
//		]);
//
//		codecept_debug( $actual );
//
//	}

}
