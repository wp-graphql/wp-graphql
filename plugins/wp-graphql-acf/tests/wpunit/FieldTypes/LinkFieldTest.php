<?php

class LinkFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'link';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfLink';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  clonedTestLink {
		    __typename
		    title
		    url
		    target
	      }
		}
		';
	}

	public function get_clone_value_to_save():array {
		return [
			'title' => 'test',
			'url' => 'https://example.com/test',
			'target' => "_blank",
		];
	}

	public function get_expected_clone_value():array {
		return [
			'__typename' => 'AcfLink',
			'title' => 'test',
			'url' => 'https://example.com/test',
			'target' => "_blank",
		];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testLink {
		    __typename
		    title
		    url
		    target
	      }
		}
		';
	}

	public function get_block_data_to_store() {
		return [
			'__typename' => 'AcfLink',
			'title' => 'test',
			'url' => 'https://example.com/test',
			'target' => "_blank",
		];
	}

	public function get_expected_block_fragment_response() {
		return [
			'__typename' => 'AcfLink',
			'title' => 'test',
			'url' => 'https://example.com/test',
			'target' => "_blank",
		];
	}

	/**
	 * Registers a repeater with a single link sub field, and stores one row for the published post.
	 *
	 * @param string $return_format The link field's return format ("url" or "array").
	 *
	 * @return string The GraphQL field name of the repeater.
	 */
	private function register_repeater_with_link( string $return_format ): string {
		$name       = 'repeater_link_' . $return_format;
		$link_key   = uniqid( 'field_repeater_link_', true );
		$field_name = \WPGraphQL\Utils\Utils::format_field_name( $name );

		$repeater_key = $this->register_acf_field(
			[
				'key'                => uniqid( 'field_repeater_', true ),
				'name'               => $name,
				'type'               => 'repeater',
				'graphql_field_name' => $field_name,
				'sub_fields'         => [
					[
						'key'                => $link_key,
						'name'               => 'link',
						'type'               => 'link',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'link',
						'return_format'      => $return_format,
					],
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[
					$link_key => [
						'title'  => 'Example',
						'url'    => 'https://example.com/',
						'target' => '_blank',
					],
				],
			],
			$this->published_post->ID
		);

		return $field_name;
	}

	/**
	 * Queries the link sub field of the first row of a field on the published post's AcfTestGroup.
	 *
	 * @param string $selection The selection on the AcfTestGroup type.
	 */
	private function query_test_group( string $selection ): array {
		return $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $selection . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#4541: inside a row, ACF hands a link field with the
	 * "url" return format to the resolver as a URL string, but the AcfLink type reads title, url and
	 * target from the stored array, so all three resolved as null.
	 */
	public function testLinkWithUrlReturnFormatInRepeaterResolves(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$field_name = $this->register_repeater_with_link( 'url' );
		$actual     = $this->query_test_group( $field_name . ' { link { title url target } }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.title', 'Example' ),
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.url', 'https://example.com/' ),
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.target', '_blank' ),
			]
		);
	}

	public function testLinkWithArrayReturnFormatInRepeaterStillResolves(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$field_name = $this->register_repeater_with_link( 'array' );
		$actual     = $this->query_test_group( $field_name . ' { link { title url target } }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.title', 'Example' ),
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.url', 'https://example.com/' ),
				$this->expectedField( 'post.acfTestGroup.' . $field_name . '.0.link.target', '_blank' ),
			]
		);
	}

	public function testLinkWithUrlReturnFormatInFlexibleContentResolves(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$this->register_acf_field(
			[
				'key'                => 'field_link_flex',
				'name'               => 'link_flex',
				'type'               => 'flexible_content',
				'graphql_field_name' => 'linkFlex',
				'layouts'            => [
					'layout_link_flex_cta' => [
						'key'        => 'layout_link_flex_cta',
						'name'       => 'cta',
						'label'      => 'CTA',
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'                => 'field_link_flex_cta_link',
								'name'               => 'cta_link',
								'type'               => 'link',
								'show_in_graphql'    => 1,
								'graphql_field_name' => 'ctaLink',
								'return_format'      => 'url',
								'parent_layout'      => 'layout_link_flex_cta',
							],
						],
					],
				],
			]
		);

		update_field(
			'field_link_flex',
			[
				[
					'acf_fc_layout'            => 'cta',
					'field_link_flex_cta_link' => [
						'title'  => 'Example',
						'url'    => 'https://example.com/',
						'target' => '_blank',
					],
				],
			],
			$this->published_post->ID
		);

		$actual = $this->query_test_group( 'linkFlex { ... on AcfTestGroupLinkFlexCtaLayout { ctaLink { title url target } } }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.linkFlex.0.ctaLink.title', 'Example' ),
				$this->expectedField( 'post.acfTestGroup.linkFlex.0.ctaLink.url', 'https://example.com/' ),
				$this->expectedField( 'post.acfTestGroup.linkFlex.0.ctaLink.target', '_blank' ),
			]
		);
	}
}
