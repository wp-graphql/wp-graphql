<?php

class UtilsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {


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
}
