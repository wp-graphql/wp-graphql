<?php
/**
 * Class SampleTest
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Utils;

/**
 * Test the content class
 */
class UtilsUnitTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test graphql queries match hash, even with white space differences
	 */
	public function test_queries_with_whitespace_differences_are_same_hash() {
		$query = "{\n  contentNodes {\n    nodes {\n      uri\n    }\n  }\n}\n";

		$query_compact = '{ contentNodes { nodes { uri } } }';

		$query_pretty = '{
			contentNodes {
				nodes {
					uri
				}
			}
		}';

		$query_hash = Utils::generateHash( $query );

		$this->assertEquals( $query_hash, Utils::generateHash( $query_compact ) );
		$this->assertEquals( $query_hash, Utils::generateHash( $query_pretty ) );
	}

	/**
	 * Test graphql query with invalid string throws error
	 */
	public function test_query_hash_with_invalid_string() {
		$this->expectException( \GraphQL\Error\SyntaxError::class );
		$invalid_query = "{\n  contentNodes {\n    nodes {\n      uri";

		// @throws SyntaxError
		Utils::generateHash( $invalid_query );
	}

}
