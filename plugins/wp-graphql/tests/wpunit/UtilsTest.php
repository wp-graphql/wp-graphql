<?php

class UtilsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Tests get_query_id generates consistent hashes and handles malformed queries.
	 */
	public function testGetQueryId() {

		$query_without_spaces   = '{posts{nodes{id,title}}}';
		$query_with_spaces      = '{ posts { nodes { id, title } } }';
		$query_with_line_breaks = '
		{
			posts {
				nodes {
					id
					title
				}
			}
		}';

		$id1 = \WPGraphQL\Utils\Utils::get_query_id( $query_without_spaces );
		$id2 = \WPGraphQL\Utils\Utils::get_query_id( $query_with_spaces );
		$id3 = \WPGraphQL\Utils\Utils::get_query_id( $query_with_line_breaks );

		codecept_debug(
			[
				$id1,
				$id2,
				$id3,
			]
		);

		// differently formatted versions of the same query should
		// all produce the same query_id
		$this->assertSame( $id1, $id2 );
		$this->assertSame( $id2, $id3 );
		$this->assertSame( $id1, $id3 );

		$invalid_query = '{ some { malformatted { query...';

		// if an invalid query is passed, we should get a null response
		$this->assertNull( \WPGraphQL\Utils\Utils::get_query_id( $invalid_query ) );
	}

	/**
	 * Tests map_enum_name_to_value maps registered enum names to their underlying values.
	 */
	public function testMapEnumNameToValue() {
		// An enum name maps back to its underlying value.
		$this->assertSame( 'post', \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'ContentTypeEnum', 'POST' ) );

		// A raw value passes through unchanged.
		$this->assertSame( 'post', \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'ContentTypeEnum', 'post' ) );

		// TaxonomyEnum derives names from graphql_single_name: post_tag is
		// exposed as TAG. The mapping must come from the registered enum
		// type, not from re-deriving a safe name from the value.
		$this->assertSame( 'post_tag', \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'TaxonomyEnum', 'TAG' ) );

		// Input matching neither a name nor a value returns null.
		$this->assertNull( \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'ContentTypeEnum', 'NOT_A_TYPE' ) );

		// A type that is not a registered enum returns null.
		$this->assertNull( \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'NotARegisteredEnum', 'POST' ) );
		$this->assertNull( \WPGraphQL\Utils\Utils::map_enum_name_to_value( 'Post', 'POST' ) );
	}

	/**
	 * Tests format_type_name_for_wp_template correctly formats template names and handles fallbacks.
	 */
	public function testFormatTypeNameForWpTemplate() {
		// Standard template name should be prefixed with Template_ and converted to PascalCase.
		$this->assertSame( 'Template_FullWidth', \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( 'Full Width', 'full-width.php' ) );

		// Template name that already contains the word "template" should not duplicate the prefix.
		$this->assertSame( 'PageTemplate', \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( 'Page Template', 'page-template.php' ) );

		// Template name starting with a number should be prefixed with Template_.
		$this->assertSame( 'Template_1Column', \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( '1 Column', '1-column.php' ) );

		// Non-ASCII template names should fall back to the file name.
		$this->assertSame( 'Template_CustomLayout', \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( 'বাংলা টেমপ্লেট', 'custom-layout.php' ) );

		// Empty name and empty file should return an empty string.
		$this->assertSame( '', \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( '', '' ) );
	}

	/**
	 * Tests format_graphql_name enforces valid characters and collapses leading underscores.
	 */
	public function testFormatGraphqlName() {
		// Valid alphanumeric name passes through.
		$this->assertSame( 'PostTitle', \WPGraphQL\Utils\Utils::format_graphql_name( 'PostTitle' ) );

		// Invalid characters are replaced by the default replacement character '_'.
		$this->assertSame( 'post_title', \WPGraphQL\Utils\Utils::format_graphql_name( 'post-title' ) );

		// Custom replacement character.
		$this->assertSame( 'postXtitle', \WPGraphQL\Utils\Utils::format_graphql_name( 'post-title', 'X' ) );

		// Multiple consecutive leading underscores are collapsed into a single leading underscore.
		$this->assertSame( '_customField', \WPGraphQL\Utils\Utils::format_graphql_name( '___customField' ) );

		// Empty string returns empty string.
		$this->assertSame( '', \WPGraphQL\Utils\Utils::format_graphql_name( '' ) );

		// Filter graphql_pre_format_name can override the formatting.
		$filter_callback = static function ( $formatted, $original ) {
			return 'Filtered_' . $original;
		};

		add_filter( 'graphql_pre_format_name', $filter_callback, 10, 2 );
		$this->assertSame( 'Filtered_myField', \WPGraphQL\Utils\Utils::format_graphql_name( 'myField' ) );
		remove_filter( 'graphql_pre_format_name', $filter_callback, 10 );
	}

	/**
	 * Tests format_field_name formats strings to camelCase and format_type_name to PascalCase.
	 */
	public function testFormatFieldNameAndTypeName() {
		// Hyphenated string converted to camelCase.
		$this->assertSame( 'postContent', \WPGraphQL\Utils\Utils::format_field_name( 'post-content' ) );

		// Underscored string converted to camelCase when $allow_underscores is false (default).
		$this->assertSame( 'postAuthorId', \WPGraphQL\Utils\Utils::format_field_name( 'post_author_id' ) );

		// Underscores preserved when $allow_underscores is true.
		$this->assertSame( 'post_author_id', \WPGraphQL\Utils\Utils::format_field_name( 'post_author_id', true ) );

		// Empty string returns empty string.
		$this->assertSame( '', \WPGraphQL\Utils\Utils::format_field_name( '' ) );

		// format_type_name formats to PascalCase.
		$this->assertSame( 'PostContent', \WPGraphQL\Utils\Utils::format_type_name( 'post-content' ) );
		$this->assertSame( 'PostAuthorId', \WPGraphQL\Utils\Utils::format_type_name( 'post_author_id' ) );
	}

	/**
	 * Tests prepare_date_response formats valid dates and handles zero dates.
	 */
	public function testPrepareDateResponse() {
		// A valid MySQL GMT date is formatted to RFC3339.
		$date_gmt = '2026-09-13 12:00:00';
		$expected = mysql_to_rfc3339( $date_gmt );
		$this->assertSame( $expected, \WPGraphQL\Utils\Utils::prepare_date_response( $date_gmt ) );

		// A zero date string returns null.
		$this->assertNull( \WPGraphQL\Utils\Utils::prepare_date_response( '0000-00-00 00:00:00' ) );

		// When the second $date parameter is passed, it takes precedence.
		$local_date     = '2026-09-13 08:00:00';
		$expected_local = mysql_to_rfc3339( $local_date );
		$this->assertSame( $expected_local, \WPGraphQL\Utils\Utils::prepare_date_response( $date_gmt, $local_date ) );
	}

	/**
	 * Tests get_node_type_from_id returns null for numeric IDs and resolves type from Relay global ID.
	 */
	public function testGetNodeTypeFromId() {
		// Numeric IDs are not Relay global IDs and return null.
		$this->assertNull( \WPGraphQL\Utils\Utils::get_node_type_from_id( 123 ) );
		$this->assertNull( \WPGraphQL\Utils\Utils::get_node_type_from_id( '456' ) );

		// Relay global ID returns the decoded node type.
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', 789 );
		$this->assertSame( 'post', \WPGraphQL\Utils\Utils::get_node_type_from_id( $global_id ) );

		// Invalid Relay ID format returns null.
		$this->assertNull( \WPGraphQL\Utils\Utils::get_node_type_from_id( 'invalid_global_id' ) );
	}

	/**
	 * Tests get_allowed_wp_kses_html returns expected allowed tags and attributes.
	 */
	public function testGetAllowedWpKsesHtml() {
		$allowed_html = \WPGraphQL\Utils\Utils::get_allowed_wp_kses_html();

		$this->assertIsArray( $allowed_html );
		$this->assertArrayHasKey( 'form', $allowed_html );
		$this->assertArrayHasKey( 'input', $allowed_html );
		$this->assertArrayHasKey( 'a', $allowed_html );
		$this->assertArrayHasKey( 'div', $allowed_html );
		$this->assertArrayHasKey( 'span', $allowed_html );

		// Check common attributes for anchor tag.
		$this->assertArrayHasKey( 'href', $allowed_html['a'] );
		$this->assertArrayHasKey( 'target', $allowed_html['a'] );
		$this->assertArrayHasKey( 'class', $allowed_html['a'] );
	}

	/**
	 * Tests get_post_preview_id resolves post ID or latest revision ID.
	 */
	public function testGetPostPreviewId() {
		$post_id = $this->factory()->post->create(
			[
				'post_title'   => 'Original Post',
				'post_content' => 'Original Content',
			]
		);

		// Without revisions, returns original post ID for both int and WP_Post object.
		$this->assertSame( $post_id, \WPGraphQL\Utils\Utils::get_post_preview_id( $post_id ) );
		$this->assertSame( $post_id, \WPGraphQL\Utils\Utils::get_post_preview_id( get_post( $post_id ) ) );

		// Create a revision.
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => 'Updated Content for Revision',
			]
		);
		$revision_id = wp_save_post_revision( $post_id );

		if ( is_int( $revision_id ) ) {
			$this->assertSame( $revision_id, \WPGraphQL\Utils\Utils::get_post_preview_id( $post_id ) );
			$this->assertSame( $revision_id, \WPGraphQL\Utils\Utils::get_post_preview_id( get_post( $post_id ) ) );
		}
	}
}
