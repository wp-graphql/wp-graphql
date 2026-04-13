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
