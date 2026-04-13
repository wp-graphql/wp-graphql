<?php

class AcfeAdvancedLinkFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_advanced_link';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'INTERFACE';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'ACFE_AdvancedLink';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeAdvancedLink {
		    ... on ACFE_AdvancedLink_Url {
		      __typename
              url
              linkText
              shouldOpenInNewWindow
            }
		  }
		}
		';
	}

	public function get_data_to_store() {
		return [
			'type' => 'url',
			'value' => 'https://wpgraphql.com',
			'title' => 'WPGraphQL',
			'target' => "1",
		];
	}

	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_expected_block_fragment_response() {
		return [
			'__typename' => 'ACFE_AdvancedLink_Url',
			'url'        => 'https://wpgraphql.com',
			'linkText'   => 'WPGraphQL',
			'shouldOpenInNewWindow' => true,
		];
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment():string {
		return '
			fragment AcfTestGroupFragment on AcfTestGroup {
				clonedTestAcfeAdvancedLink {
				  __typename
			      linkText
			      shouldOpenInNewWindow
			      ... on ACFE_AdvancedLink_Url {
			        url
			      }
			      ... on ACFE_AdvancedLink_TermNode {
			        term {
			          uri
			        }
			      }
			      ... on ACFE_AdvancedLink_ContentNode {
			        contentNode {
			          uri
			        }
			      }
				}
			}
		';
	}

	/**
	 * @return array
	 */
	public function get_clone_value_to_save(): array {
		return [
			'type'   => 'url',
			'url'    => 'https://wpgraphql.com',
			'title'  => 'WPGraphQL.com',
			'target' => 1
		];
	}

	/**
	 * @return array
	 */
	public function get_expected_clone_value(): array {
		return [
			"__typename"            => "ACFE_AdvancedLink_Url",
			"linkText"              => 'WPGraphQL.com',
			'shouldOpenInNewWindow' => true,
			'url'                   => 'https://wpgraphql.com'
		];
	}

}
