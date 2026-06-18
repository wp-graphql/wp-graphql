<?php

class OembedFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		add_filter( 'pre_oembed_result', [ $this, 'mock_oembed_result' ], 10, 3 );
	}

	public function mock_oembed_result( $result, $url, $args ) {
		return '<div>mock oembed content</div>';
	}


	/**
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_oembed_result', [ $this, 'mock_oembed_result' ], 10 );
		parent::tearDown();
	}

	public function get_clone_value_to_save(): string {
		return 'https://twitter.com/wpgraphql/status/1115652591705190400';
	}

	public function get_field_type(): string {
		return 'oembed';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

	public function get_expected_clone_value() {
		return '<div>mock oembed content</div>';
	}

}
