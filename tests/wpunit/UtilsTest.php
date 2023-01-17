<?php

class UtilsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {


	public function testGetQueryId() {


		$query_without_spaces = '{posts{nodes{id,title}}}';
		$query_with_spaces = '{ posts { nodes { id, title } } }';
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

		codecept_debug([
			$id1,
			$id2,
			$id3
		]);

		// differently formatted versions of the same query should
		// all produce the same query_id
		$this->assertSame( $id1, $id2 );
		$this->assertSame( $id2, $id3 );
		$this->assertSame( $id1, $id3 );

		$invalid_query = '{ some { malformatted { query...';

		// if an invalid query is passed, we should get a null response
		$this->assertNull( \WPGraphQL\Utils\Utils::get_query_id( $invalid_query ) );

	}
}
