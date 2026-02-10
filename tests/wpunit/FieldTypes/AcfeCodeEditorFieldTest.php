<?php

class AcfeCodeEditorFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_code_editor';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_data_to_store():string {
		return '<div>some html</div>';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeCodeEditor
		}
		';
	}

	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_expected_block_fragment_response() {
		return $this->get_block_data_to_store();
	}

	public function get_acf_clone_fragment():string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestAcfeCodeEditor
		}
		';
	}

}
