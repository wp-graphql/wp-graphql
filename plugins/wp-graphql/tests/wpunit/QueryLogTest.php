<?php

class QueryLogTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function testGetQueryLogSupportsCoreNumericQueryRows() {
		global $wpdb;

		$previous_queries = $wpdb->queries;

		$wpdb->queries = [
			[ 'SELECT 2', 0.02, 'stack' ],
		];

		$query_log = new \WPGraphQL\Utils\QueryLog();
		$trace     = $query_log->get_query_log();

		$wpdb->queries = $previous_queries;

		$this->assertSame( 'SELECT 2', $trace['queries'][0]['sql'] );
		$this->assertSame( 0.02, $trace['queries'][0]['time'] );
		$this->assertSame( 'stack', $trace['queries'][0]['stack'] );
	}

	public function testGetQueryLogSupportsHyperDbStyleQueryRows() {
		global $wpdb;

		$previous_queries = $wpdb->queries;

		$wpdb->queries = [
			[
				'query'   => 'SELECT 1',
				'elapsed' => 0.01,
				'debug'   => 'caller',
			],
		];

		$query_log = new \WPGraphQL\Utils\QueryLog();
		$trace     = $query_log->get_query_log();

		$wpdb->queries = $previous_queries;

		$this->assertArrayHasKey( 'queries', $trace );
		$this->assertCount( 1, $trace['queries'] );
		$this->assertSame( 'SELECT 1', $trace['queries'][0]['sql'] );
		$this->assertSame( 0.01, $trace['queries'][0]['time'] );
		$this->assertSame( 'caller', $trace['queries'][0]['stack'] );
	}

	public function testGetQueryLogCalculatesAggregateCountsAndTime() {
		global $wpdb;

		$previous_queries = $wpdb->queries;

		$wpdb->queries = [
			[ 'SELECT 1', 0.01, 'stack-one' ],
			[ 'SELECT 2', 0.02, 'stack-two' ],
		];

		$query_log = new \WPGraphQL\Utils\QueryLog();
		$trace     = $query_log->get_query_log();

		$wpdb->queries = $previous_queries;

		$this->assertArrayHasKey( 'queryCount', $trace );
		$this->assertArrayHasKey( 'totalTime', $trace );
		$this->assertArrayHasKey( 'queries', $trace );
		$this->assertSame( 2, $trace['queryCount'] );
		$this->assertSame( 2, count( $trace['queries'] ) );
		$this->assertEqualsWithDelta( 0.03, $trace['totalTime'], 0.00001 );
	}

	public function testGetQueryLogDefaultsMalformedRows() {
		global $wpdb;

		$previous_queries = $wpdb->queries;

		$wpdb->queries = [
			'invalid-row',
			[ 'SELECT 3', 0.03, 'stack-three' ],
		];

		$query_log = new \WPGraphQL\Utils\QueryLog();
		$trace     = $query_log->get_query_log();

		$wpdb->queries = $previous_queries;

		$this->assertSame( 2, $trace['queryCount'] );
		$this->assertCount( 2, $trace['queries'] );
		$this->assertSame( '', $trace['queries'][0]['sql'] );
		$this->assertSame( 0.0, $trace['queries'][0]['time'] );
		$this->assertSame( '', $trace['queries'][0]['stack'] );
		$this->assertSame( 'SELECT 3', $trace['queries'][1]['sql'] );
		$this->assertSame( 0.03, $trace['queries'][1]['time'] );
		$this->assertEqualsWithDelta( 0.03, $trace['totalTime'], 0.00001 );
	}
}
