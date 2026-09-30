<?php

/**
 * Connection fields (page link, image, file, etc) nested in the rows of repeater,
 * flexible content and group fields should resolve from the stored ID, regardless
 * of the return format ACF would format the value with.
 *
 * @see https://github.com/wp-graphql/wpgraphql-acf/issues/198
 */
class NestedConnectionFieldsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Each test registers its own repeater, as the schema keeps the type of a repeater registered by an earlier test.
	 *
	 * @param string $name       The name of the repeater
	 * @param array  $sub_fields The sub fields of the repeater
	 *
	 * @return string The key of the repeater
	 */
	protected function register_repeater( string $name, array $sub_fields ): string {
		return $this->register_acf_field(
			[
				'key'                => uniqid( 'field_nested_repeater_', true ),
				'name'               => $name,
				'type'               => 'repeater',
				'graphql_field_name' => \WPGraphQL\Utils\Utils::format_field_name( $name ),
				'sub_fields'         => $sub_fields,
			]
		);
	}

	/**
	 * @param string $fragment The selection of fields on the AcfTestGroup type
	 *
	 * @return array
	 */
	protected function query_post( string $fragment ): array {
		return $this->graphql(
			[
				'query'     => '
				query GetPost( $id: ID! ) {
				  post( id: $id, idType: DATABASE_ID ) {
				    acfTestGroup {
				      ' . $fragment . '
				    }
				  }
				}
				',
				'variables' => [
					'id' => $this->published_post->ID,
				],
			]
		);
	}

	public function testPageLinkInRepeaterResolves(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_page_link',
			[
				[
					'key'                => 'field_nested_page_link',
					'name'               => 'page_link',
					'type'               => 'page_link',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'pageLink',
					'post_type'          => [],
					'allow_null'         => 1,
					'multiple'           => 0,
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[ 'field_nested_page_link' => $this->published_page->ID ],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithPageLink { pageLink { nodes { databaseId } } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithPageLink.0.pageLink.nodes.0.databaseId', $this->published_page->ID ),
			]
		);
	}

	public function testImageWithUrlReturnFormatInRepeaterResolves(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_url_image',
			[
				[
					'key'                => 'field_nested_url_image',
					'name'               => 'image',
					'type'               => 'image',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'image',
					'return_format'      => 'url',
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[ 'field_nested_url_image' => $this->imageId ],
				[ 'field_nested_url_image' => $this->imageId_2 ],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithUrlImage { image { node { databaseId } } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithUrlImage.0.image.node.databaseId', $this->imageId ),
				$this->expectedField( 'post.acfTestGroup.repeaterWithUrlImage.1.image.node.databaseId', $this->imageId_2 ),
			]
		);
	}

	public function testImageWithIdReturnFormatInRepeaterStillResolves(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_id_image',
			[
				[
					'key'                => 'field_nested_id_image',
					'name'               => 'image',
					'type'               => 'image',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'image',
					'return_format'      => 'id',
				],
				[
					'key'                => 'field_nested_text',
					'name'               => 'text',
					'type'               => 'text',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'text',
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[
					'field_nested_id_image' => $this->imageId,
					'field_nested_text'     => 'row text',
				],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithIdImage { text image { node { databaseId } } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithIdImage.0.text', 'row text' ),
				$this->expectedField( 'post.acfTestGroup.repeaterWithIdImage.0.image.node.databaseId', $this->imageId ),
			]
		);
	}

	public function testImageWithUrlReturnFormatInGroupInRepeaterResolves(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_group',
			[
				[
					'key'                => 'field_nested_group',
					'name'               => 'group',
					'type'               => 'group',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'group',
					'sub_fields'         => [
						[
							'key'                => 'field_nested_group_image',
							'name'               => 'image',
							'type'               => 'image',
							'show_in_graphql'    => 1,
							'graphql_field_name' => 'image',
							'return_format'      => 'url',
						],
					],
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[
					'field_nested_group' => [ 'field_nested_group_image' => $this->imageId ],
				],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithGroup { group { image { node { databaseId } } } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithGroup.0.group.image.node.databaseId', $this->imageId ),
			]
		);
	}

	public function testPageLinkInFlexibleContentResolves(): void {
		$this->register_acf_field(
			[
				'key'                => 'field_nested_flex',
				'name'               => 'nested_flex',
				'type'               => 'flexible_content',
				'graphql_field_name' => 'nestedFlex',
				'layouts'            => [
					'layout_gallery' => [
						'key'        => 'layout_gallery',
						'name'       => 'gallery',
						'label'      => 'Gallery',
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'                => 'field_nested_flex_show_on',
								'name'               => 'show_on',
								'type'               => 'page_link',
								'show_in_graphql'    => 1,
								'graphql_field_name' => 'showOn',
								'post_type'          => [],
								'allow_null'         => 1,
								'multiple'           => 1,
								'parent_layout'      => 'layout_gallery',
							],
						],
					],
				],
			]
		);

		update_field(
			'field_nested_flex',
			[
				[
					'acf_fc_layout'             => 'gallery',
					'field_nested_flex_show_on' => [ $this->published_page->ID, $this->published_post->ID ],
				],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'nestedFlex { __typename ... on AcfTestGroupNestedFlexGalleryLayout { showOn { nodes { databaseId } } } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode( 'post.acfTestGroup.nestedFlex.0.showOn.nodes', [ 'databaseId' => $this->published_page->ID ] ),
				$this->expectedNode( 'post.acfTestGroup.nestedFlex.0.showOn.nodes', [ 'databaseId' => $this->published_post->ID ] ),
			]
		);
	}
}
